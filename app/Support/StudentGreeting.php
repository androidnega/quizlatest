<?php

namespace App\Support;

use App\Models\User;

/**
 * Rotating, personable greetings for the student dashboard.
 *
 * The greeting changes throughout the day so the dashboard never feels
 * stale, but it's stable within a single hour-bucket per user so a quick
 * tap-around doesn't make the header flicker between four different
 * phrases on every navigation. Picking is deterministic from the user
 * id + hour, so no randomness leaks into the cache layer.
 *
 * Each option exposes both the lead phrase and the separator that should
 * sit between the phrase and the student's name. Some options end in a
 * punctuation mark ("Asey!", "How be?") and would look odd if the view
 * blindly appended a comma — the separator lets the template render the
 * right shape without any per-phrase branching.
 */
final class StudentGreeting
{
    /**
     * @var list<array{lead: string, sep: string}>
     */
    public const OPTIONS = [
        ['lead' => 'Yo',      'sep' => ','],
        ['lead' => 'Asey!',   'sep' => ''],
        ['lead' => 'Wossup',  'sep' => ','],
        ['lead' => 'How be?', 'sep' => ''],
    ];

    /**
     * Pick a greeting for a given student. Stable per (user, hour).
     *
     * @return array{lead: string, sep: string}
     */
    public static function for(User|int|null $user, ?\DateTimeInterface $now = null): array
    {
        $userId = $user instanceof User ? (int) $user->id : (int) ($user ?? 0);
        $bucket = ($now ?? new \DateTimeImmutable('now'))->format('YmdH');

        $idx = abs(crc32($bucket.'-'.$userId)) % count(self::OPTIONS);

        return self::OPTIONS[$idx];
    }
}
