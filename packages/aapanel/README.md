# KVM / Bhyve Hypervisor & aaPanel Integration untuk pfSense

Modul Virtual Machine Manager resmi untuk pfSense berbasis FreeBSD Hypervisor (bhyve/KVM) dengan antarmuka WebGUI modern, kontrol Virtual Machine (VM), dan **aaPanel** sebagai VM bawaan default sistem.

---

## 🚀 Fitur Utama

1. **Virtual Machine Manager Sesuai Standar pfSense**:
   - Terintegrasi langsung pada WebGUI pfSense: **Services > Virtual Machines (KVM)** (`/services_virtual.php`).
   - Manajemen Virtual Machine lengkap: status running/stopped, vCPU, RAM, HDD, Virtual Ethernet (TAP), dan Bridge Mode.
   - Tombol kontrol interaktif: **Start**, **Stop**, **Restart**, dan **Edit**.
   - Kemampuan menambahkan Virtual Machine baru (**Add Virtual Machine**) dengan dukungan profile Linux (Ubuntu, Debian, CentOS, AlmaLinux), FreeBSD, Windows, atau OS kustom.

2. **aaPanel sebagai VM Bawaan Default (Protected & Persistent)**:
   - **aaPanel** disematkan sebagai Virtual Machine bawaan default sistem pfSense.
   - **Tidak dapat dihapus**: Tombol hapus dikunci dan diproteksi pada tingkat WebGUI serta backend daemon.
   - **Pengaturan yang dapat disesuaikan untuk aaPanel**:
     - **HDD**: Ukuran disk virtual (GB) dan lokasi path storage image disk.
     - **RAM**: Alokasi memori fisik (MB/GB).
     - **Virtual Ethernet**: Antarmuka virtual Ethernet (TAP interface).
     - **Bridge Mode**: Opsi bridging ke antarmuka fisik LAN pfSense sehingga VM berada di subnet yang sama dan mendapatkan IP via DHCP.
     - **vCPU**: Jumlah alokasi core prosesor.

3. **Hypervisor Bhyve & KVM Monitor**:
   - Mendukung hardware virtualization monitor Intel VT-x dan AMD-V via modul kernel FreeBSD `vmm.ko`.
   - Modul jaringan TAP virtual (`if_tap.ko`) dan network bridging (`if_bridge.ko`).
   - Serial virtual console (`nmdm.ko`).
   - CLI utility: `/usr/local/bin/kvm-manager` dan `/usr/local/bin/aapanel-pfsense`.
   - Service FreeBSD daemon: `/usr/local/etc/rc.d/kvm`.
   - Akses web terintegrasi aaPanel pada port `8888`.
