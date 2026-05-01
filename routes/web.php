<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\CoordinatorController;
use App\Http\Controllers\Admin\UniversityController;
use App\Http\Controllers\Coordinator\DashboardController as CoordinatorDashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return match (auth()->user()->role) {
        'admin' => redirect()->route('admin.dashboard'),
        'coordinator' => redirect()->route('coordinator.dashboard'),
        'student' => redirect()->route('student.dashboard'),
        default => redirect()->route('login'),
    };
});

Route::get('/dashboard', function () {
    return match (auth()->user()?->role) {
        'admin' => redirect()->route('admin.dashboard'),
        'coordinator' => redirect()->route('coordinator.dashboard'),
        'student' => redirect()->route('student.dashboard'),
        default => redirect()->route('login'),
    };
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', 'admin'])
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/universities', [UniversityController::class, 'index'])->name('universities.index');
        Route::get('/universities/create', [UniversityController::class, 'create'])->name('universities.create');
        Route::post('/universities', [UniversityController::class, 'store'])->name('universities.store');
        Route::get('/universities/{university}/edit', [UniversityController::class, 'edit'])->name('universities.edit');
        Route::put('/universities/{university}', [UniversityController::class, 'update'])->name('universities.update');

        Route::get('/coordinators', [CoordinatorController::class, 'index'])->name('coordinators.index');
        Route::get('/coordinators/create', [CoordinatorController::class, 'create'])->name('coordinators.create');
        Route::post('/coordinators', [CoordinatorController::class, 'store'])->name('coordinators.store');
        Route::get('/coordinators/{coordinator}/edit', [CoordinatorController::class, 'edit'])->name('coordinators.edit');
        Route::put('/coordinators/{coordinator}', [CoordinatorController::class, 'update'])->name('coordinators.update');
    });

Route::prefix('coordinator')
    ->name('coordinator.')
    ->middleware(['auth', 'verified', 'coordinator'])
    ->group(function () {
        Route::get('/dashboard', [CoordinatorDashboardController::class, 'index'])->name('dashboard');

        Route::view('/students', 'coordinator.placeholders.students')->name('students.index');
        Route::view('/programs', 'coordinator.placeholders.programs')->name('programs.index');
        Route::view('/levels', 'coordinator.placeholders.levels')->name('levels.index');
        Route::view('/classes', 'coordinator.placeholders.classes')->name('classes.index');
        Route::view('/courses', 'coordinator.placeholders.courses')->name('courses.index');
    });

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/student/dashboard', function () {
        abort_unless(auth()->user()?->role === 'student', 403);

        return view('student.dashboard');
    })->name('student.dashboard');
});

require __DIR__.'/auth.php';
