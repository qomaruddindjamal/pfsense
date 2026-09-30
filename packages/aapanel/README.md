# Integrasi aaPanel

## Catatan Arsitektur Penting: pfSense (FreeBSD) vs aaPanel (Linux)
- **pfSense** dibangun di atas kernel dan userland **FreeBSD**. pfSense sudah memiliki sistem WebGUI bawaan (PHP-FPM + Nginx) untuk seluruh administrasi firewall, routing, NAT, dan VPN.
- **aaPanel** adalah web control panel hosting/server yang dirancang khusus untuk distribusi **Linux** (Debian, Ubuntu, CentOS, AlmaLinux, Rocky Linux) dengan ketergantungan pada `systemd`, `glibc`, serta package manager `apt` / `yum`.
- Oleh karena itu, aaPanel **tidak dapat** diinstal langsung secara native di dalam FreeBSD userland pfSense.

## Opsi Penggunaan yang Didukung:
1. **Opsi 1: Server Linux Mandiri / VPS**
   Gunakan script `install-aapanel-linux.sh` jika Anda mengelola VPS Linux dan ingin aaPanel mengelola web/database/reverse proxy.
2. **Opsi 2: Berjalan di Dalam VM bhyve di pfSense**
   pfSense/FreeBSD mendukung virtualisasi bawaan `bhyve`. Anda dapat membuat VM Linux ringan di dalam pfSense menggunakan `setup-aapanel-bhyve.sh`, lalu menginstal aaPanel di dalam VM tersebut dengan port forwarding dari pfSense.
