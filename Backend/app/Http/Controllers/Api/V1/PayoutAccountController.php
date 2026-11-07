<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Models\PayoutAccount;
use App\Services\Payouts\PayoutAccounts;
use App\Support\Currencies;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Bank accounts and crypto wallets on slips. Changes need the person's password and alert the owners. */
final class PayoutAccountController extends ApiController
{
    public function __construct(private readonly PayoutAccounts $payouts) {}

    public function index(Request $request): JsonResponse
    {
        $merchant = $this->merchant($request);

        return response()->json([
            'data' => $merchant->payoutAccounts()->get()->map(fn (PayoutAccount $a): array => $this->present($a))->values(),
            'crypto' => PayoutAccounts::CRYPTO,
            'max' => PayoutAccounts::MAX_ACCOUNTS,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission($request, Permission::SETTINGS_MANAGE);
        $data = $this->validated($request, null);
        $account = $this->payouts->create($this->merchant($request), $this->user($request), $data);

        return response()->json(['data' => $this->present($account)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizePermission($request, Permission::SETTINGS_MANAGE);
        $account = $this->find($request, $id);
        $data = $this->validated($request, $account);
        $account = $this->payouts->update($this->merchant($request), $this->user($request), $account, $data);

        return response()->json(['data' => $this->present($account)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->authorizePermission($request, Permission::SETTINGS_MANAGE);
        $account = $this->find($request, $id);
        $request->validate(['current_password' => ['required', 'string', 'current_password:web']], $this->messages());
        $this->payouts->delete($this->merchant($request), $this->user($request), $account);

        return response()->json(['ok' => true]);
    }

    private function find(Request $request, int $id): PayoutAccount
    {
        return PayoutAccount::query()->where('merchant_id', $this->merchant($request)->id)->findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?PayoutAccount $existing): array
    {
        $kind = $existing->kind ?? strtoupper((string) $request->input('kind'));
        $request->merge(['kind' => $kind]);
        $bank = $kind === PayoutAccount::BANK;
        $crypto = $kind === PayoutAccount::CRYPTO;

        $validator = validator($request->all(), [
            'current_password' => ['required', 'string', 'current_password:web'],
            'kind' => ['required', Rule::in([PayoutAccount::BANK, PayoutAccount::CRYPTO])],
            'label' => ['nullable', 'string', 'max:60'],
            'show_on_slips' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'bank_name' => [Rule::requiredIf($bank), 'nullable', 'string', 'max:120'],
            'account_number' => [Rule::requiredIf($bank), 'nullable', 'string', 'max:34', 'regex:/^[A-Za-z0-9 \-]{6,34}$/'],
            'account_name' => [Rule::requiredIf($bank), 'nullable', 'string', 'max:160'],
            'currency' => ['nullable', 'string', 'size:3', Rule::in(Currencies::codes())],
            'asset' => [Rule::requiredIf($crypto), 'nullable', 'string', Rule::in(array_keys(PayoutAccounts::CRYPTO))],
            'network' => [Rule::requiredIf($crypto), 'nullable', 'string'],
            'wallet_address' => [Rule::requiredIf($crypto), 'nullable', 'string', 'max:128'],
        ], [
            ...$this->messages(),
            'account_number.regex' => 'Enter the account number as printed (6–34 letters or digits).',
        ]);
        $validator->after(function (Validator $v) use ($request, $crypto): void {
            if (! $crypto || $v->errors()->isNotEmpty()) {
                return;
            }
            $asset = strtoupper((string) $request->input('asset'));
            $network = strtoupper((string) $request->input('network'));
            if (! in_array($network, PayoutAccounts::CRYPTO[$asset] ?? [], true)) {
                $v->errors()->add('network', "{$asset} isn't accepted on that network here.");

                return;
            }
            if (! PayoutAccounts::addressLooksValid($network, trim((string) $request->input('wallet_address')))) {
                $v->errors()->add('wallet_address', "That doesn't look like a {$network} address. Check you copied the whole address for the right network.");
            }
        });

        /** @var array<string, mixed> $data */
        $data = $validator->validate();
        unset($data['current_password']);

        return $data;
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'current_password.required' => 'Enter your password to change where customers pay you.',
            'current_password.current_password' => 'Your password is incorrect.',
        ];
    }

    /** @return array<string, mixed> */
    private function present(PayoutAccount $a): array
    {
        return [
            'id' => $a->id,
            ...$a->toPublicArray(),
            'show_on_slips' => $a->show_on_slips,
            'is_default' => $a->is_default,
            'updated_at' => $a->updated_at->toIso8601String(),
        ];
    }
}
