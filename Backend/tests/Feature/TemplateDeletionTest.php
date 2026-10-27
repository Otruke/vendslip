<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MutabilityMode;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Template;
use App\Models\User;
use Tests\TestCase;

final class TemplateDeletionTest extends TestCase
{
    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::SUPER_ADMIN->value);

        return $user;
    }

    private function customTemplate(string $name = 'Bold Lagos'): Template
    {
        $this->actingAs($this->superAdmin());
        $this->post(route('admin.templates.store'), [
            'name' => $name, 'description' => 'Test', 'min_plan_tier' => 'FREE', 'sort_order' => 5,
            'supports_receipt' => '1', 'supports_invoice' => '1', 'is_active' => '1',
            'html' => '<h1>{{business_name}}</h1>{{items_table}}{{totals_block}}',
        ])->assertRedirect();

        return Template::query()->latest('id')->firstOrFail();
    }

    public function test_system_templates_cannot_be_deleted(): void
    {
        $this->actingAs($this->superAdmin());
        foreach (Template::SYSTEM_SLUGS as $slug) {
            $template = Template::query()->where('slug', $slug)->firstOrFail();
            $template->update(['is_default' => false]);
            $this->from(route('admin.templates.index'))->delete(route('admin.templates.destroy', $template))
                ->assertRedirect(route('admin.templates.index'))->assertSessionHas('error');
            $this->assertNotSoftDeleted($template);
        }
    }

    public function test_the_platform_default_cannot_be_deleted(): void
    {
        $template = $this->customTemplate();
        $this->post(route('admin.templates.default', $template))->assertRedirect();
        $this->delete(route('admin.templates.destroy', $template))->assertSessionHas('error');
        $this->assertNotSoftDeleted($template);
    }

    public function test_custom_template_is_deleted_and_issued_slips_keep_their_design(): void
    {
        [$merchant, $owner] = $this->merchantWithOwner(mode: MutabilityMode::MUTABLE_WITH_AUDIT);
        $template = $this->customTemplate();
        $merchant->forceFill(['default_receipt_template_id' => $template->id])->save();

        $this->actingAs($owner);
        $docId = $this->postJson('/api/v1/documents', $this->receiptPayload())->assertCreated()->json('data.id');
        $this->assertSame($template->id, Document::query()->findOrFail($docId)->template_id);

        $this->actingAs($this->superAdmin());
        $this->delete(route('admin.templates.destroy', $template))
            ->assertRedirect(route('admin.templates.index'))
            ->assertSessionHas('status', fn (string $m): bool => str_contains($m, '1 merchant default'));

        $this->assertSoftDeleted($template);
        $this->assertNull($merchant->fresh()?->default_receipt_template_id);
        // The issued slip still resolves (and renders) the deleted design.
        $this->assertSame($template->id, Document::query()->findOrFail($docId)->template?->id);

        // Gone from every picker.
        $this->actingAs($owner);
        $ids = collect($this->getJson('/api/v1/templates')->assertOk()->json('data'))->pluck('id');
        $this->assertNotContains($template->id, $ids->all());
        $this->getJson("/api/v1/templates/{$template->id}/preview")->assertNotFound();
        $this->putJson('/api/v1/settings/templates', ['default_receipt_template_id' => $template->id])->assertUnprocessable()->assertJsonValidationErrors('default_receipt_template_id');

        // New slips fall back to the platform default.
        $newId = $this->postJson('/api/v1/documents', $this->receiptPayload())->assertCreated()->json('data.id');
        $this->assertNotSame($template->id, Document::query()->findOrFail($newId)->template_id);
        $this->get("/api/v1/documents/{$docId}/pdf")->assertOk();
    }

    public function test_a_new_template_can_reuse_a_deleted_templates_name(): void
    {
        $first = $this->customTemplate('Lagos Lights');
        $this->delete(route('admin.templates.destroy', $first))->assertRedirect();
        $second = $this->customTemplate('Lagos Lights');
        $this->assertNotSame($first->slug, $second->slug);
    }

    public function test_only_super_admins_can_delete(): void
    {
        $template = $this->customTemplate();
        $admin = User::factory()->create();
        $admin->assignRole(Role::ADMIN->value);
        $this->actingAs($admin)->delete(route('admin.templates.destroy', $template))->assertForbidden();
        $this->assertNotSoftDeleted($template);
    }
}
