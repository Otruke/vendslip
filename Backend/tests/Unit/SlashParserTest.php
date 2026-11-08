<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Slash\DraftPayload;
use App\Services\Slash\ParsedItem;
use App\Services\Slash\SlashCommandParser;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class SlashParserTest extends TestCase
{
    private SlashCommandParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SlashCommandParser;
    }

    public function test_all_in_one_message_keeps_commas_inside_values(): void
    {
        $r = $this->parser->parse('/name = Chief Emeka Nwosu, /amount = 15000, /address = 42 Wuse II, Abuja');
        $this->assertSame(['name' => 'Chief Emeka Nwosu', 'amount' => '15000', 'address' => '42 Wuse II, Abuja'], $r->fields);
    }

    public function test_multiline_items_and_aliases(): void
    {
        $r = $this->parser->parse("/type = invoice\n/customer = Ada\n/item = 2x Leather Sneakers @ 28,500 | 1x Belt @ 5k\n/delivery = 3500\n/tax = 7.5");
        $this->assertSame('invoice', $r->fields['type']);
        $this->assertSame('Ada', $r->fields['name']);
        $this->assertSame('3500', $r->fields['shipping']);
        $this->assertSame('7.5', $r->fields['vat']);
        $this->assertCount(2, $r->items);
        $this->assertSame(['description' => 'Leather Sneakers', 'quantity' => 2, 'unit_price_minor' => 2850000, 'list_price_minor' => null], $r->items[0]->toArray());
        $this->assertSame(500000, $r->items[1]->unitPriceMinor);
    }

    public function test_item_list_price_suffix(): void
    {
        $r = $this->parser->parse('/item = 2x Sneakers @ 25,000 was 30,000 | Cap @ 4k rrp 5k | Belt @ 6k was 5k');
        $this->assertSame(2500000, $r->items[0]->unitPriceMinor);
        $this->assertSame(3000000, $r->items[0]->listPriceMinor);
        $this->assertSame('Sneakers', $r->items[0]->description);
        $this->assertSame(500000, $r->items[1]->listPriceMinor);
    }

    public function test_item_shapes(): void
    {
        $cases = [
            'Shoe @ 15000 x 2' => ['Shoe', 2, 1500000],
            'Shoe x3 @ 1k' => ['Shoe', 3, 100000],
            '3 Caps @ 2000' => ['Caps', 3, 200000],
            'Bag - 12000' => ['Bag', 1, 1200000],
            'Kaftan 45000' => ['Kaftan', 1, 4500000],
            '2x SKU-SNK-02' => ['SKU-SNK-02', 2, null],
        ];
        foreach ($cases as $line => [$desc, $qty, $price]) {
            $item = $this->parser->parseItem($line);
            $this->assertNotNull($item, $line);
            $this->assertSame([$desc, $qty, $price], [$item->description, $item->quantity, $item->unitPriceMinor], $line);
        }
        $this->assertNull($this->parser->parseItem('Shoe @ abc'));
    }

    public function test_slashes_inside_values_do_not_split(): void
    {
        $r = $this->parser->parse('/address = Plot 5/6 Lekki/Ajah, /due = 12/11/2026');
        $this->assertSame('Plot 5/6 Lekki/Ajah', $r->fields['address']);
        $this->assertSame('12/11/2026', $r->fields['due']);
    }

    public function test_bare_commands(): void
    {
        $this->assertSame('confirm', $this->parser->parse('/confirm')->command);
        $link = $this->parser->parse('/link AB12CD34');
        $this->assertSame(['link', 'AB12CD34'], [$link->command, $link->commandArgument]);
        $this->assertSame('help', $this->parser->parse('/start@VendSlipBot')->command);
        $this->assertNull($this->parser->parse('hello')->command);
    }

    public function test_draft_payload_accumulates_and_validates(): void
    {
        $today = CarbonImmutable::create(2026, 10, 24);
        [$p, $e] = DraftPayload::empty()->apply($this->parser->parse('/name = Ada'), $today);
        $this->assertSame([], $e);
        $this->assertFalse($p->isReady());
        $this->assertSame(['/amount or /item'], $p->missing());

        [$p, $e] = $p->apply($this->parser->parse('/amount = 30k /discount = 10% /paid = 5000 /vat = 7.5 incl /due = 7d /phone = 0803 998 1122'), $today);
        $this->assertSame([], $e);
        $this->assertTrue($p->isReady());
        $draft = $p->toDraftData();
        $this->assertSame(300000, $draft->discount_minor);
        $this->assertSame(500000, $draft->amount_paid_minor);
        $this->assertSame(750, $draft->vat_rate_bps);
        $this->assertTrue($draft->vat_inclusive);
        $this->assertSame('08039981122', $draft->customer_phone);

        [, $errors] = $p->apply($this->parser->parse('/email = nope /type = memo /foo = 1'), $today);
        $this->assertCount(3, $errors);
    }

    public function test_due_date_formats(): void
    {
        $today = CarbonImmutable::create(2026, 10, 24);
        $this->assertSame('2026-10-31', DraftPayload::parseDueDate('7d', $today)?->toDateString());
        $this->assertSame('2026-11-12', DraftPayload::parseDueDate('12/11/2026', $today)?->toDateString());
        $this->assertSame('2026-11-30', DraftPayload::parseDueDate('2026-11-30', $today)?->toDateString());
        $this->assertNull(DraftPayload::parseDueDate('31/02/2026', $today));
    }

    public function test_item_discounts_per_unit_percent_and_whole_line(): void
    {
        $cases = [
            '2x Ankara @ 7500 less 500' => [2, 700_000, 750_000],          // 500 off each
            '2x Ankara @ 7500 less 500 each' => [2, 700_000, 750_000],
            '2x Ankara @ 7500 500 off' => [2, 700_000, 750_000],
            '2x Ankara @ 7.5k disc 1k' => [2, 650_000, 750_000],
            '2x Ankara @ 7500 less 10%' => [2, 675_000, 750_000],          // 10% off
            '2x Ankara @ 7500 -10%' => [2, 675_000, 750_000],
            'Lace @ 20000 off 15%' => [1, 1_700_000, 2_000_000],
            '2x Ankara @ 7500 less 1000 total' => [2, 700_000, 750_000],   // 1000 off the two together
            '4 Wrapper @ 3000 less 2000 for all' => [4, 250_000, 300_000],
            'Shoe 15000 less 1000' => [1, 1_400_000, 1_500_000],
        ];
        foreach ($cases as $line => [$qty, $unit, $list]) {
            $item = $this->parser->readItem($line);
            $this->assertInstanceOf(ParsedItem::class, $item, $line);
            $this->assertSame([$qty, $unit, $list], [$item->quantity, $item->unitPriceMinor, $item->listPriceMinor], $line);
        }

        // Words that merely contain "off" are still part of the name.
        $this->assertSame('Shoulder off top', $this->parser->parseItem('Shoulder off top @ 5000')?->description);
        $this->assertNull($this->parser->parseItem('Shoulder off top @ 5000')?->listPriceMinor);
    }

    public function test_item_discount_mistakes_get_a_clear_message(): void
    {
        $this->assertStringContainsString("doesn't split evenly", (string) $this->parser->readItem('3x Ankara @ 7500 less 1000 total'));
        $this->assertStringContainsString('/discount = 1000', (string) $this->parser->readItem('3x Ankara @ 7500 less 1000 total'));
        $this->assertStringContainsString('as much as the price', (string) $this->parser->readItem('Ankara @ 7500 less 7500'));
        $this->assertStringContainsString('less than 100%', (string) $this->parser->readItem('Ankara @ 7500 less 100%'));
        $this->assertStringContainsString('add the price', (string) $this->parser->readItem('Ankara less 500'));
        $this->assertStringContainsString('not both', (string) $this->parser->readItem('Cap @ 4000 was 5000 less 100'));

        $r = $this->parser->parse('/item = 3x Ankara @ 7500 less 1000 total | Gele @ 5000');
        $this->assertCount(1, $r->items);
        $this->assertCount(1, $r->errors);
    }
}
