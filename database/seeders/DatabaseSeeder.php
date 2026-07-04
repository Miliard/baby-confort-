<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            ProductSeeder::class,
            SwimPantsSeeder::class,
            BottleSeeder::class,
            TritanBottleSeeder::class,
            FruitFeederSeeder::class,
            PPSUBottleSeeder::class,
            MilkBagsSeeder::class,
        ]);

        // Costo de envío por defecto (editable desde el panel)
        Setting::put('envio', '2.50');
    }
}
