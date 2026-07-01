<?php

namespace App\Databases;

readonly class EngineCapabilities
{
    public function __construct(
        public string $label,
        public bool $hasUsers,
        public array $optionFields,
    ) {}
}
