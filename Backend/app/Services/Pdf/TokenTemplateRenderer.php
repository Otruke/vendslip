<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\Document;
use App\Models\Merchant;
use App\Models\Template;
use Illuminate\Support\Facades\View;

/**
 * Renders admin-authored HTML templates with {{token}} placeholders.
 * Scalar tokens are HTML-escaped; block tokens are rendered from trusted partials.
 * <script>, <iframe>, <object>, <embed> and on* handlers are stripped.
 */
final class TokenTemplateRenderer
{
    /** @param array<string, mixed> $d */
    public function render(string $html, array $d): string
    {
        /** @var Document $doc */
        $doc = $d['document'];
        /** @var Merchant $merchant */
        $merchant = $d['merchant'];

        $scalar = [
            'business_name' => $merchant->name,
            'business_address' => (string) $merchant->address,
            'business_phone' => (string) $merchant->phone,
            'business_email' => (string) $merchant->email,
            'logo_url' => (string) ($d['logo'] ?? ''),
            'primary_color' => (string) $d['primary'],
            'secondary_color' => (string) $d['secondary'],
            'doc_title' => (string) $d['title'],
            'doc_number' => $doc->doc_number,
            'issued_at' => (string) $d['issued_at'],
            'due_date' => (string) ($d['due_date'] ?? ''),
            'status' => (string) $d['status_label'],
            'customer_name' => $doc->customer_name,
            'customer_phone' => (string) $doc->customer_phone,
            'customer_address' => (string) $doc->customer_address,
            'notes' => (string) $doc->notes,
            'verification_code' => (string) $d['verification_code'],
            'tagline' => (string) $d['tagline'],
            'footer' => (string) $d['footer'],
        ];

        $blocks = [
            'items_table' => View::make('pdf.partials.items-table', ['d' => $d])->render(),
            'totals_block' => View::make('pdf.partials.totals', ['d' => $d])->render(),
            'bank_block' => View::make('pdf.partials.bank', ['d' => $d])->render(),
            'qr_verification' => View::make('pdf.partials.qr', ['d' => $d])->render(),
            'watermark' => $d['watermark'] ? View::make('pdf.partials.watermark')->render() : '',
        ];

        $clean = $this->sanitize($html);

        return (string) preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', static function (array $m) use ($scalar, $blocks): string {
            $token = $m[1];
            if (array_key_exists($token, $blocks)) {
                return $blocks[$token];
            }
            if (array_key_exists($token, $scalar)) {
                return e($scalar[$token]);
            }

            return in_array($token, Template::TOKENS, true) ? '' : $m[0];
        }, $clean);
    }

    public function sanitize(string $html): string
    {
        $html = (string) preg_replace('#<\s*(script|iframe|object|embed|link|meta|base)\b[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html);
        $html = (string) preg_replace('#<\s*(script|iframe|object|embed|link|meta|base)\b[^>]*/?>#is', '', $html);
        $html = (string) preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);

        return (string) preg_replace('/(href|src)\s*=\s*(["\'])\s*(javascript|https?|file|ftp):[^"\']*\2/i', '$1=$2#$2', $html);
    }
}
