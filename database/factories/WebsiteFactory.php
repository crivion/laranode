<?php

namespace Database\Factories;

use App\Models\PhpVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Website>
 */
class WebsiteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'url' => fake()->unique()->domainName(),
            'document_root' => '/public',
            'php_version_id' => PhpVersion::factory(),
            'ssl_enabled' => false,
            'ssl_status' => 'inactive',
            'ssl_expires_at' => null,
            'ssl_generated_at' => null,
            'runtime' => 'php-fpm',
            'runtime_port' => null,
        ];
    }
}
