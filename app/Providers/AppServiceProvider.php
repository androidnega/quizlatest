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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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

        View::composer('layouts.navigation', function ($view): void {
            $user = auth()->user();
            $view->with(
                'studentPracticeNavEnabled',
                $user !== null && $user->role === 'student' && app(PracticeModuleSettings::class)->studentPracticeEnabled(),
            );
        });

        View::composer('components.layouts.coordinator', function ($view): void {
            $user = auth()->user();
            if ($user === null || $user->university_id === null) {
                return;
            }
            $year = AcademicYear::activeForUniversity((int) $user->university_id);
            $term = $year !== null ? Term::activeForAcademicYear($year->id) : null;
            $badge = $year !== null ? trim($year->name.($term !== null ? ' · '.$term->name : '')) : null;
            $view->with('staffAcademicPeriodBadge', $badge);
        });
    }
}
