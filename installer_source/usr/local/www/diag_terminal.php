<?php
##
# diag_terminal.php
# pfSense WebGUI: Terminal Console (FreeBSD System Shell)
# Part of pfSense Custom Edition
##

require_once("guiconfig.inc");

// Handle AJAX Command Execution
if (isset($_REQUEST['ajax']) && $_REQUEST['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');

    $action = $_POST['action'] ?? 'exec';

    if ($action === 'exec') {
        $cmd = trim($_POST['cmd'] ?? '');
        $cwd = trim($_POST['cwd'] ?? '/root');

        if (empty($cwd) || !is_dir($cwd)) {
            $cwd = '/root';
        }

        if ($cmd === '') {
            echo json_encode([
                'success' => true,
                'output' => '',
                'cwd' => $cwd,
                'exit_code' => 0
            ]);
            exit;
        }

        // Handle 'clear' command
        if ($cmd === 'clear') {
            echo json_encode([
                'success' => true,
                'output' => '__CLEAR__',
                'cwd' => $cwd,
                'exit_code' => 0
            ]);
            exit;
        }

        // Handle 'cd' built-in command
        if (preg_match('/^cd(?:\s+(.*))?$/', $cmd, $matches)) {
            $target = trim($matches[1] ?? '');
            if ($target === '' || $target === '~') {
                $target = '/root';
            } elseif ($target === '-') {
                $target = $_SESSION['prev_cwd'] ?? '/root';
            } elseif (substr($target, 0, 1) !== '/') {
                $target = $cwd . '/' . $target;
            }

            $real = realpath($target);
            if ($real && is_dir($real)) {
                $_SESSION['prev_cwd'] = $cwd;
                $cwd = $real;
                echo json_encode([
                    'success' => true,
                    'output' => '',
                    'cwd' => $cwd,
                    'exit_code' => 0
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'output' => "cd: " . htmlspecialchars($matches[1] ?? '') . ": No such file or directory\n",
                    'cwd' => $cwd,
                    'exit_code' => 1
                ]);
            }
            exit;
        }

        // Prevent commands that run indefinitely without count/batch flags on FreeBSD
        if (preg_match('/^ping\s+([^-\s][^\s]*)/', $cmd)) {
            $cmd = preg_replace('/^ping\s+/', 'ping -c 4 ', $cmd);
        } elseif ($cmd === 'ping') {
            $cmd = 'ping -c 4 8.8.8.8';
        }

        if ($cmd === 'top') {
            $cmd = 'top -b -d 1';
        }

        if (preg_match('/^traceroute\s+([^-\s][^\s]*)/', $cmd)) {
            $cmd = preg_replace('/^traceroute\s+/', 'traceroute -w 2 -q 1 ', $cmd);
        }

        // Execute system command in FreeBSD native shell (/bin/sh)
        $descriptorspec = [
            0 => ["pipe", "r"], // stdin
            1 => ["pipe", "w"], // stdout
            2 => ["pipe", "w"]  // stderr
        ];

        $env = [
            'PATH' => '/sbin:/bin:/usr/sbin:/usr/bin:/usr/local/sbin:/usr/local/bin',
            'HOME' => '/root',
            'USER' => 'root',
            'SHELL' => '/bin/sh',
            'TERM' => 'xterm-256color',
            'LANG' => 'en_US.UTF-8',
            'LC_ALL' => 'en_US.UTF-8'
        ];

        $process = proc_open($cmd, $descriptorspec, $pipes, $cwd, $env);
        $output = '';
        $exit_code = 0;

        if (is_resource($process)) {
            fclose($pipes[0]);

            // Set non-blocking mode on stdout and stderr
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $timeout = 15; // 15 seconds hard timeout
            $start = microtime(true);

            while (microtime(true) - $start < $timeout) {
                $read = [$pipes[1], $pipes[2]];
                $write = null;
                $except = null;
                $changed = stream_select($read, $write, $except, 0, 100000); // 100ms
                if ($changed > 0) {
                    foreach ($read as $r) {
                        $chunk = fread($r, 8192);
                        if ($chunk !== false && strlen($chunk) > 0) {
                            $output .= $chunk;
                        }
                    }
                }

                $status = proc_get_status($process);
                if (!$status['running']) {
                    while ($chunk = fread($pipes[1], 8192)) {
                        $output .= $chunk;
                    }
                    while ($chunk = fread($pipes[2], 8192)) {
                        $output .= $chunk;
                    }
                    $exit_code = $status['exitcode'];
                    break;
                }
            }

            $status = proc_get_status($process);
            if ($status['running']) {
                proc_terminate($process, 9);
                $output .= "\n[Execution timeout: Command terminated after {$timeout} seconds]\n";
                $exit_code = 124;
            }

            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        } else {
            $output = "Gagal menjalankan perintah shell FreeBSD.\n";
            $exit_code = -1;
        }

        echo json_encode([
            'success' => ($exit_code === 0),
            'output' => $output,
            'cwd' => $cwd,
            'exit_code' => $exit_code
        ]);
        exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
}



$pgtitle = [gettext("Terminal"), gettext("System Console")];
$pglinks = ["", "@self"];
$notitle = true;

// Sembunyikan alert password tidak aman dan breadcrumb pada konsol terminal
$saved_insecure_user = $_SESSION['insecure_user'] ?? null;
$_SESSION['insecure_user'] = false;
$saved_insecure_admin = $_SESSION['insecure_admin'] ?? null;
$_SESSION['insecure_admin'] = false;

include("head.inc");

// Pulihkan nilai sesi agar halaman lain tetap normal
if ($saved_insecure_user !== null) $_SESSION['insecure_user'] = $saved_insecure_user;
if ($saved_insecure_admin !== null) $_SESSION['insecure_admin'] = $saved_insecure_admin;
?>

<style>
/* Sembunyikan semua alert bawaan, card breadcrumb, dan br bawaan pfSense */
#pf-main-content > .alert,
#pf-main-content > .alert-danger,
#pf-main-content > .alert-warning,
#pf-main-content > header,
#pf-main-content > .header,
#pf-main-content > br,
.terminal-container ~ *,
header.header {
    display: none !important;
    margin: 0 !important;
    padding: 0 !important;
    height: 0 !important;
    border: none !important;
}

/* Reset and Seamless Layout Alignment with Dark Sidebar */
body {
    background-color: #0b0d11 !important;
    overflow-x: hidden !important;
}

body:not(.sidebar-collapsed) #pf-main-content {
    margin-left: var(--pf-sidebar-w) !important;
    width: calc(100% - var(--pf-sidebar-w)) !important;
    max-width: calc(100% - var(--pf-sidebar-w)) !important;
    padding: 0 !important;
    margin-top: 0 !important;
    margin-bottom: 0 !important;
}

body.sidebar-collapsed #pf-main-content {
    margin-left: var(--pf-sidebar-mini-w) !important;
    width: calc(100% - var(--pf-sidebar-mini-w)) !important;
    max-width: calc(100% - var(--pf-sidebar-mini-w)) !important;
    padding: 0 !important;
    margin-top: 0 !important;
    margin-bottom: 0 !important;
}

#pf-main-content {
    padding: 0 !important;
    position: relative !important;
    left: 0 !important;
    top: 0 !important;
    margin-right: 0 !important;
    height: calc(100vh - var(--pf-header-h) - var(--pf-footer-h)) !important;
    max-height: calc(100vh - var(--pf-header-h) - var(--pf-footer-h)) !important;
    overflow: hidden !important;
    background-color: #0b0d11 !important;
    box-sizing: border-box !important;
}

@media (max-width: 991px) {
    #pf-main-content,
    body:not(.sidebar-collapsed) #pf-main-content,
    body.sidebar-collapsed #pf-main-content {
        margin-left: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }
}

/* Full Terminal Container */
.terminal-container {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    background-color: #0b0d11;
    color: #c9d1d9;
    font-family: "Fira Code", "Cascadia Code", "JetBrains Mono", Menlo, Consolas, "Liberation Mono", Courier, monospace;
    font-size: 13.5px;
    line-height: 1.5;
    box-sizing: border-box;
}

/* Tabs Bar in Header */
.terminal-tabs-wrapper {
    display: flex;
    align-items: center;
    gap: 6px;
    height: 100%;
    overflow-x: auto;
}

.terminal-tabs-wrapper::-webkit-scrollbar {
    display: none;
}

.terminal-tab {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 4px 11px;
    background-color: #171b24;
    color: #8c95a6;
    font-size: 12px;
    font-weight: 500;
    border-radius: 4px;
    border: 1px solid #242a38;
    cursor: pointer;
    transition: all 0.12s ease;
    white-space: nowrap;
    user-select: none;
}

.terminal-tab:hover {
    background-color: #202634;
    color: #e6edf3;
    border-color: #313a4d;
}

.terminal-tab.active {
    background-color: #0b0d11;
    color: #58a6ff;
    border-color: #283754;
    font-weight: 600;
}

.terminal-tab .tab-icon {
    font-size: 11px;
    opacity: 0.9;
}

.terminal-tab .tab-close {
    font-size: 14px;
    color: #8c95a6;
    margin-left: 2px;
    border-radius: 50%;
    padding: 0 3px;
    line-height: 1;
}

.terminal-tab .tab-close:hover {
    color: #f85149;
    background-color: rgba(248, 81, 73, 0.2);
}

.btn-new-tab {
    background-color: #1a2332;
    color: #58a6ff;
    border: 1px solid #283850;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.15s ease;
    white-space: nowrap;
    user-select: none;
}

.btn-new-tab:hover {
    background-color: #223046;
    color: #79c0ff;
    border-color: #364e70;
}

.terminal-actions {
    display: flex;
    align-items: center;
    gap: 6px;
}

.term-btn {
    background-color: #1c212c;
    color: #8b949e;
    border: 1px solid #2d3546;
    padding: 3px 9px;
    border-radius: 4px;
    font-size: 11.5px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.15s ease;
}

.term-btn:hover {
    background-color: #272f3f;
    color: #f0f6fc;
    border-color: #3f4c63;
}

/* Interactive Terminal Viewport */
.terminal-viewport {
    flex: 1;
    overflow-y: auto;
    padding: 16px 20px 24px 20px;
    background-color: #0b0d11;
    cursor: text;
}

.terminal-viewport::-webkit-scrollbar {
    width: 8px;
}
.terminal-viewport::-webkit-scrollbar-track {
    background: #0b0d11;
}
.terminal-viewport::-webkit-scrollbar-thumb {
    background: #21262d;
    border-radius: 4px;
}
.terminal-viewport::-webkit-scrollbar-thumb:hover {
    background: #30363d;
}

/* Terminal Content Stream */
.terminal-content {
    white-space: pre-wrap;
    word-break: break-all;
    margin-bottom: 4px;
}

.term-history-entry {
    margin-bottom: 2px;
}

.term-cmd-echo {
    color: #56d364;
    font-weight: 600;
    display: block;
}

.term-cmd-output {
    color: #c9d1d9;
    margin: 2px 0 6px 0;
    display: block;
}

.term-cmd-error {
    color: #f85149;
    font-weight: 500;
}

/* Active Inline Input Prompt */
.terminal-input-line {
    display: flex;
    align-items: center;
    width: 100%;
    margin-top: 2px;
    line-height: 1.5;
}

.term-prompt {
    font-weight: 700;
    white-space: nowrap;
    user-select: none;
    margin-right: 6px;
}

.prompt-user {
    color: #56d364;
}

.prompt-cwd {
    color: #79c0ff;
}

.prompt-char {
    color: #56d364;
}

.term-input-wrapper {
    flex: 1;
    display: flex;
    align-items: center;
}

.term-input {
    width: 100%;
    background: transparent;
    border: none;
    outline: none;
    padding: 0;
    margin: 0;
    color: #f0f6fc;
    font-family: inherit;
    font-size: inherit;
    line-height: inherit;
    font-weight: 500;
    caret-color: #58a6ff;
}

.term-spinner {
    display: none;
    color: #e3b341;
    margin-left: 8px;
    font-size: 13px;
}
</style>

<div class="terminal-container" id="terminal-container">
    <!-- Slim Modern Console Bar with Tabs -->
    <div class="terminal-header-bar">
        <div class="terminal-tabs-wrapper" id="term-tabs-bar">
            <!-- Tabs dynamically rendered here -->
            <button type="button" class="btn-new-tab" id="btn-add-tab" title="<?=gettext("Buka Terminal Baru")?>">
                <i class="fa-solid fa-plus"></i>
                <span><?=gettext("New Terminal")?></span>
            </button>
        </div>
        <div class="terminal-actions">
            <button type="button" class="term-btn" id="btn-font-dec" title="Kecilkan Font">A-</button>
            <button type="button" class="term-btn" id="btn-font-inc" title="Besarkan Font">A+</button>
            <button type="button" class="term-btn" id="btn-clear" title="Bersihkan Layar (Ctrl+L)">
                <i class="fa-solid fa-eraser"></i> <?=gettext("Clear")?>
            </button>
            <button type="button" class="term-btn" id="btn-reset" title="Reset Konsol">
                <i class="fa-solid fa-rotate-right"></i> <?=gettext("Reset")?>
            </button>
        </div>
    </div>

    <!-- Interactive Terminal Screen (Inline Prompt) -->
    <div class="terminal-viewport" id="term-viewport">
        <div class="terminal-content" id="term-content"></div>

        <div class="terminal-input-line" id="term-input-line">
            <span class="term-prompt">
                <span class="prompt-user">root@pfSense</span>:<span class="prompt-cwd" id="prompt-cwd">~</span><span class="prompt-char">&nbsp;#&nbsp;</span>
            </span>
            <div class="term-input-wrapper">
                <input type="text" class="term-input" id="term-input" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" />
            </div>
            <span class="term-spinner" id="term-spinner">
                <i class="fa-solid fa-circle-notch fa-spin"></i>
            </span>
        </div>
    </div>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
    var sessions = [];
    var activeSessionId = null;
    var nextSessionNum = 1;
    var currentFontSize = 13.5;
    var isExecuting = false;

    function escapeHtml(text) {
        if (!text) return "";
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Convert ANSI color escape sequences to HTML spans
    function ansiToHtml(text) {
        var str = escapeHtml(text);
        str = str.replace(/\033\[0?m/g, '</span>');
        str = str.replace(/\033\[0?1m/g, '<span style="font-weight: bold;">');
        str = str.replace(/\033\[31m/g, '<span style="color: #f85149;">');
        str = str.replace(/\033\[32m/g, '<span style="color: #56d364;">');
        str = str.replace(/\033\[33m/g, '<span style="color: #e3b341;">');
        str = str.replace(/\033\[34m/g, '<span style="color: #58a6ff;">');
        str = str.replace(/\033\[35m/g, '<span style="color: #bc8cff;">');
        str = str.replace(/\033\[36m/g, '<span style="color: #39c5cf;">');
        str = str.replace(/\033\[37m/g, '<span style="color: #f0f6fc;">');
        str = str.replace(/\033\[90m/g, '<span style="color: #8b949e;">');
        str = str.replace(/\033\[[0-9;]*[a-zA-Z]/g, '');
        return str;
    }

    function getActiveSession() {
        for (var i = 0; i < sessions.length; i++) {
            if (sessions[i].id === activeSessionId) {
                return sessions[i];
            }
        }
        return sessions[0] || null;
    }

    function renderTabs() {
        var $bar = $('#term-tabs-bar');
        $bar.find('.terminal-tab').remove();

        sessions.forEach(function(s) {
            var activeClass = (s.id === activeSessionId) ? ' active' : '';
            var closeBtn = (sessions.length > 1) ? '<span class="tab-close" title="Tutup Terminal">&times;</span>' : '';
            var $tab = $(
                '<div class="terminal-tab' + activeClass + '" data-id="' + s.id + '">' +
                '  <i class="fa-solid fa-terminal tab-icon"></i>' +
                '  <span class="tab-title">' + escapeHtml(s.name) + '</span>' +
                closeBtn +
                '</div>'
            );

            $tab.on('click', function(e) {
                if ($(e.target).hasClass('tab-close')) return;
                switchSession(s.id);
            });

            $tab.find('.tab-close').on('click', function(e) {
                e.stopPropagation();
                closeSession(s.id);
            });

            $('#btn-add-tab').before($tab);
        });

        updateView();
    }

    function updateView() {
        var s = getActiveSession();
        if (!s) return;

        var displayCwd = (s.cwd === '/root') ? '~' : s.cwd;
        $('#prompt-cwd').text(displayCwd);
        $('#term-content').html(s.content);
        scrollToBottom();
        focusInput();
    }

    function scrollToBottom() {
        var vp = document.getElementById('term-viewport');
        if (vp) {
            vp.scrollTop = vp.scrollHeight;
        }
    }

    function focusInput() {
        if (!isExecuting) {
            $('#term-input').focus();
        }
    }

    function createSession() {
        var num = nextSessionNum++;
        var newSession = {
            id: 'term_' + Date.now() + '_' + num,
            name: 'Terminal ' + num,
            cwd: '/root',
            history: [],
            historyIdx: -1,
            content: '' // Clean start without banner
        };

        sessions.push(newSession);
        switchSession(newSession.id);
    }

    function switchSession(id) {
        activeSessionId = id;
        renderTabs();
    }

    function closeSession(id) {
        if (sessions.length <= 1) {
            var s = getActiveSession();
            if (s) {
                s.content = '';
                s.cwd = '/root';
                s.history = [];
                s.historyIdx = -1;
                updateView();
            }
            return;
        }

        var idx = -1;
        for (var i = 0; i < sessions.length; i++) {
            if (sessions[i].id === id) {
                idx = i;
                break;
            }
        }

        if (idx !== -1) {
            sessions.splice(idx, 1);
            if (activeSessionId === id) {
                var nextActive = sessions[Math.max(0, idx - 1)];
                activeSessionId = nextActive.id;
            }
            renderTabs();
        }
    }

    function executeCommand(cmd) {
        var s = getActiveSession();
        if (!s || isExecuting) return;

        cmd = (cmd !== undefined) ? cmd.trim() : $('#term-input').val().trim();

        if (cmd === '') {
            var displayCwd = (s.cwd === '/root') ? '~' : s.cwd;
            var echoLine = '<div class="term-history-entry">' +
                           '  <span class="term-cmd-echo"><span class="prompt-user">root@pfSense</span>:<span class="prompt-cwd">' + escapeHtml(displayCwd) + '</span><span class="prompt-char"> # </span></span>' +
                           '</div>';
            s.content += echoLine;
            $('#term-content').append(echoLine);
            scrollToBottom();
            focusInput();
            return;
        }

        // Add to session history
        if (s.history.length === 0 || s.history[s.history.length - 1] !== cmd) {
            s.history.push(cmd);
        }
        s.historyIdx = s.history.length;

        var displayCwd = (s.cwd === '/root') ? '~' : s.cwd;
        var promptEcho = '<div class="term-history-entry">' +
                         '  <span class="term-cmd-echo"><span class="prompt-user">root@pfSense</span>:<span class="prompt-cwd">' + escapeHtml(displayCwd) + '</span><span class="prompt-char"> # </span>' + escapeHtml(cmd) + '</span>' +
                         '</div>';
        s.content += promptEcho;
        $('#term-content').append(promptEcho);
        $('#term-input').val('');
        scrollToBottom();

        if (cmd === 'clear') {
            s.content = '';
            $('#term-content').empty();
            scrollToBottom();
            focusInput();
            return;
        }

        if (cmd === 'reset') {
            s.content = '';
            s.cwd = '/root';
            s.history = [];
            s.historyIdx = -1;
            updateView();
            return;
        }

        isExecuting = true;
        $('#term-spinner').show();
        $('#term-input').prop('disabled', true);

        $.ajax({
            url: '/diag_terminal.php',
            type: 'POST',
            dataType: 'json',
            data: {
                ajax: '1',
                action: 'exec',
                cmd: cmd,
                cwd: s.cwd
            },
            success: function(resp) {
                isExecuting = false;
                $('#term-spinner').hide();
                $('#term-input').prop('disabled', false);

                if (resp && resp.output === '__CLEAR__') {
                    s.content = '';
                    $('#term-content').empty();
                } else if (resp && resp.output) {
                    var cls = (!resp.success) ? 'term-cmd-output term-cmd-error' : 'term-cmd-output';
                    var outHtml = '<span class="' + cls + '">' + ansiToHtml(resp.output) + '</span>';
                    s.content += outHtml;
                    $('#term-content').append(outHtml);
                }

                if (resp && resp.cwd) {
                    s.cwd = resp.cwd;
                    var newDisplay = (s.cwd === '/root') ? '~' : s.cwd;
                    $('#prompt-cwd').text(newDisplay);
                }

                scrollToBottom();
                focusInput();
            },
            error: function(xhr, status, err) {
                isExecuting = false;
                $('#term-spinner').hide();
                $('#term-input').prop('disabled', false);
                var errHtml = '<span class="term-cmd-output term-cmd-error">Error connecting to FreeBSD shell backend: ' + escapeHtml(err || status) + "\n</span>";
                s.content += errHtml;
                $('#term-content').append(errHtml);
                scrollToBottom();
                focusInput();
            }
        });
    }

    // Input Keydown Handling
    $('#term-input').on('keydown', function(e) {
        var s = getActiveSession();
        if (!s) return;

        if (e.key === 'Enter') {
            e.preventDefault();
            executeCommand($(this).val());
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (s.history.length > 0) {
                if (s.historyIdx > 0) {
                    s.historyIdx--;
                }
                $(this).val(s.history[s.historyIdx] || '');
            }
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (s.history.length > 0) {
                if (s.historyIdx < s.history.length - 1) {
                    historyIdx++;
                    $(this).val(s.history[s.historyIdx] || '');
                } else {
                    s.historyIdx = s.history.length;
                    $(this).val('');
                }
            }
        } else if (e.ctrlKey && e.key === 'l') {
            e.preventDefault();
            s.content = '';
            $('#term-content').empty();
            scrollToBottom();
        } else if (e.ctrlKey && e.key === 'c') {
            e.preventDefault();
            var displayCwd = (s.cwd === '/root') ? '~' : s.cwd;
            var cancelLine = '<div class="term-history-entry"><span class="term-cmd-echo"><span class="prompt-user">root@pfSense</span>:<span class="prompt-cwd">' + escapeHtml(displayCwd) + '</span><span class="prompt-char"> # </span>' + escapeHtml($(this).val()) + '^C</span></div>';
            s.content += cancelLine;
            $('#term-content').append(cancelLine);
            $(this).val('');
            scrollToBottom();
        }
    });

    // Clicking anywhere in viewport focuses input
    $('#term-viewport').on('click', function(e) {
        if (!window.getSelection().toString()) {
            focusInput();
        }
    });

    // Header Actions
    $('#btn-add-tab').on('click', function() {
        createSession();
    });

    $('#btn-clear').on('click', function() {
        var s = getActiveSession();
        if (s) {
            s.content = '';
            $('#term-content').empty();
            scrollToBottom();
            focusInput();
        }
    });

    $('#btn-reset').on('click', function() {
        var s = getActiveSession();
        if (s) {
            s.content = '';
            s.cwd = '/root';
            s.history = [];
            s.historyIdx = -1;
            updateView();
        }
    });

    $('#btn-font-inc').on('click', function() {
        if (currentFontSize < 20) {
            currentFontSize += 1;
            $('#terminal-container').css('font-size', currentFontSize + 'px');
            scrollToBottom();
        }
    });

    $('#btn-font-dec').on('click', function() {
        if (currentFontSize > 11) {
            currentFontSize -= 1;
            $('#terminal-container').css('font-size', currentFontSize + 'px');
            scrollToBottom();
        }
    });

    // Start with Terminal 1
    createSession();
});
//]]>
</script>

<?php
include("foot.inc");
?>
