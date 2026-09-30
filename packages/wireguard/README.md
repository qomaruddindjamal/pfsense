# WireGuard untuk pfSense

Modul ini menyediakan integrasi WireGuard VPN ke dalam sistem pfSense / FreeBSD.

## Fitur
- Mendukung modul kernel resmi FreeBSD (`wireguard-kmod`) untuk performa tinggi & latensi rendah.
- Dapat dikelola langsung via WebGUI pfSense (`VPN -> WireGuard`) jika paket GUI diaktifkan, atau via CLI `wg` dan `wg-quick`.
- Otomatis konfigurasi startup saat pfSense boot (`/etc/rc.conf.local`).

## Cara Instalasi di pfSense
Jalankan perintah berikut di console shell pfSense:

```sh
sh /root/packages/wireguard/install-wireguard.sh
```

Atau jalankan manual melalui WebGUI pfSense:
1. Masuk ke **System > Package Manager > Available Packages**.
2. Cari `WireGuard` lalu klik **Install**.
3. Akses menu **VPN > WireGuard** untuk membuat Tunnel dan menambah Peer/Klien.
