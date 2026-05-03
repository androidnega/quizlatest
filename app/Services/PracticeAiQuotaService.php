<?php

namespace App\Services;

use App\Models\AiUsageLog;
use App\Models\PracticeQuiz;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class PracticeAiQuotaService
{
    public function __construct(
        private readonly PracticeModuleSettings $practiceSettings,
    ) {}

    /**
     * @throws ValidationException
     */
    public function assertCanGenerateAiPracticeQuiz(User $student): void
    {
        $daily = $this->practiceSettings->practiceQuizDailyLimit();
        $monthly = $this->practiceSettings->practiceQuizMonthlyLimit();

        if ($daily > 0) {
            $countToday = PracticeQuiz::query()
                ->where('student_id', $student->id)
                ->where('generated_by_ai', true)
                ->whereDate('created_at', Carbon::today())
                ->count();
            if ($countToday >= $daily) {
                throw ValidationException::withMessages([
                    'limit' => __('Daily practice quiz generation limit reached.'),
                ]);
            }
        }

        if ($monthly > 0) {
            $countMonth = PracticeQuiz::query()
                ->where('student_id', $student->id)
                ->where('generated_by_ai', true)
                ->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
                ->count();
            if ($countMonth >= $monthly) {
                throw ValidationException::withMessages([
                    'limit' => __('Monthly practice quiz generation limit reached.'),
                ]);
            }
        }
    }

    /**
     * @throws ValidationException
     */
    public function assertCanGenerateAiSummary(User $student): void
    {
        $this->assertCanGenerateAiPracticeQuiz($student);
    }

    /**
     * @throws ValidationException
     */
    public function assertTokenBudgetAllows(User $student, int $additionalTokens): void
    {
        $budget = $this->practiceSettings->practiceAiTokenBudgetPerStudent();
        if ($budget <= 0) {
            return;
        }

        $used = AiUsageLog::query()
            ->where('user_id', $student->id)
            ->whereIn('feature', ['practice_quiz', 'practice_summary'])
            ->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->sum('total_tokens');

        if ($used + $additionalTokens > $budget) {
            throw ValidationException::withMessages([
                'limit' => __('Your monthly AI usage budget for practice features is exhausted.'),
            ]);
        }
    }

    public function logUsage(User $user, string $feature, string $provider, ?string $model, int $prompt, int $completion, int $total): void
    {
        AiUsageLog::query()->create([
            'user_id' => $user->id,
            'feature' => $feature,
            'provider' => $provider,
            'model' => $model,
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $total,
        ]);
    }
}
