#!/bin/sh
# ==============================================================================
# Script: setup-pfsense-all.sh
# Menyiapkan seluruh paket kustom pfSense (WireGuard, Xray-core, KVM aaPanel, WiFi, Speedtest)
# Repository: https://github.com/qomaruddindjamal/pfsense
# ==============================================================================

set -e

SCRIPT_DIR=$(dirname "$0")
BASE_DIR="${SCRIPT_DIR}/.."
RAW_URL="https://raw.githubusercontent.com/qomaruddindjamal/pfsense/main/installer_source/packages/All"

echo "======================================================================"
echo "  Instalasi Paket Lengkap pfSense Custom Offline & Online Setup       "
echo "======================================================================"

PKGS="wireguard-pfsense.pkg xray-pfsense.pkg kvm.pkg speedtest.pkg wifi.pkg"

for pkg in ${PKGS}; do
    if [ -f "${BASE_DIR}/installer_source/packages/All/${pkg}" ]; then
        echo "\n[*] Memasang ${pkg} dari media lokal..."
        pkg add -f "${BASE_DIR}/installer_source/packages/All/${pkg}"
    else
        echo "\n[*] Mengunduh dan memasang ${pkg} dari GitHub..."
        pkg add "${RAW_URL}/${pkg}"
    fi
done

echo "\n======================================================================"
echo "  [✓] Seluruh paket kustom pfSense berhasil dipasang!"
echo "  - WireGuard: wireguard-manager status"
echo "  - Xray-core: service xray status / xray-control test"
echo "  - Speedtest: speedtest (atau via WebGUI Tools > Speedtest)"
echo "  - aaPanel  : aapanel-pfsense status"
echo "  - Wifi     : wifi-manager status"
echo "======================================================================"
