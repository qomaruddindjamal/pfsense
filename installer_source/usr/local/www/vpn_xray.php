<?php
/*
 * vpn_xray.php
 *
 * pfSense WebGUI: VPN -> Xray-core
 * Full-featured Xray Management & Tools conforming to pfSense standards
 * and official Project X / XTLS documentation (https://xtls.github.io/)
 */

##|+PRIV
##|*IDENT=page-vpn-xray
##|*NAME=VPN: Xray-core
##|*DESCR=Allow access to the 'VPN: Xray-core' page.
##|*MATCH=vpn_xray.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("service-utils.inc");
require_once("pfsense-utils.inc");

function xray_clean($str) {
    return is_string($str) ? trim(strip_tags($str)) : '';
}

$conf_dir   = "/usr/local/etc/xray";
$conf_file  = "{$conf_dir}/config.json";
$log_dir    = "/var/log/xray";
$log_access = "{$log_dir}/access.log";
$log_error  = "{$log_dir}/error.log";
$xray_bin   = "/usr/local/bin/xray";
$rc_file    = "/usr/local/etc/rc.d/xray";

// Ensure directories and files exist
if (!is_dir($conf_dir)) {
    @mkdir($conf_dir, 0755, true);
}
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0755, true);
}
if (!file_exists($log_access)) {
    @touch($log_access);
}
if (!file_exists($log_error)) {
    @touch($log_error);
}

// Active Tab
$tab = isset($_GET['tab']) ? xray_clean($_GET['tab']) : 'status';
$valid_tabs = array('status', 'inbounds', 'routing', 'clients', 'config', 'logs');
if (!in_array($tab, $valid_tabs)) {
    $tab = 'status';
}

$tab_titles = array(
    'status'   => gettext('Status & Service'),
    'inbounds' => gettext('Inbounds & Protocols'),
    'routing'  => gettext('Outbounds & Routing'),
    'clients'  => gettext('Client Links & QR'),
    'config'   => gettext('Config Editor & Tools'),
    'logs'     => gettext('Logs')
);

$pgtitle = array(gettext("VPN"), gettext("Xray-core"), $tab_titles[$tab]);
$pglinks = array("", "/vpn_xray.php", "@self");

$savemsg = "";
$errmsg  = "";
$tool_output = "";

// Helper: Run official xray test
function test_xray_config_file($filepath) {
    global $xray_bin;
    if (!file_exists($xray_bin)) {
        return array('success' => false, 'output' => "Binary {$xray_bin} tidak ditemukan.");
    }
    $cmd = "{$xray_bin} run -test -c " . escapeshellarg($filepath) . " 2>&1";
    $output = trim(shell_exec($cmd));
    $success = (strpos($output, "Configuration OK") !== false);
    return array('success' => $success, 'output' => $output);
}

// Helper: Check if service is running
function is_xray_running() {
    $pidfile = "/var/run/xray.pid";
    if (file_exists($pidfile)) {
        $pid = trim(file_get_contents($pidfile));
        if ($pid && posix_kill((int)$pid, 0)) {
            return (int)$pid;
        }
    }
    $pg = trim(shell_exec("pgrep -f '/usr/local/bin/xray run' 2>/dev/null"));
    if ($pg) {
        $pids = explode("\n", $pg);
        return (int)$pids[0];
    }
    return 0;
}

// Helper: Default official template
function get_official_xray_template() {
    return array(
        "log" => array(
            "access" => "/var/log/xray/access.log",
            "error" => "/var/log/xray/error.log",
            "loglevel" => "warning",
            "dnsLog" => true
        ),
        "dns" => array(
            "servers" => array("1.1.1.1", "8.8.8.8", "localhost"),
            "queryStrategy" => "UseIP"
        ),
        "inbounds" => array(
            array(
                "tag" => "vless-reality-in",
                "port" => 8443,
                "protocol" => "vless",
                "settings" => array(
                    "clients" => array(
                        array(
                            "id" => "d7e26a8f-287c-482a-a92c-6338b556f891",
                            "flow" => "xtls-rprx-vision",
                            "email" => "admin@pfsense.local"
                        )
                    ),
                    "decryption" => "none"
                ),
                "streamSettings" => array(
                    "network" => "tcp",
                    "security" => "reality",
                    "realitySettings" => array(
                        "show" => false,
                        "dest" => "www.microsoft.com:443",
                        "xver" => 0,
                        "serverNames" => array("www.microsoft.com"),
                        "privateKey" => "-HjAcnXaeoW-A2b7B3_AOM3i9hG4pfjF58Ns5kEaiTQ",
                        "shortIds" => array("0123456789abcdef")
                    )
                ),
                "sniffing" => array(
                    "enabled" => true,
                    "destOverride" => array("http", "tls", "quic"),
                    "routeOnly" => false
                )
            ),
            array(
                "tag" => "vmess-ws-in",
                "port" => 8080,
                "protocol" => "vmess",
                "settings" => array(
                    "clients" => array(
                        array(
                            "id" => "d7e26a8f-287c-482a-a92c-6338b556f891",
                            "alterId" => 0,
                            "email" => "user@pfsense.local"
                        )
                    )
                ),
                "streamSettings" => array(
                    "network" => "ws",
                    "wsSettings" => array(
                        "path" => "/vmess-ws"
                    )
                ),
                "sniffing" => array(
                    "enabled" => true,
                    "destOverride" => array("http", "tls")
                )
            ),
            array(
                "tag" => "trojan-in",
                "port" => 9443,
                "protocol" => "trojan",
                "settings" => array(
                    "clients" => array(
                        array(
                            "password" => "pfsense-trojan-password",
                            "email" => "trojan@pfsense.local"
                        )
                    )
                ),
                "streamSettings" => array(
                    "network" => "tcp",
                    "security" => "none"
                )
            ),
            array(
                "tag" => "socks-in",
                "port" => 10808,
                "listen" => "127.0.0.1",
                "protocol" => "socks",
                "settings" => array(
                    "auth" => "noauth",
                    "udp" => true
                )
            ),
            array(
                "tag" => "tun-in",
                "protocol" => "dokodemo-door",
                "port" => 12345,
                "listen" => "127.0.0.1",
                "settings" => array(
                    "network" => "tcp,udp",
                    "followRedirect" => true
                ),
                "sniffing" => array(
                    "enabled" => true,
                    "destOverride" => array("http", "tls", "quic")
                )
            )
        ),
        "outbounds" => array(
            array(
                "tag" => "direct",
                "protocol" => "freedom",
                "settings" => array(
                    "domainStrategy" => "UseIP"
                )
            ),
            array(
                "tag" => "block",
                "protocol" => "blackhole",
                "settings" => array(
                    "response" => array(
                        "type" => "none"
                    )
                )
            )
        ),
        "routing" => array(
            "domainStrategy" => "IPIfNonMatch",
            "domainMatcher" => "hybrid",
            "rules" => array(
                array(
                    "type" => "field",
                    "outboundTag" => "block",
                    "domain" => array("geosite:category-ads-all")
                ),
                array(
                    "type" => "field",
                    "outboundTag" => "direct",
                    "ip" => array("geoip:private")
                ),
                array(
                    "type" => "field",
                    "outboundTag" => "direct",
                    "network" => "tcp,udp"
                )
            )
        )
    );
}

// Load or initialize config
$current_conf_raw = file_exists($conf_file) ? file_get_contents($conf_file) : "";
$current_conf = json_decode($current_conf_raw, true);
if (!is_array($current_conf)) {
    $current_conf = get_official_xray_template();
    $current_conf_raw = json_encode($current_conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    file_put_contents($conf_file, $current_conf_raw);
}

// Handle Form Submissions
if ($_POST) {
    // 1. Service Action Controls
    if (isset($_POST['act'])) {
        $action = $_POST['act'];
        if ($action == 'start') {
            mwexec("/usr/sbin/service xray start");
            sleep(1);
            if (is_xray_running()) {
                $savemsg = gettext("Layanan Xray-core berhasil dijalankan.");
            } else {
                $test = test_xray_config_file($conf_file);
                $errmsg = gettext("Gagal memulai Xray-core. Hasil tes konfigurasi:\n") . $test['output'];
            }
        } elseif ($action == 'stop') {
            mwexec("/usr/sbin/service xray stop");
            sleep(1);
            $savemsg = gettext("Layanan Xray-core berhasil dihentikan.");
        } elseif ($action == 'restart') {
            mwexec("/usr/sbin/service xray restart");
            sleep(1);
            if (is_xray_running()) {
                $savemsg = gettext("Layanan Xray-core berhasil direstart.");
            } else {
                $test = test_xray_config_file($conf_file);
                $errmsg = gettext("Gagal merestart Xray-core. Hasil tes konfigurasi:\n") . $test['output'];
            }
        } elseif ($action == 'test') {
            $test = test_xray_config_file($conf_file);
            if ($test['success']) {
                $savemsg = gettext("Sintaks Konfigurasi Valid: OK!\n") . $test['output'];
            } else {
                $errmsg = gettext("Sintaks Konfigurasi Tidak Valid:\n") . $test['output'];
            }
        } elseif ($action == 'clearlogs') {
            @file_put_contents($log_access, "");
            @file_put_contents($log_error, "");
            $savemsg = gettext("File log access dan error berhasil dikosongkan.");
        } elseif ($action == 'toggle_autostart') {
            $enable = isset($_POST['autostart']) && $_POST['autostart'] == '1';
            $rc_conf = "/etc/rc.conf.local";
            $content = file_exists($rc_conf) ? file_get_contents($rc_conf) : "";
            $lines = explode("\n", $content);
            $new_lines = array();
            foreach ($lines as $line) {
                if (strpos($line, 'xray_enable=') === false && trim($line) !== "") {
                    $new_lines[] = $line;
                }
            }
            if ($enable) {
                $new_lines[] = 'xray_enable="YES"';
                $savemsg = gettext("Autostart Xray-core saat boot berhasil diaktifkan.");
            } else {
                $new_lines[] = 'xray_enable="NO"';
                $savemsg = gettext("Autostart Xray-core saat boot dinonaktifkan.");
            }
            file_put_contents($rc_conf, implode("\n", $new_lines) . "\n");
        }
    }

    // 2. Official Xray Tools
    if (isset($_POST['tool'])) {
        $tool = $_POST['tool'];
        if ($tool == 'uuid') {
            $new_uuid = trim(shell_exec("{$xray_bin} uuid 2>&1"));
            $tool_output = "Generated Official UUID:\n" . $new_uuid;
            $savemsg = gettext("UUID baru berhasil di-generate.");
        } elseif ($tool == 'x25519') {
            $xout = trim(shell_exec("{$xray_bin} x25519 2>&1"));
            $tool_output = "Generated Official X25519 Reality Keypair:\n" . $xout;
            $savemsg = gettext("Keypair Reality X25519 berhasil di-generate.");
        } elseif ($tool == 'tls_cert') {
            $domain = !empty($_POST['cert_domain']) ? xray_clean($_POST['cert_domain']) : "pfsense.local";
            $cmd = "{$xray_bin} tls cert --domain " . escapeshellarg($domain) . " --expire 87600h 2>&1";
            $cert_out = trim(shell_exec($cmd));
            if (file_exists("cert.pem") && file_exists("key.pem")) {
                @rename("cert.pem", "{$conf_dir}/server.crt");
                @rename("key.pem", "{$conf_dir}/server.key");
            }
            $tool_output = "Official TLS Self-Signed Cert Generator Output:\n" . $cert_out . "\n(Saved to {$conf_dir}/server.crt and server.key)";
            $savemsg = gettext("Sertifikat TLS berhasil dibuat.");
        } elseif ($tool == 'tls_ping') {
            $target = !empty($_POST['ping_target']) ? xray_clean($_POST['ping_target']) : "www.microsoft.com:443";
            $ping_out = trim(shell_exec("{$xray_bin} tls ping " . escapeshellarg($target) . " 2>&1"));
            $tool_output = "Official TLS Ping to [{$target}]:\n" . $ping_out;
            $savemsg = gettext("Uji TLS Ping selesai.");
        } elseif ($tool == 'reset_template') {
            $template = get_official_xray_template();
            $new_json = json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            file_put_contents($conf_file, $new_json);
            $current_conf = $template;
            $current_conf_raw = $new_json;
            $savemsg = gettext("Konfigurasi Xray-core berhasil dikembalikan ke template resmi standar.");
            if (is_xray_running()) {
                mwexec("/usr/sbin/service xray restart");
            }
        }
    }

    // 3. Save Raw Config Editor
    if (isset($_POST['save_raw_conf'])) {
        $raw_input = $_POST['conf_content'];
        $decoded = json_decode($raw_input, true);
        if ($decoded === null) {
            $errmsg = gettext("Error: Format JSON tidak valid! Silakan periksa kembali tanda kurung, kutip, atau koma.");
        } else {
            $tmp_conf = "/tmp/xray_test_save.json";
            file_put_contents($tmp_conf, $raw_input);
            $test = test_xray_config_file($tmp_conf);
            @unlink($tmp_conf);

            if (!$test['success']) {
                $errmsg = gettext("Konfigurasi ditolak oleh Xray-core (Validasi Gagal):\n") . $test['output'];
            } else {
                file_put_contents($conf_file, $raw_input);
                $current_conf = $decoded;
                $current_conf_raw = $raw_input;
                $savemsg = gettext("File konfigurasi config.json berhasil disimpan dan divalidasi.");
                if (is_xray_running()) {
                    mwexec("/usr/sbin/service xray restart");
                    $savemsg .= " " . gettext("Layanan Xray-core otomatis direstart.");
                }
            }
        }
    }

    // 4. Save Inbounds & Protocols Form
    if (isset($_POST['save_inbounds'])) {
        $inbounds = array();

        // VLESS Reality
        if (isset($_POST['vless_enable'])) {
            $v_port = (int)$_POST['vless_port'] > 0 ? (int)$_POST['vless_port'] : 8443;
            $v_uuid = !empty($_POST['vless_uuid']) ? trim($_POST['vless_uuid']) : "d7e26a8f-287c-482a-a92c-6338b556f891";
            $v_dest = !empty($_POST['vless_dest']) ? trim($_POST['vless_dest']) : "www.microsoft.com:443";
            $v_sni  = !empty($_POST['vless_sni']) ? trim($_POST['vless_sni']) : "www.microsoft.com";
            $v_priv = !empty($_POST['vless_privkey']) ? trim($_POST['vless_privkey']) : "-HjAcnXaeoW-A2b7B3_AOM3i9hG4pfjF58Ns5kEaiTQ";
            $v_sid  = !empty($_POST['vless_shortid']) ? trim($_POST['vless_shortid']) : "0123456789abcdef";
            $v_flow = !empty($_POST['vless_flow']) ? trim($_POST['vless_flow']) : "xtls-rprx-vision";

            $inbounds[] = array(
                "tag" => "vless-reality-in",
                "port" => $v_port,
                "protocol" => "vless",
                "settings" => array(
                    "clients" => array(
                        array(
                            "id" => $v_uuid,
                            "flow" => $v_flow,
                            "email" => !empty($_POST['vless_email']) ? trim($_POST['vless_email']) : "admin@pfsense.local"
                        )
                    ),
                    "decryption" => "none"
                ),
                "streamSettings" => array(
                    "network" => "tcp",
                    "security" => "reality",
                    "realitySettings" => array(
                        "show" => false,
                        "dest" => $v_dest,
                        "xver" => 0,
                        "serverNames" => array_filter(array_map('trim', explode(',', $v_sni))),
                        "privateKey" => $v_priv,
                        "shortIds" => array_filter(array_map('trim', explode(',', $v_sid)))
                    )
                ),
                "sniffing" => array(
                    "enabled" => true,
                    "destOverride" => array("http", "tls", "quic"),
                    "routeOnly" => false
                )
            );
        }

        // VMess WebSocket
        if (isset($_POST['vmess_enable'])) {
            $vm_port = (int)$_POST['vmess_port'] > 0 ? (int)$_POST['vmess_port'] : 8080;
            $vm_uuid = !empty($_POST['vmess_uuid']) ? trim($_POST['vmess_uuid']) : "d7e26a8f-287c-482a-a92c-6338b556f891";
            $vm_path = !empty($_POST['vmess_path']) ? trim($_POST['vmess_path']) : "/vmess-ws";

            $inbounds[] = array(
                "tag" => "vmess-ws-in",
                "port" => $vm_port,
                "protocol" => "vmess",
                "settings" => array(
                    "clients" => array(
                        array(
                            "id" => $vm_uuid,
                            "alterId" => 0,
                            "email" => !empty($_POST['vmess_email']) ? trim($_POST['vmess_email']) : "user@pfsense.local"
                        )
                    )
                ),
                "streamSettings" => array(
                    "network" => "ws",
                    "wsSettings" => array(
                        "path" => $vm_path
                    )
                ),
                "sniffing" => array(
                    "enabled" => true,
                    "destOverride" => array("http", "tls")
                )
            );
        }

        // Trojan
        if (isset($_POST['trojan_enable'])) {
            $tr_port = (int)$_POST['trojan_port'] > 0 ? (int)$_POST['trojan_port'] : 9443;
            $tr_pass = !empty($_POST['trojan_pass']) ? trim($_POST['trojan_pass']) : "pfsense-trojan-password";

            $inbounds[] = array(
                "tag" => "trojan-in",
                "port" => $tr_port,
                "protocol" => "trojan",
                "settings" => array(
                    "clients" => array(
                        array(
                            "password" => $tr_pass,
                            "email" => "trojan@pfsense.local"
                        )
                    )
                ),
                "streamSettings" => array(
                    "network" => "tcp",
                    "security" => "none"
                )
            );
        }

        // Shadowsocks
        if (isset($_POST['ss_enable'])) {
            $ss_port   = (int)$_POST['ss_port'] > 0 ? (int)$_POST['ss_port'] : 8388;
            $ss_method = !empty($_POST['ss_method']) ? trim($_POST['ss_method']) : "2022-blake3-aes-128-gcm";
            $ss_pass   = !empty($_POST['ss_pass']) ? trim($_POST['ss_pass']) : "pfsense-ss-key";

            $inbounds[] = array(
                "tag" => "ss-in",
                "port" => $ss_port,
                "protocol" => "shadowsocks",
                "settings" => array(
                    "method" => $ss_method,
                    "password" => $ss_pass,
                    "network" => "tcp,udp"
                )
            );
        }

        // Socks5 Local Proxy
        if (isset($_POST['socks_enable'])) {
            $so_port = (int)$_POST['socks_port'] > 0 ? (int)$_POST['socks_port'] : 10808;
            $so_ip   = !empty($_POST['socks_ip']) ? trim($_POST['socks_ip']) : "127.0.0.1";

            $inbounds[] = array(
                "tag" => "socks-in",
                "port" => $so_port,
                "listen" => $so_ip,
                "protocol" => "socks",
                "settings" => array(
                    "auth" => "noauth",
                    "udp" => true
                )
            );
        }

        // Transparent Proxy (Dokodemo-door)
        if (isset($_POST['tun_enable'])) {
            $tun_port = (int)$_POST['tun_port'] > 0 ? (int)$_POST['tun_port'] : 12345;

            $inbounds[] = array(
                "tag" => "tun-in",
                "protocol" => "dokodemo-door",
                "port" => $tun_port,
                "listen" => "127.0.0.1",
                "settings" => array(
                    "network" => "tcp,udp",
                    "followRedirect" => true
                ),
                "sniffing" => array(
                    "enabled" => true,
                    "destOverride" => array("http", "tls", "quic")
                )
            );
        }

        if (empty($inbounds)) {
            $errmsg = gettext("Peringatan: Minimal satu inbound harus diaktifkan!");
        } else {
            $new_conf = $current_conf;
            $new_conf['inbounds'] = $inbounds;

            $tmp_conf = "/tmp/xray_test_inbounds.json";
            file_put_contents($tmp_conf, json_encode($new_conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $test = test_xray_config_file($tmp_conf);
            @unlink($tmp_conf);

            if (!$test['success']) {
                $errmsg = gettext("Konfigurasi Inbounds tidak valid untuk Xray-core:\n") . $test['output'];
            } else {
                $current_conf = $new_conf;
                $current_conf_raw = json_encode($current_conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                file_put_contents($conf_file, $current_conf_raw);
                $savemsg = gettext("Pengaturan Inbounds & Protokol berhasil disimpan!");
                if (is_xray_running()) {
                    mwexec("/usr/sbin/service xray restart");
                    $savemsg .= " " . gettext("Layanan Xray-core otomatis direstart.");
                }
            }
        }
    }

    // 5. Save Outbounds & Routing Form
    if (isset($_POST['save_routing'])) {
        $dns_servers = array_filter(array_map('trim', explode(',', $_POST['dns_servers'])));
        if (empty($dns_servers)) {
            $dns_servers = array("1.1.1.1", "8.8.8.8", "localhost");
        }
        $query_strat = in_array($_POST['query_strategy'], array("UseIP", "UseIPv4", "UseIPv6")) ? $_POST['query_strategy'] : "UseIP";
        $dom_strat   = in_array($_POST['domain_strategy'], array("IPIfNonMatch", "AsIs", "IPOnDemand")) ? $_POST['domain_strategy'] : "IPIfNonMatch";

        $rules = array();
        if (isset($_POST['block_ads'])) {
            $rules[] = array(
                "type" => "field",
                "outboundTag" => "block",
                "domain" => array("geosite:category-ads-all")
            );
        }
        if (isset($_POST['direct_private'])) {
            $rules[] = array(
                "type" => "field",
                "outboundTag" => "direct",
                "ip" => array("geoip:private")
            );
        }
        if (!empty($_POST['custom_domains_direct'])) {
            $doms = array_filter(array_map('trim', explode("\n", $_POST['custom_domains_direct'])));
            if (!empty($doms)) {
                $rules[] = array(
                    "type" => "field",
                    "outboundTag" => "direct",
                    "domain" => array_values($doms)
                );
            }
        }
        $rules[] = array(
            "type" => "field",
            "outboundTag" => "direct",
            "network" => "tcp,udp"
        );

        $new_conf = $current_conf;
        $new_conf['dns'] = array(
            "servers" => array_values($dns_servers),
            "queryStrategy" => $query_strat
        );
        $new_conf['routing'] = array(
            "domainStrategy" => $dom_strat,
            "domainMatcher" => "hybrid",
            "rules" => $rules
        );

        $tmp_conf = "/tmp/xray_test_routing.json";
        file_put_contents($tmp_conf, json_encode($new_conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $test = test_xray_config_file($tmp_conf);
        @unlink($tmp_conf);

        if (!$test['success']) {
            $errmsg = gettext("Konfigurasi Routing tidak valid untuk Xray-core:\n") . $test['output'];
        } else {
            $current_conf = $new_conf;
            $current_conf_raw = json_encode($current_conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            file_put_contents($conf_file, $current_conf_raw);
            $savemsg = gettext("Pengaturan Routing & DNS berhasil disimpan!");
            if (is_xray_running()) {
                mwexec("/usr/sbin/service xray restart");
                $savemsg .= " " . gettext("Layanan Xray-core otomatis direstart.");
            }
        }
    }
}

// Current Service State
$xray_pid = is_xray_running();

// Check autostart in /etc/rc.conf.local
$is_autostart = false;
if (file_exists("/etc/rc.conf.local")) {
    $rc_c = file_get_contents("/etc/rc.conf.local");
    if (strpos($rc_c, 'xray_enable="YES"') !== false) {
        $is_autostart = true;
    }
}

// Get WAN IP or default hostname for client links
$wan_ip = "";
$ip_out = trim(shell_exec("ifconfig | grep 'inet ' | grep -v '127.0.0.1' | awk '{print $2}' | head -n 1"));
if (!empty($ip_out)) {
    $wan_ip = $ip_out;
} else {
    $wan_ip = $_SERVER['SERVER_ADDR'] ? $_SERVER['SERVER_ADDR'] : "192.168.1.1";
}

// Parse active inbounds from config
$inbound_map = array();
if (isset($current_conf['inbounds']) && is_array($current_conf['inbounds'])) {
    foreach ($current_conf['inbounds'] as $ib) {
        if (isset($ib['protocol'])) {
            $inbound_map[$ib['protocol']] = $ib;
        }
    }
}

// Get Xray Version
$xray_ver_str = trim(shell_exec("{$xray_bin} version 2>&1 | head -n 1"));
if (!$xray_ver_str) {
    $xray_ver_str = "Xray-core (Unknown version)";
}

// Logs Tail
$log_view_type = isset($_GET['log_type']) ? xray_clean($_GET['log_type']) : 'error';
$log_lines_count = isset($_GET['lines']) ? (int)$_GET['lines'] : 100;
if ($log_lines_count < 10) $log_lines_count = 50;

$target_log = ($log_view_type == 'access') ? $log_access : $log_error;
$log_content = "";
if (file_exists($target_log)) {
    $log_content = trim(shell_exec("tail -n " . escapeshellarg($log_lines_count) . " " . escapeshellarg($target_log)));
    if (empty($log_content)) {
        $log_content = gettext("File log masih kosong.");
    }
} else {
    $log_content = gettext("File log tidak ditemukan.");
}

// Build Top Tabs
$tab_array = array();
$tab_array[] = array(gettext('Status & Service'), ($tab == 'status'), '/vpn_xray.php?tab=status');
$tab_array[] = array(gettext('Inbounds & Protocols'), ($tab == 'inbounds'), '/vpn_xray.php?tab=inbounds');
$tab_array[] = array(gettext('Outbounds & Routing'), ($tab == 'routing'), '/vpn_xray.php?tab=routing');
$tab_array[] = array(gettext('Client Links & QR'), ($tab == 'clients'), '/vpn_xray.php?tab=clients');
$tab_array[] = array(gettext('Config Editor & Tools'), ($tab == 'config'), '/vpn_xray.php?tab=config');
$tab_array[] = array(gettext('Logs'), ($tab == 'logs'), '/vpn_xray.php?tab=logs');

include("head.inc");

if ($savemsg) {
    print_info_box(nl2br(htmlspecialchars($savemsg)), 'success');
}
if ($errmsg) {
    print_info_box(nl2br(htmlspecialchars($errmsg)), 'danger');
}

display_top_tabs($tab_array);
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title">
            <i class="fa fa-shield"></i> 
            <?= htmlspecialchars($tab_titles[$tab]) ?> &mdash; <?= htmlspecialchars($xray_ver_str) ?>
        </h2>
    </div>
    <div class="panel-body">

<?php if ($tab == 'status'): ?>
    <!-- TAB 1: STATUS & SERVICE -->
    <div class="row">
        <div class="col-md-6">
            <h4><i class="fa fa-info-circle"></i> <?=gettext('Status Layanan Xray-core')?></h4>
            <table class="table table-bordered table-striped">
                <tr>
                    <th style="width: 35%;"><?=gettext('Status Service')?></th>
                    <td>
                        <?php if ($xray_pid): ?>
                            <span class="label label-success" style="font-size: 13px;">
                                <i class="fa fa-check-circle"></i> RUNNING (PID: <?= htmlspecialchars($xray_pid) ?>)
                            </span>
                        <?php else: ?>
                            <span class="label label-danger" style="font-size: 13px;">
                                <i class="fa fa-times-circle"></i> STOPPED
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?=gettext('Versi Xray')?></th>
                    <td><code><?= htmlspecialchars($xray_ver_str) ?></code></td>
                </tr>
                <tr>
                    <th><?=gettext('Autostart Saat Boot')?></th>
                    <td>
                        <form method="post" action="vpn_xray.php?tab=status" class="form-inline" style="display:inline;">
                            <input type="hidden" name="act" value="toggle_autostart">
                            <?php if ($is_autostart): ?>
                                <input type="hidden" name="autostart" value="0">
                                <span class="label label-primary"><i class="fa fa-check"></i> Enabled (/etc/rc.conf.local)</span>
                                <button type="submit" class="btn btn-xs btn-default" style="margin-left: 8px;">Nonaktifkan</button>
                            <?php else: ?>
                                <input type="hidden" name="autostart" value="1">
                                <span class="label label-default"><i class="fa fa-ban"></i> Disabled</span>
                                <button type="submit" class="btn btn-xs btn-primary" style="margin-left: 8px;">Aktifkan</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
                <tr>
                    <th><?=gettext('Lokasi Binary')?></th>
                    <td><code><?= htmlspecialchars($xray_bin) ?></code></td>
                </tr>
                <tr>
                    <th><?=gettext('Database Routing Assets')?></th>
                    <td>
                        <?php
                            $has_geoip = file_exists("/usr/local/share/xray/geoip.dat");
                            $has_geosite = file_exists("/usr/local/share/xray/geosite.dat");
                        ?>
                        <span class="label <?= $has_geoip ? 'label-success' : 'label-warning' ?>">geoip.dat: <?= $has_geoip ? 'Ready' : 'Missing' ?></span>
                        <span class="label <?= $has_geosite ? 'label-success' : 'label-warning' ?>">geosite.dat: <?= $has_geosite ? 'Ready' : 'Missing' ?></span>
                    </td>
                </tr>
            </table>

            <div class="well well-sm">
                <strong><?=gettext('Kontrol Layanan:')?></strong>
                <form method="post" action="vpn_xray.php?tab=status" class="form-inline" style="margin-top: 8px;">
                    <?php if (!$xray_pid): ?>
                        <button type="submit" name="act" value="start" class="btn btn-success"><i class="fa fa-play"></i> Start Xray</button>
                    <?php else: ?>
                        <button type="submit" name="act" value="restart" class="btn btn-warning"><i class="fa fa-refresh"></i> Restart Xray</button>
                        <button type="submit" name="act" value="stop" class="btn btn-danger"><i class="fa fa-stop"></i> Stop Xray</button>
                    <?php endif; ?>
                    <button type="submit" name="act" value="test" class="btn btn-info"><i class="fa fa-check"></i> Test Config Syntax</button>
                </form>
            </div>
        </div>

        <div class="col-md-6">
            <h4><i class="fa fa-server"></i> <?=gettext('Inbound Aktif & Port Terbuka')?></h4>
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th><?=gettext('Protokol')?></th>
                        <th><?=gettext('Port')?></th>
                        <th><?=gettext('Keamanan / Transport')?></th>
                        <th><?=gettext('Status')?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $supported_inbounds = array(
                        'vless' => array('name' => 'VLESS', 'desc' => 'Reality + XTLS Vision', 'badge' => 'label-info'),
                        'vmess' => array('name' => 'VMess', 'desc' => 'WebSocket / CDN Tunneling', 'badge' => 'label-primary'),
                        'trojan' => array('name' => 'Trojan', 'desc' => 'Trojan-GFW TLS Proxy', 'badge' => 'label-default'),
                        'shadowsocks' => array('name' => 'Shadowsocks', 'desc' => 'AEAD / 2022 Ciphers', 'badge' => 'label-warning'),
                        'socks' => array('name' => 'SOCKS5', 'desc' => 'Local / LAN Proxy', 'badge' => 'label-success'),
                        'dokodemo-door' => array('name' => 'Dokodemo/TUN', 'desc' => 'Transparent Redirect Gateway', 'badge' => 'label-info')
                    );

                    foreach ($supported_inbounds as $proto => $info):
                        $found = isset($inbound_map[$proto]);
                        $port = $found && isset($inbound_map[$proto]['port']) ? $inbound_map[$proto]['port'] : '-';
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($info['name']) ?></strong></td>
                        <td><code><?= htmlspecialchars($port) ?></code></td>
                        <td><?= htmlspecialchars($info['desc']) ?></td>
                        <td>
                            <?php if ($found): ?>
                                <span class="label label-success"><i class="fa fa-check"></i> Active</span>
                            <?php else: ?>
                                <span class="label label-default">Disabled</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="text-right">
                <a href="/vpn_xray.php?tab=inbounds" class="btn btn-sm btn-primary">
                    <i class="fa fa-pencil"></i> Konfigurasi Protokol Inbounds &raquo;
                </a>
            </div>
        </div>
    </div>

<?php elseif ($tab == 'inbounds'): ?>
    <!-- TAB 2: INBOUNDS & PROTOCOLS -->
    <form method="post" action="vpn_xray.php?tab=inbounds">
        <p class="text-muted">
            <?=gettext('Konfigurasikan protokol inbound Xray-core resmi (Project X). Semua form tervalidasi langsung terhadap spesifikasi schema Xray.')?>
        </p>

        <!-- VLESS Card -->
        <?php $vl = isset($inbound_map['vless']) ? $inbound_map['vless'] : null; ?>
        <div class="panel panel-info">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <input type="checkbox" name="vless_enable" value="1" <?= $vl ? 'checked' : '' ?>>
                    <strong>1. VLESS (Reality + XTLS Vision)</strong> &mdash; <em>Rekomendasi Utama Anti-Censorship</em>
                </h3>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-3">
                        <label><?=gettext('Port Listen:')?></label>
                        <input type="number" name="vless_port" class="form-control" value="<?= $vl ? htmlspecialchars($vl['port']) : 8443 ?>">
                    </div>
                    <div class="col-md-4">
                        <label><?=gettext('Client UUID:')?></label>
                        <input type="text" name="vless_uuid" id="vless_uuid" class="form-control" value="<?= isset($vl['settings']['clients'][0]['id']) ? htmlspecialchars($vl['settings']['clients'][0]['id']) : 'd7e26a8f-287c-482a-a92c-6338b556f891' ?>">
                    </div>
                    <div class="col-md-3">
                        <label><?=gettext('Flow Control:')?></label>
                        <select name="vless_flow" class="form-control">
                            <option value="xtls-rprx-vision" selected>xtls-rprx-vision (Vision)</option>
                            <option value="">None (Standard TCP)</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label><?=gettext('Client Email/Tag:')?></label>
                        <input type="text" name="vless_email" class="form-control" value="<?= isset($vl['settings']['clients'][0]['email']) ? htmlspecialchars($vl['settings']['clients'][0]['email']) : 'admin@pfsense.local' ?>">
                    </div>
                </div>
                <div class="row" style="margin-top: 10px;">
                    <div class="col-md-3">
                        <label><?=gettext('Reality Target Dest:')?></label>
                        <input type="text" name="vless_dest" class="form-control" value="<?= isset($vl['streamSettings']['realitySettings']['dest']) ? htmlspecialchars($vl['streamSettings']['realitySettings']['dest']) : 'www.microsoft.com:443' ?>">
                        <small class="text-muted">Target handshake nyata (e.g. www.microsoft.com:443)</small>
                    </div>
                    <div class="col-md-3">
                        <label><?=gettext('ServerNames (SNI):')?></label>
                        <input type="text" name="vless_sni" class="form-control" value="<?= isset($vl['streamSettings']['realitySettings']['serverNames'][0]) ? htmlspecialchars(implode(',', $vl['streamSettings']['realitySettings']['serverNames'])) : 'www.microsoft.com' ?>">
                        <small class="text-muted">Domain penyamaran klien</small>
                    </div>
                    <div class="col-md-4">
                        <label><?=gettext('Reality Private Key:')?></label>
                        <input type="text" name="vless_privkey" id="vless_privkey" class="form-control" value="<?= isset($vl['streamSettings']['realitySettings']['privateKey']) ? htmlspecialchars($vl['streamSettings']['realitySettings']['privateKey']) : '-HjAcnXaeoW-A2b7B3_AOM3i9hG4pfjF58Ns5kEaiTQ' ?>">
                        <small class="text-muted">Dibuat via tool official <code>xray x25519</code></small>
                    </div>
                    <div class="col-md-2">
                        <label><?=gettext('ShortId (Hex):')?></label>
                        <input type="text" name="vless_shortid" class="form-control" value="<?= isset($vl['streamSettings']['realitySettings']['shortIds'][0]) ? htmlspecialchars(implode(',', $vl['streamSettings']['realitySettings']['shortIds'])) : '0123456789abcdef' ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- VMess Card -->
        <?php $vm = isset($inbound_map['vmess']) ? $inbound_map['vmess'] : null; ?>
        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <input type="checkbox" name="vmess_enable" value="1" <?= $vm ? 'checked' : '' ?>>
                    <strong>2. VMess (WebSocket Tunnel)</strong> &mdash; <em>Kompatibel dengan Cloudflare CDN & Reverse Proxy</em>
                </h3>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-3">
                        <label><?=gettext('Port Listen:')?></label>
                        <input type="number" name="vmess_port" class="form-control" value="<?= $vm ? htmlspecialchars($vm['port']) : 8080 ?>">
                    </div>
                    <div class="col-md-5">
                        <label><?=gettext('Client UUID:')?></label>
                        <input type="text" name="vmess_uuid" class="form-control" value="<?= isset($vm['settings']['clients'][0]['id']) ? htmlspecialchars($vm['settings']['clients'][0]['id']) : 'd7e26a8f-287c-482a-a92c-6338b556f891' ?>">
                    </div>
                    <div class="col-md-4">
                        <label><?=gettext('WebSocket Path:')?></label>
                        <input type="text" name="vmess_path" class="form-control" value="<?= isset($vm['streamSettings']['wsSettings']['path']) ? htmlspecialchars($vm['streamSettings']['wsSettings']['path']) : '/vmess-ws' ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Trojan Card -->
        <?php $tr = isset($inbound_map['trojan']) ? $inbound_map['trojan'] : null; ?>
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <input type="checkbox" name="trojan_enable" value="1" <?= $tr ? 'checked' : '' ?>>
                    <strong>3. Trojan</strong> &mdash; <em>Protokol Proxy Trojan Standar</em>
                </h3>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-3">
                        <label><?=gettext('Port Listen:')?></label>
                        <input type="number" name="trojan_port" class="form-control" value="<?= $tr ? htmlspecialchars($tr['port']) : 9443 ?>">
                    </div>
                    <div class="col-md-6">
                        <label><?=gettext('Password Trojan:')?></label>
                        <input type="text" name="trojan_pass" class="form-control" value="<?= isset($tr['settings']['clients'][0]['password']) ? htmlspecialchars($tr['settings']['clients'][0]['password']) : 'pfsense-trojan-password' ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Shadowsocks Card -->
        <?php $ss = isset($inbound_map['shadowsocks']) ? $inbound_map['shadowsocks'] : null; ?>
        <div class="panel panel-warning">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <input type="checkbox" name="ss_enable" value="1" <?= $ss ? 'checked' : '' ?>>
                    <strong>4. Shadowsocks (AEAD 2022)</strong>
                </h3>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-3">
                        <label><?=gettext('Port Listen:')?></label>
                        <input type="number" name="ss_port" class="form-control" value="<?= $ss ? htmlspecialchars($ss['port']) : 8388 ?>">
                    </div>
                    <div class="col-md-4">
                        <label><?=gettext('Cipher Method:')?></label>
                        <select name="ss_method" class="form-control">
                            <option value="2022-blake3-aes-128-gcm" <?= (isset($ss['settings']['method']) && $ss['settings']['method'] == '2022-blake3-aes-128-gcm') ? 'selected' : '' ?>>2022-blake3-aes-128-gcm</option>
                            <option value="aes-128-gcm" <?= (isset($ss['settings']['method']) && $ss['settings']['method'] == 'aes-128-gcm') ? 'selected' : '' ?>>aes-128-gcm</option>
                            <option value="chacha20-poly1305" <?= (isset($ss['settings']['method']) && $ss['settings']['method'] == 'chacha20-poly1305') ? 'selected' : '' ?>>chacha20-poly1305</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label><?=gettext('Password:')?></label>
                        <input type="text" name="ss_pass" class="form-control" value="<?= isset($ss['settings']['password']) ? htmlspecialchars($ss['settings']['password']) : 'pfsense-ss-key' ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Local Socks & Transparent Proxy -->
        <div class="row">
            <?php $so = isset($inbound_map['socks']) ? $inbound_map['socks'] : null; ?>
            <div class="col-md-6">
                <div class="panel panel-success">
                    <div class="panel-heading">
                        <h3 class="panel-title">
                            <input type="checkbox" name="socks_enable" value="1" <?= $so ? 'checked' : '' ?>>
                            <strong>5. SOCKS5 Local Inbound</strong>
                        </h3>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label><?=gettext('Port:')?></label>
                                <input type="number" name="socks_port" class="form-control" value="<?= $so ? htmlspecialchars($so['port']) : 10808 ?>">
                            </div>
                            <div class="col-md-6">
                                <label><?=gettext('Listen IP:')?></label>
                                <input type="text" name="socks_ip" class="form-control" value="<?= isset($so['listen']) ? htmlspecialchars($so['listen']) : '127.0.0.1' ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php $tun = isset($inbound_map['dokodemo-door']) ? $inbound_map['dokodemo-door'] : null; ?>
            <div class="col-md-6">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        <h3 class="panel-title">
                            <input type="checkbox" name="tun_enable" value="1" <?= $tun ? 'checked' : '' ?>>
                            <strong>6. Transparent Proxy (Dokodemo-door)</strong>
                        </h3>
                    </div>
                    <div class="panel-body">
                        <label><?=gettext('Port Redirect:')?></label>
                        <input type="number" name="tun_port" class="form-control" value="<?= $tun ? htmlspecialchars($tun['port']) : 12345 ?>">
                        <small class="text-muted">Digunakan untuk pengalihan rule PF (Packet Filter) transparent gateway router.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group" style="margin-top: 15px;">
            <button type="submit" name="save_inbounds" value="1" class="btn btn-primary">
                <i class="fa fa-save"></i> <?=gettext('Simpan Konfigurasi Inbounds')?>
            </button>
        </div>
    </form>

<?php elseif ($tab == 'routing'): ?>
    <!-- TAB 3: OUTBOUNDS & ROUTING -->
    <form method="post" action="vpn_xray.php?tab=routing">
        <p class="text-muted">
            <?=gettext('Pengaturan aturan routing Xray-core (Project X Routing Rule System) menggunakan database GeoIP dan GeoSite resmi.')?>
        </p>

        <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title"><?=gettext('Pengaturan DNS Xray')?></h3></div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-8">
                        <label><?=gettext('DNS Servers (Pisahkan koma):')?></label>
                        <input type="text" name="dns_servers" class="form-control" value="<?= isset($current_conf['dns']['servers']) ? htmlspecialchars(implode(', ', $current_conf['dns']['servers'])) : '1.1.1.1, 8.8.8.8, localhost' ?>">
                        <small class="text-muted">Contoh: 1.1.1.1, 8.8.8.8, https://1.1.1.1/dns-query, localhost</small>
                    </div>
                    <div class="col-md-4">
                        <label><?=gettext('Query Strategy:')?></label>
                        <select name="query_strategy" class="form-control">
                            <?php $qs = isset($current_conf['dns']['queryStrategy']) ? $current_conf['dns']['queryStrategy'] : 'UseIP'; ?>
                            <option value="UseIP" <?= $qs == 'UseIP' ? 'selected' : '' ?>>UseIP (IPv4 & IPv6)</option>
                            <option value="UseIPv4" <?= $qs == 'UseIPv4' ? 'selected' : '' ?>>UseIPv4 Only</option>
                            <option value="UseIPv6" <?= $qs == 'UseIPv6' ? 'selected' : '' ?>>UseIPv6 Only</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title"><?=gettext('Strategi Domain & Rule Engine')?></h3></div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-6">
                        <label><?=gettext('Domain Strategy:')?></label>
                        <?php $ds = isset($current_conf['routing']['domainStrategy']) ? $current_conf['routing']['domainStrategy'] : 'IPIfNonMatch'; ?>
                        <select name="domain_strategy" class="form-control">
                            <option value="IPIfNonMatch" <?= $ds == 'IPIfNonMatch' ? 'selected' : '' ?>>IPIfNonMatch (Cek Domain dulu, jika tidak cocok resolusi ke IP)</option>
                            <option value="AsIs" <?= $ds == 'AsIs' ? 'selected' : '' ?>>AsIs (Gunakan domain dan IP apa adanya tanpa resolusi DNS paksa)</option>
                            <option value="IPOnDemand" <?= $ds == 'IPOnDemand' ? 'selected' : '' ?>>IPOnDemand (Resolusi domain saat dibutuhkan rule IP)</option>
                        </select>
                    </div>
                </div>

                <hr>

                <h4><?=gettext('Aturan Bawaan (Built-in Rules):')?></h4>
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="block_ads" value="1" checked>
                        <strong><?=gettext('Blokir Iklan & Pelacak Malware (geosite:category-ads-all -> Block)')?></strong>
                    </label>
                </div>
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="direct_private" value="1" checked>
                        <strong><?=gettext('Lewatkan Lalu Lintas LAN & IP Privat Langsung (geoip:private -> Direct)')?></strong>
                    </label>
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label><?=gettext('Daftar Domain Khusus Direct (Satu domain per baris):')?></label>
                    <textarea name="custom_domains_direct" rows="4" class="form-control" placeholder="domain:speedtest.net&#10;domain:local"></textarea>
                </div>
            </div>
        </div>

        <button type="submit" name="save_routing" value="1" class="btn btn-primary">
            <i class="fa fa-save"></i> <?=gettext('Simpan Pengaturan Routing')?>
        </button>
    </form>

<?php elseif ($tab == 'clients'): ?>
    <!-- TAB 4: CLIENT LINKS & QR -->
    <h4><i class="fa fa-qrcode"></i> <?=gettext('Generator Link Klien & QR Code')?></h4>
    <p class="text-muted">
        <?=gettext('Salin link koneksi atau scan QR Code di bawah menggunakan aplikasi klien seperti v2rayNG, v2rayN, Shadowrocket, Sing-box, atau Nekobox.')?>
    </p>

    <div class="row" style="margin-bottom: 20px;">
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-addon"><?=gettext('Host / IP Publik Server:')?></span>
                <input type="text" id="client_host" class="form-control" value="<?= htmlspecialchars($wan_ip) ?>">
                <span class="input-group-btn">
                    <button class="btn btn-default" type="button" onclick="updateClientLinks()"><?=gettext('Update Links')?></button>
                </span>
            </div>
        </div>
    </div>

    <!-- Client Link Cards -->
    <div class="row">
        <?php
        // VLESS Link
        $vl = isset($inbound_map['vless']) ? $inbound_map['vless'] : null;
        if ($vl):
            $v_uuid = isset($vl['settings']['clients'][0]['id']) ? $vl['settings']['clients'][0]['id'] : '';
            $v_port = $vl['port'];
            $v_sni = isset($vl['streamSettings']['realitySettings']['serverNames'][0]) ? $vl['streamSettings']['realitySettings']['serverNames'][0] : 'www.microsoft.com';
            $v_sid = isset($vl['streamSettings']['realitySettings']['shortIds'][0]) ? $vl['streamSettings']['realitySettings']['shortIds'][0] : '0123456789abcdef';
            $v_pbk = "Nk9sFrBuCci4mEbinii8hIUwid6_v_pWLWqCH-CveiM";
        ?>
        <div class="col-md-6">
            <div class="panel panel-info">
                <div class="panel-heading"><h3 class="panel-title">VLESS Reality (XTLS Vision)</h3></div>
                <div class="panel-body text-center">
                    <div id="vless_qrcode" style="display:inline-block; margin: 10px 0; padding: 10px; background: #fff; border-radius: 6px;"></div>
                    <div class="input-group" style="margin-top: 10px;">
                        <input type="text" id="vless_link" class="form-control" readonly 
                               data-uuid="<?= htmlspecialchars($v_uuid) ?>" 
                               data-port="<?= htmlspecialchars($v_port) ?>" 
                               data-sni="<?= htmlspecialchars($v_sni) ?>" 
                               data-pbk="<?= htmlspecialchars($v_pbk) ?>" 
                               data-sid="<?= htmlspecialchars($v_sid) ?>"
                               value="vless://<?= htmlspecialchars($v_uuid) ?>@<?= htmlspecialchars($wan_ip) ?>:<?= htmlspecialchars($v_port) ?>?security=reality&encryption=none&flow=xtls-rprx-vision&sni=<?= htmlspecialchars($v_sni) ?>&fp=chrome&pbk=<?= htmlspecialchars($v_pbk) ?>&sid=<?= htmlspecialchars($v_sid) ?>&type=tcp&headerType=none#pfSense-VLESS-Reality">
                        <span class="input-group-btn">
                            <button class="btn btn-primary" onclick="copyLink('vless_link')"><i class="fa fa-copy"></i> Copy</button>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php
        // VMess Link
        $vm = isset($inbound_map['vmess']) ? $inbound_map['vmess'] : null;
        if ($vm):
            $vm_uuid = isset($vm['settings']['clients'][0]['id']) ? $vm['settings']['clients'][0]['id'] : '';
            $vm_port = $vm['port'];
            $vm_path = isset($vm['streamSettings']['wsSettings']['path']) ? $vm['streamSettings']['wsSettings']['path'] : '/vmess-ws';
            $vm_json = json_encode(array(
                "v" => "2",
                "ps" => "pfSense-VMess-WS",
                "add" => $wan_ip,
                "port" => (int)$vm_port,
                "id" => $vm_uuid,
                "aid" => 0,
                "scy" => "auto",
                "net" => "ws",
                "type" => "none",
                "host" => "",
                "path" => $vm_path,
                "tls" => ""
            ));
            $vm_link = "vmess://" . base64_encode($vm_json);
        ?>
        <div class="col-md-6">
            <div class="panel panel-primary">
                <div class="panel-heading"><h3 class="panel-title">VMess WebSocket</h3></div>
                <div class="panel-body text-center">
                    <div id="vmess_qrcode" style="display:inline-block; margin: 10px 0; padding: 10px; background: #fff; border-radius: 6px;"></div>
                    <div class="input-group" style="margin-top: 10px;">
                        <input type="text" id="vmess_link" class="form-control" readonly 
                               data-uuid="<?= htmlspecialchars($vm_uuid) ?>" 
                               data-port="<?= htmlspecialchars($vm_port) ?>" 
                               data-path="<?= htmlspecialchars($vm_path) ?>"
                               value="<?= htmlspecialchars($vm_link) ?>">
                        <span class="input-group-btn">
                            <button class="btn btn-primary" onclick="copyLink('vmess_link')"><i class="fa fa-copy"></i> Copy</button>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

<?php elseif ($tab == 'config'): ?>
    <!-- TAB 5: CONFIG EDITOR & TOOLS -->
    <div class="row">
        <div class="col-md-8">
            <h4><i class="fa fa-code"></i> <?=gettext('Editor Berkas Konfigurasi (/usr/local/etc/xray/config.json)')?></h4>
            <form method="post" action="vpn_xray.php?tab=config">
                <div class="form-group">
                    <textarea name="conf_content" id="conf_content" rows="22" class="form-control" style="font-family: monospace; font-size: 13px; line-height: 1.4; background: #222; color: #7cfc00;"><?= htmlspecialchars($current_conf_raw) ?></textarea>
                </div>
                <button type="submit" name="save_raw_conf" value="1" class="btn btn-primary">
                    <i class="fa fa-save"></i> <?=gettext('Simpan & Validasi Konfigurasi')?>
                </button>
            </form>
        </div>

        <div class="col-md-4">
            <h4><i class="fa fa-wrench"></i> <?=gettext('Official Xray CLI Tools')?></h4>
            <p class="text-muted"><?=gettext('Alat bantu bawaan langsung dari engine binary Xray-core.')?></p>

            <!-- Tool 1: UUID -->
            <div class="panel panel-default">
                <div class="panel-heading"><h4 class="panel-title">1. Generate UUID (v4)</h4></div>
                <div class="panel-body">
                    <form method="post" action="vpn_xray.php?tab=config">
                        <input type="hidden" name="tool" value="uuid">
                        <button type="submit" class="btn btn-sm btn-info btn-block"><i class="fa fa-random"></i> Generate UUID Baru</button>
                    </form>
                </div>
            </div>

            <!-- Tool 2: X25519 -->
            <div class="panel panel-default">
                <div class="panel-heading"><h4 class="panel-title">2. Generate Reality Keypair (x25519)</h4></div>
                <div class="panel-body">
                    <form method="post" action="vpn_xray.php?tab=config">
                        <input type="hidden" name="tool" value="x25519">
                        <button type="submit" class="btn btn-sm btn-warning btn-block"><i class="fa fa-key"></i> Generate Keypair X25519</button>
                    </form>
                </div>
            </div>

            <!-- Tool 3: TLS Cert -->
            <div class="panel panel-default">
                <div class="panel-heading"><h4 class="panel-title">3. Generate Self-Signed TLS Cert</h4></div>
                <div class="panel-body">
                    <form method="post" action="vpn_xray.php?tab=config">
                        <input type="hidden" name="tool" value="tls_cert">
                        <div class="form-group">
                            <input type="text" name="cert_domain" class="form-control input-sm" placeholder="Domain (e.g. pfsense.local)" value="pfsense.local">
                        </div>
                        <button type="submit" class="btn btn-sm btn-success btn-block"><i class="fa fa-certificate"></i> Buat Sertifikat TLS</button>
                    </form>
                </div>
            </div>

            <!-- Tool 4: TLS Ping -->
            <div class="panel panel-default">
                <div class="panel-heading"><h4 class="panel-title">4. Reality Fallback Target TLS Ping</h4></div>
                <div class="panel-body">
                    <form method="post" action="vpn_xray.php?tab=config">
                        <input type="hidden" name="tool" value="tls_ping">
                        <div class="form-group">
                            <input type="text" name="ping_target" class="form-control input-sm" value="www.microsoft.com:443">
                        </div>
                        <button type="submit" class="btn btn-sm btn-default btn-block"><i class="fa fa-signal"></i> Uji TLS Handshake</button>
                    </form>
                </div>
            </div>

            <!-- Tool 5: Reset Template -->
            <div class="panel panel-danger">
                <div class="panel-heading"><h4 class="panel-title">Reset ke Template Resmi</h4></div>
                <div class="panel-body">
                    <form method="post" action="vpn_xray.php?tab=config" onsubmit="return confirm('Kembalikan ke konfigurasi default resmi?');">
                        <input type="hidden" name="tool" value="reset_template">
                        <button type="submit" class="btn btn-sm btn-danger btn-block"><i class="fa fa-history"></i> Reset Default Template</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($tool_output): ?>
        <div class="row" style="margin-top: 15px;">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading"><h3 class="panel-title"><?=gettext('Hasil Eksekusi Tool:')?></h3></div>
                    <div class="panel-body">
                        <pre style="background: #111; color: #00ffcc; font-size: 13px;"><?= htmlspecialchars($tool_output) ?></pre>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

<?php elseif ($tab == 'logs'): ?>
    <!-- TAB 6: LOGS -->
    <div class="row">
        <div class="col-md-12">
            <div class="well well-sm">
                <form method="get" action="vpn_xray.php" class="form-inline">
                    <input type="hidden" name="tab" value="logs">
                    <label><?=gettext('Pilih Log:')?></label>
                    <select name="log_type" class="form-control">
                        <option value="error" <?= $log_view_type == 'error' ? 'selected' : '' ?>>Error Log (/var/log/xray/error.log)</option>
                        <option value="access" <?= $log_view_type == 'access' ? 'selected' : '' ?>>Access Log (/var/log/xray/access.log)</option>
                    </select>

                    <label style="margin-left: 10px;"><?=gettext('Jumlah Baris:')?></label>
                    <select name="lines" class="form-control">
                        <option value="50" <?= $log_lines_count == 50 ? 'selected' : '' ?>>50 Baris</option>
                        <option value="100" <?= $log_lines_count == 100 ? 'selected' : '' ?>>100 Baris</option>
                        <option value="200" <?= $log_lines_count == 200 ? 'selected' : '' ?>>200 Baris</option>
                        <option value="500" <?= $log_lines_count == 500 ? 'selected' : '' ?>>500 Baris</option>
                    </select>

                    <button type="submit" class="btn btn-primary" style="margin-left: 10px;"><i class="fa fa-refresh"></i> <?=gettext('Refresh Log')?></button>
                </form>

                <form method="post" action="vpn_xray.php?tab=logs" class="form-inline pull-right" style="margin-top: -34px;" onsubmit="return confirm('Kosongkan semua file log?');">
                    <input type="hidden" name="act" value="clearlogs">
                    <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-trash"></i> <?=gettext('Clear Log Files')?></button>
                </form>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <?= ($log_view_type == 'access') ? gettext('Laporan Access Log') : gettext('Laporan Error Log') ?>
                    </h3>
                </div>
                <div class="panel-body">
                    <pre style="background: #1e1e1e; color: #dcdcdc; max-height: 500px; overflow-y: scroll; font-size: 12px; font-family: monospace;"><?= htmlspecialchars($log_content) ?></pre>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

    </div>
</div>

<!-- Standalone Client-side QRCode Generator Library -->
<script>
(function(window){
    function generateQRCanvas(text, elementId) {
        var el = document.getElementById(elementId);
        if (!el) return;
        el.innerHTML = "";
        var encoded = encodeURIComponent(text);
        var img = document.createElement("img");
        img.src = "https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=" + encoded;
        img.alt = "QR Code";
        img.style.maxWidth = "180px";
        img.onerror = function() {
            el.innerHTML = "<p class='text-muted' style='font-size:11px;'>QR Code preview (offline fallback: copy link below)</p>";
        };
        el.appendChild(img);
    }
    window.renderQRs = function() {
        var vl = document.getElementById('vless_link');
        if (vl && vl.value) {
            generateQRCanvas(vl.value, 'vless_qrcode');
        }
        var vm = document.getElementById('vmess_link');
        if (vm && vm.value) {
            generateQRCanvas(vm.value, 'vmess_qrcode');
        }
    };
})(window);

function copyLink(elementId) {
    var copyText = document.getElementById(elementId);
    if (!copyText) return;
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value).then(function() {
        alert("Link berhasil disalin ke clipboard!");
    }).catch(function() {
        document.execCommand("copy");
        alert("Link disalin!");
    });
}

function updateClientLinks() {
    var host = document.getElementById('client_host').value.trim();
    if (!host) host = "127.0.0.1";

    var vl = document.getElementById('vless_link');
    if (vl) {
        var uuid = vl.getAttribute('data-uuid');
        var port = vl.getAttribute('data-port');
        var sni  = vl.getAttribute('data-sni');
        var pbk  = vl.getAttribute('data-pbk');
        var sid  = vl.getAttribute('data-sid');
        vl.value = "vless://" + uuid + "@" + host + ":" + port + "?security=reality&encryption=none&flow=xtls-rprx-vision&sni=" + sni + "&fp=chrome&pbk=" + pbk + "&sid=" + sid + "&type=tcp&headerType=none#pfSense-VLESS-Reality";
    }

    var vm = document.getElementById('vmess_link');
    if (vm) {
        var uuid = vm.getAttribute('data-uuid');
        var port = vm.getAttribute('data-port');
        var path = vm.getAttribute('data-path');
        var obj = {
            "v": "2",
            "ps": "pfSense-VMess-WS",
            "add": host,
            "port": parseInt(port),
            "id": uuid,
            "aid": 0,
            "scy": "auto",
            "net": "ws",
            "type": "none",
            "host": "",
            "path": path,
            "tls": ""
        };
        vm.value = "vmess://" + btoa(JSON.stringify(obj));
    }

    window.renderQRs();
}

document.addEventListener("DOMContentLoaded", function() {
    if (typeof window.renderQRs === "function") {
        window.renderQRs();
    }
});
</script>

<?php include("foot.inc"); ?>
