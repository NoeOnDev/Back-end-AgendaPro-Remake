<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ApiTestSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'API Test User',
            'email' => 'api@test.com',
            'password' => Hash::make('password123'),
        ]);
    }
}
