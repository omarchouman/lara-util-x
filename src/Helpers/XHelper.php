<?php

namespace omarchouman\LaraUtilX\Helpers;

use Illuminate\Support\Str;
use Carbon\Carbon;

class XHelper
{
    // ------------------------
    // Array Helpers
    // ------------------------
    
    public static function arrayTrim(array $array): array
    {
        return array_map(fn($value) => is_string($value) ? trim($value) : $value, $array);
    }

    public static function arrayFlatten(array $array): array
    {
        return iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveArrayIterator($array)), false);
    }

    // ------------------------
    // String Helpers
    // ------------------------
    
    public static function strBetween(string $string, string $start, string $end): ?string
    {
        $start = preg_quote($start, '/');
        $end = preg_quote($end, '/');

        $pattern = "/$start(.*?)$end/";
        preg_match($pattern, $string, $matches);
        
        return $matches[1] ?? null;
    }

    public static function strSlugify(string $string): string
    {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string), '-'));
    }

    // ------------------------
    // Date Helpers
    // ------------------------
    
    public static function carbonParse($date, $format = 'Y-m-d H:i:s'): ?string
    {
        return Carbon::parse($date)->format($format);
    }

    public static function carbonHumanDiff($date): string
    {
        return Carbon::parse($date)->diffForHumans();
    }

    // ------------------------
    // Miscellaneous Helpers
    // ------------------------

    public static function uuid(): string
    {
        return Str::uuid()->toString();
    }

    // public static function isJson(string $string): bool
    // {
    //     $decoded = json_decode($string, true);
        
    //     return json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded));
    // }
}
