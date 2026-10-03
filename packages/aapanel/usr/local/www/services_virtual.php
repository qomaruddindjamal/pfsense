<?php
##
# services_virtual.php
# pfSense WebGUI: KVM / Bhyve Hypervisor & Virtual Machine Manager
# Part of pfSense Custom Edition
##

require_once("guiconfig.inc");
require_once("service-utils.inc");

$pgtitle = array(gettext("Services"), gettext("Virtual Machines (KVM)"));
$pglinks = array("", "@self");

$conf_dir = "/usr/local/etc/kvm";
$conf_file = "{$conf_dir}/vms.json";
$kvm_manager = "/usr/local/bin/kvm-manager";

$savemsg = "";
$errmsg = "";

// Pastikan konfigurasi default tersedia
if (!is_dir($conf_dir)) {
    @mkdir($conf_dir, 0755, true);
}

// Inisialisasi file vms.json jika belum ada
function get_vm_config() {
    global $conf_file, $kvm_manager;
    if (!file_exists($conf_file)) {
        if (file_exists($kvm_manager) && is_executable($kvm_manager)) {
            mwexec("{$kvm_manager} init");
        }
    }
    if (file_exists($conf_file)) {
        $json = @file_get_contents($conf_file);
        $data = json_decode($json, true);
        if (is_array($data)) {
            // Pastikan aaPanel selalu ada sebagai VM bawaan default
            if (!isset($data['aapanel'])) {
                $data['aapanel'] = [
                    'id' => 'aapanel',
                    'name' => 'aaPanel',
                    'description' => 'aaPanel Linux Control Panel Environment (Default Built-in VM)',
                    'is_default' => true,
                    'locked' => true,
                    'os' => 'linux',
                    'cpus' => 2,
                    'ram' => 2048,
                    'disk_size' => 20,
                    'disk_path' => '/usr/local/vm/aapanel/disk.raw',
                    'vnet' => 'tap0',
                    'bridge_mode' => true,
                    'bridge_interface' => 'bridge0',
                    'parent_interface' => 'vtnet0',
                    'autostart' => true,
                    'port' => 8888,
                    'created_at' => '2026-10-01'
                ];
                @file_put_contents($conf_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
            return $data;
        }
    }
    
    // Default fallback array
    return [
        'aapanel' => [
            'id' => 'aapanel',
            'name' => 'aaPanel',
            'description' => 'aaPanel Linux Control Panel Environment (Default Built-in VM)',
            'is_default' => true,
            'locked' => true,
            'os' => 'linux',
            'cpus' => 2,
            'ram' => 2048,
            'disk_size' => 20,
            'disk_path' => '/usr/local/vm/aapanel/disk.raw',
            'vnet' => 'tap0',
            'bridge_mode' => true,
            'bridge_interface' => 'bridge0',
            'parent_interface' => 'vtnet0',
            'autostart' => true,
            'port' => 8888,
            'created_at' => '2026-10-01'
        ]
    ];
}

function save_vm_config($vms) {
    global $conf_file;
    @file_put_contents($conf_file, json_encode($vms, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

// Cek apakah VM sedang berjalan
function is_vm_running($vm_id) {
    $pid_file = "/var/run/kvm/{$vm_id}.pid";
    if (file_exists($pid_file)) {
        $pid = trim(@file_get_contents($pid_file));
        if (!empty($pid) && is_numeric($pid)) {
            $check = shell_exec("/bin/ps -p " . escapeshellarg($pid) . " -o pid= 2>/dev/null");
            if (!empty(trim($check))) {
                return (int)$pid;
            }
        }
    }
    return false;
}

$vms = get_vm_config();
$act = $_REQUEST['act'] ?? '';
$vm_id = $_REQUEST['id'] ?? '';

// Handle VM Actions (Start, Stop, Restart, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($act == 'setup_hypervisor') {
        mwexec("{$kvm_manager} setup");
        $savemsg = gettext("Modul kernel hypervisor (vmm, nmdm, if_tap, if_bridge) berhasil diaktifkan.");
    } elseif ($act == 'start' && !empty($vm_id)) {
        if (isset($vms[$vm_id])) {
            $out = shell_exec("{$kvm_manager} start " . escapeshellarg($vm_id) . " 2>&1");
            $savemsg = gettext("Virtual Machine '") . htmlspecialchars($vms[$vm_id]['name']) . gettext("' berhasil dijalankan. ") . htmlspecialchars($out);
        }
    } elseif ($act == 'stop' && !empty($vm_id)) {
        if (isset($vms[$vm_id])) {
            mwexec("{$kvm_manager} stop " . escapeshellarg($vm_id));
            $savemsg = gettext("Virtual Machine '") . htmlspecialchars($vms[$vm_id]['name']) . gettext("' berhasil dihentikan.");
        }
    } elseif ($act == 'restart' && !empty($vm_id)) {
        if (isset($vms[$vm_id])) {
            mwexec("{$kvm_manager} restart " . escapeshellarg($vm_id));
            $savemsg = gettext("Virtual Machine '") . htmlspecialchars($vms[$vm_id]['name']) . gettext("' berhasil dimuat ulang.");
        }
    } elseif ($act == 'del' && !empty($vm_id)) {
        // ATURAN: aaPanel adalah default built-in VM dan TIDAK BISA DI-DELETE
        if ($vm_id === 'aapanel' || (!empty($vms[$vm_id]['is_default']))) {
            $errmsg = gettext("PERINGATAN: Virtual Machine 'aaPanel' adalah paket bawaan default pfSense dan TIDAK DAPAT DIHAPUS.");
        } elseif (isset($vms[$vm_id])) {
            mwexec("{$kvm_manager} delete " . escapeshellarg($vm_id));
            unset($vms[$vm_id]);
            save_vm_config($vms);
            $savemsg = gettext("Virtual Machine berhasil dihapus.");
        }
    } elseif ($act == 'save_vm') {
        $edit_id = trim($_POST['vm_id'] ?? '');
        $is_aapanel = ($edit_id === 'aapanel' || !empty($vms[$edit_id]['is_default']));

        if (empty($edit_id) || !preg_match('/^[a-zA-Z0-9_\-]+$/', $edit_id)) {
            $errmsg = gettext("ID Virtual Machine tidak valid. Gunakan huruf, angka, dan garis bawah/strip.");
        } else {
            // Validasi RAM
            $ram = (int)($_POST['ram'] ?? 2048);
            if ($ram < 256) $ram = 256;

            // Validasi Disk
            $disk_size = (int)($_POST['disk_size'] ?? 20);
            if ($disk_size < 1) $disk_size = 1;
            $disk_path = trim($_POST['disk_path'] ?? "/usr/local/vm/{$edit_id}/disk.raw");

            // Validasi Networking
            $vnet = trim($_POST['vnet'] ?? 'tap0');
            $bridge_mode = !empty($_POST['bridge_mode']);
            $bridge_interface = trim($_POST['bridge_interface'] ?? 'bridge0');
            $parent_interface = trim($_POST['parent_interface'] ?? 'vtnet0');
            $cpus = (int)($_POST['cpus'] ?? 2);
            $autostart = !empty($_POST['autostart']);

            if ($is_aapanel) {
                // Untuk aaPanel: Nama & ID dilindungi, hanya HDD, RAM, vnet, dan bridge mode yang di-update
                $vms['aapanel']['ram'] = $ram;
                $vms['aapanel']['disk_size'] = $disk_size;
                $vms['aapanel']['disk_path'] = $disk_path;
                $vms['aapanel']['vnet'] = $vnet;
                $vms['aapanel']['bridge_mode'] = $bridge_mode;
                $vms['aapanel']['bridge_interface'] = $bridge_interface;
                $vms['aapanel']['parent_interface'] = $parent_interface;
                $vms['aapanel']['cpus'] = $cpus;
                $vms['aapanel']['autostart'] = $autostart;
                save_vm_config($vms);
                $savemsg = gettext("Konfigurasi aaPanel (HDD, RAM, Virtual Ethernet, Bridge Mode) berhasil diperbarui.");
            } else {
                // Untuk VM custom
                $name = trim($_POST['name'] ?? $edit_id);
                $description = trim($_POST['description'] ?? '');
                $os = trim($_POST['os'] ?? 'linux');

                $vms[$edit_id] = [
                    'id' => $edit_id,
                    'name' => $name,
                    'description' => $description,
                    'is_default' => false,
                    'locked' => false,
                    'os' => $os,
                    'cpus' => $cpus,
                    'ram' => $ram,
                    'disk_size' => $disk_size,
                    'disk_path' => $disk_path,
                    'vnet' => $vnet,
                    'bridge_mode' => $bridge_mode,
                    'bridge_interface' => $bridge_interface,
                    'parent_interface' => $parent_interface,
                    'autostart' => $autostart,
                    'created_at' => $vms[$edit_id]['created_at'] ?? date('Y-m-d')
                ];
                save_vm_config($vms);
                $savemsg = gettext("Konfigurasi Virtual Machine '") . htmlspecialchars($name) . gettext("' berhasil disimpan.");
            }
            // Kembali ke halaman list
            $act = '';
        }
    }
    // Refresh konfigurasi setelah perubahan
    $vms = get_vm_config();
}

// Cek status modul kernel
$kldstat = shell_exec("/sbin/kldstat 2>&1") ?? "";
$vmm_loaded = (strpos($kldstat, "vmm.ko") !== false);
$tap_loaded = (strpos($kldstat, "if_tap.ko") !== false || file_exists("/dev/tap0") || strpos(shell_exec("/sbin/ifconfig -l 2>&1"), "tap") !== false);
$bridge_loaded = (strpos($kldstat, "if_bridge.ko") !== false || strpos(shell_exec("/sbin/ifconfig -l 2>&1"), "bridge") !== false);
$nmdm_loaded = (strpos($kldstat, "nmdm.ko") !== false || file_exists("/dev/nmdm_0A"));

// Daftar antarmuka fisik yang tersedia untuk bridging
$net_interfaces = [];
$raw_if = shell_exec("/sbin/ifconfig -l 2>&1") ?? "";
foreach (explode(' ', trim($raw_if)) as $ifitem) {
    $ifitem = trim($ifitem);
    if (!empty($ifitem) && !preg_match('/^(lo|enc|pfsync|pflog|tap|bridge)/', $ifitem)) {
        $net_interfaces[] = $ifitem;
    }
}
if (empty($net_interfaces)) {
    $net_interfaces = ['vtnet0', 'em0', 'igb0', 're0'];
}

include("head.inc");

// Tampilkan alert box
if ($savemsg) {
    print_info_box($savemsg, "success");
}
if ($errmsg) {
    print_info_box(htmlspecialchars($errmsg), "danger");
}

// Navigasi Tabs
$tab_array = array();
$tab_array[] = array(gettext("Virtual Machines"), ($act != 'hypervisor' && $act != 'network'), "services_virtual.php");
$tab_array[] = array(gettext("Hypervisor Status"), ($act == 'hypervisor'), "services_virtual.php?act=hypervisor");
$tab_array[] = array(gettext("Network & Bridge"), ($act == 'network'), "services_virtual.php?act=network");
display_top_tabs($tab_array);
?>

<?php if ($act == 'add' || $act == 'edit'): ?>
    <?php
    $edit_vm_id = $vm_id ?: ($_POST['vm_id'] ?? '');
    $is_edit = ($act == 'edit' && !empty($edit_vm_id) && isset($vms[$edit_vm_id]));
    $curr_vm = $is_edit ? $vms[$edit_vm_id] : [
        'id' => '',
        'name' => '',
        'description' => '',
        'is_default' => false,
        'os' => 'linux',
        'cpus' => 2,
        'ram' => 2048,
        'disk_size' => 20,
        'disk_path' => '',
        'vnet' => 'tap0',
        'bridge_mode' => true,
        'bridge_interface' => 'bridge0',
        'parent_interface' => $net_interfaces[0] ?? 'vtnet0',
        'autostart' => true
    ];
    $is_aapanel = ($edit_vm_id === 'aapanel' || !empty($curr_vm['is_default']));
    ?>

    <div class="panel panel-default">
        <div class="panel-heading">
            <h2 class="panel-title">
                <i class="fa-solid <?= $is_aapanel ? 'fa-cubes' : 'fa-server' ?>"></i>
                <?= $is_aapanel ? gettext("Edit aaPanel (Default Built-in Virtual Machine)") : ($is_edit ? gettext("Edit Virtual Machine: ") . htmlspecialchars($curr_vm['name']) : gettext("Tambah Virtual Machine Baru")) ?>
            </h2>
        </div>
        <div class="panel-body">
            <?php if ($is_aapanel): ?>
                <div class="alert alert-info">
                    <i class="fa-solid fa-circle-info"></i>
                    <strong><?= gettext("aaPanel Virtual Machine Default:") ?></strong>
                    <?= gettext("aaPanel adalah paket bawaan sistem yang tidak dapat dihapus. Anda hanya dapat menyesuaikan ukuran HDD, alokasi RAM, antarmuka Virtual Ethernet (TAP), dan opsi Bridge Mode jaringan.") ?>
                </div>
            <?php endif; ?>

            <form method="post" action="services_virtual.php">
                <input type="hidden" name="act" value="save_vm" />
                <input type="hidden" name="vm_id" value="<?= htmlspecialchars($curr_vm['id'] ?: ($is_aapanel ? 'aapanel' : '')) ?>" />

                <div class="form-group row">
                    <label class="col-sm-3 col-form-label text-right font-weight-bold"><?= gettext("VM Identifier / ID") ?></label>
                    <div class="col-sm-7">
                        <input type="text" class="form-control" value="<?= htmlspecialchars($curr_vm['id'] ?: ($is_aapanel ? 'aapanel' : '')) ?>" <?= ($is_aapanel || $is_edit) ? 'readonly disabled' : 'name="vm_id" required pattern="[a-zA-Z0-9_\-]+"' ?> placeholder="contoh: ubuntu-srv" />
                        <small class="form-text text-muted"><?= gettext("Pengenal unik Virtual Machine di dalam hypervisor bhyve.") ?></small>
                    </div>
                </div>

                <div class="form-group row">
                    <label class="col-sm-3 col-form-label text-right font-weight-bold"><?= gettext("Nama Virtual Machine") ?></label>
                    <div class="col-sm-7">
                        <?php if ($is_aapanel): ?>
                            <input type="text" class="form-control" name="name" value="aaPanel" readonly disabled />
                            <small class="form-text text-muted"><?= gettext("Nama dikunci untuk paket bawaan default aaPanel.") ?></small>
                        <?php else: ?>
                            <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($curr_vm['name']) ?>" required placeholder="contoh: Ubuntu Server 24.04" />
                            <small class="form-text text-muted"><?= gettext("Nama label tampilan di WebGUI pfSense.") ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$is_aapanel): ?>
                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label text-right font-weight-bold"><?= gettext("Deskripsi") ?></label>
                        <div class="col-sm-7">
                            <input type="text" class="form-control" name="description" value="<?= htmlspecialchars($curr_vm['description']) ?>" placeholder="Deskripsi VM" />
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label text-right font-weight-bold"><?= gettext("Tipe Sistem Operasi (OS)") ?></label>
                        <div class="col-sm-4">
                            <select name="os" class="form-control">
                                <option value="linux" <?= ($curr_vm['os'] == 'linux') ? 'selected' : '' ?>>Linux (Ubuntu / Debian / CentOS / AlmaLinux)</option>
                                <option value="freebsd" <?= ($curr_vm['os'] == 'freebsd') ? 'selected' : '' ?>>FreeBSD / BSD Guest</option>
                                <option value="windows" <?= ($curr_vm['os'] == 'windows') ? 'selected' : '' ?>>Microsoft Windows</option>
                                <option value="other" <?= ($curr_vm['os'] == 'other') ? 'selected' : '' ?>>Custom / Other</option>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>

                <hr />
                <h4 style="margin-left: 15px; margin-bottom: 20px; color: #2a6496;">
                    <i class="fa-solid fa-microchip"></i> <?= gettext("Alokasi Resource Hardware (CPU, RAM, HDD)") ?>
                </h4>

                <div class="form-group row">
                    <label class="col-sm-3 col-form-label text-right font-weight-bold"><?= gettext("vCPU Cores") ?></label>
                    <div class="col-sm-3">
                        <select name="cpus" class="form-control">
                            <option value="1" <?= ($curr_vm['cpus'] == 1) ? 'selected' : '' ?>>1 vCPU</option>
                            <option value="2" <?= ($curr_vm['cpus'] == 2) ? 'selected' : '' ?>>2 vCPU (Rekomendasi)</option>
                            <option value="4" <?= ($curr_vm['cpus'] == 4) ? 'selected' : '' ?>>4 vCPU</option>
                            <option value="8" <?= ($curr_vm['cpus'] == 8) ? 'selected' : '' ?>>8 vCPU</option>
                        </select>
                    </div>
                </div>

                <div class="form-group row">
                    <label class="col-sm-3 col-form-label text-right font-weight-bold"><i class="fa-solid fa-memory"></i> <?= gettext("Alokasi RAM (Memory)") ?></label>
                    <div class="col-sm-4">
                        <div class="input-group">
                            <input type="number" class="form-control" name="ram" value="<?= htmlspecialchars($curr_vm['ram']) ?>" min="256" step="256" required />
                            <span class="input-group-addon">MB</span>
                        </div>
                        <small class="form-text text-muted"><?= gettext("Kapasitas RAM fisik untuk VM (Contoh: 1024 = 1GB, 2048 = 2GB, 4096 = 4GB, 8192 = 8GB).") ?></small>
                    </div>
                </div>

                <div class="form-group row">
                    <label class="col-sm-3 col-form-label text-right font-weight-bold"><i class="fa-solid fa-hard-drive"></i> <?= gettext("Kapasitas Penyimpanan (HDD)") ?></label>
                    <div class="col-sm-4">
                        <div class="input-group">
                            <input type="number" class="form-control" name="disk_size" value="<?= htmlspecialchars($curr_vm['disk_size']) ?>" min="5" step="5" required />
                            <span class="input-group-addon">GB</span>
                        </div>
                        <small class="form-text text-muted"><?= gettext("Ukuran virtual disk image yang dialokasikan.") ?></small>
                    </div>
                </div>

                <div class="form-group row">
                    <label class="col-sm-3 col-form-label text-right font-weight-bold"><?= gettext("Jalur Berkas Disk (Path Image)") ?></label>
                    <div class="col-sm-7">
                        <input type="text" class="form-control" name="disk_path" value="<?= htmlspecialchars($curr_vm['disk_path'] ?: ($is_aapanel ? '/usr/local/vm/aapanel/disk.raw' : '')) ?>" placeholder="/usr/local/vm/nama_vm/disk.raw" />
                        <small class="form-text text-muted"><?= gettext("Lokasi file image virtual disk di sistem file pfSense (Dibuat otomatis bila belum ada).") ?></small>
                    </div>
                </div>

                <hr />
                <h4 style="margin-left: 15px; margin-bottom: 20px; color: #2a6496;">
                    <i class="fa-solid fa-network-wired"></i> <?= gettext("Pengaturan Jaringan (Virtual Ethernet & Bridge Mode)") ?>
                </h4>

                <div class="form-group row">
                    <label class="col-sm-3 col-form-label text-right font-weight-bold"><?= gettext("Virtual Ethernet (TAP Interface)") ?></label>
                    <div class="col-sm-4">
                        <input type="text" class="form-control" name="vnet" value="<?= htmlspecialchars($curr_vm['vnet'] ?: 'tap0') ?>" required placeholder="tap0" />
                        <small class="form-text text-muted"><?= gettext("Antarmuka TAP virtual FreeBSD yang dihubungkan ke VM.") ?></small>
                    </div>
                </div>

                <div class="form-group row">
                    <label class="col-sm-3 col-form-label text-right font-weight-bold"><?= gettext("Bridge Mode") ?></label>
                    <div class="col-sm-7">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="bridge_mode" value="1" <?= (!empty($curr_vm['bridge_mode'])) ? 'checked' : '' ?> />
                                <strong><?= gettext("Aktifkan Bridge Mode Jaringan") ?></strong>
                            </label>
                        </div>
                        <small class="form-text text-muted"><?= gettext("Menghubungkan antarmuka TAP VM langsung ke bridge LAN pfSense, sehingga VM berada di subnet yang sama dan mendapatkan IP dari DHCP.") ?></small>
                    </div>
                </div>

                <div class="form-group row">
                    <label class="col-sm-3 col-form-label text-right font-weight-bold"><?= gettext("Nama Interface Bridge") ?></label>
                    <div class="col-sm-3">
                        <input type="text" class="form-control" name="bridge_interface" value="<?= htmlspecialchars($curr_vm['bridge_interface'] ?: 'bridge0') ?>" />
                        <small class="form-text text-muted"><?= gettext("Default: bridge0") ?></small>
                    </div>
                </div>

                <div class="form-group row">
                    <label class="col-sm-3 col-form-label text-right font-weight-bold"><?= gettext("Parent Physical LAN Interface") ?></label>
                    <div class="col-sm-4">
                        <select name="parent_interface" class="form-control">
                            <?php foreach ($net_interfaces as $netif): ?>
                                <option value="<?= htmlspecialchars($netif) ?>" <?= ($curr_vm['parent_interface'] == $netif) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($netif) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted"><?= gettext("Interface fisik yang digabungkan ke bridge (biasanya LAN/vtnet0/em0).") ?></small>
                    </div>
                </div>

                <div class="form-group row">
                    <label class="col-sm-3 col-form-label text-right font-weight-bold"><?= gettext("Auto-start saat Boot") ?></label>
                    <div class="col-sm-7">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="autostart" value="1" <?= (!empty($curr_vm['autostart'])) ? 'checked' : '' ?> />
                                <?= gettext("Nyalakan Virtual Machine ini secara otomatis saat pfSense menyala.") ?>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group row" style="margin-top: 30px;">
                    <div class="col-sm-9 col-sm-offset-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk"></i> <?= gettext("Simpan Pengaturan") ?>
                        </button>
                        <a href="services_virtual.php" class="btn btn-default">
                            <i class="fa-solid fa-arrow-left"></i> <?= gettext("Batal") ?>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($act == 'hypervisor'): ?>
    <!-- TAB HYPERVISOR STATUS -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h2 class="panel-title"><i class="fa-solid fa-circle-nodes"></i> <?= gettext("Status Hypervisor Kernel FreeBSD (KVM / Bhyve)") ?></h2>
        </div>
        <div class="panel-body">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th style="width: 25%;"><?= gettext("Modul Kernel") ?></th>
                        <th style="width: 50%;"><?= gettext("Fungsi & Deskripsi") ?></th>
                        <th style="width: 25%;"><?= gettext("Status Kernel") ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>vmm.ko</strong></td>
                        <td>Virtual Machine Monitor (Intel VT-x / AMD-V Hardware Virtualization)</td>
                        <td>
                            <?php if ($vmm_loaded): ?>
                                <span class="label label-success"><i class="fa-solid fa-check"></i> ACTIVE</span>
                            <?php else: ?>
                                <span class="label label-warning"><i class="fa-solid fa-triangle-exclamation"></i> NOT LOADED</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>if_tap.ko</strong></td>
                        <td>Virtual Ethernet TAP Driver untuk Guest Network</td>
                        <td>
                            <?php if ($tap_loaded): ?>
                                <span class="label label-success"><i class="fa-solid fa-check"></i> ACTIVE</span>
                            <?php else: ?>
                                <span class="label label-warning"><i class="fa-solid fa-triangle-exclamation"></i> NOT LOADED</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>if_bridge.ko</strong></td>
                        <td>Network Bridge Driver untuk Bridging VM ke LAN Fisik</td>
                        <td>
                            <?php if ($bridge_loaded): ?>
                                <span class="label label-success"><i class="fa-solid fa-check"></i> ACTIVE</span>
                            <?php else: ?>
                                <span class="label label-warning"><i class="fa-solid fa-triangle-exclamation"></i> NOT LOADED</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>nmdm.ko</strong></td>
                        <td>Virtual Serial Null-Modem Driver untuk Akses Konsol VM</td>
                        <td>
                            <?php if ($nmdm_loaded): ?>
                                <span class="label label-success"><i class="fa-solid fa-check"></i> ACTIVE</span>
                            <?php else: ?>
                                <span class="label label-warning"><i class="fa-solid fa-triangle-exclamation"></i> NOT LOADED</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <form method="post" action="services_virtual.php" style="margin-top: 20px;">
                <input type="hidden" name="act" value="setup_hypervisor" />
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-gears"></i> <?= gettext("Muat & Aktifkan Seluruh Modul Hypervisor") ?>
                </button>
            </form>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading">
            <h2 class="panel-title"><i class="fa-solid fa-terminal"></i> <?= gettext("Detail Kernel Modules (kldstat)") ?></h2>
        </div>
        <div class="panel-body">
            <pre style="background: #1e1e1e; color: #a9b7c6; padding: 15px; border-radius: 6px; font-family: monospace; font-size: 13px;"><?= htmlspecialchars($kldstat) ?></pre>
        </div>
    </div>

<?php elseif ($act == 'network'): ?>
    <!-- TAB NETWORK & BRIDGE -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h2 class="panel-title"><i class="fa-solid fa-network-wired"></i> <?= gettext("Status Interface Jaringan & Bridge Virtual") ?></h2>
        </div>
        <div class="panel-body">
            <p><?= gettext("Antarmuka fisik dan virtual yang saat ini terdaftar di pfSense:") ?></p>
            <pre style="background: #1e1e1e; color: #a9b7c6; padding: 15px; border-radius: 6px; font-family: monospace; font-size: 13px;"><?= htmlspecialchars(shell_exec("/sbin/ifconfig -a 2>&1") ?? "Tidak ada data") ?></pre>
        </div>
    </div>

<?php else: ?>
    <!-- MAIN DASHBOARD & VM LIST -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h2 class="panel-title">
                <i class="fa-solid fa-server"></i> <?= gettext("Daftar Virtual Machine (KVM / Bhyve Hypervisor)") ?>
            </h2>
        </div>
        <div class="panel-body">
            <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <a href="services_virtual.php?act=add" class="btn btn-success">
                        <i class="fa-solid fa-plus"></i> <?= gettext("Tambah Virtual Machine Baru") ?>
                    </a>
                    <form method="post" action="services_virtual.php" style="display: inline-block; margin-left: 5px;">
                        <input type="hidden" name="act" value="setup_hypervisor" />
                        <button type="submit" class="btn btn-primary" title="Muat modul kernel hypervisor">
                            <i class="fa-solid fa-shield-halved"></i> <?= gettext("Aktifkan Hypervisor") ?>
                        </button>
                    </form>
                </div>
                <div>
                    <a href="http://<?= htmlspecialchars($_SERVER["SERVER_ADDR"] ?? "192.168.1.1") ?>:8888" target="_blank" class="btn btn-info">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> <?= gettext("Buka aaPanel Web (Port 8888)") ?>
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover table-condensed">
                    <thead>
                        <tr>
                            <th style="width: 20%;"><?= gettext("Nama & Identitas") ?></th>
                            <th style="width: 15%;"><?= gettext("Alokasi Resource") ?></th>
                            <th style="width: 20%;"><?= gettext("Konfigurasi Network") ?></th>
                            <th style="width: 20%;"><?= gettext("Status Hypervisor") ?></th>
                            <th style="width: 25%; text-align: right;"><?= gettext("Aksi & Kontrol") ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vms as $id => $vm): ?>
                            <?php
                            $is_running = is_vm_running($id);
                            $is_default = !empty($vm['is_default']) || ($id === 'aapanel');
                            ?>
                            <tr>
                                <td>
                                    <strong>
                                        <i class="fa-solid <?= $is_default ? 'fa-cubes' : 'fa-server' ?>"></i>
                                        <?= htmlspecialchars($vm['name']) ?>
                                    </strong>
                                    <?php if ($is_default): ?>
                                        <span class="label label-primary" title="<?= gettext('Paket Bawaan Default pfSense') ?>" style="margin-left: 5px;">
                                            <i class="fa-solid fa-lock"></i> <?= gettext("Default") ?>
                                        </span>
                                    <?php endif; ?>
                                    <div style="font-size: 11px; color: #777; margin-top: 3px;">
                                        ID: <code><?= htmlspecialchars($id) ?></code>
                                        <?php if (!empty($vm['description'])): ?>
                                            - <?= htmlspecialchars($vm['description']) ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge" style="background-color: #5bc0de;">
                                        <?= (int)($vm['cpus'] ?? 2) ?> vCPU
                                    </span>
                                    <span class="badge" style="background-color: #337ab7;">
                                        <?= (int)($vm['ram'] ?? 2048) ?> MB RAM
                                    </span>
                                    <span class="badge" style="background-color: #f0ad4e;">
                                        <?= (int)($vm['disk_size'] ?? 20) ?> GB HDD
                                    </span>
                                </td>
                                <td>
                                    <div>
                                        <i class="fa-solid fa-ethernet"></i>
                                        Interface: <code><?= htmlspecialchars($vm['vnet'] ?? 'tap0') ?></code>
                                    </div>
                                    <div style="font-size: 11px; margin-top: 2px;">
                                        <?php if (!empty($vm['bridge_mode'])): ?>
                                            <span class="label label-info">
                                                <i class="fa-solid fa-bridge"></i> Bridge: <?= htmlspecialchars($vm['bridge_interface'] ?? 'bridge0') ?> (<?= htmlspecialchars($vm['parent_interface'] ?? 'vtnet0') ?>)
                                            </span>
                                        <?php else: ?>
                                            <span class="label label-default">Isolated / Host Only</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($is_running): ?>
                                        <span class="label label-success" style="font-size: 12px; padding: 4px 8px;">
                                            <i class="fa-solid fa-play"></i> RUNNING (PID: <?= $is_running ?>)
                                        </span>
                                        <?php if ($id === 'aapanel'): ?>
                                            <div style="margin-top: 4px;">
                                                <a href="http://<?= htmlspecialchars($_SERVER["SERVER_ADDR"] ?? "192.168.1.1") ?>:8888" target="_blank" style="font-size: 12px; font-weight: bold; color: #20a53a;">
                                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> aaPanel Panel: Port 8888
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="label label-default" style="font-size: 12px; padding: 4px 8px;">
                                            <i class="fa-solid fa-stop"></i> STOPPED
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <?php if (!$is_running): ?>
                                        <!-- START BUTTON -->
                                        <form method="post" action="services_virtual.php" style="display: inline-block;">
                                            <input type="hidden" name="act" value="start" />
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>" />
                                            <button type="submit" class="btn btn-xs btn-success" title="<?= gettext('Nyalakan Virtual Machine') ?>">
                                                <i class="fa-solid fa-play"></i> <?= gettext("Start") ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <!-- STOP BUTTON -->
                                        <form method="post" action="services_virtual.php" style="display: inline-block;">
                                            <input type="hidden" name="act" value="stop" />
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>" />
                                            <button type="submit" class="btn btn-xs btn-danger" title="<?= gettext('Hentikan Virtual Machine') ?>" onclick="return confirm('Apakah Anda yakin ingin menghentikan VM ini?');">
                                                <i class="fa-solid fa-stop"></i> <?= gettext("Stop") ?>
                                            </button>
                                        </form>
                                        <!-- RESTART BUTTON -->
                                        <form method="post" action="services_virtual.php" style="display: inline-block;">
                                            <input type="hidden" name="act" value="restart" />
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>" />
                                            <button type="submit" class="btn btn-xs btn-warning" title="<?= gettext('Muat ulang VM') ?>">
                                                <i class="fa-solid fa-rotate-right"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- EDIT BUTTON -->
                                    <a href="services_virtual.php?act=edit&id=<?= urlencode($id) ?>" class="btn btn-xs btn-info" title="<?= $is_default ? gettext('Edit Resource aaPanel (HDD, RAM, Network)') : gettext('Edit Konfigurasi VM') ?>">
                                        <i class="fa-solid fa-pencil"></i> <?= gettext("Edit") ?>
                                    </a>

                                    <!-- DELETE BUTTON -->
                                    <?php if ($is_default): ?>
                                        <button class="btn btn-xs btn-default disabled" title="<?= gettext('aaPanel adalah paket default bawaan dan tidak dapat dihapus') ?>" disabled>
                                            <i class="fa-solid fa-lock"></i>
                                        </button>
                                    <?php else: ?>
                                        <form method="post" action="services_virtual.php" style="display: inline-block;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus VM ini secara permanen?');">
                                            <input type="hidden" name="act" value="del" />
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>" />
                                            <button type="submit" class="btn btn-xs btn-danger" title="<?= gettext('Hapus Virtual Machine') ?>">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include("foot.inc"); ?>
