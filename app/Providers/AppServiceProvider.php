<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\Quiz;
use App\Models\University;
use App\Policies\CoursePolicy;
use App\Policies\ExamPolicy;
use App\Policies\UniversityPolicy;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(University::class, UniversityPolicy::class);
        Gate::policy(Course::class, CoursePolicy::class);
    }
}
