<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // En Cloud producción se suele instalar sin --dev, por lo que Faker no está disponible.
        // Evitamos factories aquí y creamos un usuario de arranque idempotente.
        User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $this->call([
            RegulatoryParameterSeeder::class,
            PaymentCalendarRuleSeeder::class,
            SolidarityFundScaleSeeder::class,
            ColombianHoliday2026Seeder::class,
            ContributorTypeSeeder::class,
            ContributorTypeSubsystemSeeder::class,
        ]);
    }
}
