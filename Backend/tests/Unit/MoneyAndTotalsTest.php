<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Services\Documents\TotalsCalculator;
use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyAndTotalsTest extends TestCase
{
    /** @return iterable<array{string, int|null}> */
    public static function moneyInputs(): iterable
    {
        yield ['15000', 1500000];
        yield ['15,000.50', 1500050];
        yield ['₦15k', 1500000];
        yield ['NGN 1.5m', 150000000];
        yield ['N2,500', 250000];
        yield ['0.005', 1];
        yield ['.5', 50];
        yield ['15000.999', 1500100];
        yield ['abc', null];
        yield ['', null];
        yield ['-5', null];
    }

    #[DataProvider('moneyInputs')]
    public function test_parses_human_money(string $input, ?int $expected): void
    {
        $this->assertSame($expected, Money::parseToMinor($input));
    }

    public function test_formats_minor_units(): void
    {
        $this->assertSame('₦15,000.00', Money::format(1500000));
        $this->assertSame('$0.05', Money::format(5, 'USD'));
        $this->assertSame(750, Money::percentToBps('7.5%'));
        $this->assertNull(Money::percentToBps('150'));
    }

    public function test_vat_exclusive_totals(): void
    {
        $t = (new TotalsCalculator)->calculate(
            [['description' => 'A', 'quantity' => 2, 'unit_price_minor' => 4500000], ['description' => 'B', 'quantity' => 1, 'unit_price_minor' => 1500000]],
            0, 350000, 750, false, 2000000,
        );
        $this->assertSame(10500000, $t->subtotalMinor);
        $this->assertSame(787500, $t->taxMinor);
        $this->assertSame(11637500, $t->totalMinor);
        $this->assertSame(9637500, $t->balanceDueMinor);
        $this->assertSame(DocumentStatus::PARTIAL, $t->statusFor(DocumentType::INVOICE));
    }

    public function test_vat_inclusive_extracts_tax_and_rounds_half_up(): void
    {
        $t = (new TotalsCalculator)->calculate([['description' => 'A', 'quantity' => 1, 'unit_price_minor' => 1075000]], 0, 0, 750, true, null);
        $this->assertSame(1075000, $t->totalMinor);
        $this->assertSame(75000, $t->taxMinor);

        $odd = (new TotalsCalculator)->calculate([['description' => 'A', 'quantity' => 1, 'unit_price_minor' => 333]], 0, 0, 750, false, null);
        $this->assertSame(25, $odd->taxMinor); // 24.975 → 25
    }

    public function test_discount_is_capped_and_paid_is_capped(): void
    {
        $t = (new TotalsCalculator)->calculate([['description' => 'A', 'quantity' => 1, 'unit_price_minor' => 1000]], 5000, 0, 0, false, 99999);
        $this->assertSame(1000, $t->discountMinor);
        $this->assertSame(0, $t->totalMinor);
        $this->assertSame(0, $t->amountPaidMinor);
    }
}
