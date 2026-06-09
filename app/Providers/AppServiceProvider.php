<?php

namespace App\Providers;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\ExamSession;
use App\Models\Level;
use App\Models\Program;
use App\Models\Quiz;
use App\Models\Term;
use App\Models\University;
use App\Models\User;
use App\Policies\ClassroomPolicy;
use App\Policies\CoursePolicy;
use App\Policies\ExamPolicy;
use App\Policies\ExamSessionPolicy;
use App\Policies\LevelPolicy;
use App\Policies\ProgramPolicy;
use App\Policies\UniversityPolicy;
use App\Policies\UserPolicy;
use App\Services\PracticeModuleSettings;
use App\Services\StudentNoticeDigestService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.name') === 'Laravel') {
            Config::set('app.name', 'QuizSnap');
        }

        // Audit P3.8: surface accidental N+1 lazy loads in non-production
        // (tests + local dev) so they get fixed before hitting the shared
        // host. We deliberately do NOT call ->shouldBeStrict() because
        // it also asserts that every selected attribute exists on the
        // model — projecting columns is one of the optimisations we just
        // applied, so silent missing-attribute access is a feature here,
        // not a bug.
        Model::preventLazyLoading(! $this->app->isProduction());

        Gate::policy(Quiz::class, ExamPolicy::class);
        Gate::policy(ExamSession::class, ExamSessionPolicy::class);
        Gate::policy(University::class, UniversityPolicy::class);
        Gate::policy(Course::class, CoursePolicy::class);
        Gate::policy(Program::class, ProgramPolicy::class);
        Gate::policy(Level::class, LevelPolicy::class);
        Gate::policy(Classroom::class, ClassroomPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        $userPolicy = app(UserPolicy::class);
        Gate::define(
            'manageCoordinatorDirectory',
            fn (User $user): bool => $userPolicy->manageCoordinatorDirectory($user),
        );
        Gate::define(
            'viewStudentDirectory',
            fn (User $user): bool => $userPolicy->viewStudentDirectory($user),
        );
        Gate::define(
            'manageSystemSettings',
            fn (User $user): bool => $userPolicy->manageSystemSettings($user),
        );
        Gate::define(
            'manageGlobalUserAccounts',
            fn (User $user): bool => $userPolicy->manageGlobalUserAccounts($user),
        );

        // Audit Phase 7 / P2.2: this composer runs for layouts.navigation,
        // components.layouts.student, student.dashboard AND
        // dashboard-mobile-wallet — that's up to 4 invocations per page
        // render. Without memoization the StudentNoticeDigestService::noticeCount
        // and ::noticesFor queries each fire 4x. We cache the heavy lookups
        // for the lifetime of one HTTP request via a static memo array
        // keyed by user id.
        $studentPracticeComposer = function ($view): void {
            static $memo = [];

            $user = auth()->user();
            $isStudent = $user !== null && $user->role === 'student';
            $userKey = $user?->id ?? 0;

            if (! isset($memo[$userKey])) {
                $practice = app(PracticeModuleSettings::class);
                $noticeService = app(StudentNoticeDigestService::class);

                $seenAt = $isStudent ? (string) session('student_notifications_seen_at', '') : '';
                $seenAt = $seenAt !== '' ? $seenAt : null;

                $memo[$userKey] = [
                    'studentPracticeNavEnabled' => $isStudent && $practice->studentPracticeEnabled(),
                    'studentMaterialsBrowseEnabled' => $isStudent && $practice->studentCourseMaterialsBrowseEnabled(),
                    'practiceEnabled' => $practice->studentPracticeEnabled(),
                    'studentNoticeCount' => $isStudent ? (int) $noticeService->noticeCount($user, $seenAt) : 0,
                    'studentHeaderNotices' => $isStudent ? $noticeService->noticesFor($user, 8, $seenAt) : [],
                ];
            }

            $cached = $memo[$userKey];
            $view->with('studentPracticeNavEnabled', $cached['studentPracticeNavEnabled']);
            $view->with('studentMaterialsBrowseEnabled', $cached['studentMaterialsBrowseEnabled']);
            $view->with(
                'studentCourseMaterialsNavEnabled',
                $cached['studentMaterialsBrowseEnabled'] && ! $cached['practiceEnabled'],
            );
            $view->with('studentNoticeCount', $cached['studentNoticeCount']);
            $view->with('studentHeaderNotices', $cached['studentHeaderNotices']);
        };

        View::composer('layouts.navigation', $studentPracticeComposer);
        View::composer('components.layouts.student', $studentPracticeComposer);
        // The mobile wallet bell + the dashboard's own greeting need the
        // unread-notice count too. The x-component layout composer above
        // does NOT bleed into slot content, so register the same composer
        // directly onto the dashboard view + the wallet partial. This makes
        // $studentNoticeCount available wherever the bell is rendered.
        View::composer('student.dashboard', $studentPracticeComposer);
        View::composer('student.partials.dashboard-mobile-wallet', $studentPracticeComposer);

        $staffLayoutComposer = function ($view): void {
            $user = auth()->user();
            if ($user === null || $user->university_id === null) {
                return;
            }

            // Audit P2.2: short-cache active year / active term lookups.
            $year = \Illuminate\Support\Facades\Cache::remember(
                "academic_year_active_full:university:{$user->university_id}",
                300,
                fn () => AcademicYear::activeForUniversity((int) $user->university_id),
            );
            $term = $year !== null
                ? \Illuminate\Support\Facades\Cache::remember(
                    "term_active:year:{$year->id}",
                    300,
                    fn () => Term::activeForAcademicYear($year->id),
                )
                : null;

            $badge = null;
            if ($year !== null) {
                $badge = __('Academic year').' · '.$year->name;
                if ($term !== null) {
                    $termLabel = trim((string) $term->name);
                    $isGenericFullYear = $termLabel === ''
                        || Str::contains(Str::lower($termLabel), 'full year');
                    if (! $isGenericFullYear) {
                        $badge .= ' · '.$termLabel;
                    }
                }
            }

            $view->with('staffAcademicPeriodBadge', $badge);
        };

        View::composer('components.layouts.coordinator', $staffLayoutComposer);
    }
}
