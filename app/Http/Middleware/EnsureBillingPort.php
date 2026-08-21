<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBillingPort
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing')) {
            return $next($request);
        }

        $host = $request->getHost();
        $port = $request->getPort();

        if (! in_array($host, ['bill.xlinkbd.net', 'billing.bill.xlinkbd.net'], true) || $port !== 8081) {
            abort(404);
        }

        return $next($request);
    }
}
