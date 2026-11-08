<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Document;
use App\Support\ShortCode;
use Tests\TestCase;

/** The one short link a business sends its customer: on the web app's address, never the API's. */
final class ShortLinkTest extends TestCase
{
    public function test_every_slip_gets_a_short_link_that_opens_and_downloads_it(): void
    {
        config(['vendslip.frontend_url' => 'https://vendslip.example', 'vendslip.share_url' => '']);
        [, $owner] = $this->merchantWithOwner();
        $this->actingAs($owner);
        $id = $this->postJson('/api/v1/documents', $this->receiptPayload())->assertCreated()->json('data.id');
        $doc = Document::query()->findOrFail($id);

        $this->assertTrue(ShortCode::looksValid((string) $doc->short_code));
        $this->assertSame('https://vendslip.example/s/'.$doc->short_code, $doc->shareUrl());

        // What the business copies or sends from the dashboard: only the short link.
        $share = $this->getJson("/api/v1/documents/{$id}/share")->assertOk();
        $this->assertSame($doc->shareUrl(), $share->json('share_url'));
        $this->assertStringContainsString($doc->shareUrl(), $share->json('message'));
        $this->assertStringNotContainsString('/api/', $share->json('message'));
        $this->assertStringNotContainsString('/api/', urldecode($share->json('whatsapp_url')));
        $this->assertStringNotContainsString('/api/', urldecode($share->json('telegram_url')));

        // The customer's page and the PDF behind it.
        auth()->guard('web')->logout();
        $this->getJson('/api/v1/public/s/'.$doc->short_code)->assertOk()
            ->assertJsonPath('doc_type', 'RECEIPT')->assertJsonPath('doc_number', $doc->doc_number)
            ->assertJsonPath('pay_uid', null)->assertJsonPath('gateways.flutterwave', false)
            ->assertJsonMissingPath('pdf_url');
        $this->get('/api/v1/public/s/'.$doc->short_code.'/pdf')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="'.$doc->doc_number.'.pdf"');

        // Unknown or malformed codes reveal nothing.
        $this->getJson('/api/v1/public/s/Zz9Zz9Zz9')->assertNotFound();
        $this->getJson('/api/v1/public/s/abc')->assertNotFound();
        $this->get('/api/v1/public/s/0000OOOOO/pdf')->assertNotFound();
    }

    public function test_a_custom_short_domain_can_be_used(): void
    {
        config(['vendslip.share_url' => 'https://vsl.ng/']);
        [, $owner] = $this->merchantWithOwner();
        $id = $this->actingAs($owner)->postJson('/api/v1/documents', $this->receiptPayload())->assertCreated()->json('data.id');
        $this->assertMatchesRegularExpression('#^https://vsl\.ng/s/[2-9a-km-zA-HJ-NP-Z]{9}$#', Document::query()->findOrFail($id)->shareUrl());
    }
}
