<?php

namespace LaraUtilX\Tests\Unit\Helpers;

use LaraUtilX\Helpers\XHelper;
use LaraUtilX\Tests\TestCase;

class XHelperTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Slugify
    // -----------------------------------------------------------------------

    public function test_slugifies_ascii()
    {
        $this->assertEquals('hello-beirut', XHelper::strSlugify('Hello Beirut'));
    }

    public function test_slugifies_non_ascii_instead_of_returning_empty()
    {
        // The old implementation stripped every non-ASCII character, so any
        // Arabic input slugified to an empty string.
        $slug = XHelper::strSlugify('مرحبا بيروت');

        $this->assertNotEmpty($slug);
        $this->assertEquals('mrhba-byrot', $slug);
    }

    public function test_slugify_accepts_a_custom_separator()
    {
        $this->assertEquals('hello_beirut', XHelper::strSlugify('Hello Beirut', '_'));
    }

    public function test_slugify_collapses_punctuation_and_spacing()
    {
        $this->assertEquals('a-b-c', XHelper::strSlugify('  a -- b !! c  '));
    }

    // -----------------------------------------------------------------------
    // Strings
    // -----------------------------------------------------------------------

    public function test_str_between_extracts_the_inner_value()
    {
        $this->assertEquals('value', XHelper::strBetween('[value]', '[', ']'));
    }

    public function test_str_between_returns_null_when_absent()
    {
        $this->assertNull(XHelper::strBetween('nothing here', '[', ']'));
    }

    // -----------------------------------------------------------------------
    // Arrays
    // -----------------------------------------------------------------------

    public function test_array_trim_trims_strings_and_leaves_others()
    {
        $this->assertEquals(
            ['a', 'b', 3, null],
            XHelper::arrayTrim(['  a ', 'b  ', 3, null])
        );
    }

    public function test_array_flatten_flattens_nested_arrays()
    {
        $this->assertEquals([1, 2, 3, 4], XHelper::arrayFlatten([1, [2, [3, 4]]]));
    }

    // -----------------------------------------------------------------------
    // Misc
    // -----------------------------------------------------------------------

    public function test_uuid_is_a_valid_v4_uuid()
    {
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            XHelper::uuid()
        );
    }

    public function test_uuids_are_unique()
    {
        $this->assertNotEquals(XHelper::uuid(), XHelper::uuid());
    }

    public function test_carbon_parse_formats_a_date()
    {
        $this->assertEquals('2026-01-02', XHelper::carbonParse('2026-01-02 10:30:00', 'Y-m-d'));
    }

    public function test_carbon_human_diff_returns_a_readable_string()
    {
        $this->assertStringContainsString('ago', XHelper::carbonHumanDiff(now()->subHour()));
    }
}
