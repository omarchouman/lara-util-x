<?php

namespace LaraUtilX\Tests\Feature\Utilities;

use LaraUtilX\Tests\TestCase;
use LaraUtilX\Utilities\CachingUtil;
use Illuminate\Support\Facades\Cache;

class CachingUtilFeatureTest extends TestCase
{
    private CachingUtil $cachingUtil;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cachingUtil = new CachingUtil(60, ['default']);
        Cache::flush();
    }

    public function test_caching_workflow_integration()
    {
        $key = 'integration_test';
        $data = ['user_id' => 123, 'name' => 'John Doe', 'email' => 'john@example.com'];
        
        // Cache the data
        $result = $this->cachingUtil->cache($key, $data);
        $this->assertEquals($data, $result);
        
        // Retrieve the data
        $retrieved = $this->cachingUtil->get($key);
        $this->assertEquals($data, $retrieved);
        
        // Verify it's actually cached
        $this->assertTrue(Cache::has($key));
        
        // Update the data
        $updatedData = ['user_id' => 123, 'name' => 'John Smith', 'email' => 'john.smith@example.com'];
        $this->cachingUtil->cache($key, $updatedData);
        
        // Verify the update
        $retrieved = $this->cachingUtil->get($key);
        $this->assertEquals($updatedData, $retrieved);
        
        // Clear the cache
        $this->cachingUtil->forget($key);
        $this->assertFalse(Cache::has($key));
    }

    public function test_caching_with_different_expiration_times()
    {
        $shortTermKey = 'short_term';
        $longTermKey = 'long_term';
        $data = ['test' => 'data'];
        
        // Cache with short expiration
        $this->cachingUtil->cache($shortTermKey, $data, 1); // 1 minute
        
        // Cache with long expiration
        $this->cachingUtil->cache($longTermKey, $data, 60); // 60 minutes
        
        // Both should be available immediately
        $this->assertTrue(Cache::has($shortTermKey));
        $this->assertTrue(Cache::has($longTermKey));
        
        // Verify data integrity
        $this->assertEquals($data, $this->cachingUtil->get($shortTermKey));
        $this->assertEquals($data, $this->cachingUtil->get($longTermKey));
    }

    public function test_caching_with_tags()
    {
        $userKey = 'user_123';
        $productKey = 'product_456';
        $userData = ['name' => 'John Doe'];
        $productData = ['name' => 'Test Product'];
        
        // Cache with different tags
        $this->cachingUtil->cache($userKey, $userData, null, ['users']);
        $this->cachingUtil->cache($productKey, $productData, null, ['products']);
        
        // Both should be cached
        $this->assertTrue(Cache::has($userKey));
        $this->assertTrue(Cache::has($productKey));
        
        // Verify data
        $this->assertEquals($userData, $this->cachingUtil->get($userKey));
        $this->assertEquals($productData, $this->cachingUtil->get($productKey));
    }

    public function test_caching_performance_with_large_data()
    {
        $key = 'large_data_test';
        $largeData = array_fill(0, 1000, [
            'id' => 1,
            'name' => 'Test Item',
            'description' => 'This is a test item with some description',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
        
        $startTime = microtime(true);
        
        // Cache large data
        $result = $this->cachingUtil->cache($key, $largeData);
        
        $cacheTime = microtime(true) - $startTime;
        
        $this->assertEquals($largeData, $result);
        $this->assertLessThan(1.0, $cacheTime); // Should cache in less than 1 second
        
        // Retrieve large data
        $startTime = microtime(true);
        $retrieved = $this->cachingUtil->get($key);
        $retrieveTime = microtime(true) - $startTime;
        
        $this->assertEquals($largeData, $retrieved);
        $this->assertLessThan(0.5, $retrieveTime); // Should retrieve in less than 0.5 seconds
    }

    public function test_caching_with_complex_data_structures()
    {
        $key = 'complex_data';
        $complexData = [
            'user' => [
                'id' => 123,
                'profile' => [
                    'name' => 'John Doe',
                    'settings' => [
                        'theme' => 'dark',
                        'notifications' => true,
                        'preferences' => [
                            'language' => 'en',
                            'timezone' => 'UTC',
                        ],
                    ],
                ],
            ],
            'permissions' => ['read', 'write', 'admin'],
            'metadata' => [
                'created_at' => now()->toDateTimeString(),
                'last_login' => now()->subHours(2)->toDateTimeString(),
            ],
        ];
        
        // Cache complex data
        $result = $this->cachingUtil->cache($key, $complexData);
        $this->assertEquals($complexData, $result);
        
        // Retrieve and verify
        $retrieved = $this->cachingUtil->get($key);
        $this->assertEquals($complexData, $retrieved);
        
        // Verify nested data integrity
        $this->assertEquals(123, $retrieved['user']['id']);
        $this->assertEquals('John Doe', $retrieved['user']['profile']['name']);
        $this->assertEquals('dark', $retrieved['user']['profile']['settings']['theme']);
        $this->assertTrue($retrieved['user']['profile']['settings']['notifications']);
        $this->assertEquals(['read', 'write', 'admin'], $retrieved['permissions']);
    }
}
