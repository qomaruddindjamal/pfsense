<a id="readme"></a>

# pfSense Custom Edition: WireGuard, Xray & Linux Takeover

<p align="center">
  <a href="#readme"><img src="https://img.shields.io/badge/%F0%9F%93%96%20README-Overview-0969da?style=for-the-badge" alt="README"></a>&nbsp;
  <a href="#codespaces"><img src="https://img.shields.io/badge/%E2%98%81%EF%B8%8F%20Codespaces-QEMU%20KVM-1f6feb?style=for-the-badge" alt="Codespaces"></a>&nbsp;
  <a href="#wireguard"><img src="https://img.shields.io/badge/%F0%9F%9B%A1%EF%B8%8F%20WireGuard-Client%20%26%20Server-2ea44f?style=for-the-badge" alt="WireGuard"></a>&nbsp;
  <a href="#xray"><img src="https://img.shields.io/badge/%E2%9A%A1%20Xray--core-Multi--Protocol-8957e5?style=for-the-badge" alt="Xray-core"></a>&nbsp;
  <a href="#aapanel"><img src="https://img.shields.io/badge/%F0%9F%8C%90%20aaPanel-Bhyve%20KVM-f59e0b?style=for-the-badge" alt="aaPanel"></a>&nbsp;
  <a href="LICENSE"><img src="https://img.shields.io/badge/%E2%9A%96%EF%B8%8F%20License-Apache--2.0-57606a?style=for-the-badge" alt="License"></a>
</p>

| [📖 **README**](#readme) | [☁️ **Codespaces**](#codespaces) | [🛡️ **WireGuard**](#wireguard) | [⚡ **Xray-core**](#xray) | [🌐 **aaPanel**](#aapanel) | [⚖️ **Apache-2.0 License**](LICENSE) |
| :---: | :---: | :---: | :---: | :---: | :---: |

---

Repository ini menyediakan kode sumber installer resmi dari Netgate/pfSense ISO, integrasi paket **WireGuard**, **Xray-core** (VLESS, VMess, Trojan, Socks5, TUN, Routing), **Speedtest** (Official Ookla CLI + WebGUI Tool & Widget), **Wifi Manager** (AP & Station), panduan **aaPanel KVM**, serta skrip otomatisasi untuk **mengganti / menimpa Linux VPS yang sedang berjalan menjadi pfSense**.

---

## 🚀 Fitur Utama

1. **Paket Instalasi Offline ke Dalam ISO**:
   - Seluruh 5 paket offline (`wireguard-pfsense.pkg`, `xray-pfsense.pkg`, `speedtest.pkg`, `wifi.pkg`, dan `kvm.pkg`) telah disisipkan ke dalam direktori offline installer (`/usr/local/share/packages/offline/`).
   - Hook instalasi otomatis disematkan pada `installer_source/usr/local/libexec/installer/pfSense-install` dan `pfSense-post-install` sehingga saat instalasi dari ISO selesai, seluruh paket otomatis terpasang tanpa memerlukan koneksi internet.
   - File ISO & USB bootable offline: `pfsense-custom-offline-installer.iso` (1.11 GB), `.img` (1.11 GB), dan `.img.gz` (681 MB).
   - Skrip pembuat citra & otomatisasi: `scripts/build_iso.sh` dan `scripts/auto_pilot.ps1`.

2. **Integrasi Menu WebGUI pfSense**:
   - **VPN > WireGuard** (`/vpn_wg.php`): Mengelola interface `wg0`, status handshake, generate keypair, konfigurasi peer, dan full routing.
   - **VPN > Xray-core** (`/vpn_xray.php`): Mengelola daemon Xray, kontrol status, editor `config.json` multi-protokol (VLESS, VMess, Trojan, Socks5, TUN, Routing), dan pemantau log error.
   - **Tools > Speedtest** (`/tools_speedtest.php`): Pengujian throughput bandwidth & latency multi-stream resmi Ookla & Python CLI, dukungan pengujian per-interface (WAN, LAN Outbound NAT, WireGuard VPN), riwayat pengujian, dan widget dashboard (`speedtest.widget.php`).
   - **Interfaces > Wifi** (`/interfaces_wifi.php`): Pengelolaan access point (AP), Virtual AP (VAP), scanning SSID, dan konfigurasi interface nirkabel.
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

<a id="codespaces"></a>

## ☁️ 2. Menjalankan & Menginstal pfSense di GitHub Codespaces

Anda dapat menjalankan pfSense langsung di dalam **GitHub Codespaces** menggunakan mesin virtual **QEMU / KVM** lengkap dengan antarmuka grafis berbasis web (**noVNC**) dan akses penuh ke **pfSense webConfigurator** tanpa memerlukan komputer lokal berspesifikasi tinggi.

### 🌟 Keunggulan di GitHub Codespaces:
- **Akselerasi Hardware KVM**: Codespaces mendukung virtualisasi `/dev/kvm` secara langsung dengan prosesor AMD EPYC / Intel Xeon 2 vCPU dan RAM 8GB.
- **Port Forwarding Otomatis & Aman**: Akses GUI dan konsol dapat dibuka langsung via browser menggunakan domain resmi HTTPS GitHub (`*.app.github.dev`).
- **Instalasi Mandiri & Otomatis**: Dilengkapi dengan skrip manajemen `scripts/pfsense-codespaces.sh` dan modul instalasi cepat `pfSense-install`.

---

### 🚀 Cara Menjalankan (Pilih Salah Satu Metode):

#### ⚡ Opsi 1: Paling Cepat & Instan (Menggunakan Rilis `img.gz` ~30 Detik)
Mengunduh citra resmi pfSense terkompresi dari [GitHub Releases](https://github.com/qomaruddindjamal/pfsense/releases/tag/pfSense) langsung ke virtual disk:
```bash
# 1. Unduh dan konversi citra rilis otomatis
bash scripts/pfsense-codespaces.sh setup-image

# 2. Jalankan pfSense langsung dari disk
bash scripts/pfsense-codespaces.sh start
```

---

#### 🛠️ Opsi 2: Kustom ISO & Otomatisasi `pfSense-install` (~1,5 Menit)
Membangun file ISO langsung dari pohon kode sumber `installer_source` dan mengeksekusi installer otomatis:
```bash
# 1. Siapkan dependensi, virtual disk, dan bangun ISO lokal
bash scripts/pfsense-codespaces.sh setup

# 2. Jalankan VM dalam Mode Installer (Boot dari ISO)
bash scripts/pfsense-codespaces.sh install
```

> **Catatan Otomatisasi `pfSense-install`:**
> - Installer secara instan mendeteksi disk virtual VirtIO (`/dev/vtbd0`), memformat ZFS pool, memasang kernel, konfigurasi default, dan menyisipkan seluruh 5 paket kustom offline (`wireguard`, `xray`, `speedtest`, `wifi`, `kvm`).
> - Setelah instalasi selesai dan VM otomatis reboot, jalankan perintah operasional:
>   ```bash
>   bash scripts/pfsense-codespaces.sh start
>   ```

---

### 🌐 Akses Layanan di Browser (GitHub Codespaces Port Forwarding)

Skrip otomatis telah dilengkapi dengan **Nginx Reverse Proxy** di port `8443` & `8080` untuk menjembatani protokol HTTP Codespaces ke HTTPS pfSense dan menginjeksi header `Host: localhost`, sehingga **bebas dari error `400 Bad Request` maupun `DNS Rebind Attack`**.

| Layanan | Port Internal | Port Codespaces | Tautan Akses Browser | Keterangan |
| :--- | :---: | :---: | :--- | :--- |
| **🌐 pfSense WebGUI** | `443` (HTTPS) | **`8443`** | `https://<codespace-name>-8443.app.github.dev/` | Dashboard manajemen utama pfSense |
| **📺 noVNC Web Console** | `5900` (VNC) | **`6080`** | `https://<codespace-name>-6080.app.github.dev/vnc.html` | Layar visual monitor VM interaktif |
| **🌐 Web HTTP (Redirect)**| `80` (HTTP) | **`8080`** | `https://<codespace-name>-8080.app.github.dev/` | Port 80 HTTP pfSense |
| **🔑 SSH pfSense** | `22` (SSH) | **`22222`** | `ssh -p 22222 admin@localhost` | Port SSH *(admin / pfsense)* |

> **Login Default pfSense:**
> - **Username**: `admin`
> - **Password**: `pfsense`
> - **IP WAN (NAT Forwarding)**: `10.0.2.15/24` (Port 8443 / 8080 / 22222)
> - **IP LAN (Virtual Subnet)**: `192.168.1.1/24`

---

### 🛠️ Perintah Tambahan `pfsense-codespaces.sh`:
```bash
bash scripts/pfsense-codespaces.sh status      # Cek status VM, server noVNC, dan URL
bash scripts/pfsense-codespaces.sh stop        # Hentikan VM dan noVNC
bash scripts/pfsense-codespaces.sh restart     # Muat ulang VM dari disk
bash scripts/pfsense-codespaces.sh screenshot  # Ambil screenshot konsol ke /tmp/pfsense_screenshot.png
```

---

## 📦 3. Pemasangan Paket Berformat `.pkg` di pfSense (Rekomendasi Cepat)

> **💡 Catatan Penting:** Jika Anda menginstal pfSense menggunakan media **Custom Offline Installer (.iso / .img)** dari repository ini, seluruh paket di bawah ini **sudah otomatis terpasang secara offline**.

Bagi Anda yang sudah memiliki instalasi pfSense yang sedang berjalan, paket dapat dipasang langsung melalui konsol shell pfSense:

```sh
# 1. Pasang WireGuard (.pkg)
pkg add https://raw.githubusercontent.com/qomaruddindjamal/pfsense/main/installer_source/packages/All/wireguard-pfsense.pkg

# 2. Pasang Xray-core (.pkg) (VLESS, VMess, Trojan, Socks5, TUN, Routing)
pkg add https://raw.githubusercontent.com/qomaruddindjamal/pfsense/main/installer_source/packages/All/xray-pfsense.pkg

# 3. Pasang Mesin Virtual + aaPanel (.pkg)
pkg add https://raw.githubusercontent.com/qomaruddindjamal/pfsense/main/installer_source/packages/All/kvm.pkg

# 4. Pasang Speedtest Tool (.pkg) (Ookla Native + GitHub CLI)
pkg add https://raw.githubusercontent.com/qomaruddindjamal/pfsense/main/installer_source/packages/All/speedtest.pkg

# 5. Pasang Wifi Manager (.pkg) (AP, VAP & Client Scanner)
pkg add https://raw.githubusercontent.com/qomaruddindjamal/pfsense/main/installer_source/packages/All/wifi.pkg
```

Setelah paket terpasang, gunakan CLI bawaan masing-masing:
- **WireGuard**: `wireguard-manager {start|stop|restart|status|genkey}`
- **Xray-core**: `xray-control {start|stop|restart|status|test|version}` atau `service xray start`
- **Mesin Virtual / aaPanel**: `aapanel-pfsense {setup-bhyve|install-linux|status}`
- **Speedtest**: `speedtest` atau akses WebGUI pada menu **Tools > Speedtest**
- **Wifi Manager**: `wifi-manager {scan|status|connect}` atau menu **Interfaces > Wifi**

---

## 🛠️ 4. Pemasangan Manual / Script di pfSense

```sh
# Clone repository ini di pfSense
pkg install -y git curl
git clone https://github.com/qomaruddindjamal/pfsense.git /root/pfsense_repo
cd /root/pfsense_repo

# Jalankan skrip setup all-in-one
sh scripts/setup-pfsense-all.sh
```

Atau pasang per paket individual:

```sh
# A. WireGuard
pkg add /root/pfsense_repo/installer_source/packages/All/wireguard-pfsense.pkg

# B. Xray-core
pkg add /root/pfsense_repo/installer_source/packages/All/xray-pfsense.pkg

# C. Mesin Virtual KVM / aaPanel
pkg add /root/pfsense_repo/installer_source/packages/All/kvm.pkg

# D. Speedtest
pkg add /root/pfsense_repo/installer_source/packages/All/speedtest.pkg

# E. Wifi Manager
pkg add /root/pfsense_repo/installer_source/packages/All/wifi.pkg
```

---

<a id="wireguard"></a>

## 🛡️ 5. Panduan Lengkap: Membuat & Menghubungkan WireGuard Client di pfSense

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

### 🔀 Konfigurasi Routing & Outbound NAT dengan WireGuard di pfSense

Setelah tunnel WireGuard aktif dan berhasil melakukan handshake, Anda dapat mengarahkan lalu lintas data klien LAN (**Policy-Based Routing**) serta mengonfigurasi **Outbound NAT (Masquerade)** agar klien di jaringan lokal pfSense dapat mengakses internet atau jaringan kantor pusat melalui VPS WireGuard.

#### 1. Mengapa Perlu Assign Interface & Outbound NAT?
- Secara default, paket WireGuard di pfSense berjalan sebagai grup antarmuka (`WireGuard`).
- Untuk membuat Gateway pengarah rute (**Routing Gateway**) dan menerapkan aturan NAT khusus, antarmuka `tun_wg0` harus di-assign secara resmi ke dalam daftar interface pfSense (misalnya menjadi `WGVPN`).
- **Outbound NAT** memastikan paket dari subnet LAN (misal `192.168.1.0/24`) diterjemahkan menjadi IP tunnel pfSense (`10.10.99.2`) saat melintasi tunnel, sehingga server tujuan tidak memerlukan routing balik ke subnet internal LAN Anda.

---

#### 2. Langkah-Langkah Setting di pfSense (WebGUI)

##### Langkah A: Assign Interface WireGuard (`tun_wg0`)
1. Buka menu **Interfaces > Assignments**.
2. Pada baris paling bawah **Available network ports**, pilih `tun_wg0 (WireGuard Tunnel)` lalu klik tombol **+ Add**.
3. Antarmuka baru bernama `OPT1` (atau nama berikutnya) akan muncul. Klik pada nama `OPT1` tersebut untuk mengedit:
   - **Enable**: Centang `Enable interface`.
   - **Description**: Ubah menjadi `WGVPN` (atau nama lain yang mudah dikenali).
   - **IPv4 Configuration Type**: Pilih `Static IPv4`.
   - **IPv4 Address**: Masukkan IP Client tunnel: `10.10.99.2` dengan prefix `/24`.
   - Klik **Save** dan klik **Apply Changes**.

##### Langkah B: Tambahkan Gateway WireGuard
1. Buka menu **System > Routing > Gateways**.
2. Klik tombol **+ Add**:
   - **Disabled**: Jangan dicentang.
   - **Interface**: Pilih `WGVPN`.
   - **Address Family**: `IPv4`.
   - **Name**: `WGVPN_GW`.
   - **Gateway**: Masukkan IP tunnel server remote, yaitu `10.10.99.1`.
   - **Monitor IP**: Masukkan `10.10.99.1` (untuk memantau latensi dan packet loss otomatis via dpinger).
   - **Description**: `WireGuard VPS Gateway`.
3. Klik **Save** dan klik **Apply Changes**.
4. Cek status di menu **Status > Gateways**: Gateway `WGVPN_GW` harus berstatus **Online** dengan delay rendah (~16-19 ms) dan loss `0.0%`.

##### Langkah C: Konfigurasi Outbound NAT (Hybrid Mode)
1. Buka menu **Firewall > NAT > Outbound**.
2. Ubah opsi mode dari *Automatic outbound NAT* menjadi **Hybrid Outbound NAT rule generation** (mode ini tetap mempertahankan NAT WAN default sembari mengizinkan penambahan rule manual).
3. Klik **Save** di bagian atas (jangan lupa klik **Apply Changes** jika muncul).
4. Di bagian tabel **Mappings**, klik tombol **Add** (ikon panah ke atas untuk menaruh rule di urutan pertama):
   - **Interface**: Pilih `WGVPN`.
   - **Address Family**: `IPv4`.
   - **Protocol**: `any`.
   - **Source**: Pilih Type `Network`, lalu isi subnet LAN Anda: `192.168.1.0` / `24` (atau pilih alias `LAN subnets`).
   - **Destination**: `Any`.
   - **Translation Address**: Pilih `Interface Address`.
   - **Description**: `NAT Outbound LAN ke WireGuard VPS`.
5. Klik **Save** dan klik tombol **Apply Changes**.

##### Langkah D: Konfigurasi Routing (Pilih Skenario Anda)

* **Skenario 1: Full Tunnel (Semua Internet Klien LAN Lewat WireGuard)**
  1. Masuk ke **VPN > WireGuard > Peers** -> Edit peer VPS Server -> Pastikan **Allowed IPs** mencakup `0.0.0.0/0`.
  2. Buka menu **Firewall > Rules > LAN**.
  3. Edit rule default `Default allow LAN to any rule` (atau buat rule baru di atasnya untuk IP/klien tertentu):
     - Scroll ke bawah dan klik tombol **Display Advanced**.
     - Cari opsi **Gateway**: Ubah dari `default` menjadi **`WGVPN_GW - 10.10.99.1`**.
     - Klik **Save** dan klik **Apply Changes**.
  4. Seluruh trafik dari LAN sekarang akan diarahkan keluar melalui tunnel WireGuard menuju VPS.

* **Skenario 2: Split Tunnel / Static Route (Hanya Subnet Kantor / Server Tertentu)**
  1. Jika hanya ingin menghubungkan pfSense ke subnet spesifik di balik server VPS (misal `10.10.77.0/24` atau `192.168.88.0/24`):
  2. Masuk ke **System > Routing > Static Routes**.
  3. Klik tombol **+ Add**:
     - **Destination network**: Masukkan subnet tujuan, misal `10.10.77.0` / `24`.
     - **Gateway**: Pilih **`WGVPN_GW - 10.10.99.1`**.
     - **Description**: `Route ke Subnet Remote via WireGuard`.
  4. Klik **Save** dan klik **Apply Changes**.

---

#### 3. Konfigurasi Sisi Server (MikroTik / Linux VPS)
Agar paket yang diteruskan dari pfSense dapat keluar ke internet dari VPS:

* **Pada MikroTik CHR VPS**:
  Pastikan ada rule masquerade pada interface internet (biasanya `ether1`):
  ```routeros
  /ip firewall nat add chain=srcnat out-interface=ether1 action=masquerade comment="Masquerade WireGuard pfSense"
  ```
* **Pada Linux VPS (Ubuntu/Debian)**:
  Aktifkan IP forwarding dan iptables masquerade:
  ```bash
  sysctl -w net.ipv4.ip_forward=1
  iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE
  ```

---

#### 4. Pengujian & Verifikasi Routing & NAT
1. **Verifikasi Gateway di pfSense**:
   Buka **Status > Gateways**: Pastikan `WGVPN_GW` berstatus `Online` dengan RTT ~16-19ms.
2. **Verifikasi Aturan NAT Aktif**:
   Buka shell pfSense dan jalankan:
   ```sh
   pfctl -sn | grep tun_wg0
   ```
   Output akan menunjukkan aturan NAT aktif:
   ```text
   nat on tun_wg0 inet from 192.168.1.0/24 to any -> 10.10.99.2 port 1024:65535
   ```
3. **Uji Jalur Rute (Traceroute) dari Komputer Klien**:
   Jalankan traceroute ke alamat publik (contoh: `8.8.8.8`):
   ```cmd
   tracert -d 8.8.8.8
   ```
   Lompatan pertama adalah gateway LAN pfSense (`192.168.1.1`), dan lompatan berikutnya langsung ke IP tunnel WireGuard VPS (`10.10.99.1`).

---

### 🔧 Tips & Penyelesaian Masalah (Troubleshooting)

| Gejala | Penyebab Umum | Solusi |
| :--- | :--- | :--- |
| **Tidak ada Handshake** (*No handshake*) | 1. Port UDP di server tertutup firewall.<br>2. Endpoint IP atau Port salah.<br>3. Public Key tertukar atau salah salin. | 1. Buka port UDP pada firewall VPS/Cloud provider.<br>2. Cek kembali IP dan port endpoint server.<br>3. Pastikan Public Key pfSense didaftarkan di server, dan Public Key server didaftarkan di pfSense. |
| **Handshake ada, tapi Ping RTO / Timeout** | 1. Firewall pfSense memblokir paket masuk.<br>2. Allowed IPs di server tidak mencakup IP pfSense.<br>3. Firewall di server memblokir ICMP. | 1. Pastikan rule **Pass** pada **Firewall > Rules > WireGuard** sudah dibuat dan diapply.<br>2. Cek `allowed-address` di server apakah sudah mencakup `10.10.99.2/32`.<br>3. Cek firewall chain input/forward di server. |
| **Koneksi terputus setelah beberapa menit** | NAT timeout pada router/modem ISP sebelum pfSense. | Pastikan kolom **Persistent Keepalive** diisi `25` detik pada konfigurasi Peer di pfSense. |
| **Koneksi lambat atau web tertentu tidak terbuka** | Masalah Fragmentasi MTU paket. | Turunkan MTU pada Tunnel pfSense menjadi `1420` atau `1360` (terutama pada koneksi PPPoE). |

---

<a id="xray"></a>

## ⚡ 6. Panduan & Konfigurasi Xray-core di pfSense

Paket Xray-core di pfSense memungkinkan router bertindak sebagai VPN gateway multi-protokol dengan bypass sensor DPI dan routing pintar (GeoIP/GeoSite).

### 🚀 Protokol yang Didukung
- **VLESS** (Port `443`): Protokol generasi terbaru berlatensi ultra-rendah dengan XTLS / Reality.
- **VMess** (Port `8443`): Kompatibel dengan WebSocket (WS) + TLS untuk tunneling melalui CDN (Cloudflare).
- **Trojan** (Port `9443`): Menyamarkan trafik VPN sebagai lalu lintas HTTPS resmi.
- **Socks5 Inbound** (Port `10808`): Proxy lokal untuk klien jaringan LAN.
- **TUN / Dokodemo-door** (Port `12345`): Mode transparan proxy langsung di tingkat kernel/firewall pfSense.

### 🖥️ Pengelolaan via WebGUI pfSense
1. Akses menu **VPN > Xray-core** (`/vpn_xray.php`).
2. Terdapat kontrol status daemon, editor JSON konfigurasi interaktif, dan pemantau error log secara real-time.

### 💻 Pengelolaan via CLI pfSense
- File konfigurasi utama: `/usr/local/etc/xray/config.json`
- Menjalankan / menghentikan layanan:
  ```sh
  service xray start     # Menjalankan Xray daemon
  service xray status    # Mengecek status aktif
  service xray restart   # Memuat ulang konfigurasi
  service xray stop      # Menghentikan layanan
  ```
- Utilitas helper: `xray-control {start|stop|restart|status|test|version}`

---

<a id="aapanel"></a>

## 🌐 7. Integrasi aaPanel

> **PENTING Mengenai Arsitektur Sistem:**
> - **pfSense** berbasis **FreeBSD** dan telah memiliki antarmuka WebGUI bawaan lengkap (Nginx + PHP) untuk routing, firewall, NAT, dan VPN.
> - **aaPanel** dibuat khusus untuk distribusi **Linux** (Debian, Ubuntu, CentOS) dengan ketergantungan pada `systemd` dan `glibc`.
> - **Opsi yang tersedia:**
>   1. Jika menggunakan VPS Linux terpisah: Pasang aaPanel langsung pada VPS Linux tersebut.
>   2. Jika ingin menjalankan aaPanel di dalam mesin pfSense: Gunakan modul virtualisasi dari paket `kvm.pkg` via CLI `aapanel-pfsense setup-bhyve` atau `kvm-manager`.

---

## 📂 Struktur Direktori Repository

```
├── .gitignore                          # Filter file besar (ISO/IMG) & konfigurasi pengabaian Git
├── LICENSE                             # Lisensi Apache-2.0
├── README.md                           # Dokumentasi utama proyek
│
├── installer_source/                   # Kode sumber installer teroptimasi (Pohon Media ISO)
│   ├── boot/                           # Sektor bootloader BIOS (cdboot/isoboot), UEFI (efi.img) & Lua scripts
│   ├── etc/                            # Konfigurasi sistem startup pfSense & dhclient
│   ├── packages/                       # Paket instalasi runtime FreeBSD, kernel pfSense, & paket kustom
│   │   └── All/                        # Berkas paket .pkg dan partisi pfSense-base terpisah
│   ├── usr/libexec/bsdinstall/         # Modul instalasi sistem FreeBSD (auto, pfSense-install, zfsboot)
│   ├── usr/local/libexec/installer/    # Shell installer pfSense offline (run-installer.sh, pfSense-installer.sh)
│   ├── usr/local/share/packages/offline/ # Symlink terpadu ke paket offline kustom (wireguard, xray, kvm, wifi, speedtest)
│   └── usr/local/www/web-installer/    # Web installer UI Netgate
│
└── scripts/                            # Skrip otomatisasi & migrasi publik
    ├── build_pkg.py                    # Script pembuat paket .pkg
    ├── remaster_iso.sh                 # Script remaster ISO offline
    ├── pfsense-codespaces.sh           # Script otomatisasi QEMU/KVM & noVNC di GitHub Codespaces
    ├── install-pfsense-from-linux.sh   # Skrip takeover menimpa Linux ke pfSense
    ├── setup-pfsense-all.sh            # Skrip otomatis pasang seluruh paket kustom pfSense
    └── config.xml.template             # Template konfigurasi pfSense otomatis
```

---

## 👨‍💻 Kontributor & Lisensi
- Dikelola oleh: **[qomaruddindjamal](https://github.com/qomaruddindjamal)**
- Lisensi: Apache License 2.0
