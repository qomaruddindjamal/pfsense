#!/bin/sh
# ==============================================================================
# Xray-core Installer & Configurator for pfSense / FreeBSD (amd64)
# Protocols: VLESS, VMess, Trojan, Socks5, TUN, Advanced Routing
# Repository: https://github.com/qomaruddindjamal/pfsense
# ==============================================================================

set -e

XRAY_VER="v1.8.24"
XRAY_URL="https://github.com/XTLS/Xray-core/releases/download/${XRAY_VER}/Xray-freebsd-64.zip"
INSTALL_DIR="/usr/local/bin"
CONFIG_DIR="/usr/local/etc/xray"
LOG_DIR="/var/log/xray"
RC_SCRIPT="/usr/local/etc/rc.d/xray"

echo "[*] Memulai instalasi Xray-core (${XRAY_VER}) untuk FreeBSD / pfSense..."

# Pastikan tool wget atau curl serta unzip terpasang
if ! which unzip >/dev/null 2>&1; then
    echo "[*] Menginstal unzip via pkg..."
    ASSUME_ALWAYS_YES=YES pkg install -y unzip curl
fi

# Buat direktori kerja
mkdir -p "${CONFIG_DIR}" "${LOG_DIR}" /tmp/xray-install

# Download binary Xray FreeBSD 64-bit
echo "[*] Mendownload Xray-core dari GitHub Releases..."
curl -sSL -o /tmp/xray-install/xray.zip "${XRAY_URL}" || fetch -o /tmp/xray-install/xray.zip "${XRAY_URL}"

# Ekstrak
echo "[*] Mengekstrak binary dan database GeoIP/GeoSite..."
unzip -o /tmp/xray-install/xray.zip -d /tmp/xray-install/

# Pasang binary & asset dat
cp /tmp/xray-install/xray "${INSTALL_DIR}/xray"
chmod 755 "${INSTALL_DIR}/xray"

mkdir -p /usr/local/share/xray
cp /tmp/xray-install/geoip.dat /usr/local/share/xray/
cp /tmp/xray-install/geosite.dat /usr/local/share/xray/

# Pasang template config jika belum ada
SCRIPT_DIR=$(dirname "$0")
if [ ! -f "${CONFIG_DIR}/config.json" ]; then
    if [ -f "${SCRIPT_DIR}/config.json" ]; then
        cp "${SCRIPT_DIR}/config.json" "${CONFIG_DIR}/config.json"
        echo "[+] Berhasil memasang template konfigurasi di ${CONFIG_DIR}/config.json"
    fi
fi

# Pasang service rc.d untuk startup otomatis
if [ -f "${SCRIPT_DIR}/xray.rc.d" ]; then
    cp "${SCRIPT_DIR}/xray.rc.d" "${RC_SCRIPT}"
    chmod 755 "${RC_SCRIPT}"
    echo "[+] Service script dipasang di ${RC_SCRIPT}"
fi

# Daftarkan service ke /etc/rc.conf.local
if ! grep -q 'xray_enable="YES"' /etc/rc.conf.local 2>/dev/null; then
    echo 'xray_enable="YES"' >> /etc/rc.conf.local
fi

# Load tun module jika belum
kldload if_tun >/dev/null 2>&1 || true
if [ -f /boot/loader.conf ] && ! grep -q 'if_tun_load="YES"' /boot/loader.conf; then
    echo 'if_tun_load="YES"' >> /boot/loader.conf
fi

# Bersihkan temporary
rm -rf /tmp/xray-install

echo "[✓] Xray-core berhasil diinstal!"
"${INSTALL_DIR}/xray" version
echo "[*] Jalankan service: service xray start"
