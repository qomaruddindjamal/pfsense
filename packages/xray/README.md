# Xray-core untuk pfSense (FreeBSD)

Modul integrasi Xray-core lengkap untuk sistem pfSense / FreeBSD.

## Protokol yang Didukung
- **VLESS**: Dilengkapi dukungan XTLS & Reality (Port default: `443`)
- **VMess**: Dilengkapi transport WebSocket (WS) & TLS (Port default: `8443`, path: `/vmess-ws`)
- **Trojan**: TLS-encrypted proxy (Port default: `9443`)
- **Socks5**: Local proxy inbound (Port: `10808`)
- **TUN / Dokodemo-door**: Transparent proxy untuk mengarahkan seluruh trafik router / LAN (Port: `12345`)
- **Advanced Routing**: Pembagian rute otomatis berbasis domain & IP (GeoIP & GeoSite: bypass lokal, blokir iklan, filter trafik).

## Cara Instalasi
Jalankan perintah ini di shell pfSense:

```sh
sh /root/packages/xray/install-xray.sh
```

## Pengelolaan Layanan
Gunakan FreeBSD rc service:
```sh
service xray start      # Menjalankan Xray
service xray stop       # Menghentikan Xray
service xray restart    # Memuat ulang konfigurasi
service xray status     # Memeriksa status Xray
```

File konfigurasi berada di: `/usr/local/etc/xray/config.json`
File log berada di: `/var/log/xray/access.log` dan `/var/log/xray/error.log`
