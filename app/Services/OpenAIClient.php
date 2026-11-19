<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;

class OpenAIClient
{
    public function isEnabled(): bool
    {
        return (bool) config('openai.forecasting_enabled', false);
    }

    public function generateJson(string $prompt, array $options = []): array
    {
        if (!$this->isEnabled() || !config('openai.api_key') || !config('openai.model')) throw new ForecastFailure('provider_configuration');
        $schema = $this->schema($options['response_schema'] ?? ['type' => 'object']);
        $timeout = max(1, min(60, (int) config('openai.timeout_seconds', 45)));
        try {
            $response = Http::withToken(config('openai.api_key'))->acceptJson()->connectTimeout(min(15, $timeout))->timeout($timeout)
                ->withOptions(OpenAITransport::options())
                ->post('https://api.openai.com/v1/responses', [
                    'model' => config('openai.model'), 'store' => false,
                    'input' => $prompt, 'max_output_tokens' => 12000,
                    'text' => ['format' => ['type' => 'json_schema', 'name' => 'document_forecast', 'strict' => true, 'schema' => $schema]],
                ]);
        } catch (ConnectionException $error) {
            $previous = $error->getPrevious();
            $context = $previous && method_exists($previous, 'getHandlerContext') ? $previous->getHandlerContext() : [];
            \Illuminate\Support\Facades\Log::warning('Forecast provider transport failed.', ['curl_errno' => (int) ($context['errno'] ?? 0)]);
            throw new ForecastFailure('provider_connection');
        }
        if (!$response->successful()) throw new ForecastFailure('provider_http', $response->status());
        if (strlen($response->body()) > 2000000) throw new ForecastFailure('response_size');
        if ($response->json('status') !== 'completed') throw new ForecastFailure('response_json');
        $text = '';
        foreach ($response->json('output', []) as $item) {
            foreach ($item['content'] ?? [] as $part) if (($part['type'] ?? null) === 'output_text') $text .= $part['text'] ?? '';
        }
        $decoded = json_decode($text, true);
        if (!is_array($decoded)) throw new ForecastFailure('response_json');
        return $decoded;
    }

    private function schema(array $schema): array
    {
        if (isset($schema['type']) && is_string($schema['type'])) $schema['type'] = strtolower($schema['type']);
        if (($schema['type'] ?? null) === 'object') $schema['additionalProperties'] = false;
        foreach ($schema['properties'] ?? [] as $key => $property) $schema['properties'][$key] = $this->schema($property);
        if (isset($schema['items'])) $schema['items'] = $this->schema($schema['items']);
        return $schema;
    }
}
