<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use TP\Core\ValidationRule;
use TP\Core\Validator;

class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    public function testValidateInPassesWhenValueInAllowed(): void
    {
        $rules = [new ValidationRule('status', ['in' => ['active', 'inactive']])];
        $result = $this->validator->validate(['status' => 'active'], $rules);
        $this->assertTrue($result->isValid);
    }

    public function testValidateInFailsWhenValueNotInAllowed(): void
    {
        $rules = [new ValidationRule('status', ['in' => ['active', 'inactive']])];
        $result = $this->validator->validate(['status' => 'deleted'], $rules);
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('status', $result->getErrorMessages()[0]);
    }

    public function testValidateInPassesWhenValueIsNull(): void
    {
        $rules = [new ValidationRule('status', ['in' => ['active', 'inactive']])];
        $result = $this->validator->validate([], $rules);
        $this->assertTrue($result->isValid);
    }

    public function testValidateBooleanPassesForValidValues(): void
    {
        $rules = [new ValidationRule('flag', ['boolean'])];
        foreach (['0', '1', 'true', 'false', 0, 1] as $value) {
            $result = $this->validator->validate(['flag' => $value], $rules);
            $this->assertTrue($result->isValid, "Expected valid for: " . var_export($value, true));
        }
    }

    public function testValidateBooleanFailsForInvalidValue(): void
    {
        $rules = [new ValidationRule('flag', ['boolean'])];
        $result = $this->validator->validate(['flag' => 'maybe'], $rules);
        $this->assertFalse($result->isValid);
    }

    public function testValidateEmailPassesForValidEmail(): void
    {
        $rules = [new ValidationRule('email', ['email'])];
        $result = $this->validator->validate(['email' => 'user@example.com'], $rules);
        $this->assertTrue($result->isValid);
    }

    public function testValidateEmailFailsForInvalidEmail(): void
    {
        $rules = [new ValidationRule('email', ['email'])];
        $result = $this->validator->validate(['email' => 'not-an-email'], $rules);
        $this->assertFalse($result->isValid);
    }

    public function testValidateEmailPassesWhenNull(): void
    {
        // email not required — null should not produce email format error
        $rules = [new ValidationRule('email', ['email'])];
        $result = $this->validator->validate([], $rules);
        $this->assertTrue($result->isValid);
    }

    public function testValidateStringFailsForNonString(): void
    {
        // This can only be tested directly against the Validator (not through HTTP which always sends strings)
        $rules = [new ValidationRule('name', ['string'])];
        // Pass an actual non-string via direct validator call with typed array
        $result = $this->validator->validate(['name' => 42], $rules);
        $this->assertFalse($result->isValid);
    }

    public function testValidateMaxFailsForNumericExceedingMax(): void
    {
        $rules = [new ValidationRule('count', ['max' => 10])];
        $result = $this->validator->validate(['count' => '15'], $rules);
        $this->assertFalse($result->isValid);
    }

    public function testValidateMaxPassesForNumericBelowMax(): void
    {
        $rules = [new ValidationRule('count', ['max' => 10])];
        $result = $this->validator->validate(['count' => '5'], $rules);
        $this->assertTrue($result->isValid);
    }

    public function testValidateMinFailsForNumericBelowMin(): void
    {
        $rules = [new ValidationRule('count', ['min' => 5])];
        $result = $this->validator->validate(['count' => '3'], $rules);
        $this->assertFalse($result->isValid);
    }

    public function testValidateIntegerPassesForDigitString(): void
    {
        $rules = [new ValidationRule('id', ['integer'])];
        $result = $this->validator->validate(['id' => '42'], $rules);
        $this->assertTrue($result->isValid);
    }

    public function testValidateIntegerFailsForNonInteger(): void
    {
        $rules = [new ValidationRule('id', ['integer'])];
        $result = $this->validator->validate(['id' => 'abc'], $rules);
        $this->assertFalse($result->isValid);
    }

    public function testValidateDatePassesForValidDate(): void
    {
        $rules = [new ValidationRule('date', ['date'])];
        $result = $this->validator->validate(['date' => '2026-03-15'], $rules);
        $this->assertTrue($result->isValid);
    }

    public function testValidateDateFailsForInvalidDate(): void
    {
        $rules = [new ValidationRule('date', ['date'])];
        $result = $this->validator->validate(['date' => 'not-a-date'], $rules);
        $this->assertFalse($result->isValid);
    }

    public function testMultipleRulesAllPass(): void
    {
        $rules = [
            new ValidationRule('username', ['required', 'string', 'min' => 3, 'max' => 20]),
        ];
        $result = $this->validator->validate(['username' => 'alice'], $rules);
        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function testMultipleRulesCollectAllErrors(): void
    {
        $rules = [
            new ValidationRule('username', ['required', 'min' => 3]),
            new ValidationRule('email', ['required', 'email']),
        ];
        $result = $this->validator->validate([], $rules);
        $this->assertFalse($result->isValid);
        $this->assertCount(2, $result->errors); // One 'required' per field
    }
}
