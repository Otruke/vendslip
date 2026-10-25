<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\DocumentType;
use App\Enums\Permission;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use App\Services\Billing\PlanEntitlements;
use App\Services\Pdf\SampleDocumentFactory;
use App\Services\Pdf\SlipRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class TemplateController extends ApiController
{
    public function __construct(
        private readonly SampleDocumentFactory $samples,
        private readonly SlipRenderer $renderer,
        private readonly PlanEntitlements $entitlements,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $merchant = $this->merchant($request);

        return response()->json([
            'data' => TemplateResource::collection(Template::query()->active()->get())->resolve($request),
            'defaults' => [
                'receipt' => $merchant->default_receipt_template_id,
                'invoice' => $merchant->default_invoice_template_id,
            ],
        ]);
    }

    public function preview(Request $request, int $id): Response
    {
        $data = $request->validate(['type' => ['nullable', Rule::in(['RECEIPT', 'INVOICE'])]]);
        $merchant = $this->merchant($request);
        $template = Template::query()->active()->findOrFail($id);
        $type = DocumentType::from($data['type'] ?? 'RECEIPT');
        $watermark = ! $this->entitlements->has($merchant, PlanEntitlements::REMOVE_WATERMARK);

        return response($this->renderer->renderBytes($this->samples->make($merchant, $template, $type, $watermark)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function setDefaults(Request $request): JsonResponse
    {
        $this->authorizePermission($request, Permission::SETTINGS_MANAGE);
        $merchant = $this->merchant($request);
        $data = $request->validate([
            'default_receipt_template_id' => ['nullable', 'integer', Rule::exists('templates', 'id')->where('is_active', true)->where('supports_receipt', true)],
            'default_invoice_template_id' => ['nullable', 'integer', Rule::exists('templates', 'id')->where('is_active', true)->where('supports_invoice', true)],
        ]);
        foreach ($data as $templateId) {
            if ($templateId !== null && ! Template::query()->findOrFail((int) $templateId)->isAvailableTo($merchant->effectiveTier())) {
                abort(403, 'That template needs a higher plan.');
            }
        }
        $merchant->update($data);

        return response()->json(['ok' => true]);
    }
}
