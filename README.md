# pfSense Custom Edition: WireGuard, Xray & Linux Takeover (CHR-Style)

Repository ini menyediakan kode sumber installer resmi dari Netgate/pfSense ISO, integrasi paket **WireGuard** dan **Xray-core** (VLESS, VMess, Trojan, Socks5, TUN, Routing), panduan **aaPanel**, serta skrip otomatisasi untuk **mengganti / menimpa Linux VPS yang sedang berjalan menjadi pfSense** (seperti metode instalasi MikroTik CHR via `dd`).

---

## 🚀 Fitur Utama

1. **Kode Sumber Installer pfSense (dari ISO)**:
   - Diekstrak langsung dari `netgate-installer-amd64.iso`.
   - Modul `bsdinstall`, `pfSense-installer.sh`, Web-Installer UI, konfigurasi Lua bootloader, dan konfigurasi sistem.
2. **WireGuard VPN**:
   - Modul kernel FreeBSD `wireguard-kmod` & tools `wg-quick`.
   - Templat konfigurasi server & peer siap pakai.
3. **Xray-core (Multi-Protocol & Transparent Routing)**:
   - Mendukung **VLESS** (dengan Reality / XTLS).
   - Mendukung **VMess** (dengan WebSocket & TLS).
   - Mendukung **Trojan** (dengan TLS).
   - Mendukung **Socks5** (inbound & outbound).
   - Mendukung antarmuka **TUN / Dokodemo-door** untuk proxy transparan seluruh trafik router.
   - Aturan **Routing** tingkat lanjut (GeoIP & GeoSite: pemisahan trafik lokal, proxy, blokir iklan).
   - Layanan FreeBSD rc.d daemon (`service xray start|stop|status`).
4. **aaPanel Integration**:
   - Skrip instalasi aaPanel resmi untuk Linux VPS.
   - Penjelasan arsitektur FreeBSD vs Linux serta panduan menjalankan aaPanel di dalam VM `bhyve` di pfSense.
5. **Linux-to-pfSense Reinstall / Takeover Script (CHR-Style)**:
   - Menimpa OS Linux aktif (Ubuntu / Debian / CentOS / AlmaLinux / Rocky) secara otomatis tanpa perlu membuka ISO via panel provider.

---

## ⚡ 1. Cara Mengganti Linux Menjadi pfSense (Seperti MikroTik CHR)

Pada VPS Linux aktif Anda (masuk via SSH sebagai root), jalankan perintah 1-baris berikut:

```bash
bash <(curl -sSL https://raw.githubusercontent.com/qomaruddindjamal/pfsense/main/scripts/install-pfsense-from-linux.sh)
```

### Yang dilakukan skrip ini secara otomatis:
1. Mendeteksi antarmuka jaringan utama (interface, IPv4, gateway, netmask, MAC).
2. Mendeteksi disk utama VPS (`/dev/vda`, `/dev/sda`, atau `/dev/nvme0n1`).
3. Mengunduh dan menulis image pfSense langsung ke disk utama via `dd`.
4. Melakukan sinkronisasi disk dan memicu reboot paksa hardware via **Linux SysRq Trigger** (`echo b > /proc/sysrq-trigger`).
5. VPS akan langsung boot masuk ke sistem pfSense!

> **Login Default pfSense setelah reboot:**
> - **Username**: `admin`
> - **Password**: `pfsense`
> - **WebGUI**: Akses IP VPS melalui browser di port `80` atau `443`.

---

## 🛡️ 2. Pemasangan WireGuard & Xray di pfSense

Setelah masuk ke console shell pfSense (via SSH atau Serial/VNC menu opsi `8) Shell`), jalankan instalasi all-in-one:

```sh
# Clone repository ini di pfSense
pkg install -y git curl
git clone https://github.com/qomaruddindjamal/pfsense.git /root/pfsense_repo
cd /root/pfsense_repo

# Jalankan skrip setup all-in-one
sh scripts/setup-pfsense-all.sh
```

Atau pasang per modul:

### A. WireGuard
```sh
sh packages/wireguard/install-wireguard.sh
```
- File konfigurasi: `/usr/local/etc/wireguard/wg0.conf`
- Menjalankan interface: `wg-quick up wg0`

### B. Xray-core
```sh
sh packages/xray/install-xray.sh
```
- Protokol aktif: **VLESS** (port 443), **VMess** (port 8443), **Trojan** (port 9443), **Socks5** (port 10808), **TUN/Dokodemo** (port 12345).
- File konfigurasi: `/usr/local/etc/xray/config.json`
- Kelola layanan:
  ```sh
  service xray start
  service xray status
  service xray restart
  service xray stop
  ```

---

## 🌐 3. Integrasi aaPanel

> **PENTING Mengenai Arsitektur Sistem:**
> - **pfSense** berbasis **FreeBSD** dan telah memiliki antarmuka WebGUI bawaan lengkap (Nginx + PHP) untuk routing, firewall, NAT, dan VPN.
> - **aaPanel** dibuat khusus untuk distribusi **Linux** (Debian, Ubuntu, CentOS) dengan ketergantungan pada `systemd` dan `glibc`.
> - **Opsi yang tersedia:**
>   1. Jika menggunakan VPS Linux terpisah: Jalankan `bash packages/aapanel/install-aapanel-linux.sh`.
>   2. Jika ingin menjalankan aaPanel di dalam mesin pfSense: Gunakan hypervisor bawaan pfSense yaitu **bhyve** melalui skrip `sh packages/aapanel/setup-aapanel-bhyve.sh` untuk menjalankan VM Linux kecil yang memuat aaPanel.

---

## 📂 Struktur Direktori Repository

```
├── .gitignore                          # Filter file besar (ISO/RAW images)
├── LICENSE                             # Lisensi Apache-2.0
├── README.md                           # Dokumentasi utama (file ini)
├── installer_source/                   # Kode sumber installer yang diekstrak dari ISO
│   ├── boot/lua/                       # Skrip Lua bootloader pfSense
│   ├── etc/                            # Konfigurasi sistem startup pfSense
│   ├── usr/libexec/bsdinstall/         # Modul bsdinstall FreeBSD untuk pfSense
│   ├── usr/local/libexec/installer/    # Shell installer pfSense (pfSense-installer.sh, dll)
│   └── usr/local/www/web-installer/    # Web installer UI Netgate
├── packages/
│   ├── wireguard/                      # Skrip & konfigurasi WireGuard
│   │   ├── install-wireguard.sh
│   │   ├── wg0.conf.example
│   │   └── README.md
│   ├── xray/                           # Skrip & konfigurasi Xray-core
│   │   ├── install-xray.sh
│   │   ├── config.json                 # VLESS, VMess, Trojan, Socks5, TUN, Routing
│   │   ├── xray.rc.d                   # Service daemon FreeBSD
│   │   ├── pf-rules.conf               # Aturan redirect PF
│   │   └── README.md
│   └── aapanel/                        # Skrip & integrasi aaPanel
│       ├── install-aapanel-linux.sh
│       ├── setup-aapanel-bhyve.sh
│       └── README.md
└── scripts/
    ├── install-pfsense-from-linux.sh   # Skrip takeover menimpa Linux ke pfSense (CHR-Style)
    ├── setup-pfsense-all.sh            # Skrip otomatis pasang WireGuard & Xray
    └── config.xml.template             # Template konfigurasi pfSense otomatis
```

---

## 👨‍💻 Kontributor & Lisensi
- Dikelola oleh: **[qomaruddindjamal](https://github.com/qomaruddindjamal)**
- Lisensi: Apache License 2.0
