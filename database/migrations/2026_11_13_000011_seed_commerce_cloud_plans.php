<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacy = DB::table('plans')->where('code', 'comercio-cloud-anual')->first();
        $essential = DB::table('plans')->where('code', 'comercio-cloud-essencial')->first();
        if ($legacy && ! $essential) {
            DB::table('plans')->where('id', $legacy->id)->update(['code' => 'comercio-cloud-essencial']);
            $essential = DB::table('plans')->where('id', $legacy->id)->first();
        } elseif ($legacy && $essential) {
            DB::table('subscriptions')->where('plan_id', $legacy->id)->update(['plan_id' => $essential->id]);
            DB::table('plans')->where('id', $legacy->id)->delete();
        }

        $now = now()->toDateTimeString();
        $commonFeatures = json_encode(['cloud_access' => true, 'services_module' => 'optional'], JSON_THROW_ON_ERROR);
        DB::table('plans')->updateOrInsert(['code' => 'comercio-cloud-essencial'], [
            'name' => 'RMC Flow Comércio Cloud - Essencial', 'billing_cycle' => 'annual', 'price_cents' => 17900,
            'initial_price_cents' => 17900, 'renewal_price_cents' => 8000,
            'limits' => json_encode(['users' => 3, 'computers' => null], JSON_THROW_ON_ERROR),
            'features' => $commonFeatures, 'active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('plans')->updateOrInsert(['code' => 'comercio-cloud-equipe'], [
            'name' => 'RMC Flow Comércio Cloud - Equipe', 'billing_cycle' => 'annual', 'price_cents' => 29900,
            'initial_price_cents' => 29900, 'renewal_price_cents' => 12000,
            'limits' => json_encode(['users' => 10, 'computers' => null], JSON_THROW_ON_ERROR),
            'features' => $commonFeatures, 'active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('plans')->where('code', 'comercio-cloud-anual')->update(['active' => false]);
    }

    public function down(): void
    {
        $essentialId = DB::table('plans')->where('code', 'comercio-cloud-essencial')->value('id');
        $teamId = DB::table('plans')->where('code', 'comercio-cloud-equipe')->value('id');
        if ($teamId && DB::table('subscriptions')->where('plan_id', $teamId)->exists()) {
            throw new RuntimeException('Cannot roll back the Commerce Cloud plan catalog while subscriptions use the Equipe plan.');
        }
        if ($teamId) DB::table('plans')->where('id', $teamId)->delete();
        if ($essentialId) DB::table('plans')->where('id', $essentialId)->update([
            'code' => 'comercio-cloud-anual', 'name' => 'RMC Flow Comércio Cloud Anual', 'price_cents' => 17900,
            'initial_price_cents' => 17900, 'renewal_price_cents' => 8000,
            'limits' => json_encode(['computers' => null]), 'active' => true, 'updated_at' => now()->toDateTimeString(),
        ]);
    }
};
