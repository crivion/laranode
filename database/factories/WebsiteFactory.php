<?php

namespace Database\Factories;

use App\Models\PhpVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebsiteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'url' => fake()->unique()->domainName(),
            'document_root' => '/public',
            'php_version_id' => PhpVersion::factory(),
        ];
    }
}
