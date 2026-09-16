<?php

namespace LaraUtilX\Http\Middleware;

use Closure;
use Illuminate\Support\Arr;
use LaraUtilX\Models\AccessLog;

class AccessLogMiddleware
{
    public function handle($request, Closure $next)
    {
        $excluded = $this->excludedAttributes();
        $data     = $this->redact($request->all(), $excluded);

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
     * Remove excluded attributes from the request body.
     *
     * Matching is case-insensitive and applies at any depth, because
     * Request::except() only strips top-level keys and would leave a nested
     * user[password] in place. Entries written in dot notation are still
     * honoured, so a list may target one specific nested key.
     */
    protected function redact(array $data, array $excluded): array
    {
        if ($excluded === []) {
            return $data;
        }

        $dotted = array_values(array_filter($excluded, fn ($key) => str_contains($key, '.')));

        if ($dotted !== []) {
            Arr::forget($data, $dotted);
        }

        $names = array_map('strtolower', array_diff($excluded, $dotted));

        return $this->redactRecursive($data, $names);
    }

    private function redactRecursive(array $data, array $names): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $names, true)) {
                unset($data[$key]);
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->redactRecursive($value, $names);
            }
        }

        return $data;
    }

    /**
     * Tokens are routinely passed in query strings, and fullUrl() would persist
     * them verbatim. Parameter names are matched case-insensitively.
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

        $names = array_map('strtolower', $excluded);

        return $base . '?' . http_build_query($this->redactParams($params, $names));
    }

    private function redactParams(array $params, array $names): array
    {
        foreach ($params as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $names, true)) {
                $params[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $params[$key] = $this->redactParams($value, $names);
            }
        }

        return $params;
    }
}
