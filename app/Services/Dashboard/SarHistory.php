<?php

namespace App\Services\Dashboard;

use App\Services\Contracts\HistoricStatsContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

abstract class SarHistory implements HistoricStatsContract
{
    public function __construct(public ?string $sarFile)
    {
        $this->sarFile = $this->sarFile ? '/var/log/sysstat/' . $this->sarFile : $this->getSarFileList()->first();
    }

    public function runCommands(): array
    {
        return [];
    }

    public function getStats(): array
    {

        if (!count($this->getSarFileList())) {
            return ['error' => 'No sar files found in /var/log/sysstat/sa[0-9]{2}', 'code' => 1];
        }

        if (!count($this->runCommands())) {
            return ['error' => 'No commands to run', 'code' => 1];
        }

        $cmd = Process::pipe($this->runCommands());

        if ($cmd->failed()) {
            return ['error' => $cmd->errorOutput() . ' while running ' . implode(' | ', $this->runCommands()) . $this->sarFile, 'code' => $cmd->exitCode()];
        }

        $metrics = $cmd->output();
        $metrics = explode(PHP_EOL, $metrics);
        $metrics = collect($metrics)
            ->filter(fn($line) => !empty($line))
            ->map(fn($line) => explode(' ', $line));

        return ['metrics' => $this->parseLines($metrics), 'sarFiles' => $this->getSarFileList()];
    }

    public function getSarFileList(): Collection
    {

        $sarFiles = File::glob('/var/log/sysstat/sa*');
        $sarFiles = collect($sarFiles);

        // remove sar files from list, we only need sa[0-9]{2}
        $sarFiles = $sarFiles->reject(fn($file) => !preg_match('#^/var/log/sysstat/sa[0-9]{2}$#', $file));
        $sarFiles = $sarFiles->mapWithKeys(fn($file) => [(int)str_replace('sa', '', basename($file)) => $file]);
        $sarFiles = $sarFiles->mapWithKeys(fn($file, $key) => [date('Y-m-') . $key => $file]);
        $sarFiles = $sarFiles->mapWithKeys(fn($file, $key) => [Carbon::parse($key)->format('jS F Y') => $file]);

        // add keys for frontend
        return $sarFiles->reverse();
    }
}
