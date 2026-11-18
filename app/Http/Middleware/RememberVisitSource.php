<?php

namespace App\Http\Middleware;

use App\Support\Referrals;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zapamiętuje w sesji gościa, skąd przyszedł i czy z linku zapraszającego (App\Support\Referrals).
 */
class RememberVisitSource
{
    public function handle(Request $request, Closure $next): Response
    {
        Referrals::remember($request);

        return $next($request);
    }
}
