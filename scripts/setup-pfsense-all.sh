#!/bin/sh
# ==============================================================================
# Script: setup-pfsense-all.sh
# Menyiapkan WireGuard dan Xray-core sekaligus di sistem pfSense / FreeBSD
# Repository: https://github.com/qomaruddindjamal/pfsense
# ==============================================================================

set -e

SCRIPT_DIR=$(dirname "$0")
BASE_DIR="${SCRIPT_DIR}/.."

echo "======================================================================"
echo "    Instalasi Paket Lengkap pfSense: WireGuard & Xray-core           "
echo "======================================================================"

# 1. Install WireGuard
echo "\n--- 1. Memasang WireGuard ---"
sh "${BASE_DIR}/packages/wireguard/install-wireguard.sh"

# 2. Install Xray-core
echo "\n--- 2. Memasang Xray-core (VLESS, VMess, Trojan, Socks5, TUN) ---"
sh "${BASE_DIR}/packages/xray/install-xray.sh"

echo "\n[✓] Seluruh konfigurasi selesai dipasang!"
echo "Untuk mengaktifkan Xray: service xray start"
echo "Untuk mengaktifkan WireGuard: wg-quick up wg0"
