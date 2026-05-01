<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\CoordinatorController;
use App\Http\Controllers\Admin\UniversityController;
use App\Http\Controllers\Coordinator\DashboardController as CoordinatorDashboardController;
use App\Http\Controllers\Coordinator\ClassCourseAssignmentController;
use App\Http\Controllers\Coordinator\ClassroomController;
use App\Http\Controllers\Coordinator\CourseController;
use App\Http\Controllers\Coordinator\LevelController;
use App\Http\Controllers\Coordinator\ProgramController;
use App\Http\Controllers\Coordinator\StudentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamSessionController;
use App\Http\Controllers\ProctoringUploadController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('proctoring/uploads')
        ->name('proctoring.uploads.')
        ->group(function () {
            Route::post('/path', [ProctoringUploadController::class, 'createUploadPath'])->name('path');
            Route::post('/file', [ProctoringUploadController::class, 'uploadFile'])->name('file');
            Route::post('/metadata', [ProctoringUploadController::class, 'storeMetadata'])->name('metadata');
        });

    Route::prefix('exam-sessions')
        ->name('exam-sessions.')
        ->group(function () {
            Route::post('/start', [ExamSessionController::class, 'start'])->name('start');
            Route::post('/{examSession}/answers', [ExamSessionController::class, 'saveAnswer'])->name('answers.save');
            Route::post('/{examSession}/heartbeat', [ExamSessionController::class, 'heartbeat'])->name('heartbeat');
            Route::post('/{examSession}/proctoring-events', [ExamSessionController::class, 'logProctoringEvent'])->name('proctoring-events.store');
            Route::post('/{examSession}/submit', [ExamSessionController::class, 'submit'])->name('submit');
            Route::post('/{examSession}/force-submit', [ExamSessionController::class, 'forceSubmit'])->name('force-submit');
            Route::get('/{examSession}/review-timeline', [ExamSessionController::class, 'reviewTimeline'])->name('review-timeline');
            Route::post('/{examSession}/review/release', [ExamSessionController::class, 'releaseHeldResult'])->name('review.release');
            Route::post('/{examSession}/review/confirm-fail', [ExamSessionController::class, 'confirmFail'])->name('review.confirm-fail');
            Route::post('/{examSession}/review/override', [ExamSessionController::class, 'overrideDecision'])->name('review.override');
        });
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

        Route::get('/students', [StudentController::class, 'index'])->name('students.index');
        Route::get('/students/upload', [StudentController::class, 'uploadForm'])->name('students.upload');
        Route::post('/students/upload/preview', [StudentController::class, 'previewImport'])->name('students.preview');
        Route::post('/students/upload/import', [StudentController::class, 'import'])->name('students.import');
        Route::post('/students/bulk-status', [StudentController::class, 'bulkStatus'])->name('students.bulk-status');
        Route::post('/students/bulk-assign-class', [StudentController::class, 'bulkAssignClass'])->name('students.bulk-assign-class');
        Route::get('/students/{student}/assign-class', [StudentController::class, 'editClass'])->name('students.assign-class.edit');
        Route::put('/students/{student}/assign-class', [StudentController::class, 'updateClass'])->name('students.assign-class.update');
        Route::get('/students/template', [StudentController::class, 'template'])->name('students.template');
        Route::get('/programs', [ProgramController::class, 'index'])->name('programs.index');
        Route::get('/programs/create', [ProgramController::class, 'create'])->name('programs.create');
        Route::post('/programs', [ProgramController::class, 'store'])->name('programs.store');
        Route::get('/programs/{program}/edit', [ProgramController::class, 'edit'])->name('programs.edit');
        Route::put('/programs/{program}', [ProgramController::class, 'update'])->name('programs.update');
        Route::patch('/programs/{program}/toggle-status', [ProgramController::class, 'toggleStatus'])->name('programs.toggle-status');

        Route::get('/levels', [LevelController::class, 'index'])->name('levels.index');
        Route::patch('/levels/{level}/toggle-status', [LevelController::class, 'toggleStatus'])->name('levels.toggle-status');
        Route::get('/classes', [ClassroomController::class, 'index'])->name('classes.index');
        Route::get('/classes/create', [ClassroomController::class, 'create'])->name('classes.create');
        Route::post('/classes', [ClassroomController::class, 'store'])->name('classes.store');
        Route::get('/classes/{classroom}/edit', [ClassroomController::class, 'edit'])->name('classes.edit');
        Route::put('/classes/{classroom}', [ClassroomController::class, 'update'])->name('classes.update');
        Route::patch('/classes/{classroom}/toggle-status', [ClassroomController::class, 'toggleStatus'])->name('classes.toggle-status');
        Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
        Route::get('/courses/create', [CourseController::class, 'create'])->name('courses.create');
        Route::post('/courses', [CourseController::class, 'store'])->name('courses.store');
        Route::get('/courses/{course}/edit', [CourseController::class, 'edit'])->name('courses.edit');
        Route::put('/courses/{course}', [CourseController::class, 'update'])->name('courses.update');
        Route::patch('/courses/{course}/toggle-status', [CourseController::class, 'toggleStatus'])->name('courses.toggle-status');
        Route::get('/courses/assign/classes', [ClassCourseAssignmentController::class, 'edit'])->name('courses.assign.edit');
        Route::post('/courses/assign/classes', [ClassCourseAssignmentController::class, 'update'])->name('courses.assign.update');
    });

require __DIR__.'/auth.php';
