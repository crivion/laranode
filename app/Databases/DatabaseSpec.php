<?php

namespace App\Databases;

readonly class DatabaseSpec
{
    public function __construct(
        public string $name,
        public string $dbUser,
        public string $password,
        public int $userId,
        public array $options = [],
    ) {}
}
