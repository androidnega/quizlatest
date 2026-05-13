<?php

use App\Http\Controllers\Admin\AcademicResetSnapshotsController;
use App\Http\Controllers\Admin\AcademicYearController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\CoordinatorController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ProctoringGovernanceController;
use App\Http\Controllers\Admin\UniversityController;
use App\Http\Controllers\Admin\UserAccountController;
use App\Http\Controllers\Coordinator\AcademicResetController;
use App\Http\Controllers\Coordinator\ClassCourseAssignmentController;
use App\Http\Controllers\Coordinator\ClassroomController;
use App\Http\Controllers\Coordinator\CourseController;
use App\Http\Controllers\Coordinator\LevelController;
use App\Http\Controllers\Coordinator\ProgramController;
use App\Http\Controllers\Coordinator\StudentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Examiner\ClassExplorerController;
use App\Http\Controllers\Examiner\CourseMaterialController as ExaminerCourseMaterialController;
use App\Http\Controllers\Examiner\CoursesController as ExaminerCoursesController;
use App\Http\Controllers\Examiner\DashboardController as ExaminerDashboardController;
use App\Http\Controllers\Examiner\ExamBuilderController;
use App\Http\Controllers\Examiner\ExamSessionReviewController as ExaminerExamSessionReviewController;
use App\Http\Controllers\Examiner\ManualGradingController as ExaminerManualGradingController;
use App\Http\Controllers\Examiner\PracticeOverviewController;
use App\Http\Controllers\ExamSessionController;
use App\Http\Controllers\Marketing\AboutController;
use App\Http\Controllers\ProctoringUploadController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileFaceImageController;
use App\Http\Controllers\SecureExamEvidenceController;
use App\Http\Controllers\Student\StudentAssignmentsController;
use App\Http\Controllers\Student\StudentCourseMaterialController;
use App\Http\Controllers\Student\StudentExamController;
use App\Http\Controllers\Student\StudentExamEntryController;
use App\Http\Controllers\Student\StudentPracticeHubController;
use App\Http\Controllers\Student\StudentPracticeQuizController;
use App\Http\Controllers\Student\StudentPracticeSummaryController;
use App\Http\Controllers\Student\StudentResultController;
use App\Http\Controllers\Student\StudentRevisionSelfCheckController;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::get('/about', AboutController::class)->name('about');

Route::get('/quiz-hero-demo', function () {
    return view('quiz-hero-demo');
})->name('quiz-hero-demo');

Route::any('/staff/login', fn () => redirect('/admin_login', 301));

Route::redirect('/profile', '/dashboard/profile', 301);
Route::redirect('/profile/face-image', '/dashboard/profile/face-image', 301);

Route::any('/admin/{path?}', function (?string $path = null) {
    $path = $path === null ? '' : ltrim($path, '/');
    if ($path === '' || $path === 'dashboard') {
        return redirect('/dashboard', 301);
    }

    return redirect('/dashboard/'.$path, 301);
})->where('path', '.*');

Route::any('/coordinator/{path?}', function (?string $path = null) {
    $path = $path === null ? '' : ltrim($path, '/');
    if ($path === '' || $path === 'dashboard') {
        return redirect('/dashboard', 301);
    }

    return redirect('/dashboard/'.$path, 301);
})->where('path', '.*');

Route::any('/examiner/{path?}', function (?string $path = null) {
    $path = $path === null ? '' : ltrim($path, '/');

    return redirect($path === '' ? '/dashboard/workspace' : '/dashboard/'.$path, 301);
})->where('path', '.*');

Route::any('/student/results/{path?}', function (?string $path = null) {
    $path = $path === null ? '' : ltrim($path, '/');
    $base = '/dashboard/results';

    return redirect($path === '' ? $base : $base.'/'.$path, 301);
})->where('path', '.*');

Route::any('/student/practice/{path?}', function (?string $path = null) {
    $path = $path === null ? '' : ltrim($path, '/');
    $base = '/dashboard/practice';

    return redirect($path === '' ? $base : $base.'/'.$path, 301);
})->where('path', '.*');

Route::middleware('auth')->group(function () {
    Route::get('/student/exam/{examSession}', [StudentExamController::class, 'take'])
        ->name('student.exam.take');

    Route::middleware(['verified', 'student'])->group(function () {
        Route::get('/student/exams/{quiz}/prepare', [StudentExamEntryController::class, 'prepare'])
            ->name('student.exam.prepare');
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
            Route::post('/{examSession}/resume', [ExamSessionController::class, 'resume'])->name('resume');
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

    Route::prefix('proctoring/uploads')
        ->name('proctoring.uploads.')
        ->group(function () {
            Route::post('/path', [ProctoringUploadController::class, 'createUploadPath'])->name('path');
            Route::post('/file', [ProctoringUploadController::class, 'uploadFile'])->name('file');
            Route::post('/metadata', [ProctoringUploadController::class, 'storeMetadata'])->name('metadata');
        });
});

Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::any('student/{path?}', function (?string $path = null) {
        $suffix = ($path === null || $path === '') ? '' : '/'.ltrim($path, '/');

        return redirect('/dashboard'.$suffix, 301);
    })->where('path', '.*');

    Route::redirect('admin', '/dashboard', 301);
    Route::redirect('admin/{path}', '/dashboard/{path}', 301)->where('path', '.+');

    Route::any('coordinator/{path?}', function (?string $path = null) {
        $path = $path === null || $path === '' ? '' : '/'.ltrim($path, '/');

        return redirect('/dashboard'.$path, 301);
    })->where('path', '.*');

    Route::any('examiner/{path?}', function (?string $path = null) {
        $path = $path === null ? '' : ltrim($path, '/');
        if ($path === '') {
            return redirect('/dashboard/workspace', 301);
        }

        return redirect('/dashboard/'.$path, 301);
    })->where('path', '.*');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/profile/face-image', [ProfileFaceImageController::class, 'show'])->name('profile.face-image');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware('student')->group(function () {
        Route::post('/policy-notice/dismiss', [DashboardController::class, 'dismissStudentPolicyNotice'])
            ->name('student.dashboard.policy-notice.dismiss');

        Route::get('/assignments', [StudentAssignmentsController::class, 'index'])->name('student.assignments.index');

        Route::prefix('results')
            ->name('student.results.')
            ->group(function () {
                Route::get('/', [StudentResultController::class, 'index'])->name('index');
                Route::get('/{examSession}/pdf', [StudentResultController::class, 'pdf'])->name('pdf');
                Route::get('/{examSession}', [StudentResultController::class, 'show'])->name('show');
            });

        Route::prefix('practice')
            ->name('student.practice.')
            ->group(function () {
                Route::get('/', [StudentPracticeHubController::class, 'index'])->name('index');
                Route::get('/revision', [StudentRevisionSelfCheckController::class, 'show'])->name('revision');
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
    });

    Route::middleware('admin')
        ->name('admin.')
        ->group(function () {
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

            Route::get('/users', [UserAccountController::class, 'index'])->name('users.index');
            Route::get('/users/create', [UserAccountController::class, 'create'])->name('users.create');
            Route::post('/users', [UserAccountController::class, 'store'])->name('users.store');
            Route::get('/users/{user}/edit', [UserAccountController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [UserAccountController::class, 'update'])->name('users.update');
            Route::get('/health-snapshot', [AdminDashboardController::class, 'healthSnapshot'])->name('health-snapshot');

            Route::post('/proctoring/toggle', [ProctoringGovernanceController::class, 'toggle'])->name('proctoring.toggle');
            Route::post('/proctoring/emergency-shutdown', [ProctoringGovernanceController::class, 'emergencyShutdown'])->name('proctoring.emergency-shutdown');
            Route::post('/proctoring/override-config', [ProctoringGovernanceController::class, 'overrideConfig'])->name('proctoring.override-config');

            Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings.index');
            Route::put('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
            Route::post('/settings/lock', [AdminSettingsController::class, 'lock'])->name('settings.lock');
            Route::post('/settings/unlock', [AdminSettingsController::class, 'unlock'])->name('settings.unlock');

            Route::get('/academic-reset-snapshots', [AcademicResetSnapshotsController::class, 'index'])->name('academic-reset-snapshots.index');

            Route::get('/exam-sessions/{examSession}/evidence/verification', [SecureExamEvidenceController::class, 'verification'])
                ->name('exam-sessions.evidence.verification');
            Route::get('/exam-sessions/{examSession}/evidence/events/{proctoringEvent}', [SecureExamEvidenceController::class, 'eventSnapshot'])
                ->name('exam-sessions.evidence.event');

            Route::get('/academic-years', [AcademicYearController::class, 'index'])->name('academic-years.index');
            Route::get('/academic-years/create', [AcademicYearController::class, 'create'])->name('academic-years.create');
            Route::post('/academic-years', [AcademicYearController::class, 'store'])->name('academic-years.store');
            Route::get('/academic-years/{academic_year}/edit', [AcademicYearController::class, 'edit'])->name('academic-years.edit');
            Route::put('/academic-years/{academic_year}', [AcademicYearController::class, 'update'])->name('academic-years.update');
            Route::post('/academic-years/{academic_year}/terms', [AcademicYearController::class, 'storeTerm'])->name('academic-years.terms.store');
            Route::put('/academic-years/{academic_year}/terms/{term}', [AcademicYearController::class, 'updateTerm'])->name('academic-years.terms.update');
        });

    Route::middleware('coordinator')
        ->name('coordinator.')
        ->group(function () {
            Route::get('/academic-reset', [AcademicResetController::class, 'index'])->name('academic-reset.index');
            Route::post('/academic-reset/preview', [AcademicResetController::class, 'preview'])->name('academic-reset.preview');
            Route::get('/academic-reset/{snapshot}/review', [AcademicResetController::class, 'review'])->name('academic-reset.review');
            Route::post('/academic-reset/{snapshot}/apply', [AcademicResetController::class, 'apply'])->name('academic-reset.apply');

            Route::get('/students', [StudentController::class, 'index'])->name('students.index');
            Route::get('/students/export-json', [StudentController::class, 'exportJson'])->name('students.export-json');
            Route::get('/students/import-json', [StudentController::class, 'importJsonForm'])->name('students.import-json.form');
            Route::post('/students/import-json', [StudentController::class, 'importJson'])->name('students.import-json');
            Route::get('/students/upload', [StudentController::class, 'uploadForm'])->name('students.upload');
            Route::post('/students/upload/preview', [StudentController::class, 'previewImport'])->name('students.preview');
            Route::post('/students/upload/import', [StudentController::class, 'import'])->name('students.import');
            Route::post('/students/bulk-status', [StudentController::class, 'bulkStatus'])->name('students.bulk-status');
            Route::get('/students/template', [StudentController::class, 'template'])->name('students.template');
            Route::get('/students/{student}/assign-class', [StudentController::class, 'legacyAssignClassRedirect'])->name('students.assign-class.edit');
            Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
            Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
            Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
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
            Route::get('/classes/{classroom}', [ClassroomController::class, 'show'])->name('classes.show');
            Route::get('/classes/{classroom}/students/upload', [StudentController::class, 'classScopedUploadForm'])->name('classes.students.upload');
            Route::post('/classes/{classroom}/students/preview', [StudentController::class, 'previewImportForClassroom'])->name('classes.students.preview');
            Route::get('/classes/{classroom}/students/template', [StudentController::class, 'classScopedTemplate'])->name('classes.students.template');
            Route::get('/classes/{classroom}/edit', [ClassroomController::class, 'edit'])->name('classes.edit');
            Route::put('/classes/{classroom}', [ClassroomController::class, 'update'])->name('classes.update');
            Route::patch('/classes/{classroom}/toggle-status', [ClassroomController::class, 'toggleStatus'])->name('classes.toggle-status');
            Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
            Route::get('/courses/create', [CourseController::class, 'create'])->name('courses.create');
            Route::post('/courses', [CourseController::class, 'store'])->name('courses.store');
            Route::get('/courses/assign/examiners', [CourseController::class, 'editExaminerAssignments'])->name('courses.examiners.edit');
            Route::post('/courses/assign/examiners', [CourseController::class, 'updateExaminerAssignments'])->name('courses.examiners.update');
            Route::get('/courses/{course}/edit', [CourseController::class, 'edit'])->name('courses.edit');
            Route::put('/courses/{course}', [CourseController::class, 'update'])->name('courses.update');
            Route::patch('/courses/{course}/toggle-status', [CourseController::class, 'toggleStatus'])->name('courses.toggle-status');
            Route::get('/courses/assign/classes', [ClassCourseAssignmentController::class, 'edit'])->name('courses.assign.edit');
            Route::post('/courses/assign/classes', [ClassCourseAssignmentController::class, 'update'])->name('courses.assign.update');

        });

    Route::name('examiner.')
        ->middleware('examiner')
        ->group(function () {
            Route::get('/teaching-classes', [ClassExplorerController::class, 'index'])->name('teaching-classes.index');
            Route::get('/teaching-classes/{classroom}', [ClassExplorerController::class, 'show'])->name('teaching-classes.show');
            Route::get('/teaching-classes/{classroom}/students/roster.csv', [ClassExplorerController::class, 'downloadClassRoster'])->name('teaching-classes.students.roster');
            Route::get('/teaching-classes/{classroom}/students/template', [ClassExplorerController::class, 'downloadStudentTemplate'])->name('teaching-classes.students.template');
            Route::get('/teaching-classes/{classroom}/students', [ClassExplorerController::class, 'studentsIndex'])->name('teaching-classes.students.index');
            Route::post('/teaching-classes/{classroom}/students', [ClassExplorerController::class, 'storeStudent'])->name('teaching-classes.students.store');
            Route::get('/teaching-classes/{classroom}/students/{student}', [ClassExplorerController::class, 'showStudent'])->name('teaching-classes.students.show');
            Route::post('/exams/sessions/{examSession}/invalidate-for-retake', [ExamSessionController::class, 'invalidateForRetake'])
                ->name('exam-sessions.invalidate-for-retake');

            Route::get('/workspace', [ExaminerDashboardController::class, 'index'])->name('dashboard');
            Route::get('/quizzes/{exam}', [ExamBuilderController::class, 'builder'])->name('quizzes.workspace');
            Route::prefix('/exams')->group(function () {
                Route::get('/', [ExamBuilderController::class, 'index'])->name('exams.index');
                Route::get('/create', [ExamBuilderController::class, 'create'])->name('exams.create');
                Route::post('/create/validate-import-json', [ExamBuilderController::class, 'validateCreateImportJson'])->name('exams.create.validate-import-json');
                Route::post('/create/outline-suggest-topics', [ExamBuilderController::class, 'suggestCreateOutlineTopics'])->name('exams.create.outline-suggest-topics');
                Route::post('/', [ExamBuilderController::class, 'store'])->name('exams.store');
                Route::get('/{exam}/builder', function (Request $request, Quiz $exam) {
                    $to = route('examiner.quizzes.workspace', ['exam' => $exam]);
                    if ($request->getQueryString()) {
                        $to .= '?'.$request->getQueryString();
                    }

                    return redirect($to, 301);
                });
                Route::post('/{exam}/publish', [ExamBuilderController::class, 'publish'])->name('exams.publish');
                Route::post('/{exam}/unpublish', [ExamBuilderController::class, 'unpublish'])->name('exams.unpublish');
                Route::post('/{exam}/archive', [ExamBuilderController::class, 'archive'])->name('exams.archive');
                Route::post('/{exam}/clone', [ExamBuilderController::class, 'cloneExam'])->name('exams.clone');
                Route::post('/{exam}/release-assignment-grades', [ExamBuilderController::class, 'releaseAssignmentGrades'])->name('exams.release-assignment-grades');
                Route::patch('/{exam}/schedule', [ExamBuilderController::class, 'updateSchedule'])->name('exams.schedule.update');
                Route::patch('/{exam}/delivery', [ExamBuilderController::class, 'updateDeliverySettings'])->name('exams.delivery.update');
                Route::patch('/{exam}/proctoring-options', [ExamBuilderController::class, 'updateProctoringExaminerChoices'])->name('exams.proctoring-options.update');
                Route::patch('/{exam}/question-types', [ExamBuilderController::class, 'updateSelectedQuestionTypes'])->name('exams.question-types.update');
                Route::patch('/{exam}/questions/{question}/pool-status', [ExamBuilderController::class, 'updateQuestionPoolStatus'])->name('exams.questions.pool-status');
                Route::patch('/{exam}/questions/pool-status/bulk', [ExamBuilderController::class, 'bulkUpdateQuestionPoolStatus'])->name('exams.questions.pool-status.bulk');
                Route::post('/{exam}/sections', [ExamBuilderController::class, 'storeSection'])->name('exams.sections.store');
                Route::post('/{exam}/sections/{section}/questions', [ExamBuilderController::class, 'storeQuestion'])->name('exams.questions.store');
                Route::post('/{exam}/questions/import/preview', [ExamBuilderController::class, 'previewQuestionImport'])->name('exams.questions.import.preview');
                Route::post('/{exam}/questions/import/cancel', [ExamBuilderController::class, 'cancelQuestionImport'])->name('exams.questions.import.cancel');
                Route::post('/{exam}/questions/import/commit', [ExamBuilderController::class, 'commitQuestionImport'])->name('exams.questions.import.commit');
                Route::post('/{exam}/questions/ai/prompt', [ExamBuilderController::class, 'buildAiPrompt'])->name('exams.questions.ai.prompt');
                Route::post('/{exam}/questions/ai/generate', [ExamBuilderController::class, 'generateWithAi'])->name('exams.questions.ai.generate');
                Route::get('/{exam}/sessions', [ExaminerExamSessionReviewController::class, 'index'])->name('exams.sessions.index');
                Route::get('/{exam}/sessions/export.csv', [ExaminerExamSessionReviewController::class, 'exportCsv'])->name('exams.sessions.export-csv');
                Route::post('/{exam}/sessions/invalidate-range', [ExaminerExamSessionReviewController::class, 'invalidateSessionsInRange'])->name('exams.sessions.invalidate-range');
                Route::get('/{exam}/classes', [ExaminerExamSessionReviewController::class, 'classSummary'])->name('exams.classes.summary');
                Route::get('/{exam}/classes/{classroom}/results', [ExaminerExamSessionReviewController::class, 'classResults'])->name('exams.classes.results');
            });

            Route::prefix('/examiner/exams')->group(function () {
                Route::get('/', fn () => redirect()->route('examiner.exams.index', [], 301));
                Route::get('/create', fn () => redirect()->route('examiner.exams.create', [], 301));
                Route::post('/', fn () => redirect()->route('examiner.exams.create', [], 301));
                Route::get('/{exam}/builder', function (Request $request, Quiz $exam) {
                    $to = route('examiner.quizzes.workspace', ['exam' => $exam]);
                    if ($request->getQueryString()) {
                        $to .= '?'.$request->getQueryString();
                    }

                    return redirect($to, 301);
                });
            });

            // Distinct from coordinator `dashboard/courses` (coordinator.courses.index).
            Route::get('/assigned-courses', [ExaminerCoursesController::class, 'index'])->name('courses.index');
            Route::get('/courses/{course}/outline', [ExaminerCourseMaterialController::class, 'outline'])->name('courses.outline');
            Route::get('/courses/{course}/materials', [ExaminerCourseMaterialController::class, 'index'])->name('courses.materials.index');
            Route::get('/courses/{course}', [ExaminerExamSessionReviewController::class, 'courseOverview'])->name('courses.show');
            Route::get('/courses/{course}/classes/{classroom}', [ExaminerExamSessionReviewController::class, 'courseClassOverview'])->name('courses.classes.show');
            Route::post('/courses/{course}/materials', [ExaminerCourseMaterialController::class, 'store'])->name('courses.materials.store');
            Route::get('/courses/{course}/materials/{material}/download', [ExaminerCourseMaterialController::class, 'download'])->name('courses.materials.download');
            Route::delete('/courses/{course}/materials/{material}', [ExaminerCourseMaterialController::class, 'destroy'])->name('courses.materials.destroy');
            Route::get('/practice-overview', [PracticeOverviewController::class, 'index'])->name('practice-overview.index');

            Route::get('/grading/pending-essays', [ExaminerManualGradingController::class, 'index'])->name('grading.pending');
            Route::get('/grading/answers/{answer}', [ExaminerManualGradingController::class, 'show'])->name('grading.show');
            Route::post('/grading/answers/{answer}', [ExaminerManualGradingController::class, 'grade'])->name('grading.grade');

            Route::get('/exams/sessions/{examSession}/evidence/verification', [SecureExamEvidenceController::class, 'verification'])
                ->name('exam-sessions.evidence.verification');
            Route::get('/exams/sessions/{examSession}/evidence/events/{proctoringEvent}', [SecureExamEvidenceController::class, 'eventSnapshot'])
                ->name('exam-sessions.evidence.event');
            Route::get('/exams/sessions/{examSession}', [ExaminerExamSessionReviewController::class, 'show'])->name('exam-sessions.show');
        });
});

require __DIR__.'/auth.php';
