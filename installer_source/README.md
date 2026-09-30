# pfSense / Netgate Installer Source Code

Folder ini berisi kode sumber modul instalasi yang diekstrak langsung dari file media instalasi resmi `netgate-installer-amd64.iso`.

## Struktur Direktori
1. **`boot/lua/`**:
   - Skrip Lua bootloader FreeBSD/pfSense (`brand-pfSense.lua`, `logo-pfSensebw.lua`, `menu.lua`, `config.lua`).
2. **`usr/local/libexec/installer/`**:
   - Mesin inti skrip installer pfSense (`pfSense-installer.sh`, `pfSense-installerd.sh`, `pfSense-post-install`, `pfSense-disk-part`, `pfSense-wan-setup`, dll.).
3. **`usr/libexec/bsdinstall/`**:
   - Modul `bsdinstall` FreeBSD yang telah dikustomisasi untuk instalasi pfSense (konfigurasi ZFS, partisi otomatis, konfigurasi jaringan).
4. **`usr/local/www/web-installer/`**:
   - Frontend Web Installer Netgate berbasis Web UI untuk proses setup melalui antarmuka browser.
5. **`etc/`**:
   - Template skrip startup sistem (`rc`, `rc.conf`, `defaults`, `devd`).
