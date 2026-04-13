<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        if (Role::where('name', 'ADMIN')->exists() && ! $user->hasRole('ADMIN')) {
            $user->assignRole('ADMIN');
        }

        $this->call([
            RegulatoryParameterSeeder::class,
            PaymentCalendarRuleSeeder::class,
            SolidarityFundScaleSeeder::class,
            ColombianHoliday2026Seeder::class,
            ContributorTypeSeeder::class,
            ContributorTypeSubsystemSeeder::class,
            ExcelCatalogSeeder::class,
            ExcelEtlSeeder::class,
        ]);
    }
}
