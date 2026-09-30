#!/bin/sh
#
# pfSense Installer pfSense-installerd.sh module.
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

installer_start() {
	local _pid

	/usr/local/etc/rc.d/nginx start
	_pid="$(/usr/bin/pgrep pfSense-installer)"
	[ -z "${_pid}" ] && \
	    /usr/local/bin/cgi-fcgi -start -connect 127.0.0.1:9000 /usr/local/sbin/pfSense-installer
}

cleanup() {
	/bin/rm -f "${INSTALLER_PIPE}" 2> /dev/null
	trap "-" 1 2 15 EXIT
	exit 0
}

#
# main()
#

/bin/rm -f "${INSTALLER_PIPE}" 2> /dev/null
/usr/bin/mkfifo -m 0777 "${INSTALLER_PIPE}" 2> /dev/null
trap cleanup 1 2 15 EXIT

if [ ! -p "${INSTALLER_PIPE}" ] || [ ! -r "${INSTALLER_PIPE}" ]; then
	echo "Cannot create the installer start pipe."
	exit 1
fi

while [ 1 ]; do
	while read cmd; do
		echo "line: $cmd"
		[ "${cmd}" = "start" ] && \
		    installer_start
	done < "${INSTALLER_PIPE}"
	sleep 1
done

/bin/rm -f "${INSTALLER_PIPE}" 2> /dev/null

exit 0
