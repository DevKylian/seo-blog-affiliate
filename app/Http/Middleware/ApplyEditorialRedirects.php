<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyEditorialRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && ! $request->is('admin/*', 'livewire/*', 'build/*')) {
            $path = '/'.ltrim($request->path(), '/');
            $redirect = Redirect::query()->where('from_path', $path)->where('active', true)->first();
            if ($redirect && $redirect->to_path !== $path) {
                return redirect($redirect->to_path, $redirect->status_code);
            }
        }

        return $next($request);
    }
}
