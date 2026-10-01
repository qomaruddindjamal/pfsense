#!/usr/bin/env python3
"""
build_pkg.py - Script pembuat paket FreeBSD/pfSense (.pkg)
Format: FreeBSD pkg-ng (+MANIFEST + file hierarchy, dikompresi dengan zstd)
Repository: https://github.com/qomaruddindjamal/pfsense
"""

import os
import sys
import json
import shutil
import subprocess
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
PACKAGES_DIR = BASE_DIR / "packages"
OUTPUT_DIR = PACKAGES_DIR / "pkg"
OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

import time

def safe_rmtree(path, retries=5, delay=0.5):
    p = Path(path)
    if not p.exists():
        return
    for i in range(retries):
        try:
            shutil.rmtree(p)
            return
        except Exception:
            time.sleep(delay)
    try:
        shutil.rmtree(p, ignore_errors=True)
    except Exception:
        pass

def run_cmd(cmd, cwd=None):
    print(f"[*] Menjalankan: {' '.join(str(c) for c in cmd)}")
    res = subprocess.run(cmd, cwd=cwd, capture_output=True, text=True)
    if res.returncode != 0:
        print(f"[!] Error: {res.stderr}")
        sys.exit(res.returncode)
    return res.stdout

def create_pkg(pkg_name, version, comment, desc, root_staging_dir, post_install_script=None):
    manifest = {
        "name": pkg_name,
        "version": version,
        "origin": f"net/{pkg_name}",
        "comment": comment,
        "arch": "FreeBSD:14:amd64",
        "www": "https://github.com/qomaruddindjamal/pfsense",
        "maintainer": "jamalpancur@gmail.com",
        "prefix": "/",
        "licenselogic": "single",
        "licenses": ["Apache-2.0"],
        "desc": desc,
    }
    if post_install_script:
        manifest["scripts"] = {
            "post-install": post_install_script
        }

    manifest_file = root_staging_dir / "+MANIFEST"
    with open(manifest_file, "w", encoding="utf-8") as f:
        json.dump(manifest, f, indent=2)

    pkg_filename = f"{pkg_name}-{version}.pkg"
    pkg_filepath = OUTPUT_DIR / pkg_filename
    pkg_latest_filepath = OUTPUT_DIR / f"{pkg_name}.pkg"

    if pkg_filepath.exists():
        pkg_filepath.unlink()
    if pkg_latest_filepath.exists():
        pkg_latest_filepath.unlink()

    print(f"[*] Mengemas {pkg_filename} dengan tar (zstd)...")
    # Pack using tar.exe with zstd compression
    # tar.exe --zstd -cf <pkg_file> +MANIFEST usr ...
    entries = [p.name for p in root_staging_dir.iterdir()]
    cmd = ["tar.exe", "--zstd", "-cf", str(pkg_filepath)] + entries
    run_cmd(cmd, cwd=root_staging_dir)

    # Buat copy dengan nama tanpa versi untuk kemudahan akses (e.g. wireguard-pfsense.pkg)
    shutil.copy2(pkg_filepath, pkg_latest_filepath)
    size_mb = pkg_filepath.stat().st_size / (1024 * 1024)
    print(f"[OK] Berhasil membuat paket: {pkg_filepath} ({size_mb:.2f} MB)")
    print(f"[OK] Link cepat: {pkg_latest_filepath}\n")

def build_wireguard():
    print("=== Membangun wireguard-pfsense.pkg ===")
    staging = BASE_DIR / "staging_wireguard"
    if staging.exists():
        shutil.rmtree(staging)
    staging.mkdir(parents=True)

    # Struktur direktori
    (staging / "usr/local/bin").mkdir(parents=True)
    (staging / "usr/local/etc/wireguard").mkdir(parents=True)
    (staging / "usr/local/etc/rc.d").mkdir(parents=True)
    (staging / "usr/local/pkg").mkdir(parents=True)
    (staging / "usr/local/www").mkdir(parents=True)

    # WebGUI Menu & Page
    if (PACKAGES_DIR / "wireguard/usr/local/pkg/wireguard.xml").exists():
        shutil.copy2(PACKAGES_DIR / "wireguard/usr/local/pkg/wireguard.xml", staging / "usr/local/pkg/wireguard.xml")
    if (PACKAGES_DIR / "wireguard/usr/local/www/vpn_wg.php").exists():
        shutil.copy2(PACKAGES_DIR / "wireguard/usr/local/www/vpn_wg.php", staging / "usr/local/www/vpn_wg.php")

    # 1. wireguard-manager CLI
    manager_sh = """#!/bin/sh
# WireGuard Management CLI for pfSense
case "$1" in
  start)
    echo "[*] Memulai WireGuard wg0..."
    wg-quick up wg0
    ;;
  stop)
    echo "[*] Menghentikan WireGuard wg0..."
    wg-quick down wg0
    ;;
  restart)
    wg-quick down wg0 2>/dev/null || true
    wg-quick up wg0
    ;;
  status)
    wg show
    ;;
  genkey)
    mkdir -p /usr/local/etc/wireguard
    wg genkey | tee /usr/local/etc/wireguard/server_private.key | wg pubkey > /usr/local/etc/wireguard/server_public.key
    echo "[+] Keypair baru dibuat di /usr/local/etc/wireguard/"
    echo "Public Key: $(cat /usr/local/etc/wireguard/server_public.key)"
    ;;
  *)
    echo "Penggunaan: wireguard-manager {start|stop|restart|status|genkey}"
    exit 1
    ;;
esac
"""
    with open(staging / "usr/local/bin/wireguard-manager", "w", newline="\n", encoding="utf-8") as f:
        f.write(manager_sh)

    # 2. Config example
    shutil.copy2(PACKAGES_DIR / "wireguard/wg0.conf.example", staging / "usr/local/etc/wireguard/wg0.conf.example")

    # 3. Service rc.d
    rc_sh = """#!/bin/sh
#
# PROVIDE: wireguard_pfsense
# REQUIRE: NETWORKING
# KEYWORD: shutdown
#

. /etc/rc.subr

name="wireguard_pfsense"
rcvar="wireguard_pfsense_enable"

load_rc_config $name

: ${wireguard_pfsense_enable:="NO"}

start_cmd="wg_start"
stop_cmd="wg_stop"

wg_start() {
    if [ -f /usr/local/etc/wireguard/wg0.conf ]; then
        echo "Starting WireGuard wg0..."
        /usr/local/bin/wg-quick up wg0
    fi
}

wg_stop() {
    echo "Stopping WireGuard wg0..."
    /usr/local/bin/wg-quick down wg0 2>/dev/null || true
}

run_rc_command "$1"
"""
    with open(staging / "usr/local/etc/rc.d/wireguard-pfsense", "w", newline="\n", encoding="utf-8") as f:
        f.write(rc_sh)

    post_install = """#!/bin/sh
kldload if_wg >/dev/null 2>&1 || true
if [ -f /boot/loader.conf ] && ! grep -q 'if_wg_load="YES"' /boot/loader.conf; then
    echo 'if_wg_load="YES"' >> /boot/loader.conf
fi
if ! grep -q 'wireguard_enable="YES"' /etc/rc.conf.local 2>/dev/null; then
    echo 'wireguard_enable="YES"' >> /etc/rc.conf.local
fi
chmod 755 /usr/local/bin/wireguard-manager /usr/local/etc/rc.d/wireguard-pfsense
echo "[✓] WireGuard package terpasang! Jalankan 'wireguard-manager genkey' lalu 'wireguard-manager start'"
"""
    create_pkg(
        pkg_name="wireguard-pfsense",
        version="1.0.0",
        comment="WireGuard VPN service and manager for pfSense / FreeBSD",
        desc="Paket WireGuard lengkap untuk pfSense dengan kernel module if_wg, CLI manager, dan service startup.",
        root_staging_dir=staging,
        post_install_script=post_install
    )
    safe_rmtree(staging)

def build_xray():
    print("=== Membangun xray-pfsense.pkg ===")
    staging = BASE_DIR / "staging_xray"
    if staging.exists():
        safe_rmtree(staging)
    staging.mkdir(parents=True)

    (staging / "usr/local/bin").mkdir(parents=True)
    (staging / "usr/local/share/xray").mkdir(parents=True)
    (staging / "usr/local/etc/xray").mkdir(parents=True)
    (staging / "usr/local/etc/rc.d").mkdir(parents=True)
    (staging / "usr/local/pkg").mkdir(parents=True)
    (staging / "usr/local/www").mkdir(parents=True)

    # WebGUI Menu & Page
    if (PACKAGES_DIR / "xray/usr/local/pkg/xray.xml").exists():
        shutil.copy2(PACKAGES_DIR / "xray/usr/local/pkg/xray.xml", staging / "usr/local/pkg/xray.xml")
    if (PACKAGES_DIR / "xray/usr/local/www/vpn_xray.php").exists():
        shutil.copy2(PACKAGES_DIR / "xray/usr/local/www/vpn_xray.php", staging / "usr/local/www/vpn_xray.php")

    xray_bin = PACKAGES_DIR / "xray/usr/local/bin/xray"
    geoip_dat = PACKAGES_DIR / "xray/usr/local/share/xray/geoip.dat"
    geosite_dat = PACKAGES_DIR / "xray/usr/local/share/xray/geosite.dat"

    if xray_bin.exists():
        shutil.copy2(xray_bin, staging / "usr/local/bin/xray")
    if geoip_dat.exists():
        shutil.copy2(geoip_dat, staging / "usr/local/share/xray/geoip.dat")
    if geosite_dat.exists():
        shutil.copy2(geosite_dat, staging / "usr/local/share/xray/geosite.dat")

    # Salin config.json
    shutil.copy2(PACKAGES_DIR / "xray/config.json", staging / "usr/local/etc/xray/config.json")
    # Salin rc.d service
    shutil.copy2(PACKAGES_DIR / "xray/xray.rc.d", staging / "usr/local/etc/rc.d/xray")

    # CLI Helper xray-control
    control_sh = """#!/bin/sh
# Xray Management CLI for pfSense
case "$1" in
  start) service xray start ;;
  stop) service xray stop ;;
  restart) service xray restart ;;
  status) service xray status ;;
  test) /usr/local/bin/xray -test -config /usr/local/etc/xray/config.json ;;
  version) /usr/local/bin/xray version ;;
  *)
    echo "Penggunaan: xray-control {start|stop|restart|status|test|version}"
    exit 1
    ;;
esac
"""
    with open(staging / "usr/local/bin/xray-control", "w", newline="\n", encoding="utf-8") as f:
        f.write(control_sh)

    post_install = """#!/bin/sh
mkdir -p /var/log/xray
chmod 755 /usr/local/bin/xray /usr/local/bin/xray-control /usr/local/etc/rc.d/xray
kldload if_tun >/dev/null 2>&1 || true
if [ -f /boot/loader.conf ] && ! grep -q 'if_tun_load="YES"' /boot/loader.conf; then
    echo 'if_tun_load="YES"' >> /boot/loader.conf
fi
if ! grep -q 'xray_enable="YES"' /etc/rc.conf.local 2>/dev/null; then
    echo 'xray_enable="YES"' >> /etc/rc.conf.local
fi
echo "[✓] Xray-core multi-protokol terpasang! Kelola dengan 'xray-control start' atau 'service xray start'"
"""
    create_pkg(
        pkg_name="xray-pfsense",
        version="1.8.24",
        comment="Xray-core multi-protocol proxy (VLESS, VMess, Trojan, Socks, TUN, Routing) for pfSense",
        desc="Paket Xray-core resmi untuk pfSense/FreeBSD mendukung VLESS+Reality, VMess+WS, Trojan, Socks5, TUN, dan Advanced Routing.",
        root_staging_dir=staging,
        post_install_script=post_install
    )
    safe_rmtree(staging)

def build_kvm():
    print("=== Membangun kvm.pkg (aaPanel KVM Engine) ===")
    staging = BASE_DIR / "staging_aapanel"
    if staging.exists():
        safe_rmtree(staging)
    staging.mkdir(parents=True)

    (staging / "usr/local/bin").mkdir(parents=True)
    (staging / "usr/local/etc/aapanel").mkdir(parents=True)
    (staging / "usr/local/share/aapanel").mkdir(parents=True)

    # CLI command aapanel-pfsense
    cli_sh = """#!/bin/sh
# aaPanel Integration & Bhyve Manager for pfSense
echo "=================================================================="
echo "      aaPanel Management Utility for pfSense (FreeBSD)           "
echo "=================================================================="

case "$1" in
  setup-bhyve)
    echo "[*] Mengaktifkan hypervisor bhyve di kernel pfSense..."
    kldload vmm >/dev/null 2>&1 || true
    kldload nmdm >/dev/null 2>&1 || true
    kldload if_tap >/dev/null 2>&1 || true
    kldload if_bridge >/dev/null 2>&1 || true
    echo "[✓] Modul vmm, nmdm, if_tap, if_bridge telah aktif."
    ;;
  install-linux)
    echo "[*] Menjalankan installer resmi aaPanel di sistem Linux..."
    URL="https://www.aapanel.com/script/install_7.0_en.sh"
    if [ -f /usr/bin/curl ]; then
        curl -ksSO "$URL"
    else
        fetch -o install_7.0_en.sh "$URL" || wget --no-check-certificate -O install_7.0_en.sh "$URL"
    fi
    bash install_7.0_en.sh aapanel
    ;;
  status)
    echo "[*] Status modul virtualisasi bhyve:"
    kldstat | grep -E "vmm|nmdm|if_tap|if_bridge" || echo "Modul bhyve belum dimuat."
    ;;
  *)
    echo "Penggunaan: aapanel-pfsense {setup-bhyve|install-linux|status}"
    exit 1
    ;;
esac
"""
    with open(staging / "usr/local/bin/aapanel-pfsense", "w", newline="\n", encoding="utf-8") as f:
        f.write(cli_sh)

    # Salin seluruh bundle offline aaPanel (panel_7_en.zip, bt7_en.init, dll) ke dalam paket
    bundle_dir = PACKAGES_DIR / "aapanel/bundle"
    if bundle_dir.exists():
        for bfile in bundle_dir.iterdir():
            if bfile.is_file():
                shutil.copy2(bfile, staging / "usr/local/share/aapanel" / bfile.name)

    # Config file
    conf = """# Konfigurasi aaPanel pfSense Integration
BHYVE_VM_NAME="aapanel-vm"
BHYVE_VM_RAM="2048M"
BHYVE_VM_CPUS="2"
BHYVE_DISK_SIZE="20G"
"""
    with open(staging / "usr/local/etc/aapanel/aapanel.conf", "w", newline="\n", encoding="utf-8") as f:
        f.write(conf)

    post_install = """#!/bin/sh
chmod 755 /usr/local/bin/aapanel-pfsense
kldload vmm >/dev/null 2>&1 || true
for mod in vmm nmdm if_tap if_bridge; do
    if [ -f /boot/loader.conf ] && ! grep -q "${mod}_load=\\"YES\\"" /boot/loader.conf; then
        echo "${mod}_load=\\"YES\\"" >> /boot/loader.conf
    fi
done
echo "[✓] aaPanel integration package terpasang! Ketik 'aapanel-pfsense' untuk opsi perintah."
"""
    create_pkg(
        pkg_name="kvm",
        version="1.0.0",
        comment="KVM & Virtual Engine Manager with aaPanel for pfSense",
        desc="Paket virtualisasi KVM/bhyve dan container Linux lengkap dengan aaPanel di dalamnya untuk pfSense.",
        root_staging_dir=staging,
        post_install_script=post_install
    )
    safe_rmtree(staging)

if __name__ == "__main__":
    build_wireguard()
    build_xray()
    build_kvm()
    print("[*] Selesai membangun seluruh paket .pkg!")
