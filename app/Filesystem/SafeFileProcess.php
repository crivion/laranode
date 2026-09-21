<?php

namespace App\Filesystem;

use RuntimeException;
use Symfony\Component\Process\Process;

class SafeFileProcess
{
    /**
     * @param  resource|string|null  $input
     * @param  list<string>  $arguments
     */
    public static function run(
        string $operation,
        string $root,
        array $arguments,
        $input = null,
        ?string $systemUser = null,
        ?string $binPath = null,
    ): string {
        $privileged = $systemUser !== null
            && $binPath !== null
            && $root === '/home/'.$systemUser;

        $stagedPath = null;
        $stagedName = '-';

        if ($privileged && $input !== null) {
            $stagedPath = self::stageInput($input);
            $stagedName = basename($stagedPath);
            $input = null;
        }

        $command = ! $privileged
            ? ['/usr/bin/python3', __DIR__.'/safe_file.py', $operation, $root, '-', '-', '-', ...$arguments]
            : ['sudo', rtrim((string) $binPath, '/').'/laranode-safe-file.sh', $operation, $systemUser, $stagedName, ...$arguments];

        try {
            $process = new Process($command);
            $process->setTimeout(120);
            $process->setInput($input);
            $process->run();

            if (! $process->isSuccessful()) {
                $message = trim($process->getErrorOutput()) ?: trim($process->getOutput());

                throw new RuntimeException($message ?: 'Secure filesystem operation failed');
            }

            return $process->getOutput();
        } finally {
            // the helper consumes and unlinks the staged file itself, so this
            // only cleans up after a failure before it got that far
            if ($stagedPath !== null && is_file($stagedPath)) {
                @unlink($stagedPath);
            }
        }
    }

    /** @param resource|string $input */
    private static function stageInput($input): string
    {
        $directory = storage_path('app/secure-file-staging');

        if (! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create secure staging directory');
        }

        $path = $directory.'/chunk-'.bin2hex(random_bytes(16));
        $destination = @fopen($path, 'x+b');

        if ($destination === false) {
            throw new RuntimeException('Unable to stage file contents');
        }

        try {
            if (is_resource($input)) {
                if (stream_copy_to_stream($input, $destination) === false) {
                    throw new RuntimeException('Unable to stage complete file contents');
                }
            } else {
                $remaining = $input;
                while ($remaining !== '') {
                    $written = fwrite($destination, $remaining);
                    if ($written === false || $written === 0) {
                        throw new RuntimeException('Unable to stage complete file contents');
                    }
                    $remaining = substr($remaining, $written);
                }
            }

            fflush($destination);
        } catch (\Throwable $exception) {
            @unlink($path);

            throw $exception;
        } finally {
            fclose($destination);
        }

        chmod($path, 0600);

        return $path;
    }
}
