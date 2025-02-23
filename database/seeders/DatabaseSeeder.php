<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Factories\PhpVersionFactory;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        PhpVersionFactory::new()->create();
    }
}
