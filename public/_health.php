<?php
/**
 * QUIZSNAP — no-SSH diagnostic + repair console for cPanel.
 *
 * Usage:
 *   1. EDIT the $TOKEN constant below to a long random string of your choice.
 *   2. Upload public/_health.php to /home/neckpre1/fada/public/ via cPanel
 *      File Manager (or `git pull` if your cPanel supports Git deployment).
 *   3. Visit https://fada.neckpressing.com/_health.php?token=YOUR_TOKEN
 *   4. Use the buttons to clear caches, run migrations, generate APP_KEY, etc.
 *   5. ★ DELETE this file after the site is healthy.
 *
 * SECURITY:
 *   - Refuses to run unless ?token=… matches $TOKEN below.
 *   - Refuses to run if $TOKEN is left at the default value.
 *   - Never prints database credentials, OTP codes, or session contents.
 */

declare(strict_types=1);

// ─────────────────────────────────────────────────────────────────────
//   ✏️  STEP 1 — Set this to a long random secret (16+ chars, letters
//       and digits only).  Re-upload after editing.
//
//   The unconfigured-check below uses a SHA1 hash of the placeholder
//   so a global "Replace All" in your editor cannot accidentally
//   replace both the constant value AND the comparison string.
// ─────────────────────────────────────────────────────────────────────
const TOKEN = 'CHANGE_ME_TO_A_LONG_RANDOM_STRING';
// ─────────────────────────────────────────────────────────────────────

// 75aeb3da64dd8e1db335bd609f123b617b1b0432 == sha1('CHANGE_ME_TO_A_LONG_RANDOM_STRING')
if (sha1(TOKEN) === '75aeb3da64dd8e1db335bd609f123b617b1b0432' || strlen(TOKEN) < 16) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "_health.php is unconfigured. Edit the TOKEN constant first.";
    exit;
}

$provided = (string) ($_GET['token'] ?? '');
if (! hash_equals(TOKEN, $provided)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'forbidden';
    exit;
}

// ── Path helpers ─────────────────────────────────────────────────────
$root = realpath(__DIR__ . '/..');
chdir($root);

// ── Action dispatcher (safe artisan-only operations) ─────────────────
$action = (string) ($_GET['action'] ?? '');
$results = [];

if ($action !== '') {
    $results[] = run_action($action, $root);
}

// ── Collect diagnostics (always) ─────────────────────────────────────
$diag = collect_diagnostics($root);
render_page($diag, $results);

// ─────────────────────────────────────────────────────────────────────
//   Helpers
// ─────────────────────────────────────────────────────────────────────

/**
 * @return array{title: string, lines: array<int, array{tone: string, text: string}>}
 */
function run_action(string $action, string $root): array
{
    $lines = [];
    $title = "Action: {$action}";

    switch ($action) {
        case 'tail_log':
            $logs = glob($root . '/storage/logs/laravel-*.log');
            usort($logs, fn ($a, $b) => filemtime($b) <=> filemtime($a));
            $log = $logs[0] ?? ($root . '/storage/logs/laravel.log');
            if (! is_file($log)) {
                $lines[] = ['tone' => 'warn', 'text' => 'No laravel-*.log file yet.'];
            } else {
                $contents = (string) @file_get_contents($log);
                $tail = array_slice(explode("\n", trim($contents)), -80);
                foreach ($tail as $row) {
                    $lines[] = ['tone' => 'log', 'text' => $row];
                }
            }
            break;

        case 'fix_permissions':
            $dirs = [
                'storage',
                'storage/framework',
                'storage/framework/cache',
                'storage/framework/cache/data',
                'storage/framework/sessions',
                'storage/framework/views',
                'storage/framework/testing',
                'storage/logs',
                'bootstrap/cache',
            ];
            foreach ($dirs as $rel) {
                $abs = $root . '/' . $rel;
                if (! is_dir($abs)) {
                    @mkdir($abs, 0755, true);
                    $lines[] = ['tone' => 'ok', 'text' => "created $rel"];
                }
                @chmod($abs, 0755);
            }
            $lines[] = ['tone' => 'ok', 'text' => 'storage/ + bootstrap/cache permissions normalised.'];
            break;

        case 'create_env':
            if (is_file($root . '/.env')) {
                $lines[] = ['tone' => 'warn', 'text' => '.env already exists — not overwriting.'];
                break;
            }
            $tpl = $root . '/.env.production.example';
            if (! is_file($tpl)) {
                $lines[] = ['tone' => 'fail', 'text' => '.env.production.example missing — cannot bootstrap.'];
                break;
            }
            if (@copy($tpl, $root . '/.env')) {
                @chmod($root . '/.env', 0644);
                $lines[] = ['tone' => 'ok', 'text' => '.env created from .env.production.example.'];
                $lines[] = ['tone' => 'warn', 'text' => 'Edit .env via File Manager: set APP_URL, DB_DATABASE, DB_USERNAME, DB_PASSWORD.'];
            } else {
                $lines[] = ['tone' => 'fail', 'text' => 'Could not copy template — check permissions on the project root.'];
            }
            break;

        case 'strip_redis':
            $envPath = $root . '/.env';
            if (! is_file($envPath)) {
                $lines[] = ['tone' => 'fail', 'text' => '.env missing.'];
                break;
            }
            $env = (string) file_get_contents($envPath);
            $env = preg_replace('/^CACHE_STORE=redis$/m', 'CACHE_STORE=file', $env);
            $env = preg_replace('/^SESSION_DRIVER=redis$/m', 'SESSION_DRIVER=file', $env);
            $env = preg_replace('/^QUEUE_CONNECTION=redis$/m', 'QUEUE_CONNECTION=database', $env);
            $env = preg_replace('/^BROADCAST_CONNECTION=redis$/m', 'BROADCAST_CONNECTION=null', $env);
            $env = preg_replace('/^REDIS_[A-Z_]+=.*$\n?/m', '', (string) $env);
            $env = preg_replace('/^REVERB_SCALING_[A-Z_]+=.*$\n?/m', '', (string) $env);
            file_put_contents($envPath, $env);
            $lines[] = ['tone' => 'ok', 'text' => 'Redis driver values cleared from .env (CACHE_STORE / SESSION_DRIVER / QUEUE_CONNECTION / BROADCAST_CONNECTION).'];
            $lines[] = ['tone' => 'ok', 'text' => 'REDIS_* / REVERB_SCALING_* lines removed.'];
            break;

        case 'toggle_debug':
            $envPath = $root . '/.env';
            if (! is_file($envPath)) {
                $lines[] = ['tone' => 'fail', 'text' => '.env missing.'];
                break;
            }
            $env = (string) file_get_contents($envPath);
            if (preg_match('/^APP_DEBUG=true$/m', $env)) {
                $env = preg_replace('/^APP_DEBUG=true$/m', 'APP_DEBUG=false', $env);
                $msg = 'APP_DEBUG=false (production mode).';
            } else {
                $env = preg_replace('/^APP_DEBUG=.*$/m', 'APP_DEBUG=true', $env);
                $msg = 'APP_DEBUG=true (refresh the broken page to see the full stack trace).';
            }
            file_put_contents($envPath, $env);
            $lines[] = ['tone' => 'ok', 'text' => $msg];
            // Always nuke the cached config after toggling.
            run_artisan_silent($root, 'config:clear', $lines);
            break;

        case 'config_clear':
            run_artisan_silent($root, 'config:clear', $lines);
            run_artisan_silent($root, 'cache:clear', $lines);
            run_artisan_silent($root, 'view:clear', $lines);
            run_artisan_silent($root, 'route:clear', $lines);
            break;

        case 'config_cache':
            run_artisan_silent($root, 'config:cache', $lines);
            run_artisan_silent($root, 'route:cache', $lines);
            run_artisan_silent($root, 'view:cache', $lines);
            break;

        case 'key_generate':
            run_artisan_silent($root, 'key:generate', $lines, ['--force' => true]);
            run_artisan_silent($root, 'config:clear', $lines);
            break;

        case 'migrate':
            run_artisan_silent($root, 'migrate', $lines, ['--force' => true]);
            break;

        case 'storage_link':
            run_artisan_silent($root, 'storage:link', $lines);
            break;

        case 'self_destruct':
            $lines[] = ['tone' => 'ok', 'text' => 'Goodbye 👋  This file has been removed.'];
            @unlink(__FILE__);
            break;

        default:
            $lines[] = ['tone' => 'fail', 'text' => "Unknown action: {$action}"];
    }

    return ['title' => $title, 'lines' => $lines];
}

/**
 * @param array<int, array{tone: string, text: string}> $lines
 * @param array<string, mixed> $args
 */
function run_artisan_silent(string $root, string $command, array &$lines, array $args = []): void
{
    if (! is_file($root . '/vendor/autoload.php')) {
        $lines[] = ['tone' => 'fail', 'text' => "Cannot run `{$command}`: vendor/ is missing. Upload vendor.zip and extract it first."];
        return;
    }

    try {
        require_once $root . '/vendor/autoload.php';
        if (! class_exists(\Illuminate\Foundation\Application::class)) {
            $lines[] = ['tone' => 'fail', 'text' => "Cannot run `{$command}`: Laravel autoload failed."];
            return;
        }

        /** @var \Illuminate\Foundation\Application $app */
        $app = require $root . '/bootstrap/app.php';
        /** @var \Illuminate\Contracts\Console\Kernel $kernel */
        $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $exit = $kernel->call($command, $args, $output);
        $tone = $exit === 0 ? 'ok' : 'fail';
        $lines[] = ['tone' => $tone, 'text' => "artisan {$command} → exit={$exit}"];
        $body = trim($output->fetch());
        foreach (explode("\n", $body) as $row) {
            if (trim($row) === '') {
                continue;
            }
            $lines[] = ['tone' => 'log', 'text' => '   '.$row];
        }
    } catch (\Throwable $e) {
        $lines[] = ['tone' => 'fail', 'text' => "artisan {$command} threw: ".$e->getMessage()];
    }
}

/**
 * @return array<string, mixed>
 */
function collect_diagnostics(string $root): array
{
    $php = PHP_VERSION;
    $phpOk = version_compare($php, '8.3.0', '>=');

    $exts = ['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'fileinfo', 'bcmath', 'gd', 'curl', 'zip'];
    $extStatus = [];
    foreach ($exts as $ext) {
        $extStatus[$ext] = extension_loaded($ext);
    }

    $envExists = is_file($root . '/.env');
    $appKeySet = false;
    $appDebug = '?';
    $appEnv = '?';
    $hasRedis = false;

    if ($envExists) {
        $env = (string) file_get_contents($root . '/.env');
        $appKeySet = (bool) preg_match('/^APP_KEY=base64:.{40,}/m', $env);
        if (preg_match('/^APP_DEBUG=(\S+)/m', $env, $m)) {
            $appDebug = $m[1];
        }
        if (preg_match('/^APP_ENV=(\S+)/m', $env, $m)) {
            $appEnv = $m[1];
        }
        $hasRedis = (bool) preg_match('/^(CACHE_STORE|SESSION_DRIVER|QUEUE_CONNECTION|BROADCAST_CONNECTION)=redis$/m', $env);
    }

    $vendor = is_file($root . '/vendor/autoload.php');
    $manifest = is_file($root . '/public/build/manifest.json');

    $writableDirs = [
        'storage',
        'storage/framework',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/framework/cache',
        'storage/logs',
        'bootstrap/cache',
    ];
    $writeStatus = [];
    foreach ($writableDirs as $rel) {
        $abs = $root . '/' . $rel;
        if (! is_dir($abs)) {
            $writeStatus[$rel] = 'missing';
        } elseif (! is_writable($abs)) {
            $writeStatus[$rel] = 'not_writable';
        } else {
            $writeStatus[$rel] = 'ok';
        }
    }

    return [
        'php_version' => $php,
        'php_ok' => $phpOk,
        'extensions' => $extStatus,
        'env_exists' => $envExists,
        'app_key_set' => $appKeySet,
        'app_debug' => $appDebug,
        'app_env' => $appEnv,
        'has_redis' => $hasRedis,
        'vendor_present' => $vendor,
        'vite_manifest' => $manifest,
        'writable_dirs' => $writeStatus,
        'project_root' => $root,
    ];
}

/**
 * @param array<string, mixed> $diag
 * @param array<int, array{title: string, lines: array<int, array{tone: string, text: string}>}> $results
 */
function render_page(array $diag, array $results): void
{
    $token = TOKEN;
    $url = strtok((string) $_SERVER['REQUEST_URI'], '?') . '?token=' . urlencode($token);
    $btn = function (string $action, string $label, string $tone = 'primary') use ($url): string {
        $cls = htmlspecialchars($tone, ENT_QUOTES);
        $a = htmlspecialchars($action, ENT_QUOTES);
        $l = htmlspecialchars($label, ENT_QUOTES);
        return "<a class=\"btn btn-{$cls}\" href=\"{$url}&action={$a}\">{$l}</a>";
    };

    header('Content-Type: text/html; charset=utf-8');
    ?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8">
<title>QUIZSNAP — health console</title>
<style>
:root { --bg:#0f172a; --panel:#1e293b; --line:#334155; --muted:#94a3b8; --ok:#34d399; --warn:#fbbf24; --fail:#f87171; --info:#60a5fa; --text:#e2e8f0; }
* { box-sizing: border-box; }
body { margin:0; font:14px/1.5 ui-sans-serif,system-ui,sans-serif; background:var(--bg); color:var(--text); }
.wrap { max-width: 1100px; margin: 0 auto; padding: 24px; }
h1 { font-size:22px; margin:0 0 4px; }
h2 { font-size:14px; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); margin:24px 0 8px; }
.panel { background:var(--panel); border:1px solid var(--line); border-radius:12px; padding:16px 20px; margin:0 0 16px; }
.row { display:grid; grid-template-columns: 220px 1fr; gap:8px 12px; align-items:center; padding:6px 0; border-top:1px solid var(--line); }
.row:first-of-type { border-top:0; }
.k { color:var(--muted); }
.pill { display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; }
.pill.ok { background:rgba(52,211,153,.15); color:var(--ok); }
.pill.warn { background:rgba(251,191,36,.15); color:var(--warn); }
.pill.fail { background:rgba(248,113,113,.15); color:var(--fail); }
.pill.muted { background:rgba(148,163,184,.15); color:var(--muted); }
.btn { display:inline-block; padding:7px 12px; border-radius:8px; text-decoration:none; font-weight:500; font-size:13px; margin:4px 6px 4px 0; border:1px solid transparent; }
.btn-primary { background:#2563eb; color:#fff; }
.btn-warn    { background:#d97706; color:#fff; }
.btn-danger  { background:#dc2626; color:#fff; }
.btn-muted   { background:transparent; border-color:var(--line); color:var(--text); }
.code { font-family: ui-monospace, SFMono-Regular, monospace; font-size:12.5px; background:#0a1222; border:1px solid var(--line); padding:10px 12px; border-radius:8px; white-space:pre-wrap; max-height:520px; overflow:auto; }
.line-ok { color:var(--ok); }
.line-warn { color:var(--warn); }
.line-fail { color:var(--fail); }
.line-log { color:var(--muted); }
.banner { padding:14px 16px; border-radius:10px; margin-bottom:16px; font-weight:500; }
.banner.bad { background:rgba(248,113,113,.12); border:1px solid rgba(248,113,113,.4); color:var(--fail); }
.banner.good { background:rgba(52,211,153,.10); border:1px solid rgba(52,211,153,.4); color:var(--ok); }
.muted { color:var(--muted); }
</style>
</head><body><div class="wrap">

<h1>QUIZSNAP — health console</h1>
<p class="muted">Project root: <?=htmlspecialchars((string) $diag['project_root'])?></p>

<?php if (! empty($results)): ?>
    <?php foreach ($results as $r): ?>
        <div class="panel">
            <h2>▶︎ <?=htmlspecialchars($r['title'])?></h2>
            <div class="code"><?php foreach ($r['lines'] as $row): ?><div class="line-<?=htmlspecialchars($row['tone'])?>"><?=htmlspecialchars($row['text'])?></div><?php endforeach; ?></div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php
$blockers = [];
if (! $diag['php_ok'])           { $blockers[] = "PHP {$diag['php_version']} is too old — switch to 8.3+ in cPanel → MultiPHP Manager."; }
foreach ($diag['extensions'] as $ext => $ok) { if (! $ok) { $blockers[] = "PHP extension `$ext` missing — enable in cPanel → Select PHP Version."; } }
if (! $diag['vendor_present'])   { $blockers[] = "vendor/ missing — upload vendor.zip via File Manager and extract it inside the project root."; }
if (! $diag['env_exists'])       { $blockers[] = ".env missing — click \"Create .env from template\" below."; }
if ($diag['env_exists'] && ! $diag['app_key_set']) { $blockers[] = "APP_KEY blank — click \"Generate APP_KEY\"."; }
foreach ($diag['writable_dirs'] as $rel => $st) { if ($st !== 'ok') { $blockers[] = "$rel is $st — click \"Fix permissions\"."; } }
if ($diag['has_redis'])          { $blockers[] = "Redis driver values still in .env — click \"Strip Redis from .env\"."; }
?>

<?php if ($blockers): ?>
<div class="banner bad">
    <strong>Likely cause of the 500:</strong>
    <ul style="margin:6px 0 0 18px;">
        <?php foreach ($blockers as $b): ?><li><?=htmlspecialchars($b)?></li><?php endforeach; ?>
    </ul>
</div>
<?php else: ?>
<div class="banner good">All preflight checks pass. If the site is still 500ing, click <strong>Show last log</strong> to read the runtime error.</div>
<?php endif; ?>

<h2>System</h2>
<div class="panel">
    <div class="row"><span class="k">PHP version</span><span><span class="pill <?=$diag['php_ok']?'ok':'fail'?>"><?=htmlspecialchars($diag['php_version'])?></span> <span class="muted">(needs 8.3+)</span></span></div>
    <?php foreach ($diag['extensions'] as $ext => $ok): ?>
        <div class="row"><span class="k">ext: <?=htmlspecialchars($ext)?></span><span class="pill <?=$ok?'ok':'fail'?>"><?=$ok?'loaded':'missing'?></span></div>
    <?php endforeach; ?>
</div>

<h2>App</h2>
<div class="panel">
    <div class="row"><span class="k">.env file</span><span class="pill <?=$diag['env_exists']?'ok':'fail'?>"><?=$diag['env_exists']?'present':'missing'?></span></div>
    <div class="row"><span class="k">APP_KEY</span><span class="pill <?=$diag['app_key_set']?'ok':'fail'?>"><?=$diag['app_key_set']?'set':'BLANK'?></span></div>
    <div class="row"><span class="k">APP_ENV</span><span class="pill muted"><?=htmlspecialchars((string) $diag['app_env'])?></span></div>
    <div class="row"><span class="k">APP_DEBUG</span><span class="pill <?=$diag['app_debug']==='true'?'warn':'muted'?>"><?=htmlspecialchars((string) $diag['app_debug'])?></span></div>
    <div class="row"><span class="k">Redis values in .env</span><span class="pill <?=$diag['has_redis']?'fail':'ok'?>"><?=$diag['has_redis']?'present':'clean'?></span></div>
    <div class="row"><span class="k">vendor/autoload.php</span><span class="pill <?=$diag['vendor_present']?'ok':'fail'?>"><?=$diag['vendor_present']?'present':'MISSING'?></span></div>
    <div class="row"><span class="k">public/build/manifest.json</span><span class="pill <?=$diag['vite_manifest']?'ok':'warn'?>"><?=$diag['vite_manifest']?'present':'missing'?></span></div>
</div>

<h2>Writable directories</h2>
<div class="panel">
    <?php foreach ($diag['writable_dirs'] as $rel => $st): ?>
        <div class="row"><span class="k"><?=htmlspecialchars($rel)?></span><span class="pill <?= $st==='ok'?'ok':'fail' ?>"><?=htmlspecialchars($st)?></span></div>
    <?php endforeach; ?>
</div>

<h2>Repair actions</h2>
<div class="panel">
    <div style="margin-bottom:6px;"><strong>Read what's actually failing:</strong></div>
    <?=$btn('tail_log', 'Show last 80 log lines', 'primary')?>
    <?=$btn('toggle_debug', 'Toggle APP_DEBUG (browser-visible stack trace)', 'warn')?>

    <div style="margin:14px 0 6px;"><strong>Bootstrap:</strong></div>
    <?=$btn('create_env', 'Create .env from template', 'primary')?>
    <?=$btn('key_generate', 'Generate APP_KEY', 'primary')?>
    <?=$btn('strip_redis', 'Strip Redis from .env', 'primary')?>
    <?=$btn('fix_permissions', 'Fix storage/ + bootstrap/cache permissions', 'primary')?>

    <div style="margin:14px 0 6px;"><strong>Caches & migrations:</strong></div>
    <?=$btn('config_clear', 'Clear all caches', 'primary')?>
    <?=$btn('migrate', 'php artisan migrate --force', 'warn')?>
    <?=$btn('config_cache', 'Rebuild production caches', 'primary')?>
    <?=$btn('storage_link', 'php artisan storage:link', 'muted')?>

    <div style="margin:14px 0 6px;"><strong>When the site is healthy:</strong></div>
    <?=$btn('self_destruct', '🗑  Delete _health.php', 'danger')?>
</div>

<p class="muted" style="margin-top:24px;">
    ⚠️ This file accepts only the token configured at the top of <code>public/_health.php</code>.
    <strong>Delete it as soon as the site is healthy.</strong>
</p>

</div></body></html><?php
}
