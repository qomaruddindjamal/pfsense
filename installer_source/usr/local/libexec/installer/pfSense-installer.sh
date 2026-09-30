#!/bin/sh
#
# pfSense Installer pfSense-installer.sh module.
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2023-2024 Rubicon Communications, LLC (Netgate)
# All rights reserved.
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
# http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.

INSTALL_INC_PATH="/usr/local/libexec/installer"
. "${INSTALL_INC_PATH}/pfSense-common"

advanced_opts() {
	local _error _opt _info _len _model_desc _model_text _type _serial
	local _serial_en _suffix _swap

	_info="$(get_info)"
	_ce_repos_en="$(json_read "${_info}" '."install-settings"."CE_repositories"')"
	_type="$(json_read "${_info}" '."install-settings"."console_type"')"
	_serial_en="$(json_read "${_info}" '."install-settings"."serial_console_enabled"')"
	_swap="$(json_read "${_info}" '."install-settings"."swap_size"')"
	_model_desc="$(get_model_descr)"
	_model_text=""

	[ -n "${_model_desc}" ] && \
	    [  "${_model_desc}" != "unknown hardware" ] && \
	    _model_text="\nDetected: ${_model_desc}\n"

	if [ "${_ce_repos_en}" = "true" ]; then
		_ce_repos="enabled"
	else
		_ce_repos="disabled"
	fi
	if [ "${_serial_en}" = "true" ]; then
		_serial="enabled"
	else
		_serial="disabled"
	fi

	while [ 1 ]; do
		if [ -n "${_swap}" ] && [ "${_swap}" = "0" ]; then
			_swap_text="Swap disabled"
		else
			_swap_text="${_swap}"
		fi
		exec 3>&1
		_opt=`${BSDDIALOG} --backtitle "$(get_title)" \
			--title " Advanced Options " --colors \
			--menu "${_model_text}\nSet the pfSense installation options\n" \
			--item-help --cancel-label "Back" 0 0 0 \
			">>> Continue" "Continue the installation (save options)" \
			    "Save the current settings and continue with the installation" \
			"C CE repositories" "${_ce_repos}" "Show the CE repositories even if a Plus subscription is available" \
			"s Swap Size" "${_swap_text}" "Set the swap partition size" \
			"S Console Serial" "${_serial}" "Enable or disable the serial console" \
			"T Console Type" "${_type}" "Set the Console Type: EFI, video, none" \
			2>&1 1>&3`
		_error="${?}"
		exec 3>&-

		[ "${_error}" -eq 1 ] && \
		    return 0

		[ "${_error}" -ne 0 ] && \
		    return 1

		case "${_opt}" in
		"C CE repositories")
			if [ "${_ce_repos_en}" = "true" ]; then
				_ce_repos_en="false"
				_ce_repos="disabled"
			else
				_ce_repos_en="true"
				_ce_repos="enabled"
			fi
			;;
		"s Swap Size")
			exec 3>&1
			SWAPNEW="$("${BSDDIALOG}" --backtitle "$(get_title)" \
			    --title " Swap Size " --colors \
			    --inputbox "\nEnter the size of the swap partition.\n\n\
Please consider the total storage size and the minimum requirements to install pfSense.\n\n\
Use \"0\" to disable the swap.\n" \
			    0 0 "${_swap}" 2>&1 1>&3)" || return 1
			exec 3>&-
			if [ -z "${SWAPNEW}" ]; then
				errx "Warning!" "\nThe Swap Size must be set!\n"
				continue
			fi
			_len="${#SWAPNEW}"
			_suffix="$(echo "$SWAPNEW}" | \
			    "${CUT}" -c "${_len}" | tr -d "[:digit:]" | \
                            tr -d "[:blank:]" | tr -d "[:special:]" | \
			    tr -d "[:punct:]")"
			if [ -n "${_suffix}" ] && \
			    [ "${_suffix}" != "m" ] && \
			    [ "${_suffix}" != "M" ] && \
			    [ "${_suffix}" != "g" ] && \
			    [ "${_suffix}" != "G" ]; then
				errx "Warning!" "\nInvalid size suffix: ${_suffix}\n"
				continue
			fi
			[ -n "${_suffix}" ] && \
			    SWAPNEW="$(echo "${SWAPNEW}" | \
			    "${CUT}" -c "1-$(( "${_len}" - 1 ))")"
			if [ "${SWAPNEW}" != "0" ]; then
				SWAPNEW="$(echo -n "${SWAPNEW}" | "${SED}" 's/^0*//')"
				if [ -z "${SWAPNEW}" ]; then
					errx "Warning!" "\nInvalid Swap Size!\n"
					continue
				fi
			fi
			SWAPN="$(printf "%d" "${SWAPNEW}")"
			if [ "${SWAPN}" != "${SWAPNEW}" ]; then
				errx "Warning!" "\nInvalid Swap Size: ${SWAPNEW}\n"
				continue;
			fi
			if [ "${SWAPNEW}" -lt 0 ] || \
			    [ "${SWAPNEW}" -gt 9999 ]; then
				errx "Warning!" "\nInvalid Swap Size: ${SWAPNEW} (must be in 0~9999 range)\n"
				continue
			fi
			_swap="${SWAPNEW}${_suffix}"
			;;
		"S Console Serial")
			if [ "${_serial_en}" = "true" ]; then
				_serial_en="false"
				_serial="disabled"
			else
				_serial_en="true"
				_serial="enabled"
			fi
			;;
		"T Console Type")
			if [ "${_type}" = "efi" ]; then
				_type="video"
			elif [ "${_type}" = "video" ]; then
				_type="none"
			elif [ "${_type}" = "none" ]; then
				_type="efi"
			fi
			;;
		">>> Continue")
			break
			;;
		esac
	done

	if ! ce_repos_set "${_ce_repos_en}"; then
		errx "Warning!" "\nCannot set the CE repositories state!\n"
		return 1
	fi
	if ! swap_size_set "${_swap}"; then
		errx "Warning!" "\nCannot set the swap size!\n"
		return 1
	fi
	if ! console_type_set "${_type}"; then
		errx "Warning!" "\nCannot set the console type!\n"
		return 1
	fi
	if ! console_serial_set "${_serial_en}"; then
		errx "Warning!" "\nCannot set the serial console state!\n"
		return 1
	fi

	return 0
}

#
# Show the Copyright message
#
installer_copyright() {

	TEXT=$("${CURL}" ${CURLFLAGS} -sN "${INSTALLER_URL}/copyright" | "${JQ}" -r '.text' 2> /dev/null)
	exec 3>&1
	"${BSDDIALOG}" --backtitle "$(get_title)" \
		--title " Copyright and Distribution Notice " \
		--ok-label "Accept" --no-label "Cancel" \
		--colors \
		--yesno "${TEXT}" 0 0 2>&1 1>&3
	ERROR=$?
	exec 3>&-

	if [ "${ERROR}" -ne 0 ]; then
		installer_reset
		return 1
	fi

	#
	# Accept the Copyright
	#
	if ! "${CURL}" ${CURLFLAGS} -s -d "accept=1" "${INSTALLER_URL}/copyright" 2>&1 > /dev/null; then
		return 1
	fi

	return 0
}

installer_console() {

	kbdcontrol -d >/dev/null 2>&1
	if [ $? -eq 0 ]; then
		# Syscons: use xterm, start interesting things on other VTYs
		TERM=xterm

		# Don't send ESC on function-key 62/63 (left/right command key)
		kbdcontrol -f 62 '' > /dev/null 2>&1
		kbdcontrol -f 63 '' > /dev/null 2>&1

		if [ -z "$EXTERNAL_VTY_STARTED" ]; then
			# Init will clean these processes up if/when the system
			# goes multiuser
			touch /tmp/bsdinstall_log
			tail -f /tmp/bsdinstall_log > /dev/ttyv2 &
			/usr/libexec/getty autologin ttyv3 &
			EXTERNAL_VTY_STARTED=1
		fi
	else
		NID="$(get_nid)"
		MODEL="$(/sbin/sysctl -qn dev.netgate.desc)"
		# Serial or other console
		echo
		echo "Welcome to pfSense!"
		while [ 1 ]; do
			DEVID=0
			if [ -n "${MODEL}" ] && [ "${MODEL}" != "unkown" ]; then
				echo
				echo -n "${MODEL}"
				[ -n "${NID}" ] && [ "${NID}" != "null" ] && \
				    echo -n " - "
				DEVID=1
			fi
			if [ -n "${NID}" ] && [ "${NID}" != "null" ]; then
				[ "${DEVID}" = "0" ] && echo
				echo -n "Netgate Device ID: ${NID}"
				DEVID=1
			fi
			[ "${DEVID}" = "1" ] && echo
			echo
			echo "Please choose the appropriate terminal type for your system."
			echo "Common console types are:"
			echo "   ansi     Standard ANSI terminal"
			echo "   vt100    VT100 or compatible terminal"
			echo "   xterm    xterm terminal emulator (or compatible)"
			echo "   cons25w  cons25w terminal"
			echo
			echo -n "Console type [vt100]: "
			read ITERM
			ITERM=${ITERM:-vt100}
			if [ "${ITERM}" != "ansi" ] && [ "${ITERM}" != "vt100" ] && \
			    [ "${ITERM}" != "xterm" ] && [ "${ITERM}" != "cons25w" ]; then
				continue
			fi
			break
		done
		TERM="${ITERM}"
	fi
	export TERM
}

installer_main() {
	local _cfg_count _cfg_selected _error _i _info

	VERSION="$(get_version)"
	[ -f "${INSTALLER_VERSION}" ] && [ -r "${INSTALLER_VERSION}" ] && \
	    /bin/rm -f "${INSTALLER_VERSION}" 2> /dev/null
	if [ ! -f "${INSTALLER_VERSION}" ] || \
	    [ -w "${INSTALLER_VERSION}" ]; then
		echo -n "${VERSION}" > "${INSTALLER_VERSION}"
	fi
	if ! installer_reset; then
		"${BSDDIALOG}" --colors --backtitle "$(get_title)" \
		    --title " Netgate Installer Error " \
		    --msgbox "\nCannot connect to installer daemon.\n\nTrying again...\n" \
		    --ok-label "Retry" 0 0
		# restart the Installer backend.
		return 22
	fi

	if ! installer_copyright; then
		return 1
	fi

	while [ 1 ]; do
		_cfg_count="$(cfg_count)"

		exec 3>&1
		if [ "${_cfg_count}" -gt 0 ]; then
			_info="$(get_info)"
			_cfg_selected="$(cfg_restore_selected "${_info}")"
			IMODE="$(${BSDDIALOG} --backtitle "$(get_title)" \
			    --title " Welcome " --colors \
			    --menu "\nWelcome to pfSense!\n${_cfg_selected} " --item-help \
			    --extra-button --extra-label "Advanced Options" 0 0 0 \
			    "Install" "Install pfSense" "Install pfSense with the selected configuration file" \
			    "Rescue Shell" "Launch a shell for rescue operations" \
				"Launch a shell for rescue operations" \
			    "Configuration Restore" "Select a configuration file to restore" \
				"Install pfSense and restore the selected configuration file" \
			    2>&1 1>&3)"
		else
			IMODE="$(${BSDDIALOG} --backtitle "$(get_title)" \
			    --title " Welcome " --colors \
			    --menu "\nWelcome to pfSense!\n " --item-help \
			    --extra-button --extra-label "Advanced Options" 0 0 0 \
			    "Install" "Install pfSense" "Install pfSense with the selected configuration file" \
			    "Rescue Shell" "Launch a shell for rescue operations" \
				"Launch a shell for rescue operations" \
			    2>&1 1>&3)"
		fi
		_error="${?}"
		exec 3>&-

		[ "${_error}" -eq 1 ] && \
		    return 1

		if [ "${_error}" -eq 3 ]; then
			if ! advanced_opts; then
				return 1
			fi
		fi

		if [ "${IMODE}" = "Configuration Restore" ]; then
			"${INSTALL_INC_PATH}/pfSense-config-restore"
			continue
		fi

		[ "${_error}" -eq 0 ] && \
		    break
	done

	case "$IMODE" in
	"Install")	# Install
		# If not netbooting, have the installer configure the network
		dlv=`/sbin/sysctl -n vfs.nfs.diskless_valid 2> /dev/null`
		if [ ${dlv:=0} -eq 0 -a ! -f /etc/diskless ]; then
			BSDINSTALL_CONFIGCURRENT=yes; export BSDINSTALL_CONFIGCURRENT
		fi

		if ! system_requirements_verify; then
			# Exit
			return 2
		fi

		trap true SIGINT	# Ignore cntrl-C here
		env \
			BSDINSTALL_SKIP_FINALCONFIG=y \
			BSDINSTALL_SKIP_HARDENING=y \
			BSDINSTALL_SKIP_HOSTNAME=y \
			BSDINSTALL_SKIP_KEYMAP=y \
			BSDINSTALL_SKIP_MANUAL=y \
			BSDINSTALL_SKIP_SERVICES=y \
			BSDINSTALL_SKIP_TIME=y \
			BSDINSTALL_SKIP_USERS=y \
			DISTRIBUTIONS="base.txz" \
			PFSENSE_INSTALL=yes \
			OSNAME=pfSense \
			bsdinstall
		case "${?}" in
		0)
			if ! state_set "INSTALL-DONE"; then
                                "${BSDDIALOG}" --colors --backtitle "$(get_title)" \
                                    --title " Netgate Installer Error " \
                                    --msgbox "\nThe installation has failed!\n\n\
The system is NOT installed.\n\nPlease check the installation log in ${INSTALL_LOG}.\n" \
                                    0 0
				return 2
			fi
			"${BSDDIALOG}" --backtitle "$(get_title)" \
			    --title " Complete " --yes-label "Reboot" --no-label "Shell" \
			    --yesno "\nInstallation of pfSense complete! Would you like to reboot into the installed system now?\n" \
			    0 0 && installer_reboot
			clear
			echo "When finished, type 'exit' to reboot."
			/usr/bin/env NO_INSTALLER=1 /bin/sh
			installer_reboot
			;;
		1)
			# Exit
			return 2
			;;
		2)
			# Continue
			return 12
			;;
		esac
		return 0
		;;
	"Rescue Shell")	# Rescue Shell
		clear
		echo "When finished, type 'exit' to return to the installer."
		/usr/bin/env NO_INSTALLER=1 /bin/sh
		;;
	esac

	return 0
}

system_requirements_verify() {
	local _disk_count _info _nic_count

	"${BSDDIALOG}" --backtitle "$(get_title)"       \
	    --title " Requirements Check " --colors     \
	    --infobox "\nVerifying the System Requirements (this can take a while)...\n" \
	    0 60

	_info="$(get_info)"
	_disk_count="$(json_read "${_info}" '.disk_count')"
	_nic_count="$(json_read "${_info}" '.nic_count')"

	if [ -n "${_disk_count}" ] && [ "${_disk_count}" = "0" ]; then
		errx "Warning!" \
		    "\nCannot continue with the installation, no valid storage devices detected.\n"
		return 1
	fi
	if [ -n "${_nic_count}" ] && [ "${_nic_count}" = "0" ]; then
		errx "Warning!" \
		    "\nCannot continue with the installation, no network interfaces detected.\n"
		return 1
	fi

	return 0
}

#
# main()
#

trap true SIGINT	# Ignore cntrl-C here
while [ 1 ]; do

	# Set the console type - if necessary.
	unset TERM
	installer_console
	if [ -n "${TERM}" ]; then
		break
	fi
done

# Query terminal size; useful for serial lines.
resizewin -z

while [ 1 ]; do

	installer_main
	case "${?}" in
	2)
		# Exit
		break
		;;
	12)
		# Continue
		continue
		;;
	22)
		[ -f "${INSTALLER_PIPE}" ] && [ -w "${INSTALLER_PIPE}" ] && \
		    echo "start" > "${INSTALLER_PIPE}"
		;;
	esac
	if ! "${BSDDIALOG}" --colors --backtitle "$(get_title)" \
	    --title " Netgate Installer " \
	    --yesno "\nDo you want to restart the installation ?\n" \
	    --yes-label "Restart" --no-label "Exit" 0 0; then
		break
	fi
done

exit 0
