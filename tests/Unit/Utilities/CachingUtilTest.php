<?php

namespace LaraUtilX\Tests\Unit\Utilities;

use LaraUtilX\Tests\TestCase;
use LaraUtilX\Utilities\CachingUtil;
use Illuminate\Support\Facades\Cache;
use Illuminate\Cache\TaggableStore;

class CachingUtilTest extends TestCase
{
    private CachingUtil $cachingUtil;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cachingUtil = new CachingUtil(60, ['default']);
        Cache::flush();
    }

    public function test_can_cache_data_with_default_expiration()
    {
        $key = 'test_key';
        $data = ['test' => 'data'];
        
        // Mock Cache facade
        Cache::shouldReceive('getStore')->andReturn(new \Illuminate\Cache\ArrayStore());
        Cache::shouldReceive('put')->with($key, $data, \Mockery::any())->andReturn(true);
        Cache::shouldReceive('get')->with($key, null)->andReturn($data);
        Cache::shouldReceive('get')->with($key)->andReturn($data);
        
        $result = $this->cachingUtil->cache($key, $data);
        
        $this->assertEquals($data, $result);
        $this->assertEquals($data, Cache::get($key));
    }

    public function test_can_cache_data_with_custom_expiration()
    {
        $key = 'test_key_custom';
        $data = ['test' => 'data'];
        $minutes = 30;
        
        // Mock Cache facade
        Cache::shouldReceive('getStore')->andReturn(new \Illuminate\Cache\ArrayStore());
        Cache::shouldReceive('put')->with($key, $data, $minutes * 60)->andReturn(true);
        Cache::shouldReceive('get')->with($key, null)->andReturn($data);
        Cache::shouldReceive('get')->with($key)->andReturn($data);
        
        $result = $this->cachingUtil->cache($key, $data, $minutes);
        
        $this->assertEquals($data, $result);
        $this->assertEquals($data, Cache::get($key));
    }

    public function test_can_cache_data_with_custom_tags()
    {
        $key = 'test_key_tags';
        $data = ['test' => 'data'];
        $tags = ['custom', 'test'];
        
        $result = $this->cachingUtil->cache($key, $data, null, $tags);
        
        $this->assertEquals($data, $result);
    }

    public function test_can_retrieve_cached_data()
    {
        $key = 'test_key_get';
        $data = ['test' => 'data'];
        
        // Mock Cache facade
        Cache::shouldReceive('getStore')->andReturn(new \Illuminate\Cache\ArrayStore());
        Cache::shouldReceive('put')->with($key, $data, \Mockery::any())->andReturn(true);
        Cache::shouldReceive('get')->with($key, null)->andReturn($data);
        
        $this->cachingUtil->cache($key, $data);
        $result = $this->cachingUtil->get($key);
        
        $this->assertEquals($data, $result);
    }

    public function test_returns_default_when_key_not_found()
    {
        $key = 'non_existent_key';
        $default = 'default_value';
        
        // Mock Cache facade
        Cache::shouldReceive('get')->with($key, $default)->andReturn($default);
        
        $result = $this->cachingUtil->get($key, $default);
        
        $this->assertEquals($default, $result);
    }

    public function test_can_forget_cached_data()
    {
        $key = 'test_key_forget';
        $data = ['test' => 'data'];
        
        // Mock Cache facade - first call returns data, after forget returns null
        Cache::shouldReceive('getStore')->andReturn(new \Illuminate\Cache\ArrayStore());
        Cache::shouldReceive('put')->with($key, $data, \Mockery::any())->andReturn(true);
        Cache::shouldReceive('get')->with($key, null)->andReturn($data)->once();
        Cache::shouldReceive('forget')->with($key)->andReturn(true);
        Cache::shouldReceive('get')->with($key, null)->andReturn(null)->once();
        
        $this->cachingUtil->cache($key, $data);
        $this->assertEquals($data, $this->cachingUtil->get($key));
        
        $this->cachingUtil->forget($key);
        $this->assertNull($this->cachingUtil->get($key));
    }

    public function test_handles_taggable_store_gracefully()
    {
        $key = 'test_key_taggable';
        $data = ['test' => 'data'];
        $tags = ['test'];
        
        // Mock Cache facade to avoid store issues
        Cache::shouldReceive('getStore')->andReturn(new \Illuminate\Cache\ArrayStore());
        Cache::shouldReceive('put')->andReturn(true);
        
        $result = $this->cachingUtil->cache($key, $data, null, $tags);
        
        $this->assertEquals($data, $result);
    }
}
