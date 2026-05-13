<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\PracticeModuleSettings;
use Illuminate\Http\RedirectResponse;

class StudentPracticeHubController extends Controller
{
    public function index(PracticeModuleSettings $practice): RedirectResponse
    {
        $practice->assertStudentPracticeOrAbort();

        return redirect()->route('student.practice.revision');
    }
}
