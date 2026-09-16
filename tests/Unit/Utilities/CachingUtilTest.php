<?php

namespace LaraUtilX\Tests\Unit\Utilities;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;
use LaraUtilX\Tests\TestCase;
use LaraUtilX\Utilities\CachingUtil;

class CachingUtilTest extends TestCase
{
    private CachingUtil $cachingUtil;

    protected function setUp(): void
    {
        parent::setUp();

        // The array store is taggable, so tagged reads and writes are exercised
        // for real rather than through a mock that cannot disagree with us.
        $this->cachingUtil = new CachingUtil(60, ['default']);
        Cache::flush();
    }

    public function test_store_under_test_is_taggable()
    {
        $this->assertInstanceOf(TaggableStore::class, Cache::getStore());
    }

    // -----------------------------------------------------------------------
    // Round trips
    // -----------------------------------------------------------------------

    public function test_tagged_data_can_be_read_back()
    {
        $data = ['test' => 'data'];

        $this->cachingUtil->cache('test_key', $data);

        $this->assertEquals($data, $this->cachingUtil->get('test_key'));
    }

    public function test_untagged_data_can_be_read_back()
    {
        $util = new CachingUtil(60, []);

        $util->cache('plain_key', 'value');

        $this->assertEquals('value', $util->get('plain_key'));
        $this->assertEquals('value', Cache::get('plain_key'));
    }

    public function test_cache_returns_the_data_it_stored()
    {
        $data = ['a' => 1];

        $this->assertEquals($data, $this->cachingUtil->cache('returned', $data));
    }

    public function test_custom_expiration_is_honoured()
    {
        $this->cachingUtil->cache('expiring', 'value', 120);

        $this->assertEquals('value', $this->cachingUtil->get('expiring'));
    }

    public function test_explicit_tags_override_the_defaults()
    {
        $this->cachingUtil->cache('tagged', 'value', null, ['reports']);

        $this->assertEquals('value', $this->cachingUtil->get('tagged', null, ['reports']));
    }

    public function test_returns_default_when_key_not_found()
    {
        $this->assertEquals('fallback', $this->cachingUtil->get('missing', 'fallback'));
        $this->assertNull($this->cachingUtil->get('missing'));
    }

    // -----------------------------------------------------------------------
    // Forgetting
    // -----------------------------------------------------------------------

    public function test_can_forget_tagged_data()
    {
        $this->cachingUtil->cache('forget_me', 'value');
        $this->assertEquals('value', $this->cachingUtil->get('forget_me'));

        $this->cachingUtil->forget('forget_me');

        $this->assertNull($this->cachingUtil->get('forget_me'));
    }

    public function test_can_forget_untagged_data()
    {
        $util = new CachingUtil(60, []);
        $util->cache('plain_forget', 'value');

        $util->forget('plain_forget');

        $this->assertNull($util->get('plain_forget'));
    }
}
