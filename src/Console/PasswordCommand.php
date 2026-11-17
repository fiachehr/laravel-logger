<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Console;

use Illuminate\Console\Command;

final class PasswordCommand extends Command
{
    protected $signature = 'log-explorer:password';

    protected $description = 'Generate a dashboard password hash using hidden input; do not edit host files';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Run this command interactively to enter a password without shell history.');

            return self::FAILURE;
        }
        $password = $this->secret('Dashboard password (12-72 bytes)', false);
        if (! is_string($password) || strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
            $this->error('Use a password from 12 to 72 bytes without null characters.');

            return self::FAILURE;
        }
        $confirmation = $this->secret('Confirm dashboard password', false);
        if (! is_string($confirmation) || ! hash_equals($password, $confirmation)) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        unset($password, $confirmation);
        $this->line("LOG_EXPLORER_PASSWORD_HASH='".$hash."'");
        $this->line('Copy this line to your private environment configuration, set LOG_EXPLORER_USERNAME, and rebuild the host config cache.');

        return self::SUCCESS;
    }
}
