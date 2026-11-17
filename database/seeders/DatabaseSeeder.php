<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Plan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Plan::updateOrCreate(['code' => 'comercio-cloud-essencial'], [
            'name' => 'RMC Flow Comércio Cloud - Essencial',
            'billing_cycle' => 'annual',
            'price_cents' => 17900,
            'initial_price_cents' => 17900,
            'renewal_price_cents' => 8000,
            'limits' => ['users' => 3, 'computers' => null],
            'features' => ['cloud_access' => true, 'services_module' => 'optional'],
            'active' => true,
        ]);
        Plan::updateOrCreate(['code' => 'comercio-cloud-equipe'], [
            'name' => 'RMC Flow Comércio Cloud - Equipe',
            'billing_cycle' => 'annual',
            'price_cents' => 29900,
            'initial_price_cents' => 29900,
            'renewal_price_cents' => 12000,
            'limits' => ['users' => 10, 'computers' => null],
            'features' => ['cloud_access' => true, 'services_module' => 'optional'],
            'active' => true,
        ]);
        Plan::query()->where('code', 'comercio-cloud-anual')->update(['active' => false]);
    }
}
