<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

final class QrCodeService
{
    /** PNG data URI (DOMPDF renders PNG most reliably). */
    public function pngDataUri(string $data, int $scale = 6): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'eccLevel' => EccLevel::M,
            'scale' => $scale,
            'quietzoneSize' => 2,
            'outputBase64' => true,
        ]);

        return (string) (new QRCode($options))->render($data);
    }

    /** Inline SVG markup for HTML pages (admin / 2FA enrolment). */
    public function svg(string $data): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'eccLevel' => EccLevel::M,
            'outputBase64' => false,
            'quietzoneSize' => 2,
            'addQuietzone' => true,
        ]);

        return (string) (new QRCode($options))->render($data);
    }
}
