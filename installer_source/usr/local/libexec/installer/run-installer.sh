#!/bin/sh
export TERM=xterm
export HOME=/root
export PATH=/sbin:/bin:/usr/sbin:/usr/bin:/usr/local/sbin:/usr/local/bin
/sbin/ldconfig -m /lib /usr/lib /usr/local/lib 2>/dev/null || true
if ! /usr/bin/pgrep -q pfSense-installer; then /usr/local/bin/cgi-fcgi -start -connect 127.0.0.1:9000 /usr/local/sbin/pfSense-installer 2>/dev/null || true; fi
if ! /usr/bin/pgrep -q nginx; then /usr/local/etc/rc.d/nginx onestart 2>/dev/null || /usr/local/sbin/nginx 2>/dev/null || true; fi

while :; do
    clear
    echo "=========================================================="
    echo "  pfSense Custom Offline Installer"
    echo "=========================================================="
    echo "Memulai antarmuka instalasi pfSense..."
    echo ""
    if [ -x /usr/local/libexec/installer/pfSense-installer.sh ]; then
        /usr/local/libexec/installer/pfSense-installer.sh
    else
        echo "ERROR: /usr/local/libexec/installer/pfSense-installer.sh tidak ditemukan!"
    fi
    echo ""
    echo "=========================================================="
    echo "  pfSense Offline Console & Recovery Menu"
    echo "=========================================================="
    echo "  1) Jalankan Ulang Installer (pfSense-installer.sh)"
    echo "  2) Buka Shell (/bin/sh)"
    echo "  3) Mulai Ulang / Reboot VM"
    echo "=========================================================="
    printf "Pilih opsi [1-3] (default 1): "
    read _ans
    case "${_ans}" in
        2)
            echo "Ketik exit untuk kembali ke menu installer."
            /bin/sh
            ;;
        3)
            /sbin/reboot
            ;;
        *)
            continue
            ;;
    esac
done
