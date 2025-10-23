<?php

namespace LaraUtilX\Tests\Unit\Rules;

use LaraUtilX\Tests\TestCase;
use LaraUtilX\Rules\RejectCommonPasswords;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;

class RejectCommonPasswordsTest extends TestCase
{
    private RejectCommonPasswords $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new RejectCommonPasswords();
    }

    public function test_rejects_common_passwords()
    {
        $commonPasswords = [
            'password',
            '123456',
            '123456789',
            'qwerty',
            'abc123',
            'password123',
            'admin',
            'letmein',
            'welcome',
            'monkey',
        ];

        foreach ($commonPasswords as $password) {
            $validator = ValidatorFacade::make(
                ['password' => $password],
                ['password' => [$this->rule]]
            );

            $this->assertTrue($validator->fails());
            $this->assertStringContainsString(
                'common password that is not allowed',
                $validator->errors()->first('password')
            );
        }
    }

    public function test_accepts_strong_passwords()
    {
        $strongPasswords = [
            'MyStr0ng!P@ssw0rd',
            'ComplexP@ssw0rd123!',
            'VerySecureP@ssw0rd2024',
            'RandomString123!@#',
            'AnotherSecureP@ssw0rd',
        ];

        foreach ($strongPasswords as $password) {
            $validator = ValidatorFacade::make(
                ['password' => $password],
                ['password' => [$this->rule]]
            );

            $this->assertFalse($validator->fails());
        }
    }

    public function test_case_insensitive_rejection()
    {
        $commonPasswords = [
            'PASSWORD',
            'Password',
            'PaSsWoRd',
            'ADMIN',
            'Admin',
            'AdMiN',
        ];

        foreach ($commonPasswords as $password) {
            $validator = ValidatorFacade::make(
                ['password' => $password],
                ['password' => [$this->rule]]
            );

            $this->assertTrue($validator->fails());
        }
    }

    public function test_handles_whitespace()
    {
        $passwordsWithWhitespace = [
            ' password',
            'password ',
            ' password ',
            "\tpassword",
            "password\t",
        ];

        foreach ($passwordsWithWhitespace as $password) {
            $validator = ValidatorFacade::make(
                ['password' => $password],
                ['password' => [$this->rule]]
            );

            $this->assertTrue($validator->fails());
        }
    }

    public function test_accepts_non_string_values()
    {
        $nonStringValues = [
            null,
            123,
            [],
            true,
            false,
        ];

        foreach ($nonStringValues as $value) {
            $validator = ValidatorFacade::make(
                ['password' => $value],
                ['password' => [$this->rule]]
            );

            $this->assertFalse($validator->fails());
        }
    }

    public function test_rejects_numeric_sequences()
    {
        $numericSequences = [
            '111111',
            '000000',
            '666666',
            '888888',
            '999999',
            '11111111',
            '00000000',
            '123321',
            '654321',
        ];

        foreach ($numericSequences as $password) {
            $validator = ValidatorFacade::make(
                ['password' => $password],
                ['password' => [$this->rule]]
            );

            $this->assertTrue($validator->fails());
        }
    }

    public function test_rejects_keyboard_patterns()
    {
        $keyboardPatterns = [
            'qwerty',
            'qwertyuiop',
            'asdfghjkl',
            'zxcvbnm',
            'qazwsx',
        ];

        foreach ($keyboardPatterns as $password) {
            $validator = ValidatorFacade::make(
                ['password' => $password],
                ['password' => [$this->rule]]
            );

            $this->assertTrue($validator->fails());
        }
    }

    public function test_rejects_common_words_with_numbers()
    {
        $commonWithNumbers = [
            'password1',
            'password12',
            'password123',
            'admin1',
            'admin12',
            'admin123',
            'user1',
            'user12',
            'user123',
        ];

        foreach ($commonWithNumbers as $password) {
            $validator = ValidatorFacade::make(
                ['password' => $password],
                ['password' => [$this->rule]]
            );

            $this->assertTrue($validator->fails());
        }
    }

    public function test_accepts_mixed_case_strong_passwords()
    {
        $mixedCasePasswords = [
            'MyPassword123!',
            'SecurePass2024',
            'ComplexP@ssw0rd',
            'StrongP@ssw0rd123',
        ];

        foreach ($mixedCasePasswords as $password) {
            $validator = ValidatorFacade::make(
                ['password' => $password],
                ['password' => [$this->rule]]
            );

            $this->assertFalse($validator->fails());
        }
    }

    public function test_rule_implements_validation_rule_interface()
    {
        $this->assertInstanceOf(
            \Illuminate\Contracts\Validation\ValidationRule::class,
            $this->rule
        );
    }
}
