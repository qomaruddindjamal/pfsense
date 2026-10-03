# FreeBSD / pfSense Packages (.pkg)

Direktori ini berisi paket biner resmi berformat **`.pkg`** (FreeBSD pkg-ng standar) yang dapat diinstal langsung di sistem pfSense menggunakan perintah `pkg add`.

## Daftar Paket
1. **`wireguard-pfsense.pkg`**:
   - Berisi modul kernel WireGuard, template konfigurasi interface `wg0.conf.example`, service startup daemon, dan CLI `wireguard-manager`.
2. **`xray-pfsense.pkg`**:
   - Berisi binary asli FreeBSD 64-bit `xray`, database `geoip.dat` dan `geosite.dat`, konfigurasi multi-protokol (VLESS + Reality, VMess + WS, Trojan, Socks5, TUN, Routing), service daemon `/usr/local/etc/rc.d/xray`, dan CLI `xray-control`.
3. **`kvm.pkg`**:
   - Berisi mesin virtual KVM / bhyve & container Linux yang di dalamnya sudah terpasang sistem **aaPanel**, utilitas manajemen `aapanel-pfsense`, konfigurasi Virtual-Ethernet (`tap0`/`bridge0`), serta service autostart saat pfSense boot.

5. **`speedtest.pkg`**:
   - Berisi Ookla Official CLI 64-bit (`speedtest`), Python `speedtest-cli` dari GitHub, halaman WebGUI Speedtest (`tools_speedtest.php`), Dashboard Widget (`speedtest.widget.php`), serta integrasi menu **Tools > Speedtest**.

---

## Cara Instalasi di pfSense

### 1. Instalasi Otomatis via URL (Langsung dari GitHub)
Masuk ke terminal / SSH pfSense (pilih menu `8) Shell`), lalu jalankan:

```sh
# Instal WireGuard (.pkg)
pkg add https://raw.githubusercontent.com/qomaruddindjamal/pfsense/main/packages/pkg/wireguard-pfsense.pkg

# Instal Xray-core (.pkg)
pkg add https://raw.githubusercontent.com/qomaruddindjamal/pfsense/main/packages/pkg/xray-pfsense.pkg

# Instal Mesin Virtual + aaPanel (.pkg)
pkg add https://raw.githubusercontent.com/qomaruddindjamal/pfsense/main/packages/pkg/kvm.pkg

# Instal Speedtest (.pkg)
pkg add https://raw.githubusercontent.com/qomaruddindjamal/pfsense/main/packages/pkg/speedtest.pkg
```

### 2. Instalasi dari File Lokal (.pkg)
Jika Anda sudah menyalin file `.pkg` ke sistem pfSense:

```sh
pkg add wireguard-pfsense.pkg
pkg add xray-pfsense.pkg
pkg add kvm.pkg
pkg add speedtest.pkg
```

---

## Perintah Pengelolaan Setelah Terpasang

### WireGuard
```sh
wireguard-manager genkey   # Membuat private/public key baru
wireguard-manager start    # Menjalankan interface WireGuard (wg-quick up wg0)
wireguard-manager status   # Menampilkan status handshake dan transfer
wireguard-manager stop     # Mematikan interface
```

### Xray-core
```sh
xray-control test          # Memvalidasi syntax file konfigurasi
xray-control start         # Menjalankan service Xray
xray-control status        # Memeriksa status proses Xray
xray-control restart       # Restart Xray (setelah edit config.json)
xray-control version       # Cek versi Xray
```

### aaPanel
```sh
aapanel-pfsense setup-bhyve # Memuat modul virtualisasi FreeBSD bhyve di pfSense
aapanel-pfsense status      # Cek status modul hypervisor
```

### Speedtest
```sh
# Jalankan pengujian kecepatan Ookla via CLI
speedtest

# Jalankan pengujian via interface tertentu (misal LAN 192.168.1.1 untuk uji Outbound NAT)
speedtest -i 192.168.1.1

# Atau gunakan WebGUI di menu: Tools > Speedtest
```

