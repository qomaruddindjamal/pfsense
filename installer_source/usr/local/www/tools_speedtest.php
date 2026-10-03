<?php
##
# tools_speedtest.php
# pfSense WebGUI: Speedtest Tool (Tools > Speedtest)
# Integrated with Ookla CLI & sivel/speedtest-cli from GitHub
# Supports LAN Interface testing with pfSense Outbound NAT
##

// Prevent CSRF blockage on AJAX requests (standard pfSense pattern for AJAX endpoints)
if (isset($_REQUEST['ajax']) && $_REQUEST['ajax'] == '1') {
    $nocsrf = true;
}

require_once("guiconfig.inc");
require_once("interfaces.inc");
require_once("util.inc");

$history_file = "/var/log/speedtest_history.json";

// AJAX Handler
if (isset($_REQUEST['ajax']) && $_REQUEST['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_REQUEST['action'] ?? '';

    if ($action === 'servers') {
        $cmd = "/usr/local/bin/speedtest -L --format=json 2>/dev/null";
        $output = shell_exec($cmd);
        $data = json_decode($output, true);
        echo json_encode([
            'success' => true,
            'servers' => $data['servers'] ?? []
        ]);
        exit;
    }

    if ($action === 'history') {
        $history = [];
        if (file_exists($history_file)) {
            $history = json_decode(file_get_contents($history_file), true) ?: [];
        }
        echo json_encode(['success' => true, 'history' => $history]);
        exit;
    }

    if ($action === 'clear_history') {
        @unlink($history_file);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'run') {
        $engine = $_POST['engine'] ?? 'ookla';
        $server_id = trim($_POST['server_id'] ?? '');
        $interface = trim($_POST['interface'] ?? '');

        // Resolve interface IP if specified
        $bind_ip = '';
        if (!empty($interface)) {
            $bind_ip = trim(shell_exec("ifconfig " . escapeshellarg($interface) . " 2>/dev/null | grep 'inet ' | awk '{print $2}'"));
        }

        if ($engine === 'ookla') {
            $cmd = "/usr/local/bin/speedtest -f json --accept-license --accept-gdpr";
            if (!empty($server_id) && is_numeric($server_id)) {
                $cmd .= " -s " . escapeshellarg($server_id);
            }
            if (!empty($bind_ip)) {
                // Binding to IP directly tests the subnet & Outbound NAT
                $cmd .= " -i " . escapeshellarg($bind_ip);
            } elseif (!empty($interface)) {
                $cmd .= " -I " . escapeshellarg($interface);
            }
            $cmd .= " 2>&1";

            $output = shell_exec($cmd);

            // Locate final result JSON line
            $res = null;
            $res_pos = strpos($output, '{"type":"result"');
            if ($res_pos !== false) {
                $raw_json = substr($output, $res_pos);
                $nl = strpos($raw_json, "\n");
                if ($nl !== false) {
                    $raw_json = substr($raw_json, 0, $nl);
                }
                $res = json_decode($raw_json, true);
            } else {
                // Search lines from bottom
                $lines = explode("\n", trim($output));
                foreach (array_reverse($lines) as $l) {
                    $cand = json_decode(trim($l), true);
                    if (is_array($cand) && isset($cand['download']['bandwidth'])) {
                        $res = $cand;
                        break;
                    }
                }
            }

            if (!empty($res) && isset($res['download']['bandwidth'])) {
                $dl_mbps = round(($res['download']['bandwidth'] * 8) / 1000000, 2);
                $ul_mbps = round(($res['upload']['bandwidth'] * 8) / 1000000, 2);
                $ping_ms = round($res['ping']['latency'] ?? 0, 2);
                $jitter_ms = round($res['ping']['jitter'] ?? 0, 2);
                $loss_pct = round($res['packetLoss'] ?? 0, 1);
                $isp = $res['isp'] ?? 'Unknown ISP';
                $server_name = ($res['server']['name'] ?? 'Auto') . ' (' . ($res['server']['location'] ?? '') . ')';
                $client_ip = $res['interface']['externalIp'] ?? ($res['interface']['internalIp'] ?? '');
                $result_url = $res['result']['url'] ?? '';

                $if_label = !empty($interface) ? $interface : 'Default WAN';
                if (!empty($bind_ip)) {
                    $if_label .= " ($bind_ip)";
                }

                $record = [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'engine' => 'Ookla Native',
                    'interface' => $if_label,
                    'download' => $dl_mbps,
                    'upload' => $ul_mbps,
                    'ping' => $ping_ms,
                    'jitter' => $jitter_ms,
                    'loss' => $loss_pct,
                    'isp' => $isp,
                    'server' => $server_name,
                    'client_ip' => $client_ip,
                    'url' => $result_url
                ];

                // Append to history
                $history = [];
                if (file_exists($history_file)) {
                    $history = json_decode(file_get_contents($history_file), true) ?: [];
                }
                array_unshift($history, $record);
                if (count($history) > 20) {
                    $history = array_slice($history, 0, 20);
                }
                file_put_contents($history_file, json_encode($history, JSON_PRETTY_PRINT));

                echo json_encode([
                    'success' => true,
                    'data' => $record,
                    'raw' => $res
                ]);
                exit;
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Gagal mendapatkan hasil Ookla Speedtest: ' . substr($output, 0, 500)
                ]);
                exit;
            }
        } else {
            // Sivel Python speedtest-cli
            $cmd = "/usr/local/bin/python3 /usr/local/bin/speedtest-cli --json";
            if (!empty($server_id) && is_numeric($server_id)) {
                $cmd .= " --server " . escapeshellarg($server_id);
            }
            if (!empty($bind_ip)) {
                $cmd .= " --source " . escapeshellarg($bind_ip);
            }
            $cmd .= " 2>&1";

            $output = shell_exec($cmd);
            $res = json_decode($output, true);

            if (!empty($res) && isset($res['download'])) {
                $dl_mbps = round($res['download'] / 1000000, 2);
                $ul_mbps = round($res['upload'] / 1000000, 2);
                $ping_ms = round($res['ping'] ?? 0, 2);
                $isp = $res['client']['isp'] ?? 'Unknown ISP';
                $server_name = ($res['server']['sponsor'] ?? '') . ' - ' . ($res['server']['name'] ?? '');
                $client_ip = $res['client']['ip'] ?? '';
                $result_url = $res['share'] ?? '';

                $if_label = !empty($interface) ? $interface : 'Default WAN';
                if (!empty($bind_ip)) {
                    $if_label .= " ($bind_ip)";
                }

                $record = [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'engine' => 'Python speedtest-cli',
                    'interface' => $if_label,
                    'download' => $dl_mbps,
                    'upload' => $ul_mbps,
                    'ping' => $ping_ms,
                    'jitter' => '-',
                    'loss' => '-',
                    'isp' => $isp,
                    'server' => $server_name,
                    'client_ip' => $client_ip,
                    'url' => $result_url
                ];

                $history = [];
                if (file_exists($history_file)) {
                    $history = json_decode(file_get_contents($history_file), true) ?: [];
                }
                array_unshift($history, $record);
                if (count($history) > 20) {
                    $history = array_slice($history, 0, 20);
                }
                file_put_contents($history_file, json_encode($history, JSON_PRETTY_PRINT));

                echo json_encode([
                    'success' => true,
                    'data' => $record,
                    'raw' => $res
                ]);
                exit;
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Gagal mendapatkan hasil speedtest-cli: ' . substr($output, 0, 500)
                ]);
                exit;
            }
        }
    }

    echo json_encode(['success' => false, 'error' => 'Aksi tidak dikenal']);
    exit;
}

$pgtitle = array(gettext("Tools"), gettext("Speedtest"));
include("head.inc");

// Load existing history server-side for initial render
$initial_history = [];
if (file_exists($history_file)) {
    $initial_history = json_decode(file_get_contents($history_file), true) ?: [];
}
// Default values start at 0 until user clicks Start Test


// Build interface list dynamically
$configured_interfaces = get_configured_interface_with_descr();
$interfaces_list = [
    '' => 'Default Gateway (Automatic Routing)'
];

// Highlight LAN first for Outbound NAT testing
foreach ($configured_interfaces as $ifkey => $ifdescr) {
    $realif = get_real_interface($ifkey);
    $ip = get_interface_ip($ifkey);
    $ip_str = $ip ? " - $ip" : "";
    $is_lan = (strtolower($ifkey) === 'lan' || strtolower($ifdescr) === 'lan');
    
    if ($is_lan) {
        $interfaces_list[$realif] = "LAN ($realif$ip_str) [Uji Outbound NAT]";
    } else {
        $interfaces_list[$realif] = strtoupper($ifdescr) . " ($realif$ip_str)";
    }
}

// Check WireGuard tun_wg0
$wg_ip = trim(shell_exec("ifconfig tun_wg0 2>/dev/null | grep 'inet ' | awk '{print $2}'"));
if (!empty($wg_ip)) {
    $interfaces_list['tun_wg0'] = "WireGuard VPN (tun_wg0 - $wg_ip) [Tunnel VPN]";
}
?>

<style>
.speedtest-container {
    padding: 10px 0;
}
.kpi-card {
    background: #ffffff;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    border: 1px solid #e2e8f0;
    margin-bottom: 20px;
    transition: transform 0.2s, box-shadow 0.2s;
    text-align: center;
}
body.theme-dark .kpi-card,
body.theme-matrix .kpi-card {
    background: #1e293b;
    border-color: #334155;
    color: #f8fafc;
}
.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.kpi-label {
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    margin-bottom: 8px;
}
body.theme-dark .kpi-label,
body.theme-matrix .kpi-label {
    color: #94a3b8;
}
.kpi-value {
    font-size: 38px;
    font-weight: 700;
    line-height: 1.1;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
.kpi-unit {
    font-size: 16px;
    font-weight: 500;
    color: #64748b;
    margin-left: 4px;
}
.kpi-dl { color: #0284c7; }
.kpi-ul { color: #16a34a; }
.kpi-ping { color: #d97706; }
.kpi-jitter { color: #9333ea; }

.status-badge {
    display: inline-block;
    padding: 8px 18px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    background: #f1f5f9;
    color: #475569;
    transition: all 0.3s;
}
body.theme-dark .status-badge {
    background: #334155;
    color: #cbd5e1;
}
.status-badge.running {
    background: #e0f2fe;
    color: #0369a1;
    box-shadow: 0 0 10px rgba(2,132,199,0.3);
    animation: pulse 1.5s infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.85; transform: scale(1.02); }
}

.btn-speedtest-start {
    font-size: 16px;
    font-weight: 600;
    padding: 12px 28px;
    border-radius: 8px;
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
    border: none;
    color: #fff;
    box-shadow: 0 4px 10px rgba(2,132,199,0.3);
    transition: all 0.2s;
    cursor: pointer;
}
.btn-speedtest-start:hover:not(:disabled) {
    background: linear-gradient(135deg, #0369a1 0%, #075985 100%);
    box-shadow: 0 6px 14px rgba(2,132,199,0.4);
    transform: translateY(-1px);
    color: #fff;
}
.btn-speedtest-start:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.speed-progress-bar {
    height: 10px;
    border-radius: 5px;
    background: #e2e8f0;
    overflow: hidden;
    margin: 15px 0;
    display: none;
}
.speed-progress-inner {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, #0284c7, #16a34a);
    transition: width 0.4s ease;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
}
body.theme-dark .info-item {
    border-bottom-color: #334155;
}
.info-item:last-child { border-bottom: none; }
.info-title { color: #64748b; font-weight: 500; }
body.theme-dark .info-title { color: #94a3b8; }
.info-val { font-weight: 600; }
</style>

<div class="panel panel-default speedtest-container">
    <div class="panel-heading">
        <h2 class="panel-title">
            <i class="fa-solid fa-gauge-high"></i> &nbsp;<?=gettext("Speedtest Internet Bandwidth & Latency Tool")?>
            <span class="pull-right">
                <span class="label label-info"><i class="fa-solid fa-bolt"></i> Ookla Official CLI 1.2</span>
                <span class="label label-success"><i class="fa-brands fa-github"></i> GitHub CLI</span>
            </span>
        </h2>
    </div>
    <div class="panel-body">
        
        <!-- Controls & Options -->
        <div class="row" style="margin-bottom: 20px;">
            <div class="col-md-3">
                <label for="engine-select"><strong><?=gettext("Engine:")?></strong></label>
                <select id="engine-select" class="form-control">
                    <option value="ookla" selected>Ookla Official Native (Multi-stream, Fast)</option>
                    <option value="sivel">Speedtest-CLI by Sivel (GitHub Python)</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="interface-select"><strong><?=gettext("Interface / Jalur Routing:")?></strong></label>
                <select id="interface-select" class="form-control">
                    <?php foreach ($interfaces_list as $if_key => $if_lbl): ?>
                        <option value="<?=htmlspecialchars($if_key)?>" <?=($if_key === 'vtnet1') ? 'selected style="font-weight:bold; color:#0284c7;"' : ''?>>
                            <?=htmlspecialchars($if_lbl)?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted"><i class="fa-solid fa-info-circle"></i> Pilih <strong>LAN</strong> untuk menguji throughput yang melewati aturan Outbound NAT pfSense.</small>
            </div>
            <div class="col-md-3">
                <label for="server-select"><strong><?=gettext("Test Server:")?></strong></label>
                <select id="server-select" class="form-control">
                    <option value=""><?=gettext("Auto (Best Latency Server)")?></option>
                </select>
            </div>
            <div class="col-md-2" style="padding-top: 24px;">
                <button type="button" id="btn-start" class="btn btn-speedtest-start btn-block">
                    <i class="fa-solid fa-play"></i> &nbsp;<?=gettext("Start Test")?>
                </button>
            </div>
        </div>

        <!-- Progress Bar & Status -->
        <div class="row">
            <div class="col-md-12 text-center" style="margin-bottom: 15px;">
                <span id="test-status" class="status-badge">
                    <i class="fa-solid fa-circle-play text-primary"></i> <?=gettext("Siap pengujian (Klik 'Start Test')")?>
                </span>
            </div>
            <div class="col-md-12">
                <div id="speed-progress" class="speed-progress-bar">
                    <div id="speed-progress-inner" class="speed-progress-inner"></div>
                </div>
            </div>
        </div>

        <!-- KPI Cards (Default 0 sampai tombol Start Test diklik) -->
        <div class="row">
            <div class="col-md-3 col-sm-6">
                <div class="kpi-card">
                    <div class="kpi-label"><i class="fa-solid fa-download"></i> <?=gettext("Download")?></div>
                    <div class="kpi-value kpi-dl">
                        <span id="val-dl">0.00</span><span class="kpi-unit">Mbps</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="kpi-card">
                    <div class="kpi-label"><i class="fa-solid fa-upload"></i> <?=gettext("Upload")?></div>
                    <div class="kpi-value kpi-ul">
                        <span id="val-ul">0.00</span><span class="kpi-unit">Mbps</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="kpi-card">
                    <div class="kpi-label"><i class="fa-solid fa-stopwatch"></i> <?=gettext("Ping / Latency")?></div>
                    <div class="kpi-value kpi-ping">
                        <span id="val-ping">0</span><span class="kpi-unit">ms</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="kpi-card">
                    <div class="kpi-label"><i class="fa-solid fa-wave-square"></i> <?=gettext("Jitter")?></div>
                    <div class="kpi-value kpi-jitter">
                        <span id="val-jitter">0</span><span class="kpi-unit">ms</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Connection Info -->
        <div class="row">
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading"><h3 class="panel-title"><i class="fa-solid fa-circle-info"></i> <?=gettext("Connection Details")?></h3></div>
                    <div class="panel-body">
                        <div class="info-item">
                            <span class="info-title"><?=gettext("ISP Provider:")?></span>
                            <span class="info-val" id="det-isp">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-title"><?=gettext("Client IP:")?></span>
                            <span class="info-val" id="det-ip">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-title"><?=gettext("Packet Loss:")?></span>
                            <span class="info-val" id="det-loss">-</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading"><h3 class="panel-title"><i class="fa-solid fa-server"></i> <?=gettext("Target Server")?></h3></div>
                    <div class="panel-body">
                        <div class="info-item">
                            <span class="info-title"><?=gettext("Server Sponsor:")?></span>
                            <span class="info-val" id="det-server">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-title"><?=gettext("Tested Interface:")?></span>
                            <span class="info-val" id="det-if">-</span>
                        </div>
                        <div class="info-item">
                            <span class="info-title"><?=gettext("Online Result:")?></span>
                            <span class="info-val" id="det-url">-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- History Table -->
        <div class="panel panel-default" style="margin-top: 10px;">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="fa-solid fa-clock-rotate-left"></i> <?=gettext("Speedtest History (Last 20 Tests)")?>
                    <button type="button" id="btn-clear-history" class="btn btn-xs btn-default pull-right">
                        <i class="fa-solid fa-trash"></i> <?=gettext("Clear History")?>
                    </button>
                </h3>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th><?=gettext("Time")?></th>
                            <th><?=gettext("Engine")?></th>
                            <th><?=gettext("Interface")?></th>
                            <th><?=gettext("Server")?></th>
                            <th><?=gettext("Ping")?></th>
                            <th><?=gettext("Download")?></th>
                            <th><?=gettext("Upload")?></th>
                            <th><?=gettext("Link")?></th>
                        </tr>
                    </thead>
                    <tbody id="history-tbody">
                        <?php if (empty($initial_history)): ?>
                            <tr><td colspan="8" class="text-center text-muted" style="padding: 20px; font-style: italic;"><?=gettext("Belum ada data pengujian. Silakan klik tombol 'Start Test' untuk melakukan pengujian kecepatan riil.")?></td></tr>
                        <?php else: ?>
                            <?php foreach ($initial_history as $h): ?>
                                <tr>
                                    <td><?=htmlspecialchars($h['timestamp'])?></td>
                                    <td><span class="label label-default"><?=htmlspecialchars($h['engine'])?></span></td>
                                    <td><?=htmlspecialchars($h['interface'])?></td>
                                    <td><?=htmlspecialchars($h['server'])?></td>
                                    <td><strong><?=htmlspecialchars($h['ping'])?></strong> ms</td>
                                    <td><strong class="text-primary"><?=htmlspecialchars($h['download'])?></strong> Mbps</td>
                                    <td><strong class="text-success"><?=htmlspecialchars($h['upload'])?></strong> Mbps</td>
                                    <td>
                                        <?php if (!empty($h['url'])): ?>
                                            <a href="<?=htmlspecialchars($h['url'])?>" target="_blank" class="btn btn-xs btn-info"><i class="fa-solid fa-arrow-up-right-from-square"></i> Result</a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script type="text/javascript">
//<![CDATA[
$(document).ready(function() {
    // 1. Load Server list on init
    $.ajax({
        url: '/tools_speedtest.php',
        type: 'GET',
        data: { ajax: '1', action: 'servers' },
        dataType: 'json',
        success: function(res) {
            if (res && res.success && res.servers && res.servers.length > 0) {
                var sel = $('#server-select');
                res.servers.forEach(function(s) {
                    sel.append($('<option>', {
                        value: s.id,
                        text: s.name + ' - ' + s.location + ' (' + s.country + ')'
                    }));
                });
            }
        }
    });

    // 2. Function to refresh history table
    function refreshHistory() {
        $.ajax({
            url: '/tools_speedtest.php',
            type: 'GET',
            data: { ajax: '1', action: 'history' },
            dataType: 'json',
            success: function(res) {
                if (res && res.success && res.history && res.history.length > 0) {
                    var tbody = $('#history-tbody');
                    tbody.empty();
                    res.history.forEach(function(h) {
                        var linkHtml = h.url ? '<a href="' + h.url + '" target="_blank" class="btn btn-xs btn-info"><i class="fa-solid fa-arrow-up-right-from-square"></i> Result</a>' : '-';
                        var row = $('<tr>');
                        row.append($('<td>').text(h.timestamp));
                        row.append($('<td>').html('<span class="label label-default">' + h.engine + '</span>'));
                        row.append($('<td>').text(h.interface));
                        row.append($('<td>').text(h.server));
                        row.append($('<td>').html('<strong>' + h.ping + '</strong> ms'));
                        row.append($('<td>').html('<strong class="text-primary">' + h.download + '</strong> Mbps'));
                        row.append($('<td>').html('<strong class="text-success">' + h.upload + '</strong> Mbps'));
                        row.append($('<td>').html(linkHtml));
                        tbody.append(row);
                    });
                }
            }
        });
    }

    // 3. Start Speedtest button handler
    $('#btn-start').on('click', function(e) {
        e.preventDefault();

        var btn = $(this);
        btn.prop('disabled', true);
        btn.html('<i class="fa-solid fa-spinner fa-spin"></i> &nbsp;Testing...');

        $('#test-status').removeClass().addClass('status-badge running').html('<i class="fa-solid fa-spinner fa-spin"></i> Sedang Menjalankan Pengujian (15-30 detik)...');
        $('#speed-progress').show();
        $('#speed-progress-inner').css('width', '20%');

        var engine = $('#engine-select').val();
        var iface = $('#interface-select').val();
        var srv = $('#server-select').val();

        // Animated progress increment while test is running
        var progressVal = 20;
        var progressTimer = setInterval(function() {
            progressVal += 10;
            if (progressVal > 85) progressVal = 85;
            $('#speed-progress-inner').css('width', progressVal + '%');
        }, 2000);

        $.ajax({
            url: '/tools_speedtest.php',
            type: 'POST',
            data: {
                ajax: '1',
                action: 'run',
                engine: engine,
                interface: iface,
                server_id: srv
            },
            dataType: 'json',
            timeout: 120000,
            success: function(res) {
                clearInterval(progressTimer);
                $('#speed-progress-inner').css('width', '100%');
                setTimeout(function() { $('#speed-progress').fadeOut(); }, 1200);

                btn.prop('disabled', false);
                btn.html('<i class="fa-solid fa-play"></i> &nbsp;Start Test');

                if (res && res.success && res.data) {
                    var d = res.data;
                    $('#test-status').removeClass().addClass('status-badge').html('<i class="fa-solid fa-circle-check text-success"></i> Pengujian Selesai!');

                    $('#val-dl').text(d.download);
                    $('#val-ul').text(d.upload);
                    $('#val-ping').text(d.ping);
                    $('#val-jitter').text(d.jitter);

                    $('#det-isp').text(d.isp);
                    $('#det-ip').text(d.client_ip);
                    if (d.loss !== '-' && d.loss !== null && d.loss !== undefined) {
                        $('#det-loss').text(d.loss + '%');
                    } else {
                        $('#det-loss').text('-');
                    }
                    $('#det-server').text(d.server);
                    $('#det-if').text(d.interface);

                    if (d.url) {
                        $('#det-url').html('<a href="' + d.url + '" target="_blank" class="btn btn-xs btn-info"><i class="fa-solid fa-link"></i> View Certificate</a>');
                    } else {
                        $('#det-url').text('-');
                    }

                    refreshHistory();
                } else {
                    var errMsg = (res && res.error) ? res.error : 'Pengujian gagal mendapatkan hasil';
                    $('#test-status').removeClass().addClass('status-badge').html('<i class="fa-solid fa-triangle-exclamation text-danger"></i> Error: ' + errMsg);
                    alert(errMsg);
                }
            },
            error: function(xhr, status, err) {
                clearInterval(progressTimer);
                $('#speed-progress').fadeOut();
                btn.prop('disabled', false);
                btn.html('<i class="fa-solid fa-play"></i> &nbsp;Start Test');
                var errMsg = 'Koneksi pengujian gagal (' + (err || status) + '). Status HTTP: ' + xhr.status;
                $('#test-status').removeClass().addClass('status-badge').html('<i class="fa-solid fa-triangle-exclamation text-danger"></i> ' + errMsg);
                alert(errMsg);
            }
        });
    });

    // 4. Clear History button handler
    $('#btn-clear-history').on('click', function(e) {
        e.preventDefault();
        if (confirm('Hapus seluruh riwayat pengujian Speedtest?')) {
            $.ajax({
                url: '/tools_speedtest.php',
                type: 'POST',
                data: {
                    ajax: '1',
                    action: 'clear_history'
                },
                dataType: 'json',
                success: function() {
                    $('#history-tbody').html('<tr><td colspan="8" class="text-center text-muted" style="padding: 20px; font-style: italic;">Belum ada data pengujian. Silakan klik tombol "Start Test" untuk melakukan pengujian kecepatan riil.</td></tr>');
                    $('#val-dl').text('0.00');
                    $('#val-ul').text('0.00');
                    $('#val-ping').text('0');
                    $('#val-jitter').text('0');
                    $('#det-isp').text('-');
                    $('#det-ip').text('-');
                    $('#det-loss').text('-');
                    $('#det-server').text('-');
                    $('#det-if').text('-');
                    $('#det-url').text('-');
                }
            });
        }
    });
});
//]]>
</script>

<?php
include("foot.inc");
?>
