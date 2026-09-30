# pfSense Official Offline Package Repository (Local Mirror)

Repositori ini memuat salinan lengkap 218 paket resmi FreeBSD / pfSense (versi 2.9.0 / FreeBSD 16-CURRENT) hasil sinkronisasi dari instalasi resmi, serta paket kustom WireGuard, Xray-core, dan Virtual aaPanel.

---

## 1. Struktur Repositori

```text
packages/
├── meta.conf               # Metadata katalog resmi pkg-ng
├── packagesite.pkg         # Index katalog daftar seluruh paket (YAML)
├── data.pkg                # Ekstensi data katalog
├── join_pkgs.sh            # Skrip shell penggabung otomatis paket terbagi
├── join_pkgs.bat           # Skrip Windows penggabung otomatis
└── All/                    # Seluruh 217+ berkas paket biner .pkg
    ├── pfSense-2.9.0.pkg
    ├── pfSense-base-2.9.0.pkg.partaa  (Partisi 1/2)
    ├── pfSense-base-2.9.0.pkg.partab  (Partisi 2/2)
    ├── pfSense-kernel-pfSense-2.9.0.pkg
    ├── wireguard-pfsense.pkg
    ├── xray-pfsense.pkg
    ├── virtual.pkg
    └── ... (PHP 8.5 stack, Python 3.11/3.12, Unbound, StrongSwan, dll.)
```

---

## 2. Penggabungan Otomatis (Reassemble)

Karena batas unggah per berkas di GitHub adalah 100 MB, berkas `pfSense-base-2.9.0.pkg` (~115.9 MB) dibagi menjadi 2 berkas (`partaa` & `partab` masing-masing ~58 MB).

Untuk menggabungkannya kembali:
* **Di Linux / FreeBSD / macOS:**
  ```sh
  sh join_pkgs.sh
  # atau manual:
  cat All/pfSense-base-2.9.0.pkg.part* > All/pfSense-base-2.9.0.pkg
  ```
* **Di Windows:**
  Cukup klik ganda berkas `join_pkgs.bat`.

*Skrip instalasi pfSense (`pfSense-install` dan `pfSense-post-install`) secara otomatis menjalankan penggabungan ini saat mode offline dijalankan.*

---

## 3. Cara Mengaktifkan Repositori Offline di pfSense

Tambahkan berkas konfigurasi repositori lokal di `/usr/local/etc/pkg/repos/offline.conf`:

```conf
OfflineRepo: {
  url: "file:///packages",
  mirror_type: "NONE",
  enabled: true
}
```

Lalu nonaktifkan repositori online di `/etc/pkg/FreeBSD.conf` (`enabled: false`), dan jalankan:
```sh
pkg update
pkg install <nama-paket>
```
Sistem akan menginstal paket secara instan langsung dari folder lokal tanpa koneksi internet.
