<?php

namespace Database\Seeders;

use App\Services\DemoPersonaDataService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ClearSpendSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogSeeder::class);

        app(DemoPersonaDataService::class)->seedAllPersonas(
            Carbon::parse('2026-07-25')->startOfDay(),
        );
    }
}
