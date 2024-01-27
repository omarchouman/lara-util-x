<?php

namespace omarchouman\LaraUtilX\Utilities;

use Illuminate\Pagination\LengthAwarePaginator;

class PaginationUtil
{
    /**
     * Paginate a collection.
     *
     * @param  array  $items
     * @param  int  $perPage
     * @param  int  $currentPage
     * @param  array  $options
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public static function paginate(array $items, int $perPage, int $currentPage, array $options = [])
    {
        $paginator = new LengthAwarePaginator(
            array_slice($items, ($currentPage - 1) * $perPage, $perPage),
            count($items),
            $perPage,
            $currentPage,
            $options
        );

        return $paginator;
    }
}
