<?php

namespace LaraUtilX\Utilities;

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

    /**
     * Paginate an Eloquent or Query Builder instance.
     *
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param int $perPage
     * @param int|null $page
     * @param array $options
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function paginateQuery($query, int $perPage, ?int $page = null, array $options = [])
    {
        $page = $page ?: (request()->input('page', 1));
        return $query->paginate($perPage, ['*'], 'page', $page)->appends($options);
    }
}
