#!/bin/sh
# ==============================================================================
# Script: remaster_iso.sh
# Menambahkan paket offline (WireGuard, Xray, aaPanel) ke dalam file ISO pfSense
# ==============================================================================

set -e

ISO_ORIGINAL="netgate-installer-amd64.iso"
ISO_OUTPUT="pfsense-custom-offline-installer.iso"
MNT_DIR="/tmp/pfsense_iso_mnt"
WORK_DIR="/tmp/pfsense_iso_work"

echo "[*] Mempersiapkan remastering ISO pfSense dengan paket offline..."

if [ ! -f "${ISO_ORIGINAL}" ]; then
    echo "[!] File ${ISO_ORIGINAL} tidak ditemukan di direktori saat ini."
    exit 1
fi

mkdir -p "${MNT_DIR}" "${WORK_DIR}"

# 1. Mount atau Ekstrak ISO
echo "[*] Mengekstrak isi ISO asli..."
if which 7z >/dev/null 2>&1; then
    7z x "${ISO_ORIGINAL}" -o"${WORK_DIR}" -y
elif which xorriso >/dev/null 2>&1; then
    xorriso -osirrox on -indev "${ISO_ORIGINAL}" -extract / "${WORK_DIR}"
fi

# 2. Sisipkan paket offline
echo "[*] Menyisipkan paket offline WireGuard, Xray, dan aaPanel..."
mkdir -p "${WORK_DIR}/usr/local/share/packages/offline"
cp -r installer_source/usr/local/share/packages/offline/* "${WORK_DIR}/usr/local/share/packages/offline/"

# 3. Timpa modul installer dengan versi offline cepat
cp -f installer_source/usr/local/libexec/installer/pfSense-install "${WORK_DIR}/usr/local/libexec/installer/"
cp -f installer_source/usr/local/libexec/installer/pfSense-post-install "${WORK_DIR}/usr/local/libexec/installer/"
chmod +x "${WORK_DIR}/usr/local/libexec/installer/pfSense-install"
chmod +x "${WORK_DIR}/usr/local/libexec/installer/pfSense-post-install"

# 4. Bangun kembali ISO bootable
echo "[*] Membuat image ISO baru: ${ISO_OUTPUT}..."
if which mkisofs >/dev/null 2>&1; then
    mkisofs -V "PFSENSE" -J -r -file-mode 0755 -dir-mode 0755 -b boot/cdboot -no-emul-boot -o "${ISO_OUTPUT}" "${WORK_DIR}"
elif which xorriso >/dev/null 2>&1; then
    xorriso -as mkisofs -V "PFSENSE" -J -r -file-mode 0755 -dir-mode 0755 -b boot/cdboot -no-emul-boot -o "${ISO_OUTPUT}" "${WORK_DIR}"
fi

echo "[✓] ISO Custom pfSense Offline berhasil dibuat: ${ISO_OUTPUT}"
