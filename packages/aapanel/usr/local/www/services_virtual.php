<?php
##
# services_virtual.php
# pfSense WebGUI: Services -> Virtual Machines (KVM/Bhyve & aaPanel)
# Part of pfSense Custom Edition
##

require_once("guiconfig.inc");
require_once("service-utils.inc");

$pgtitle = array(gettext("Services"), gettext("Virtual Machines"));
$pglinks = array("", "@self");

$conf_file = "/usr/local/etc/aapanel/aapanel.conf";
$savemsg = "";
$errmsg = "";

if (isset($_POST['act']) && $_POST['act'] == 'setup_bhyve') {
    mwexec("/usr/local/bin/aapanel-pfsense setup-bhyve");
    $savemsg = "Modul kernel Virtual Machine Bhyve/KVM berhasil diaktifkan.";
} elseif (isset($_POST['save_conf'])) {
    if (isset($_POST['conf_content'])) {
        @mkdir(dirname($conf_file), 0755, true);
        file_put_contents($conf_file, $_POST['conf_content']);
        $savemsg = "Konfigurasi aapanel.conf berhasil disimpan.";
    }
}

// Cek status modul kernel
$kldstat = shell_exec("/sbin/kldstat 2>&1");
$vmm_loaded = (strpos($kldstat, "vmm.ko") !== false);
$tap_loaded = (strpos($kldstat, "if_tap.ko") !== false);
$bridge_loaded = (strpos($kldstat, "if_bridge.ko") !== false);
$nmdm_loaded = (strpos($kldstat, "nmdm.ko") !== false);

$conf_content = file_exists($conf_file) ? file_get_contents($conf_file) : "PORT=8888\nVM_MEMORY=2048M\nVM_CPUS=2\nDISK_SIZE=20G\n";

include("head.inc");

if ($savemsg) {
    print_info_box($savemsg, "success");
}
if ($errmsg) {
    print_info_box(nl2br(htmlspecialchars($errmsg)), "danger");
}
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title">Status Hypervisor KVM / Bhyve & aaPanel</h2>
    </div>
    <div class="panel-body">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Komponen Virtualisasi</th>
                    <th>Deskripsi</th>
                    <th>Status Kernel</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>vmm (Kernel VM Monitor)</strong></td>
                    <td>Intel VT-x / AMD-V Hardware Virtualization Driver</td>
                    <td>
                        <?php if ($vmm_loaded): ?>
                            <span class="label label-success"><i class="fa-solid fa-check"></i> ACTIVE</span>
                        <?php else: ?>
                            <span class="label label-warning"><i class="fa-solid fa-pause"></i> NOT LOADED</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>if_tap (Virtual Ethernet TAP)</strong></td>
                    <td>Virtual Ethernet Interface untuk VM Guest & Bridging</td>
                    <td>
                        <?php if ($tap_loaded): ?>
                            <span class="label label-success"><i class="fa-solid fa-check"></i> ACTIVE</span>
                        <?php else: ?>
                            <span class="label label-warning"><i class="fa-solid fa-pause"></i> NOT LOADED</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>if_bridge (Network Bridge)</strong></td>
                    <td>Penghubung jaringan virtual VM ke interface LAN pfSense</td>
                    <td>
                        <?php if ($bridge_loaded): ?>
                            <span class="label label-success"><i class="fa-solid fa-check"></i> ACTIVE</span>
                        <?php else: ?>
                            <span class="label label-warning"><i class="fa-solid fa-pause"></i> NOT LOADED</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>nmdm (Virtual Serial Console)</strong></td>
                    <td>Null-modem driver untuk akses konsol serial VM</td>
                    <td>
                        <?php if ($nmdm_loaded): ?>
                            <span class="label label-success"><i class="fa-solid fa-check"></i> ACTIVE</span>
                        <?php else: ?>
                            <span class="label label-warning"><i class="fa-solid fa-pause"></i> NOT LOADED</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <form method="post" action="services_virtual.php" class="form-inline" style="margin-top: 15px;">
            <button type="submit" name="act" value="setup_bhyve" class="btn btn-primary">
                <i class="fa-solid fa-cogs"></i> Aktifkan Semua Modul Hypervisor
            </button>
            <a href="http://<?= htmlspecialchars($_SERVER["SERVER_ADDR"] ?? "192.168.1.1") ?>:8888" target="_blank" class="btn btn-info">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Buka aaPanel Web (Port 8888)
            </a>
        </form>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title">Konfigurasi Virtual Machine & aaPanel (/usr/local/etc/aapanel/aapanel.conf)</h2>
    </div>
    <div class="panel-body">
        <form method="post" action="services_virtual.php">
            <div class="form-group">
                <textarea name="conf_content" rows="8" class="form-control" style="font-family: monospace;"><?= htmlspecialchars($conf_content) ?></textarea>
            </div>
            <button type="submit" name="save_conf" value="1" class="btn btn-success"><i class="fa-solid fa-floppy-disk"></i> Simpan Konfigurasi</button>
        </form>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title">Kernel Modules Detail (kldstat)</h2>
    </div>
    <div class="panel-body">
        <pre><?= htmlspecialchars($kldstat) ?></pre>
    </div>
</div>

<?php include("foot.inc"); ?>
