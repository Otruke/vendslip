<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Slash\DraftPayload;
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
        $this->assertSame(['description' => 'Leather Sneakers', 'quantity' => 2, 'unit_price_minor' => 2850000], $r->items[0]->toArray());
        $this->assertSame(500000, $r->items[1]->unitPriceMinor);
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
        $this->assertSame('help', $this->parser->parse('/start@NetSlipBot')->command);
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
}
