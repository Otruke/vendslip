<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\BotDraftSession;
use App\Models\ChannelIdentity;
use App\Models\Document;
use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Services\Settings\PlatformSettings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Twilio\Security\RequestValidator;

/** Primary route: WhatsApp, Messenger and Instagram through Meta directly (/api/v1/webhooks/meta). */
final class MetaWebhookTest extends TestCase
{
    private int $seq = 0;

    /** Simulates Meta refusing to fetch the PDF for a document/file message. */
    private bool $refuseAttachments = false;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'vendslip.meta.app_secret' => 'meta-app-secret',
            'vendslip.meta.verify_token' => 'meta-verify-token',
            'vendslip.meta.whatsapp_phone_number_id' => '1112223334',
            'vendslip.meta.whatsapp_token' => 'wa-system-token',
            'vendslip.meta.whatsapp_number' => '+2348012345678',
            'vendslip.meta.page_id' => '5550001',
            'vendslip.meta.page_token' => 'page-token',
            'vendslip.meta.instagram_account_id' => '17841400000000000',
            'vendslip.meta.instagram_username' => 'vendslip',
        ]);
        Http::fake(function (HttpRequest $r) {
            $isAttachment = ($r['type'] ?? null) === 'document' || isset($r['message']['attachment']);
            if ($this->refuseAttachments && $isAttachment) {
                return Http::response(['error' => ['message' => 'Media download error', 'code' => 131053]], 400);
            }

            return Http::response(['messages' => [['id' => 'wamid.OUT']], 'message_id' => 'm_out']);
        });
    }

    private function admin(Role $role = Role::SUPER_ADMIN): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    /** @param array<string, mixed> $payload */
    private function meta(array $payload, string $secret = 'meta-app-secret'): TestResponse
    {
        $body = (string) json_encode($payload);

        return $this->call('POST', '/api/v1/webhooks/meta', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, $secret),
        ], $body);
    }

    /** @param array<string, mixed> $message */
    private function whatsapp(array $message, string $from = '2348031234567', string $phoneNumberId = '1112223334'): TestResponse
    {
        return $this->meta(['object' => 'whatsapp_business_account', 'entry' => [['id' => 'WABA', 'changes' => [[
            'field' => 'messages',
            'value' => [
                'messaging_product' => 'whatsapp',
                'metadata' => ['display_phone_number' => '2348012345678', 'phone_number_id' => $phoneNumberId],
                'contacts' => [['wa_id' => $from, 'profile' => ['name' => 'Tobi']]],
                'messages' => [['from' => $from, 'id' => $message['id'] ?? 'wamid.'.(++$this->seq), 'timestamp' => '1730000000', ...$message]],
            ],
        ]]]]]);
    }

    private function waText(string $text): TestResponse
    {
        return $this->whatsapp(['type' => 'text', 'text' => ['body' => $text]]);
    }

    /** @param array<string, mixed> $message */
    private function messaging(string $object, string $accountId, array $message, string $sender = '9900112233'): TestResponse
    {
        return $this->meta(['object' => $object, 'entry' => [['id' => $accountId, 'time' => 1730000000, 'messaging' => [[
            'sender' => ['id' => $sender],
            'recipient' => ['id' => $accountId],
            'timestamp' => 1730000000,
            'message' => ['mid' => 'mid.'.(++$this->seq), ...$message],
        ]]]]]);
    }

    private function linkCode(): string
    {
        [, $user] = $this->merchantWithOwner();
        $this->actingAs($user);
        $code = (string) $this->postJson('/api/v1/channels/link-code')->assertCreated()->json('code');
        auth()->guard('web')->logout();

        return $code;
    }

    /** @return list<array<string, mixed>> request bodies sent to Graph, in order */
    private function sent(string $host = 'graph.facebook.com'): array
    {
        return collect(Http::recorded())
            ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), $host))
            ->map(fn (array $pair): array => $pair[0]->data())
            ->values()->all();
    }

    public function test_verification_handshake(): void
    {
        $this->get('/api/v1/webhooks/meta?hub.mode=subscribe&hub.verify_token=meta-verify-token&hub.challenge=1158201444')
            ->assertOk()->assertSeeText('1158201444')->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->get('/api/v1/webhooks/meta?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=1158201444')->assertForbidden();
        $this->get('/api/v1/webhooks/meta?hub.mode=subscribe&hub.verify_token=meta-verify-token&hub.challenge=<script>')->assertForbidden();
    }

    public function test_rejects_unsigned_and_forged_payloads(): void
    {
        $this->postJson('/api/v1/webhooks/meta', ['object' => 'page', 'entry' => []])->assertForbidden();
        $this->meta(['object' => 'page', 'entry' => []], secret: 'not-the-secret')->assertForbidden();
        $this->assertTrue(SecurityAuditLog::query()->where('event', 'webhook.meta_bad_signature')->exists());
        Http::assertNothingSent();
    }

    public function test_whatsapp_link_buffer_buttons_and_pdf_document(): void
    {
        $code = $this->linkCode();
        $this->waText('/link '.$code)->assertOk()->assertSeeText('EVENT_RECEIVED');

        $this->assertSame('whatsapp:+2348031234567', ChannelIdentity::query()->firstOrFail()->external_user_id);
        Http::assertSent(fn (HttpRequest $r): bool => $r->url() === 'https://graph.facebook.com/v24.0/1112223334/messages'
            && $r->hasHeader('Authorization', 'Bearer wa-system-token')
            && $r['to'] === '2348031234567' && $r['type'] === 'text' && str_contains($r['text']['body'], 'Linked'));

        // Partial draft: buffered silently, nothing sent.
        $before = count($this->sent());
        $this->waText('/name = Ada')->assertOk();
        $this->assertCount($before, $this->sent());

        $this->waText('/amount = 5000, /type = invoice')->assertOk();
        $summary = collect($this->sent())->last();
        $this->assertSame('interactive', $summary['type']);
        $this->assertSame('button', $summary['interactive']['type']);
        $this->assertStringContainsString('Invoice for Ada', $summary['interactive']['body']['text']);
        $this->assertSame(['NS_CONFIRM', 'NS_EDIT', 'NS_CANCEL'], array_map(fn (array $b): string => $b['reply']['id'], $summary['interactive']['action']['buttons']));

        // Later edits stay silent (summary already shown).
        $before = count($this->sent());
        $this->waText('/address = 42 Wuse II, Abuja')->assertOk();
        $this->assertCount($before, $this->sent());

        // Tap Confirm → PDF delivered as a document.
        $tap = ['id' => 'wamid.TAP', 'type' => 'interactive', 'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => 'NS_CONFIRM', 'title' => '✅ Confirm']]];
        $this->whatsapp($tap)->assertOk();
        $doc = collect($this->sent())->last();
        $document = Document::query()->firstOrFail();
        $this->assertSame('document', $doc['type']);
        $this->assertSame($document->doc_number.'.pdf', $doc['document']['filename']);
        // The customer sees only the short link on the web app; the signed file address is for Meta to fetch.
        $this->assertMatchesRegularExpression('#View & download: https?://[^/]+/s/[2-9a-km-zA-HJ-NP-Z]{9}$#', $doc['document']['caption']);
        $this->assertStringNotContainsString('/api/', $doc['document']['caption']);
        $this->assertStringContainsString('signature=', $doc['document']['link']);
        $this->assertSame('WHATSAPP', $document->channel->value);

        // Meta retrying the same delivery never issues twice.
        $before = count($this->sent());
        $this->whatsapp($tap)->assertOk();
        $this->assertCount($before, $this->sent());
        $this->assertSame(1, Document::query()->count());

        // Tokens never travel in the URL.
        Http::assertNotSent(fn (HttpRequest $r): bool => str_contains($r->url(), 'token'));
    }

    public function test_whatsapp_ignores_statuses_other_numbers_and_media(): void
    {
        $this->meta(['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['field' => 'messages', 'value' => [
            'metadata' => ['phone_number_id' => '1112223334'], 'statuses' => [['id' => 'wamid.X', 'status' => 'read']],
        ]]]]]])->assertOk();
        $this->whatsapp(['type' => 'text', 'text' => ['body' => '/help']], phoneNumberId: '9999999999')->assertOk();
        $this->whatsapp(['type' => 'image', 'image' => ['id' => 'media1']])->assertOk();
        Http::assertNothingSent();
    }

    public function test_whatsapp_falls_back_to_link_when_document_is_refused(): void
    {
        $this->refuseAttachments = true;
        $this->waText('/link '.$this->linkCode());
        $this->waText('/name = Ada /amount = 1000');
        $this->waText('1')->assertOk();

        $last = collect($this->sent())->last();
        $this->assertSame('text', $last['type']);
        $this->assertMatchesRegularExpression('#/s/[2-9a-km-zA-HJ-NP-Z]{9}$#', $last['text']['body']);
        $this->assertStringNotContainsString('/api/', $last['text']['body']);
    }

    public function test_messenger_quick_replies_and_pdf_file(): void
    {
        $this->messaging('page', '5550001', ['text' => '/link '.$this->linkCode()])->assertOk();
        $this->assertSame('messenger:9900112233', ChannelIdentity::query()->firstOrFail()->external_user_id);
        Http::assertSent(fn (HttpRequest $r): bool => $r->url() === 'https://graph.facebook.com/v24.0/me/messages'
            && $r->hasHeader('Authorization', 'Bearer page-token')
            && $r['recipient']['id'] === '9900112233' && $r['messaging_type'] === 'RESPONSE'
            && str_contains($r['message']['text'], 'Linked'));

        $this->messaging('page', '5550001', ['text' => '/name = Ada, /amount = 5000'])->assertOk();
        $summary = collect($this->sent())->last();
        $this->assertStringContainsString('Receipt for Ada', $summary['message']['text']);
        $payloads = array_column($summary['message']['quick_replies'], 'payload');
        $this->assertSame('NS_CONFIRM', $payloads[0]);
        $this->assertContains('NS_CANCEL', $payloads);

        // Our own echoed reply and messages to another page are ignored.
        $before = count($this->sent());
        $this->messaging('page', '5550001', ['text' => 'echo', 'is_echo' => true])->assertOk();
        $this->messaging('page', '7777777', ['text' => '/help'])->assertOk();
        $this->assertCount($before, $this->sent());

        $this->messaging('page', '5550001', ['text' => '✅ Confirm', 'quick_reply' => ['payload' => 'NS_CONFIRM']])->assertOk();
        $sent = $this->sent();
        $file = end($sent);
        $this->assertSame('file', $file['message']['attachment']['type']);
        $this->assertStringContainsString('signature=', $file['message']['attachment']['payload']['url']);
        $this->assertSame('FACEBOOK', Document::query()->firstOrFail()->channel->value);
    }

    public function test_instagram_uses_instagram_login_token_and_its_app_secret(): void
    {
        config(['vendslip.meta.instagram_token' => 'IGAA-token', 'vendslip.meta.instagram_app_secret' => 'ig-app-secret']);
        $code = $this->linkCode();
        $body = (string) json_encode(['object' => 'instagram', 'entry' => [['id' => '17841400000000000', 'messaging' => [[
            'sender' => ['id' => '4455'], 'recipient' => ['id' => '17841400000000000'], 'message' => ['mid' => 'ig.1', 'text' => '/link '.$code],
        ]]]]]);
        $this->call('POST', '/api/v1/webhooks/meta', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'ig-app-secret'),
        ], $body)->assertOk();

        $this->assertSame('instagram:4455', ChannelIdentity::query()->firstOrFail()->external_user_id);
        Http::assertSent(fn (HttpRequest $r): bool => $r->url() === 'https://graph.instagram.com/v24.0/me/messages'
            && $r->hasHeader('Authorization', 'Bearer IGAA-token') && ! isset($r['messaging_type']));
        $this->assertSame([], $this->sent('graph.facebook.com'));
    }

    public function test_instagram_falls_back_to_page_token_and_caps_text_at_1000_bytes(): void
    {
        $this->messaging('instagram', '17841400000000000', ['text' => '/link '.$this->linkCode()], sender: '4455')->assertOk();
        $item = '/item = 1x '.str_repeat('Agbada embroidery ', 6).'@ 1000';
        $this->messaging('instagram', '17841400000000000', ['text' => "/name = Ada\n".implode("\n", array_fill(0, 12, $item))], sender: '4455')->assertOk();

        $summary = collect($this->sent())->last();
        Http::assertSent(fn (HttpRequest $r): bool => $r->hasHeader('Authorization', 'Bearer page-token'));
        $this->assertLessThanOrEqual(1000, strlen($summary['message']['text']));
        $this->assertTrue(mb_check_encoding($summary['message']['text'], 'UTF-8'));
        $this->assertNotEmpty($summary['message']['quick_replies']);
    }

    public function test_switch_routes_only_the_selected_provider_answers(): void
    {
        $code = $this->linkCode();
        $settings = app(PlatformSettings::class);

        // Meta is live: Twilio's webhook is acknowledged but silent.
        $url = url('/api/v1/webhooks/whatsapp');
        $params = ['MessageSid' => 'SM-meta-live', 'From' => 'whatsapp:+2348031234567', 'Body' => '/link '.$code];
        $sig = (new RequestValidator('twilio-test-token'))->computeSignature($url, $params);
        $this->withHeader('X-Twilio-Signature', $sig)->post($url, $params)->assertOk()->assertSee('<Response/>', false);
        $this->assertSame(0, ChannelIdentity::query()->count());

        // Switch to Twilio: Meta's webhook goes quiet and Twilio answers.
        $settings->set('channels.meta_provider', 'twilio');
        $this->waText('/link '.$code)->assertOk();
        Http::assertNothingSent();
        $params['MessageSid'] = 'SM-twilio-live';
        $sig = (new RequestValidator('twilio-test-token'))->computeSignature($url, $params);
        $this->withHeader('X-Twilio-Signature', $sig)->post($url, $params)->assertOk()->assertSee('Linked');

        // Same chat id on both routes: switching back keeps the link working.
        $settings->set('channels.meta_provider', 'meta');
        $this->waText('/name = Ada /amount = 1000')->assertOk();
        $this->assertSame('interactive', collect($this->sent())->last()['type']);
        $this->assertSame(1, ChannelIdentity::query()->count());
        $this->assertSame(1, BotDraftSession::query()->count());
    }

    public function test_admin_toggle_and_channel_status(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('admin.gateways.edit'))->assertOk()
            ->assertSee('Meta Cloud API')->assertSeeInOrder(['aria-pressed="true"', 'Meta Cloud API', 'aria-pressed="false"', 'Twilio'], false)->assertSee(url('/api/v1/webhooks/meta'))
            ->assertDontSee('meta-app-secret')->assertDontSee('wa-system-token');

        $this->put(route('admin.gateways.provider'), ['provider' => 'nonsense'])->assertSessionHasErrors('provider');
        $this->put(route('admin.gateways.provider'), ['provider' => 'twilio'])->assertRedirect()->assertSessionHas('status');
        $this->assertSame('twilio', app(PlatformSettings::class)->string('channels.meta_provider'));
        $this->assertTrue(SecurityAuditLog::query()->where('event', 'admin.channel_provider_switched')->exists());
        $this->get(route('admin.gateways.edit'))->assertSeeInOrder(['aria-pressed="false"', 'Meta Cloud API', 'aria-pressed="true"', 'Twilio'], false);

        // A regular platform admin cannot flip it.
        $this->actingAs($this->admin(Role::ADMIN));
        $this->put(route('admin.gateways.provider'), ['provider' => 'meta'])->assertForbidden();

        // Merchant view follows the selected route.
        [, $owner] = $this->merchantWithOwner();
        $this->actingAs($owner);
        $channels = fn (): array => collect($this->getJson('/api/v1/channels')->assertOk()->json('channels'))->pluck('configured', 'channel')->all();
        $this->assertFalse($channels()['WHATSAPP']); // Twilio selected, no Twilio number set
        app(PlatformSettings::class)->set('channels.meta_provider', 'meta');
        $this->assertSame(['WHATSAPP' => true, 'TELEGRAM' => true, 'INSTAGRAM' => true, 'FACEBOOK' => true], $channels());
        $this->getJson('/api/v1/channels')->assertJsonPath('handles.whatsapp_link', 'https://wa.me/2348012345678')
            ->assertJsonPath('handles.messenger_link', 'https://m.me/5550001')
            ->assertJsonPath('handles.instagram_link', 'https://ig.me/m/vendslip');
    }
}
