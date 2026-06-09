<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Support\CodingCourseHeuristics;
use Tests\TestCase;

class CodingCourseHeuristicsTest extends TestCase
{
    public function test_detects_cs_course_code(): void
    {
        $course = new Course(['code' => 'CS101', 'title' => 'Intro']);

        $this->assertTrue(CodingCourseHeuristics::likelyForCourse($course));
    }

    public function test_detects_programming_in_title(): void
    {
        $course = new Course(['code' => 'MATH200', 'title' => 'Introduction to Programming']);

        $this->assertTrue(CodingCourseHeuristics::likelyForCourse($course));
    }

    public function test_ignores_unrelated_course(): void
    {
        $course = new Course(['code' => 'ENG101', 'title' => 'English Composition']);

        $this->assertFalse(CodingCourseHeuristics::likelyForCourse($course));
    }
}
