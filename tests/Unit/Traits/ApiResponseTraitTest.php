<?php

namespace LaraUtilX\Tests\Unit\Traits;

use LaraUtilX\Tests\TestCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class ApiResponseTraitTest extends TestCase
{
    use \LaraUtilX\Traits\ApiResponseTrait;

    public function test_can_send_success_response()
    {
        $data = ['test' => 'data'];
        $message = 'Test success message';
        $statusCode = 200;
        $meta = ['meta_key' => 'meta_value'];
        
        $response = $this->successResponse($data, $message, $statusCode, $meta);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals($statusCode, $response->getStatusCode());
        
        $responseData = $response->getData(true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals($message, $responseData['message']);
        $this->assertEquals($data, $responseData['data']);
        $this->assertEquals($meta, $responseData['meta']);
    }

    public function test_success_response_with_defaults()
    {
        $response = $this->successResponse();
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        $responseData = $response->getData(true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Request successful.', $responseData['message']);
        $this->assertNull($responseData['data']);
        $this->assertEmpty($responseData['meta']);
    }

    public function test_can_send_error_response()
    {
        $message = 'Test error message';
        $statusCode = 400;
        $errors = ['field' => ['Error message']];
        $debug = ['debug_key' => 'debug_value'];
        
        $response = $this->errorResponse($message, $statusCode, $errors, $debug);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals($statusCode, $response->getStatusCode());
        
        $responseData = $response->getData(true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals($message, $responseData['message']);
        $this->assertEquals($errors, $responseData['errors']);
        
        // Debug should be present when app.debug is true (default in tests)
        if (config('app.debug')) {
            $this->assertArrayHasKey('debug', $responseData);
            $this->assertEquals($debug, $responseData['debug']);
        }
    }

    public function test_error_response_with_defaults()
    {
        $response = $this->errorResponse();
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        
        $responseData = $response->getData(true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Something went wrong.', $responseData['message']);
        $this->assertEmpty($responseData['errors']);
        $this->assertArrayNotHasKey('debug', $responseData);
    }

    public function test_error_response_hides_debug_in_production()
    {
        Config::set('app.debug', false);
        
        $debug = ['debug_key' => 'debug_value'];
        $response = $this->errorResponse('Error', 500, [], $debug);
        
        $responseData = $response->getData(true);
        $this->assertArrayNotHasKey('debug', $responseData);
    }

    public function test_can_handle_exception_response()
    {
        $exception = new \Exception('Test exception message');
        $statusCode = 500;
        
        // Mock Log facade
        Log::shouldReceive('error')
            ->with($exception->getMessage(), \Mockery::type('array'))
            ->once();
        
        $response = $this->exceptionResponse($exception, $statusCode);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals($statusCode, $response->getStatusCode());
        
        $responseData = $response->getData(true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Internal server error.', $responseData['message']);
        
        // Debug should be present when app.debug is true (default in tests)
        if (config('app.debug')) {
            $this->assertArrayHasKey('debug', $responseData);
            $this->assertEquals(\Exception::class, $responseData['debug']['exception']);
            $this->assertEquals('Test exception message', $responseData['debug']['message']);
        }
    }

    public function test_can_send_paginated_response()
    {
        // Create a real paginator instance
        $items = collect([1, 2, 3]);
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            10,
            5,
            1,
            ['path' => '/test']
        );
        
        $message = 'Paginated data fetched';
        $response = $this->paginatedResponse($paginator, $message);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        $responseData = $response->getData(true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals($message, $responseData['message']);
        $this->assertEquals([1, 2, 3], $responseData['data']);
        $this->assertArrayHasKey('pagination', $responseData['meta']);
        
        $pagination = $responseData['meta']['pagination'];
        $this->assertEquals(10, $pagination['total']);
        $this->assertEquals(3, $pagination['count']);
        $this->assertEquals(5, $pagination['per_page']);
        $this->assertEquals(1, $pagination['current_page']);
        $this->assertEquals(2, $pagination['total_pages']);
    }

    public function test_paginated_response_with_default_message()
    {
        $items = collect([1, 2, 3]);
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            10,
            5,
            1,
            ['path' => '/test']
        );
        
        $response = $this->paginatedResponse($paginator);
        
        $responseData = $response->getData(true);
        $this->assertEquals('Data fetched successfully.', $responseData['message']);
    }
}
