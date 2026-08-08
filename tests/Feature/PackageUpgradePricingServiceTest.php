<?php

namespace Tests\Feature;

use App\Models\PaketUndangan;
use App\Services\PackageUpgradePricingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PackageUpgradePricingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('paket_undangans', function (Blueprint $table) {
            $table->id();
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function test_upgrade_target_seratus_ribu_mendapat_diskon_empat_puluh_persen(): void
    {
        $pricing = app(PackageUpgradePricingService::class)
            ->calculate(new PaketUndangan(['price' => 100000]), 'upgrade');

        $this->assertSame(100000.0, $pricing['original_price']);
        $this->assertSame(40, $pricing['discount_percentage']);
        $this->assertSame(40000.0, $pricing['discount_amount']);
        $this->assertSame(60000.0, $pricing['payable_amount']);
    }

    public function test_upgrade_target_tiga_ratus_ribu_mendapat_diskon_empat_puluh_persen(): void
    {
        $pricing = app(PackageUpgradePricingService::class)
            ->calculate(new PaketUndangan(['price' => 300000]), 'upgrade');

        $this->assertSame(300000.0, $pricing['original_price']);
        $this->assertSame(40, $pricing['discount_percentage']);
        $this->assertSame(120000.0, $pricing['discount_amount']);
        $this->assertSame(180000.0, $pricing['payable_amount']);
    }

    public function test_initial_purchase_tidak_mendapat_diskon_upgrade(): void
    {
        $pricing = app(PackageUpgradePricingService::class)
            ->calculate(new PaketUndangan(['price' => 300000]), 'subscribe');

        $this->assertSame(300000.0, $pricing['original_price']);
        $this->assertSame(0, $pricing['discount_percentage']);
        $this->assertSame(0.0, $pricing['discount_amount']);
        $this->assertSame(300000.0, $pricing['payable_amount']);
    }
}
