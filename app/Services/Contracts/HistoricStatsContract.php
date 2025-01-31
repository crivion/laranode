<?php

namespace App\Services\Contracts;

use Illuminate\Support\Collection;

interface HistoricStatsContract
{
    public function runCommands(): array;
    public function getStats(): array;
    public function parseLines(Collection $metrics): Collection;
    public function getSarFileList(): Collection;
}
