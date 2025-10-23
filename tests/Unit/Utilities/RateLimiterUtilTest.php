<?php

namespace LaraUtilX\Tests\Unit\Utilities;

use LaraUtilX\Tests\TestCase;
use LaraUtilX\Utilities\RateLimiterUtil;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Cache\ArrayStore;

class RateLimiterUtilTest extends TestCase
{
    private RateLimiterUtil $rateLimiterUtil;
    private Repository $cache;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->cache = new \Illuminate\Cache\Repository(new ArrayStore());
        $this->rateLimiterUtil = new RateLimiterUtil($this->cache);
    }

    public function test_can_attempt_within_limit()
    {
        $key = 'test_key';
        $maxAttempts = 5;
        $decayMinutes = 1;
        
        $result = $this->rateLimiterUtil->attempt($key, $maxAttempts, $decayMinutes);
        
        $this->assertTrue($result);
    }

    public function test_blocks_after_exceeding_limit()
    {
        $key = 'test_key_limit';
        $maxAttempts = 2;
        $decayMinutes = 1;
        
        // First attempt should succeed
        $result1 = $this->rateLimiterUtil->attempt($key, $maxAttempts, $decayMinutes);
        $this->assertTrue($result1);
        
        // Second attempt should succeed
        $result2 = $this->rateLimiterUtil->attempt($key, $maxAttempts, $decayMinutes);
        $this->assertTrue($result2);
        
        // Third attempt should fail
        $result3 = $this->rateLimiterUtil->attempt($key, $maxAttempts, $decayMinutes);
        $this->assertFalse($result3);
    }

    public function test_can_get_attempts_count()
    {
        $key = 'test_key_attempts';
        $maxAttempts = 5;
        $decayMinutes = 1;
        
        // No attempts initially
        $this->assertEquals(0, $this->rateLimiterUtil->attempts($key));
        
        // After one attempt
        $this->rateLimiterUtil->attempt($key, $maxAttempts, $decayMinutes);
        $this->assertEquals(1, $this->rateLimiterUtil->attempts($key));
        
        // After two attempts
        $this->rateLimiterUtil->attempt($key, $maxAttempts, $decayMinutes);
        $this->assertEquals(2, $this->rateLimiterUtil->attempts($key));
    }

    public function test_can_get_remaining_attempts()
    {
        $key = 'test_key_remaining';
        $maxAttempts = 3;
        $decayMinutes = 1;
        
        // Initially should have max attempts
        $this->assertEquals(3, $this->rateLimiterUtil->remaining($key, $maxAttempts));
        
        // After one attempt
        $this->rateLimiterUtil->attempt($key, $maxAttempts, $decayMinutes);
        $this->assertEquals(2, $this->rateLimiterUtil->remaining($key, $maxAttempts));
        
        // After two attempts
        $this->rateLimiterUtil->attempt($key, $maxAttempts, $decayMinutes);
        $this->assertEquals(1, $this->rateLimiterUtil->remaining($key, $maxAttempts));
        
        // After three attempts
        $this->rateLimiterUtil->attempt($key, $maxAttempts, $decayMinutes);
        $this->assertEquals(0, $this->rateLimiterUtil->remaining($key, $maxAttempts));
    }

    public function test_can_clear_attempts()
    {
        $key = 'test_key_clear';
        $maxAttempts = 2;
        $decayMinutes = 1;
        
        // Make some attempts
        $this->rateLimiterUtil->attempt($key, $maxAttempts, $decayMinutes);
        $this->rateLimiterUtil->attempt($key, $maxAttempts, $decayMinutes);
        
        $this->assertEquals(2, $this->rateLimiterUtil->attempts($key));
        
        // Clear the attempts
        $this->rateLimiterUtil->clear($key);
        
        $this->assertEquals(0, $this->rateLimiterUtil->attempts($key));
    }

    public function test_can_check_if_too_many_attempts()
    {
        $key = 'test_key_too_many';
        $maxAttempts = 2;
        $decayMinutes = 1;
        
        // Initially not too many attempts
        $this->assertFalse($this->rateLimiterUtil->tooManyAttempts($key, $maxAttempts));
        
        // After exceeding limit
        $this->rateLimiterUtil->attempt($key, $maxAttempts, $decayMinutes);
        $this->rateLimiterUtil->attempt($key, $maxAttempts, $decayMinutes);
        $this->rateLimiterUtil->attempt($key, $maxAttempts, $decayMinutes);
        
        $this->assertTrue($this->rateLimiterUtil->tooManyAttempts($key, $maxAttempts));
    }

    public function test_can_hit_rate_limiter()
    {
        $key = 'test_key_hit';
        $decaySeconds = 60;
        
        $result = $this->rateLimiterUtil->hit($key, $decaySeconds);
        
        $this->assertIsInt($result);
        $this->assertEquals(1, $this->rateLimiterUtil->attempts($key));
    }

    public function test_can_get_available_in_time()
    {
        $key = 'test_key_available';
        $maxAttempts = 1;
        $decayMinutes = 1;
        
        // Initially available
        $this->assertEquals(0, $this->rateLimiterUtil->availableIn($key));
        
        // After hitting limit
        $this->rateLimiterUtil->attempt($key, $maxAttempts, $decayMinutes);
        $this->rateLimiterUtil->attempt($key, $maxAttempts, $decayMinutes);
        
        $availableIn = $this->rateLimiterUtil->availableIn($key);
        $this->assertGreaterThan(0, $availableIn);
        $this->assertLessThanOrEqual(60, $availableIn); // Should be within decay time
    }

    public function test_can_get_rate_limiter_instance()
    {
        $rateLimiter = $this->rateLimiterUtil->getRateLimiter();
        
        $this->assertInstanceOf(RateLimiter::class, $rateLimiter);
    }

    public function test_different_keys_are_independent()
    {
        $key1 = 'test_key_1';
        $key2 = 'test_key_2';
        $maxAttempts = 1;
        $decayMinutes = 1;
        
        // Hit limit for key1
        $this->rateLimiterUtil->attempt($key1, $maxAttempts, $decayMinutes);
        $this->rateLimiterUtil->attempt($key1, $maxAttempts, $decayMinutes);
        
        // key2 should still be available
        $this->assertTrue($this->rateLimiterUtil->attempt($key2, $maxAttempts, $decayMinutes));
        
        // key1 should be blocked
        $this->assertFalse($this->rateLimiterUtil->attempt($key1, $maxAttempts, $decayMinutes));
    }
}
