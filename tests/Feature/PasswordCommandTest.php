<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Tests\Feature;

use Fiachehr\LaravelLogger\Console\PasswordCommand;
use Fiachehr\LaravelLogger\Tests\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class PasswordCommandTest extends TestCase
{
    private function runPassword(array $inputs, bool $interactive = true): CommandTester
    {
        $command = app(PasswordCommand::class);
        $command->setLaravel(app());
        $tester = new CommandTester($command);
        $tester->setInputs($inputs);
        $tester->execute([], ['interactive' => $interactive]);

        return $tester;
    }

    public function test_hidden_prompt_outputs_a_verifiable_hash_and_never_the_password(): void
    {
        $password = 'Synthetic-command-password-ONLY';
        $tester = $this->runPassword([$password, $password]);
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringNotContainsString($password, $tester->getDisplay());
        self::assertMatchesRegularExpression("/LOG_EXPLORER_PASSWORD_HASH='[^']+'/", $tester->getDisplay());
        preg_match("/LOG_EXPLORER_PASSWORD_HASH='([^']+)'/", $tester->getDisplay(), $matches);
        self::assertTrue(password_verify($password, $matches[1]));
    }

    public function test_short_mismatched_and_noninteractive_passwords_are_rejected(): void
    {
        foreach ([[['short'], true], [['long-enough-password', 'does-not-match'], true], [[], false]] as [$inputs, $interactive]) {
            $tester = $this->runPassword($inputs, $interactive);
            self::assertSame(1, $tester->getStatusCode());
            self::assertStringNotContainsString('LOG_EXPLORER_PASSWORD_HASH=', $tester->getDisplay());
        }
    }
}
