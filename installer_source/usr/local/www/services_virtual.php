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
$images_dir = "/usr/local/vm/images";
$kvm_manager = "/usr/local/bin/kvm-manager";

$savemsg = "";
$errmsg = "";

// Pastikan direktori konfigurasi dan image tersedia
if (!is_dir($conf_dir)) {
    @mkdir($conf_dir, 0755, true);
}
if (!is_dir($images_dir)) {
    @mkdir($images_dir, 0755, true);
}

// Preset Berkas ISO Daring Berdasarkan Tipe OS
$os_online_presets = [
    'linux' => [
        [
            'name' => 'Debian 12 Bookworm (Minimal Netinst ~60MB)',
            'filename' => 'debian-12-netinst.iso',
            'url' => 'https://cdimage.debian.org/debian-cd/current/amd64/iso-cd/debian-12.7.0-amd64-netinst.iso'
        ],
        [
            'name' => 'Alpine Linux 3.20 (Virtual Standard ~60MB)',
            'filename' => 'alpine-virt-3.20.3-x86_64.iso',
            'url' => 'https://dl-cdn.alpinelinux.org/alpine/v3.20/releases/x86_64/alpine-virt-3.20.3-x86_64.iso'
        ],
        [
            'name' => 'Ubuntu Server 24.04 LTS (Mini/Netboot ~100MB)',
            'filename' => 'noble-mini-iso-amd64.iso',
            'url' => 'https://cdimage.ubuntu.com/ubuntu-mini-iso/daily-live/current/noble-mini-iso-amd64.iso'
        ],
        [
            'name' => 'Rocky Linux 9 Minimal (~2GB)',
            'filename' => 'Rocky-9-latest-x86_64-minimal.iso',
            'url' => 'https://download.rockylinux.org/pub/rocky/9/isos/x86_64/Rocky-9-latest-x86_64-minimal.iso'
        ]
    ],
    'freebsd' => [
        [
            'name' => 'FreeBSD 14.1-RELEASE (Bootonly ~350MB)',
            'filename' => 'FreeBSD-14.1-RELEASE-amd64-bootonly.iso',
            'url' => 'https://download.freebsd.org/releases/amd64/amd64/ISO-IMAGES/14.1/FreeBSD-14.1-RELEASE-amd64-bootonly.iso'
        ],
        [
            'name' => 'FreeBSD 13.3-RELEASE (Bootonly ~350MB)',
            'filename' => 'FreeBSD-13.3-RELEASE-amd64-bootonly.iso',
            'url' => 'https://download.freebsd.org/releases/amd64/amd64/ISO-IMAGES/13.3/FreeBSD-13.3-RELEASE-amd64-bootonly.iso'
        ]
    ],
    'windows' => [
        [
            'name' => 'RedHat VirtIO Windows Drivers ISO (~500MB)',
            'filename' => 'virtio-win.iso',
            'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/latest-virtio/virtio-win.iso'
        ]
    ],
    'other' => []
];

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
                    'iso_path' => '',
                    'install_mode' => 'disk',
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
            'iso_path' => '',
            'install_mode' => 'disk',
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

// Dapatkan daftar berkas image yang ada
function get_available_images() {
    global $images_dir;
    $files = [];
    if (is_dir($images_dir)) {
        foreach (scandir($images_dir) as $f) {
            if ($f === '.' || $f === '..') continue;
            $p = "{$images_dir}/{$f}";
            if (is_file($p)) {
                $bytes = filesize($p);
                $mb = round($bytes / (1024 * 1024), 2);
                $gb = round($bytes / (1024 * 1024 * 1024), 2);
                $files[] = [
                    'name' => $f,
                    'path' => $p,
                    'size_bytes' => $bytes,
                    'size_human' => ($gb >= 1) ? "{$gb} GB" : "{$mb} MB",
                    'modified' => date('Y-m-d H:i', filemtime($p))
                ];
            }
        }
    }
    return $files;
}

// Cek unduhan yang sedang berjalan
function get_active_downloads() {
    $downloads = [];
    $pids = glob("/var/run/kvm/download_*.pid");
    if (!empty($pids)) {
        foreach ($pids as $pf) {
            $fname = preg_replace('/^download_(.*)\.pid$/', '$1', basename($pf));
            $pid = trim(@file_get_contents($pf));
            if (!empty($pid) && is_numeric($pid)) {
                $check = shell_exec("/bin/ps -p " . escapeshellarg($pid) . " -o pid= 2>/dev/null");
                if (!empty(trim($check))) {
                    $log_f = "/var/log/kvm/download_{$fname}.log";
                    $last_log = "";
                    if (file_exists($log_f)) {
                        $last_log = shell_exec("tail -n 3 " . escapeshellarg($log_f) . " 2>/dev/null");
                    }
                    $cur_path = "/usr/local/vm/images/{$fname}";
                    $cur_size = file_exists($cur_path) ? round(filesize($cur_path) / (1024 * 1024), 2) . " MB" : "0 MB";
                    $downloads[] = [
                        'filename' => $fname,
                        'pid' => $pid,
                        'current_size' => $cur_size,
                        'log' => $last_log
                    ];
                }
            }
        }
    }
    return $downloads;
}

$vms = get_vm_config();
$act = $_REQUEST['act'] ?? '';
$vm_id = $_REQUEST['id'] ?? '';

// Handle VM Actions, Uploads, and Downloads
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
            $disk_p = $vms[$vm_id]['disk_path'] ?? '';
            $iso_p = $vms[$vm_id]['iso_path'] ?? '';
            $vm_name = $vms[$vm_id]['name'] ?? $vm_id;

            // Eksekusi pembersihan mendalam via kvm-manager
            mwexec("{$kvm_manager} delete " . escapeshellarg($vm_id));

            // Pastikan berkas virtual disk fisik benar-benar terhapus dari storage
            if (!empty($disk_p) && file_exists($disk_p)) {
                @unlink($disk_p);
            }
            $vm_dir = "/usr/local/vm/{$vm_id}";
            if (is_dir($vm_dir)) {
                @rmdir($vm_dir);
            }

            // Hapus berkas media installer ISO jika ada dan tidak dipakai VM lain
            if (!empty($iso_p) && file_exists($iso_p)) {
                $used_elsewhere = false;
                foreach ($vms as $k => $v) {
                    if ($k !== $vm_id && !empty($v['iso_path']) && $v['iso_path'] === $iso_p) {
                        $used_elsewhere = true;
                        break;
                    }
                }
                if (!$used_elsewhere) {
                    @unlink($iso_p);
                }
            }

            unset($vms[$vm_id]);
            save_vm_config($vms);
            $savemsg = sprintf(gettext("Virtual Machine '%s' dan seluruh berkas virtual disk berhasil dihapus permanen dari sistem penyimpanan."), htmlspecialchars($vm_name));
        }
    } elseif ($act == 'eject_iso' && !empty($vm_id)) {
        if (isset($vms[$vm_id])) {
            $iso_p = $vms[$vm_id]['iso_path'] ?? '';
            $iso_name = basename($iso_p);

            mwexec("{$kvm_manager} eject-iso " . escapeshellarg($vm_id));

            if (!empty($iso_p) && file_exists($iso_p)) {
                $used_elsewhere = false;
                foreach ($vms as $k => $v) {
                    if ($k !== $vm_id && !empty($v['iso_path']) && $v['iso_path'] === $iso_p) {
                        $used_elsewhere = true;
                        break;
                    }
                }
                if (!$used_elsewhere) {
                    @unlink($iso_p);
                }
            }

            $vms[$vm_id]['iso_path'] = '';
            $vms[$vm_id]['install_mode'] = 'disk';
            save_vm_config($vms);
            $savemsg = sprintf(gettext("Media instalasi '%s' berhasil dilepas dari VM '%s' dan berkas ISO telah dihapus permanen dari penyimpanan agar tidak menjadi sampah."), htmlspecialchars($iso_name), htmlspecialchars($vms[$vm_id]['name']));
        }
    } elseif ($act == 'purge_images') {
        mwexec("{$kvm_manager} purge-images");
        if (is_dir($images_dir)) {
            $files = glob("{$images_dir}/*");
            if (!empty($files)) {
                foreach ($files as $f) {
                    if (is_file($f)) {
                        @unlink($f);
                    }
                }
            }
        }
        foreach ($vms as $k => &$v) {
            if (!empty($v['iso_path'])) {
                $v['iso_path'] = '';
                $v['install_mode'] = 'disk';
            }
        }
        save_vm_config($vms);
        $savemsg = gettext("Seluruh sampah berkas ISO dan image instalasi berhasil dibersihkan dari penyimpanan pfSense.");
        $act = 'images';
    } elseif ($act == 'upload_image') {
        // UPLOAD IMAGE MANUAL DARI BROWSER
        if (isset($_FILES['iso_file']) && $_FILES['iso_file']['error'] === UPLOAD_ERR_OK) {
            $orig_name = basename($_FILES['iso_file']['name']);
            $safe_name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $orig_name);
            $dest = "{$images_dir}/{$safe_name}";
            if (move_uploaded_file($_FILES['iso_file']['tmp_name'], $dest)) {
                $savemsg = gettext("Berkas image '") . htmlspecialchars($safe_name) . gettext("' berhasil diunggah ke repositori images.");
            } else {
                $errmsg = gettext("Gagal menyimpan berkas yang diunggah ke ") . htmlspecialchars($dest);
            }
        } else {
            $upload_err_code = $_FILES['iso_file']['error'] ?? 'No file';
            $errmsg = gettext("Gagal mengunggah berkas. Kode kesalahan: ") . $upload_err_code;
        }
        $act = 'images';
    } elseif ($act == 'download_image') {
        // DOWNLOAD IMAGE DARING DARI URL
        $down_url = trim($_POST['download_url'] ?? '');
        $down_file = trim($_POST['download_filename'] ?? '');
        if (empty($down_url)) {
            $errmsg = gettext("URL pengunduhan image tidak boleh kosong!");
        } else {
            if (empty($down_file)) {
                $parsed = basename(parse_url($down_url, PHP_URL_PATH));
                $down_file = !empty($parsed) ? $parsed : ("os_image_" . time() . ".iso");
            }
            $safe_down_file = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $down_file);
            mwexec_bg("{$kvm_manager} download-image " . escapeshellarg($safe_down_file) . " " . escapeshellarg($down_url));
            $savemsg = gettext("Pengunduhan image daring '") . htmlspecialchars($safe_down_file) . gettext("' dimulai di latar belakang.");
        }
        $act = 'images';
    } elseif ($act == 'delete_image') {
        $del_file = trim($_POST['image_filename'] ?? '');
        if (!empty($del_file)) {
            $safe_del = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $del_file);
            mwexec("{$kvm_manager} delete-image " . escapeshellarg($safe_del));
            $savemsg = gettext("Berkas image '") . htmlspecialchars($safe_del) . gettext("' berhasil dihapus.");
        }
        $act = 'images';
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
                // Untuk VM custom: Dukungan Instalasi Online atau Upload Manual
                $name = trim($_POST['name'] ?? $edit_id);
                $description = trim($_POST['description'] ?? '');
                $os = trim($_POST['os'] ?? 'linux');
                $install_mode = trim($_POST['install_mode'] ?? 'online');
                $iso_path = "";

                // Periksa apakah ada upload manual berkas ISO langsung di form ini
                if (isset($_FILES['vm_iso_upload']) && $_FILES['vm_iso_upload']['error'] === UPLOAD_ERR_OK) {
                    $upl_name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($_FILES['vm_iso_upload']['name']));
                    $dest_path = "{$images_dir}/{$upl_name}";
                    if (move_uploaded_file($_FILES['vm_iso_upload']['tmp_name'], $dest_path)) {
                        $iso_path = $dest_path;
                    }
                }

                // Jika tidak upload langsung, gunakan pilihan form
                if (empty($iso_path)) {
                    if ($install_mode === 'manual') {
                        $sel_img = trim($_POST['selected_image'] ?? '');
                        if (!empty($sel_img) && file_exists("{$images_dir}/{$sel_img}")) {
                            $iso_path = "{$images_dir}/{$sel_img}";
                        }
                    } elseif ($install_mode === 'online') {
                        $on_url = trim($_POST['online_iso_url'] ?? '');
                        $on_file = trim($_POST['online_iso_file'] ?? '');
                        if (!empty($on_url)) {
                            if (empty($on_file)) {
                                $on_file = basename(parse_url($on_url, PHP_URL_PATH)) ?: ("{$edit_id}_install.iso");
                            }
                            $safe_on_file = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $on_file);
                            $iso_path = "{$images_dir}/{$safe_on_file}";
                            if (!file_exists($iso_path)) {
                                mwexec_bg("{$kvm_manager} download-image " . escapeshellarg($safe_on_file) . " " . escapeshellarg($on_url));
                            }
                        }
                    }
                }

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
                    'iso_path' => $iso_path,
                    'install_mode' => $install_mode,
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

$available_images = get_available_images();
$active_downloads = get_active_downloads();

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
$tab_array[] = array(gettext("Virtual Machines"), ($act != 'images' && $act != 'hypervisor' && $act != 'network'), "services_virtual.php");
$tab_array[] = array(gettext("ISO & Images Manager"), ($act == 'images'), "services_virtual.php?act=images");
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
        'iso_path' => '',
        'install_mode' => 'online',
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
                    <?= gettext("aaPanel adalah paket bawaan sistem yang tidak dapat dihapus. Anda dapat menyesuaikan ukuran HDD, alokasi RAM, antarmuka Virtual Ethernet (TAP), dan opsi Bridge Mode jaringan.") ?>
                    <div style="margin-top: 10px; background: rgba(0,0,0,0.1); padding: 8px 12px; border-radius: 4px; font-family: monospace; font-size: 11px;">
                        <strong><?= gettext("Perintah Instalasi Resmi aaPanel (Linux VM):") ?></strong><br>
                        <code>URL=https://www.aapanel.com/script/install_panel_en.sh &amp;&amp; if [ -f /usr/bin/curl ];then curl -ksSO $URL ;else wget --no-check-certificate -O install_panel_en.sh $URL;fi;bash install_panel_en.sh ipssl</code>
                    </div>
                </div>
            <?php endif; ?>

            <form method="post" action="services_virtual.php" enctype="multipart/form-data">
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
                            <select name="os" id="os_select" class="form-control" onchange="onOsTypeChange(this.value)">
                                <option value="linux" <?= ($curr_vm['os'] == 'linux') ? 'selected' : '' ?>>Linux (Ubuntu / Debian / CentOS / Alpine / Rocky)</option>
                                <option value="freebsd" <?= ($curr_vm['os'] == 'freebsd') ? 'selected' : '' ?>>FreeBSD / BSD Guest</option>
                                <option value="windows" <?= ($curr_vm['os'] == 'windows') ? 'selected' : '' ?>>Microsoft Windows</option>
                                <option value="other" <?= ($curr_vm['os'] == 'other') ? 'selected' : '' ?>>Custom / Other</option>
                            </select>
                            <small class="form-text text-muted"><?= gettext("Menyesuaikan preset instalasi online dan optimasi driver bhyve.") ?></small>
                        </div>
                    </div>

                    <hr />
                    <h4 style="margin-left: 15px; margin-bottom: 10px; color: #2a6496;">
                        <i class="fa-solid fa-compact-disc"></i> <?= gettext("Pilihan Metode Instalasi (Online atau Upload Manual)") ?>
                    </h4>

                    <div class="alert alert-info" style="margin-left: 15px; margin-right: 15px; margin-bottom: 15px; font-size: 12px;">
                        <i class="fa-solid fa-circle-info"></i> <strong><?= gettext("Media Instalasi & Penghapusan Otomatis:") ?></strong>
                        <?= gettext("Media ISO/citra yang dipilih atau diunggah di bawah hanya berfungsi sebagai installer sementara. Setelah instalasi OS selesai, Anda dapat langsung menghapus berkas ISO melalui tombol 'Selesai & Hapus ISO' di daftar VM agar tidak menjadi sampah. Jika VM ini dihapus di kemudian hari, seluruh Virtual Disk fisik (.raw) juga akan otomatis ikut terhapus bersih dari pfSense.") ?>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label text-right font-weight-bold"><?= gettext("Metode Sumber Image") ?></label>
                        <div class="col-sm-7">
                            <div class="radio">
                                <label>
                                    <input type="radio" name="install_mode" value="online" id="mode_online" <?= ($curr_vm['install_mode'] == 'online') ? 'checked' : '' ?> onclick="toggleInstallMode('online')" />
                                    <strong><i class="fa-solid fa-cloud-arrow-down text-primary"></i> <?= gettext("Install Online (Pilih Preset Resmi OS atau Masukkan URL)") ?></strong>
                                </label>
                            </div>
                            <div class="radio">
                                <label>
                                    <input type="radio" name="install_mode" value="manual" id="mode_manual" <?= ($curr_vm['install_mode'] == 'manual') ? 'checked' : '' ?> onclick="toggleInstallMode('manual')" />
                                    <strong><i class="fa-solid fa-cloud-arrow-up text-success"></i> <?= gettext("Upload Manual / Pilih Berkas Image dari Komputer") ?></strong>
                                </label>
                            </div>
                            <div class="radio">
                                <label>
                                    <input type="radio" name="install_mode" value="disk" id="mode_disk" <?= ($curr_vm['install_mode'] == 'disk') ? 'checked' : '' ?> onclick="toggleInstallMode('disk')" />
                                    <strong><i class="fa-solid fa-hard-drive text-muted"></i> <?= gettext("Boot dari Virtual Disk (Tanpa Media ISO Instalasi)") ?></strong>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- BOX INSTALL ONLINE -->
                    <div id="box_install_online" style="<?= ($curr_vm['install_mode'] == 'online') ? '' : 'display: none;' ?>">
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label text-right font-weight-bold"><?= gettext("Preset Image Online Resmi") ?></label>
                            <div class="col-sm-7">
                                <select id="preset_online_select" class="form-control" onchange="applyPresetUrl(this.value)">
                                    <option value=""><?= gettext("-- Pilih Preset Image Resmi OS --") ?></option>
                                    <?php foreach ($os_online_presets['linux'] as $idx => $p): ?>
                                        <option value="<?= htmlspecialchars(json_encode($p)) ?>" data-os="linux"><?= htmlspecialchars($p['name']) ?></option>
                                    <?php endforeach; ?>
                                    <?php foreach ($os_online_presets['freebsd'] as $idx => $p): ?>
                                        <option value="<?= htmlspecialchars(json_encode($p)) ?>" data-os="freebsd"><?= htmlspecialchars($p['name']) ?></option>
                                    <?php endforeach; ?>
                                    <?php foreach ($os_online_presets['windows'] as $idx => $p): ?>
                                        <option value="<?= htmlspecialchars(json_encode($p)) ?>" data-os="windows"><?= htmlspecialchars($p['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label text-right font-weight-bold"><?= gettext("URL Download Image Daring") ?></label>
                            <div class="col-sm-7">
                                <input type="url" class="form-control" name="online_iso_url" id="online_iso_url" placeholder="https://example.com/os.iso" />
                                <small class="form-text text-muted"><?= gettext("Jika berkas belum ada di repositori lokal, pfSense akan mengunduhnya secara otomatis di latar belakang.") ?></small>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label text-right font-weight-bold"><?= gettext("Nama File Simpanan") ?></label>
                            <div class="col-sm-4">
                                <input type="text" class="form-control" name="online_iso_file" id="online_iso_file" placeholder="nama_berkas.iso" />
                            </div>
                        </div>
                    </div>

                    <!-- BOX UPLOAD MANUAL -->
                    <div id="box_install_manual" style="<?= ($curr_vm['install_mode'] == 'manual') ? '' : 'display: none;' ?>">
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label text-right font-weight-bold"><?= gettext("Pilih dari Image yang Sudah Diunggah") ?></label>
                            <div class="col-sm-5">
                                <select name="selected_image" class="form-control">
                                    <option value=""><?= gettext("-- Pilih Image Tersedia --") ?></option>
                                    <?php foreach ($available_images as $img): ?>
                                        <?php $isSelected = (basename($curr_vm['iso_path']) === $img['name']); ?>
                                        <option value="<?= htmlspecialchars($img['name']) ?>" <?= $isSelected ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($img['name']) ?> (<?= htmlspecialchars($img['size_human']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-sm-2">
                                <a href="services_virtual.php?act=images" class="btn btn-default" target="_blank" title="Kelola file image di tab baru">
                                    <i class="fa-solid fa-folder-open"></i> <?= gettext("Kelola Image") ?>
                                </a>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label text-right font-weight-bold"><?= gettext("Atau Upload Berkas Baru") ?></label>
                            <div class="col-sm-7">
                                <input type="file" name="vm_iso_upload" class="form-control" accept=".iso,.img,.raw,.qcow2" />
                                <small class="form-text text-muted"><?= gettext("Unggah berkas image (.iso, .img, .raw, .qcow2) langsung dari perangkat Anda dan sematkan ke VM ini.") ?></small>
                            </div>
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

    <script type="text/javascript">
    function toggleInstallMode(mode) {
        var boxOnline = document.getElementById('box_install_online');
        var boxManual = document.getElementById('box_install_manual');
        if (boxOnline) boxOnline.style.display = (mode === 'online') ? 'block' : 'none';
        if (boxManual) boxManual.style.display = (mode === 'manual') ? 'block' : 'none';
    }

    function onOsTypeChange(osType) {
        var select = document.getElementById('preset_online_select');
        if (!select) return;
        var options = select.options;
        for (var i = 0; i < options.length; i++) {
            var opt = options[i];
            var dataOs = opt.getAttribute('data-os');
            if (!dataOs) continue;
            if (dataOs === osType || osType === 'other') {
                opt.style.display = 'block';
            } else {
                opt.style.display = 'none';
            }
        }
        select.selectedIndex = 0;
    }

    function applyPresetUrl(jsonStr) {
        if (!jsonStr) return;
        try {
            var data = JSON.parse(jsonStr);
            if (data.url) document.getElementById('online_iso_url').value = data.url;
            if (data.filename) document.getElementById('online_iso_file').value = data.filename;
        } catch(e) {}
    }
    </script>

<?php elseif ($act == 'images'): ?>
    <!-- TAB ISO & IMAGES MANAGER -->
    <div class="alert alert-info">
        <i class="fa-solid fa-circle-info"></i> <strong><?= gettext("Pemberitahuan Media Instalasi:") ?></strong>
        <?= gettext("Berkas ISO/citra yang ada di repositori ini hanya digunakan sebagai media boot sementara saat proses instalasi OS ke virtual disk. Setelah instalasi selesai, Anda disarankan langsung menghapus berkas ISO (atau klik tombol 'Selesai & Hapus ISO' di daftar VM) agar ruang penyimpanan pfSense tetap bersih dan tidak menjadi sampah.") ?>
    </div>

    <div class="row">
        <!-- BOX UPLOAD MANUAL -->
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h2 class="panel-title"><i class="fa-solid fa-cloud-arrow-up text-success"></i> <?= gettext("Upload Berkas Image / ISO Manual") ?></h2>
                </div>
                <div class="panel-body">
                    <form method="post" action="services_virtual.php" enctype="multipart/form-data">
                        <input type="hidden" name="act" value="upload_image" />
                        <div class="form-group">
                            <label class="font-weight-bold"><?= gettext("Pilih Berkas dari Komputer (.iso, .img, .raw, .qcow2)") ?></label>
                            <input type="file" name="iso_file" class="form-control" accept=".iso,.img,.raw,.qcow2" required />
                        </div>
                        <button type="submit" class="btn btn-success">
                            <i class="fa-solid fa-upload"></i> <?= gettext("Unggah ke pfSense") ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- BOX DOWNLOAD ONLINE -->
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h2 class="panel-title"><i class="fa-solid fa-cloud-arrow-down text-primary"></i> <?= gettext("Unduh Image Resmi / Online") ?></h2>
                </div>
                <div class="panel-body">
                    <form method="post" action="services_virtual.php">
                        <input type="hidden" name="act" value="download_image" />
                        <div class="form-group">
                            <label class="font-weight-bold"><?= gettext("Pilih Preset Sistem Operasi Resmi") ?></label>
                            <select class="form-control" onchange="var d=this.value?JSON.parse(this.value):null; if(d){document.getElementById('down_url').value=d.url; document.getElementById('down_file').value=d.filename;}">
                                <option value=""><?= gettext("-- Pilih Preset OS Populer --") ?></option>
                                <optgroup label="Linux Distributions">
                                    <?php foreach ($os_online_presets['linux'] as $p): ?>
                                        <option value="<?= htmlspecialchars(json_encode($p)) ?>"><?= htmlspecialchars($p['name']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="FreeBSD OS">
                                    <?php foreach ($os_online_presets['freebsd'] as $p): ?>
                                        <option value="<?= htmlspecialchars(json_encode($p)) ?>"><?= htmlspecialchars($p['name']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Windows Drivers">
                                    <?php foreach ($os_online_presets['windows'] as $p): ?>
                                        <option value="<?= htmlspecialchars(json_encode($p)) ?>"><?= htmlspecialchars($p['name']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold"><?= gettext("URL Download Langsung") ?></label>
                            <input type="url" name="download_url" id="down_url" class="form-control" placeholder="https://..." required />
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold"><?= gettext("Simpan Sebagai (Nama File)") ?></label>
                            <input type="text" name="download_filename" id="down_file" class="form-control" placeholder="file.iso" />
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-download"></i> <?= gettext("Mulai Unduh di Latar Belakang") ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- UNDUHAN AKTIF -->
    <?php if (!empty($active_downloads)): ?>
        <div class="panel panel-info">
            <div class="panel-heading">
                <h2 class="panel-title"><i class="fa-solid fa-spinner fa-spin"></i> <?= gettext("Proses Unduhan Sedang Berlangsung") ?></h2>
            </div>
            <div class="panel-body">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th><?= gettext("Nama File Target") ?></th>
                            <th><?= gettext("PID") ?></th>
                            <th><?= gettext("Ukuran Saat Ini") ?></th>
                            <th><?= gettext("Status Log Terakhir") ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_downloads as $dw): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($dw['filename']) ?></strong></td>
                                <td><code><?= htmlspecialchars($dw['pid']) ?></code></td>
                                <td><span class="label label-info"><?= htmlspecialchars($dw['current_size']) ?></span></td>
                                <td><small style="font-family: monospace;"><?= nl2br(htmlspecialchars($dw['log'])) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- TABEL IMAGE TERSIMPAN -->
    <div class="panel panel-default">
        <div class="panel-heading clearfix">
            <div class="pull-left">
                <h2 class="panel-title" style="margin-top: 4px;"><i class="fa-solid fa-folder-tree"></i> <?= gettext("Daftar Berkas Image / ISO di pfSense (/usr/local/vm/images/)") ?></h2>
            </div>
            <div class="pull-right">
                <form method="post" action="services_virtual.php" style="display: inline-block;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus dan membersihkan SEMUA berkas installer ISO/IMG di direktori penyimpanan pfSense?');">
                    <input type="hidden" name="act" value="purge_images" />
                    <button type="submit" class="btn btn-xs btn-danger" title="<?= gettext('Bersihkan semua berkas ISO agar menghemat ruang penyimpanan') ?>">
                        <i class="fa-solid fa-broom"></i> <?= gettext("Bersihkan Semua Sampah ISO") ?>
                    </button>
                </form>
            </div>
        </div>
        <div class="panel-body">
            <?php if (empty($available_images)): ?>
                <div class="alert alert-warning">
                    <i class="fa-solid fa-triangle-exclamation"></i> <?= gettext("Belum ada berkas ISO atau disk image yang diunggah/diunduh ke direktori penyimpanan.") ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th style="width: 40%;"><?= gettext("Nama Berkas") ?></th>
                                <th style="width: 20%;"><?= gettext("Ukuran File") ?></th>
                                <th style="width: 25%;"><?= gettext("Tanggal Modifikasi") ?></th>
                                <th style="width: 15%; text-align: right;"><?= gettext("Aksi") ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($available_images as $im): ?>
                                <tr>
                                    <td>
                                        <i class="fa-solid fa-compact-disc text-primary"></i>
                                        <strong><?= htmlspecialchars($im['name']) ?></strong>
                                        <div style="font-size: 11px; color: #888;"><?= htmlspecialchars($im['path']) ?></div>
                                    </td>
                                    <td><span class="badge" style="background-color: #337ab7;"><?= htmlspecialchars($im['size_human']) ?></span></td>
                                    <td><?= htmlspecialchars($im['modified']) ?></td>
                                    <td style="text-align: right;">
                                        <form method="post" action="services_virtual.php" style="display: inline-block;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus berkas image ini?');">
                                            <input type="hidden" name="act" value="delete_image" />
                                            <input type="hidden" name="image_filename" value="<?= htmlspecialchars($im['name']) ?>" />
                                            <button type="submit" class="btn btn-xs btn-danger" title="<?= gettext('Hapus berkas ini') ?>">
                                                <i class="fa-solid fa-trash"></i> <?= gettext("Hapus") ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
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
                    <a href="services_virtual.php?act=images" class="btn btn-default" style="margin-left: 5px;">
                        <i class="fa-solid fa-compact-disc text-primary"></i> <?= gettext("Kelola ISO / Images") ?>
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
                            <th style="width: 20%;"><?= gettext("Media & Network") ?></th>
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
                                    <?php if (!empty($vm['iso_path'])): ?>
                                        <div style="font-size: 11px; margin-top: 4px; padding: 4px 6px; background-color: #fcf8e3; border: 1px solid #faebcc; border-radius: 4px; color: #8a6d3b;">
                                            <div><i class="fa-solid fa-compact-disc text-primary"></i> <strong>Installer:</strong> <?= htmlspecialchars(basename($vm['iso_path'])) ?></div>
                                            <div style="margin-top: 3px;">
                                                <form method="post" action="services_virtual.php" style="display: inline-block;" onsubmit="return confirm('Instalasi sistem operasi telah selesai? Media ISO akan dilepas dari VM dan berkas ISO akan dihapus permanen agar tidak menjadi sampah.');">
                                                    <input type="hidden" name="act" value="eject_iso" />
                                                    <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>" />
                                                    <button type="submit" class="btn btn-xs btn-warning" style="font-size: 10px; padding: 2px 6px;" title="<?= gettext('Instalasi selesai: Lepas & Hapus berkas installer dari penyimpanan') ?>">
                                                        <i class="fa-solid fa-eject"></i> <?= gettext("Selesai & Hapus ISO") ?>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endif; ?>
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
                                        <form method="post" action="services_virtual.php" style="display: inline-block;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus VM ini secara permanen beserta seluruh virtual disk dan datanya?');">
                                            <input type="hidden" name="act" value="del" />
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>" />
                                            <button type="submit" class="btn btn-xs btn-danger" title="<?= gettext('Hapus Virtual Machine & Seluruh Virtual Disk') ?>">
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
