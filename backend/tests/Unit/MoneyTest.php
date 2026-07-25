<?php

namespace Tests\Unit;

use App\Services\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Money helpers: dollar ↔ cent conversion and integer arithmetic.
 */
class MoneyTest extends TestCase
{
    #[DataProvider('dollarProvider')]
    #[TestDox('Converts dollar strings to integer cents')]
    public function test_dollars_to_cents(string $input, int $expected): void
    {
        $this->assertSame($expected, Money::dollarsToCents($input));
    }

    public static function dollarProvider(): array
    {
        return [
            ['0.00', 0],
            ['1.00', 100],
            ['84.23', 8423],
            ['0.1', 10],
            ['-12.50', -1250],
        ];
    }

    #[TestDox('Formats cents back to a stable two-decimal dollar string')]
    public function test_cents_to_dollar_string_is_stable(): void
    {
        $this->assertSame('84.23', Money::centsToDollarString(8423));
        $this->assertSame('-12.50', Money::centsToDollarString(-1250));
        $this->assertSame('0.00', Money::centsToDollarString(0));
    }

    #[TestDox('Sums cents and computes percents with integer math only')]
    public function test_sum_and_percent_use_integer_math(): void
    {
        $this->assertSame(300, Money::sumCents(100, 200));
        $this->assertSame(50, Money::percentOf(50, 100));
        $this->assertSame(33, Money::percentOf(1, 3));
    }
}
