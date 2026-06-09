<?php

namespace Tests\Unit;

use App\Services\OutlineTopicSuggester;
use Tests\TestCase;

class OutlineTopicSuggesterTest extends TestCase
{
    public function test_extracts_line_based_topics(): void
    {
        $text = "Introduction to calculus\nDerivatives in practice\n\nShort\n".str_repeat('x', 300);

        $topics = app(OutlineTopicSuggester::class)->suggestFromPlainText($text, max: 10);

        $this->assertContains('Introduction to calculus', $topics);
        $this->assertContains('Derivatives in practice', $topics);
        $this->assertNotContains('Short', $topics);
    }

    public function test_drops_course_outline_boilerplate(): void
    {
        $text = <<<'TXT'
        COURSE OUTLINE
        DEPARTMENT OF COMPUTER SCIENCE
        FACULTY OF APPLIED SCIENCES - TAKORADI TECHNICAL UNIVERSITY
        SECOND SEMESTER - 2025/2026 ACADEMIC YEAR

        Course Description
        Course Code and Title: ICT 226 - PHP Programming
        Class: HND-ICT I
        Course Weight: 3 Credit Hours
        Teaching Approach: Lecture and Practical Exercises

        Course Rationale
        The course is designed to provide a thorough introduction to server-side web scripting using PHP.
        Students will be introduced to the principles of dynamic web page generation, form handling,
        session management, cookie management, file handling, and database integration using MySQL.

        Instructor Information
        Lecturer: Augustine D. Yeboah
        Status: Full Time
        Office: E-Skills for Girls Lab
        Office Hours: 08:00 am to 05:00 pm
        Phone: 0552477942
        Email: augustine.danquahyeboah@ttu.edu.gh

        Course Content
        1. Set up a PHP development environment (XAMPP/WAMP/LAMP)
        2. PHP syntax, variables, and operators
        3. Control structures and loops
        4. Functions and arrays
        5. Form handling and validation
        6. Session and cookie management
        7. File handling in PHP
        8. Database integration with MySQL
        9. Building a simple CRUD application

        References
        Welling, L. and Thomson, L. PHP and MySQL Web Development.
        TXT;

        $topics = app(OutlineTopicSuggester::class)->suggestFromPlainText($text, max: 25);

        // Real topics survive.
        $this->assertContains('Set up a PHP development environment (XAMPP/WAMP/LAMP)', $topics);
        $this->assertContains('PHP syntax, variables, and operators', $topics);
        $this->assertContains('Functions and arrays', $topics);
        $this->assertContains('Form handling and validation', $topics);
        $this->assertContains('Database integration with MySQL', $topics);

        // Boilerplate / metadata never makes it through.
        $this->assertNotContains('COURSE OUTLINE', $topics);
        $this->assertNotContains('DEPARTMENT OF COMPUTER SCIENCE', $topics);
        $this->assertNotContains('FACULTY OF APPLIED SCIENCES - TAKORADI TECHNICAL UNIVERSITY', $topics);
        $this->assertNotContains('SECOND SEMESTER - 2025/2026 ACADEMIC YEAR', $topics);
        $this->assertNotContains('Course Description', $topics);
        $this->assertNotContains('Course Code and Title: ICT 226 - PHP Programming', $topics);
        $this->assertNotContains('Class: HND-ICT I', $topics);
        $this->assertNotContains('Course Weight: 3 Credit Hours', $topics);
        $this->assertNotContains('Teaching Approach: Lecture and Practical Exercises', $topics);
        $this->assertNotContains('Course Rationale', $topics);
        $this->assertNotContains('Instructor Information', $topics);
        $this->assertNotContains('Lecturer: Augustine D. Yeboah', $topics);
        $this->assertNotContains('Status: Full Time', $topics);
        $this->assertNotContains('Office: E-Skills for Girls Lab', $topics);
        $this->assertNotContains('Office Hours: 08:00 am to 05:00 pm', $topics);
        $this->assertNotContains('Phone: 0552477942', $topics);
        $this->assertNotContains('Email: augustine.danquahyeboah@ttu.edu.gh', $topics);

        // Prose sentences are never useful as chips.
        foreach ($topics as $topic) {
            $this->assertStringNotContainsString('The course is designed', $topic);
            $this->assertStringNotContainsString('Students will be introduced', $topic);
        }
    }

    public function test_falls_back_when_no_explicit_pivot_section(): void
    {
        $text = <<<'TXT'
        Linear equations
        Quadratic equations
        Polynomial expansions
        Email: helpdesk@school.edu
        Office: Room 12
        TXT;

        $topics = app(OutlineTopicSuggester::class)->suggestFromPlainText($text, max: 10);

        $this->assertContains('Linear equations', $topics);
        $this->assertContains('Quadratic equations', $topics);
        $this->assertContains('Polynomial expansions', $topics);
        $this->assertNotContains('Email: helpdesk@school.edu', $topics);
        $this->assertNotContains('Office: Room 12', $topics);
    }
}
