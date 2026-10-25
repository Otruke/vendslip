<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AuditSeverity;
use App\Http\Controllers\Controller;
use App\Models\SecurityAuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class SecurityLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'event' => ['nullable', 'string', 'max:96'],
            'severity' => ['nullable', Rule::enum(AuditSeverity::class)],
            'merchant' => ['nullable', 'uuid'],
        ]);
        $query = SecurityAuditLog::query()->with(['actor', 'merchant'])->latest('created_at')->latest('id');
        if (! empty($filters['event'])) {
            $query->where('event', 'like', str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters['event']).'%');
        }
        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }
        if (! empty($filters['merchant'])) {
            $query->where('merchant_id', $filters['merchant']);
        }

        return view('admin.security.index', [
            'logs' => $query->paginate(50)->withQueryString(),
            'filters' => $filters,
            'events' => SecurityAuditLog::query()->select('event')->distinct()->orderBy('event')->pluck('event'),
        ]);
    }
}
