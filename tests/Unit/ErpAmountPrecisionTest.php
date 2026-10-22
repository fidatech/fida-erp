<?php
namespace Tests\Unit;
use App\Services\ERP\Decimal;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ErpAmountPrecisionTest extends TestCase
{
    public function test_amounts_reject_precision_that_database_cannot_store(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Decimal::fixed('0.0000001',true);
    }
    public function test_amounts_accept_exactly_six_places(): void
    {
        $this->assertSame('1.123456',Decimal::fixed('1.123456',true));
        $this->assertSame('-1.123456',Decimal::fixed('-1.123456'));
        $this->assertSame('0.000001',Decimal::fixed('0.000001',true));
    }
    public function test_rates_keep_twelve_places_for_foreign_exchange(): void
    {
        $this->assertSame('0.000000000001',Decimal::valid('0.000000000001',true));
    }
}
