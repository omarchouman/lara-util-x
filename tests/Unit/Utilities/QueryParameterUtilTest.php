<?php

namespace LaraUtilX\Tests\Unit\Utilities;

use LaraUtilX\Tests\TestCase;
use LaraUtilX\Utilities\QueryParameterUtil;
use Illuminate\Http\Request;

class QueryParameterUtilTest extends TestCase
{
    public function test_can_parse_allowed_parameters()
    {
        $request = Request::create('/test', 'GET', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 25,
            'unwanted_param' => 'should_be_ignored'
        ]);
        
        $allowedParameters = ['name', 'email', 'age'];
        
        $result = QueryParameterUtil::parse($request, $allowedParameters);
        
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('age', $result);
        $this->assertArrayNotHasKey('unwanted_param', $result);
        
        $this->assertEquals('John Doe', $result['name']);
        $this->assertEquals('john@example.com', $result['email']);
        $this->assertEquals(25, $result['age']);
    }

    public function test_ignores_unallowed_parameters()
    {
        $request = Request::create('/test', 'GET', [
            'allowed_param' => 'allowed_value',
            'forbidden_param' => 'forbidden_value',
            'another_forbidden' => 'another_value'
        ]);
        
        $allowedParameters = ['allowed_param'];
        
        $result = QueryParameterUtil::parse($request, $allowedParameters);
        
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('allowed_param', $result);
        $this->assertArrayNotHasKey('forbidden_param', $result);
        $this->assertArrayNotHasKey('another_forbidden', $result);
    }

    public function test_handles_empty_allowed_parameters()
    {
        $request = Request::create('/test', 'GET', [
            'param1' => 'value1',
            'param2' => 'value2'
        ]);
        
        $allowedParameters = [];
        
        $result = QueryParameterUtil::parse($request, $allowedParameters);
        
        $this->assertEmpty($result);
    }

    public function test_handles_missing_parameters()
    {
        $request = Request::create('/test', 'GET', []);
        
        $allowedParameters = ['name', 'email', 'age'];
        
        $result = QueryParameterUtil::parse($request, $allowedParameters);
        
        $this->assertEmpty($result);
    }

    public function test_handles_partial_parameters()
    {
        $request = Request::create('/test', 'GET', [
            'name' => 'John Doe',
            'unwanted' => 'ignored'
        ]);
        
        $allowedParameters = ['name', 'email', 'age'];
        
        $result = QueryParameterUtil::parse($request, $allowedParameters);
        
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertEquals('John Doe', $result['name']);
    }

    public function test_preserves_parameter_types()
    {
        $request = Request::create('/test', 'GET', [
            'string_param' => 'string_value',
            'int_param' => '123',
            'bool_param' => 'true',
            'array_param' => ['item1', 'item2']
        ]);
        
        $allowedParameters = ['string_param', 'int_param', 'bool_param', 'array_param'];
        
        $result = QueryParameterUtil::parse($request, $allowedParameters);
        
        $this->assertIsString($result['string_param']);
        $this->assertIsString($result['int_param']); // Request parameters are always strings
        $this->assertIsString($result['bool_param']); // Request parameters are always strings
        $this->assertIsArray($result['array_param']);
    }

    public function test_handles_duplicate_parameter_names()
    {
        $request = Request::create('/test', 'GET', [
            'param' => 'value1'
        ]);
        
        $allowedParameters = ['param', 'param']; // Duplicate in allowed list
        
        $result = QueryParameterUtil::parse($request, $allowedParameters);
        
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('param', $result);
        $this->assertEquals('value1', $result['param']);
    }

    public function test_handles_empty_string_parameters()
    {
        $request = Request::create('/test', 'GET', [
            'empty_param' => '',
            'normal_param' => 'normal_value'
        ]);
        
        $allowedParameters = ['empty_param', 'normal_param'];
        
        $result = QueryParameterUtil::parse($request, $allowedParameters);
        
        $this->assertCount(2, $result);
        $this->assertEquals('', $result['empty_param']);
        $this->assertEquals('normal_value', $result['normal_param']);
    }
}
