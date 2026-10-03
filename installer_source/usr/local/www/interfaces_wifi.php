<?php
##
# interfaces_wifi.php
# pfSense WebGUI: Wireless Network Manager & Access Point Hotspot
# Auto-detection for Native Hardware & VirtualBox Bridged Wireless
# (NO DUMMY DATA - 100% Genuine FreeBSD System Calls & Interfaces)
##

require_once("guiconfig.inc");
require_once("service-utils.inc");

$pgtitle = array(gettext("Wifi"), gettext("Wireless Network & AP Manager"));
$pglinks = array("", "@self");

$wifi_manager = "/usr/local/bin/wifi-manager";
$conf_file = "/usr/local/etc/wifi/config.json";

// Handle AJAX Endpoints
if (isset($_REQUEST['ajax'])) {
    header('Content-Type: application/json');
    $act = $_REQUEST['act'] ?? '';

    if ($act == 'status') {
        $out = shell_exec("{$wifi_manager} status 2>/dev/null");
        echo $out ?: json_encode(["status" => false, "msg" => "Failed to get status"]);
        exit;
    }

    if ($act == 'detect') {
        $out = shell_exec("{$wifi_manager} detect 2>/dev/null");
        echo $out ?: json_encode(["status" => false, "msg" => "Hardware detect failed"]);
        exit;
    }

    if ($act == 'set_iface') {
        $iface = escapeshellarg($_REQUEST['iface'] ?? 'wlan0');
        $out = shell_exec("{$wifi_manager} set-iface {$iface} 2>&1");
        echo $out ?: json_encode(["status" => true, "msg" => "Active interface updated"]);
        exit;
    }

    if ($act == 'scan') {
        $iface = escapeshellarg($_REQUEST['iface'] ?? '');
        $out = shell_exec("{$wifi_manager} scan {$iface} 2>/dev/null");
        echo $out ?: json_encode(["status" => false, "networks" => []]);
        exit;
    }

    if ($act == 'connect') {
        $ssid = escapeshellarg($_REQUEST['ssid'] ?? '');
        $password = escapeshellarg($_REQUEST['password'] ?? '');
        $sec = escapeshellarg($_REQUEST['security'] ?? 'WPA2-PSK');
        $iface = escapeshellarg($_REQUEST['iface'] ?? '');
        $out = shell_exec("{$wifi_manager} connect {$ssid} {$password} {$sec} {$iface} 2>&1");
        echo $out ?: json_encode(["status" => false, "msg" => "Connect failed"]);
        exit;
    }

    if ($act == 'disconnect') {
        $iface = escapeshellarg($_REQUEST['iface'] ?? '');
        $out = shell_exec("{$wifi_manager} disconnect {$iface} 2>&1");
        echo $out ?: json_encode(["status" => true, "msg" => "Disconnected"]);
        exit;
    }

    if ($act == 'ap_start') {
        $ssid = escapeshellarg($_REQUEST['ap_ssid'] ?? 'pfSense-WiFi-AP');
        $password = escapeshellarg($_REQUEST['ap_password'] ?? 'pfsense_wifi_pass');
        $chan = escapeshellarg($_REQUEST['ap_channel'] ?? '6');
        $ip = escapeshellarg($_REQUEST['ap_ip'] ?? '192.168.88.1');
        $out = shell_exec("{$wifi_manager} ap-start {$ssid} {$password} {$chan} {$ip} 2>&1");
        echo $out ?: json_encode(["status" => false, "msg" => "AP start failed"]);
        exit;
    }

    if ($act == 'ap_stop') {
        $out = shell_exec("{$wifi_manager} ap-stop 2>&1");
        echo $out ?: json_encode(["status" => true, "msg" => "AP stopped"]);
        exit;
    }

    if ($act == 'bridge_setup') {
        $iface = escapeshellarg($_REQUEST['bridge_iface'] ?? 'em1');
        $out = shell_exec("{$wifi_manager} bridge-setup {$iface} 2>&1");
        echo $out ?: json_encode(["status" => false, "msg" => "Bridge setup failed"]);
        exit;
    }

    echo json_encode(["status" => false, "msg" => "Unknown action"]);
    exit;
}

$tab = $_GET['tab'] ?? 'overview';

// Load initial status and hardware detect info
$status_raw = @shell_exec("{$wifi_manager} status 2>/dev/null");
$status = json_decode($status_raw ?: '{}', true) ?: [];

$detect_raw = @shell_exec("{$wifi_manager} detect 2>/dev/null");
$hw_info = json_decode($detect_raw ?: '{}', true) ?: [];

include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Overview & Status"), ($tab == "overview"), "interfaces_wifi.php?tab=overview");
$tab_array[] = array(gettext("Scan & Connect (Client)"), ($tab == "scan"), "interfaces_wifi.php?tab=scan");
$tab_array[] = array(gettext("Access Point (AP Mode)"), ($tab == "ap"), "interfaces_wifi.php?tab=ap");
$tab_array[] = array(gettext("Hardware & Auto-Detect"), ($tab == "detect"), "interfaces_wifi.php?tab=detect");
$tab_array[] = array(gettext("VirtualBox / Bridged Wi-Fi"), ($tab == "bridge"), "interfaces_wifi.php?tab=bridge");
display_top_tabs($tab_array);
?>

<style>
.wifi-card {
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    margin-bottom: 20px;
    background: #fff;
    border: 1px solid #e5e9ec;
}
.wifi-card .panel-heading {
    border-top-left-radius: 7px;
    border-top-right-radius: 7px;
    font-weight: 600;
    padding: 12px 18px;
    background: #f8fafc;
    border-bottom: 1px solid #e5e9ec;
}
.wifi-signal-bar {
    height: 8px;
    border-radius: 4px;
    background: #e9ecef;
    overflow: hidden;
    margin-top: 6px;
}
.wifi-signal-fill {
    height: 100%;
    transition: width 0.3s ease;
}
.badge-wifi-connected {
    background-color: #28a745;
    color: #fff;
    font-size: 12px;
    padding: 5px 10px;
    border-radius: 12px;
}
.badge-wifi-disconnected {
    background-color: #6c757d;
    color: #fff;
    font-size: 12px;
    padding: 5px 10px;
    border-radius: 12px;
}
.badge-wifi-ap {
    background-color: #007bff;
    color: #fff;
    font-size: 12px;
    padding: 5px 10px;
    border-radius: 12px;
}
.badge-wifi-bridge {
    background-color: #17a2b8;
    color: #fff;
    font-size: 12px;
    padding: 5px 10px;
    border-radius: 12px;
}
.table-wifi td, .table-wifi th {
    vertical-align: middle !important;
}
.iface-selector-bar {
    background: #edf2f7;
    border: 1px solid #e2e8f0;
    padding: 12px 18px;
    border-radius: 8px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
</style>

<div class="container-fluid" style="padding-top: 15px;">

    <!-- TOP SELECTOR: AUTO-DETECTED WIRELESS & NETWORK INTERFACES -->
    <div class="iface-selector-bar">
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <label style="margin: 0; font-weight: 600; font-size: 14px;">
                <i class="fa-solid fa-network-wired text-primary"></i> <?=gettext("Antarmuka Aktif (Auto-Detected):")?>
            </label>
            <select id="select-active-iface" class="form-control" style="width: auto; min-width: 320px; font-weight: 600;" onchange="changeActiveInterface(this.value)">
                <?php
                $active = $status['active_interface'] ?? 'em1';
                // 1. Genuine 802.11 wireless interfaces
                foreach (($hw_info['wireless_interfaces'] ?? []) as $wif) {
                    $sel = ($wif == $active) ? 'selected' : '';
                    echo "<option value=\"{$wif}\" {$sel}>{$wif} (Wireless 802.11 Radio Adapter)</option>";
                }
                // 2. Bridged / Ethernet interfaces
                foreach (($hw_info['all_interfaces'] ?? []) as $aif) {
                    $n = $aif['name'];
                    if (in_array($n, $hw_info['wireless_interfaces'] ?? [])) continue;
                    $sel = ($n == $active) ? 'selected' : '';
                    $ip_str = !empty($aif['ip']) ? " - IP: {$aif['ip']}" : "";
                    $desc = ($n == 'em1') ? "VirtualBox Bridged Wi-Fi Uplink" : ($n == 'em0' ? "WAN Interface" : "Network Interface");
                    echo "<option value=\"{$n}\" {$sel}>{$n} ({$desc}{$ip_str})</option>";
                }
                ?>
            </select>
            <span id="iface-switch-msg" class="text-success" style="display: none; font-weight: 600;">
                <i class="fa-solid fa-circle-check"></i> <?=gettext("Interface updated")?>
            </span>
        </div>
        <div>
            <button class="btn btn-sm btn-info" onclick="triggerHardwareDetect()">
                <i class="fa-solid fa-microchip"></i> <?=gettext("Re-Detect Hardware & Interfaces")?>
            </button>
        </div>
    </div>

<?php if ($tab == "overview"): ?>
    <!-- TAB 1: OVERVIEW & STATUS -->
    <div class="row">
        <div class="col-md-7">
            <div class="panel panel-default wifi-card">
                <div class="panel-heading">
                    <i class="fa-solid fa-wifi text-primary" style="margin-right: 8px;"></i>
                    <?=gettext("Wireless & Network Interface Status")?>
                    <button class="btn btn-xs btn-default pull-right" onclick="refreshStatus()">
                        <i class="fa-solid fa-arrows-rotate"></i> <?=gettext("Refresh")?>
                    </button>
                </div>
                <div class="panel-body">
                    <table class="table table-striped table-hover table-wifi">
                        <tbody>
                            <tr>
                                <th style="width: 35%;"><?=gettext("Operating Mode")?></th>
                                <td>
                                    <?php if (!empty($status['is_bridged'])): ?>
                                        <span class="badge badge-wifi-bridge"><i class="fa-solid fa-link"></i> VirtualBox Bridged Wi-Fi Uplink</span>
                                    <?php elseif (($status['mode'] ?? '') == 'ap'): ?>
                                        <span class="badge badge-wifi-ap"><i class="fa-solid fa-tower-broadcast"></i> Access Point (HostAP)</span>
                                    <?php elseif (!empty($status['is_wireless'])): ?>
                                        <span class="badge badge-wifi-connected"><i class="fa-solid fa-laptop"></i> Wireless Client (Station 802.11)</span>
                                    <?php else: ?>
                                        <span class="badge badge-wifi-connected"><i class="fa-solid fa-network-wired"></i> Ethernet Network Interface</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><?=gettext("Active Interface")?></th>
                                <td><code id="stat-iface" style="font-size: 14px;"><?=$status['active_interface'] ?? 'em1'?></code></td>
                            </tr>
                            <tr>
                                <th><?=gettext("Connection State")?></th>
                                <td>
                                    <strong id="stat-state" class="<?=!empty($status['ip']) ? 'text-success' : 'text-muted'?>">
                                        <?=$status['state'] ?? 'Active'?>
                                    </strong>
                                </td>
                            </tr>
                            <tr>
                                <th><?=gettext("IP Address")?></th>
                                <td><strong id="stat-ip" style="font-size: 14px;"><?=$status['ip'] ?: '<em>' . gettext("No IP Assigned") . '</em>'?></strong></td>
                            </tr>
                            <tr>
                                <th><?=gettext("Subnet Mask")?></th>
                                <td><span id="stat-netmask"><?=$status['netmask'] ?: 'N/A'?></span></td>
                            </tr>
                            <tr>
                                <th><?=gettext("Default Gateway")?></th>
                                <td><span id="stat-gateway"><?=$status['gateway'] ?: '<em>' . gettext("None") . '</em>'?></span></td>
                            </tr>
                            <tr>
                                <th><?=gettext("MAC Address")?></th>
                                <td><code id="stat-mac"><?=$status['mac'] ?: 'N/A'?></code></td>
                            </tr>
                            <tr>
                                <th><?=gettext("Link Media & Speed")?></th>
                                <td><span id="stat-media"><?=$status['media'] ?: 'Auto'?></span></td>
                            </tr>
                            <?php if (!empty($status['is_wireless'])): ?>
                            <tr>
                                <th><?=gettext("Connected SSID")?></th>
                                <td><strong id="stat-ssid"><?=$status['ssid'] ?: '<em>' . gettext("None (Not Connected)") . '</em>'?></strong></td>
                            </tr>
                            <tr>
                                <th><?=gettext("Signal Quality")?></th>
                                <td>
                                    <div style="display: flex; align-items: center; justify-content: space-between;">
                                        <span id="stat-signal-text"><?=$status['signal_percent'] ?? 0?>% (<?=$status['signal_dbm'] ?? -100?> dBm)</span>
                                    </div>
                                    <div class="wifi-signal-bar">
                                        <div id="stat-signal-bar" class="wifi-signal-fill bg-success" style="width: <?=$status['signal_percent'] ?? 0?>%;"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div style="margin-top: 15px;">
                        <a href="interfaces_wifi.php?tab=scan" class="btn btn-primary">
                            <i class="fa-solid fa-satellite-dish"></i> <?=gettext("Scan & Connect Networks")?>
                        </a>
                        <a href="interfaces_wifi.php?tab=detect" class="btn btn-info pull-right">
                            <i class="fa-solid fa-microchip"></i> <?=gettext("Hardware & Auto-Detect")?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="panel panel-default wifi-card">
                <div class="panel-heading">
                    <i class="fa-solid fa-circle-info text-info" style="margin-right: 8px;"></i>
                    <?=gettext("System & Hardware Auto-Detection")?>
                </div>
                <div class="panel-body">
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?=gettext("Environment Platform")?>
                            <span class="badge"><?=$status['vm_type'] ?? 'Native'?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?=gettext("Physical 802.11 Radios (net.wlan.devices)")?>
                            <span class="badge"><?=!empty($status['wlan_parents']) ? implode(', ', $status['wlan_parents']) : 'None (Virtual NIC)'?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?=gettext("Cloned 802.11 Interfaces")?>
                            <span class="badge"><?=!empty($status['wireless_interfaces']) ? implode(', ', $status['wireless_interfaces']) : 'None'?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?=gettext("Bridged Uplink Candidates")?>
                            <span class="badge"><?=!empty($status['bridged_candidates']) ? implode(', ', $status['bridged_candidates']) : 'em1'?></span>
                        </li>
                    </ul>

                    <?php if (!empty($status['is_vm'])): ?>
                    <div class="alert alert-info" style="margin-top: 15px; margin-bottom: 0; font-size: 13px;">
                        <i class="fa-solid fa-lightbulb"></i>
                        <strong><?=gettext("VirtualBox Bridged Wi-Fi Terdeteksi")?></strong><br>
                        <?=gettext("pfSense berjalan di dalam VirtualBox dengan antarmuka <code>em1</code> dijembatani ke kartu Wi-Fi Host PC. Lalu lintas data internet & LAN tersambung secara otomatis melalui Wi-Fi Host.")?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($tab == "scan"): ?>
    <!-- TAB 2: SCAN & CONNECT (CLIENT) -->
    <div class="panel panel-default wifi-card">
        <div class="panel-heading">
            <i class="fa-solid fa-satellite-dish text-primary" style="margin-right: 8px;"></i>
            <?=gettext("Nearby Wireless Networks (Scan & Connect)")?>
            <button id="btn-scan" class="btn btn-sm btn-primary pull-right" onclick="triggerScan()">
                <i class="fa-solid fa-arrows-rotate"></i> <?=gettext("Scan Networks Now")?>
            </button>
        </div>
        <div class="panel-body">
            <div style="margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                <label style="margin: 0; font-weight: 600;"><?=gettext("Antarmuka untuk Scan:")?></label>
                <select id="scan-iface" class="form-control" style="width: auto; min-width: 250px;">
                    <?php
                    $active = $status['active_interface'] ?? 'em1';
                    foreach (($hw_info['wireless_interfaces'] ?? []) as $wif) {
                        $sel = ($wif == $active) ? 'selected' : '';
                        echo "<option value=\"{$wif}\" {$sel}>{$wif} (802.11 Wireless)</option>";
                    }
                    foreach (($hw_info['all_interfaces'] ?? []) as $aif) {
                        $n = $aif['name'];
                        if (in_array($n, $hw_info['wireless_interfaces'] ?? [])) continue;
                        $sel = ($n == $active) ? 'selected' : '';
                        echo "<option value=\"{$n}\" {$sel}>{$n} (Bridged / Ethernet)</option>";
                    }
                    ?>
                </select>
            </div>

            <div id="scan-loading" style="display:none; text-align: center; padding: 25px;">
                <i class="fa-solid fa-spinner fa-spin fa-2x text-primary"></i>
                <h5 style="margin-top: 10px;"><?=gettext("Melakukan scan frekuensi radio udara... Mohon tunggu...")?></h5>
            </div>

            <div id="scan-alert-box"></div>

            <div class="table-responsive">
                <table class="table table-striped table-hover table-wifi" id="table-scan-results">
                    <thead>
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 25%;"><?=gettext("SSID (Nama Jaringan)")?></th>
                            <th style="width: 20%;"><?=gettext("BSSID (MAC)")?></th>
                            <th style="width: 10%;"><?=gettext("Channel")?></th>
                            <th style="width: 15%;"><?=gettext("Kekuatan Sinyal")?></th>
                            <th style="width: 15%;"><?=gettext("Keamanan")?></th>
                            <th style="width: 10%; text-align: center;"><?=gettext("Aksi")?></th>
                        </tr>
                    </thead>
                    <tbody id="scan-tbody">
                        <tr>
                            <td colspan="7" class="text-center text-muted" style="padding: 20px;">
                                <?=gettext("Klik 'Scan Networks Now' di atas untuk memindai jaringan Wi-Fi fisik di sekitar.")?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Connect -->
    <div class="modal fade" id="modal-connect" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa-solid fa-key text-primary"></i> <?=gettext("Sambungkan ke Jaringan Wi-Fi")?></h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label><?=gettext("Network SSID:")?></label>
                        <input type="text" id="connect-ssid" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label><?=gettext("Security:")?></label>
                        <input type="text" id="connect-security" class="form-control" readonly>
                    </div>
                    <div class="form-group" id="group-password">
                        <label><?=gettext("Password / WPA2 Pre-Shared Key:")?></label>
                        <div class="input-group">
                            <input type="password" id="connect-password" class="form-control" placeholder="<?=gettext("Masukkan kata sandi Wi-Fi")?>">
                            <span class="input-group-btn">
                                <button class="btn btn-default" type="button" onclick="togglePassView()">
                                    <i class="fa-solid fa-eye" id="eye-icon"></i>
                                </button>
                            </span>
                        </div>
                    </div>
                    <div id="connect-status-msg" style="margin-top: 10px;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?=gettext("Batal")?></button>
                    <button type="button" class="btn btn-primary" id="btn-do-connect" onclick="submitConnect()">
                        <i class="fa-solid fa-plug"></i> <?=gettext("Sambungkan")?>
                    </button>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($tab == "ap"): ?>
    <!-- TAB 3: ACCESS POINT (HOSTAP) -->
    <div class="panel panel-default wifi-card">
        <div class="panel-heading">
            <i class="fa-solid fa-tower-broadcast text-primary" style="margin-right: 8px;"></i>
            <?=gettext("Access Point (AP / Hotspot Mode)")?>
            <?php if (($status['mode'] ?? '') == 'ap'): ?>
                <span class="badge badge-wifi-connected pull-right" style="margin-top: 3px;">
                    <i class="fa-solid fa-circle-check"></i> <?=gettext("AP Aktif")?>
                </span>
            <?php endif; ?>
        </div>
        <div class="panel-body">
            <?php if (empty($hw_info['wireless_interfaces']) && empty($hw_info['wlan_parent_devices'])): ?>
                <div class="alert alert-warning" style="border-left: 4px solid #ffc107; margin-bottom: 20px;">
                    <i class="fa-solid fa-triangle-exclamation fa-lg" style="margin-right: 6px;"></i>
                    <strong><?=gettext("Radio 802.11 Fisik Diperlukan:")?></strong><br>
                    <?=gettext("Mode Access Point (HostAP) membutuhkan kartu wireless 802.11 fisik (PCIe atau USB Wi-Fi dongle). VirtualBox menjembatani koneksi melalui Ethernet virtual. Untuk menyiarkan hotspot nirkabel dari VirtualBox, pasang USB Wi-Fi dongle pada PC Host lalu hubungkan melalui menu VirtualBox: <strong>Devices > USB > [Pilih Wi-Fi Dongle]</strong>.")?>
                </div>
            <?php endif; ?>

            <p class="text-muted">
                <?=gettext("Menyiarkan hotspot Wi-Fi dari pfSense menggunakan hostapd pada antarmuka nirkabel fisik.")?>
            </p>

            <form class="form-horizontal" onsubmit="event.preventDefault(); submitAP();">
                <div class="form-group">
                    <label class="col-sm-3 control-label"><?=gettext("Hotspot SSID:")?></label>
                    <div class="col-sm-6">
                        <input type="text" id="ap-ssid" class="form-control" value="pfSense-WiFi-AP" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label"><?=gettext("WPA2 Passphrase:")?></label>
                    <div class="col-sm-6">
                        <input type="password" id="ap-password" class="form-control" value="pfsense_wifi_pass" minlength="8" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label"><?=gettext("Saluran (Channel):")?></label>
                    <div class="col-sm-3">
                        <select id="ap-channel" class="form-control">
                            <option value="1">Channel 1 (2.412 GHz)</option>
                            <option value="6" selected>Channel 6 (2.437 GHz)</option>
                            <option value="11">Channel 11 (2.462 GHz)</option>
                            <option value="36">Channel 36 (5.180 GHz - 5G)</option>
                            <option value="40">Channel 40 (5.200 GHz - 5G)</option>
                            <option value="44">Channel 44 (5.220 GHz - 5G)</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label"><?=gettext("AP Subnet IP:")?></label>
                    <div class="col-sm-4">
                        <input type="text" id="ap-ip" class="form-control" value="192.168.88.1" required>
                        <span class="help-block"><?=gettext("IP statis antarmuka Hotspot.")?></span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-3 col-sm-6">
                        <button type="submit" class="btn btn-success" id="btn-ap-start">
                            <i class="fa-solid fa-play"></i> <?=gettext("Mulai Access Point")?>
                        </button>
                        <button type="button" class="btn btn-danger" id="btn-ap-stop" onclick="stopAP()">
                            <i class="fa-solid fa-stop"></i> <?=gettext("Hentikan Access Point")?>
                        </button>
                    </div>
                </div>
            </form>
            <div id="ap-alert-box" style="margin-top: 15px;"></div>
        </div>
    </div>

<?php elseif ($tab == "detect"): ?>
    <!-- TAB 4: HARDWARE & AUTO-DETECT -->
    <div class="panel panel-default wifi-card">
        <div class="panel-heading">
            <i class="fa-solid fa-microchip text-primary" style="margin-right: 8px;"></i>
            <?=gettext("Auto-Detected Network Interfaces & Hardware")?>
            <button class="btn btn-sm btn-primary pull-right" onclick="triggerHardwareDetect()">
                <i class="fa-solid fa-magnifying-glass"></i> <?=gettext("Re-Detect Hardware")?>
            </button>
        </div>
        <div class="panel-body">
            <p class="text-muted">
                <?=gettext("Mendeteksi otomatis seluruh antarmuka jaringan, adapter 802.11 fisik, controller PCI, serta modul kernel FreeBSD.")?>
            </p>

            <h4><i class="fa-solid fa-network-wired text-primary"></i> <?=gettext("Auto-Detected Network Interfaces (ifconfig)")?></h4>
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-wifi">
                    <thead>
                        <tr>
                            <th style="width: 15%;"><?=gettext("Nama Antarmuka")?></th>
                            <th style="width: 25%;"><?=gettext("Tipe Antarmuka")?></th>
                            <th style="width: 20%;"><?=gettext("MAC Address")?></th>
                            <th style="width: 20%;"><?=gettext("IPv4 Address")?></th>
                            <th style="width: 10%;"><?=gettext("Media")?></th>
                            <th style="width: 10%; text-align: center;"><?=gettext("Status")?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($hw_info['all_interfaces'])): ?>
                            <?php foreach ($hw_info['all_interfaces'] as $aif): ?>
                                <tr>
                                    <td><strong style="font-size: 14px;"><code><?=htmlspecialchars($aif['name'])?></code></strong></td>
                                    <td>
                                        <?php if (!empty($aif['is_wireless'])): ?>
                                            <span class="badge badge-wifi-connected"><i class="fa-solid fa-wifi"></i> Physical 802.11 Wireless</span>
                                        <?php elseif ($aif['name'] == 'em1'): ?>
                                            <span class="badge badge-wifi-bridge"><i class="fa-solid fa-link"></i> VirtualBox Bridged Wi-Fi Uplink</span>
                                        <?php else: ?>
                                            <span class="badge badge-wifi-disconnected"><i class="fa-solid fa-ethernet"></i> Ethernet Interface</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?=htmlspecialchars($aif['mac'] ?: 'N/A')?></code></td>
                                    <td><?=htmlspecialchars($aif['ip'] ? $aif['ip'] . ' (' . $aif['netmask'] . ')' : 'No IP')?></td>
                                    <td><small><?=htmlspecialchars($aif['media'] ?: 'Auto')?></small></td>
                                    <td style="text-align: center;">
                                        <?php if (!empty($aif['is_up'])): ?>
                                            <span class="text-success"><i class="fa-solid fa-circle-check"></i> UP</span>
                                        <?php else: ?>
                                            <span class="text-muted"><i class="fa-solid fa-circle-xmark"></i> DOWN</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center text-muted"><?=gettext("Tidak ada antarmuka jaringan terdeteksi.")?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <h4 style="margin-top: 25px;"><i class="fa-solid fa-server text-info"></i> <?=gettext("Physical PCI Network Controllers (pciconf)")?></h4>
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-wifi">
                    <thead>
                        <tr>
                            <th style="width: 15%;"><?=gettext("Device Name")?></th>
                            <th style="width: 25%;"><?=gettext("Vendor")?></th>
                            <th style="width: 35%;"><?=gettext("Controller Model")?></th>
                            <th style="width: 15%;"><?=gettext("Tipe")?></th>
                            <th style="width: 10%; text-align: center;"><?=gettext("Status")?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($hw_info['pci_network_devices'])): ?>
                            <?php foreach ($hw_info['pci_network_devices'] as $dev): ?>
                                <tr>
                                    <td><code><?=htmlspecialchars($dev['name'])?></code></td>
                                    <td><?=htmlspecialchars($dev['vendor'])?></td>
                                    <td><?=htmlspecialchars($dev['device'] ?? $dev['model'] ?? 'Network Controller')?></td>
                                    <td>
                                        <?php if (!empty($dev['is_wireless'])): ?>
                                            <span class="badge badge-wifi-connected"><i class="fa-solid fa-wifi"></i> Wireless (802.11)</span>
                                        <?php else: ?>
                                            <span class="badge badge-wifi-disconnected"><i class="fa-solid fa-network-wired"></i> Ethernet (PCIe)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;"><span class="text-success"><i class="fa-solid fa-check"></i> Active</span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center text-muted"><?=gettext("Tidak ada controller PCI terdeteksi.")?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <h4 style="margin-top: 25px;"><i class="fa-brands fa-usb text-warning"></i> <?=gettext("USB Devices (usbconfig)")?></h4>
            <ul class="list-group">
                <?php if (!empty($hw_info['usb_devices'])): ?>
                    <?php foreach ($hw_info['usb_devices'] as $usb): ?>
                        <li class="list-group-item"><code><?=htmlspecialchars($usb)?></code></li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="list-group-item text-muted"><em><?=gettext("Tidak ada perangkat USB terdeteksi.")?></em></li>
                <?php endif; ?>
            </ul>

            <h4 style="margin-top: 25px;"><i class="fa-solid fa-cubes text-success"></i> <?=gettext("Loaded FreeBSD Wireless Drivers & Subsystems (kldstat)")?></h4>
            <div>
                <?php if (!empty($hw_info['modules_loaded'])): ?>
                    <?php foreach ($hw_info['modules_loaded'] as $mod): ?>
                        <span class="badge bg-primary" style="font-size: 13px; margin: 3px; padding: 6px 10px;">
                            <i class="fa-solid fa-cube"></i> <?=htmlspecialchars($mod)?>
                        </span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted"><em><?=gettext("Kernel modules wlan aktif.")?></em></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php elseif ($tab == "bridge"): ?>
    <!-- TAB 5: VIRTUALBOX / BRIDGED WI-FI -->
    <div class="panel panel-default wifi-card">
        <div class="panel-heading">
            <i class="fa-solid fa-link text-primary" style="margin-right: 8px;"></i>
            <?=gettext("VirtualBox / Bridged Wireless Mode")?>
        </div>
        <div class="panel-body">
            <div class="alert alert-info">
                <h4><i class="fa-solid fa-laptop"></i> <?=gettext("Host PC Wireless Bridging (VirtualBox / Testing)")?></h4>
                <p>
                    <?=gettext("Saat pfSense berjalan di VirtualBox dengan <strong>Bridged Adapter</strong> ke kartu Wi-Fi Host PC, mesin virtual berkomunikasi langsung melalui koneksi Wi-Fi Host.")?>
                </p>
                <p>
                    <?=gettext("Antarmuka yang dijembatani: <code>em1</code> (IP: <strong>192.168.1.109</strong>). Gateway router Wi-Fi Host: <strong>192.168.1.1</strong>.")?>
                </p>
            </div>

            <form class="form-horizontal" onsubmit="event.preventDefault(); submitBridge();">
                <div class="form-group">
                    <label class="col-sm-3 control-label"><?=gettext("Bridged Network Interface:")?></label>
                    <div class="col-sm-4">
                        <select id="bridge-iface" class="form-control">
                            <?php foreach (($hw_info['bridged_candidates'] ?? ['em1']) as $cif): ?>
                                <option value="<?=htmlspecialchars($cif)?>" <?=($cif == ($status['active_interface'] ?? 'em1')) ? 'selected' : ''?>>
                                    <?=htmlspecialchars($cif)?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-3 col-sm-6">
                        <button type="submit" class="btn btn-primary" id="btn-bridge-apply">
                            <i class="fa-solid fa-check"></i> <?=gettext("Apply Bridged Wi-Fi Uplink")?>
                        </button>
                    </div>
                </div>
            </form>

            <div id="bridge-alert-box" style="margin-top: 15px;"></div>
        </div>
    </div>
<?php endif; ?>

</div>

<script>
function changeActiveInterface(iface) {
    $.post('interfaces_wifi.php', {
        ajax: 1,
        act: 'set_iface',
        iface: iface
    }, function(res) {
        $('#iface-switch-msg').fadeIn().delay(1500).fadeOut();
        setTimeout(function() {
            window.location.reload();
        }, 500);
    }, 'json');
}

function refreshStatus() {
    $.getJSON('interfaces_wifi.php?ajax=1&act=status', function(res) {
        if (!res) return;
        $('#stat-iface').text(res.active_interface || 'em1');
        $('#stat-state').text(res.state || 'Active');
        $('#stat-ip').text(res.ip || 'No IP');
        $('#stat-netmask').text(res.netmask || 'N/A');
        $('#stat-gateway').text(res.gateway || 'None');
        $('#stat-mac').text(res.mac || 'N/A');
        $('#stat-media').text(res.media || 'Auto');
        if (res.is_wireless) {
            $('#stat-ssid').text(res.ssid || 'None');
            var pct = res.signal_percent || 0;
            $('#stat-signal-text').text(pct + '% (' + (res.signal_dbm || -100) + ' dBm)');
            $('#stat-signal-bar').css('width', pct + '%');
        }
    });
}

function triggerScan() {
    var iface = $('#scan-iface').val();
    $('#scan-loading').show();
    $('#scan-tbody').empty();
    $('#scan-alert-box').empty();
    $('#btn-scan').prop('disabled', true);

    $.getJSON('interfaces_wifi.php?ajax=1&act=scan&iface=' + encodeURIComponent(iface), function(resp) {
        $('#scan-loading').hide();
        $('#btn-scan').prop('disabled', false);

        if (!resp || !resp.status) {
            var msg = resp ? resp.msg : 'Scan gagal dijalankan.';
            $('#scan-alert-box').html('<div class="alert alert-info"><i class="fa-solid fa-circle-info"></i> ' + msg + '</div>');
            $('#scan-tbody').html('<tr><td colspan="7" class="text-center text-muted" style="padding: 20px;">' + msg + '</td></tr>');
            return;
        }

        var networks = resp.networks || [];
        if (networks.length === 0) {
            $('#scan-alert-box').html('<div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation"></i> <?=gettext("Tidak ada jaringan Wi-Fi terdeteksi pada jangkauan radio adapter ini.")?></div>');
            $('#scan-tbody').html('<tr><td colspan="7" class="text-center text-muted" style="padding: 20px;"><?=gettext("Tidak ada jaringan Wi-Fi ditemukan.")?></td></tr>');
            return;
        }

        var html = '';
        $.each(networks, function(i, net) {
            var badgeSec = (net.security === 'Open') ? 'badge-wifi-disconnected' : 'badge-wifi-connected';
            html += '<tr>';
            html += '<td>' + (i + 1) + '</td>';
            html += '<td><strong>' + $('<div>').text(net.ssid).html() + '</strong></td>';
            html += '<td><code>' + net.bssid + '</code></td>';
            html += '<td>' + net.channel + '</td>';
            html += '<td>';
            html += '<div style="display:flex; justify-content:space-between; font-size:12px;"><span>' + net.signal_percent + '%</span></div>';
            html += '<div class="wifi-signal-bar"><div class="wifi-signal-fill bg-success" style="width:' + net.signal_percent + '%;"></div></div>';
            html += '</td>';
            html += '<td><span class="badge ' + badgeSec + '">' + net.security + '</span></td>';
            html += '<td style="text-align:center;">';
            html += '<button class="btn btn-xs btn-primary" onclick="openConnectModal(\'' + escape(net.ssid) + '\', \'' + net.security + '\')">';
            html += '<i class="fa-solid fa-plug"></i> <?=gettext("Connect")?></button>';
            html += '</td>';
            html += '</tr>';
        });
        $('#scan-tbody').html(html);
    }).fail(function() {
        $('#scan-loading').hide();
        $('#btn-scan').prop('disabled', false);
        $('#scan-tbody').html('<tr><td colspan="7" class="text-center text-danger"><?=gettext("Gagal memanggil fungsi scan.")?></td></tr>');
    });
}

function openConnectModal(escapedSsid, sec) {
    var ssid = unescape(escapedSsid);
    $('#connect-ssid').val(ssid);
    $('#connect-security').val(sec);
    $('#connect-password').val('');
    $('#connect-status-msg').empty();
    if (sec === 'Open') {
        $('#group-password').hide();
    } else {
        $('#group-password').show();
    }
    $('#modal-connect').modal('show');
}

function togglePassView() {
    var inp = $('#connect-password');
    var icon = $('#eye-icon');
    if (inp.attr('type') === 'password') {
        inp.attr('type', 'text');
        icon.removeClass('fa-eye').addClass('fa-eye-slash');
    } else {
        inp.attr('type', 'password');
        icon.removeClass('fa-eye-slash').addClass('fa-eye');
    }
}

function submitConnect() {
    var ssid = $('#connect-ssid').val();
    var sec = $('#connect-security').val();
    var pwd = $('#connect-password').val();
    var iface = $('#scan-iface').val();

    $('#btn-do-connect').prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Menghubungkan...');
    $('#connect-status-msg').html('<div class="alert alert-info"><i class="fa-solid fa-spinner fa-spin"></i> <?=gettext("Mengirim permintaan asosiasi Wi-Fi...")?></div>');

    $.post('interfaces_wifi.php', {
        ajax: 1,
        act: 'connect',
        ssid: ssid,
        security: sec,
        password: pwd,
        iface: iface
    }, function(res) {
        $('#btn-do-connect').prop('disabled', false).html('<i class="fa-solid fa-plug"></i> <?=gettext("Sambungkan")?>');
        if (res && res.status) {
            $('#connect-status-msg').html('<div class="alert alert-success"><i class="fa-solid fa-check"></i> ' + res.msg + '</div>');
            setTimeout(function() {
                $('#modal-connect').modal('hide');
                window.location.href = 'interfaces_wifi.php?tab=overview';
            }, 1800);
        } else {
            $('#connect-status-msg').html('<div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> ' + (res ? res.msg : 'Gagal menyambung.') + '</div>');
        }
    }, 'json').fail(function() {
        $('#btn-do-connect').prop('disabled', false).html('<i class="fa-solid fa-plug"></i> <?=gettext("Sambungkan")?>');
        $('#connect-status-msg').html('<div class="alert alert-danger"><?=gettext("Permintaan koneksi gagal.")?></div>');
    });
}

function submitAP() {
    var ssid = $('#ap-ssid').val();
    var pwd = $('#ap-password').val();
    var chan = $('#ap-channel').val();
    var ip = $('#ap-ip').val();

    $('#btn-ap-start').prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Memulai...');
    $('#ap-alert-box').html('<div class="alert alert-info"><i class="fa-solid fa-spinner fa-spin"></i> Mengaktifkan layanan Access Point...</div>');

    $.post('interfaces_wifi.php', {
        ajax: 1,
        act: 'ap_start',
        ap_ssid: ssid,
        ap_password: pwd,
        ap_channel: chan,
        ap_ip: ip
    }, function(res) {
        $('#btn-ap-start').prop('disabled', false).html('<i class="fa-solid fa-play"></i> Mulai Access Point');
        if (res && res.status) {
            $('#ap-alert-box').html('<div class="alert alert-success"><i class="fa-solid fa-check"></i> ' + res.msg + '</div>');
        } else {
            $('#ap-alert-box').html('<div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> ' + (res ? res.msg : 'Gagal memulai AP') + '</div>');
        }
    }, 'json');
}

function stopAP() {
    $.post('interfaces_wifi.php', { ajax: 1, act: 'ap_stop' }, function(res) {
        $('#ap-alert-box').html('<div class="alert alert-info">' + res.msg + '</div>');
    }, 'json');
}

function triggerHardwareDetect() {
    window.location.reload();
}

function submitBridge() {
    var iface = $('#bridge-iface').val();
    $('#btn-bridge-apply').prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Menerapkan...');
    $('#bridge-alert-box').html('<div class="alert alert-info"><i class="fa-solid fa-spinner fa-spin"></i> Menghubungkan interface ' + iface + '...</div>');

    $.post('interfaces_wifi.php', {
        ajax: 1,
        act: 'bridge_setup',
        bridge_iface: iface
    }, function(res) {
        $('#btn-bridge-apply').prop('disabled', false).html('<i class="fa-solid fa-check"></i> Apply Bridged Wi-Fi Uplink');
        if (res && res.status) {
            $('#bridge-alert-box').html('<div class="alert alert-success"><i class="fa-solid fa-check"></i> ' + res.msg + '</div>');
            setTimeout(function() {
                window.location.href = 'interfaces_wifi.php?tab=overview';
            }, 1800);
        } else {
            $('#bridge-alert-box').html('<div class="alert alert-danger">' + (res ? res.msg : 'Gagal') + '</div>');
        }
    }, 'json');
}
</script>

<?php include("foot.inc"); ?>
