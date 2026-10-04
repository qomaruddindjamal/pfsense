#!/usr/bin/env bash
# ==============================================================================
# Script: install-pfsense-from-linux.sh
# Deskripsi: Mengganti / Menimpa OS Linux VPS yang sedang berjalan menjadi pfSense
# Repository: https://github.com/qomaruddindjamal/pfsense
# ==============================================================================

set -e

# Warna output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}======================================================================${NC}"
echo -e "${GREEN}    pfSense Auto-Installer / Takeover Script dari Linux                 ${NC}"
echo -e "${YELLOW}           Repository: https://github.com/qomaruddindjamal/pfsense     ${NC}"
echo -e "${BLUE}======================================================================${NC}"

# 1. Validasi Akses Root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}[ERROR] Script ini harus dijalankan sebagai root! (sudo -i)${NC}"
    exit 1
fi

# 2. Periksa dependensi
echo -e "${YELLOW}[*] Memeriksa paket pendukung (wget, curl, gzip, parted, udev)...${NC}"
if which apt-get >/dev/null 2>&1; then
    apt-get update -y >/dev/null 2>&1 || true
    apt-get install -y wget curl gzip parted udev >/dev/null 2>&1 || true
elif which yum >/dev/null 2>&1; then
    yum install -y wget curl gzip parted udev >/dev/null 2>&1 || true
elif which apk >/dev/null 2>&1; then
    apk add wget curl gzip parted udev >/dev/null 2>&1 || true
fi

# 3. Deteksi Informasi Jaringan Saat Ini
echo -e "${YELLOW}[*] Mendeteksi konfigurasi jaringan VPS...${NC}"
MAIN_IF=$(ip route get 8.8.8.8 2>/dev/null | awk '{print $5; exit}')
if [ -z "$MAIN_IF" ]; then
    MAIN_IF=$(ip -4 route show default | awk '{print $5; exit}')
fi

CURRENT_IP=$(ip -4 addr show dev "$MAIN_IF" | grep inet | awk '{print $2}' | cut -d/ -f1 | head -n1)
CURRENT_PREFIX=$(ip -4 addr show dev "$MAIN_IF" | grep inet | awk '{print $2}' | cut -d/ -f2 | head -n1)
CURRENT_GW=$(ip route show default | awk '{print $3; exit}')
CURRENT_MAC=$(cat /sys/class/net/"$MAIN_IF"/address 2>/dev/null || echo "Unknown")

echo -e "${GREEN}[+] Interface Utama : ${MAIN_IF}${NC}"
echo -e "${GREEN}[+] IP Address Saat Ini: ${CURRENT_IP}/${CURRENT_PREFIX}${NC}"
echo -e "${GREEN}[+] Gateway Saat Ini   : ${CURRENT_GW}${NC}"
echo -e "${GREEN}[+] MAC Address        : ${CURRENT_MAC}${NC}"

# 4. Deteksi Harddisk Utama
echo -e "${YELLOW}[*] Mendeteksi harddisk utama sistem...${NC}"
ROOT_PART=$(df / | tail -1 | awk '{print $1}')
TARGET_DISK=$(lsblk -no pkname "$ROOT_PART" 2>/dev/null || echo "")

if [ -z "$TARGET_DISK" ]; then
    # Fallback pencarian /dev/vda, /dev/sda, /dev/nvme0n1
    if [ -b "/dev/vda" ]; then
        TARGET_DISK="vda"
    elif [ -b "/dev/sda" ]; then
        TARGET_DISK="sda"
    elif [ -b "/dev/nvme0n1" ]; then
        TARGET_DISK="nvme0n1"
    else
        echo -e "${RED}[ERROR] Gagal mendeteksi disk utama!${NC}"
        exit 1
    fi
fi

TARGET_DEV="/dev/${TARGET_DISK}"
DISK_SIZE=$(lsblk -bno SIZE "$TARGET_DEV" | head -1 | awk '{printf "%.1f GB", $1/1024/1024/1024}')
echo -e "${GREEN}[+] Target Disk         : ${TARGET_DEV} (${DISK_SIZE})${NC}"

# 5. Konfirmasi Pengguna
echo -e "\n${RED}======================================================================${NC}"
echo -e "${RED}[PERINGATAN KERAS] PROSES INI AKAN MENIMPA SELURUH ISI DISK ${TARGET_DEV}!${NC}"
echo -e "${RED}SEMUA DATA DI LINUX SAAT INI AKAN DIHAPUS DAN DIGANTI DENGAN PFSENSE!${NC}"
echo -e "${RED}======================================================================${NC}"

# Pilihan URL Image
# Menggunakan Custom Offline Installer pfSense img.gz dari rilis GitHub repository ini
DEFAULT_IMG_URL="https://github.com/qomaruddindjamal/pfsense/releases/download/pfSense/pfsense-offline-installer.img.gz"
IMG_URL="${1:-$DEFAULT_IMG_URL}"

echo -e "${YELLOW}URL Image pfSense yang akan digunakan:${NC} ${IMG_URL}"
echo -e "Menunggu 5 detik sebelum memulai proses penulisan disk..."
sleep 5

# 6. Menghapus Signature Disk dan Menulis Image pfSense via DD
echo -e "${YELLOW}[*] Mendownload dan mengekstrak image pfSense langsung ke ${TARGET_DEV}...${NC}"

# Menulis langsung stream gzip ke target disk
if curl -sSL -I "$IMG_URL" | grep -q "200 OK\|302 Found\|301 Moved"; then
    echo -e "${GREEN}[*] Mengalirkan image ke disk... Mohon tunggu beberapa menit.${NC}"
    curl -sSL "$IMG_URL" | gzip -dc | dd of="$TARGET_DEV" bs=4M status=progress oflag=sync
else
    echo -e "${YELLOW}[!] URL utama tidak merespon langsung, mencoba via wget...${NC}"
    wget -qO- "$IMG_URL" | gzip -dc | dd of="$TARGET_DEV" bs=4M status=progress oflag=sync
fi

echo -e "${GREEN}[✓] Penulisan image pfSense ke ${TARGET_DEV} selesai!${NC}"

# 7. Sinkronisasi Disk
echo -e "${YELLOW}[*] Menyinkronkan filesystem...${NC}"
sync

# 8. Informasi Akses Default pfSense
echo -e "\n${GREEN}======================================================================${NC}"
echo -e "${GREEN}      INSTALASI SELESAI - SISTEM AKAN REBOOT KE PFSENSE               ${NC}"
echo -e "${GREEN}======================================================================${NC}"
echo -e "Informasi Akses Default pfSense setelah reboot:"
echo -e "  - WebGUI / SSH IP : ${CURRENT_IP} (atau via Serial/VNC Console)"
echo -e "  - Username        : admin"
echo -e "  - Password Default: pfsense"
echo -e "  - WebGUI Port     : 80 / 443"
echo -e "  - SSH Port        : 22"
echo -e "${YELLOW}Catatan: Pada booting pertama, pfSense akan mengenali antarmuka jaringan${NC}"
echo -e "${YELLOW}(vtnet0 untuk KVM/Proxmox, em0/igb0/vmx0 untuk VMware/Baremetal).${NC}"
echo -e "${GREEN}======================================================================${NC}"

# 9. Trigger SysRq Reboot (Instant reboot tanpa hang)
echo -e "${YELLOW}[*] Mengirim sinyal reboot paksa hardware via SysRq...${NC}"
sleep 3
echo 1 > /proc/sys/kernel/sysrq 2>/dev/null || true
echo b > /proc/sysrq-trigger 2>/dev/null || reboot -f
