<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DatabaseFactory extends Factory
{
    public function definition(): array
    {
        $suffix = strtolower(Str::random(8));

        return [
            'user_id' => User::factory(),
            'website_id' => null,
            'name' => 'test_'.$suffix,
            'db_user' => 'user_'.$suffix,
            'db_password' => 'test-password',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ];
    }
}
