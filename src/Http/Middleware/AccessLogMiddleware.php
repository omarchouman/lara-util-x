<?php

namespace LaraUtilX\Http\Middleware;

use Closure;
use LaraUtilX\Models\AccessLog;

class AccessLogMiddleware
{
    public function handle($request, Closure $next)
    {
        $excluded = $this->excludedAttributes();
        $data     = $request->except($excluded);

        $logData = [
            'ip' => $request->ip() ?: null,
            'method' => $request->method() ?: null,
            'url' => $this->redactQueryString($request->fullUrl(), $excluded),
            'user_agent' => $request->header('User-Agent') ?: null,
            'request_data' => $data ? json_encode($data) : null,
        ];

        AccessLog::create($logData);

        return $next($request);
    }

    /**
     * Attributes kept out of the access log. Shares the audit trail's list so
     * credentials are redacted consistently wherever the package records a
     * request.
     */
    protected function excludedAttributes(): array
    {
        return config('lara-util-x.access_log.excluded_attributes')
            ?? config('lara-util-x.audit.excluded_attributes', []);
    }

    /**
     * Tokens are routinely passed in query strings, and fullUrl() would persist
     * them verbatim.
     */
    protected function redactQueryString(string $url, array $excluded): string
    {
        if ($excluded === [] || ! str_contains($url, '?')) {
            return $url;
        }

        [$base, $query] = explode('?', $url, 2);

        parse_str($query, $params);

        if ($params === []) {
            return $url;
        }

        foreach (array_keys($params) as $key) {
            if (in_array($key, $excluded, true)) {
                $params[$key] = '[redacted]';
            }
        }

        return $base . '?' . http_build_query($params);
    }
}
