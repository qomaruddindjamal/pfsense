#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
cd "${ROOT_DIR}" || cd /c/pfsense

ISO_NAME="pfsense-custom-offline-installer.iso"
IMG_NAME="pfsense-custom-offline-installer.img"
GZ_NAME="pfsense-custom-offline-installer.img.gz"

echo "=========================================================="
echo "  Membangun Universal Hybrid ISO & USB Disk Image pfSense "
echo "=========================================================="

xorriso -as mkisofs -V "PFSENSE" -iso-level 3 -J -r \
  -file-mode 0755 -dir-mode 0755 \
  -b boot/cdboot -no-emul-boot \
  -eltorito-alt-boot -b boot/efi.img -no-emul-boot \
  -G installer_source/boot/isoboot \
  -o "${ISO_NAME}" \
  installer_source

echo "[✓] Citra hybrid ISO berhasil dibuat: ${ISO_NAME}"
cp -f "${ISO_NAME}" pfsense-offline-installer.iso
cp -f "${ISO_NAME}" "${IMG_NAME}"
echo "[✓] Citra disk USB (.img) berhasil disiapkan: ${IMG_NAME}"

echo "[*] Mengompres ke format ${GZ_NAME}..."
if command -v 7z >/dev/null 2>&1; then
    rm -f "${GZ_NAME}"
    7z a -tgzip -mx=6 "${GZ_NAME}" "${IMG_NAME}" >/dev/null
elif command -v gzip >/dev/null 2>&1; then
    gzip -k -f -9 "${IMG_NAME}"
fi

echo "[✓] Berkas ${GZ_NAME} berhasil dibuat!"
ls -lh "${ISO_NAME}" "${IMG_NAME}" "${GZ_NAME}"
