<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Slash\DraftPayload;
use App\Services\Slash\SlashCommandParser;
use Tests\TestCase;

/** /discount off the whole order: a plain amount or a percentage. */
final class OrderDiscountTest extends TestCase
{
    public function test_order_discount_takes_an_amount_or_a_percentage(): void
    {
        [$amount] = DraftPayload::empty()->apply(app(SlashCommandParser::class)->parse("/name = Ada\n/item = Gele @ 5000\n/discount = 2,000"));
        $this->assertSame(200_000, $amount->discountFor(1_000_000));
        [$percent] = DraftPayload::empty()->apply(app(SlashCommandParser::class)->parse("/name = Ada\n/item = Gele @ 5000\n/discount = 10%"));
        $this->assertSame(100_000, $percent->discountFor(1_000_000));
        [, $errors] = DraftPayload::empty()->apply(app(SlashCommandParser::class)->parse('/discount = some'));
        $this->assertStringContainsString('or 10%', $errors[0]);
    }
}
