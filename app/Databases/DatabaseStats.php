<?php

namespace App\Databases;

readonly class DatabaseStats
{
    public function __construct(
        public int $tableCount,
        public float $sizeMb,
        public array $extra = [],
    ) {}
}
