<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Tests\Unit;

use Fiachehr\LaravelLogger\Security\PrivacyPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PrivacyPolicyTest extends TestCase
{
    public static function secretKeys(): array
    {
        return array_map(fn ($key) => [$key], ['password', 'PasswordConfirmation', 'accessToken', 'refresh_token', 'apiKey', 'Authorization', 'coo-kies', 'session_id', 'csrfToken', 'MFA', 'otpCode', 'private_key', 'cardNumber', 'IBAN', 'emailAddress', 'phoneNumber', 'street-address', 'bindings', 'requestBody']);
    }

    #[DataProvider('secretKeys')]
    public function test_secrets_stay_excluded_even_when_allowlisted(string $key): void
    {
        $policy = new PrivacyPolicy(['payload_level' => 'safe', 'safe_fields' => [$key, 'operation']]);
        self::assertTrue($policy->sensitive($key));
        self::assertSame([], $policy->context([$key => 'SECRET_CANARY', 'operation' => [$key => 'SECRET_CANARY']])['operation'] ?? []);
        self::assertStringNotContainsString('SECRET_CANARY', json_encode($policy->context([$key => 'SECRET_CANARY'])));
    }

    public function test_modes_unknown_objects_and_cycles_are_bounded(): void
    {
        $object = new class implements \JsonSerializable
        {
            public function jsonSerialize(): mixed
            {
                throw new \RuntimeException('Must never run');
            }

            public function __toString(): string
            {
                throw new \RuntimeException('Must never run');
            }
        };
        $values = ['operation' => $object, 'count' => 2, 'unapproved' => 'SECRET_CANARY'];
        self::assertSame([], (new PrivacyPolicy(['payload_level' => 'none']))->context($values));
        self::assertSame(['count' => 2], (new PrivacyPolicy)->context($values));
        $values['operation'] = &$values;
        $result = (new PrivacyPolicy(['payload_level' => 'safe']))->context($values);
        self::assertLessThan(1000, strlen(json_encode($result)));
    }

    public function test_categorical_default_and_redaction_defense_in_depth(): void
    {
        self::assertSame('Application log event', (new PrivacyPolicy)->message('SECRET_CANARY'));
        $policy = new PrivacyPolicy(['message_mode' => 'redacted']);
        $text = $policy->message('password=SECRET_CANARY Bearer SECRET_CANARY jane@example.com https://example.com/?token=SECRET_CANARY');
        self::assertStringNotContainsString('SECRET_CANARY', $text);
        self::assertStringNotContainsString('jane@example.com', $text);
        self::assertSame('App\\Jobs\\MailJob', $policy->identifier('App\\Jobs\\MailJob'));
        self::assertSame('unknown', $policy->identifier('<script>bad</script>'));
        self::assertNotFalse(json_encode($policy->text("bad\xC3\x28\ntext"), JSON_THROW_ON_ERROR));
    }
}
