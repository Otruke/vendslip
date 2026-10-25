<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\Document;

interface SlipRenderer
{
    /** Render and persist the PDF, returning its storage path (also saved on the document). */
    public function store(Document $document): string;

    /** Raw PDF bytes, rendering on demand if the stored file is missing or stale. */
    public function content(Document $document): string;

    /** Render PDF bytes without persisting (template previews of unsaved sample documents). */
    public function renderBytes(Document $document): string;

    /** Rendered HTML (used for previews and tests). */
    public function html(Document $document): string;
}
