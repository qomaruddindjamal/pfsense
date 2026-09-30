#!/bin/bash
# ==============================================================================
# aaPanel Installer Script (Linux Environment)
# Supported: Ubuntu, Debian, AlmaLinux, RockyLinux, CentOS
# Repository: https://github.com/qomaruddindjamal/pfsense
# ==============================================================================

set -e

echo "[*] Memeriksa lingkungan sistem untuk instalasi aaPanel..."

if [ "$(uname)" != "Linux" ]; then
    echo "[ERROR] aaPanel membutuhkan sistem operasi Linux (Ubuntu/Debian/CentOS/AlmaLinux)."
    echo "Sistem saat ini terdeteksi: $(uname)"
    echo "Jika Anda menggunakan pfSense (FreeBSD), jalankan aaPanel di dalam VM bhyve menggunakan script setup-aapanel-bhyve.sh"
    exit 1
fi

echo "[*] Mendownload dan menjalankan installer resmi aaPanel..."
URL="https://www.aapanel.com/script/install_7.0_en.sh"

if [ -f /usr/bin/curl ]; then
    curl -ksSO "$URL"
else
    wget --no-check-certificate -O install_7.0_en.sh "$URL"
fi

bash install_7.0_en.sh aapanel
