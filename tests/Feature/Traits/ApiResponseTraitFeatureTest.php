<?php

namespace LaraUtilX\Tests\Feature\Traits;

use LaraUtilX\Tests\TestCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Config;

class ApiResponseTraitFeatureTest extends TestCase
{
    use \LaraUtilX\Traits\ApiResponseTrait;

    public function test_success_response_integration()
    {
        $data = [
            'users' => [
                ['id' => 1, 'name' => 'John Doe'],
                ['id' => 2, 'name' => 'Jane Smith'],
            ],
        ];
        $message = 'Users retrieved successfully';
        $meta = [
            'total' => 2,
            'page' => 1,
        ];
        
        $response = $this->successResponse($data, $message, 200, $meta);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        $responseData = $response->getData(true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals($message, $responseData['message']);
        $this->assertEquals($data, $responseData['data']);
        $this->assertEquals($meta, $responseData['meta']);
    }

    public function test_error_response_integration()
    {
        $message = 'Validation failed';
        $errors = [
            'email' => ['The email field is required.'],
            'password' => ['The password field is required.'],
        ];
        $debug = [
            'request_data' => ['email' => '', 'password' => ''],
            'validation_rules' => ['email' => 'required', 'password' => 'required'],
        ];
        
        $response = $this->errorResponse($message, 422, $errors, $debug);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());
        
        $responseData = $response->getData(true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals($message, $responseData['message']);
        $this->assertEquals($errors, $responseData['errors']);
        $this->assertEquals($debug, $responseData['debug']);
    }

    public function test_paginated_response_integration()
    {
        // Create a mock paginator with realistic data
        $items = collect(range(1, 25))->map(function ($i) {
            return [
                'id' => $i,
                'name' => "Item {$i}",
                'created_at' => now()->subDays($i)->toDateTimeString(),
            ];
        });
        
        $paginator = new LengthAwarePaginator(
            $items->forPage(1, 10),
            25,
            10,
            1,
            ['path' => '/api/items']
        );
        
        $response = $this->paginatedResponse($paginator, 'Items retrieved successfully');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        $responseData = $response->getData(true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Items retrieved successfully', $responseData['message']);
        $this->assertCount(10, $responseData['data']);
        $this->assertArrayHasKey('pagination', $responseData['meta']);
        
        $pagination = $responseData['meta']['pagination'];
        $this->assertEquals(25, $pagination['total']);
        $this->assertEquals(10, $pagination['count']);
        $this->assertEquals(10, $pagination['per_page']);
        $this->assertEquals(1, $pagination['current_page']);
        $this->assertEquals(3, $pagination['total_pages']);
    }

    public function test_exception_response_integration()
    {
        $exception = new \InvalidArgumentException('Invalid parameter provided');
        $exception->setTrace([
            ['file' => '/app/controllers/TestController.php', 'line' => 42],
            ['file' => '/app/routes/web.php', 'line' => 15],
        ]);
        
        $response = $this->exceptionResponse($exception, 400);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        
        $responseData = $response->getData(true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Internal server error.', $responseData['message']);
        $this->assertArrayHasKey('debug', $responseData);
        $this->assertEquals(\InvalidArgumentException::class, $responseData['debug']['exception']);
        $this->assertEquals('Invalid parameter provided', $responseData['debug']['message']);
        $this->assertIsArray($responseData['debug']['trace']);
    }

    public function test_debug_mode_affects_error_response()
    {
        // Test with debug enabled
        Config::set('app.debug', true);
        $response = $this->errorResponse('Test error', 500, [], ['debug_info' => 'sensitive_data']);
        $responseData = $response->getData(true);
        $this->assertArrayHasKey('debug', $responseData);
        
        // Test with debug disabled
        Config::set('app.debug', false);
        $response = $this->errorResponse('Test error', 500, [], ['debug_info' => 'sensitive_data']);
        $responseData = $response->getData(true);
        $this->assertArrayNotHasKey('debug', $responseData);
    }

    public function test_response_headers_are_correct()
    {
        $response = $this->successResponse(['test' => 'data']);
        
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
        $this->assertNotNull($response->headers->get('Date'));
    }

    public function test_large_data_response_performance()
    {
        $largeData = array_fill(0, 1000, [
            'id' => 1,
            'name' => 'Test Item',
            'description' => 'This is a test item with some description',
            'metadata' => [
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ],
        ]);
        
        $startTime = microtime(true);
        $response = $this->successResponse($largeData);
        $responseTime = microtime(true) - $startTime;
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertLessThan(1.0, $responseTime); // Should respond in less than 1 second
        
        $responseData = $response->getData(true);
        $this->assertCount(1000, $responseData['data']);
    }

    public function test_nested_data_integrity()
    {
        $nestedData = [
            'user' => [
                'id' => 123,
                'profile' => [
                    'name' => 'John Doe',
                    'settings' => [
                        'theme' => 'dark',
                        'notifications' => [
                            'email' => true,
                            'push' => false,
                        ],
                    ],
                ],
            ],
            'permissions' => ['read', 'write'],
        ];
        
        $response = $this->successResponse($nestedData);
        $responseData = $response->getData(true);
        
        $this->assertEquals($nestedData, $responseData['data']);
        $this->assertEquals(123, $responseData['data']['user']['id']);
        $this->assertEquals('John Doe', $responseData['data']['user']['profile']['name']);
        $this->assertEquals('dark', $responseData['data']['user']['profile']['settings']['theme']);
        $this->assertTrue($responseData['data']['user']['profile']['settings']['notifications']['email']);
        $this->assertFalse($responseData['data']['user']['profile']['settings']['notifications']['push']);
    }
}
