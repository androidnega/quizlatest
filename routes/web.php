<?php

use App\Http\Controllers\Admin\AcademicResetSnapshotsController;
use App\Http\Controllers\Admin\AcademicYearController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\CoordinatorController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ProctoringGovernanceController;
use App\Http\Controllers\Admin\UniversityController;
use App\Http\Controllers\Coordinator\AcademicResetController;
use App\Http\Controllers\Coordinator\ClassCourseAssignmentController;
use App\Http\Controllers\Coordinator\ClassroomController;
use App\Http\Controllers\Coordinator\CourseController;
use App\Http\Controllers\Coordinator\DashboardController as CoordinatorDashboardController;
use App\Http\Controllers\Coordinator\ExamSessionReviewController;
use App\Http\Controllers\Coordinator\LevelController;
use App\Http\Controllers\Coordinator\ManualGradingController;
use App\Http\Controllers\Coordinator\ProgramController;
use App\Http\Controllers\Coordinator\StudentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Examiner\CourseMaterialController as ExaminerCourseMaterialController;
use App\Http\Controllers\Examiner\DashboardController as ExaminerDashboardController;
use App\Http\Controllers\Examiner\ExamBuilderController;
use App\Http\Controllers\Examiner\PracticeOverviewController;
use App\Http\Controllers\ExamSessionController;
use App\Http\Controllers\ProctoringUploadController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Student\StudentCourseMaterialController;
use App\Http\Controllers\Student\StudentExamController;
use App\Http\Controllers\Student\StudentExamEntryController;
use App\Http\Controllers\Student\StudentPracticeHubController;
use App\Http\Controllers\Student\StudentPracticeQuizController;
use App\Http\Controllers\Student\StudentPracticeSummaryController;
use App\Http\Controllers\Student\StudentResultController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/student/exam/{examSession}', [StudentExamController::class, 'take'])
        ->name('student.exam.take');

    Route::middleware(['verified', 'student'])->group(function () {
        Route::get('/student/exams/{quiz}/prepare', [StudentExamEntryController::class, 'prepare'])
            ->name('student.exam.prepare');
    });

    Route::prefix('student/results')
        ->name('student.results.')
        ->middleware(['verified', 'student'])
        ->group(function () {
            Route::get('/', [StudentResultController::class, 'index'])->name('index');
            Route::get('/{examSession}/pdf', [StudentResultController::class, 'pdf'])->name('pdf');
            Route::get('/{examSession}', [StudentResultController::class, 'show'])->name('show');
        });

    Route::prefix('student/practice')
        ->name('student.practice.')
        ->middleware(['verified', 'student'])
        ->group(function () {
            Route::get('/', [StudentPracticeHubController::class, 'index'])->name('index');
            Route::get('/materials', [StudentCourseMaterialController::class, 'index'])->name('materials.index');
            Route::get('/materials/{material}/download', [StudentCourseMaterialController::class, 'download'])->name('materials.download');

            Route::get('/summaries', [StudentPracticeSummaryController::class, 'index'])->name('summaries.index');
            Route::post('/summaries', [StudentPracticeSummaryController::class, 'store'])->name('summaries.store');
            Route::get('/summaries/{practiceSummary}', [StudentPracticeSummaryController::class, 'show'])->name('summaries.show');

            Route::get('/quizzes', [StudentPracticeQuizController::class, 'index'])->name('quizzes.index');
            Route::get('/quizzes/create', [StudentPracticeQuizController::class, 'create'])->name('quizzes.create');
            Route::post('/quizzes', [StudentPracticeQuizController::class, 'store'])->name('quizzes.store');
            Route::get('/quizzes/{practiceQuiz}', [StudentPracticeQuizController::class, 'show'])->name('quizzes.show');
            Route::delete('/quizzes/{practiceQuiz}', [StudentPracticeQuizController::class, 'destroy'])->name('quizzes.destroy');
            Route::get('/quizzes/{practiceQuiz}/take', [StudentPracticeQuizController::class, 'take'])->name('quizzes.take');
            Route::post('/quizzes/{practiceQuiz}/submit', [StudentPracticeQuizController::class, 'submit'])->name('quizzes.submit');
            Route::get('/quizzes/{practiceQuiz}/results/{attempt}', [StudentPracticeQuizController::class, 'result'])->name('quizzes.result');
        });

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
            Route::post('/verify-otp', [ExamSessionController::class, 'verifyOtp'])
                ->middleware('throttle:20,1')
                ->name('verify-otp');
            Route::post('/start', [ExamSessionController::class, 'start'])
                ->middleware('throttle:60,1')
                ->name('start');
            Route::post('/proctoring-capability', [ExamSessionController::class, 'proctoringCapability'])->name('proctoring-capability');
            Route::get('/{examSession}/state', [ExamSessionController::class, 'state'])->name('state');
            Route::post('/{examSession}/answers', [ExamSessionController::class, 'saveAnswer'])->name('answers.save');
            Route::post('/{examSession}/heartbeat', [ExamSessionController::class, 'heartbeat'])->name('heartbeat');
            Route::post('/{examSession}/verification-image', [ExamSessionController::class, 'storeVerificationImage'])
                ->middleware('throttle:30,1')
                ->name('verification-image');
            Route::post('/{examSession}/proctoring-events', [ExamSessionController::class, 'logProctoringEvent'])->name('proctoring-events.store');
            Route::post('/{examSession}/proctoring-events/batch', [ExamSessionController::class, 'logProctoringEventBatch'])->name('proctoring-events.batch');
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

        Route::post('/proctoring/toggle', [ProctoringGovernanceController::class, 'toggle'])->name('proctoring.toggle');
        Route::post('/proctoring/emergency-shutdown', [ProctoringGovernanceController::class, 'emergencyShutdown'])->name('proctoring.emergency-shutdown');
        Route::post('/proctoring/override-config', [ProctoringGovernanceController::class, 'overrideConfig'])->name('proctoring.override-config');

        Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/lock', [AdminSettingsController::class, 'lock'])->name('settings.lock');
        Route::post('/settings/unlock', [AdminSettingsController::class, 'unlock'])->name('settings.unlock');

        Route::get('/academic-reset-snapshots', [AcademicResetSnapshotsController::class, 'index'])->name('academic-reset-snapshots.index');

        Route::get('/academic-years', [AcademicYearController::class, 'index'])->name('academic-years.index');
        Route::get('/academic-years/create', [AcademicYearController::class, 'create'])->name('academic-years.create');
        Route::post('/academic-years', [AcademicYearController::class, 'store'])->name('academic-years.store');
        Route::get('/academic-years/{academic_year}/edit', [AcademicYearController::class, 'edit'])->name('academic-years.edit');
        Route::put('/academic-years/{academic_year}', [AcademicYearController::class, 'update'])->name('academic-years.update');
        Route::post('/academic-years/{academic_year}/terms', [AcademicYearController::class, 'storeTerm'])->name('academic-years.terms.store');
        Route::put('/academic-years/{academic_year}/terms/{term}', [AcademicYearController::class, 'updateTerm'])->name('academic-years.terms.update');
    });

Route::prefix('coordinator')
    ->name('coordinator.')
    ->middleware(['auth', 'verified', 'coordinator'])
    ->group(function () {
        Route::get('/dashboard', [CoordinatorDashboardController::class, 'index'])->name('dashboard');

        Route::get('/academic-reset', [AcademicResetController::class, 'index'])->name('academic-reset.index');
        Route::post('/academic-reset/preview', [AcademicResetController::class, 'preview'])->name('academic-reset.preview');
        Route::get('/academic-reset/{snapshot}/review', [AcademicResetController::class, 'review'])->name('academic-reset.review');
        Route::post('/academic-reset/{snapshot}/apply', [AcademicResetController::class, 'apply'])->name('academic-reset.apply');

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

        Route::get('/grading/pending-essays', [ManualGradingController::class, 'index'])->name('grading.pending');
        Route::get('/grading/answers/{answer}', [ManualGradingController::class, 'show'])->name('grading.show');
        Route::post('/grading/answers/{answer}', [ManualGradingController::class, 'grade'])->name('grading.grade');

        Route::get('/exams/{exam}/sessions', [ExamSessionReviewController::class, 'index'])->name('exams.sessions.index');
        Route::get('/exam-sessions/{examSession}', [ExamSessionReviewController::class, 'show'])->name('exam-sessions.show');
    });

Route::prefix('examiner')
    ->name('examiner.')
    ->middleware(['auth', 'verified', 'coordinator'])
    ->group(function () {
        Route::get('/dashboard', [ExaminerDashboardController::class, 'index'])->name('dashboard');
        Route::get('/exams', [ExamBuilderController::class, 'index'])->name('exams.index');
        Route::get('/exams/create', [ExamBuilderController::class, 'create'])->name('exams.create');
        Route::post('/exams', [ExamBuilderController::class, 'store'])->name('exams.store');
        Route::get('/exams/{exam}/builder', [ExamBuilderController::class, 'builder'])->name('exams.builder');
        Route::post('/exams/{exam}/publish', [ExamBuilderController::class, 'publish'])->name('exams.publish');
        Route::post('/exams/{exam}/unpublish', [ExamBuilderController::class, 'unpublish'])->name('exams.unpublish');
        Route::post('/exams/{exam}/archive', [ExamBuilderController::class, 'archive'])->name('exams.archive');
        Route::post('/exams/{exam}/clone', [ExamBuilderController::class, 'cloneExam'])->name('exams.clone');
        Route::patch('/exams/{exam}/schedule', [ExamBuilderController::class, 'updateSchedule'])->name('exams.schedule.update');
        Route::patch('/exams/{exam}/delivery', [ExamBuilderController::class, 'updateDeliverySettings'])->name('exams.delivery.update');
        Route::patch('/exams/{exam}/questions/{question}/pool-status', [ExamBuilderController::class, 'updateQuestionPoolStatus'])->name('exams.questions.pool-status');
        Route::post('/exams/{exam}/sections', [ExamBuilderController::class, 'storeSection'])->name('exams.sections.store');
        Route::post('/exams/{exam}/sections/{section}/questions', [ExamBuilderController::class, 'storeQuestion'])->name('exams.questions.store');
        Route::post('/exams/{exam}/questions/import/preview', [ExamBuilderController::class, 'previewQuestionImport'])->name('exams.questions.import.preview');
        Route::post('/exams/{exam}/questions/import/cancel', [ExamBuilderController::class, 'cancelQuestionImport'])->name('exams.questions.import.cancel');
        Route::post('/exams/{exam}/questions/import/commit', [ExamBuilderController::class, 'commitQuestionImport'])->name('exams.questions.import.commit');
        Route::post('/exams/{exam}/questions/ai/prompt', [ExamBuilderController::class, 'buildAiPrompt'])->name('exams.questions.ai.prompt');
        Route::post('/exams/{exam}/questions/ai/generate', [ExamBuilderController::class, 'generateWithAi'])->name('exams.questions.ai.generate');

        Route::get('/courses/{course}/materials', [ExaminerCourseMaterialController::class, 'index'])->name('courses.materials.index');
        Route::post('/courses/{course}/materials', [ExaminerCourseMaterialController::class, 'store'])->name('courses.materials.store');
        Route::delete('/courses/{course}/materials/{material}', [ExaminerCourseMaterialController::class, 'destroy'])->name('courses.materials.destroy');
        Route::get('/practice-overview', [PracticeOverviewController::class, 'index'])->name('practice-overview.index');
    });

require __DIR__.'/auth.php';
