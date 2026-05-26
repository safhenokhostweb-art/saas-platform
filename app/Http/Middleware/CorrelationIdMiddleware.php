<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\Telemetry\Tracing\RequestTraceManager;
use SaaSPlatform\CoreUtils\Telemetry\Tracing\RuntimeRequestProfiler;

class CorrelationIdMiddleware
{
    public function __construct(
        private RequestTraceManager   $traceManager,
        private RuntimeRequestProfiler $profiler
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $this->traceManager->initFromHeaders($request->headers->all());
        $this->profiler->start();

        $response = $next($request);

        foreach ($this->traceManager->propagationHeaders() as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}
