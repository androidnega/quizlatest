<?php

namespace Tests\Unit;

use App\Support\StudentGreeting;
use PHPUnit\Framework\TestCase;

/**
 * Pure-function test for the rotating dashboard greeting. We don't hit the
 * DB here — the helper takes either a User or an int user-id, so passing
 * ints lets us pin behaviour without a Laravel app boot.
 */
class StudentGreetingTest extends TestCase
{
    public function test_all_four_phrases_are_available_and_in_the_documented_order(): void
    {
        $leads = array_column(StudentGreeting::OPTIONS, 'lead');

        $this->assertSame(
            ['Yo', 'Asey!', 'Wossup', 'How be?'],
            $leads,
            'The rotating dashboard greetings must match exactly what the product spec listed.',
        );
    }

    public function test_phrases_ending_in_punctuation_omit_the_trailing_comma(): void
    {
        $byLead = [];
        foreach (StudentGreeting::OPTIONS as $opt) {
            $byLead[$opt['lead']] = $opt['sep'];
        }

        // "Asey!" and "How be?" already carry sentence-ending punctuation,
        // so the template separator must be empty — no awkward "Asey!, John".
        $this->assertSame('', $byLead['Asey!']);
        $this->assertSame('', $byLead['How be?']);

        // The plain phrases get the standard ", Name" treatment.
        $this->assertSame(',', $byLead['Yo']);
        $this->assertSame(',', $byLead['Wossup']);
    }

    public function test_pick_is_stable_within_the_same_hour_bucket_for_the_same_user(): void
    {
        $bucket = new \DateTimeImmutable('2026-05-23 12:15:00');

        $a = StudentGreeting::for(42, $bucket);
        $b = StudentGreeting::for(42, $bucket->modify('+30 minutes'));

        $this->assertSame(
            $a,
            $b,
            'The same student looking at the dashboard twice in one hour must see the same greeting — no flicker.',
        );
    }

    public function test_pick_rotates_when_the_hour_bucket_changes(): void
    {
        // Across 24 different hour-buckets for the same user, we should
        // hit every option at least once — proves the rotation actually
        // rotates and isn't just stuck on a single phrase.
        $hits = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $bucket = new \DateTimeImmutable(sprintf('2026-05-23 %02d:00:00', $hour));
            $hits[StudentGreeting::for(7, $bucket)['lead']] = true;
        }

        $this->assertSame(
            ['Yo', 'Asey!', 'Wossup', 'How be?'],
            array_keys(array_intersect_key(
                ['Yo' => true, 'Asey!' => true, 'Wossup' => true, 'How be?' => true],
                $hits,
            )),
            'Over the course of a day every greeting must be picked at least once for the same user.',
        );
    }

    public function test_different_users_can_see_different_greetings_in_the_same_hour(): void
    {
        $bucket = new \DateTimeImmutable('2026-05-23 09:00:00');
        $leads = [];
        for ($uid = 1; $uid <= 50; $uid++) {
            $leads[StudentGreeting::for($uid, $bucket)['lead']] = true;
        }

        $this->assertGreaterThan(
            1,
            count($leads),
            '50 different students in the same hour must collectively see more than one greeting (rotation is per-user, not global).',
        );
    }
}
