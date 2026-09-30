#!/bin/sh
#
# Script otomatis penggabung berkas paket pfSense split (.partaa + .partab)
#

PKG_DIR="$(cd "$(dirname "$0")/All" 2>/dev/null && pwd)"
if [ ! -d "${PKG_DIR}" ]; then
    PKG_DIR="$(cd "$(dirname "$0")" 2>/dev/null && pwd)"
fi

echo "=========================================================="
echo "    PFSENSE OFFLINE PACKAGE REASSEMBLER (AUTO-JOIN)       "
echo "=========================================================="

for partaa in "${PKG_DIR}"/*.pkg.partaa; do
    if [ -f "${partaa}" ]; then
        pkg_target="${partaa%.partaa}"
        if [ ! -f "${pkg_target}" ]; then
            echo "[*] Menggabungkan partisi $(basename "${pkg_target}")..."
            cat "${pkg_target}".part* > "${pkg_target}"
            echo "[✓] Berhasil: $(basename "${pkg_target}") ($(stat -f "%z" "${pkg_target}" 2>/dev/null || wc -c < "${pkg_target}") bytes)"
        else
            echo "[=] Paket sudah ada: $(basename "${pkg_target}")"
        fi
    fi
done
echo "[✓] Seluruh paket repositori offline lengkap dan siap digunakan!"
