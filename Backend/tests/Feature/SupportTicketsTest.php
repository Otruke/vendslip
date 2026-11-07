<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\BusinessAlert;
use App\Notifications\SupportStaffNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Tickets from businesses, answered by support agents, scored by QA, internal notes kept private. */
final class SupportTicketsTest extends TestCase
{
    private function staff(Role $role, string $name = 'Staff'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole($role->value);

        return $user;
    }

    private function openTicket(User $owner): string
    {
        return $this->actingAs($owner)->postJson('/api/v1/support/tickets', [
            'subject' => 'Bot not replying', 'category' => 'BOT', 'body' => 'Telegram bot stopped answering since morning.',
        ])->assertCreated()->assertJsonPath('data.status', 'OPEN')->json('data.reference');
    }

    public function test_full_ticket_flow_between_business_agent_and_qa(): void
    {
        Notification::fake();
        [$merchant, $owner] = $this->merchantWithOwner();
        $agent = $this->staff(Role::SUPPORT_AGENT, 'Tola Agent');
        $qa = $this->staff(Role::QA_REVIEWER, 'Quinn QA');

        $ref = $this->openTicket($owner);
        Notification::assertSentTo($agent, SupportStaffNotification::class);
        $ticket = SupportTicket::query()->where('reference', $ref)->firstOrFail();

        // The agent works from the console only: no merchants or payments.
        $this->actingAs($agent)->get(route('admin.support.index'))->assertOk()->assertSee($ref);
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.support.index'));
        $this->get(route('admin.merchants.index'))->assertForbidden();
        $this->post(route('admin.support.note', $ticket), ['body' => 'Webhook looks unset'])->assertSessionHas('status');
        $this->post(route('admin.support.reply', $ticket), ['body' => 'We reset your webhook. Try now.', 'then' => 'AWAITING_CUSTOMER'])->assertSessionHas('status');
        $ticket->refresh();
        $this->assertSame('AWAITING_CUSTOMER', $ticket->status);
        $this->assertSame($agent->id, $ticket->assigned_to_user_id, 'replying takes an unassigned ticket');
        $this->assertNotNull($ticket->first_response_at);
        Notification::assertSentTo($owner, BusinessAlert::class, fn (BusinessAlert $n, array $via): bool => str_contains($n->title, $ref) && in_array('mail', $via, true));

        // The business sees the reply but never the internal note, and agents by first name only.
        $show = $this->actingAs($owner)->getJson("/api/v1/support/tickets/{$ref}")->assertOk();
        $bodies = collect($show->json('data.messages'))->pluck('body')->all();
        $this->assertContains('We reset your webhook. Try now.', $bodies);
        $this->assertNotContains('Webhook looks unset', $bodies);
        $this->assertSame('Tola · VendSlip support', collect($show->json('data.messages'))->firstWhere('kind', 'AGENT')['author']);

        $this->postJson("/api/v1/support/tickets/{$ref}/messages", ['body' => 'Works now, thanks!'])->assertOk()->assertJsonPath('data.status', 'OPEN');

        // QA can read, note and score, but not reply.
        $this->actingAs($qa)->get(route('admin.support.show', $ticket))->assertOk()->assertSee('Webhook looks unset');
        $this->post(route('admin.support.reply', $ticket), ['body' => 'hi', 'then' => 'RESOLVED'])->assertSessionHas('error');
        $this->put(route('admin.support.review', $ticket), ['score' => 4, 'note' => 'Quick fix, could explain cause'])->assertSessionHas('status');
        $this->assertSame(4, $ticket->refresh()->qa_score);
        $this->assertSame(3, $ticket->messages()->where('is_internal', false)->count());

        $this->actingAs($owner)->postJson("/api/v1/support/tickets/{$ref}/close")->assertOk()->assertJsonPath('data.status', 'CLOSED');
        $this->postJson("/api/v1/support/tickets/{$ref}/messages", ['body' => 'one more'])->assertUnprocessable();
    }

    public function test_visibility_and_assignment_rules(): void
    {
        Notification::fake();
        [$merchant, $owner] = $this->merchantWithOwner();
        [, $otherOwner] = $this->merchantWithOwner();
        $clerk = User::factory()->create(['merchant_id' => $merchant->id]);
        $clerk->assignRole(Role::SALES_AGENT->value);
        $agentA = $this->staff(Role::SUPPORT_AGENT, 'Agent A');
        $agentB = $this->staff(Role::SUPPORT_AGENT, 'Agent B');
        $super = $this->staff(Role::SUPER_ADMIN, 'Sade Super');

        $ref = $this->openTicket($owner);
        $ticket = SupportTicket::query()->where('reference', $ref)->firstOrFail();
        $this->actingAs($otherOwner)->getJson("/api/v1/support/tickets/{$ref}")->assertNotFound();
        $this->actingAs($clerk)->getJson('/api/v1/support/tickets')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($owner)->getJson('/api/v1/support/tickets')->assertJsonCount(1, 'data');

        $this->actingAs($agentA)->put(route('admin.support.assign', $ticket), ['user_id' => $agentA->id])->assertSessionHas('status');
        $this->actingAs($agentB)->put(route('admin.support.assign', $ticket), ['user_id' => $agentB->id])->assertSessionHas('error');
        $this->actingAs($super)->put(route('admin.support.assign', $ticket), ['user_id' => $agentB->id])->assertSessionHas('status');
        $this->assertSame($agentB->id, $ticket->refresh()->assigned_to_user_id);
        Notification::assertSentTo($agentB, SupportStaffNotification::class, fn (SupportStaffNotification $n): bool => str_starts_with($n->title, 'Assigned to you'));

        // Super-Admin creates support staff from Platform staff.
        $this->actingAs($super)->post(route('admin.admins.store'), [
            'name' => 'New Agent', 'email' => 'agent@vendslip.test', 'role' => 'SupportAgent',
            'password' => 'Str0ng-Passw0rd!', 'password_confirmation' => 'Str0ng-Passw0rd!',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(User::query()->where('email', 'agent@vendslip.test')->firstOrFail()->hasRole('SupportAgent'));
    }
}
