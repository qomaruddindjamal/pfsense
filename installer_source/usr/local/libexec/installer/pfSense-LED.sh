#!/bin/sh
#
# pfSense Installer pfSense-LED.sh module.
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
. "${INSTALL_INC_PATH}/pfSense-LED"

unset MODEL

while getopts M: opt; do
	case "${opt}" in
	M)
		MODEL="${OPTARG}"
		shift 2
		;;
	*)
		exit 1
		;;
	esac
done

if [ "${#}" -ne 1 ]; then
	echo
	echo Usage:
	echo
	echo "${0} [off|ready|installing|installed]"
	exit 1
fi

if [ -z "${MODEL}" ]; then
	MODEL="$(get_model)"
fi

if [ -z "${MODEL}" ]; then
	echo "Cannot detect the system model.  exiting."
	exit 1
fi

ACTION="${1}"
case "${ACTION}" in
"installed")
	led_installed "${MODEL}"
	;;
"installing")
	led_installing "${MODEL}"
	;;
"off")
	led_off "${MODEL}"
	;;
"ready")
	led_ready "${MODEL}"
	;;
*)
	usage
	;;
esac

exit 0
