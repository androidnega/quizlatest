<?php

namespace App\Services;

/**
 * Picks candidate topic lines from plain outline text (PDF/TXT/DOCX/CSV
 * extraction) without calling an LLM. The picker is intentionally
 * conservative: it strips boilerplate (department/faculty/lecturer rows,
 * "Course Description", "By the end of this course…", etc.) and prefers
 * lines inside an explicit "Course Content / Topics / Syllabus" window
 * when the document has one.
 */
final class OutlineTopicSuggester
{
    /** @var list<string> Metadata-label prefixes that mark a row as boilerplate, not a topic. */
    private const JUNK_LABEL_PREFIXES = [
        'course code', 'course title', 'course name', 'course weight',
        'credit hours', 'credit hour', 'class', 'classes',
        'teaching approach', 'teaching method', 'mode of delivery',
        'lecturer', 'instructor', 'tutor', 'facilitator',
        'status', 'office hours', 'office', 'phone', 'tel', 'mobile', 'fax',
        'email', 'website',
        'semester', 'academic year', 'session', 'year of study',
        'department', 'faculty', 'university', 'school of', 'college of',
        'institute of',
        'pre-requisite', 'prerequisite', 'co-requisite', 'corequisite',
        'course rationale', 'course description', 'course summary',
        'course information', 'instructor information',
        'general information', 'course objectives', 'learning outcomes',
        'learning objectives',
    ];

    /** @var list<string> Single-token headings we drop outright when they sit on their own line. */
    private const JUNK_STANDALONE_HEADINGS = [
        'content', 'contents', 'overview', 'introduction', 'objectives',
        'outline', 'syllabus', 'topics', 'modules', 'units', 'chapters',
    ];

    /** @var list<string> Sentence starters that almost always mark prose, not a topic. */
    private const SENTENCE_STARTERS = [
        'the ', 'this ', 'these ', 'those ', 'students ', 'student ',
        'we ', 'our ', 'you ', 'your ', 'it is ', 'there ', 'in this ',
        'by the end ', 'upon completion ', 'at the end ',
        'lecturer ', 'instructor ',
    ];

    /**
     * @return list<string>
     */
    public function suggestFromPlainText(string $text, int $max = 25): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $text = mb_substr($text, 0, 120_000);
        $lines = preg_split('/\R+/u', $text) ?: [];

        // If the document has an explicit "Course Content / Topics / Syllabus"
        // pivot, restrict candidate gathering to that window. Otherwise scan
        // the whole document and rely on per-line junk filtering.
        $startIdx = $this->detectTopicSectionStart($lines);
        if ($startIdx !== null) {
            $endIdx = $this->detectTopicSectionEnd($lines, $startIdx) ?? count($lines);
            $lines = array_slice($lines, $startIdx + 1, $endIdx - $startIdx - 1);
        }

        $candidates = [];
        foreach ($lines as $rawLine) {
            $clean = $this->cleanCandidate((string) $rawLine);
            if ($clean !== null) {
                $candidates[] = $clean;
            }
        }

        $candidates = array_values(array_unique($candidates));

        return array_slice($candidates, 0, max(1, min(50, $max)));
    }

    /**
     * @param  list<string>  $lines
     */
    private function detectTopicSectionStart(array $lines): ?int
    {
        $pivots = [
            '/^\s*(course\s+content|course\s+outline|course\s+topics|topics?\s+covered|topics?|syllabus|modules?|units?|chapters?|content|contents)\s*[:.]?\s*$/iu',
            '/^\s*(week|unit|module|chapter|lecture|lesson|topic)\s+\d+\b/iu',
        ];

        foreach ($lines as $i => $line) {
            $trimmed = trim((string) $line);
            foreach ($pivots as $pat) {
                if (preg_match($pat, $trimmed)) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $lines
     */
    private function detectTopicSectionEnd(array $lines, int $startIdx): ?int
    {
        $endPivots = [
            '/^\s*(references?|bibliography|grading|grading\s+(scheme|policy)|assessment\s+(criteria|methods?|policy)|attendance|policies|policy|appendix|recommended\s+reading|further\s+reading|reading\s+list|required\s+texts?|core\s+texts?)\s*[:.]?\s*$/iu',
        ];

        $count = count($lines);
        for ($i = $startIdx + 1; $i < $count; $i++) {
            $trimmed = trim((string) $lines[$i]);
            foreach ($endPivots as $pat) {
                if (preg_match($pat, $trimmed)) {
                    return $i;
                }
            }
        }

        return null;
    }

    private function cleanCandidate(string $line): ?string
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        // Strip leading bullet characters.
        $line = preg_replace('/^[\s\-*•·◦▪►▶→\t]+/u', '', $line) ?? $line;

        // Strip leading numbering — supports "1.", "1)", "1.1", "1.1.2",
        // "(a)", "a)", "a.", roman numerals "i.", "ii.", "(iv)" etc.
        $line = preg_replace('/^\(?([ivxlcdm]{1,6})\)?[\.\)]\s*/iu', '', $line) ?? $line;
        $line = preg_replace('/^\(?([a-z])\)?[\.\)]\s*/iu', '', $line) ?? $line;
        $line = preg_replace('/^\d+(\.\d+)*[\.\)]\s*/u', '', $line) ?? $line;
        $line = trim($line);

        if ($line === '') {
            return null;
        }

        $len = mb_strlen($line);
        if ($len < 6 || $len > 140) {
            return null;
        }

        // URLs / emails / phone-only lines.
        if (preg_match('#https?://#iu', $line)) {
            return null;
        }
        if (preg_match('/[\w.+\-]+@[\w-]+\.[\w.\-]+/u', $line)) {
            return null;
        }
        if (preg_match('/^\+?\d[\d\s\-().]{6,}$/u', $line)) {
            return null;
        }

        // Pagination / "Page N", "p. N".
        if (preg_match('/^(page|p\.)\s*\d+/iu', $line)) {
            return null;
        }

        $lower = mb_strtolower($line);

        // Standalone section headings ("Content", "Outline" by themselves).
        if (in_array($lower, self::JUNK_STANDALONE_HEADINGS, true)) {
            return null;
        }

        // Drop a line that is exactly a junk label (e.g. "Course Description").
        foreach (self::JUNK_LABEL_PREFIXES as $label) {
            if ($lower === $label) {
                return null;
            }
        }

        // "<Label>: <Value>" rows are almost always metadata. If anything before
        // the first colon contains a known junk keyword, drop the whole line.
        // Examples we want to catch: "Course Code and Title: ICT 226 - PHP",
        // "Office Hours: 08:00 am to 05:00 pm", "Phone: 0552477942".
        $colonPos = mb_strpos($line, ':');
        if ($colonPos !== false && $colonPos > 0) {
            $lhs = mb_strtolower(mb_substr($line, 0, $colonPos));
            if (mb_strlen($lhs) <= 60) {
                foreach (self::JUNK_LABEL_PREFIXES as $label) {
                    if (str_contains($lhs, $label)) {
                        return null;
                    }
                }
            }
        }

        // Section headers usually end with ":" — never useful as a topic chip.
        if (str_ends_with($line, ':')) {
            return null;
        }

        // ALL-CAPS institutional headers ("DEPARTMENT OF X", "FACULTY OF Y",
        // "SECOND SEMESTER - 2025/2026 ACADEMIC YEAR"). Two or more capitalized
        // word tokens with no lowercase letters anywhere → drop.
        if ($this->isAllCapsHeader($line)) {
            return null;
        }

        // Prose detection — long lines or lines starting with classic prose
        // connectors are almost never useful as a chip.
        $wordCount = count(preg_split('/\s+/u', $line) ?: []);
        if ($wordCount > 18) {
            return null;
        }
        foreach (self::SENTENCE_STARTERS as $starter) {
            if (str_starts_with($lower, $starter)) {
                return null;
            }
        }

        // Mostly numbers / punctuation.
        $alpha = (int) preg_match_all('/\p{L}/u', $line);
        if ($alpha < 4) {
            return null;
        }

        // Trim a trailing period — chip labels read better without it.
        $line = rtrim($line, '. ');

        // Re-check length after trimming.
        if (mb_strlen($line) < 6) {
            return null;
        }

        return $line;
    }

    private function isAllCapsHeader(string $line): bool
    {
        if (! preg_match('/\p{L}/u', $line)) {
            return false;
        }

        $words = preg_split('/\s+/u', $line) ?: [];
        $capitalWords = 0;
        $hasLowercaseLetter = false;

        foreach ($words as $word) {
            // Strip punctuation around the word.
            $clean = preg_replace('/^[\p{P}\p{S}]+|[\p{P}\p{S}]+$/u', '', $word) ?? $word;
            if ($clean === '' || mb_strlen($clean) < 2) {
                continue;
            }
            if (preg_match('/\p{Ll}/u', $clean)) {
                $hasLowercaseLetter = true;
            }
            if (mb_strtoupper($clean) === $clean) {
                $capitalWords++;
            }
        }

        return ! $hasLowercaseLetter && $capitalWords >= 2;
    }
}
