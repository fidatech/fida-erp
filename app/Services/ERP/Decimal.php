<?php
namespace App\Services\ERP;

use InvalidArgumentException;
/** All arithmetic is decimal-string based; require the bcmath PHP extension. */
final class Decimal
{
    public static function valid(string $v, bool $positive=false): string
    {
        if (!preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]{1,12})?$/D', $v)) {
            throw new InvalidArgumentException('Invalid decimal amount');
        }
        if ($positive && bccomp($v,'0',12)<=0) throw new InvalidArgumentException('Amount must be positive');
        return $v;
    }
    public static function add(string $a,string $b): string {return bcadd($a,$b,6);}
    public static function sub(string $a,string $b): string {return bcsub($a,$b,6);}
    public static function mul(string $a,string $b): string {return bcmul($a,$b,6);}
    public static function div(string $a,string $b): string {return bcdiv($a,$b,6);}
    public static function cmp(string $a,string $b): int {return bccomp($a,$b,6);}
    public static function abs(string $a): string {return ltrim($a,'-');}
}
