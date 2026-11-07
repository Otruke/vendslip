<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Security\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every successful change (POST, PUT, PATCH, DELETE) is written to the audit log, even where a
 * controller has no specific entry of its own: who did what, to which record, from where. Bodies
 * are never stored, so passwords, keys and customer details stay out of the log.
 */
final class AuditTrail
{
    /** Writes that change nothing worth auditing. */
    private const array SKIP = ['composer/parse', 'notifications', 'auth/logout', 'auth/two-factor'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)
            || $response->getStatusCode() >= 400
            || $request->attributes->get(AuditLogger::LOGGED) === true
            // A web form that failed (validation or a business rule) redirects back with errors: nothing changed.
            || ($request->hasSession() && array_intersect(['errors', 'error'], (array) $request->session()->get('_flash.new', [])) !== [])) {
            return $response;
        }
        $route = $request->route();
        if ($route === null || Str::startsWith($route->uri(), array_map(static fn (string $s): string => 'api/v1/'.$s, self::SKIP))) {
            return $response;
        }

        $user = $request->user();
        $this->audit->log(self::eventFor($route->getActionName(), $route->getActionMethod()), $user instanceof User ? $user : null, null, [
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'subject' => array_filter($route->parameters(), static fn ($v): bool => is_scalar($v)) ?: null,
            'status' => $response->getStatusCode(),
        ]);

        return $response;
    }

    /** "App\…\DocumentController@store" → "document.created"; "…@creditNote" → "document.credit_note". */
    public static function eventFor(string $action, string $method): string
    {
        $controller = Str::of(Str::before(class_basename($action), '@'))->beforeLast('Controller')->snake()->toString();
        $verb = match ($method) {
            'store' => 'created',
            'update' => 'updated',
            'destroy' => 'deleted',
            default => Str::snake($method),
        };

        return ($controller !== '' ? $controller : 'action').'.'.$verb;
    }
}
