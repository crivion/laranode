<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScheduledBackup>
 */
class ScheduledBackupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['db', 'files']),
            'target' => fake()->slug(2).'_ln',
            'storage' => 'local',
            'disk_name' => 'backups',
            'cron_expression' => '0 2 * * *',
            'retention_count' => 7,
            's3_key' => null,
            's3_secret' => null,
            's3_region' => null,
            's3_bucket' => null,
            's3_endpoint' => null,
            'enabled' => true,
            'last_run_at' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => ['enabled' => false]);
    }

    public function withS3(): static
    {
        return $this->state(fn (array $attributes) => [
            'storage' => 's3',
            's3_key' => 'AKIAIOSFODNN7EXAMPLE',
            's3_secret' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
            's3_region' => 'us-east-1',
            's3_bucket' => 'my-backups',
            's3_endpoint' => null,
        ]);
    }
}
