<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class Laranode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laranode:create-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates an admin account for Laranode';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->askUntilValid('What is your name?', function ($answer) {
            return !empty($answer);
        });

        $email = $this->askUntilValid('Email', function ($answer) {
            return !empty($answer) && filter_var($answer, FILTER_VALIDATE_EMAIL);
        });

        $password = $this->askUntilValid('Password', function ($answer) {
            return !empty($answer) && strlen($answer) >= 6;
        }, 'Password must be at least 6 characters');

        $this->info('Creating admin account');

        User::create([
            'username' => 'laranode_ln', // don't change this
            'name' => $name,
            'email' => $email,
            'password' => bcrypt($password),
            'role' => 'admin',
            'ssh_access' => true,
        ]);


        $this->info('Done. Login at ' . route('login'));
    }

    /**
     * Ask a question and validate the answer
     *
     * @param string $question
     * @param callable $validator
     * @param string $errorMessage
     * @return string
     */
    protected function askUntilValid($question, callable $validator, $errorMessage = 'The input is invalid, please try again.')
    {
        $answer = $this->ask($question);

        while (!$validator($answer)) {
            $this->error($errorMessage);
            $answer = $this->ask($question);
        }

        return $answer;
    }
}
