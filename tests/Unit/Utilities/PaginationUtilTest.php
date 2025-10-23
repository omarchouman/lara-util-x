<?php

namespace LaraUtilX\Tests\Unit\Utilities;

use LaraUtilX\Tests\TestCase;
use LaraUtilX\Utilities\PaginationUtil;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaginationUtilTest extends TestCase
{
    public function test_can_paginate_array()
    {
        $items = range(1, 25); // 25 items
        $perPage = 10;
        $currentPage = 1;
        
        $paginator = PaginationUtil::paginate($items, $perPage, $currentPage);
        
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertEquals(25, $paginator->total());
        $this->assertEquals(10, $paginator->perPage());
        $this->assertEquals(1, $paginator->currentPage());
        $this->assertCount(10, $paginator->items());
        $this->assertEquals([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $paginator->items());
    }

    public function test_can_paginate_array_second_page()
    {
        $items = range(1, 25); // 25 items
        $perPage = 10;
        $currentPage = 2;
        
        $paginator = PaginationUtil::paginate($items, $perPage, $currentPage);
        
        $this->assertEquals(2, $paginator->currentPage());
        $this->assertCount(10, $paginator->items());
        $this->assertEquals([11, 12, 13, 14, 15, 16, 17, 18, 19, 20], $paginator->items());
    }

    public function test_can_paginate_array_last_page()
    {
        $items = range(1, 25); // 25 items
        $perPage = 10;
        $currentPage = 3;
        
        $paginator = PaginationUtil::paginate($items, $perPage, $currentPage);
        
        $this->assertEquals(3, $paginator->currentPage());
        $this->assertCount(5, $paginator->items());
        $this->assertEquals([21, 22, 23, 24, 25], $paginator->items());
    }

    public function test_can_paginate_array_with_options()
    {
        $items = range(1, 10);
        $perPage = 5;
        $currentPage = 1;
        $options = ['path' => '/test', 'pageName' => 'p'];
        
        $paginator = PaginationUtil::paginate($items, $perPage, $currentPage, $options);
        
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertEquals(10, $paginator->total());
    }

    public function test_handles_empty_array()
    {
        $items = [];
        $perPage = 10;
        $currentPage = 1;
        
        $paginator = PaginationUtil::paginate($items, $perPage, $currentPage);
        
        $this->assertEquals(0, $paginator->total());
        $this->assertCount(0, $paginator->items());
    }

    public function test_handles_page_beyond_available_data()
    {
        $items = range(1, 5);
        $perPage = 10;
        $currentPage = 2;
        
        $paginator = PaginationUtil::paginate($items, $perPage, $currentPage);
        
        $this->assertEquals(5, $paginator->total());
        $this->assertCount(0, $paginator->items());
    }

    public function test_can_paginate_query_builder()
    {
        // Create a mock query builder
        $query = $this->createMock(Builder::class);
        $query->method('paginate')->willReturn(new LengthAwarePaginator(
            [1, 2, 3, 4, 5],
            10,
            2,
            1,
            ['path' => '/test']
        ));
        
        $perPage = 2;
        $page = 1;
        $options = ['path' => '/test'];
        
        $paginator = PaginationUtil::paginateQuery($query, $perPage, $page, $options);
        
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
    }

    public function test_paginate_query_uses_request_page_when_no_page_provided()
    {
        // Mock the request
        $request = Request::create('/test', 'GET', ['page' => 3]);
        $this->app->instance('request', $request);
        
        $query = $this->createMock(Builder::class);
        $query->method('paginate')->willReturn(new LengthAwarePaginator(
            [1, 2, 3, 4, 5],
            10,
            2,
            3,
            ['path' => '/test']
        ));
        
        $perPage = 2;
        $options = ['path' => '/test'];
        
        $paginator = PaginationUtil::paginateQuery($query, $perPage, null, $options);
        
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
    }

    public function test_paginate_query_defaults_to_page_1_when_no_request_page()
    {
        // Mock the request with no page parameter
        $request = Request::create('/test', 'GET', []);
        $this->app->instance('request', $request);
        
        $query = $this->createMock(Builder::class);
        $query->method('paginate')->willReturn(new LengthAwarePaginator(
            [1, 2, 3, 4, 5],
            10,
            2,
            1,
            ['path' => '/test']
        ));
        
        $perPage = 2;
        $options = ['path' => '/test'];
        
        $paginator = PaginationUtil::paginateQuery($query, $perPage, null, $options);
        
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
    }
}
