#!/bin/sh
# ==============================================================================
# Script setup bhyve VM untuk menjalankan aaPanel (Linux) di atas FreeBSD/pfSense
# Repository: https://github.com/qomaruddindjamal/pfsense
# ==============================================================================

set -e

echo "[*] Menyiapkan modul hypervisor bhyve di pfSense/FreeBSD..."

kldload vmm >/dev/null 2>&1 || true
kldload nmdm >/dev/null 2>&1 || true
kldload if_tap >/dev/null 2>&1 || true
kldload if_bridge >/dev/null 2>&1 || true

# Tambahkan ke /boot/loader.conf
for mod in vmm nmdm if_tap if_bridge; do
    if ! grep -q "${mod}_load=\"YES\"" /boot/loader.conf 2>/dev/null; then
        echo "${mod}_load=\"YES\"" >> /boot/loader.conf
    fi
done

VM_DIR="/usr/local/vm/aapanel"
mkdir -p "${VM_DIR}"

echo "[+] Hypervisor bhyve siap di pfSense!"
echo "Untuk menginstal Linux (Debian/Ubuntu) + aaPanel di dalam bhyve:"
echo "1. Gunakan 'vm-bhyve' (pkg install -y vm-bhyve) atau shell script bhyve."
echo "2. Setelah sistem operasi Linux di dalam VM aktif, jalankan install-aapanel-linux.sh."
