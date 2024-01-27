<?php

namespace omarchouman\LaraUtilX\Utilities;

use Illuminate\Support\Collection;

class FilteringUtil
{
    /**
     * Filter a collection by a given key value pair.
     *
     * @param Collection $items
     * @param string $name
     * @param string $operator
     * @param string $value
     * @return Collection
     */
    public static function filter(Collection $items, string $name, string $operator, $value)
    {
        return $items->filter(function ($item) use ($name, $operator, $value) {
            switch ($operator) {
                case 'equals':
                    return data_get($item, $name) == $value;
                case 'not_equals':
                    return data_get($item, $name) != $value;
                case 'contains':
                    return stripos(data_get($item, $name), $value) !== false;
                case 'not_contains':
                    return stripos(data_get($item, $name), $value) === false;
                case 'starts_with':
                    return stripos(data_get($item, $name), $value) === 0;
                case 'ends_with':
                    return stripos(data_get($item, $name), $value) === strlen(data_get($item, $name)) - strlen($value);
                default:
                    return false;
            }
        });
    }
}