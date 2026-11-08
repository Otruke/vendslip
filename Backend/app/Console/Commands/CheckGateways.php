<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Merchant;
use App\Services\Payments\GatewayCredentials;
use App\Services\Settings\PlatformSettings;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Checks that the Flutterwave and NOWPayments keys work (read-only calls, no money moves), says
 * whether they are test or live keys, and prints the webhook URLs to paste into each dashboard.
 */
final class CheckGateways extends Command
{
    protected $signature = 'vendslip:check-gateways {--merchant= : Check a business\'s own invoice-payment keys (merchant id) instead of VendSlip\'s}';

    protected $description = 'Check the payment gateway keys and show the webhook URLs';

    public function handle(PlatformSettings $settings): int
    {
        $merchantId = $this->option('merchant');
        if (is_string($merchantId) && $merchantId !== '') {
            $merchant = Merchant::query()->find($merchantId);
            if ($merchant === null) {
                $this->error('No business with that id.');

                return self::FAILURE;
            }
            $credentials = GatewayCredentials::merchant($merchant);
            $suffix = '/merchants/'.$merchant->id;
            $this->info("Checking {$merchant->name}'s own keys (invoice payments)");
        } else {
            $credentials = GatewayCredentials::platform($settings);
            $suffix = '';
            $this->info('Checking VendSlip\'s keys (plans and credit packs)');
        }

        $ok = $this->flutterwave($credentials, $suffix);
        $ok = $this->nowPayments($credentials, $suffix) && $ok;

        $appUrl = (string) config('app.url');
        if (! str_starts_with($appUrl, 'https://')) {
            $this->newLine();
            $this->warn("APP_URL is {$appUrl}. Gateways can only reach a public HTTPS address: set APP_URL to your tunnel or live domain.");
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function flutterwave(GatewayCredentials $credentials, string $suffix): bool
    {
        $this->newLine();
        $this->line('<options=bold>Flutterwave</>');
        $secret = $credentials->flutterwaveSecretKey;
        if ($secret === null) {
            $this->line('  Not set up (no secret key).');

            return true;
        }
        $this->line('  Mode: '.(str_contains($secret, '_TEST') ? 'TEST keys (no real money)' : 'LIVE keys (real money)'));
        try {
            $response = Http::baseUrl((string) config('vendslip.flutterwave.base_url'))->withToken($secret)->acceptJson()->timeout(15)->get('/banks/NG');
            $valid = $response->successful();
            $this->line('  Secret key: '.($valid ? '<fg=green>accepted</>' : '<fg=red>rejected ('.$response->status().' '.$response->json('message', '').')</>'));
        } catch (ConnectionException) {
            $valid = false;
            $this->line('  Secret key: <fg=red>could not reach Flutterwave (network)</>');
        }
        $this->line('  Secret hash: '.($credentials->flutterwaveSecretHash !== null ? 'set' : '<fg=red>missing: webhooks will be refused</>'));
        $this->line('  Webhook URL: '.url('/api/v1/webhooks/flutterwave'.$suffix));

        return $valid && $credentials->flutterwaveSecretHash !== null;
    }

    private function nowPayments(GatewayCredentials $credentials, string $suffix): bool
    {
        $this->newLine();
        $this->line('<options=bold>NOWPayments</>');
        $key = $credentials->nowPaymentsApiKey;
        if ($key === null) {
            $this->line('  Not set up (no API key).');

            return true;
        }
        $base = (string) config('vendslip.nowpayments.base_url');
        $this->line('  Server: '.$base.(str_contains($base, 'sandbox') ? ' (SANDBOX)' : ' (LIVE)'));
        try {
            $status = Http::baseUrl($base)->acceptJson()->timeout(15)->get('/status');
            $this->line('  API status: '.($status->json('message') === 'OK' ? '<fg=green>OK</>' : '<fg=red>'.$status->status().'</>'));
            $response = Http::baseUrl($base)->withHeaders(['x-api-key' => $key])->acceptJson()->timeout(15)->get('/merchant/coins');
            $valid = $response->successful();
            $coins = $response->json('selectedCurrencies');
            $this->line('  API key: '.($valid ? '<fg=green>accepted</>' : '<fg=red>rejected ('.$response->status().' '.$response->json('message', '').')</>'));
            if ($valid && is_array($coins)) {
                $this->line('  Coins you accept: '.($coins === [] ? 'none chosen yet (choose them in the NOWPayments dashboard)' : implode(', ', array_slice($coins, 0, 12)).(count($coins) > 12 ? ' …' : '')));
            }
        } catch (ConnectionException) {
            $valid = false;
            $this->line('  API key: <fg=red>could not reach NOWPayments (network)</>');
        }
        $this->line('  IPN secret: '.($credentials->nowPaymentsIpnSecret !== null ? 'set' : '<fg=red>missing: payment notifications will be refused</>'));
        $this->line('  IPN URL: '.url('/api/v1/webhooks/nowpayments'.$suffix).' (sent with each invoice; nothing to paste)');

        return $valid && $credentials->nowPaymentsIpnSecret !== null;
    }
}
