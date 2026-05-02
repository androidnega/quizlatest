<?php

namespace App\Services;

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
     * @return array{ok: true, sections: list<array<string, mixed>>}|array{ok: false, errors: list<string>}
     */
    public function generateFromPrompt(string $prompt): array
    {
        $key = $this->aiSettings->apiKey();
        $model = trim((string) ($this->aiSettings->modelName() ?? ''));
        if ($key === null || trim($key) === '') {
            return ['ok' => false, 'errors' => ['AI API key is not configured.']];
        }
        if ($model === '') {
            return ['ok' => false, 'errors' => ['AI model name is not configured.']];
        }

        try {
            $response = Http::timeout(120)
                ->withToken($key)
                ->acceptJson()
                ->post('https://api.openai.com/v1/chat/completions', [
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
        } catch (\Throwable) {
            return ['ok' => false, 'errors' => ['AI request failed. Try again later.']];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'errors' => ['AI provider returned an error. Try again later.']];
        }

        $content = data_get($response->json(), 'choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            return ['ok' => false, 'errors' => ['AI response was empty.']];
        }

        $extracted = $this->extractJsonObject($content);

        return $this->importValidator->validateJsonString($extracted);
    }

    private function extractJsonObject(string $content): string
    {
        $trim = trim($content);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $trim, $m)) {
            return trim($m[1]);
        }

        return $trim;
    }
}
