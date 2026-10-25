<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\Document;
use App\Models\Template;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

/**
 * Sandboxed HTML → PDF (DOMPDF): no PHP execution, no remote fetches, chroot to the app.
 */
final class DompdfSlipRenderer implements SlipRenderer
{
    public function __construct(private readonly SlipViewModel $viewModel) {}

    public function store(Document $document): string
    {
        $path = $this->pathFor($document);
        Storage::disk($this->disk())->put($path, $this->renderPdf($document));

        if ($document->pdf_storage_path !== $path) {
            $old = $document->pdf_storage_path;
            $document->forceFill(['pdf_storage_path' => $path])->saveQuietly();
            if ($old !== null && $old !== $path) {
                Storage::disk($this->disk())->delete($old);
            }
        }

        return $path;
    }

    public function content(Document $document): string
    {
        $expected = $this->pathFor($document);
        $disk = Storage::disk($this->disk());
        if ($document->pdf_storage_path !== $expected || ! $disk->exists($expected)) {
            $this->store($document);
        }

        return (string) $disk->get($expected);
    }

    public function html(Document $document): string
    {
        $document->loadMissing('template', 'merchant', 'items');
        $data = $this->viewModel->build($document);
        $template = $document->template;
        $paper = 'a4';

        if ($template !== null && $template->engine === Template::ENGINE_HTML && filled($template->html)) {
            $body = (new TokenTemplateRenderer)->render((string) $template->html, $data);

            return View::make('pdf.layout', ['data' => $data, 'body' => $body, 'paper' => $paper])->render();
        }

        // Only shipped Blade templates can be rendered by name (never an arbitrary view from the DB).
        $view = match ($template?->blade_view) {
            'pdf.templates.minimalist-social' => 'pdf.templates.minimalist-social',
            default => 'pdf.templates.classic-executive',
        };

        return View::make($view, ['d' => $data])->render();
    }

    public function renderBytes(Document $document): string
    {
        return $this->renderPdf($document);
    }

    private function renderPdf(Document $document): string
    {
        $document->loadMissing('template');
        $paper = $document->template?->slug === Template::MINIMALIST_SOCIAL ? 'a5' : 'a4';

        return Pdf::loadHTML($this->html($document))
            ->setPaper($paper, 'portrait')
            ->setOptions([
                'isRemoteEnabled' => false,
                'isPhpEnabled' => false,
                'isJavascriptEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'chroot' => [resource_path(), storage_path('fonts')],
                'fontDir' => storage_path('fonts'),
                'fontCache' => storage_path('fonts'),
                'defaultFont' => 'DejaVu Sans',
                'dpi' => 96,
            ])
            ->output();
    }

    private function pathFor(Document $document): string
    {
        return sprintf(
            '%s/%s/%s-r%d-%s.pdf',
            config('netslip.pdf.directory', 'slips'),
            $document->merchant_id,
            $document->doc_number,
            $document->revision_number,
            substr(hash('sha256', $document->verification_hash.$document->status->value.$document->amount_paid_minor.$document->updated_at?->getTimestamp()), 0, 10),
        );
    }

    private function disk(): string
    {
        return (string) config('netslip.pdf.disk', 'local');
    }
}
