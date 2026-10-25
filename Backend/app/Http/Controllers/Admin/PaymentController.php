<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'purpose' => ['nullable', Rule::enum(PaymentPurpose::class)],
            'gateway' => ['nullable', Rule::enum(PaymentGateway::class)],
            'q' => ['nullable', 'string', 'max:64'],
        ]);
        $query = PaymentTransaction::query()->with('merchant')->latest();
        foreach (['status', 'purpose', 'gateway'] as $key) {
            if (! empty($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }
        if (! empty($filters['q'])) {
            $query->where(static fn ($q) => $q->where('reference', 'ilike', '%'.$filters['q'].'%')->orWhere('gateway_reference', 'ilike', '%'.$filters['q'].'%'));
        }

        return view('admin.payments.index', ['payments' => $query->paginate(30)->withQueryString(), 'filters' => $filters]);
    }
}
