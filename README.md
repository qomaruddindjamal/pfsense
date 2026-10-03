# pfSense Custom Edition: WireGuard, Xray & Linux Takeover

Repository ini menyediakan kode sumber installer resmi dari Netgate/pfSense ISO, integrasi paket **WireGuard** dan **Xray-core** (VLESS, VMess, Trojan, Socks5, TUN, Routing), panduan **aaPanel**, serta skrip otomatisasi untuk **mengganti / menimpa Linux VPS yang sedang berjalan menjadi pfSense**.

---

## 🚀 Fitur Utama

1. **Paket Instalasi Offline ke Dalam ISO**:
   - Seluruh paket (`wireguard-pfsense.pkg`, `xray-pfsense.pkg`, dan `kvm.pkg`) telah disisipkan ke dalam direktori offline installer (`/usr/local/share/packages/offline/`).
   - Hook instalasi otomatis disematkan pada `installer_source/usr/local/libexec/installer/pfSense-post-install` sehingga saat instalasi dari ISO selesai, ketiga paket otomatis terpasang tanpa memerlukan koneksi internet.
   - Tersedia skrip remaster ISO: `scripts/remaster_iso.sh`.

2. **Integrasi Menu WebGUI pfSense**:
   - **VPN > WireGuard** (`/vpn_wg.php`): Mengelola interface `wg0`, status handshake, generate keypair, dan konfigurasi peer.
   - **VPN > Xray-core** (`/vpn_xray.php`): Mengelola daemon Xray, kontrol status, editor `config.json` multi-protokol (VLESS, VMess, Trojan, Socks5, TUN, Routing), dan pemantau log error.
   - **aaPanel Mandiri (Tanpa Menu di pfSense)**: Sesuai rancangan, aaPanel **TIDAK** ditampilkan di WebGUI pfSense karena aaPanel telah memiliki antarmuka WebGUI modern tersendiri (port 8888).

3. **WireGuard VPN**:
   - Modul kernel FreeBSD `wireguard-kmod` & tools `wg-quick`.
   - Templat konfigurasi server & peer siap pakai.

4. **Xray-core (Multi-Protocol & Transparent Routing)**:
   - Mendukung **VLESS** (dengan Reality / XTLS).
   - Mendukung **VMess** (dengan WebSocket & TLS).
   - Mendukung **Trojan** (dengan TLS).
   - Mendukung **Socks5** (inbound & outbound).
   - Mendukung antarmuka **TUN / Dokodemo-door** untuk proxy transparan seluruh trafik router.
   - Aturan **Routing** tingkat lanjut (GeoIP & GeoSite: pemisahan trafik lokal, proxy, blokir iklan).
   - Layanan FreeBSD rc.d daemon (`service xray start|stop|status`).

5. **Linux-to-pfSense Reinstall / Takeover Script**:
   - Menimpa OS Linux aktif (Ubuntu / Debian / CentOS / AlmaLinux / Rocky) secara otomatis tanpa perlu membuka ISO via panel provider.

---

## ⚡ 1. Cara Mengganti Linux Menjadi pfSense

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

## 📦 2. Pemasangan Paket Berformat `.pkg` di pfSense (Rekomendasi Cepat)

Anda dapat menginstal paket secara langsung di console shell pfSense menggunakan perintah `pkg add`:

```sh
# 1. Pasang WireGuard (.pkg)
pkg add https://raw.githubusercontent.com/qomaruddindjamal/pfsense/main/packages/pkg/wireguard-pfsense.pkg

# 2. Pasang Xray-core (.pkg) (VLESS, VMess, Trojan, Socks5, TUN, Routing)
pkg add https://raw.githubusercontent.com/qomaruddindjamal/pfsense/main/packages/pkg/xray-pfsense.pkg

# 3. Pasang Mesin Virtual + aaPanel (.pkg)
pkg add https://raw.githubusercontent.com/qomaruddindjamal/pfsense/main/packages/pkg/kvm.pkg
```

Setelah paket terpasang, gunakan CLI bawaan masing-masing:
- **WireGuard**: `wireguard-manager {start|stop|restart|status|genkey}`
- **Xray-core**: `xray-control {start|stop|restart|status|test|version}` atau `service xray start`
- **Mesin Virtual / aaPanel**: `aapanel-pfsense {setup-bhyve|install-linux|status}`

---

## 🛠️ 3. Pemasangan Manual / Script di pfSense

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

## 🛡️ 4. Panduan Lengkap: Membuat & Menghubungkan WireGuard Client di pfSense

Bagian ini memandu Anda menghubungkan pfSense sebagai **WireGuard Client (Peer)** ke sebuah WireGuard Server (misalnya: MikroTik CHR VPS, Ubuntu/Debian Linux VPS, atau router kantor pusat).

### 📐 Contoh Skenario / Topologi
- **Server WireGuard (MikroTik CHR / VPS Linux)**:
  - IP Publik / Domain: `103.93.162.168`
  - Port Listening UDP: `13235` (atau port pilihan Anda, misal `51820`)
  - IP Tunnel Server: `10.10.99.1/24`
  - Public Key Server: `r9T/01aMVJ0WI2v2PaODiKdUxg9eZCPtY/cCEmk0uBc=`
- **pfSense (Client / Site Office / Home Lab)**:
  - IP Tunnel Client: `10.10.99.2/24`
  - Listen Port: `51820`
  - MTU: `1420`
  - Keepalive: `25` detik

---

### 🖥️ Metode 1: Konfigurasi Melalui WebGUI pfSense (Disarankan)

#### Langkah 1: Aktifkan Service WireGuard
1. Buka browser dan login ke WebGUI pfSense (`https://<IP_PFSENSE>`).
2. Masuk ke menu **VPN > WireGuard > Settings**.
3. Centang opsi **Enable WireGuard**.
4. Klik **Save**.

#### Langkah 2: Buat Tunnel Baru (Client Interface)
1. Masuk ke tab **VPN > WireGuard > Tunnels**, klik **+ Add Tunnel**.
2. Isi formulir konfigurasi Tunnel:
   - **Enable**: Centang `Enable Tunnel`.
   - **Description**: Contoh: `Tunnel to VPS Server`.
   - **Listen Port**: Masukkan `51820` (atau kosongkan untuk acak).
   - **Interface Keys**:
     - Klik tombol **Generate** untuk membuat kunci baru secara otomatis.
     - **Private Key**: Akan terisi otomatis (rahasia pfSense).
     - **Public Key**: Salin kunci ini karena **harus didaftarkan pada server WireGuard**.
   - **Interface Addresses**:
     - Klik **+ Add Address**.
     - **Address**: `10.10.99.2`
     - **Subnet / Prefix**: `24`
     - **Description**: `Tunnel IPv4`
   - **MTU**: Masukkan `1420` (disarankan untuk kompatibilitas enkapsulasi paket).
3. Klik **Save Tunnel**.

#### Langkah 3: Tambahkan Peer (Server WireGuard)
1. Masuk ke tab **VPN > WireGuard > Peers**, klik **+ Add Peer**.
2. Isi formulir konfigurasi Peer:
   - **Enable**: Centang `Enable Peer`.
   - **Tunnel**: Pilih tunnel yang baru dibuat (`tun_wg0`).
   - **Description**: Contoh: `VPS CHR Server (103.93.162.168)`.
   - **Dynamic Endpoint**: **Jangan dicentang** (uncheck), karena server memiliki IP publik / host statis.
   - **Endpoint**: Masukkan IP publik atau hostname server, contoh: `103.93.162.168`.
   - **Endpoint Port**: Masukkan port listening server, contoh: `13235` (atau `51820`).
   - **Public Key**: Masukkan **Public Key milik Server WireGuard**.
   - **Allowed IPs**:
     - Tambahkan subnet yang diizinkan melintasi tunnel:
       - `10.10.99.0/24` (Subnet tunnel VPN).
       - (Opsional) Jika ingin mengakses subnet LAN di belakang server (misal `192.168.1.0/24`), tambahkan baris baru.
       - (Opsional) Jika ingin semua trafik internet dialihkan ke server: masukkan `0.0.0.0/0`.
   - **Persistent Keepalive**: Masukkan `25` (sangat penting jika pfSense berada di balik NAT / ISP rumahan agar sesi tunnel tidak ditutup oleh router upstream).
3. Klik **Save Peer**.
4. Setelah kembali ke daftar, klik tombol **Apply Changes** berwarna oranye di bagian atas layar.

#### Langkah 4: Buat Firewall Rule di pfSense
Agar pfSense mengizinkan lalu lintas data dan respons ping (ICMP) melintasi tunnel WireGuard:
1. Masuk ke menu **Firewall > Rules**.
2. Pilih tab **WireGuard** (Interface Group).
3. Klik **Add** (ikon panah ke atas) untuk membuat rule:
   - **Action**: `Pass`
   - **Interface**: `WireGuard`
   - **Address Family**: `IPv4`
   - **Protocol**: `Any`
   - **Source**: `Any`
   - **Destination**: `Any`
   - **Description**: `Allow all WireGuard traffic`
4. Klik **Save**, lalu klik tombol **Apply Changes**.

#### Langkah 5: Konfigurasi di Sisi Server (Wajib!)

Sebelum koneksi berjalan, server WireGuard harus mendaftarkan Public Key pfSense:

* **Jika Server menggunakan MikroTik RouterOS (CHR / Routerboard)**:
  ```routeros
  /interface/wireguard/peers/add interface=wg-pfsense \
      public-key="<PUBLIC_KEY_PFSENSE>" \
      allowed-address=10.10.99.2/32 \
      comment="pfSense Client Peer"
  ```
  *(Pastikan firewall MikroTik mengizinkan port UDP WireGuard pada chain `input`)*:
  ```routeros
  /ip firewall filter add chain=input action=accept protocol=udp dst-port=13235 comment="Allow WireGuard" place-before=1
  ```

* **Jika Server menggunakan Linux (Ubuntu/Debian `/etc/wireguard/wg0.conf`)**:
  ```ini
  [Peer]
  PublicKey = <PUBLIC_KEY_PFSENSE>
  AllowedIPs = 10.10.99.2/32
  ```
  Lalu muat ulang konfigurasi: `wg syncconf wg0 <(wg-quick strip wg0)`

#### Langkah 6: Uji & Verifikasi Koneksi
1. Masuk ke **Status > WireGuard** di WebGUI pfSense.
2. Periksa status Peer:
   - **Latest Handshake**: Menampilkan waktu aktif (contoh: *10 seconds ago*).
   - **Transfer**: Nilai data diterima (RX) dan dikirim (TX) terus bertambah.
3. Lakukan Ping Test:
   - Buka menu **Diagnostics > Ping**.
   - Hostname: `10.10.99.1` (IP tunnel server).
   - IP Protocol: `IPv4`.
   - Source Address: `WireGuard` atau `tun_wg0`.
   - Klik **Ping**: Hasil harus `0.0% packet loss` dengan latensi rendah (~16-18 ms).

---

### 💻 Metode 2: Konfigurasi Otomatis via Shell / CLI pfSense

Jika Anda mengonfigurasi pfSense langsung dari shell atau skrip otomatis:

1. Buat pasangan kunci (KeyPair):
   ```sh
   wg genkey | tee /etc/wireguard/client_private.key | wg pubkey > /etc/wireguard/client_public.key
   ```
2. Buat file konfigurasi `/usr/local/etc/wireguard/wg0.conf`:
   ```ini
   [Interface]
   PrivateKey = <ISI_DARI_client_private.key>
   Address = 10.10.99.2/24
   ListenPort = 51820
   MTU = 1420

   [Peer]
   PublicKey = <PUBLIC_KEY_MILIK_SERVER>
   Endpoint = 103.93.162.168:13235
   AllowedIPs = 10.10.99.0/24
   PersistentKeepalive = 25
   ```
3. Jalankan antarmuka tunnel:
   ```sh
   wg-quick up wg0
   ```
4. Cek status koneksi:
   ```sh
   wg show
   ping -c 4 10.10.99.1
   ```

---

### 🔧 Tips & Penyelesaian Masalah (Troubleshooting)

| Gejala | Penyebab Umum | Solusi |
| :--- | :--- | :--- |
| **Tidak ada Handshake** (*No handshake*) | 1. Port UDP di server tertutup firewall.<br>2. Endpoint IP atau Port salah.<br>3. Public Key tertukar atau salah salin. | 1. Buka port UDP pada firewall VPS/Cloud provider.<br>2. Cek kembali IP dan port endpoint server.<br>3. Pastikan Public Key pfSense didaftarkan di server, dan Public Key server didaftarkan di pfSense. |
| **Handshake ada, tapi Ping RTO / Timeout** | 1. Firewall pfSense memblokir paket masuk.<br>2. Allowed IPs di server tidak mencakup IP pfSense.<br>3. Firewall di server memblokir ICMP. | 1. Pastikan rule **Pass** pada **Firewall > Rules > WireGuard** sudah dibuat dan diapply.<br>2. Cek `allowed-address` di server apakah sudah mencakup `10.10.99.2/32`.<br>3. Cek firewall chain input/forward di server. |
| **Koneksi terputus setelah beberapa menit** | NAT timeout pada router/modem ISP sebelum pfSense. | Pastikan kolom **Persistent Keepalive** diisi `25` detik pada konfigurasi Peer di pfSense. |
| **Koneksi lambat atau web tertentu tidak terbuka** | Masalah Fragmentasi MTU paket. | Turunkan MTU pada Tunnel pfSense menjadi `1420` atau `1360` (terutama pada koneksi PPPoE). |

---

## 🌐 5. Integrasi aaPanel

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
│   ├── pkg/                            # Paket resmi siap pasang (.pkg)
│   │   ├── wireguard-pfsense.pkg       # Modul kernel & WebGUI WireGuard
│   │   ├── xray-pfsense.pkg            # Xray multi-protokol & WebGUI VPN
│   │   └── kvm.pkg                 # Mesin Virtual & aaPanel di dalamnya
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
    ├── build_pkg.py                    # Script pembuat paket .pkg
    ├── remaster_iso.sh                 # Script remaster ISO offline
    ├── install-pfsense-from-linux.sh   # Skrip takeover menimpa Linux ke pfSense
    ├── setup-pfsense-all.sh            # Skrip otomatis pasang WireGuard & Xray
    └── config.xml.template             # Template konfigurasi pfSense otomatis
```

---

## 👨‍💻 Kontributor & Lisensi
- Dikelola oleh: **[qomaruddindjamal](https://github.com/qomaruddindjamal)**
- Lisensi: Apache License 2.0
