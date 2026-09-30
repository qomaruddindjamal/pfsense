#
HOME=/root
export HOME
PATH=/sbin:/bin:/usr/sbin:/usr/bin:/usr/local/sbin:/usr/local/bin:~/bin
export PATH
TERM=${TERM:-xterm}
export TERM
PAGER=less
export PAGER

# set ENV to a file invoked each time sh is started for interactive use.
ENV=$HOME/.shrc; export ENV

# Query terminal size; useful for serial lines.
if [ -x /usr/bin/resizewin ] ; then /usr/bin/resizewin -z ; fi

/sbin/ldconfig -m /lib /usr/lib /usr/local/lib 2>/dev/null || true

PFSENSE_INSTALLER="/usr/local/libexec/installer/pfSense-installer.sh"

if [ -z "${NO_INSTALLER}" ] && [ -x "${PFSENSE_INSTALLER}" ]; then
	"${PFSENSE_INSTALLER}"
fi

while true; do
	echo ""
	echo "=========================================================="
	echo "  pfSense Offline Console & Recovery"
	echo "=========================================================="
	echo "  1) Jalankan Ulang Installer (pfSense-installer.sh)"
	echo "  2) Masuk ke Shell (/bin/sh)"
	echo "  3) Reboot Sistem"
	echo "=========================================================="
	printf "Pilih [1]: "
	read _ans
	case "${_ans}" in
		1|"") [ -x "${PFSENSE_INSTALLER}" ] && "${PFSENSE_INSTALLER}" ;;
		2) /bin/sh ;;
		3) /sbin/reboot ;;
	esac
done
