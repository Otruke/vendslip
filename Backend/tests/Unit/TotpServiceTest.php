<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Security\TotpService;
use Tests\TestCase;

final class TotpServiceTest extends TestCase
{
    public function test_rfc6238_vector_and_replay_protection(): void
    {
        $totp = new TotpService;
        // RFC 6238 Appendix B SHA-1 seed "12345678901234567890" → base32
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $this->assertSame('287082', $totp->codeAt($secret, intdiv(59, 30)));
        $this->assertSame('081804', $totp->codeAt($secret, intdiv(1111111109, 30)));

        $this->assertTrue($totp->verify($secret, '287082', 'u1', 59));
        $this->assertFalse($totp->verify($secret, '287082', 'u1', 59), 'replay must be rejected');
        $this->assertFalse($totp->verify($secret, '000000', 'u2', 59));

        $generated = $totp->generateSecret();
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $generated);
        $this->assertStringStartsWith('otpauth://totp/NetSlip:', $totp->otpauthUri($generated, 'a@b.c'));
    }
}
