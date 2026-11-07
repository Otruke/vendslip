<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Pricing\FxRates;
use Illuminate\Console\Command;

final class RefreshFxRates extends Command
{
    protected $signature = 'vendslip:fx-refresh';

    protected $description = 'Fetch the latest exchange rates used to price plans in other currencies';

    public function handle(FxRates $fx): int
    {
        $count = $fx->refresh();
        if ($count === null) {
            $this->warn('Could not fetch exchange rates; keeping the last saved ones.');

            return self::FAILURE;
        }
        $this->info("Saved {$count} exchange rates.");

        return self::SUCCESS;
    }
}
