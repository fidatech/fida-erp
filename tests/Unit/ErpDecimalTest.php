<?php
namespace Tests\Unit;

use App\Services\ERP\Decimal;
use PHPUnit\Framework\TestCase;

/** Run on a PHP installation with BCMath extension installed. */
final class ErpDecimalTest extends TestCase
{
    public function test_basic_decimal_posting_math(): void
    {
        $this->assertSame('0.300000', Decimal::add('0.1', '0.2'));
        $this->assertSame('12.345600', Decimal::mul('6.1728', '2'));
        $this->assertSame(0, Decimal::cmp('12.000000', '12'));
    }

    public function test_invalid_and_negative_quantity_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Decimal::valid('-2', true);
    }
}
