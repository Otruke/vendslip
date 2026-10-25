<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next, string $level = 'admin'): Response
    {
        $user = $request->user();
        $allowed = $user instanceof User && $user->is_active
            && ($level === 'super' ? $user->isSuperAdmin() : $user->isPlatformAdmin());

        if (! $allowed) {
            if ($request->expectsJson()) {
                abort(403);
            }
            if ($user === null) {
                return redirect()->route('admin.login');
            }
            abort(403, 'Platform administrators only.');
        }

        return $next($request);
    }
}
