<?php
##
# diag_terminal.php
# pfSense WebGUI: Diagnostics -> Terminal Console
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

        // Execute system command
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

            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $exit_code = proc_close($process);
            $output = $stdout . $stderr;
        } else {
            $output = "Gagal menjalankan perintah: proc_open() error.\n";
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

$pgtitle = [gettext("Diagnostics"), gettext("Terminal Console")];
$pglinks = ["", "@self"];

include("head.inc");
?>

<style>
/* Modern Terminal Console Styling */
.terminal-page-container {
    margin-top: 10px;
    margin-bottom: 30px;
}

.terminal-card {
    background-color: #0d1117;
    border: 1px solid #30363d;
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* Tabs Bar */
.terminal-tabs-bar {
    display: flex;
    align-items: center;
    background-color: #161b22;
    border-bottom: 1px solid #30363d;
    padding: 6px 10px 0 10px;
    gap: 4px;
    user-select: none;
    overflow-x: auto;
}

.terminal-tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 14px;
    background-color: #21262d;
    color: #8b949e;
    font-size: 13px;
    font-weight: 500;
    border-radius: 6px 6px 0 0;
    border: 1px solid #30363d;
    border-bottom: none;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
}

.terminal-tab:hover {
    background-color: #2b313a;
    color: #c9d1d9;
}

.terminal-tab.active {
    background-color: #0d1117;
    color: #58a6ff;
    border-top: 2px solid #58a6ff;
    font-weight: 600;
}

.terminal-tab .tab-icon {
    font-size: 11px;
    opacity: 0.8;
}

.terminal-tab .tab-close {
    font-size: 14px;
    line-height: 1;
    color: #8b949e;
    margin-left: 4px;
    border-radius: 50%;
    padding: 1px 4px;
}

.terminal-tab .tab-close:hover {
    background-color: rgba(248, 81, 73, 0.2);
    color: #f85149;
}

.btn-add-terminal {
    background-color: #238636;
    color: #ffffff;
    border: none;
    border-radius: 4px;
    padding: 5px 12px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 6px;
    margin-left: 6px;
    transition: background-color 0.15s ease;
}

.btn-add-terminal:hover {
    background-color: #2ea043;
    color: #ffffff;
}

/* Window Title Bar */
.terminal-window-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 14px;
    background-color: #161b22;
    border-bottom: 1px solid #21262d;
    color: #8b949e;
    font-size: 12px;
}

.window-dots {
    display: flex;
    gap: 6px;
    align-items: center;
}

.dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}

.dot-red { background-color: #ff5f56; }
.dot-yellow { background-color: #ffbd2e; }
.dot-green { background-color: #27c93f; }

.window-title {
    font-family: monospace;
    font-size: 12px;
    color: #c9d1d9;
    font-weight: 500;
}

.window-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.window-btn {
    background: transparent;
    border: 1px solid #30363d;
    color: #8b949e;
    padding: 3px 8px;
    font-size: 11px;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.15s ease;
}

.window-btn:hover {
    background: #21262d;
    color: #f0f6fc;
    border-color: #8b949e;
}

/* Quick Commands Bar */
.terminal-quick-bar {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background-color: #0d1117;
    border-bottom: 1px solid #21262d;
    overflow-x: auto;
    font-size: 11px;
}

.quick-label {
    color: #8b949e;
    font-weight: 600;
    margin-right: 4px;
    white-space: nowrap;
}

.quick-cmd-btn {
    background-color: #161b22;
    border: 1px solid #30363d;
    color: #58a6ff;
    padding: 2px 8px;
    border-radius: 12px;
    cursor: pointer;
    font-family: monospace;
    font-size: 11px;
    white-space: nowrap;
    transition: all 0.15s ease;
}

.quick-cmd-btn:hover {
    background-color: #21262d;
    border-color: #58a6ff;
    color: #79c0ff;
}

/* Screen / Console Body */
.terminal-screen-container {
    position: relative;
    height: 520px;
    background-color: #0d1117;
    display: flex;
    flex-direction: column;
}

.terminal-output {
    flex: 1;
    padding: 14px;
    overflow-y: auto;
    font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, Courier, monospace;
    font-size: 13px;
    line-height: 1.5;
    color: #c9d1d9;
    white-space: pre-wrap;
    word-break: break-all;
}

.terminal-output::-webkit-scrollbar {
    width: 8px;
}
.terminal-output::-webkit-scrollbar-thumb {
    background: #30363d;
    border-radius: 4px;
}

.term-banner {
    color: #388bfd;
    font-weight: bold;
    margin-bottom: 12px;
}

.term-line-cmd {
    color: #7ee787;
    font-weight: 600;
}

.term-line-err {
    color: #f85149;
}

.term-line-out {
    color: #e6edf3;
}

/* Input Row */
.terminal-input-row {
    display: flex;
    align-items: center;
    padding: 10px 14px;
    background-color: #161b22;
    border-top: 1px solid #30363d;
    gap: 8px;
}

.terminal-prompt-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-family: monospace;
    font-size: 13px;
    font-weight: 700;
    color: #7ee787;
    white-space: nowrap;
    user-select: none;
}

.terminal-prompt-cwd {
    color: #79c0ff;
}

.terminal-input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    color: #f0f6fc;
    font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, Courier, monospace;
    font-size: 13px;
    font-weight: 500;
    caret-color: #58a6ff;
}

.terminal-run-btn {
    background-color: #238636;
    color: #ffffff;
    border: none;
    padding: 5px 14px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: background-color 0.15s ease;
}

.terminal-run-btn:hover {
    background-color: #2ea043;
}

.terminal-status-spinner {
    display: none;
    color: #e3b341;
    font-size: 12px;
}
</style>

<div class="terminal-page-container">
    <div class="terminal-card">
        <!-- Terminal Tabs Navigation -->
        <div class="terminal-tabs-bar" id="term-tabs-bar">
            <!-- Tabs injected dynamically via JS -->
            <button type="button" class="btn-add-terminal" id="btn-add-tab" title="<?=gettext("Buka Terminal Baru")?>">
                <i class="fa-solid fa-plus"></i>
                <span><?=gettext("Tambah Terminal")?></span>
            </button>
        </div>

        <!-- Terminal Window Controls -->
        <div class="terminal-window-header">
            <div class="window-dots">
                <span class="dot dot-red" title="Close Session" id="btn-dot-close"></span>
                <span class="dot dot-yellow" title="Clear Screen" id="btn-dot-clear"></span>
                <span class="dot dot-green" title="Add Session" id="btn-dot-add"></span>
            </div>
            <div class="window-title" id="term-header-title">
                root@pfSense [~]
            </div>
            <div class="window-actions">
                <button type="button" class="window-btn" id="btn-font-dec" title="Kecilkan Font">A-</button>
                <button type="button" class="window-btn" id="btn-font-inc" title="Besarkan Font">A+</button>
                <button type="button" class="window-btn" id="btn-clear-term" title="Bersihkan Layar (Ctrl+L)">
                    <i class="fa-solid fa-eraser"></i> <?=gettext("Clear")?>
                </button>
            </div>
        </div>

        <!-- Quick Shortcut Bar -->
        <div class="terminal-quick-bar">
            <span class="quick-label"><i class="fa-solid fa-bolt"></i> <?=gettext("Shortcut:")?></span>
            <button type="button" class="quick-cmd-btn" data-cmd="uptime">uptime</button>
            <button type="button" class="quick-cmd-btn" data-cmd="top -b -d 1">top</button>
            <button type="button" class="quick-cmd-btn" data-cmd="ifconfig -a">ifconfig</button>
            <button type="button" class="quick-cmd-btn" data-cmd="netstat -rn">routes</button>
            <button type="button" class="quick-cmd-btn" data-cmd="pfctl -sr">pfctl rules</button>
            <button type="button" class="quick-cmd-btn" data-cmd="pfctl -si">pfctl info</button>
            <button type="button" class="quick-cmd-btn" data-cmd="ps aux">ps aux</button>
            <button type="button" class="quick-cmd-btn" data-cmd="df -h">df -h</button>
            <button type="button" class="quick-cmd-btn" data-cmd="dmesg | tail -n 25">dmesg</button>
            <button type="button" class="quick-cmd-btn" data-cmd="uname -mrs">uname</button>
        </div>

        <!-- Terminal Output & Interactive Shell -->
        <div class="terminal-screen-container" id="terminal-screen">
            <div class="terminal-output" id="term-output"></div>

            <div class="terminal-input-row">
                <div class="terminal-prompt-badge">
                    <span>root@pfSense</span>
                    <span>[<span class="terminal-prompt-cwd" id="term-prompt-cwd">~</span>]#</span>
                </div>
                <input type="text" class="terminal-input" id="term-input" autocomplete="off" spellcheck="false" placeholder="Ketik perintah shell di sini lalu tekan Enter..." />
                <span class="terminal-status-spinner" id="term-spinner">
                    <i class="fa-solid fa-circle-notch fa-spin"></i>
                </span>
                <button type="button" class="terminal-run-btn" id="btn-run-cmd">
                    <i class="fa-solid fa-play"></i> <?=gettext("Jalankan")?>
                </button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
    var sessions = [];
    var activeSessionId = null;
    var nextSessionNum = 1;
    var currentFontSize = 13;

    var BANNER_TEXT = 
"  ___  __ ____                     _____                   _             _ \n" +
" | _ \\/ _| ___|  ___ _ __  ___  ___|_   _|__ _ _ _ __ ___ (_)_ _  __ _ | |\n" +
" |  _/  _\\___ \\ / -_) '  \\(_-< / -_) | |/ -_) '_| '  \\/ _ \\| | ' \\/ _` || |\n" +
" |_| |_| |____/ \\___|_|_|_/__/ \\___| |_|\\___|_| |_|_|_\\___/|_|_||_\\__,_||_|\n\n" +
"  pfSense Interactive Web Terminal (FreeBSD)\n" +
"  Ketik perintah shell atau gunakan '+ Tambah Terminal' untuk sesi baru.\n" +
"  ========================================================================\n\n";

    // Escape HTML string
    function escapeHtml(text) {
        if (!text) return "";
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Convert ANSI color codes to styled HTML
    function ansiToHtml(text) {
        var str = escapeHtml(text);
        str = str.replace(/\033\[0?m/g, '</span>');
        str = str.replace(/\033\[0?1m/g, '<span style="font-weight: bold;">');
        str = str.replace(/\033\[31m/g, '<span style="color: #f85149;">'); // Red
        str = str.replace(/\033\[32m/g, '<span style="color: #7ee787;">'); // Green
        str = str.replace(/\033\[33m/g, '<span style="color: #e3b341;">'); // Yellow
        str = str.replace(/\033\[34m/g, '<span style="color: #58a6ff;">'); // Blue
        str = str.replace(/\033\[35m/g, '<span style="color: #bc8cff;">'); // Magenta
        str = str.replace(/\033\[36m/g, '<span style="color: #39c5cf;">'); // Cyan
        str = str.replace(/\033\[37m/g, '<span style="color: #f0f6fc;">'); // White
        str = str.replace(/\033\[90m/g, '<span style="color: #8b949e;">'); // Bright black/grey
        // Remove unhandled ANSI sequences
        str = str.replace(/\033\[[0-9;]*[a-zA-Z]/g, '');
        return str;
    }

    // Get Active Session Object
    function getActiveSession() {
        for (var i = 0; i < sessions.length; i++) {
            if (sessions[i].id === activeSessionId) {
                return sessions[i];
            }
        }
        return null;
    }

    // Render Tabs Bar
    function renderTabs() {
        var $bar = $('#term-tabs-bar');
        $bar.find('.terminal-tab').remove();

        sessions.forEach(function(s) {
            var activeClass = (s.id === activeSessionId) ? ' active' : '';
            var $tab = $(
                '<div class="terminal-tab' + activeClass + '" data-id="' + s.id + '">' +
                '  <i class="fa-solid fa-terminal tab-icon"></i>' +
                '  <span class="tab-title">' + escapeHtml(s.name) + '</span>' +
                '  <span class="tab-close" title="Tutup Terminal">&times;</span>' +
                '</div>'
            );

            // Tab click -> switch active
            $tab.on('click', function(e) {
                if ($(e.target).hasClass('tab-close')) return;
                switchSession(s.id);
            });

            // Close tab click
            $tab.find('.tab-close').on('click', function(e) {
                e.stopPropagation();
                closeSession(s.id);
            });

            $('#btn-add-tab').before($tab);
        });

        updateHeaderAndPrompt();
    }

    // Update Header title and prompt cwd display
    function updateHeaderAndPrompt() {
        var s = getActiveSession();
        if (!s) return;

        var displayCwd = s.cwd;
        if (displayCwd === '/root') displayCwd = '~';

        $('#term-header-title').text(s.name + ' — root@pfSense: ' + displayCwd);
        $('#term-prompt-cwd').text(displayCwd);
        $('#term-output').html(s.content);
        scrollOutputToBottom();
    }

    function scrollOutputToBottom() {
        var out = document.getElementById('term-output');
        if (out) {
            out.scrollTop = out.scrollHeight;
        }
    }

    // Create New Terminal Session
    function createSession() {
        var num = nextSessionNum++;
        var newSession = {
            id: 'term_' + Date.now() + '_' + num,
            name: 'Terminal ' + num,
            cwd: '/root',
            history: [],
            historyIdx: -1,
            content: ansiToHtml(BANNER_TEXT)
        };

        sessions.push(newSession);
        switchSession(newSession.id);
    }

    // Switch Active Session
    function switchSession(id) {
        activeSessionId = id;
        renderTabs();
        $('#term-input').focus();
    }

    // Close Terminal Session
    function closeSession(id) {
        if (sessions.length <= 1) {
            // Jika hanya tinggal 1, bersihkan saja
            var s = getActiveSession();
            if (s) {
                s.content = ansiToHtml(BANNER_TEXT);
                s.cwd = '/root';
                s.historyIdx = -1;
                updateHeaderAndPrompt();
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

    // Execute Command in Active Session
    function executeCommand(cmdStr) {
        var s = getActiveSession();
        if (!s) return;

        var cmd = (cmdStr !== undefined) ? cmdStr.trim() : $('#term-input').val().trim();
        if (cmd === '') return;

        // Push to session history
        if (s.history[s.history.length - 1] !== cmd) {
            s.history.push(cmd);
        }
        s.historyIdx = s.history.length;

        var displayCwd = s.cwd === '/root' ? '~' : s.cwd;
        var promptLine = '<span class="term-line-cmd">root@pfSense [' + escapeHtml(displayCwd) + ']# ' + escapeHtml(cmd) + '</span>\n';
        s.content += promptLine;
        $('#term-output').html(s.content);
        scrollOutputToBottom();

        $('#term-input').val('');
        $('#term-spinner').show();
        $('#btn-run-cmd').prop('disabled', true);

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
                $('#term-spinner').hide();
                $('#btn-run-cmd').prop('disabled', false);

                if (resp && resp.output === '__CLEAR__') {
                    s.content = '';
                } else if (resp && resp.output) {
                    s.content += ansiToHtml(resp.output);
                    if (!resp.output.endsWith("\n")) {
                        s.content += "\n";
                    }
                }

                if (resp && resp.cwd) {
                    s.cwd = resp.cwd;
                }

                updateHeaderAndPrompt();
                $('#term-input').focus();
            },
            error: function(xhr, status, err) {
                $('#term-spinner').hide();
                $('#btn-run-cmd').prop('disabled', false);
                s.content += '<span class="term-line-err">Error AJAX: ' + escapeHtml(err || status) + '</span>\n';
                updateHeaderAndPrompt();
                $('#term-input').focus();
            }
        });
    }

    // Setup Event Listeners
    $('#btn-add-tab, #btn-dot-add').on('click', function() {
        createSession();
    });

    $('#btn-run-cmd').on('click', function() {
        executeCommand();
    });

    // Enter Key & Arrow History
    $('#term-input').on('keydown', function(e) {
        var s = getActiveSession();
        if (!s) return;

        if (e.key === 'Enter') {
            e.preventDefault();
            executeCommand();
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
                    s.historyIdx++;
                    $(this).val(s.history[s.historyIdx] || '');
                } else {
                    s.historyIdx = s.history.length;
                    $(this).val('');
                }
            }
        } else if (e.ctrlKey && e.key === 'l') {
            e.preventDefault();
            s.content = '';
            updateHeaderAndPrompt();
        } else if (e.ctrlKey && e.key === 'c') {
            e.preventDefault();
            $(this).val('');
        }
    });

    // Clear Button
    $('#btn-clear-term, #btn-dot-clear').on('click', function() {
        var s = getActiveSession();
        if (s) {
            s.content = '';
            updateHeaderAndPrompt();
            $('#term-input').focus();
        }
    });

    // Close Dot
    $('#btn-dot-close').on('click', function() {
        if (activeSessionId) {
            closeSession(activeSessionId);
        }
    });

    // Quick Command Buttons
    $('.quick-cmd-btn').on('click', function() {
        var cmd = $(this).data('cmd');
        if (cmd) {
            executeCommand(cmd);
        }
    });

    // Font size adjustments
    $('#btn-font-inc').on('click', function() {
        if (currentFontSize < 20) {
            currentFontSize++;
            $('#term-output, #term-input').css('font-size', currentFontSize + 'px');
        }
    });

    $('#btn-font-dec').on('click', function() {
        if (currentFontSize > 10) {
            currentFontSize--;
            $('#term-output, #term-input').css('font-size', currentFontSize + 'px');
        }
    });

    // Click anywhere on terminal screen -> focus input
    $('#terminal-screen').on('click', function(e) {
        if (!$(e.target).closest('button').length) {
            $('#term-input').focus();
        }
    });

    // Inisialisasi Terminal Pertama
    createSession();
});
//]]>
</script>

<?php include("foot.inc"); ?>
