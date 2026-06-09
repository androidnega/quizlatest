<?php

namespace Tests\Feature\Student;

use App\Models\User;
use App\Services\StudentDashboardBrandingService;
use App\Services\SystemSettingsService;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Wallet mobile dashboard: theme picker, cross-page chrome, countdown,
 * deduplicated nav, and notification badge.
 *
 * The wallet view itself only renders at phone widths (`lg:hidden`) but the
 * Blade output is identical at every viewport; we assert on the rendered HTML
 * rather than viewport behaviour.
 */
class StudentDashboardWalletThemeTest extends TestCase
{
    use RefreshDatabase;

    private function seedUniversity(): int
    {
        $this->seed(InitialSetupSeeder::class);

        return (int) DB::table('universities')->value('id');
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_super_admin' => true,
            'university_id' => $this->seedUniversity(),
            'email_verified_at' => now(),
        ]);
    }

    private function student(int $uniId): User
    {
        return User::factory()->create([
            'role' => 'student',
            'university_id' => $uniId,
            'email' => 'student.wallet.'.Str::random(6).'@test.edu',
            'index_number' => 'WL'.random_int(1000, 9999),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    public function test_wallet_theme_defaults_to_teal_when_unset(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $branding = app(StudentDashboardBrandingService::class);

        $this->assertSame('teal', $branding->walletTheme());
    }

    public function test_wallet_theme_falls_back_to_teal_for_unknown_slugs(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $branding = app(StudentDashboardBrandingService::class);
        $admin = $this->superAdmin();
        app(SystemSettingsService::class)->set(
            StudentDashboardBrandingService::WALLET_THEME_SETTING_KEY,
            'bogus-theme-value',
            $admin,
        );

        $this->assertSame(
            'teal',
            $branding->walletTheme(),
            'Unknown theme slugs must fall back to the default so a bad write never breaks the UI.',
        );
    }

    public function test_wallet_theme_options_include_all_four_known_themes(): void
    {
        $branding = app(StudentDashboardBrandingService::class);
        $slugs = array_column($branding->walletThemeOptions(), 'slug');

        $this->assertSame(['teal', 'forest', 'indigo', 'coral', 'noir'], $slugs);
    }

    public function test_super_admin_can_pick_each_wallet_theme(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $super = $this->superAdmin();
        $settings = app(SystemSettingsService::class);

        foreach (['teal', 'forest', 'indigo', 'coral', 'noir'] as $slug) {
            $this->actingAs($super)
                ->put(route('admin.settings.update'), [
                    'student_dashboard_mobile_wallet' => '1',
                    'student_dashboard_mobile_wallet_theme' => $slug,
                ])
                ->assertRedirect(route('admin.settings.index'));

            $this->assertSame(
                $slug,
                $settings->get(StudentDashboardBrandingService::WALLET_THEME_SETTING_KEY),
                "Theme '{$slug}' must be persisted by the admin form submission.",
            );
        }
    }

    public function test_super_admin_settings_page_renders_all_four_theme_radios(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $super = $this->superAdmin();

        $html = (string) $this->actingAs($super)
            ->get(route('admin.settings.index', absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="student_dashboard_mobile_wallet_theme"', $html);
        foreach (['teal', 'forest', 'indigo', 'coral', 'noir'] as $slug) {
            $this->assertStringContainsString(
                'value="'.$slug.'"',
                $html,
                "Theme radio for '{$slug}' must be rendered.",
            );
        }
    }

    public function test_invalid_wallet_theme_value_is_rejected_by_validation(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $super = $this->superAdmin();

        $this->actingAs($super)
            ->put(route('admin.settings.update'), [
                'student_dashboard_mobile_wallet_theme' => 'not-a-theme',
            ])
            ->assertSessionHasErrors('student_dashboard_mobile_wallet_theme');
    }

    public function test_student_body_carries_wallet_theme_attribute_when_enabled(): void
    {
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);
        $super = $this->superAdmin();
        $settings = app(SystemSettingsService::class);
        $settings->set('student_dashboard_mobile_wallet', '1', $super);
        $settings->set(
            StudentDashboardBrandingService::WALLET_THEME_SETTING_KEY,
            'forest',
            $super,
        );

        $this->actingAs($student);
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('qs-std--wallet', $html, '<body> must carry the wallet flag class.');
        $this->assertStringContainsString('data-qs-wallet-theme="forest"', $html, '<body> must expose the chosen theme slug so cross-page CSS can apply.');
        // The wallet partial itself also carries the per-instance theme attr.
        $this->assertStringContainsString('data-theme="forest"', $html);
    }

    public function test_student_body_does_not_carry_wallet_attributes_when_disabled(): void
    {
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);
        $settings = app(SystemSettingsService::class);
        $settings->set('student_dashboard_mobile_wallet', '0', $this->superAdmin());

        $this->actingAs($student);
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('qs-std--wallet', $html);
        $this->assertStringNotContainsString('data-qs-wallet-theme', $html);
    }

    public function test_wallet_theme_attribute_applies_to_other_student_pages_too(): void
    {
        // The whole point of the cross-page work: the chosen theme must reach
        // every student page (worklist, results, notifications, help, etc.) so
        // the mobile chrome looks like one coherent product.
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);
        $super = $this->superAdmin();
        $settings = app(SystemSettingsService::class);
        $settings->set('student_dashboard_mobile_wallet', '1', $super);
        $settings->set(
            StudentDashboardBrandingService::WALLET_THEME_SETTING_KEY,
            'indigo',
            $super,
        );

        $this->actingAs($student);
        foreach ([
            route('student.work.index'),
            route('student.results.index'),
            route('student.notifications.index'),
            route('student.help'),
        ] as $url) {
            $html = (string) $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString(
                'data-qs-wallet-theme="indigo"',
                $html,
                "Theme attribute must be present on {$url} so the mobile chrome stays themed across pages.",
            );
        }
    }

    public function test_wallet_hero_renders_live_countdown_for_next_upcoming_item(): void
    {
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);
        $super = $this->superAdmin();

        $settings = app(SystemSettingsService::class);
        $settings->set('student_dashboard_mobile_wallet', '1', $super);

        // Place the student in a class + course and publish an assignment with a
        // due date in the near future so the digest returns countdown data.
        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => (int) DB::table('departments')->where('code', 'CS')->value('id'),
            'code' => 'CS-CD-'.Str::random(4),
            'title' => 'Countdown course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $classroomId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'name' => 'CD-Section',
            'program_id' => (int) DB::table('programs')->where('code', 'BCS')->value('id'),
            'level_id' => (int) DB::table('levels')->where('code', '100')->value('id'),
            'academic_year_id' => (int) DB::table('academic_years')->where('is_active', true)->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('class_course')->insert(['class_id' => $classroomId, 'course_id' => $courseId]);
        DB::table('users')->where('id', $student->id)->update(['class_id' => $classroomId]);

        $dueAt = now()->addDays(2)->addHours(4)->addMinutes(12);
        $quizId = DB::table('quizzes')->insertGetId([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $super->id,
            'title' => 'Countdown midterm exam',
            'description' => 'Soon.',
            'assessment_type' => 'assignment',
            'selected_question_types' => json_encode(['essay']),
            'status' => 'published',
            'published_at' => now()->subHour(),
            'duration_minutes' => 90,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => json_encode(\App\Support\AssessmentProctoringDefaults::baselineForType('assignment', true, true, true)),
            'due_at' => $dueAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('quiz_class')->insert(['quiz_id' => $quizId, 'class_id' => $classroomId]);

        $this->actingAs($student->fresh());
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        // The hero focus is now the single source for the countdown — flat
        // on the gradient with the quiz title FIRST, the live clock SECOND,
        // and the caption ("Due in" / "Opens in") LAST.
        $this->assertStringContainsString('qs-std-wallet__hero-focus', $html, 'Wallet hero focus block must render.');
        $this->assertStringContainsString('qs-std-wallet__hero-title', $html, 'Hero must show the quiz title (it is the focal element).');
        $this->assertStringContainsString('qs-std-wallet__hero-clock', $html, 'Hero must show a live countdown clock.');
        $this->assertStringContainsString('qs-std-wallet__hero-caption', $html, 'Hero must show the trailing countdown caption.');
        $this->assertStringContainsString('data-qs-countdown', $html, 'Countdown must have the hook attribute the JS timer looks for.');
        $this->assertStringContainsString('data-qs-countdown-ends="', $html, 'Countdown must carry an ISO target.');

        // Mobile clock is hh:mm:ss only — the days segment is intentionally
        // omitted on phones (the JS rolls day overflow into hours).
        foreach (['data-qs-countdown-hours', 'data-qs-countdown-minutes', 'data-qs-countdown-seconds'] as $seg) {
            $this->assertStringContainsString($seg, $html, "Countdown must have the {$seg} segment.");
        }
        // Pull just the wallet clock and assert it has NO days slot. The
        // hero is now a non-clickable <div> with the live-clock segments
        // inside, so the closing wrapper we anchor on is </div>, not </a>.
        preg_match('/<span\s+class="qs-std-wallet__hero-clock"[\s\S]*?<\/span>\s*<\/div>/', $html, $clockMatch);
        $this->assertNotEmpty($clockMatch, 'Wallet hero clock markup must render.');
        $this->assertStringNotContainsString('data-qs-countdown-days', $clockMatch[0], 'Mobile wallet clock must not carry a days segment.');

        // The four CTA / counter / year strings that the user asked us to
        // strip must NOT appear inside the wallet hero block.
        preg_match('/<header class="qs-std-wallet__hero"[\s\S]*?<\/header>/', $html, $heroMatch);
        $this->assertNotEmpty($heroMatch, 'Wallet hero markup must be present so we can inspect it.');
        $heroHtml = $heroMatch[0];

        $this->assertStringNotContainsString('Your worklist today', $heroHtml, 'The "Your worklist today" header has been removed.');
        $this->assertStringNotContainsString('open item', $heroHtml, 'The "N open item(s)" counter has been removed.');
        $this->assertStringNotContainsString('See results', $heroHtml, 'The duplicated "See results" CTA has been removed.');
        $this->assertStringNotContainsString('View open', $heroHtml, 'The duplicated "View open" CTA has been removed.');

        // The clock segments must appear BEFORE the caption (title → clock
        // → caption), not the old "Opens in → title → clock" arrangement.
        $clockPos = strpos($heroHtml, 'qs-std-wallet__hero-clock');
        $captionPos = strpos($heroHtml, 'qs-std-wallet__hero-caption');
        $titlePos = strpos($heroHtml, 'qs-std-wallet__hero-title');
        $this->assertNotFalse($titlePos);
        $this->assertNotFalse($clockPos);
        $this->assertNotFalse($captionPos);
        $this->assertLessThan($clockPos, $titlePos, 'Title must come before the clock.');
        $this->assertLessThan($captionPos, $clockPos, 'Clock must come before the trailing caption.');
    }

    public function test_idle_wallet_hero_is_not_a_clickable_link(): void
    {
        // "You are all caught up / No upcoming items right now" should
        // never be a link. The user was tapping the empty hero and being
        // shipped to the worklist by accident. The Start pill (rendered
        // only when a quiz is startable) is the only clickable element
        // in the hero.
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);
        $super = $this->superAdmin();
        app(SystemSettingsService::class)->set('student_dashboard_mobile_wallet', '1', $super);

        $this->actingAs($student);
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        preg_match('/<header class="qs-std-wallet__hero"[\s\S]*?<\/header>/', $html, $heroMatch);
        $this->assertNotEmpty($heroMatch);
        $heroHtml = $heroMatch[0];

        // The idle copy is present.
        $this->assertStringContainsString('You are all caught up', $heroHtml);
        $this->assertStringContainsString('No upcoming items right now', $heroHtml);

        // The idle hero-focus block must NOT be an <a>, so the whole tile
        // can't be tapped. A plain <div> with the --idle modifier instead.
        $this->assertMatchesRegularExpression(
            '/<div\s+class="qs-std-wallet__hero-focus qs-std-wallet__hero-focus--idle"[\s\S]*?<\/div>/',
            $heroHtml,
            'Idle hero must render as a <div>, not an <a>, so it is not clickable.',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<a[^>]*class="qs-std-wallet__hero-focus[^"]*qs-std-wallet__hero-focus--idle/',
            $heroHtml,
            'Idle hero must not be wrapped in an anchor tag.',
        );
        // And there must be NO Start pill in the idle case either.
        $this->assertStringNotContainsString(
            'qs-std-wallet__hero-start qs-std-wallet__hero-start--ready',
            $heroHtml,
            'Idle hero must not render the Start pill — there is nothing to start.',
        );
    }

    public function test_wallet_hero_does_not_render_the_old_worklist_counter_or_ctas_even_when_idle(): void
    {
        // Same dedup guarantees but with no upcoming item — the hero falls
        // back to the friendly "You are all caught up" state and STILL must
        // not show the year, the counter, or the View open / See results CTAs.
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);
        $super = $this->superAdmin();
        app(SystemSettingsService::class)->set('student_dashboard_mobile_wallet', '1', $super);

        $this->actingAs($student);
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        preg_match('/<header class="qs-std-wallet__hero"[\s\S]*?<\/header>/', $html, $heroMatch);
        $this->assertNotEmpty($heroMatch);
        $heroHtml = $heroMatch[0];

        $this->assertStringNotContainsString('Your worklist today', $heroHtml);
        $this->assertStringNotContainsString('open item', $heroHtml);
        $this->assertStringNotContainsString('See results', $heroHtml);
        $this->assertStringNotContainsString('View open', $heroHtml);
    }

    public function test_student_desktop_dashboard_does_not_render_the_academic_year_line(): void
    {
        // The desktop student dashboard header used to surface the active
        // academic year ("2026/2027"). The user explicitly asked us to scope
        // year hierarchy to coordinators / super admins.
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);

        // Put the student inside an academic-year-bound class so the
        // controller WOULD compute a semester label — we want to prove the
        // VIEW omits it even when the data is available.
        $classroomId = DB::table('classes')
            ->where('university_id', $uniId)
            ->whereNotNull('academic_year_id')
            ->value('id');
        if ($classroomId !== null) {
            DB::table('users')->where('id', $student->id)->update(['class_id' => $classroomId]);
        }

        $this->actingAs($student->fresh());
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        $activeYearName = (string) DB::table('academic_years')
            ->where('university_id', $uniId)
            ->where('is_active', true)
            ->value('name');

        if ($activeYearName !== '') {
            // Be defensive: only the dashboard header surface — not feed rows
            // that legitimately mention dates. Slice the wrapping page block
            // and confirm the year name is not in the heading area.
            preg_match('/<header class="mb-6 hidden lg:block"[\s\S]*?<\/header>/', $html, $headerMatch);
            $this->assertNotEmpty($headerMatch, 'Desktop student dashboard header must render.');
            $this->assertStringNotContainsString($activeYearName, $headerMatch[0], 'Desktop student dashboard header must not show the academic year.');
        }
    }

    public function test_examiner_dashboard_blade_does_not_contain_the_academic_year_picker_markup(): void
    {
        // Year selector on the examiner dashboard has been removed: it was
        // bleeding admin hierarchy into the examiner surface. The controller
        // still defaults silently to the active year.
        //
        // We assert against the Blade source here because the live dashboard
        // would need a full examiner course assignment + policy seed to
        // render; the absence of the picker MARKUP is the property we care
        // about and it cannot regress without this string check failing.
        $source = (string) file_get_contents(resource_path('views/examiner/dashboard.blade.php'));

        $this->assertStringNotContainsString('id="examiner-dashboard-year"', $source, 'Examiner dashboard Blade must not contain the academic year <select>.');
        $this->assertStringNotContainsString('name="academic_year_id"', $source, 'Examiner dashboard Blade must not bind the academic_year_id form field.');
    }

    public function test_wallet_quick_actions_ring_always_renders_materials_tile_next_to_help(): void
    {
        // The Materials tile used to be conditional on the
        // studentMaterialsBrowseEnabled feature flag, so students with
        // browse turned off never saw it. The user asked for the tile to
        // always appear in the ring, sitting just before Help on the right.
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);
        $super = $this->superAdmin();
        app(SystemSettingsService::class)->set('student_dashboard_mobile_wallet', '1', $super);

        $this->actingAs($student);
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        preg_match('/<section class="qs-std-wallet__actions-card"[\s\S]*?<\/section>/', $html, $ringMatch);
        $this->assertNotEmpty($ringMatch, 'Quick actions ring must render in the wallet.');
        $ringHtml = $ringMatch[0];

        $this->assertStringContainsString('>Materials<', $ringHtml, 'Materials tile must be in the ring regardless of the browse flag.');
        $this->assertStringContainsString('>Help<', $ringHtml, 'Help tile must still be in the ring.');

        // Order: Materials must come BEFORE Help so the user sees them as
        // adjacent tiles on the right of the ring.
        $matPos = strpos($ringHtml, '>Materials<');
        $helpPos = strpos($ringHtml, '>Help<');
        $this->assertNotFalse($matPos);
        $this->assertNotFalse($helpPos);
        $this->assertLessThan($helpPos, $matPos, 'Materials must sit just before Help in the ring.');
    }

    public function test_student_desktop_dashboard_hides_the_open_and_new_assessments_feed(): void
    {
        // The "Open & new assessments" section was duplicating the new
        // desktop hero countdown card + the stat-grid worklist link. We
        // hide it on desktop (>=lg) while keeping it on the classic mobile
        // dashboard for students whose super admin has the wallet off.
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);

        $this->actingAs($student->fresh());
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        preg_match('/<section[^>]+id="dash-new-heading"|<section[^>]+aria-labelledby="dash-new-heading"[^>]*>/', $html, $sectionMatch);
        if ($sectionMatch !== []) {
            // The section may still render server-side, but its wrapper
            // MUST carry the lg:hidden utility so it disappears at desktop.
            preg_match('/<section[^>]*aria-labelledby="dash-new-heading"[^>]*>/', $html, $openTag);
            $this->assertNotEmpty($openTag, 'Open tag for the new-assessments section must be parseable.');
            $this->assertStringContainsString('lg:hidden', $openTag[0], '"Open & new assessments" section must be hidden on desktop (lg:hidden).');
        }
    }

    public function test_student_desktop_dashboard_renders_the_hero_countdown_BELOW_the_stat_grid(): void
    {
        // The user explicitly asked for the countdown to sit AFTER the stat
        // cards on desktop (was previously above). Position in markup
        // determines visual order — we just check ordering of the two
        // anchors in the rendered HTML.
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);
        $super = $this->superAdmin();

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => (int) DB::table('departments')->where('code', 'CS')->value('id'),
            'code' => 'CS-OR-'.Str::random(4),
            'title' => 'Order course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $classroomId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'name' => 'OR-Section',
            'program_id' => (int) DB::table('programs')->where('code', 'BCS')->value('id'),
            'level_id' => (int) DB::table('levels')->where('code', '100')->value('id'),
            'academic_year_id' => (int) DB::table('academic_years')->where('is_active', true)->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('class_course')->insert(['class_id' => $classroomId, 'course_id' => $courseId]);
        DB::table('users')->where('id', $student->id)->update(['class_id' => $classroomId]);

        $quizId = DB::table('quizzes')->insertGetId([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $super->id,
            'title' => 'Order proof exam',
            'description' => 'Soon.',
            'assessment_type' => 'assignment',
            'selected_question_types' => json_encode(['essay']),
            'status' => 'published',
            'published_at' => now()->subHour(),
            'duration_minutes' => 90,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => json_encode(\App\Support\AssessmentProctoringDefaults::baselineForType('assignment', true, true, true)),
            'due_at' => now()->addDays(1)->addHours(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('quiz_class')->insert(['quiz_id' => $quizId, 'class_id' => $classroomId]);

        $this->actingAs($student->fresh());
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        $statPos = strpos($html, 'qs-stat-grid--dash');
        $countdownPos = strpos($html, 'qs-std-hero-countdown__card');
        $this->assertNotFalse($statPos, 'Stat grid must render.');
        $this->assertNotFalse($countdownPos, 'Desktop hero countdown must render.');
        $this->assertLessThan($countdownPos, $statPos, 'Stat grid must precede the desktop hero countdown.');
    }

    public function test_wallet_hero_surfaces_open_quiz_with_start_button_when_no_live_countdown(): void
    {
        // The user's intent: when the countdown is done (or never existed
        // because the quiz is already open), the hero must surface THAT
        // exam right there with a Start button — never fall back to a
        // generic "You are all caught up" message while a startable quiz
        // is waiting in the digest.
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);
        $super = $this->superAdmin();
        app(SystemSettingsService::class)->set('student_dashboard_mobile_wallet', '1', $super);

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => (int) DB::table('departments')->where('code', 'CS')->value('id'),
            'code' => 'CS-OP-'.Str::random(4),
            'title' => 'Open quiz course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $classroomId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'name' => 'OP-Section',
            'program_id' => (int) DB::table('programs')->where('code', 'BCS')->value('id'),
            'level_id' => (int) DB::table('levels')->where('code', '100')->value('id'),
            'academic_year_id' => (int) DB::table('academic_years')->where('is_active', true)->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('class_course')->insert(['class_id' => $classroomId, 'course_id' => $courseId]);
        DB::table('users')->where('id', $student->id)->update(['class_id' => $classroomId]);

        // Quiz already open (start_time in the past, end_time well in the
        // future) → digest emits cta_label "Start quiz" but NO countdown
        // because we're not within the closing-soon window.
        DB::table('quizzes')->insert([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $super->id,
            'title' => 'Open right now quiz',
            'description' => 'Live.',
            'assessment_type' => 'quiz',
            'selected_question_types' => json_encode(['mcq']),
            'status' => 'published',
            'published_at' => now()->subDay(),
            'start_time' => now()->subHour(),
            'end_time' => now()->addDays(5),
            'duration_minutes' => 45,
            'total_marks' => 10,
            'questions_per_student' => 5,
            'proctoring_settings' => json_encode(\App\Support\AssessmentProctoringDefaults::baselineForType('quiz', true, true, true)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($student->fresh());
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        // The hero container is now a <div>, not an <a> — only the inner
        // Start pill is tappable. Match on the <div> wrapper so the test
        // reflects the new "container is informational" contract.
        preg_match('/<div[^>]+class="qs-std-wallet__hero-focus[\s\S]*?<\/div>/', $html, $heroMatch);
        $this->assertNotEmpty($heroMatch, 'Wallet hero focus block must render.');
        $heroHtml = $heroMatch[0];

        $this->assertStringContainsString('Open right now quiz', $heroHtml, 'Hero must show the open quiz title — not "You are all caught up".');
        $this->assertStringContainsString('qs-std-wallet__hero-start', $heroHtml, 'Hero must render the standalone Start pill when no live countdown but a quiz is startable.');
        $this->assertStringContainsString('qs-std-wallet__hero-start--ready', $heroHtml, 'Start pill must carry the ready state.');
        $this->assertStringContainsString('Start quiz', $heroHtml, 'Start pill must surface the digest cta_label.');
        $this->assertStringNotContainsString('You are all caught up', $heroHtml, 'Caught-up fallback must NOT show while a startable quiz exists.');

        // And critically: the Start pill itself must be a real <a>, while
        // the surrounding hero container must NOT be wrapped in an anchor.
        $this->assertMatchesRegularExpression(
            '/<a[^>]+class="qs-std-wallet__hero-start[^"]*"/',
            $heroHtml,
            'The Start pill itself must be an anchor — it is the only tappable element in the hero.',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<a[^>]+class="qs-std-wallet__hero-focus/',
            $heroHtml,
            'The hero container must NOT be an anchor — only the inner pill is clickable.',
        );
    }

    public function test_desktop_hero_countdown_card_renders_start_pill_when_no_live_countdown(): void
    {
        // Mirror of the mobile wallet behaviour for the desktop "ticket"
        // card — when the digest has no live countdown but a quiz is
        // startable right now, the desktop hero must still render and
        // surface that exam with a Start pill.
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);
        $super = $this->superAdmin();

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => (int) DB::table('departments')->where('code', 'CS')->value('id'),
            'code' => 'CS-DK-'.Str::random(4),
            'title' => 'Desktop start course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $classroomId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'name' => 'DK-Section',
            'program_id' => (int) DB::table('programs')->where('code', 'BCS')->value('id'),
            'level_id' => (int) DB::table('levels')->where('code', '100')->value('id'),
            'academic_year_id' => (int) DB::table('academic_years')->where('is_active', true)->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('class_course')->insert(['class_id' => $classroomId, 'course_id' => $courseId]);
        DB::table('users')->where('id', $student->id)->update(['class_id' => $classroomId]);

        // Open right now, no closing-soon window → no countdown but
        // canStart → digest cta_label "Start quiz".
        DB::table('quizzes')->insert([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $super->id,
            'title' => 'Desktop start quiz',
            'description' => 'Live now.',
            'assessment_type' => 'quiz',
            'selected_question_types' => json_encode(['mcq']),
            'status' => 'published',
            'published_at' => now()->subDay(),
            'start_time' => now()->subHour(),
            'end_time' => now()->addDays(7),
            'duration_minutes' => 45,
            'total_marks' => 10,
            'questions_per_student' => 5,
            'proctoring_settings' => json_encode(\App\Support\AssessmentProctoringDefaults::baselineForType('quiz', true, true, true)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($student->fresh());
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('qs-std-hero-countdown__card', $html, 'Desktop hero card must render even without a live countdown.');
        $this->assertStringContainsString('qs-std-hero-countdown__start', $html, 'Desktop hero must surface a permanent Start pill when a quiz is startable right now.');
        $this->assertStringContainsString('Desktop start quiz', $html, 'Desktop hero must show the startable quiz title.');
        $this->assertStringContainsString('Start quiz', $html, 'Desktop hero Start pill must use the digest cta_label.');
    }

    public function test_dashboard_feed_row_countdown_transforms_into_start_cta_when_expired(): void
    {
        // The user explicitly said: when the countdown is done, it must
        // TRANSFORM into "Start" — never be removed, never be left at 00:00:00.
        // This proves the feed-row in the classic mobile dashboard panel
        // (rendered with wallet OFF) carries the pre-rendered Start swap.
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);
        $super = $this->superAdmin();

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => (int) DB::table('departments')->where('code', 'CS')->value('id'),
            'code' => 'CS-FR-'.Str::random(4),
            'title' => 'Feed row swap course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $classroomId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'name' => 'FR-Section',
            'program_id' => (int) DB::table('programs')->where('code', 'BCS')->value('id'),
            'level_id' => (int) DB::table('levels')->where('code', '100')->value('id'),
            'academic_year_id' => (int) DB::table('academic_years')->where('is_active', true)->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('class_course')->insert(['class_id' => $classroomId, 'course_id' => $courseId]);
        DB::table('users')->where('id', $student->id)->update(['class_id' => $classroomId]);

        DB::table('quizzes')->insert([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $super->id,
            'title' => 'Feed swap quiz',
            'description' => 'Soon.',
            'assessment_type' => 'quiz',
            'selected_question_types' => json_encode(['mcq']),
            'status' => 'published',
            'published_at' => now()->subHour(),
            'start_time' => now()->addHours(3),
            'end_time' => now()->addHours(6),
            'duration_minutes' => 45,
            'total_marks' => 10,
            'questions_per_student' => 5,
            'proctoring_settings' => json_encode(\App\Support\AssessmentProctoringDefaults::baselineForType('quiz', true, true, true)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($student->fresh());
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        // The feed row carries the live block AND the expired CTA inside
        // the SAME .qs-wl-countdown wrapper so the JS .is-expired class
        // can swap visibility without removing the element.
        preg_match('/<div\s+class="qs-wl-countdown[^"]*"[\s\S]*?<\/div>\s*<\/div>/', $html, $rowCountdownMatch);
        $this->assertNotEmpty($rowCountdownMatch, 'Worklist feed-row must render the countdown wrapper.');
        $rowHtml = $rowCountdownMatch[0];

        $this->assertStringContainsString('qs-wl-countdown__live', $rowHtml, 'Feed-row must wrap the ticking clock in __live so CSS can hide it on expire.');
        $this->assertStringContainsString('qs-wl-countdown__expired', $rowHtml, 'Feed-row must pre-render the post-expiry CTA.');
        $this->assertStringContainsString('qs-wl-countdown__expired--ready', $rowHtml, 'Feed-row "Opens in" must produce the ready expired state.');
        // The dashboard feed pulls from the digest which provides a
        // type-aware Start label ("Start quiz" / "Start exam" / etc.) — so
        // we assert the prefix "Start" appears as the post-expiry CTA text,
        // regardless of the trailing type word.
        $this->assertMatchesRegularExpression('/<span>Start(\s+\w+)?<\/span>/', $rowHtml, 'Feed-row expired CTA must surface a Start label (with optional type word).');
        $this->assertStringContainsString('data-qs-countdown-expired-state="ready"', $rowHtml, 'Feed-row countdown must carry the expired-state hook.');
    }

    public function test_wallet_hero_carries_dynamic_expired_cta_markup_when_countdown_is_an_opens_in(): void
    {
        // The hero must ship the post-expiry "Start now" CTA in the markup
        // alongside the live clock. studentDashboardCountdown.js then swaps
        // visibility on `.is-expired` so the surface dynamically transitions
        // from a timer to a start button without a page reload.
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);
        $super = $this->superAdmin();
        app(SystemSettingsService::class)->set('student_dashboard_mobile_wallet', '1', $super);

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => (int) DB::table('departments')->where('code', 'CS')->value('id'),
            'code' => 'CS-EX-'.Str::random(4),
            'title' => 'Expired CTA course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $classroomId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'name' => 'EX-Section',
            'program_id' => (int) DB::table('programs')->where('code', 'BCS')->value('id'),
            'level_id' => (int) DB::table('levels')->where('code', '100')->value('id'),
            'academic_year_id' => (int) DB::table('academic_years')->where('is_active', true)->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('class_course')->insert(['class_id' => $classroomId, 'course_id' => $courseId]);
        DB::table('users')->where('id', $student->id)->update(['class_id' => $classroomId]);

        // start_time in the near future → digest returns "Opens in" countdown.
        DB::table('quizzes')->insert([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $super->id,
            'title' => 'Expired CTA quiz',
            'description' => 'Soon.',
            'assessment_type' => 'quiz',
            'selected_question_types' => json_encode(['mcq']),
            'status' => 'published',
            'published_at' => now()->subHour(),
            'start_time' => now()->addHours(3),
            'end_time' => now()->addHours(6),
            'duration_minutes' => 45,
            'total_marks' => 10,
            'questions_per_student' => 5,
            'proctoring_settings' => json_encode(\App\Support\AssessmentProctoringDefaults::baselineForType('quiz', true, true, true)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($student->fresh());
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        // 1. The countdown wrapper opts in to staying visible on expire.
        $this->assertStringContainsString('data-qs-countdown-keep-visible', $html, 'Hero countdown must opt in to staying visible when expired so CSS can swap it.');
        // 2. The data-driven expired state is on the wrapper.
        $this->assertStringContainsString('data-qs-countdown-expired-state="ready"', $html, 'Hero must carry the expired state derived from prefix.');
        // 3. The expired-CTA markup is pre-rendered (server-side text, JS just toggles CSS).
        $this->assertStringContainsString('qs-std-wallet__hero-expired', $html, 'Mobile wallet hero must render the expired CTA pill.');
        $this->assertStringContainsString('qs-std-wallet__hero-expired--ready', $html, 'Mobile expired pill must carry the ready state modifier.');
        $this->assertStringContainsString('Start quiz', $html, 'Post-expiry CTA must be the assessment-type-aware "Start quiz" label.');
        // 4. Live + expired blocks coexist so CSS can swap between them.
        $this->assertStringContainsString('qs-std-wallet__hero-clock-live', $html, 'Mobile hero must keep the live clock wrapper so CSS can hide it on expire.');
    }

    public function test_dashboard_digest_promotes_closes_in_countdown_to_closed_cta(): void
    {
        // The digest must surface a state-aware expired CTA per prefix so
        // every consumer (mobile wallet, desktop card, future widgets) can
        // display the right post-expiry message without re-deriving.
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);
        $super = $this->superAdmin();

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => (int) DB::table('departments')->where('code', 'CS')->value('id'),
            'code' => 'CS-CL-'.Str::random(4),
            'title' => 'Closing window course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $classroomId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'name' => 'CL-Section',
            'program_id' => (int) DB::table('programs')->where('code', 'BCS')->value('id'),
            'level_id' => (int) DB::table('levels')->where('code', '100')->value('id'),
            'academic_year_id' => (int) DB::table('academic_years')->where('is_active', true)->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('class_course')->insert(['class_id' => $classroomId, 'course_id' => $courseId]);
        DB::table('users')->where('id', $student->id)->update(['class_id' => $classroomId]);

        // Already-open quiz that closes soon → "Closes in" prefix.
        DB::table('quizzes')->insert([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $super->id,
            'title' => 'Closing soon quiz',
            'description' => 'Soon.',
            'assessment_type' => 'quiz',
            'selected_question_types' => json_encode(['mcq']),
            'status' => 'published',
            'published_at' => now()->subDay(),
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(2),
            'duration_minutes' => 45,
            'total_marks' => 10,
            'questions_per_student' => 5,
            'proctoring_settings' => json_encode(\App\Support\AssessmentProctoringDefaults::baselineForType('quiz', true, true, true)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $digest = app(\App\Services\StudentNoticeDigestService::class)
            ->dashboardOpenAssessments($student->fresh());

        $this->assertNotSame([], $digest, 'Digest must surface the closing-soon item.');
        $item = $digest[0];
        $this->assertSame('Closes in', $item['countdown_prefix'] ?? null);
        $this->assertSame('Closed', $item['countdown_expired_cta'] ?? null, 'A "Closes in" prefix must promote to a "Closed" CTA on expire.');
        $this->assertSame('closed', $item['countdown_expired_state'] ?? null, 'A "Closes in" prefix must carry the "closed" expired state.');
    }

    public function test_dashboard_digest_promotes_due_in_countdown_to_submit_now_cta(): void
    {
        // Assignments with a due-soon countdown should flip to "Submit now"
        // when the timer hits 0 — overdue, but the action surface is still
        // an action, not a dead end.
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);
        $super = $this->superAdmin();

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => (int) DB::table('departments')->where('code', 'CS')->value('id'),
            'code' => 'CS-DU-'.Str::random(4),
            'title' => 'Due soon course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $classroomId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'name' => 'DU-Section',
            'program_id' => (int) DB::table('programs')->where('code', 'BCS')->value('id'),
            'level_id' => (int) DB::table('levels')->where('code', '100')->value('id'),
            'academic_year_id' => (int) DB::table('academic_years')->where('is_active', true)->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('class_course')->insert(['class_id' => $classroomId, 'course_id' => $courseId]);
        DB::table('users')->where('id', $student->id)->update(['class_id' => $classroomId]);

        // Assignment with a due date soon → "Due in" prefix.
        DB::table('quizzes')->insert([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $super->id,
            'title' => 'Due soon essay',
            'description' => 'Soon.',
            'assessment_type' => 'assignment',
            'selected_question_types' => json_encode(['essay']),
            'status' => 'published',
            'published_at' => now()->subDay(),
            'due_at' => now()->addHours(4),
            'duration_minutes' => 90,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => json_encode(\App\Support\AssessmentProctoringDefaults::baselineForType('assignment', true, true, true)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $digest = app(\App\Services\StudentNoticeDigestService::class)
            ->dashboardOpenAssessments($student->fresh());

        $this->assertNotSame([], $digest);
        $item = $digest[0];
        $this->assertStringContainsString('Due in', (string) ($item['countdown_prefix'] ?? ''));
        $this->assertSame('Submit now', $item['countdown_expired_cta'] ?? null, 'A "Due in" prefix must promote to a "Submit now" CTA on expire.');
        $this->assertSame('overdue', $item['countdown_expired_state'] ?? null, 'A "Due in" prefix must carry the "overdue" expired state.');
    }

    public function test_student_desktop_dashboard_renders_the_new_hero_countdown_card(): void
    {
        // The user asked for a sleek cream / dark "exam ticket" countdown at
        // the top of the desktop dashboard — this proves the new partial
        // renders with the right structural hooks (so the existing
        // studentDashboardCountdown.js timer can drive it).
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);
        $super = $this->superAdmin();

        // Same upcoming-assignment seed pattern used in the wallet test.
        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => (int) DB::table('departments')->where('code', 'CS')->value('id'),
            'code' => 'CS-DT-'.Str::random(4),
            'title' => 'Desktop hero course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $classroomId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'name' => 'DT-Section',
            'program_id' => (int) DB::table('programs')->where('code', 'BCS')->value('id'),
            'level_id' => (int) DB::table('levels')->where('code', '100')->value('id'),
            'academic_year_id' => (int) DB::table('academic_years')->where('is_active', true)->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('class_course')->insert(['class_id' => $classroomId, 'course_id' => $courseId]);
        DB::table('users')->where('id', $student->id)->update(['class_id' => $classroomId]);

        $quizId = DB::table('quizzes')->insertGetId([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $super->id,
            'title' => 'Desktop hero exam',
            'description' => 'Soon.',
            'assessment_type' => 'assignment',
            'selected_question_types' => json_encode(['essay']),
            'status' => 'published',
            'published_at' => now()->subHour(),
            'duration_minutes' => 90,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => json_encode(\App\Support\AssessmentProctoringDefaults::baselineForType('assignment', true, true, true)),
            'due_at' => now()->addDays(1)->addHours(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('quiz_class')->insert(['quiz_id' => $quizId, 'class_id' => $classroomId]);

        $this->actingAs($student->fresh());
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('qs-std-hero-countdown', $html, 'Desktop hero countdown wrapper must render.');
        $this->assertStringContainsString('qs-std-hero-countdown__card', $html, 'Desktop hero countdown card must render.');
        $this->assertStringContainsString('qs-std-hero-countdown__timer', $html, 'Desktop hero countdown timer block must render.');
        $this->assertStringContainsString('qs-std-hero-countdown__clock', $html, 'Desktop hero countdown clock must render.');
        $this->assertStringContainsString('Desktop hero exam', $html, 'Desktop hero must surface the upcoming quiz title.');
        $this->assertStringContainsString('data-qs-countdown-ends="', $html, 'Desktop hero must carry an ISO countdown target.');
    }

    public function test_wallet_quick_actions_ring_does_not_duplicate_results_or_notifications(): void
    {
        // The bug being fixed: "Results" and "Notifications" used to appear
        // both in the quick-actions ring AND in the bottom nav / bell. The
        // ring should now surface Worklist + Assignments + (Materials) + Help.
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);
        $super = $this->superAdmin();
        app(SystemSettingsService::class)->set('student_dashboard_mobile_wallet', '1', $super);

        $this->actingAs($student);
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        // Pull out just the quick-actions ring slice so we don't false-match
        // strings from the bottom nav, the bell, the activity feed, etc.
        $ringMatched = preg_match(
            '/<section class="qs-std-wallet__actions-card"[\s\S]*?<\/section>/',
            $html,
            $ringMatch,
        );
        $this->assertSame(1, $ringMatched, 'Quick actions ring must be present.');
        $ringHtml = $ringMatch[0];

        $this->assertStringContainsString('>'.__('Worklist').'<', $ringHtml);
        $this->assertStringContainsString('>'.__('Assignments').'<', $ringHtml);
        $this->assertStringContainsString('>'.__('Help').'<', $ringHtml);

        $this->assertStringNotContainsString(
            '>'.__('Results').'<',
            $ringHtml,
            'Results lives in the bottom nav + the See results CTA — must not be duplicated in the ring.',
        );
        $this->assertStringNotContainsString(
            '>'.__('Notices').'<',
            $ringHtml,
            'Notifications live on the bell — must not be duplicated in the ring.',
        );
        $this->assertStringNotContainsString(
            '>'.__('Notifications').'<',
            $ringHtml,
            'Notifications live on the bell — must not be duplicated in the ring.',
        );
    }

    public function test_wallet_floating_nav_does_not_carry_a_redundant_notification_badge(): void
    {
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);
        $super = $this->superAdmin();
        app(SystemSettingsService::class)->set('student_dashboard_mobile_wallet', '1', $super);

        $this->actingAs($student);
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString(
            'qs-std-wallet__nav-fab-dot',
            $html,
            'The central FAB no longer doubles as a notifications button — its dot/badge must not render.',
        );
    }

    public function test_noir_theme_renders_dark_wallet_chrome_across_student_pages(): void
    {
        // The dark "noir" theme is the QuizSnap-coloured equivalent of the
        // dark fintech wallet screenshot — same matte-black structure but
        // populated with QuizSnap data. Picking it must:
        //   1. expose a "Noir" radio in admin settings,
        //   2. flip the wallet hero's data-theme attribute,
        //   3. light up the body-level data-qs-wallet-theme="noir" attribute
        //      so the mobile shell + FAB on every student page picks up the
        //      matching dark chrome.
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);
        $super = $this->superAdmin();
        $settings = app(SystemSettingsService::class);
        $settings->set('student_dashboard_mobile_wallet', '1', $super);
        $settings->set(
            StudentDashboardBrandingService::WALLET_THEME_SETTING_KEY,
            'noir',
            $super,
        );

        $this->actingAs($student->fresh());
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('data-theme="noir"', $html, 'Wallet hero must carry the noir data-theme attribute when noir is picked.');
        $this->assertStringContainsString('data-qs-wallet-theme="noir"', $html, 'Body must carry the noir wallet-theme attribute so the mobile shell picks up the dark chrome on every student page.');

        // The admin settings page should also expose Noir as a radio.
        $adminHtml = (string) $this->actingAs($super)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('value="noir"', $adminHtml);
        $this->assertStringContainsString('Noir wallet', $adminHtml);
    }

    public function test_dashboard_greetings_use_the_rotating_set_instead_of_plain_hi(): void
    {
        // Both the desktop dashboard header AND the mobile wallet hero must
        // pick from the rotating greeting list (Yo / Asey! / Wossup / How
        // be?) instead of the old "Hello," / "Welcome back" copy. Asserting
        // both surfaces in one run keeps the spec single-sourced.
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);
        $super = $this->superAdmin();
        app(SystemSettingsService::class)->set('student_dashboard_mobile_wallet', '1', $super);

        $this->actingAs($student->fresh());
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        // Old copy must be gone from the dashboard.
        $this->assertStringNotContainsString('Hello,', $html, 'The static "Hello," greeting must be replaced by the rotating set.');
        $this->assertStringNotContainsString('Welcome back,', $html, 'The static "Welcome back," greeting must be replaced by the rotating set.');

        // Exactly one of the four rotating phrases must appear in the
        // mobile wallet greet area.
        preg_match('/qs-std-wallet__greet-lead">([^<]*)</', $html, $walletMatch);
        $this->assertNotEmpty($walletMatch, 'Mobile wallet must render the greeting lead span.');
        $walletLead = trim((string) $walletMatch[1]);
        $this->assertContains(
            rtrim($walletLead, ','),
            ['Yo', 'Asey!', 'Wossup', 'How be?'],
            'Mobile wallet greeting must come from the rotating set, got: '.$walletLead,
        );

        // And the same lead must appear once in the desktop header H1 too
        // (since both surfaces share the same hour-bucket pick for one
        // user, the greeting matches exactly).
        $expectedDesktopLead = rtrim($walletLead, ',');
        $this->assertMatchesRegularExpression(
            '/<h1[^>]*>\s*'.preg_quote($expectedDesktopLead, '/').'/',
            $html,
            'Desktop dashboard H1 must lead with the same rotating greeting as the wallet hero.',
        );
    }

    public function test_wallet_bell_renders_unread_notice_count_badge(): void
    {
        // The mobile dashboard bell must show the unread-notice count badge.
        // The composer ships $studentNoticeCount onto the layout but slot
        // content doesn't see it, so we register the composer onto the
        // dashboard view + the wallet partial too. This test proves the bell
        // badge actually renders when the digest has unread notices.
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);
        $super = $this->superAdmin();
        app(SystemSettingsService::class)->set('student_dashboard_mobile_wallet', '1', $super);

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => (int) DB::table('departments')->where('code', 'CS')->value('id'),
            'code' => 'CS-BN-'.Str::random(4),
            'title' => 'Bell badge course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $classroomId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'name' => 'BN-Section',
            'program_id' => (int) DB::table('programs')->where('code', 'BCS')->value('id'),
            'level_id' => (int) DB::table('levels')->where('code', '100')->value('id'),
            'academic_year_id' => (int) DB::table('academic_years')->where('is_active', true)->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('class_course')->insert(['class_id' => $classroomId, 'course_id' => $courseId]);
        DB::table('users')->where('id', $student->id)->update(['class_id' => $classroomId]);

        // Publish a couple of quizzes recently — the digest service will
        // surface them as "new published" unread notices for the student.
        for ($i = 0; $i < 3; $i++) {
            DB::table('quizzes')->insert([
                'university_id' => $uniId,
                'course_id' => $courseId,
                'created_by' => $super->id,
                'title' => 'Bell notice quiz '.$i,
                'description' => 'Notice.',
                'assessment_type' => 'quiz',
                'selected_question_types' => json_encode(['mcq']),
                'status' => 'published',
                'published_at' => now()->subHours($i + 1),
                'start_time' => now()->subHour(),
                'end_time' => now()->addDays(7),
                'duration_minutes' => 30,
                'total_marks' => 10,
                'questions_per_student' => 5,
                'proctoring_settings' => json_encode(\App\Support\AssessmentProctoringDefaults::baselineForType('quiz', true, true, true)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $expectedCount = app(\App\Services\StudentNoticeDigestService::class)
            ->noticeCount($student->fresh());
        $this->assertGreaterThan(
            0,
            $expectedCount,
            'Test setup precondition: published quizzes must surface as unread notices.',
        );

        $this->actingAs($student->fresh());
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        preg_match(
            '/<a[^>]*class="qs-std-wallet__bell"[^>]*>([\s\S]*?)<\/a>/',
            $html,
            $bellMatch,
        );
        $this->assertNotEmpty($bellMatch, 'Mobile wallet must render the bell anchor.');

        $this->assertStringContainsString(
            'qs-std-wallet__bell-dot',
            $bellMatch[0],
            'The unread-notice badge must render on the wallet bell when the digest has unread notices.',
        );
        $expectedLabel = $expectedCount > 9 ? '9+' : (string) $expectedCount;
        $this->assertStringContainsString(
            '>'.$expectedLabel.'<',
            $bellMatch[0],
            'The bell badge must display the actual unread-notice count from the digest.',
        );
    }

    public function test_mobile_wallet_hero_with_live_countdown_is_not_clickable_at_the_container_level(): void
    {
        // While the timer is ticking, ONLY the inline expired-state pill
        // should be tappable (and only once the JS reveals it). The hero
        // container must be a plain <div>, not an <a>, so a stray tap on
        // the title / clock / caption never navigates the student away.
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);
        $super = $this->superAdmin();

        app(SystemSettingsService::class)->set('student_dashboard_mobile_wallet', '1', $super);

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => (int) DB::table('departments')->where('code', 'CS')->value('id'),
            'code' => 'CS-NCK-'.Str::random(4),
            'title' => 'Non-clickable hero course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $classroomId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'name' => 'NCK-Section',
            'program_id' => (int) DB::table('programs')->where('code', 'BCS')->value('id'),
            'level_id' => (int) DB::table('levels')->where('code', '100')->value('id'),
            'academic_year_id' => (int) DB::table('academic_years')->where('is_active', true)->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('class_course')->insert(['class_id' => $classroomId, 'course_id' => $courseId]);
        DB::table('users')->where('id', $student->id)->update(['class_id' => $classroomId]);

        DB::table('quizzes')->insert([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $super->id,
            'title' => 'Hero NCK upcoming quiz',
            'description' => 'Soon.',
            'assessment_type' => 'quiz',
            'selected_question_types' => json_encode(['mcq']),
            'status' => 'published',
            'published_at' => now()->subMinutes(20),
            // Opens in the near future so the digest emits an "Opens in"
            // countdown — the surface we're trying to neutralize.
            'start_time' => now()->addHours(2),
            'end_time' => now()->addDays(3),
            'duration_minutes' => 30,
            'total_marks' => 10,
            'questions_per_student' => 5,
            'proctoring_settings' => json_encode(\App\Support\AssessmentProctoringDefaults::baselineForType('quiz', true, true, true)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($student->fresh());
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        // Pull the hero block out of the wallet header so we don't pick
        // up any links outside the hero (bell, profile greeting, etc.).
        preg_match('/<header class="qs-std-wallet__hero"[\s\S]*?<\/header>/', $html, $heroMatch);
        $this->assertNotEmpty($heroMatch, 'Wallet hero markup must render.');
        $heroHtml = $heroMatch[0];

        // The hero container must be a <div>, never an <a>.
        $this->assertMatchesRegularExpression(
            '/<div[^>]+class="qs-std-wallet__hero-focus[^"]*"/',
            $heroHtml,
            'The mobile wallet hero must be rendered as a non-clickable <div>.',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<a[^>]+class="qs-std-wallet__hero-focus[^"]*"/',
            $heroHtml,
            'The mobile wallet hero must NOT be wrapped in an anchor while a countdown is ticking.',
        );

        // The live clock must be present and the expired pill must be
        // rendered as an <a> (it stays hidden by CSS until the JS adds
        // .is-expired, then becomes the lone tap target).
        $this->assertStringContainsString('qs-std-wallet__hero-clock', $heroHtml, 'Hero must surface the live countdown clock.');
        $this->assertMatchesRegularExpression(
            '/<a[^>]+class="qs-std-wallet__hero-expired[^"]*"/',
            $heroHtml,
            'The post-expiry CTA must be a real <a> so it becomes the only tappable element when the timer hits 00:00:00.',
        );
    }

    public function test_mobile_wallet_hero_closed_state_renders_informational_span_not_link(): void
    {
        // The "Closed" state is informational — there is nowhere useful to
        // navigate to once the window has shut — so the post-expiry pill
        // must stay as a plain <span>, NOT an <a>. Confirms we don't tease
        // a tap target for a dead path.
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);
        $super = $this->superAdmin();

        app(SystemSettingsService::class)->set('student_dashboard_mobile_wallet', '1', $super);

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => (int) DB::table('departments')->where('code', 'CS')->value('id'),
            'code' => 'CS-CL-'.Str::random(4),
            'title' => 'Closing hero course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $classroomId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'name' => 'CL-Section',
            'program_id' => (int) DB::table('programs')->where('code', 'BCS')->value('id'),
            'level_id' => (int) DB::table('levels')->where('code', '100')->value('id'),
            'academic_year_id' => (int) DB::table('academic_years')->where('is_active', true)->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('class_course')->insert(['class_id' => $classroomId, 'course_id' => $courseId]);
        DB::table('users')->where('id', $student->id)->update(['class_id' => $classroomId]);

        DB::table('quizzes')->insert([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $super->id,
            'title' => 'Closes soon quiz',
            'description' => 'Wraps shortly.',
            'assessment_type' => 'quiz',
            'selected_question_types' => json_encode(['mcq']),
            'status' => 'published',
            'published_at' => now()->subHour(),
            // Already open, closing soon → the digest emits a "Closes in"
            // countdown which the surface maps to the closed-state pill.
            'start_time' => now()->subMinutes(20),
            'end_time' => now()->addHours(1)->addMinutes(30),
            'duration_minutes' => 30,
            'total_marks' => 10,
            'questions_per_student' => 5,
            'proctoring_settings' => json_encode(\App\Support\AssessmentProctoringDefaults::baselineForType('quiz', true, true, true)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($student->fresh());
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        preg_match('/<header class="qs-std-wallet__hero"[\s\S]*?<\/header>/', $html, $heroMatch);
        $this->assertNotEmpty($heroMatch);
        $heroHtml = $heroMatch[0];

        // The closing state must render the expired pill as a non-link
        // span — there's no productive page to send the student to.
        $this->assertStringContainsString('qs-std-wallet__hero-expired--closed', $heroHtml, 'Closing surface must use the closed state modifier.');
        $this->assertMatchesRegularExpression(
            '/<span[^>]+class="qs-std-wallet__hero-expired qs-std-wallet__hero-expired--closed"/',
            $heroHtml,
            'The closed-state pill must be a <span>, never an <a> — there is no path to navigate to once the window has shut.',
        );
    }

    /**
     * Helper: enable the wallet UI and switch to the "noir" theme.
     */
    private function enableNoirTheme(User $admin): void
    {
        $settings = app(SystemSettingsService::class);
        $settings->set('student_dashboard_mobile_wallet', '1', $admin);
        $settings->set(StudentDashboardBrandingService::WALLET_THEME_SETTING_KEY, 'noir', $admin);
    }

    public function test_noir_theme_propagates_to_every_student_page(): void
    {
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);
        $admin = $this->superAdmin();

        $this->enableNoirTheme($admin);

        // The full list of student routes that the noir theme is supposed
        // to cover. Each one must mount the wallet body class + the noir
        // theme attribute so the cross-page noir CSS selectors engage. If
        // a future page is added under the student layout, add it here so
        // we keep parity automatically.
        $routes = [
            'Dashboard'           => 'dashboard',
            'Worklist (/work)'    => 'student.work.index',
            'Assignments'         => 'student.assignments.index',
            'Results'             => 'student.results.index',
            'Notifications'       => 'student.notifications.index',
            'Help'                => 'student.help',
            'Profile'             => 'profile.edit',
        ];

        $this->actingAs($student->fresh());
        foreach ($routes as $label => $routeName) {
            $html = (string) $this->get(route($routeName))->assertOk()->getContent();

            $this->assertStringContainsString(
                'class="qs-std h-full overflow-x-hidden overflow-y-hidden bg-white text-[#101828] antialiased qs-std--wallet"',
                $html,
                $label.' must mount the wallet body class so cross-page noir styling applies.',
            );
            $this->assertStringContainsString(
                'data-qs-wallet-theme="noir"',
                $html,
                $label.' must carry the noir wallet theme attribute on <body>.',
            );
            $this->assertStringContainsString(
                'qs-app-main-scroll',
                $html,
                $label.' must render the main scroll container so noir CSS overrides have something to anchor on.',
            );
        }
    }

    public function test_noir_theme_keeps_other_themes_unaffected_when_unset(): void
    {
        // Sanity check the negative case: when the theme is NOT noir, the
        // body must not carry the noir attribute. Stops a regression
        // where someone changes the default and accidentally darkens
        // every other student.
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);
        $admin = $this->superAdmin();

        $settings = app(SystemSettingsService::class);
        $settings->set('student_dashboard_mobile_wallet', '1', $admin);
        // Don't set the noir theme — leave default (teal).

        $this->actingAs($student->fresh());
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('data-qs-wallet-theme="teal"', $html, 'Default theme must remain teal.');
        $this->assertStringNotContainsString('data-qs-wallet-theme="noir"', $html, 'Noir must not apply when another theme is selected.');
    }

    public function test_noir_css_targets_every_surface_that_appears_in_the_results_ticket_screenshot(): void
    {
        // The screenshot the user shared after the noir rollout showed the
        // /results page with white stat cards, white worklist tickets,
        // pastel type tags, and a violet "View result" button — none of
        // which had been re-themed. This test guards the compiled CSS so
        // those surfaces stay covered by the noir overrides.
        $cssPath = base_path('resources/css/student-dashboard.css');
        $css = file_get_contents($cssPath);
        $this->assertNotFalse($css, 'student-dashboard.css must be readable.');

        // Quick helper: every selector must be scoped under the noir
        // body attribute so we don't risk leaking dark styles onto the
        // other four light themes.
        $assertNoirCovers = function (string $selector) use ($css) {
            $needle = 'body.qs-std--wallet[data-qs-wallet-theme="noir"] '.$selector;
            $this->assertStringContainsString(
                $needle,
                $css,
                'Noir CSS must explicitly cover '.$selector.' so it doesn\'t stay light on the dark page.',
            );
        };

        // Stat tiles on the /results overview (the white cards in the
        // screenshot).
        $assertNoirCovers('.qs-stat-card');
        $assertNoirCovers('.qs-stat-card__value');
        $assertNoirCovers('.qs-stat-card__label');

        // Result tickets (.qs-wl-item) + their inner text + per-bucket
        // type-tag chips that drove the pastel "QUIZ" / "ASSIGNMENT" pills.
        $assertNoirCovers('.qs-wl-item');
        $assertNoirCovers('.qs-wl-item__title');
        $assertNoirCovers('.qs-wl-item__sub');
        $assertNoirCovers('.qs-wl-pill');
        $assertNoirCovers('.qs-type-tag--quiz');
        $assertNoirCovers('.qs-type-tag--assignment');

        // The violet "View result" button — must be flipped to the cyan
        // accent on noir instead of inheriting the bucket purple.
        $assertNoirCovers('.qs-wl-action--primary');

        // Help-page body copy + bullet lists hard-code a dark slate
        // (#475569) that disappears against the matte black page. Both
        // must be re-painted by the noir CSS.
        $assertNoirCovers('.qs-help-item__body');
        $assertNoirCovers('.qs-help-item__bullets');

        // Worklist outer panel + its header strip (eyebrow, title,
        // avatar chip, "All assignments" CTA) on /work — previously
        // the whole container stayed a bright-white card on the matte
        // black page, which made the "You are all caught up" title
        // invisible (light-on-light).
        $assertNoirCovers('.qs-wl-panel');
        $assertNoirCovers('.qs-wl-panel__head');
        $assertNoirCovers('.qs-wl-panel__title');
        $assertNoirCovers('.qs-wl-panel__cta');
        $assertNoirCovers('.qs-wl-empty__sub');

        // Mobile wallet bottom nav — the round FAB icon and the inactive
        // tab labels were getting their colour clobbered by the generic
        // noir "every <a> is cyan" rule, which made the FAB icon match
        // the FAB background (cyan-on-cyan) and the inactive tabs read
        // as bright cyan instead of muted off-white.
        $assertNoirCovers('.qs-std-wallet__nav-fab');
        $assertNoirCovers('.qs-std-wallet__nav-item:not(.is-active)');

        // And the exclusion list on the generic <a> rule must list the
        // wallet nav surfaces so this can't regress again.
        $css = file_get_contents(base_path('resources/css/student-dashboard.css'));
        $this->assertStringContainsString(
            ':not([class*="qs-std-wallet__nav"])',
            $css,
            'The generic noir link colouring rule must exclude wallet nav surfaces to keep the FAB icon visible.',
        );
    }

    public function test_light_themes_propagate_their_accent_to_primary_buttons_and_worklist_actions(): void
    {
        // The four non-noir themes (teal / forest / indigo / coral) used
        // to only paint the wallet hero — every other student page kept
        // the default neutral chrome. This test guards the rule-set that
        // lifts --w-hero onto the primary worklist action + .qs-std-btn--primary
        // so the chosen theme actually shows up across the app.
        $cssPath = base_path('resources/css/student-dashboard.css');
        $css = file_get_contents($cssPath);
        $this->assertNotFalse($css, 'student-dashboard.css must be readable.');

        // The :not([data-qs-wallet-theme="noir"]) guard keeps the cyan
        // accent on the noir variant — only the four light themes pick
        // up the cross-page accent propagation.
        $this->assertStringContainsString(
            'body.qs-std--wallet:not([data-qs-wallet-theme="noir"]) .qs-wl-action--primary',
            $css,
            'Light themes must propagate their accent to primary worklist actions.',
        );
        $this->assertStringContainsString(
            'body.qs-std--wallet:not([data-qs-wallet-theme="noir"]) .qs-std-btn--primary',
            $css,
            'Light themes must propagate their accent to the .qs-std-btn--primary surface.',
        );
        // Both rule-sets must use the wallet token (not a hard-coded hex)
        // so each light theme picks up the colour from its own palette.
        $this->assertStringContainsString('background: var(--w-hero', $css, 'Primary worklist action must read the wallet hero token.');
    }

    public function test_noir_theme_does_not_render_attribute_when_wallet_disabled(): void
    {
        // If the wallet UI is OFF entirely, the body must not carry the
        // theme attribute — the noir CSS would never match anyway, but
        // omitting the attribute keeps the DOM clean for the non-wallet
        // student experience.
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);

        // Don't enable the wallet at all.
        $this->actingAs($student->fresh());
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-qs-wallet-theme', $html, 'Theme attribute must be omitted when wallet UI is off.');
        $this->assertStringNotContainsString('qs-std--wallet', $html, 'Wallet body class must be omitted when wallet UI is off.');
    }

    public function test_desktop_hero_countdown_card_is_not_clickable_at_the_container_level(): void
    {
        // Mirror of the mobile rule for the desktop ticket card. The card
        // chrome (chip, course slot, type slot, status row, clock segments)
        // must stay informational; the only <a> inside the card should be
        // the post-expiry CTA pill (or the permanent Start pill when there
        // is no countdown).
        $uniId = $this->seedUniversity();
        $student = $this->student($uniId);
        $super = $this->superAdmin();

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => (int) DB::table('departments')->where('code', 'CS')->value('id'),
            'code' => 'CS-DKT-'.Str::random(4),
            'title' => 'Desktop ticket course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $classroomId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'name' => 'DKT-Section',
            'program_id' => (int) DB::table('programs')->where('code', 'BCS')->value('id'),
            'level_id' => (int) DB::table('levels')->where('code', '100')->value('id'),
            'academic_year_id' => (int) DB::table('academic_years')->where('is_active', true)->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('class_course')->insert(['class_id' => $classroomId, 'course_id' => $courseId]);
        DB::table('users')->where('id', $student->id)->update(['class_id' => $classroomId]);

        DB::table('quizzes')->insert([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $super->id,
            'title' => 'Desktop ticket upcoming exam',
            'description' => 'Soon.',
            'assessment_type' => 'exam',
            'selected_question_types' => json_encode(['mcq']),
            'status' => 'published',
            'published_at' => now()->subMinutes(15),
            'start_time' => now()->addHours(3),
            'end_time' => now()->addDays(3),
            'duration_minutes' => 60,
            'total_marks' => 50,
            'questions_per_student' => 10,
            'proctoring_settings' => json_encode(\App\Support\AssessmentProctoringDefaults::baselineForType('exam', true, true, true)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($student->fresh());
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        // Isolate the desktop hero card so we don't pick up any unrelated
        // links elsewhere on the page.
        preg_match('/<section class="qs-std-hero-countdown[\s\S]*?<\/section>/', $html, $cardMatch);
        $this->assertNotEmpty($cardMatch, 'Desktop hero countdown section must render.');
        $cardHtml = $cardMatch[0];

        // The card itself must be a <div>, never an <a>.
        $this->assertMatchesRegularExpression(
            '/<div[^>]+class="qs-std-hero-countdown__card"/',
            $cardHtml,
            'The desktop hero countdown card must be a non-clickable <div>.',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<a[^>]+class="qs-std-hero-countdown__card"/',
            $cardHtml,
            'The desktop hero countdown card must NOT be wrapped in an anchor.',
        );

        // The post-expiry CTA must still be a real <a>, so when the JS
        // reveals it the student has a single, clear tap target.
        $this->assertMatchesRegularExpression(
            '/<a[^>]+class="qs-std-hero-countdown__expired[^"]*"/',
            $cardHtml,
            'The desktop expired CTA must be a real <a> so it becomes the only tappable element on expiry.',
        );
    }
}
