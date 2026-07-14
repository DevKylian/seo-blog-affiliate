<?php

namespace App\Http\Middleware;

use App\Models\AdminAccessLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $user = $request->user();
        $response = $next($request);

        if ($user?->is_admin && ($request->is('admin*') || $request->is('livewire/update'))) {
            AdminAccessLog::query()->create([
                'user_id' => $user->id,
                'method' => $request->method(),
                'path' => '/'.$request->path(),
                'route_name' => $request->route()?->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                'status_code' => $response->getStatusCode(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'created_at' => now(),
            ]);
        }

        return $response;
    }
}
