<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\DocumentType;
use App\Enums\PlanTier;
use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\Template;
use App\Models\User;
use App\Services\Pdf\SampleDocumentFactory;
use App\Services\Pdf\SlipRenderer;
use App\Services\Pdf\TokenTemplateRenderer;
use App\Services\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

final class TemplateController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.templates.index', ['templates' => Template::query()->orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function create(): View
    {
        return view('admin.templates.form', ['template' => new Template([
            'engine' => Template::ENGINE_HTML, 'supports_receipt' => true, 'supports_invoice' => true,
            'min_plan_tier' => PlanTier::FREE, 'is_active' => true, 'html' => self::starterHtml(),
        ]), 'tokens' => Template::TOKENS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $template = Template::query()->create([...$data, 'engine' => Template::ENGINE_HTML, 'slug' => $this->slug($data['name'])]);
        $this->audit->log('admin.template_created', $this->actor($request), null, ['template' => $template->slug]);

        return redirect()->route('admin.templates.edit', $template)->with('status', 'Template created.');
    }

    public function edit(Template $template): View
    {
        return view('admin.templates.form', ['template' => $template, 'tokens' => Template::TOKENS]);
    }

    public function update(Request $request, Template $template): RedirectResponse
    {
        $data = $this->validated($request, $template);
        if ($template->engine === Template::ENGINE_BLADE) {
            unset($data['html']); // shipped Blade templates are code-managed
        }
        $template->update($data);
        $this->audit->log('admin.template_updated', $this->actor($request), null, ['template' => $template->slug]);

        return back()->with('status', 'Template saved.');
    }

    public function makeDefault(Request $request, Template $template): RedirectResponse
    {
        DB::transaction(static function () use ($template): void {
            Template::query()->update(['is_default' => false]);
            $template->update(['is_default' => true, 'is_active' => true]);
        });

        return back()->with('status', "{$template->name} is now the platform default.");
    }

    /**
     * Delete a custom template. The two system templates and the current platform default are
     * protected. Merchants who picked it fall back to the platform default; slips already issued
     * with it keep rendering with it (soft delete).
     */
    public function destroy(Request $request, Template $template): RedirectResponse
    {
        if ($template->isSystem()) {
            return back()->with('error', "{$template->name} is a system template. You can edit or hide it, but not delete it.");
        }
        if ($template->is_default) {
            return back()->with('error', "{$template->name} is the platform default. Make another template the default first.");
        }

        $affected = DB::transaction(static function () use ($template): int {
            $receipt = Merchant::query()->where('default_receipt_template_id', $template->id)->update(['default_receipt_template_id' => null]);
            $invoice = Merchant::query()->where('default_invoice_template_id', $template->id)->update(['default_invoice_template_id' => null]);
            $template->update(['is_active' => false]);
            $template->delete();

            return $receipt + $invoice;
        });
        $this->audit->log('admin.template_deleted', $this->actor($request), null, ['template' => $template->slug, 'merchant_defaults_reset' => $affected]);

        $note = $affected > 0 ? " {$affected} merchant default".($affected === 1 ? '' : 's').' switched to the platform default.' : '';

        return redirect()->route('admin.templates.index')->with('status', "{$template->name} deleted.{$note}");
    }

    public function preview(Request $request, Template $template, SampleDocumentFactory $samples, SlipRenderer $renderer): Response
    {
        $type = DocumentType::from((string) $request->query('type', 'RECEIPT') === 'INVOICE' ? 'INVOICE' : 'RECEIPT');
        $merchant = new Merchant([
            'name' => 'Amina Luxury Couture', 'email' => 'hello@aminaluxury.ng', 'phone' => '+2348031234567',
            'address' => '14 Adeola Odeku St, Victoria Island, Lagos', 'currency' => 'NGN', 'vat_rate_bps' => 750, 'vat_inclusive' => false,
            'primary_color' => '#377581', 'secondary_color' => '#265363', 'bank_name' => 'Providus Bank',
            'bank_account_number' => '0192837465', 'bank_account_name' => 'Amina Luxury Couture', 'plan_tier' => PlanTier::PRO,
            'record_mutability_mode' => 'IMMUTABLE', 'status' => 'ACTIVE',
        ]);
        $merchant->id = '00000000-0000-0000-0000-000000000000';

        return response($renderer->renderBytes($samples->make($merchant, $template, $type, $request->boolean('watermark'))), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="template-preview.pdf"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Template $template): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'html' => [Rule::requiredIf($template === null || $template->engine === Template::ENGINE_HTML), 'nullable', 'string', 'max:100000'],
            'supports_receipt' => ['required', 'boolean'],
            'supports_invoice' => ['required', 'boolean'],
            'min_plan_tier' => ['required', Rule::enum(PlanTier::class)],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,1000'],
        ]);
        if (isset($data['html'])) {
            $data['html'] = (new TokenTemplateRenderer)->sanitize((string) $data['html']);
        }
        $data['sort_order'] = (int) ($data['sort_order'] ?? 10);

        return $data;
    }

    private function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'template';
        $slug = $base;
        while (Template::withTrashed()->where('slug', $slug)->exists()) { // deleted slugs stay reserved
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }

    private function actor(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }

    private static function starterHtml(): string
    {
        return <<<'HTML'
<div style="padding:32px;">
  {{watermark}}
  <table><tr>
    <td><h1 style="margin:0;color:{{primary_color}};">{{business_name}}</h1><div class="muted">{{business_address}} · {{business_phone}}</div></td>
    <td style="text-align:right;"><div class="label">{{doc_title}}</div><div style="font-size:20px;font-weight:800;">{{doc_number}}</div><div class="muted">{{issued_at}}</div></td>
  </tr></table>
  <div style="margin:20px 0 12px;"><div class="label">Billed to</div><strong>{{customer_name}}</strong> <span class="muted">{{customer_phone}}</span></div>
  {{items_table}}
  <table style="margin-top:16px;"><tr>
    <td style="width:55%;vertical-align:top;">{{bank_block}}</td>
    <td style="vertical-align:top;">{{totals_block}}</td>
  </tr></table>
  <div style="margin-top:24px;">{{qr_verification}}</div>
  <p class="script" style="font-size:20px;color:{{secondary_color}};">{{footer}}</p>
</div>
HTML;
    }
}
