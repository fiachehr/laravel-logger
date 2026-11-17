<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Logging;

use DateTimeZone;
use Fiachehr\LaravelLogger\Context\CurrentContext;
use Fiachehr\LaravelLogger\Diagnostics\DiagnosticRecord;
use Fiachehr\LaravelLogger\Security\ExceptionNormalizer;
use Fiachehr\LaravelLogger\Security\PrivacyPolicy;
use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;
use Throwable;

final class JsonFormatter implements FormatterInterface
{
    public function __construct(
        private PrivacyPolicy $policy,
        private ExceptionNormalizer $exceptions,
        private CurrentContext $context,
        private string $environment,
        private string $application,
        private string $service,
    ) {}

    public function format(LogRecord $record): string
    {
        try {
            $exception = ($record->context['exception'] ?? null) instanceof Throwable ? $record->context['exception'] : null;
            $ambient = $this->context->get($exception);
            $event = $this->policy->identifier($record->context['event'] ?? 'application.log', 'application.log');
            $data = [
                'schema_version' => '1.0',
                'timestamp' => $record->datetime->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
                'level' => $record->level->getName(),
                'environment' => $this->policy->identifier($this->environment),
                'application' => $this->policy->identifier($this->application),
                'service' => $this->policy->identifier($this->service),
                'event' => $event,
                'message' => $this->policy->message($record->message),
                'context' => (object) $this->policy->context(array_merge($record->context, $ambient)),
            ];
            if (isset($ambient['request_id']) && is_string($ambient['request_id']) && preg_match('/\A[a-zA-Z0-9_-]{16,64}\z/D', $ambient['request_id'])) {
                $data['request_id'] = $ambient['request_id'];
            }
            if (($record->context['exception'] ?? null) instanceof Throwable) {
                $data['exception'] = $this->exceptions->normalize($record->context['exception']);
            }
            if (($record->context['_diagnostic'] ?? null) instanceof DiagnosticRecord) {
                $diagnostic = $record->context['_diagnostic'];
                $data['diagnostic'] = [
                    'label' => $this->policy->identifier($diagnostic->label, 'diagnostic'),
                    'summary' => $this->policy->summary($diagnostic->value),
                    'file' => $this->exceptions->path($diagnostic->file),
                    'line' => $diagnostic->line,
                ];
            }
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (strlen($json) > max(512, $this->policy->limit('max_event_bytes', 16384, 65536))) {
                $data = array_intersect_key($data, array_flip(['schema_version', 'timestamp', 'level', 'request_id']));
                $data['event'] = 'logging.event_truncated';
                $data['message'] = 'Event exceeded configured limit';
                $json = json_encode($data, JSON_THROW_ON_ERROR);
            }

            return $json."\n";
        } catch (Throwable) {
            // Never log formatter input or recurse into Laravel's logger on failure.
            return '{"schema_version":"1.0","level":"WARNING","event":"logging.encoding_failed","message":"Log event omitted"}'."\n";
        }
    }

    public function formatBatch(array $records): array
    {
        return array_map($this->format(...), $records);
    }
}
