<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireUserRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!$request->user() || $request->user()->role !== $role) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'You are not authorized to perform this action.'], 403);
            }

            return response()->view('errors.403', status: 403);
        }

        return $next($request);
    }
}
