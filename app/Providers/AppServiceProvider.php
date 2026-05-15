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

        $studentPracticeComposer = function ($view): void {
            $user = auth()->user();
            $practice = app(PracticeModuleSettings::class);
            $view->with(
                'studentPracticeNavEnabled',
                $user !== null && $user->role === 'student' && $practice->studentPracticeEnabled(),
            );
            $view->with(
                'studentCourseMaterialsNavEnabled',
                $user !== null
                    && $user->role === 'student'
                    && $practice->courseMaterialUploadsEnabled()
                    && ! $practice->studentPracticeEnabled(),
            );
            $view->with(
                'studentNoticeCount',
                $user !== null && $user->role === 'student'
                    ? (int) app(StudentNoticeDigestService::class)->noticeCount($user)
                    : 0,
            );
        };

        View::composer('layouts.navigation', $studentPracticeComposer);
        View::composer('components.layouts.student', $studentPracticeComposer);

        $staffLayoutComposer = function ($view): void {
            $user = auth()->user();
            if ($user === null || $user->university_id === null) {
                return;
            }
            $year = AcademicYear::activeForUniversity((int) $user->university_id);
            $term = $year !== null ? Term::activeForAcademicYear($year->id) : null;

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
