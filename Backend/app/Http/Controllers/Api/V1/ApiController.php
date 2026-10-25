<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    protected function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    protected function merchant(Request $request): Merchant
    {
        $merchant = $this->user($request)->merchant;
        abort_unless($merchant instanceof Merchant, 403);

        return $merchant;
    }

    protected function authorizePermission(Request $request, Permission $permission): void
    {
        abort_unless($this->user($request)->can($permission->value), 403, 'Your role does not allow this action.');
    }
}
