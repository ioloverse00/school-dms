<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;

class GeminiClient
{
    public function isEnabled(): bool
    {
        return (bool) config('gemini.enabled', false);
    }

    public function generateJson(string $prompt, array $options = []): array
    {
        if (!$this->isEnabled() || !(string) config('gemini.api_key')) {
            throw new ForecastFailure('provider_configuration');
        }
        $model = (string) ($options['model'] ?? config('gemini.model'));
        $baseUrl = rtrim((string) config('gemini.base_url'), '/');
        $timeout = max(1, min(25, (int) ($options['timeout_seconds'] ?? config('gemini.timeout_seconds', 25))));
        $generation = ['temperature' => (float) ($options['temperature'] ?? 0.2), 'responseMimeType' => 'application/json'];
        if (is_array($options['response_schema'] ?? null)) $generation['responseSchema'] = $options['response_schema'];
        if ($model === 'gemini-2.5-flash') $generation['thinkingConfig'] = ['thinkingBudget' => 0];
        // One bounded request. No automatic fallback/retry multiplication on shared hosting.
        try {
            $response = Http::connectTimeout(min(5, $timeout))->timeout($timeout)->acceptJson()
                ->withHeaders(['Content-Type' => 'application/json', 'x-goog-api-key' => (string) config('gemini.api_key')])
                ->post($baseUrl.'/v1beta/models/'.$model.':generateContent', [
                    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                    'generationConfig' => $generation,
                ]);
        } catch (ConnectionException $exception) {
            $previous = $exception->getPrevious();
            $context = $previous && method_exists($previous, 'getHandlerContext') ? $previous->getHandlerContext() : [];
            // Numeric transport code only: never log request URLs, headers or provider bodies.
            \Illuminate\Support\Facades\Log::warning('Gemini transport failed.', ['curl_errno' => (int) ($context['errno'] ?? 0)]);
            throw new ForecastFailure('provider_connection');
        }
        if (!$response->successful()) {
            throw new ForecastFailure('provider_http', $response->status());
        }
        if (strlen($response->body()) > 2000000) {
            throw new ForecastFailure('response_size');
        }
        $text = $response->json('candidates.0.content.parts.0.text');
        $decoded = is_string($text) ? $this->parseJsonFromText($text) : null;
        if (!is_array($decoded)) { throw new ForecastFailure('response_json'); }
        return $decoded;
    }
    private function parseJsonFromText(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        // Handle fenced code blocks: ```json ... ```
        if (preg_match('/^```(?:json)?\\s*(.*?)\\s*```\\s*$/is', $text, $m)) {
            $text = trim($m[1] ?? '');
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Try to extract a JSON object/array substring from mixed text.
        $substr = $this->extractTopLevelJsonSubstring($text);
        if ($substr === null) {
            return null;
        }

        $decoded = json_decode($substr, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function extractTopLevelJsonSubstring(string $text): ?string
    {
        $startObj = strpos($text, '{');
        $startArr = strpos($text, '[');

        if ($startObj === false && $startArr === false) {
            return null;
        }

        $start = null;
        $open = null;
        $close = null;

        if ($startObj !== false && ($startArr === false || $startObj < $startArr)) {
            $start = $startObj;
            $open = '{';
            $close = '}';
        } else {
            $start = $startArr;
            $open = '[';
            $close = ']';
        }

        $depth = 0;
        $inString = false;
        $escape = false;
        $len = strlen($text);

        for ($i = $start; $i < $len; $i++) {
            $ch = $text[$i];

            if ($inString) {
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($ch === '\\') {
                    $escape = true;
                    continue;
                }
                if ($ch === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($ch === '"') {
                $inString = true;
                continue;
            }

            if ($ch === $open) {
                $depth++;
                continue;
            }
            if ($ch === $close) {
                $depth--;
                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }
}
