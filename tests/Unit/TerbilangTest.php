<?php

namespace Tests\Unit;

use App\Helpers\Terbilang;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TerbilangTest extends TestCase
{
    #[DataProvider('provideAngka')]
    public function test_rupiah(float|int $angka, string $harapan): void
    {
        $this->assertSame($harapan, Terbilang::rupiah($angka));
    }

    public static function provideAngka(): array
    {
        return [
            [0, 'Nol Rupiah'],
            [1000, 'Seribu Rupiah'],
            [11000, 'Sebelas Ribu Rupiah'],
            [100000, 'Seratus Ribu Rupiah'],
            [1500000, 'Satu Juta Lima Ratus Ribu Rupiah'],
            [1500000.50, 'Satu Juta Lima Ratus Ribu Rupiah Lima Puluh Sen'],
            [21550000, 'Dua Puluh Satu Juta Lima Ratus Lima Puluh Ribu Rupiah'],
            [1000000000, 'Satu Miliar Rupiah'],
        ];
    }

    public function test_helper_function_terbilang(): void
    {
        require_once __DIR__.'/../../app/helpers.php';

        $this->assertSame('Seribu Rupiah', terbilang(1000));
    }
}
