#!/bin/bash
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
cd "${ROOT_DIR}" || cd /c/pfsense
xorriso -as mkisofs -V "pfSense_Installer" -iso-level 3 -J -R \
  -b boot/cdboot -no-emul-boot -boot-load-size 4 -boot-info-table \
  -eltorito-alt-boot -b boot/efi.img -no-emul-boot \
  -o pfsense-custom-offline-installer.iso \
  installer_source
