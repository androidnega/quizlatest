<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class DeepSeekAiService
{
    private const string DEFAULT_BASE = 'https://api.deepseek.com';

    public function __construct(
        private readonly SystemSettingsService $system,
        private readonly PracticeModuleSettings $practiceSettings,
    ) {}

    /**
     * @return array{content: string, prompt_tokens: int, completion_tokens: int, total_tokens: int, model: string|null}
     */
    public function chatJsonInstruction(User $user, string $systemPrompt, string $userPrompt): array
    {
        $apiKey = $this->system->get('deepseek_api_key');
        if ($apiKey === null || $apiKey === '') {
            throw ValidationException::withMessages([
                'ai' => __('DeepSeek API key is not configured.'),
            ]);
        }

        $model = $this->practiceSettings->deepseekModel();
        $base = self::DEFAULT_BASE;

        $estimateTokens = (int) ceil((strlen($systemPrompt) + strlen($userPrompt)) / 4) + 2048;
        app(PracticeAiQuotaService::class)->assertTokenBudgetAllows($user, $estimateTokens);

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(120)
            ->post($base.'/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object'],
            ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'ai' => __('AI request failed: :msg', ['msg' => $response->body()]),
            ]);
        }

        $json = $response->json();
        $content = $json['choices'][0]['message']['content'] ?? '';
        if (! is_string($content) || $content === '') {
            throw ValidationException::withMessages([
                'ai' => __('Empty AI response.'),
            ]);
        }

        $usage = $json['usage'] ?? [];

        return [
            'content' => $content,
            'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
            'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
            'model' => $json['model'] ?? $model,
        ];
    }

    /**
     * Summary generation does not require JSON object mode on all providers — use plain text.
     *
     * @return array{content: string, prompt_tokens: int, completion_tokens: int, total_tokens: int, model: string|null}
     */
    public function chatPlainInstruction(User $user, string $systemPrompt, string $userPrompt): array
    {
        $apiKey = $this->system->get('deepseek_api_key');
        if ($apiKey === null || $apiKey === '') {
            throw ValidationException::withMessages([
                'ai' => __('DeepSeek API key is not configured.'),
            ]);
        }

        $model = $this->practiceSettings->deepseekModel();
        $base = self::DEFAULT_BASE;

        $estimateTokens = (int) ceil((strlen($systemPrompt) + strlen($userPrompt)) / 4) + 4096;
        app(PracticeAiQuotaService::class)->assertTokenBudgetAllows($user, $estimateTokens);

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(120)
            ->post(rtrim($base, '/').'/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.4,
            ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'ai' => __('AI request failed: :msg', ['msg' => $response->body()]),
            ]);
        }

        $json = $response->json();
        $content = $json['choices'][0]['message']['content'] ?? '';
        if (! is_string($content) || $content === '') {
            throw ValidationException::withMessages([
                'ai' => __('Empty AI response.'),
            ]);
        }

        $usage = $json['usage'] ?? [];

        return [
            'content' => $content,
            'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
            'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
            'model' => $json['model'] ?? $model,
        ];
    }
}
