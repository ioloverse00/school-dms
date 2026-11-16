<?php

namespace App\Services;

use RuntimeException;
use Throwable;

class ForecastFailure extends RuntimeException
{
    private const STAGES = [
        'provider_configuration' => 'configuration',
        'provider_connection' => 'provider',
        'provider_http' => 'provider',
        'response_size' => 'response',
        'response_json' => 'parsing',
        'schema_invalid' => 'validation',
        'document_types_invalid' => 'validation',
        'counts_invalid' => 'validation',
        'totals_inconsistent' => 'validation',
    ];

    public function __construct(private readonly string $reason, private readonly ?int $httpStatus = null)
    {
        parent::__construct('Forecast failed: '.(isset(self::STAGES[$reason]) ? $reason : 'unexpected'));
    }

    public static function diagnostics(Throwable $exception): array
    {
        $reason = $exception instanceof self && isset(self::STAGES[$exception->reason]) ? $exception->reason : 'unexpected';
        $context = ['reason' => $reason, 'stage' => self::STAGES[$reason] ?? 'internal', 'exception_type' => get_class($exception)];
        if ($exception instanceof self && $reason === 'provider_http' && $exception->httpStatus >= 100 && $exception->httpStatus <= 599) {
            $context['http_status'] = $exception->httpStatus;
        }
        return $context;
    }
}
