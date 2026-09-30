#!/bin/sh
#
# PROVIDE: xray
# REQUIRE: NETWORKING
# KEYWORD: shutdown
#
# Add the following lines to /etc/rc.conf.local to enable xray:
# xray_enable="YES"
# xray_config="/usr/local/etc/xray/config.json"
#

. /etc/rc.subr

name="xray"
rcvar="xray_enable"

load_rc_config $name

: ${xray_enable:="NO"}
: ${xray_config:="/usr/local/etc/xray/config.json"}
: ${xray_user:="root"}

command="/usr/local/bin/xray"
command_args="-config ${xray_config} run > /dev/null 2>&1 &"
pidfile="/var/run/${name}.pid"

start_cmd="${name}_start"
stop_cmd="${name}_stop"
status_cmd="${name}_status"

xray_start() {
    echo "Starting ${name}..."
    if [ ! -f "${xray_config}" ]; then
        echo "Error: Config file ${xray_config} not found!"
        return 1
    fi
    /usr/sbin/daemon -p ${pidfile} -u ${xray_user} ${command} -config ${xray_config} run
}

xray_stop() {
    if [ -f "${pidfile}" ]; then
        echo "Stopping ${name}..."
        kill -TERM $(cat ${pidfile}) 2>/dev/null || true
        rm -f ${pidfile}
    else
        echo "${name} is not running."
    fi
}

xray_status() {
    if [ -f "${pidfile}" ] && kill -0 $(cat ${pidfile}) 2>/dev/null; then
        echo "${name} is running as pid $(cat ${pidfile})."
    else
        echo "${name} is not running."
    fi
}

run_rc_command "$1"
