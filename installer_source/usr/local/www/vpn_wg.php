<?php
##
# vpn_wg.php
# pfSense WebGUI: VPN -> WireGuard
# Part of pfSense Custom Edition
##

require_once("guiconfig.inc");
require_once("service-utils.inc");

$pgtitle = array(gettext("VPN"), gettext("WireGuard"));
$pglinks = array("", "@self");

$conf_file = "/usr/local/etc/wireguard/wg0.conf";
$savemsg = "";

if ($_POST['act'] == 'start') {
    mwexec("/usr/local/bin/wg-quick up wg0");
    $savemsg = "WireGuard interface wg0 berhasil diaktifkan.";
} elseif ($_POST['act'] == 'stop') {
    mwexec("/usr/local/bin/wg-quick down wg0");
    $savemsg = "WireGuard interface wg0 dinonaktifkan.";
} elseif ($_POST['act'] == 'genkey') {
    mwexec("/usr/local/bin/wireguard-manager genkey");
    $savemsg = "Keypair baru berhasil dibuat.";
} elseif ($_POST['save_conf']) {
    if (isset($_POST['conf_content'])) {
        file_put_contents($conf_file, $_POST['conf_content']);
        $savemsg = "Konfigurasi wg0.conf berhasil disimpan.";
    }
}

$wg_status = shell_exec("/usr/bin/wg show 2>&1");
$conf_content = file_exists($conf_file) ? file_get_contents($conf_file) : "";

include("head.inc");

if ($savemsg) {
    print_info_box($savemsg, 'success');
}
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title">Status WireGuard Interface (wg0)</h2>
    </div>
    <div class="panel-body">
        <pre><?= htmlspecialchars($wg_status ? $wg_status : "Interface wg0 belum berjalan atau belum dikonfigurasi.") ?></pre>
        <form method="post" action="vpn_wg.php" class="form-inline">
            <button type="submit" name="act" value="start" class="btn btn-success"><i class="fa fa-play"></i> Start wg0</button>
            <button type="submit" name="act" value="stop" class="btn btn-danger"><i class="fa fa-stop"></i> Stop wg0</button>
            <button type="submit" name="act" value="genkey" class="btn btn-info"><i class="fa fa-key"></i> Generate Keypair Baru</button>
        </form>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title">Konfigurasi WireGuard (/usr/local/etc/wireguard/wg0.conf)</h2>
    </div>
    <div class="panel-body">
        <form method="post" action="vpn_wg.php">
            <div class="form-group">
                <textarea name="conf_content" rows="12" class="form-control" style="font-family: monospace;"><?= htmlspecialchars($conf_content) ?></textarea>
            </div>
            <button type="submit" name="save_conf" value="1" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Konfigurasi</button>
        </form>
    </div>
</div>

<?php include("foot.inc"); ?>
