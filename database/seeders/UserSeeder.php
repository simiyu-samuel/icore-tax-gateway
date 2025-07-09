<?php
namespace Database\Seeders;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash; // Import Hash facade

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@icore.com'],
            [
                'name' => 'ICORE Admin',
                'password' => Hash::make('password'), // Use a strong password in production
            ]
        );

        User::firstOrCreate(
            ['email' => 'taxpayer1@example.com'],
            [
                'name' => 'Taxpayer One',
                'password' => Hash::make('password'),
            ]
        );
    }
}