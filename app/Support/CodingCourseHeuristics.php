<?php

namespace App\Support;

use App\Models\Course;

class CodingCourseHeuristics
{
    /**
     * Whether an assignment for this course should open in a code-editor style by default.
     */
    public static function likelyForCourse(?Course $course): bool
    {
        if ($course === null) {
            return false;
        }

        $code = strtoupper(trim((string) ($course->code ?? '')));
        $title = strtolower(trim((string) ($course->title ?? '')));

        if ($code !== '' && preg_match('/^(CS|CSC|COMP|IT|ICT|CPE|CE|SE|INFT|DATA|BINF|SWE|DEV|PROG|PY|JAVA|JS|WEB)[\s\-_]/i', $code)) {
            return true;
        }

        if ($code !== '' && preg_match('/^(CS|CSC|COMP|IT|ICT|CPE|CE|SE|INFT|DATA|BINF|SWE|DEV|PROG)/i', $code)) {
            return true;
        }

        $titleNeedles = [
            'programming',
            'computer science',
            'software',
            'coding',
            'javascript',
            'python',
            'java ',
            'web development',
            'data structures',
            'algorithms',
            'full stack',
            'backend',
            'frontend',
        ];

        foreach ($titleNeedles as $needle) {
            if (str_contains($title, $needle)) {
                return true;
            }
        }

        if ($course->relationLoaded('department') && $course->department !== null) {
            $deptCode = strtoupper(trim((string) ($course->department->code ?? '')));
            if (in_array($deptCode, ['CS', 'CSC', 'COMP', 'IT', 'ICT'], true)) {
                return true;
            }
        }

        return false;
    }
}
