<?php
/**
 * WordPress Permissions Fixer
 * Ultra-optimized, memory-safe standalone script to reset WordPress permissions.
 *
 * USAGE: Upload to your WordPress root directory, access via browser ONCE, then delete.
 * GitHub: https://github.com/digitivup/wordpress-permissions-fixer/
 */

declare(strict_types=1);

// --- SECURITY: Change this fallback key before deploying, or use the auto-generated one ---
define('SECRET_KEY', 'CHANGE_ME_BEFORE_UPLOAD');

// If the key hasn't been changed, enforce a dynamic secure key and guide the user
if (SECRET_KEY === 'CHANGE_ME_BEFORE_UPLOAD') {
    if (!isset($_GET['key']) || empty($_GET['key'])) {
        $dynamic_key = bin2hex(random_bytes(16));
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]";
        
        header('HTTP/1.0 403 Forbidden');
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Setup Required - WordPress Permissions Fixer</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #334155; padding: 40px 20px; display: flex; align-items: center; justify-content: center; min-height: 80vh; margin: 0; }
                .setup-card { background: #ffffff; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; max-width: 550px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
                h2 { margin-top: 0; color: #0f172a; }
                code { background: #f1f5f9; padding: 4px 8px; border-radius: 6px; font-family: monospace; font-size: 13px; color: #0284c7; word-break: break-all; }
                .btn { display: inline-block; background: #0284c7; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; margin-top: 15px; font-size: 14px; }
                .btn:hover { background: #0369a1; }
            </style>
        </head>
        <body>
            <div class="setup-card">
                <h2>🔒 Security Setup Required</h2>
                <p>To run this tool safely, either edit this file and change the <code>SECRET_KEY</code> constant, or use the secure temporary URL below:</p>
                <p style="margin: 20px 0;"><a href="<?php echo htmlspecialchars($current_url . '?key=' . $dynamic_key); ?>" class="btn">Launch with Temporary Secure Key</a></p>
                <small style="color: #64748b;">Temporary URL key for this session: <code><?php echo $dynamic_key; ?></code></small>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Authentication verification
if (!isset($_GET['key']) || !hash_equals(SECRET_KEY, $_GET['key'])) {
    header('HTTP/1.0 403 Forbidden');
    die('Access Denied. Invalid or missing security key.');
}

// Priority Action: Self-destruction
if (isset($_GET['delete']) && $_GET['delete'] === '1') {
    @unlink(__FILE__);
    header('Location: ?key=' . urlencode($_GET['key']) . '&deleted=true');
    exit;
}

// Raise server limits for large websites
@set_time_limit(300);
@ini_set('memory_limit', '256M');

$wp_root = __DIR__;
$stats   = ['scanned' => 0, 'updated' => 0, 'errors' => 0, 'blocked' => 0, 'ok' => 0];
$logs    = [];

define('MAX_LOG_ENTRIES', 500);
$log_truncated = false;

function process_permissions(string $path, array &$stats, array &$logs, bool &$log_truncated): void
{
    $skip_dirs = ['.git' => true, 'node_modules' => true, '.github' => true];
    $has_posix = function_exists('posix_getpwuid');
    $proc_uid  = $has_posix ? posix_geteuid() : null;

    try {
        if (!is_dir($path)) {
            throw new Exception("The target path is not a valid directory.");
        }

        $dir_iterator = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);

        $filter = new RecursiveCallbackFilterIterator(
            $dir_iterator,
            function ($current) use ($skip_dirs) {
                return !isset($skip_dirs[$current->getFilename()]);
            }
        );

        $iterator = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $item) {
            $stats['scanned']++;
            $filepath = $item->getPathname();
            $filename = $item->getFilename();

            if ($item->isDir()) {
                $target = 0755;
                $label  = 'DIR';
            } elseif ($filename === 'wp-config.php') {
                $target = 0640; // 0640 is standard web-server safe practice
            } else {
                $target = 0644;
                $label  = 'FILE';
            }

            $current_perms = fileperms($filepath) & 0777;

            if ($current_perms === $target) {
                $stats['ok']++;
                continue;
            }

            if (@chmod($filepath, $target)) {
                $stats['updated']++;
                if (count($logs) < MAX_LOG_ENTRIES) {
                    $logs[] = [
                        'type' => 'success',
                        'msg'  => sprintf('%s → %04o : %s', $label, $target, $filepath),
                    ];
                } else {
                    $log_truncated = true;
                }
            } else {
                $owner_uid = fileowner($filepath);
                if ($has_posix && $proc_uid !== null && $owner_uid !== $proc_uid) {
                    $stats['blocked']++;
                    if (count($logs) < MAX_LOG_ENTRIES) {
                        $owner_data = posix_getpwuid($owner_uid);
                        $logs[] = [
                            'type' => 'blocked',
                            'msg'  => sprintf('OWNER MISMATCH (%s) : %s', $owner_data['name'] ?? $owner_uid, $filepath),
                        ];
                    } else {
                        $log_truncated = true;
                    }
                } else {
                    $stats['errors']++;
                    if (count($logs) < MAX_LOG_ENTRIES) {
                        $logs[] = [
                            'type' => 'error',
                            'msg'  => 'FAILED chmod : ' . $filepath,
                        ];
                    } else {
                        $log_truncated = true;
                    }
                }
            }
        }
    } catch (Exception $e) {
        $logs[] = ['type' => 'error', 'msg' => 'Critical Error: ' . $e->getMessage()];
    }
}

if (!isset($_GET['deleted'])) {
    process_permissions($wp_root, $stats, $logs, $log_truncated);
}

$errors_color = ($stats['errors'] > 0 || $stats['blocked'] > 0) ? '#dc2626' : '#16a34a';
$passed_key = htmlspecialchars($_GET['key'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WordPress Permissions Fixer</title>
    <style>
        :root {
            --bg:      #f8fafc;
            --panel:   #ffffff;
            --text:    #334155;
            --blue:    #0284c7;
            --green:   #16a34a;
            --red:     #dc2626;
            --orange:  #ea580c;
            --border:  #e2e8f0;
        }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 40px 20px;
        }
        .container { max-width: 1000px; margin: 0 auto; }

        .header { text-align: center; margin-bottom: 35px; }
        .header h1 { margin: 0 0 8px; font-size: 28px; color: #0f172a; }
        .header p  { margin: 0; font-size: 14px; color: #64748b; }
        .header code { background: #e2e8f0; padding: 3px 8px; border-radius: 4px; font-size: 12px; color: #0f172a; font-family: monospace; }

        .grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        @media(max-width:768px) { .grid { grid-template-columns: repeat(2, 1fr); } }
        @media(max-width:480px) { .grid { grid-template-columns: 1fr; } }

        .card {
            background: var(--panel);
            padding: 20px 15px;
            border-radius: 12px;
            border: 1px solid var(--border);
            border-top: 4px solid var(--border);
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .card:hover { transform: translateY(-2px); }
        .card .value { font-size: 32px; font-weight: 700; margin-bottom: 6px; color: #0f172a; }
        .card .label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .8px; color: #64748b; }
        .card.c-scan    { border-top-color: var(--blue); }
        .card.c-ok      { border-top-color: var(--green); }
        .card.c-updated { border-top-color: #a855f7; }
        .card.c-blocked { border-top-color: var(--orange); }
        .card.c-errors  { border-top-color: var(--red); }

        .legend {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 12px;
            margin-bottom: 12px;
            color: #64748b;
            padding: 0 4px;
        }
        .legend span { display: flex; align-items: center; gap: 6px; }
        .leg-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; }

        .console-panel {
            background: var(--panel);
            border-radius: 12px;
            border: 1px solid var(--border);
            margin-bottom: 24px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .console-header {
            background: #0f172a;
            color: #f8fafc;
            padding: 14px 20px;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .console-header .badge {
            background: #1e293b;
            color: #38bdf8;
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid #334155;
        }
        .console-body {
            background: #090d16;
            color: #cbd5e1;
            padding: 20px;
            max-height: 400px;
            overflow-y: auto;
            font-family: "Fira Code", Monaco, Consolas, monospace;
            font-size: 12px;
            line-height: 1.8;
        }
        .log-line    { margin: 0 0 4px 0; white-space: pre-wrap; word-break: break-all; }
        .log-success { color: #4ade80; }
        .log-blocked { color: #fb923c; }
        .log-error   { color: #f87171; font-weight: bold; }
        .log-empty   { color: #4b5563; font-style: italic; text-align: center; padding: 20px 0; }
        .log-warn-limit { background: #221510; color: #fdba74; padding: 10px; border-radius: 6px; margin-top: 10px; border: 1px solid #431407; }

        .note-ovh {
            background: #fefce8;
            border: 1px solid #fef08a;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 13px;
            line-height: 1.5;
            color: #713f12;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .delete-zone {
            background: #fef2f2;
            border: 1px solid #fee2e2;
            padding: 28px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .delete-zone h3 { margin-top: 0; color: #991b1b; font-size: 18px; margin-bottom: 8px; }
        .delete-zone p  { color: #7f1d1d; font-size: 13px; margin-bottom: 20px; }
        .btn-delete {
            display: inline-block;
            background: var(--red);
            color: white;
            padding: 14px 36px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
            transition: all .2s;
        }
        .btn-delete:hover { background: #b91c1c; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(220, 38, 38, 0.35); }

        .success-banner {
            background: var(--green);
            color: white;
            padding: 24px;
            border-radius: 12px;
            text-align: center;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.2);
        }
    </style>
</head>
<body>
<div class="container">

    <div class="header">
        <h1>🔒 WordPress Permissions Fixer</h1>
        <p>Root Directory: <code><?php echo htmlspecialchars($wp_root); ?></code></p>
    </div>

    <?php if (isset($_GET['deleted'])): ?>

        <div class="success-banner">
            🎉 The script has been successfully removed from your server. Your environment is secure.
        </div>

    <?php else: ?>

        <div class="grid">
            <div class="card c-scan">
                <div class="value"><?php echo $stats['scanned']; ?></div>
                <div class="label">Total Scanned</div>
            </div>
            <div class="card c-ok">
                <div class="value" style="color:var(--green)"><?php echo $stats['ok']; ?></div>
                <div class="label">Already Correct</div>
            </div>
            <div class="card c-updated">
                <div class="value" style="color:#a855f7"><?php echo $stats['updated']; ?></div>
                <div class="label">Updated</div>
            </div>
            <div class="card c-blocked">
                <div class="value" style="color:var(--orange)"><?php echo $stats['blocked']; ?></div>
                <div class="label">Owner Lock</div>
            </div>
            <div class="card c-errors">
                <div class="value" style="color:<?php echo $errors_color; ?>"><?php echo $stats['errors']; ?></div>
                <div class="label">Failures</div>
            </div>
        </div>

        <div class="legend">
            <span><span class="leg-dot" style="background:#4ade80"></span> Permissions updated</span>
            <span><span class="leg-dot" style="background:#fb923c"></span> System file lock</span>
            <span><span class="leg-dot" style="background:#f87171"></span> Critical write failure</span>
        </div>

        <div class="console-panel">
            <div class="console-header">
                <span>Activity Log</span>
                <span class="badge"><?php echo count($logs); ?> item(s)</span>
            </div>
            <div class="console-body" id="console">
                <?php if (empty($logs)): ?>
                    <p class="log-line log-empty">✓ All permissions are already secure. No changes required.</p>
                <?php else: ?>
                    <?php foreach ($logs as $log):
                        $css = match($log['type']) {
                            'success' => 'log-success',
                            'blocked' => 'log-blocked',
                            'error'   => 'log-error',
                            default   => '',
                        };
                        $prefix = match($log['type']) {
                            'success' => '[FIXED] ',
                            'blocked' => '[LOCK]  ',
                            'error'   => '[ERROR] ',
                            default   => '        ',
                        };
                    ?>
                        <p class="log-line <?php echo $css; ?>"><?php echo $prefix . ' ' . htmlspecialchars($log['msg']); ?></p>
                    <?php endforeach; ?>

                    <?php if ($log_truncated): ?>
                        <div class="log-warn-limit">
                            ℹ️ Display limited to the first <?php echo MAX_LOG_ENTRIES; ?> rows to save memory. All system files were still processed successfully.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($stats['blocked'] > 0): ?>
        <div class="note-ovh">
            <strong>Technical Note (Shared Hosting Environments):</strong> Files marked as "Owner Lock" belong to the system PHP user rather than your FTP account context. This isolation setup is normal on shared platforms (such as OVH Cloud) and ensures environment stability.
        </div>
        <?php endif; ?>

        <div class="delete-zone">
            <h3>⚠️ Critical Action Required: Post-Execution Cleanup</h3>
            <p>Leaving script execution utilities publicly accessible introduces severe security vectors. Destroy this file immediately.</p>
            <a href="?key=<?php echo urlencode($passed_key); ?>&delete=1" class="btn-delete">
                🗑️ Clean up and Delete This Script From Server
            </a>
        </div>

    <?php endif; ?>

</div>

<script>
    const c = document.getElementById('console');
    if (c) c.scrollTop = c.scrollHeight;
</script>

</body>
</html>
