<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\Antibots\Detector;

class AntiBotMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function __construct(protected Detector $detector) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $this->detector->run();

        if ($response) {
            return $response;
        }
        return $next($request);
    }
}
