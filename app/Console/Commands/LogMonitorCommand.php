<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use React\EventLoop\Factory;
use React\Stream\ReadableResourceStream;


class LogMonitorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laranode:log-monitor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs continously (in a loop) on demand and fires AccessLogEntryReceived event on reading';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $loop = Factory::create();

        // Path to the Apache access log file
        $logFilePath = '/tmp/readmelive';

        $file = fopen($logFilePath, 'r');

        $loop->addPeriodicTimer(1, function () use ($file) {
            print 'Checking file' . PHP_EOL;

            while (($line = fgets($file)) !== false) {
                print $line . PHP_EOL;
            }
        });

        fclose($file);

        // Start the event loop
        $loop->run();

        return 0;
    }
}
