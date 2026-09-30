#!/bin/sh
# ==============================================================================
# WireGuard Installer & Configuration Hook for pfSense / FreeBSD
# Repository: https://github.com/qomaruddindjamal/pfsense
# ==============================================================================

set -e

echo "[*] Memulai instalasi dan konfigurasi WireGuard di pfSense..."

# 1. Update pkg repo dan install pfSense-pkg-WireGuard
if which pkg >/dev/null 2>&1; then
    echo "[*] Menginstal paket pfSense-pkg-WireGuard melalui FreeBSD pkg..."
    ASSUME_ALWAYS_YES=YES pkg update -f
    ASSUME_ALWAYS_YES=YES pkg install -y pfSense-pkg-WireGuard || {
        echo "[!] Gagal install pfSense-pkg-WireGuard, mencoba wireguard-tools kmod langsung..."
        ASSUME_ALWAYS_YES=YES pkg install -y wireguard-tools wireguard-kmod
    }
else
    echo "[ERROR] 'pkg' tidak ditemukan. Pastikan Anda berada di sistem FreeBSD/pfSense."
    exit 1
fi

# 2. Pastikan kernel module wireguard dimuat saat boot
if [ -f /boot/loader.conf ]; then
    if ! grep -q "if_wg_load=" /boot/loader.conf; then
        echo 'if_wg_load="YES"' >> /boot/loader.conf
        echo "[+] if_wg_load=\"YES\" ditambahkan ke /boot/loader.conf"
    fi
fi

# 3. Buat direktori konfigurasi wireguard
mkdir -p /usr/local/etc/wireguard
chmod 700 /usr/local/etc/wireguard

# 4. Generate Keypair jika belum ada
if [ ! -f /usr/local/etc/wireguard/server_private.key ]; then
    echo "[*] Membuat WireGuard Key Pair baru..."
    wg genkey | tee /usr/local/etc/wireguard/server_private.key | wg pubkey > /usr/local/etc/wireguard/server_public.key
    chmod 600 /usr/local/etc/wireguard/server_private.key
    echo "[+] Public Key Server: $(cat /usr/local/etc/wireguard/server_public.key)"
fi

# 5. Salin template konfigurasi jika belum ada wg0.conf
SCRIPT_DIR=$(dirname "$0")
if [ ! -f /usr/local/etc/wireguard/wg0.conf ]; then
    if [ -f "${SCRIPT_DIR}/wg0.conf.example" ]; then
        cp "${SCRIPT_DIR}/wg0.conf.example" /usr/local/etc/wireguard/wg0.conf
        SERVER_PRIV=$(cat /usr/local/etc/wireguard/server_private.key)
        sed -i '' "s|<SERVER_PRIVATE_KEY>|${SERVER_PRIV}|g" /usr/local/etc/wireguard/wg0.conf
        echo "[+] Template wg0.conf berhasil dipasang di /usr/local/etc/wireguard/wg0.conf"
    fi
fi

# 6. Enable service pada rc.conf.local
if ! grep -q 'wireguard_enable="YES"' /etc/rc.conf.local 2>/dev/null; then
    echo 'wireguard_enable="YES"' >> /etc/rc.conf.local
    echo 'wireguard_interfaces="wg0"' >> /etc/rc.conf.local
fi

echo "[✓] Instalasi WireGuard selesai!"
echo "Untuk menjalankan interface: wg-quick up wg0"
