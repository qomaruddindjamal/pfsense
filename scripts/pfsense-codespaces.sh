#!/bin/bash
# ==============================================================================
# Script: pfsense-codespaces.sh
# Menjalankan & Menginstal pfSense di GitHub Codespaces (QEMU / KVM + noVNC + Nginx Proxy)
# ==============================================================================

set -e

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WORK_DIR="${HOME}/pfsense_vm"
DISK_IMG="${WORK_DIR}/pfsense-disk.qcow2"
DISK_SIZE="10G"
ISO_IMG="${WORK_DIR}/pfsense.iso"
MONITOR_SOCK="/tmp/qemu-monitor.sock"
RAM="3072"
CPUS="2"

# Port Forwarding
VNC_PORT="5900"
NOVNC_PORT="6080"
WAN_HTTPS="8443"
WAN_HTTP="8080"
WAN_SSH="22222"
QEMU_PFSENSE_PORT="8444"

check_kvm() {
    if [ -e /dev/kvm ]; then
        sudo chmod 666 /dev/kvm 2>/dev/null || true
        KVM_OPT="-enable-kvm -cpu host"
        echo "[✓] KVM Hardware Acceleration aktif (/dev/kvm terdeteksi)."
    else
        KVM_OPT="-cpu qemu64"
        echo "[!] /dev/kvm tidak ditemukan. Menggunakan emulasi CPU standar."
    fi
}

install_deps() {
    echo "[*] Memeriksa dependensi sistem..."
    sudo apt-get update -qq
    sudo apt-get install -y -qq \
        qemu-system-x86 \
        qemu-utils \
        xorriso \
        ovmf \
        novnc \
        websockify \
        socat \
        netpbm \
        ffmpeg \
        nginx \
        curl
    echo "[✓] Semua dependensi berhasil dipasang."
}

build_iso() {
    mkdir -p "${WORK_DIR}"
    echo "[*] Menyiapkan struktur direktori installer..."
    mkdir -p "${DIR}/installer_source"/{dev,proc,mnt,media,tmp,net}
    chmod 1777 "${DIR}/installer_source/tmp" 2>/dev/null || true

    echo "[*] Membangun file bootable ISO pfSense..."
    xorriso -as mkisofs \
        -V "PFSENSE" \
        -J -r \
        -file-mode 0755 \
        -dir-mode 0755 \
        -b boot/cdboot \
        -no-emul-boot \
        -o "${ISO_IMG}" \
        "${DIR}/installer_source"
    echo "[✓] ISO berhasil dibangun di: ${ISO_IMG}"
}

create_disk() {
    mkdir -p "${WORK_DIR}"
    if [ ! -f "${DISK_IMG}" ]; then
        echo "[*] Membuat virtual disk QCOW2 ${DISK_SIZE}..."
        qemu-img create -f qcow2 "${DISK_IMG}" "${DISK_SIZE}"
        echo "[✓] Virtual disk dibuat: ${DISK_IMG}"
    else
        echo "[i] Virtual disk sudah ada: ${DISK_IMG}"
    fi
}

setup_image() {
    install_deps
    mkdir -p "${WORK_DIR}"
    local RELEASE_IMG_URL="https://github.com/qomaruddindjamal/pfsense/releases/download/pfSense/pfsense-offline-installer.img.gz"
    echo "[*] Mengunduh & menulis image pfSense langsung dari GitHub Releases..."
    echo "[*] URL: ${RELEASE_IMG_URL}"
    curl -sSL "${RELEASE_IMG_URL}" | gzip -dc | dd of="${WORK_DIR}/pfsense-disk.raw" bs=4M status=progress
    echo "[*] Mengonversi citra disk ke format QCOW2..."
    qemu-img convert -f raw -O qcow2 "${WORK_DIR}/pfsense-disk.raw" "${DISK_IMG}"
    qemu-img resize "${DISK_IMG}" "${DISK_SIZE}"
    rm -f "${WORK_DIR}/pfsense-disk.raw"
    echo "[✓] Virtual disk pfSense siap! Jalankan '$0 start' untuk langsung boot."
}

start_novnc() {
    if ! pgrep -f "websockify.*${NOVNC_PORT}" > /dev/null; then
        echo "[*] Menjalankan noVNC Web Server di port ${NOVNC_PORT}..."
        nohup /usr/bin/websockify --web /usr/share/novnc/ "${NOVNC_PORT}" 127.0.0.1:"${VNC_PORT}" > "${WORK_DIR}/websockify.log" 2>&1 &
        sleep 1
    fi
}

setup_reverse_proxy() {
    echo "[*] Mengonfigurasi Nginx Reverse Proxy untuk WebGUI Codespaces..."
    sudo mkdir -p /etc/nginx/sites-available /etc/nginx/sites-enabled
    cat << 'EOF' | sudo tee /etc/nginx/sites-available/pfsense-proxy > /dev/null
server {
    listen 8443 default_server;
    listen 8080;
    server_name _;

    location / {
        proxy_pass https://127.0.0.1:8444;
        proxy_ssl_verify off;
        proxy_set_header Host localhost;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_read_timeout 300s;
        proxy_connect_timeout 75s;
    }
}
EOF
    sudo rm -f /etc/nginx/sites-enabled/default
    sudo ln -sf /etc/nginx/sites-available/pfsense-proxy /etc/nginx/sites-enabled/pfsense-proxy
    sudo nginx -t > /dev/null 2>&1
    sudo nginx -s reload 2>/dev/null || sudo nginx
    echo "[✓] Nginx reverse proxy aktif (menghubungkan port ${WAN_HTTPS}/${WAN_HTTP} ke pfSense)."
}

print_urls() {
    local CS_NAME="${CODESPACE_NAME}"
    echo ""
    echo "=========================================================================="
    echo "              pfSense di GitHub Codespaces Berhasil Aktif                 "
    echo "=========================================================================="
    if [ -n "${CS_NAME}" ]; then
        echo "🌐 pfSense WebGUI    : https://${CS_NAME}-${WAN_HTTPS}.app.github.dev/"
        echo "📺 noVNC Web Console : https://${CS_NAME}-${NOVNC_PORT}.app.github.dev/vnc.html"
        echo "🔑 SSH pfSense       : Port ${WAN_SSH} (user: admin, pass: pfsense)"
    else
        echo "🌐 pfSense WebGUI    : https://localhost:${WAN_HTTPS}/"
        echo "📺 noVNC Web Console : http://localhost:${NOVNC_PORT}/vnc.html"
        echo "🔑 SSH pfSense       : Port ${WAN_SSH}"
    fi
    echo "=========================================================================="
    echo ""
}

start_vm_installer() {
    check_kvm
    create_disk
    if [ ! -f "${ISO_IMG}" ]; then
        build_iso
    fi
    start_novnc
    setup_reverse_proxy

    echo "[*] Memulai VM pfSense dalam Mode Installer (Boot dari ISO)..."
    rm -f "${MONITOR_SOCK}"
    nohup qemu-system-x86_64 \
        ${KVM_OPT} \
        -smp "${CPUS}" \
        -m "${RAM}" \
        -drive file="${DISK_IMG}",if=virtio,format=qcow2 \
        -cdrom "${ISO_IMG}" \
        -boot order=d,menu=on \
        -netdev user,id=wan,hostfwd=tcp::${QEMU_PFSENSE_PORT}-:443,hostfwd=tcp::${WAN_SSH}-:22 \
        -device e1000,netdev=wan,mac=52:54:00:12:34:56 \
        -netdev user,id=lan,net=192.168.1.0/24 \
        -device e1000,netdev=lan,mac=52:54:00:12:34:57 \
        -vnc 127.0.0.1:0 \
        -monitor unix:"${MONITOR_SOCK}",server,nowait \
        > "${WORK_DIR}/qemu.log" 2>&1 &

    sleep 3
    print_urls
}

start_vm_disk() {
    check_kvm
    if [ ! -f "${DISK_IMG}" ]; then
        echo "[!] Disk image belum ada. Jalankan '$0 install' terlebih dahulu!"
        exit 1
    fi
    start_novnc
    setup_reverse_proxy

    echo "[*] Memulai VM pfSense dari Virtual Disk (Mode Operasional)..."
    rm -f "${MONITOR_SOCK}"
    nohup qemu-system-x86_64 \
        ${KVM_OPT} \
        -smp "${CPUS}" \
        -m "${RAM}" \
        -drive file="${DISK_IMG}",if=virtio,format=qcow2 \
        -boot c \
        -netdev user,id=wan,hostfwd=tcp::${QEMU_PFSENSE_PORT}-:443,hostfwd=tcp::${WAN_SSH}-:22 \
        -device e1000,netdev=wan,mac=52:54:00:12:34:56 \
        -netdev user,id=lan,net=192.168.1.0/24 \
        -device e1000,netdev=lan,mac=52:54:00:12:34:57 \
        -vnc 127.0.0.1:0 \
        -monitor unix:"${MONITOR_SOCK}",server,nowait \
        > "${WORK_DIR}/qemu.log" 2>&1 &

    sleep 3
    print_urls
}

stop_vm() {
    echo "[*] Menghentikan VM pfSense, noVNC, dan proxy..."
    killall qemu-system-x86_64 2>/dev/null || true
    pkill -f "websockify.*${NOVNC_PORT}" 2>/dev/null || true
    sudo nginx -s stop 2>/dev/null || true
    rm -f "${MONITOR_SOCK}"
    echo "[✓] Layanan berhasil dihentikan."
}

status_vm() {
    echo "=== Status pfSense Codespaces ==="
    if pgrep -f "qemu-system-x86_64" > /dev/null; then
        echo "● QEMU VM      : AKTIF (PID: $(pgrep -f "qemu-system-x86_64"))"
    else
        echo "○ QEMU VM      : BERHENTI"
    fi

    if pgrep -f "websockify.*${NOVNC_PORT}" > /dev/null; then
        echo "● noVNC Server : AKTIF di port ${NOVNC_PORT}"
    else
        echo "○ noVNC Server : BERHENTI"
    fi

    if pgrep -f "nginx: master" > /dev/null; then
        echo "● Nginx Proxy  : AKTIF di port ${WAN_HTTPS} & ${WAN_HTTP}"
    else
        echo "○ Nginx Proxy  : BERHENTI"
    fi

    if [ -f "${DISK_IMG}" ]; then
        echo "💾 Virtual Disk: $(ls -lh "${DISK_IMG}" | awk '{print $5, $9}')"
    fi

    print_urls
}

screenshot_vm() {
    local OUT_PNG="${1:-/tmp/pfsense_screenshot.png}"
    if [ ! -e "${MONITOR_SOCK}" ]; then
        echo "[!] Monitor socket tidak aktif. Apakah VM sedang berjalan?"
        exit 1
    fi
    echo "screendump /tmp/screen.ppm" | socat - UNIX-CONNECT:"${MONITOR_SOCK}" >/dev/null 2>&1
    ffmpeg -y -i /tmp/screen.ppm "${OUT_PNG}" >/dev/null 2>&1
    echo "[✓] Screenshot tersimpan di: ${OUT_PNG}"
}

case "$1" in
    setup)
        install_deps
        build_iso
        create_disk
        ;;
    setup-image)
        setup_image
        ;;
    build-iso)
        build_iso
        ;;
    install)
        stop_vm
        start_vm_installer
        ;;
    start)
        stop_vm
        start_vm_disk
        ;;
    stop)
        stop_vm
        ;;
    restart)
        stop_vm
        start_vm_disk
        ;;
    status)
        status_vm
        ;;
    screenshot)
        screenshot_vm "$2"
        ;;
    *)
        echo "Penggunaan: $0 {setup|setup-image|build-iso|install|start|stop|restart|status|screenshot}"
        echo ""
        echo "Perintah:"
        echo "  setup-image : [Instan] Unduh & tulis image rilis img.gz dari GitHub Releases (~30 detik)"
        echo "  setup       : Pasang semua dependensi sistem, bangun ISO & siapkan disk"
        echo "  install     : Jalankan VM boot dari ISO untuk memulai proses instalasi"
        echo "  start       : Jalankan VM dari disk yang sudah terinstal pfSense"
        echo "  stop        : Hentikan VM, proxy Nginx, dan server noVNC"
        echo "  status      : Cek status proses dan tautan URL WebGUI / noVNC"
        echo "  screenshot  : Ambil tangkapan layar konsol VM secara instan"
        exit 1
        ;;
esac
