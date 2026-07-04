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
            WipesAromaSeeder::class,
            PreemieSeeder::class,
            OvernightPantySeeder::class,
            SanitaryNapkinSeeder::class,
            AdultPantsSeeder::class,
        ]);

        // Costo de envío por defecto (solo si aún no existe; no pisa tus cambios)
        if (! Setting::where('key', 'envio')->exists()) {
            Setting::put('envio', '2.50');
        }
    }
}
