<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CheckAccess
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param $userType
     * @return JsonResponse|Response
     */
    public function handle(Request $request, Closure $next, $userType): JsonResponse|Response
    {
        if (auth()->user()->type == $userType) {
            return $next($request);
        }

        return response()->json(['You do not have permission to access for this page.']);
    }
}
