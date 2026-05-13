<?php

namespace App\Services;

/**
 * Admin-controlled toggles and limits for the unofficial practice / course-material AI module.
 */
class PracticeModuleSettings
{
    public function __construct(
        private readonly SystemSettingsService $system,
    ) {}

    public function studentPracticeEnabled(): bool
    {
        return $this->system->getBool('enable_student_practice_quizzes', false);
    }

    public function courseMaterialUploadsEnabled(): bool
    {
        return $this->system->getBool('enable_course_material_uploads', false);
    }

    public function aiSummaryEnabled(): bool
    {
        return $this->studentPracticeEnabled()
            && $this->system->getBool('enable_ai_summary', false);
    }

    public function aiPracticeQuizGenerationEnabled(): bool
    {
        return $this->studentPracticeEnabled()
            && $this->system->getBool('enable_ai_practice_quiz_generation', false);
    }

    public function examinerPracticeOverviewEnabled(): bool
    {
        return $this->studentPracticeEnabled()
            && $this->system->getBool('allow_examiner_practice_overview', false);
    }

    public function practiceQuizDailyLimit(): int
    {
        return max(0, $this->system->getInt('practice_quiz_daily_limit', 5));
    }

    public function practiceQuizMonthlyLimit(): int
    {
        return max(0, $this->system->getInt('practice_quiz_monthly_limit', 50));
    }

    /**
     * Rolling monthly token budget per student for practice AI features.
     */
    public function practiceAiTokenBudgetPerStudent(): int
    {
        return max(0, $this->system->getInt('practice_ai_token_limit_per_student', 100_000));
    }

    public function practiceAiProvider(): string
    {
        return strtolower(trim($this->system->get('practice_ai_provider') ?? 'deepseek')) ?: 'deepseek';
    }

    public function deepseekModel(): string
    {
        return trim($this->system->get('deepseek_model') ?? 'deepseek-chat') ?: 'deepseek-chat';
    }

    public function assertStudentPracticeOrAbort(): void
    {
        abort_unless($this->studentPracticeEnabled(), 403, __('Practice features are disabled.'));
    }

    /**
     * Students may browse uploaded course files when practice hub is on, or when material uploads are enabled alone.
     */
    public function studentCourseMaterialsBrowseEnabled(): bool
    {
        return $this->studentPracticeEnabled() || $this->courseMaterialUploadsEnabled();
    }

    public function assertStudentCourseMaterialsBrowseOrAbort(): void
    {
        abort_unless($this->studentCourseMaterialsBrowseEnabled(), 403, __('Course materials are not available.'));
    }

    public function assertMaterialUploadsOrAbort(): void
    {
        abort_unless($this->courseMaterialUploadsEnabled(), 403, __('Course material uploads are disabled.'));
    }

    public function assertAiSummaryOrAbort(): void
    {
        abort_unless($this->aiSummaryEnabled(), 403, __('AI summaries are disabled.'));
    }

    public function assertAiPracticeQuizOrAbort(): void
    {
        abort_unless($this->aiPracticeQuizGenerationEnabled(), 403, __('AI practice quiz generation is disabled.'));
    }

    public function assertExaminerOverviewOrAbort(): void
    {
        abort_unless($this->examinerPracticeOverviewEnabled(), 403, __('Practice analytics are disabled.'));
    }

    public function deepseekConfigured(): bool
    {
        $key = $this->system->get('deepseek_api_key');

        return $key !== null && $key !== '';
    }
}
