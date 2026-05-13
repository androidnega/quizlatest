<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Calls the configured LLM using {@see AiIntegrationSettings}. Never logs secrets.
 */
final class ExamAiQuestionGenerator
{
    public function __construct(
        private readonly AiIntegrationSettings $aiSettings,
        private readonly ExamQuestionImportValidator $importValidator,
    ) {}

    /**
     * @param  list<string>|null  $allowedTypes  Question types permitted for this run (e.g. assessment selection).
     * @param  list<string>|null  $existingQuestionTextsNormalized  Lowercased trimmed question_text values already in the pool.
     * @return array{ok: true, sections: list<array<string, mixed>>}|array{ok: false, errors: list<string>}
     */
    public function generateFromPrompt(string $prompt, ?array $allowedTypes = null, ?array $existingQuestionTextsNormalized = null): array
    {
        $key = $this->aiSettings->apiKey();
        $model = trim((string) ($this->aiSettings->modelName() ?? ''));
        if ($key === null || trim($key) === '') {
            return ['ok' => false, 'errors' => ['AI API key is not configured.']];
        }
        if ($model === '') {
            return ['ok' => false, 'errors' => ['AI model name is not configured.']];
        }

        $provider = $this->resolveProvider($model);
        $endpoint = $provider === 'deepseek'
            ? 'https://api.deepseek.com/chat/completions'
            : 'https://api.openai.com/v1/chat/completions';

        try {
            $response = Http::timeout(120)
                ->withToken($key)
                ->acceptJson()
                ->post($endpoint, [
                    'model' => $model,
                    'temperature' => 0.25,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You return only compact JSON matching the user schema. No markdown.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ]);
        } catch (ConnectionException) {
            return ['ok' => false, 'errors' => ['AI request timed out or could not connect. Check internet/server access and try again.']];
        } catch (\Throwable) {
            return ['ok' => false, 'errors' => ['AI request failed before reaching provider. Try again later.']];
        }

        if (! $response->successful()) {
            $status = $response->status();
            $providerMessage = trim((string) (
                data_get($response->json(), 'error.message')
                ?? data_get($response->json(), 'message')
                ?? ''
            ));

            $hint = match ($status) {
                401 => 'Unauthorized: check AI API key in Admin Settings.',
                403 => 'Forbidden: provider blocked this request/model for this key.',
                404 => 'Model not found: verify AI model name in Admin Settings.',
                429 => 'Rate-limited: too many requests or quota exceeded.',
                500, 502, 503, 504 => 'Provider is temporarily unavailable.',
                default => 'Provider returned an error.',
            };

            $suffix = $providerMessage !== '' ? ' '.$providerMessage : '';

            return ['ok' => false, 'errors' => ["AI provider error ({$status}, {$provider}): {$hint}{$suffix}"]];
        }

        $content = data_get($response->json(), 'choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            return ['ok' => false, 'errors' => ['AI response was empty.']];
        }

        $extracted = $this->extractJsonObject($content);

        return $this->importValidator->validateJsonString($extracted, $allowedTypes, $existingQuestionTextsNormalized);
    }

    private function extractJsonObject(string $content): string
    {
        $trim = trim($content);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $trim, $m)) {
            return trim($m[1]);
        }

        return $trim;
    }

    private function resolveProvider(string $model): string
    {
        $m = strtolower(trim($model));

        if (str_contains($m, 'deepseek')) {
            return 'deepseek';
        }

        return 'openai';
    }
}
