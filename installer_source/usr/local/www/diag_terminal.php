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

            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $exit_code = proc_close($process);
            $output = $stdout . $stderr;
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

// Fetch authentic FreeBSD / pfSense system banner
$freebsd_banner = "";
if (file_exists("/etc/rc.banner")) {
    $freebsd_banner .= shell_exec("/etc/rc.banner 2>&1") . "\n";
}
$uname_out = shell_exec("/usr/bin/uname -mrs 2>&1");
if ($uname_out) {
    $freebsd_banner .= trim($uname_out) . "\n";
}
$freebsd_banner .= "Type any FreeBSD shell command to execute.\n";

$pgtitle = [gettext("Terminal"), gettext("System Console")];
$pglinks = ["", "@self"];
$notitle = true; // Supress default header for full-bleed terminal view

include("head.inc");
?>

<style>
/* Full Bleed Viewport Layout for Terminal */
#pf-main-content {
    padding: 0 !important;
    margin: 0 !important;
    max-width: none !important;
    width: calc(100% - var(--pf-sidebar-w)) !important;
    left: var(--pf-sidebar-w) !important;
    height: calc(100vh - var(--pf-header-h) - var(--pf-footer-h)) !important;
    position: fixed !important;
    top: var(--pf-header-h) !important;
    overflow: hidden !important;
    background-color: #0b0d11 !important;
}

body.sidebar-collapsed #pf-main-content {
    width: calc(100% - var(--pf-sidebar-mini-w)) !important;
    left: var(--pf-sidebar-mini-w) !important;
}

@media (max-width: 991px) {
    #pf-main-content {
        width: 100% !important;
        left: 0 !important;
    }
}

.terminal-full-layout {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    background-color: #0b0d11;
    overflow: hidden;
}

/* Top Tab Bar & Utilities */
.terminal-top-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background-color: #12151b;
    border-bottom: 1px solid #1f2530;
    padding: 4px 10px 0 10px;
    height: 38px;
    user-select: none;
    flex-shrink: 0;
}

.terminal-tabs-left {
    display: flex;
    align-items: center;
    gap: 4px;
    height: 100%;
    overflow-x: auto;
}

.terminal-tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    background-color: #1a1e27;
    color: #8c95a6;
    font-size: 12.5px;
    font-weight: 500;
    border-radius: 5px 5px 0 0;
    border: 1px solid #262c38;
    border-bottom: none;
    cursor: pointer;
    transition: all 0.12s ease;
    white-space: nowrap;
    height: 100%;
    box-sizing: border-box;
}

.terminal-tab:hover {
    background-color: #242a36;
    color: #d8e0ed;
}

.terminal-tab.active {
    background-color: #0b0d11;
    color: #4da3ff;
    border-top: 2px solid #4da3ff;
    border-left: 1px solid #1f2530;
    border-right: 1px solid #1f2530;
    font-weight: 600;
}

.terminal-tab .tab-icon {
    font-size: 11px;
    opacity: 0.85;
}

.terminal-tab .tab-close {
    font-size: 13px;
    color: #8c95a6;
    margin-left: 4px;
    border-radius: 50%;
    padding: 1px 4px;
    line-height: 1;
}

.terminal-tab .tab-close:hover {
    background-color: rgba(248, 81, 73, 0.25);
    color: #f85149;
}

.btn-add-terminal {
    background-color: #1f6feb;
    color: #ffffff;
    border: none;
    border-radius: 4px;
    padding: 4px 10px;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-left: 6px;
    margin-bottom: 2px;
    transition: background-color 0.15s ease;
}

.btn-add-terminal:hover {
    background-color: #388bfd;
}

.terminal-top-right {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 2px;
}

.term-tool-btn {
    background: #181d26;
    border: 1px solid #28303e;
    color: #8c95a6;
    padding: 3px 8px;
    font-size: 11px;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.12s ease;
}

.term-tool-btn:hover {
    background: #232a37;
    color: #ffffff;
    border-color: #4da3ff;
}

/* Quick Commands Bar */
.terminal-quick-chips {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    background-color: #0e1117;
    border-bottom: 1px solid #1a202c;
    overflow-x: auto;
    font-size: 11px;
    flex-shrink: 0;
}

.quick-chip-label {
    color: #6e7687;
    font-weight: 600;
    margin-right: 3px;
    white-space: nowrap;
}

.quick-chip {
    background-color: #161b24;
    border: 1px solid #262e3d;
    color: #4da3ff;
    padding: 2px 7px;
    border-radius: 10px;
    cursor: pointer;
    font-family: monospace;
    font-size: 11px;
    white-space: nowrap;
    transition: all 0.12s ease;
}

.quick-chip:hover {
    background-color: #202735;
    border-color: #4da3ff;
    color: #70b7ff;
}

/* Terminal Screen Content */
.terminal-screen-full {
    flex: 1;
    display: flex;
    flex-direction: column;
    background-color: #0b0d11;
    overflow: hidden;
}

.terminal-console-output {
    flex: 1;
    padding: 12px 16px;
    overflow-y: auto;
    font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, Courier, monospace;
    font-size: 13px;
    line-height: 1.5;
    color: #d1d7e0;
    white-space: pre-wrap;
    word-break: break-all;
}

.terminal-console-output::-webkit-scrollbar {
    width: 7px;
}
.terminal-console-output::-webkit-scrollbar-thumb {
    background: #262c38;
    border-radius: 3px;
}

.term-line-cmd {
    color: #56d364;
    font-weight: 600;
}

.term-line-err {
    color: #f85149;
}

/* Bottom Input Row */
.terminal-bottom-input {
    display: flex;
    align-items: center;
    padding: 8px 14px;
    background-color: #12151b;
    border-top: 1px solid #1f2530;
    gap: 8px;
    flex-shrink: 0;
}

.terminal-prompt-label {
    display: inline-flex;
    align-items: center;
    font-family: monospace;
    font-size: 13px;
    font-weight: 700;
    color: #56d364;
    white-space: nowrap;
    user-select: none;
}

.terminal-prompt-cwd {
    color: #79c0ff;
}

.terminal-cmd-input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    color: #f0f6fc;
    font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, Courier, monospace;
    font-size: 13px;
    font-weight: 500;
    caret-color: #4da3ff;
}

.terminal-submit-btn {
    background-color: #238636;
    color: #ffffff;
    border: none;
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: background-color 0.15s ease;
}

.terminal-submit-btn:hover {
    background-color: #2ea043;
}

.terminal-exec-spinner {
    display: none;
    color: #e3b341;
    font-size: 12px;
}
</style>

<div class="terminal-full-layout" id="terminal-layout">
    <!-- Top Strip: Tabs + Controls -->
    <div class="terminal-top-strip">
        <div class="terminal-tabs-left" id="term-tabs-bar">
            <!-- Tabs injected dynamically via JS -->
            <button type="button" class="btn-add-terminal" id="btn-add-tab" title="<?=gettext("Buka Terminal Baru")?>">
                <i class="fa-solid fa-plus"></i>
                <span><?=gettext("Tambah Terminal")?></span>
            </button>
        </div>

        <div class="terminal-top-right">
            <button type="button" class="term-tool-btn" id="btn-font-dec" title="Kecilkan Font">A-</button>
            <button type="button" class="term-tool-btn" id="btn-font-inc" title="Besarkan Font">A+</button>
            <button type="button" class="term-tool-btn" id="btn-clear-term" title="Bersihkan Layar (Ctrl+L)">
                <i class="fa-solid fa-eraser"></i> <?=gettext("Clear")?>
            </button>
        </div>
    </div>

    <!-- Quick Commands Bar -->
    <div class="terminal-quick-chips">
        <span class="quick-chip-label"><i class="fa-solid fa-bolt"></i> <?=gettext("FreeBSD:")?></span>
        <button type="button" class="quick-chip" data-cmd="uptime">uptime</button>
        <button type="button" class="quick-chip" data-cmd="top -b -d 1">top</button>
        <button type="button" class="quick-chip" data-cmd="ifconfig -a">ifconfig</button>
        <button type="button" class="quick-chip" data-cmd="netstat -rn">netstat</button>
        <button type="button" class="quick-chip" data-cmd="pfctl -sr">pfctl rules</button>
        <button type="button" class="quick-chip" data-cmd="pfctl -si">pfctl info</button>
        <button type="button" class="quick-chip" data-cmd="ps aux">ps aux</button>
        <button type="button" class="quick-chip" data-cmd="df -h">df -h</button>
        <button type="button" class="quick-chip" data-cmd="dmesg | tail -n 25">dmesg</button>
        <button type="button" class="quick-chip" data-cmd="uname -mrs">uname</button>
    </div>

    <!-- Terminal Screen Content -->
    <div class="terminal-screen-full" id="terminal-screen">
        <div class="terminal-console-output" id="term-output"></div>

        <div class="terminal-bottom-input">
            <div class="terminal-prompt-label">
                <span>root@pfSense:</span><span class="terminal-prompt-cwd" id="term-prompt-cwd">~</span><span>&nbsp;#&nbsp;</span>
            </div>
            <input type="text" class="terminal-cmd-input" id="term-input" autocomplete="off" spellcheck="false" placeholder="Ketik perintah FreeBSD shell di sini lalu tekan Enter..." />
            <span class="terminal-exec-spinner" id="term-spinner">
                <i class="fa-solid fa-circle-notch fa-spin"></i>
            </span>
            <button type="button" class="terminal-submit-btn" id="btn-run-cmd">
                <i class="fa-solid fa-play"></i> <?=gettext("Run")?>
            </button>
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

    var FREEBSD_SYSTEM_BANNER = <?=json_encode($freebsd_banner)?>;

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
        str = str.replace(/\033\[32m/g, '<span style="color: #56d364;">'); // Green
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

    function getActiveSession() {
        for (var i = 0; i < sessions.length; i++) {
            if (sessions[i].id === activeSessionId) {
                return sessions[i];
            }
        }
        return null;
    }

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

        updateHeaderAndPrompt();
    }

    function updateHeaderAndPrompt() {
        var s = getActiveSession();
        if (!s) return;

        var displayCwd = s.cwd;
        if (displayCwd === '/root') displayCwd = '~';

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

    function createSession() {
        var num = nextSessionNum++;
        var newSession = {
            id: 'term_' + Date.now() + '_' + num,
            name: 'Terminal ' + num,
            cwd: '/root',
            history: [],
            historyIdx: -1,
            content: ansiToHtml(FREEBSD_SYSTEM_BANNER)
        };

        sessions.push(newSession);
        switchSession(newSession.id);
    }

    function switchSession(id) {
        activeSessionId = id;
        renderTabs();
        $('#term-input').focus();
    }

    function closeSession(id) {
        if (sessions.length <= 1) {
            var s = getActiveSession();
            if (s) {
                s.content = ansiToHtml(FREEBSD_SYSTEM_BANNER);
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

    function executeCommand(cmdStr) {
        var s = getActiveSession();
        if (!s) return;

        var cmd = (cmdStr !== undefined) ? cmdStr.trim() : $('#term-input').val().trim();
        if (cmd === '') return;

        if (s.history[s.history.length - 1] !== cmd) {
            s.history.push(cmd);
        }
        s.historyIdx = s.history.length;

        var displayCwd = s.cwd === '/root' ? '~' : s.cwd;
        var promptLine = '<span class="term-line-cmd">root@pfSense:' + escapeHtml(displayCwd) + ' # ' + escapeHtml(cmd) + '</span>\n';
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
                s.content += '<span class="term-line-err">Error: ' + escapeHtml(err || status) + '</span>\n';
                updateHeaderAndPrompt();
                $('#term-input').focus();
            }
        });
    }

    $('#btn-add-tab').on('click', function() {
        createSession();
    });

    $('#btn-run-cmd').on('click', function() {
        executeCommand();
    });

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

    $('#btn-clear-term').on('click', function() {
        var s = getActiveSession();
        if (s) {
            s.content = '';
            updateHeaderAndPrompt();
            $('#term-input').focus();
        }
    });

    $('.quick-chip').on('click', function() {
        var cmd = $(this).data('cmd');
        if (cmd) {
            executeCommand(cmd);
        }
    });

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

    $('#terminal-screen').on('click', function(e) {
        if (!$(e.target).closest('button').length) {
            $('#term-input').focus();
        }
    });

    createSession();
});
//]]>
</script>

<?php include("foot.inc"); ?>
