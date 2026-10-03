<?php
##
# vpn_xray.php
# pfSense WebGUI: VPN -> Xray-core
# Part of pfSense Custom Edition
##

require_once("guiconfig.inc");
require_once("service-utils.inc");

$pgtitle = array(gettext("VPN"), gettext("Xray-core"));
$pglinks = array("", "@self");

$conf_file = "/usr/local/etc/xray/config.json";
$log_access = "/var/log/xray/access.log";
$log_error = "/var/log/xray/error.log";
$savemsg = "";
$errmsg = "";

if ($_POST['act'] == 'start') {
    mwexec("/usr/sbin/service xray start");
    $savemsg = "Layanan Xray-core berhasil dijalankan.";
} elseif ($_POST['act'] == 'stop') {
    mwexec("/usr/sbin/service xray stop");
    $savemsg = "Layanan Xray-core dihentikan.";
} elseif ($_POST['act'] == 'restart') {
    mwexec("/usr/sbin/service xray restart");
    $savemsg = "Layanan Xray-core berhasil direstart.";
} elseif ($_POST['act'] == 'test') {
    $test_out = shell_exec("/usr/local/bin/xray -test -config " . escapeshellarg($conf_file) . " 2>&1");
    if (strpos($test_out, "Configuration OK") !== false) {
        $savemsg = "Uji Konfigurasi Berhasil: " . $test_out;
    } else {
        $errmsg = "Uji Konfigurasi Gagal:\n" . $test_out;
    }
} elseif ($_POST['save_conf']) {
    if (isset($_POST['conf_content'])) {
        $json_test = json_decode($_POST['conf_content']);
        if ($json_test === null) {
            $errmsg = "Error: Format JSON tidak valid! Silakan periksa kembali tanda kurung atau koma.";
        } else {
            file_put_contents($conf_file, $_POST['conf_content']);
            $savemsg = "File konfigurasi config.json berhasil disimpan.";
        }
    }
}

// Cek status proses Xray
$xray_pid = "";
if (file_exists("/var/run/xray.pid")) {
    $xray_pid = trim(file_get_contents("/var/run/xray.pid"));
    if (!posix_kill((int)$xray_pid, 0)) {
        $xray_pid = "";
    }
}

$conf_content = file_exists($conf_file) ? file_get_contents($conf_file) : "";
$err_log_content = file_exists($log_error) ? shell_exec("tail -n 25 " . escapeshellarg($log_error)) : "Belum ada log error.";

include("head.inc");

if ($savemsg) {
    print_info_box($savemsg, 'success');
}
if ($errmsg) {
    print_info_box(nl2br(htmlspecialchars($errmsg)), 'danger');
}
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title">Status Layanan Xray-core</h2>
    </div>
    <div class="panel-body">
        <p>
            <strong>Status:</strong>
            <?php if ($xray_pid): ?>
                <span class="label label-success">RUNNING (PID: <?= htmlspecialchars($xray_pid) ?>)</span>
            <?php else: ?>
                <span class="label label-danger">STOPPED</span>
            <?php endif; ?>
        </p>

        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Protokol</th>
                    <th>Port</th>
                    <th>Keamanan / Transport</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>VLESS</strong></td>
                    <td><code>443</code></td>
                    <td>Reality + XTLS (Vision)</td>
                    <td>Anti-censorship generasi terbaru penyamaran TLS</td>
                </tr>
                <tr>
                    <td><strong>VMess</strong></td>
                    <td><code>8443</code></td>
                    <td>WebSocket (<code>/vmess-ws</code>) + TLS</td>
                    <td>Dukungan tunneling kompatibel Cloudflare CDN</td>
                </tr>
                <tr>
                    <td><strong>Trojan</strong></td>
                    <td><code>9443</code></td>
                    <td>TLS</td>
                    <td>Protokol proxy standar Trojan-GFW</td>
                </tr>
                <tr>
                    <td><strong>Socks5</strong></td>
                    <td><code>10808</code></td>
                    <td>Local / NoAuth</td>
                    <td>Inbound proxy lokal untuk aplikasi internal</td>
                </tr>
                <tr>
                    <td><strong>TUN / Transparent</strong></td>
                    <td><code>12345</code></td>
                    <td>Dokodemo-door (TCP/UDP)</td>
                    <td>Pengalihan paket transparan seluruh jaringan router</td>
                </tr>
            </tbody>
        </table>

        <form method="post" action="vpn_xray.php" class="form-inline">
            <button type="submit" name="act" value="start" class="btn btn-success"><i class="fa fa-play"></i> Start Xray</button>
            <button type="submit" name="act" value="restart" class="btn btn-warning"><i class="fa fa-refresh"></i> Restart Xray</button>
            <button type="submit" name="act" value="stop" class="btn btn-danger"><i class="fa fa-stop"></i> Stop Xray</button>
            <button type="submit" name="act" value="test" class="btn btn-info"><i class="fa fa-check"></i> Test Konfigurasi</button>
        </form>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title">Editor Konfigurasi (/usr/local/etc/xray/config.json)</h2>
    </div>
    <div class="panel-body">
        <form method="post" action="vpn_xray.php">
            <div class="form-group">
                <textarea name="conf_content" rows="18" class="form-control" style="font-family: monospace; font-size: 13px;"><?= htmlspecialchars($conf_content) ?></textarea>
            </div>
            <button type="submit" name="save_conf" value="1" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Konfigurasi</button>
        </form>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title">Log Kesalahan Terakhir (/var/log/xray/error.log)</h2>
    </div>
    <div class="panel-body">
        <pre><?= htmlspecialchars($err_log_content) ?></pre>
    </div>
</div>

<?php include("foot.inc"); ?>
