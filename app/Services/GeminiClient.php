<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiClient
{
    public function isEnabled(): bool
    {
        return (bool) config('gemini.enabled', false);
    }

    public function generateJson(string $prompt, array $options = []): array
    {
        if (!$this->isEnabled()) {
            throw new RuntimeException('Gemini is disabled. Set GEMINI_ENABLED=true.');
        }

        $apiKey = (string) config('gemini.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('Missing GEMINI_API_KEY.');
        }

        $primaryModel = (string) ($options['model'] ?? config('gemini.model'));
        $fallbackModels = (array) config('gemini.fallback_models', []);
        $modelsToTry = array_values(array_filter(array_merge([$primaryModel], $fallbackModels)));
        $baseUrl = rtrim((string) config('gemini.base_url'), '/');
        $timeout = (int) ($options['timeout_seconds'] ?? config('gemini.timeout_seconds', 30));
        $retries = (int) ($options['retries'] ?? config('gemini.retries', 2));

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => (float) ($options['temperature'] ?? 0.2),
            ],
        ];

        $lastError = null;

        foreach ($modelsToTry as $model) {
            $url = $baseUrl . '/v1beta/models/' . $model . ':generateContent';
            $attempts = max(1, $retries + 1);

            for ($attempt = 1; $attempt <= $attempts; $attempt++) {
                $response = Http::timeout($timeout)
                    ->acceptJson()
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($url . '?key=' . urlencode($apiKey), $payload);

                if ($response->successful()) {
                    $data = $response->json();
                    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    if (!is_string($text) || trim($text) === '') {
                        $lastError = 'Gemini returned an empty response.';
                        continue;
                    }

                    $decoded = $this->parseJsonFromText($text);
                    if (!is_array($decoded)) {
                        $lastError = 'Gemini did not return valid JSON.';
                        continue;
                    }

                    return $decoded;
                }

                $status = $response->status();
                $lastError = 'Gemini API error (' . $status . ') using model ' . $model . ': ' . $response->body();

                // Retry on transient errors
                if (in_array($status, [429, 500, 503], true) && $attempt < $attempts) {
                    usleep((int) (250000 * (2 ** ($attempt - 1))));
                    continue;
                }

                // If model is overloaded/unavailable, try next model (if any)
                if (in_array($status, [429, 503], true)) {
                    break;
                }

                // Non-transient: stop immediately
                throw new RuntimeException($lastError);
            }
        }

        throw new RuntimeException($lastError ?: 'Gemini API error.');
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
                if ($ch === '\\\\') {
                    $escape = true;
                    continue;
                }
                if ($ch === '\"') {
                    $inString = false;
                }
                continue;
            }

            if ($ch === '\"') {
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
