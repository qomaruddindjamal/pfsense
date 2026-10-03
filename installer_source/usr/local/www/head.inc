<?php
/*
 * head.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2026 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once("globals.inc");
require_once("functions.inc");
require_once("shortcuts.inc");
require_once("service-utils.inc");
require_once('notices.inc');

header('Content-Type: text/html; charset=utf-8');

$pagetitle = strip_tags(gentitle($pgtitle));
$system_url = config_get_path('system/hostname') . "." . config_get_path('system/domain');

if ($user_settings['webgui']['pagenamefirst']) {
	$tabtitle = $pagetitle . " - " . htmlspecialchars($system_url);
} else {
	$tabtitle = htmlspecialchars($system_url) . " - " . $pagetitle;
}

$cssfile = "/css/pfSense.css";

if (isset($user_settings['webgui']['webguicss'])) {
	if (file_exists("/usr/local/www/css/" . $user_settings['webgui']['webguicss'])) {
		$cssfile = "/css/" . $user_settings['webgui']['webguicss'];
	}
}

// set default columns to two if unset
if (config_get_path('system/webgui/dashboardcolumns') === null) {
	config_set_path('system/webgui/dashboardcolumns', 2);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">
<? include('includes/iconheaders.html') ?>
	<meta name="theme-color" content="#ffffff">

	<link rel="stylesheet" href="/vendor/font-awesome/css/all.min.css?v=<?=filemtime('/usr/local/www/vendor/font-awesome/css/all.min.css')?>">
	<link rel="stylesheet" href="/vendor/sortable/sortable-theme-bootstrap.css?v=<?=filemtime('/usr/local/www/vendor/sortable/sortable-theme-bootstrap.css')?>">
	<link rel="stylesheet" href="/vendor/jquery-treegrid/css/jquery.treegrid.css?v=<?=filemtime('/usr/local/www/vendor/jquery-treegrid/css/jquery.treegrid.css')?>">
	<link rel="stylesheet" href="<?=$cssfile?>?v=<?=filemtime('/usr/local/www/' . $cssfile)?>" />
	<link rel="stylesheet" href="/css/sidebar-layout.css?v=<?=filemtime('/usr/local/www/css/sidebar-layout.css')?>" />

	<title><?=$tabtitle?></title>
	<script type="text/javascript">
	//<![CDATA[
	var events = events || [];
	//]]>
	</script>
</head>

<?php

/* Determine automated help URL. Should output the page name and parameters
   separately */
$uri_split = "";
preg_match("/\/(.*)\?(.*)/", $_SERVER["REQUEST_URI"], $uri_split);

/* If there was no match, there were no parameters, just grab the filename
   Otherwise, use the matched filename from above. */
if (empty($uri_split[0])) {
	$pagename = ltrim($_SERVER["REQUEST_URI"], '/');
} else {
	$pagename = $uri_split[1];
}

/* If the page name is still empty, the user must have requested / (index.php) */
if (empty($pagename)) {
	$pagename = "index.php";
}

/* If the filename is pkg_edit.php or wizard.php, reparse looking
	for the .xml filename */
if (($pagename == "pkg.php") || ($pagename == "pkg_edit.php") || ($pagename == "wizard.php")) {
	$param_split = explode('&', $uri_split[2]);
	foreach ($param_split as $param) {
		if (substr($param, 0, 4) == "xml=") {
			$xmlfile = explode('=', $param);
			$pagename = $xmlfile[1];
		}
	}
} else if ($pagename == "status_logs.php") {
	$param_split = explode('&', $uri_split[2]);
	foreach ($param_split as $param) {
		if (substr($param, 0, 8) == "logfile=") {
			$logtype = explode('=', $param);
			$pagename .= '-' . $logtype[1];
		}
	}
}

// Build the full help URL.
$helpurl .= "{$g['help_base_url']}?page={$pagename}";

/*
 * Read files from $g['ext_menu_path']/*.xml and fill an array with menu info
 */
function read_ext_menu_path_data() {
	global $g;

	$result = array();

	if (!is_dir(g_get('ext_menu_path'))) {
		return $result;
	}

	foreach (glob("{$g['ext_menu_path']}/*.xml") as $menu_xml) {
		$xml_data = parse_xml_config_pkg($menu_xml, "packagegui");
		if (empty($xml_data['menu'])) {
			continue;
		}
		foreach ($xml_data['menu'] as $menu) {
			$result[] = $menu;
		}
	}

	return $result;
}

// Create a menu entry of any installed packages in the specified category
// (Now reads the menu information from installedpackages/menu only)
function return_ext_menu($section) {
	global $ext_menu_path_data;

	$htmltext = "";
	$extarray = array();
	$ext_menu_entries = array();

	if ((!empty(config_get_path('installedpackages/package'))) && (!empty(config_get_path('installedpackages/menu')))) {
		foreach (config_get_path('installedpackages/menu', []) as $menu) {
			if (isset($menu['name']) && ($menu['name'] != "AutoConfigBackup")) { // AutoConfigBackup was moved to a built-in function
	//			print('Name: ' . $menu['name'] . ', Pkg category: ' . $menu['category'] . ', Section: ' . $section . '<br />');
				if (isset($menu['section']) && ($menu['section'] == $section)) {
					$ext_menu_entries[] = $menu;
				}
			}
		}
	}

	foreach ($ext_menu_path_data as $menu) {
		if ($menu['section'] == $section) {
			$ext_menu_entries[] = $menu;
		}
	}

	foreach ($ext_menu_entries as $menu) {
		if ($menu['url'] != "") {
			$test_url = $menu['url'];
			$addresswithport = getenv("HTTP_HOST");
			$colonpos = strpos($addresswithport, ":");

			if ($colonpos !== false) {
				//my url is actually just the IP address of the pfsense box
				$myurl = substr($addresswithport, 0, $colonpos);
			} else {
				$myurl = $addresswithport;
			}
			$description = str_replace('$myurl', $myurl, $menu['url']);
		} else {
			$description = '/pkg.php?xml=' . $menu['configfile'];
			$test_url=$description;
		}

		if (isAllowedPage($test_url)) {
			$extarray[] = array($menu['name'], $description);
		}
	}

	return $extarray;
}

function output_menu($arrayitem, $target = null, $section = "") {
	$output = "";

	foreach ($arrayitem as $item) {

		/* If the user has access to help pages, also show the full help menu. See #5909 */
		if (isAllowedPage($item[1]) || $item[1] == "/index.php?logout" ||
		    (($section == "Help") && isAllowedPage("help.php")) ||
		    (substr($item[1], 0, 8) == "https://")) {
			$attr = sprintf("href=\"%s\"", htmlentities($item[1]));

			if ($target) {
				$attr .= sprintf(" target=\"%s\"", htmlentities($target));
			}

			$class = "navlnk";

			if ($item['class']) {
				$class .= " {$item['class']}";
			}

			$attr .= sprintf(" class=\"%s\"", htmlentities($class));

			if ($item['style']) {
				$attr .= sprintf(" style=\"%s\"", htmlentities($item['style']));
			}


			if ($item[0] == '-DIVIDER-') {
				$output .= ' <li class="divider"></li>';
			} else {
				$output .= "<li>". sprintf("<a %s %s>%s</a>", $attr, ($item[1] == "/index.php?logout") ? "usepost":"",$item[0]) . "</li>\n";
			}
		}
	}

	return $output;
}

$ext_menu_path_data = read_ext_menu_path_data();

// System
$system_menu = array();
$system_menu[] = array(gettext("Advanced"), "/system_advanced_admin.php");
$system_menu[] = array(gettext("Update"), "/pkg_mgr_install.php?id=firmware");
$system_menu[] = array(gettext("General Setup"), "/system.php");
$system_menu[] = array(gettext("High Availability"), "/system_hasync.php");
$system_menu[] = array(gettext("Package Manager"), "/pkg_mgr_installed.php");
$system_menu[] = array(gettext("Setup Wizard"), "/wizard.php?xml=setup_wizard.xml");
$system_menu[] = array(gettext("Routing"), "/system_gateways.php");

if (isAllowedPage("system_camanager.php")) {
	$system_menu[] = array(gettext("Certificates"), "/system_camanager.php");
} elseif (isAllowedPage("system_certmanager.php")) {
	$system_menu[] = array(gettext("Certificates"), "/system_certmanager.php");
} elseif (isAllowedPage("system_crlmanager.php")) {
	$system_menu[] = array(gettext("Certificates"), "/system_crlmanager.php");
}

$system_menu[] = array(gettext("Register"), "/system_register.php");

if (isAllowedPage("system_usermanager.php")) {
	$system_menu[] = array(gettext("User Manager"), "/system_usermanager.php");
}

if (isAllowedPage("system_usermanager_passwordmg.php")) {
	$system_menu[] = array(gettext("User Password Manager"), "/system_usermanager_passwordmg.php");
}

if ($user_settings['customsettings'] && isAllowedPage("system_user_settings.php")) {
	$system_menu[] = array(gettext("User Settings"), "/system_user_settings.php");
}

$system_menu = msort(array_merge($system_menu, return_ext_menu("System")), 0);

/* ensure Logout is the last item in the menu */
$system_menu[] = array(gettext("Logout") . " (" . $_SESSION['Username'] . ")", "/index.php?logout");

// Interfaces
// NOTE:
// Now that menus are sorted, adding a DIVIDER must be done after the sorting so an array is formed of the
// items above the divider and another for below it. These are then sorted and combined with the divider
$interfaces_menu = array();
$interfaces_top = array();
$interfaces_bottom = array();

if (!config_path_enabled('system/webgui', 'noassigninterfaces')) {
	$interfaces_top[] = array(gettext("Assignments"), "/interfaces_assign.php");
	$div = true;
}

$platform = system_identify_specific_platform();

if ($platform['name'] == "uFW") {
	$interfaces_top[] = array(gettext("Switches"), "/switch_system.php");
}

$opts = get_configured_interface_with_descr(true);

foreach ($opts as $oif => $odescr) {
	if (config_get_path("interfaces/{$oif}/ovpn") === null) {
		$interfaces_bottom[] = array(htmlspecialchars($odescr), "/interfaces.php?if={$oif}");
	}
}

$interfaces_bottom = array_merge($interfaces_bottom, return_ext_menu("Interfaces"));

if ($user_settings['webgui']['interfacessort']) {
	$interfaces_bottom = msort($interfaces_bottom, 0);
}

// Combine the top section, the divider and the bottom section of this menu
$interfaces_menu = array_merge($interfaces_top, [array(0 => "-DIVIDER-")], $interfaces_bottom);

// Firewall
$firewall_menu = array();
$firewall_menu[] = array(gettext("Aliases"), "/firewall_aliases.php");
$firewall_menu[] = array(gettext("NAT"), "/firewall_nat.php");
$firewall_menu[] = array(gettext("Rules"), "/firewall_rules.php");
$firewall_menu[] = array(gettext("Schedules"), "/firewall_schedule.php");
$firewall_menu[] = array(gettext("Traffic Shaper"), "/firewall_shaper.php");
$firewall_menu[] = array(gettext("Virtual IPs"), "/firewall_virtual_ip.php");
$firewall_menu = msort(array_merge($firewall_menu, return_ext_menu("Firewall")), 0);

// Services
$services_menu = array();
$services_menu[] = array(gettext("Auto Config Backup"), "/services_acb_settings.php");
$services_menu[] = array(gettext("Captive Portal"), "/services_captiveportal.php");
$services_menu[] = array(gettext("DNS Forwarder"), "/services_dnsmasq.php");
$services_menu[] = array(gettext("DNS Resolver"), "/services_unbound.php");
$services_menu[] = array(gettext("DHCP Relay"), "/services_dhcp_relay.php");
$services_menu[] = array(gettext("DHCPv6 Relay"), "/services_dhcpv6_relay.php");

if (g_get('services_dhcp_server_enable')) {
	$services_menu[] = array(gettext("DHCP Server"), "/services_dhcp_settings.php");
	$services_menu[] = array(htmlspecialchars(gettext("DHCPv6 Server")), "/services_dhcpv6_settings.php");
}

$services_menu[] = [gettext('Router Advertisement'), '/services_radvd.php'];

$services_menu[] = array(gettext("Dynamic DNS"), "/services_dyndns.php");
$services_menu[] = array(gettext("IGMP Proxy"), "/services_igmpproxy.php");
$services_menu[] = array(gettext("NTP"), "/services_ntpd.php");
$services_menu[] = array(gettext("PPPoE Server"), "/services_pppoe.php");
$services_menu[] = array(gettext("SNMP"), "/services_snmp.php");

if (count(config_get_path('interfaces', [])) > 1) {
	/* no use for UPnP in single-interface deployments
	remove to reduce user confusion
	*/
	$services_menu[] = array(gettext("UPnP IGD &amp; PCP"), "/pkg_edit.php?xml=miniupnpd.xml");
}

$services_menu[] = array(gettext("Wake-on-LAN"), "/services_wol.php");
$services_menu = msort(array_merge($services_menu, return_ext_menu("Services")), 0);

// VPN
$vpn_menu = array();
$vpn_menu[] = array(gettext("IPsec"), "/vpn_ipsec.php");
$vpn_menu[] = array(gettext("OpenVPN"), "/vpn_openvpn_server.php");
//$vpn_menu[] = array(gettext("PPTP"), "/vpn_pptp.php");
$vpn_menu[] = array(gettext("L2TP"), "/vpn_l2tp.php");
$vpn_menu = msort(array_merge($vpn_menu, return_ext_menu("VPN")), 0);
// Pastikan WireGuard tidak muncul di sub-menu VPN
$vpn_menu = array_filter($vpn_menu, function($item) {
	return ($item[1] ?? '') !== '/vpn_wg.php';
});

// Status
$status_menu = array();
$status_menu[] = array(gettext("Captive Portal"), "/status_captiveportal.php");
$status_menu[] = array(gettext("CARP (failover)"), "/status_carp.php");
$status_menu[] = array(gettext("Dashboard"), "/index.php");
$status_menu[] = array(gettext("Gateways"), "/status_gateways.php");
$status_menu[] = array(gettext("DHCP Leases"), "/status_dhcp_leases.php");
$status_menu[] = array(gettext("DHCPv6 Leases"), "/status_dhcpv6_leases.php");
$status_menu[] = array(gettext("DNS Resolver"), "/status_unbound.php");
$status_menu[] = array(gettext("Filter Reload"), "/status_filter_reload.php?user=true");
$status_menu[] = array(gettext("Interfaces"), "/status_interfaces.php");
$status_menu[] = array(gettext("IPsec"), "/status_ipsec.php");
$status_menu[] = array(gettext("NTP"), "/status_ntpd.php");
$status_menu[] = array(gettext("OpenVPN"), "/status_openvpn.php");
$status_menu[] = array(gettext("Queues"), "/status_queues.php");
$status_menu[] = array(gettext("Services"), "/status_services.php");
$status_menu[] = array(gettext("System Logs"), "/status_logs.php");
$status_menu[] = array(gettext("Traffic Graph"), "/status_graph.php");

if (count(config_get_path('interfaces', [])) > 1) {
	$status_menu[] = array(gettext("UPnP IGD &amp; PCP"), "/status_upnp.php");
}

$wifdescrs = array();
$ifentries = get_configured_interface_with_descr();
foreach ($ifentries as $ent => $entdesc) {
	if (is_array(config_get_path("interfaces/{$ent}/wireless")) &&
	    preg_match(g_get('wireless_regex'), config_get_path("interfaces/{$ent}/if"))) {
		$wifdescrs[$ent] = $entdesc;
	}
}

if (count($wifdescrs) > 0) {
	$status_menu[] = array(gettext("Wireless"), "/status_wireless.php");
}

$status_menu = msort(array_merge($status_menu, return_ext_menu("Status")), 0);

// Tools
$tools_menu = array();
$tools_menu[] = array(gettext("Speedtest"), "/tools_speedtest.php");

// Diagnostics
$diagnostics_menu = array();
$diagnostics_menu[] = array(gettext("ARP Table"), "/diag_arp.php");
$diagnostics_menu[] = array(gettext("Authentication"), "/diag_authentication.php");
$diagnostics_menu[] = array(htmlspecialchars(gettext("Backup & Restore")), "/diag_backup.php");
$diagnostics_menu[] = array(gettext("Command Prompt"), "/diag_command.php");
$diagnostics_menu[] = array(gettext("DNS Lookup"), "/diag_dns.php");
$diagnostics_menu[] = array(gettext("Edit File"), "/diag_edit.php");
$diagnostics_menu[] = array(gettext("Factory Defaults"), "/diag_defaults.php");

if (file_exists("/var/run/gmirror_active")) {
	$diagnostics_menu[] = array(gettext("GEOM Mirrors"), "/diag_gmirror.php");
}

$diagnostics_menu[] = array(gettext("Halt System"), "/diag_halt.php");
$diagnostics_menu[] = array(gettext("Limiter Info"), "/diag_limiter_info.php");
$diagnostics_menu[] = array(gettext("NDP Table"), "/diag_ndp.php");
$diagnostics_menu[] = array(gettext("Tables"), "/diag_tables.php");
$diagnostics_menu[] = array(gettext("Ping"), "/diag_ping.php");
$diagnostics_menu[] = array(gettext("Test Port"), "/diag_testport.php");
$diagnostics_menu[] = array(gettext("pfInfo"), "/diag_pf_info.php");
$diagnostics_menu[] = array(gettext("pfTop"), "/diag_pftop.php");
$diagnostics_menu[] = array(gettext("Reboot"), "/diag_reboot.php");
$diagnostics_menu[] = array(gettext("Routes"), "/diag_routes.php");
$diagnostics_menu[] = array(gettext("S.M.A.R.T. Status"), "/diag_smart.php");
$diagnostics_menu[] = array(gettext("Sockets"), "/diag_sockets.php");
$diagnostics_menu[] = array(gettext("States"), "/diag_dump_states.php");
$diagnostics_menu[] = array(gettext("States Summary"), "/diag_states_summary.php");
$diagnostics_menu[] = array(gettext("System Activity"), "/diag_system_activity.php");
$diagnostics_menu[] = array(gettext("Traceroute"), "/diag_traceroute.php");
$diagnostics_menu[] = array(gettext("Packet Capture"), "/diag_packet_capture.php");

$diagnostics_menu = msort(array_merge($diagnostics_menu, return_ext_menu("Diagnostics")), 0);

if (!g_get('disablehelpmenu')) {
	$help_menu = array();
	$help_menu[] = array(gettext("About this Page"), $helpurl);
	if (g_get('product_name') == "pfSense") {
		$help_menu[] = array(gettext("Bug Database"), "https://redirects.netgate.com/issues");
	}

	$help_menu[] = array(gettext("User Forum"), "https://redirects.netgate.com/forum");
	$help_menu[] = array(gettext("Documentation"), "https://redirects.netgate.com/docs");
	$help_menu[] = array(gettext("Paid Support"), "https://redirects.netgate.com/support");
	$help_menu[] = array(gettext("pfSense Book"), "https://redirects.netgate.com/book");
	$help_menu[] = array(gettext("FreeBSD Handbook"), "https://redirects.netgate.com/fbsdhandbook");
	if  (strpos(g_get('product_label'), 'Plus') === false) {
		$help_menu[] = array(gettext("Upgrade to pfSense Plus"), "https://redirects.netgate.com/upgrade");
	}
	$help_menu = msort(array_merge($help_menu, return_ext_menu("Help")), 0);
}

$menuclass = "static";
$menuclass_dropdown = '';
if ($user_settings['webgui']['webguifixedmenu'] == "fixed") {
	$menuclass = "fixed";
	$menuclass_dropdown = ' dropdown-menu-fixed';
}

$numColumns = (int) $user_settings['webgui']['dashboardcolumns'];

if (($pagename === "index.php") && ($numColumns > 2)) {
	$columnsContainer = 'style="max-width: ' . 585*$numColumns . 'px;width: 100%"';
}

$display_notices = false;
$allow_clear_notices = false;

if (are_notices_pending()) {
	// Evaluate user privs to determine if notices should be displayed, and if the user can clear them.
	$user_entry = getUserEntry($_SESSION['Username']);
	$user_entry = $user_entry['item'];
	if (isAdminUID($_SESSION['Username']) || userHasPrivilege($user_entry, "user-view-clear-notices") || userHasPrivilege($user_entry, "page-all")) {
		$display_notices = true;
		$allow_clear_notices = true;
	} elseif (userHasPrivilege($user_entry, "user-view-notices")) {
		$display_notices = true;
	}
}
?>
<body id="<?=$numColumns?>">
<nav id="topmenu" class="navbar navbar-<?=$menuclass?>-top navbar-inverse">
	<div class="header-inner">
		<div class="header-left">
			<div class="header-brand-area">
				<a class="navbar-brand" href="/" title="<?=gettext("pfSense Home")?>">
					<?php include("/usr/local/www/logo.svg"); ?>
				</a>
			</div>
			<div class="header-nav-actions">
				<button type="button" id="sidebar-toggle-btn" title="<?=gettext("Toggle Navigation")?>">
					<i class="fa-solid fa-bars"></i>
				</button>
				<a href="/" class="header-home-btn" title="<?=gettext("Home")?>">
					<i class="fa-solid fa-house"></i>
					<span><?=gettext("Home")?></span>
				</a>
			</div>
		</div>
		<ul class="header-right-nav">
			<li class="header-host-info">
				<i class="fa-solid fa-server"></i>
				<span><?=htmlspecialchars(config_get_path('system/hostname') . '.' . config_get_path('system/domain'))?></span>
			</li>
			<?php if ($display_notices):?>
				<?php $notices = get_notices()?>
				<li>
					<a href="#" data-toggle="modal" data-target="#notices" class="header-btn-link" title="<?=gettext("Notices")?>">
						<i class="fa-solid fa-bell text-danger"></i>
						<span class="badge bg-danger" style="margin-left: 3px; font-size: 10px;"><?=count($notices)?></span>
					</a>
				</li>
			<?php endif; ?>
			<li>
				<a href="/index.php?logout" usepost class="header-btn-link" title="<?=gettext("Logout") . " (" . $_SESSION['Username'] . "@" . htmlspecialchars($system_url) . ")"?>">
					<i class="fa-solid fa-right-from-bracket"></i>
				</a>
			</li>
		</ul>
	</div>
</nav>

<!-- Backdrop overlay for mobile devices -->
<div id="sidebar-backdrop"></div>

<!-- Left Sidebar Navigation -->
<aside id="pf-sidebar">
	<div class="sidebar-header-box">
		<div class="sidebar-brand-title">
			<i class="fa-solid fa-shield-halved" style="color: #337ab7; margin-right: 8px;"></i>
			<span>pfSense</span>
		</div>
		<button id="sidebar-close-btn" class="sidebar-close-btn" aria-label="<?=gettext("Close Navigation")?>">
			<i class="fa-solid fa-xmark"></i>
		</button>
	</div>
	<div class="sidebar-section-title"><?=gettext("Navigation")?></div>
	<ul class="sidebar-menu">
	<?php
        if ($user_settings['webgui']['webguihostnamemenu'] == 'hostonly') {
            $help_menu_title = htmlspecialchars(config_get_path('system/hostname'));
        }
        elseif ($user_settings['webgui']['webguihostnamemenu'] == 'fqdn') {
            $help_menu_title = htmlspecialchars($system_url);
        }
        else {
            $help_menu_title = 'Help';
        }

		$wg_custom_icon = '<span class="sidebar-icon sidebar-icon-wg" title="WireGuard"><svg viewBox="-2 0 306 306" fill="currentColor"><g transform="translate(-1.5394)"><path d="m301.28 145.56s6.9396-145.56-153.04-145.56c-141.48 0-145.9 139.63-145.9 139.63s-20.811 160.37 149.16 160.37c163.02 0 149.78-154.44 149.78-154.44zm-197.8-50.863c30.017-18.364 68.366-7.1401 82.735 20.476 2.7233 5.2338 3.0694 13.291 1.3447 18.782-5.9546 18.956-20.014 29.587-39.312 34.103 5.6892-4.8707 10.218-10.394 11.659-18.025a26.402 26.402 0 0 0-4.5425-20.956 26.76 26.76 0 0 0-30.811-9.3892c-11.881 4.5111-18.389 15.354-17.216 28.683 1.0898 12.381 10.484 20.405 28.061 23.453-2.627 1.3904-4.6503 2.4144-6.6299 3.5172a63.918 63.918 0 0 0-20.544 17.868c-1.7839 2.4084-3.0104 2.6024-5.727 0.94116-35.338-21.61-37.609-75.844 0.98226-99.453zm-26.449 133.53c-5.6769 1.441-11.178 3.5742-16.981 5.4775 2.8385-19.151 25.265-36.788 44.23-34.776a48.881 48.881 0 0 0-9.242 25.893c-6.302 1.1606-12.241 1.9414-18.007 3.405zm120.79-186.98c5.6099 0.20612 11.23 0.12091 16.844 0.25378a29.052 29.052 0 0 1 4.1674 0.58069 40.607 40.607 0 0 1-4.2357 5.4332c-2.007 1.8701-4.2745 3.6986-7.1661 0.856-0.6955-0.68372-2.3386-0.52679-3.5487-0.54272-5.5823-0.07336-11.172-0.25177-16.746-0.04132a104.04 104.04 0 0 0-14.425 1.473c-0.89368 0.16046-2.2299 3.1315-1.8191 4.227 0.9693 2.5853 2.3833 5.4363 4.4779 7.0898 7.7403 6.11 15.972 11.596 23.748 17.664 7.556 5.8966 14.589 12.358 18.875 21.253 5.5843 11.59 5.747 23.743 3.3388 35.95-4.0203 20.378-14.333 37.261-31.032 49.524-6.7288 4.941-15.06 7.7451-22.767 11.295-6.778 3.1225-13.755 5.8115-20.549 8.9008-12.249 5.5695-19.133 18.865-17.108 32.688 1.8585 12.685 12.987 23.271 25.735 25.456 15.292 2.6216 31.071-7.3163 34.812-22.86 4.2067-17.478-5.2898-33.083-23.065-37.813-0.78271-0.20831-1.5684-0.40552-3.2012-0.8269 4.7549-2.1245 8.8614-3.6381 12.653-5.7244q9.9213-5.4594 19.481-11.562c1.8742-1.199 2.8868-1.1996 4.4852 0.18225 12.225 10.57 19.518 23.718 21.563 39.839 3.3845 26.684-9.2471 51.198-33.072 63.762-36.86 19.439-81.965-2.6864-90.106-43.552-6.9738-35.003 17.73-66.754 47.462-72.884 12.787-2.6364 24.48-7.9596 33.57-17.807 5.8652-6.3541 8.7084-11.806 9.6772-14.266a39.565 39.565 0 0 0 2.7211-14.469 33.867 33.867 0 0 0-2.9654-12.398c-3.104-7.075-14.995-18.33-17.939-20.704l-28-21.921c-0.98761-0.81256-2.0994-0.75366-4.5079-0.59045-2.8611 0.19391-10.175 0.59888-13.331-0.22815 2.553-1.9321 9.5132-4.7451 12.502-7.007-9.0734-6.1297-19.43-3.9158-28.941-5.7461 2.1992-4.0959 13.081-10.39 19.27-11.091a91.533 91.533 0 0 0-1.6876-10.281c-0.37781-1.3917-1.9312-2.7408-3.2864-3.5355-3.286-1.9267-6.7694-3.5167-10.549-5.4327a21.936 21.936 0 0 1 11.332-3.5055 42.316 42.316 0 0 1 11.348 1.1056c6.7422 1.5405 12.124 0.53491 17.488-4.048-4.222-1.7002-8.4435-3.2535-12.538-5.0907a123.04 123.04 0 0 1-11.779-6.1583c10.622 1.4755 20.896 5.4585 31.757 4.0034q0.1387-0.74048 0.27728-1.4809c-8.1194-1.8899-16.239-3.7798-25.229-5.8724 15.04-1.3769 29.042-1.604 42.301 4.8541 3.731 1.8173 7.6348 3.3215 11.211 5.3972 1.7443 1.0124 2.9186 3.0078 4.3496 4.5594 1.1366 1.2325 2.0495 2.8837 3.446 3.6264 5.3 2.8184 11.134 2.9291 17.078 2.7879 0.04443-0.67694 0.08606-1.3114 0.1308-1.9933 5.9821 1.8693 12.715 8.7679 12.704 13.806-9.6911 0-19.374-0.037-29.056 0.05389-1.0348 0.0097-2.0626 0.76563-3.0936 1.1754 0.97986 0.57067 1.9428 1.5994 2.9423 1.6362z"/><path d="m185.32 26.906a1.4806 1.4806 0 0 0-0.18927 2.3686 2.2326 2.2326 0 0 0 3.0724 0.8219c0.9328-0.47052 1.8478-0.97137 2.975-1.5665-0.9079-0.775-1.6362-1.4148-2.3857-2.0324-1.318-1.086-2.411-0.40386-3.4724 0.40833z"/></g></svg></span>';

		$categories = [
			['id' => 'system',      'name' => 'System',      'icon' => 'fa-solid fa-gears',          'menu' => $system_menu,      'href' => null],
			['id' => 'wifi',        'name' => 'Wifi',        'icon' => 'fa-solid fa-wifi',           'url' => '/interfaces_wifi.php'],
			['id' => 'interfaces',  'name' => 'Interfaces',  'icon' => 'fa-solid fa-network-wired',  'menu' => $interfaces_menu,  'href' => null],
			['id' => 'wireguard',   'name' => 'WireGuard',   'icon' => null,                         'custom_icon' => $wg_custom_icon, 'url' => '/wg/vpn_wg_tunnels.php'],
			['id' => 'firewall',    'name' => 'Firewall',    'icon' => 'fa-solid fa-shield-halved',  'menu' => $firewall_menu,    'href' => null],
			['id' => 'services',    'name' => 'Services',    'icon' => 'fa-solid fa-cubes',          'menu' => $services_menu,    'href' => null],
			['id' => 'vpn',         'name' => 'VPN',         'icon' => 'fa-solid fa-key',            'menu' => $vpn_menu,         'href' => null],
			['id' => 'kvm',         'name' => 'KVM',         'icon' => 'fa-solid fa-server',         'url' => '/services_virtual.php'],
			['id' => 'diagnostics', 'name' => 'Diagnostics', 'icon' => 'fa-solid fa-toolbox',        'menu' => $diagnostics_menu, 'href' => null],
			['id' => 'terminal',    'name' => 'Terminal',    'icon' => 'fa-solid fa-terminal',       'url' => '/diag_terminal.php'],
			['id' => 'tools',       'name' => 'Tools',       'icon' => 'fa-solid fa-screwdriver-wrench', 'menu' => $tools_menu, 'href' => null],
			['id' => 'status',      'name' => 'Status',      'icon' => 'fa-solid fa-chart-line',     'menu' => $status_menu,      'href' => null],
			['id' => 'help',        'name' => $help_menu_title, 'icon' => 'fa-solid fa-circle-question','menu' => $help_menu,    'href' => '_blank']
		];

		$current_path = parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH) ?? '';
		$current_page = basename($current_path);
		$is_home = empty($current_page) || $current_page == 'index.php';
		$is_wg_page = (strpos($current_path, '/wg/') !== false || strpos($current_page, 'vpn_wg_') === 0 || $current_page == 'status_wireguard.php');

		foreach ($categories as $cat):
			if ($cat['name'] == 'Help' && g_get('disablehelpmenu')) {
				continue;
			}
			$has_sub = empty($cat['url']);
			if ($is_home || $cat['id'] == 'help') {
				// Pada index/home atau Help (link eksternal/dokumentasi), tidak boleh ada menu yang aktif
				$is_active = false;
				$menu_output = $has_sub ? output_menu($cat['menu'], $cat['href'], $cat['name']) : "";
			} elseif ($current_page == 'interfaces_wifi.php') {
				// Halaman Wifi khusus mengaktifkan menu tunggal Wifi
				$is_active = ($cat['id'] == 'wifi');
				$menu_output = $has_sub ? output_menu($cat['menu'], $cat['href'], $cat['name']) : "";
			} elseif ($current_page == 'diag_terminal.php') {
				// Halaman Terminal khusus mengaktifkan menu tunggal Terminal
				$is_active = ($cat['id'] == 'terminal');
				$menu_output = $has_sub ? output_menu($cat['menu'], $cat['href'], $cat['name']) : "";
			} elseif ($current_page == 'services_virtual.php') {
				// Halaman KVM khusus mengaktifkan menu tunggal KVM
				$is_active = ($cat['id'] == 'kvm');
				$menu_output = $has_sub ? output_menu($cat['menu'], $cat['href'], $cat['name']) : "";
			} elseif ($is_wg_page) {
				// Halaman WireGuard khusus mengaktifkan menu tunggal WireGuard
				$is_active = ($cat['id'] == 'wireguard');
				$menu_output = $has_sub ? output_menu($cat['menu'], $cat['href'], $cat['name']) : "";
			} elseif ($has_sub) {
				$menu_output = output_menu($cat['menu'], $cat['href'], $cat['name']);
				if (strlen($menu_output) == 0) continue;
				// Pencocokan persis pada nama file target di href lokal
				$is_active = (preg_match('/href=["\'][^"\'?#]*?\/' . preg_quote($current_page, '/') . '(["\'?#]|$)/i', $menu_output) === 1);
			} else {
				$menu_output = "";
				$cat_target = basename(parse_url($cat['url'] ?? '', PHP_URL_PATH) ?? '');
				$is_active = (!empty($cat_target) && $current_page == $cat_target);
			}
	?>
		<li class="sidebar-item <?=$is_active ? 'active' : ''?>">
			<?php if ($has_sub): ?>
			<a href="#sub-<?=$cat['id']?>" class="sidebar-link" aria-expanded="<?=$is_active ? 'true' : 'false'?>">
				<i class="<?=$cat['icon']?> sidebar-icon"></i>
				<span class="sidebar-text"><?=gettext($cat['name'])?></span>
				<i class="fa-solid fa-caret-right sidebar-arrow"></i>
			</a>
			<div id="sub-<?=$cat['id']?>" class="sidebar-flyout">
				<div class="flyout-header">
					<i class="<?=$cat['icon']?>"></i>
					<span><?=gettext($cat['name'])?></span>
				</div>
				<ul class="sidebar-submenu">
					<?=$menu_output?>
				</ul>
			</div>
			<?php else: ?>
			<a href="<?=$cat['url']?>" class="sidebar-link direct-link" title="<?=gettext($cat['name'])?>">
				<?php if (!empty($cat['custom_icon'])): ?>
					<?=$cat['custom_icon']?>
				<?php else: ?>
					<i class="<?=$cat['icon']?> sidebar-icon"></i>
				<?php endif; ?>
				<span class="sidebar-text"><?=gettext($cat['name'])?></span>
			</a>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
	</ul>
</aside>

<div id="pf-main-content" class="container <?=$menuclass?>" <?=$columnsContainer?>>

<?php
/* Password Security warnings */

	/* Setup user index to determine index IDs for user manager links. */
	$userindex = index_users();

	/* Gather info about the current user */
	$cu = getUserEntry($_SESSION['Username']);

	/* When logging in as non-admin user, warn about admin account password
	 * if the user has sufficient access to do correct the problem.
	 * Do not test the account if it is in a disabled state. */
	if (($_SESSION['Username'] != 'admin') &&
	    !is_account_disabled('admin') &&
	    isAllowedPage('system_usermanager.php')) {
		/* Check the admin password and cache the result in the session */
		$insecure_admin = $_SESSION['insecure_admin'];
		if (!isset($insecure_admin)) {
			$insecure_admin = check_current_password('admin');
			phpsession_begin();
			$_SESSION['insecure_admin'] = $insecure_admin;
			phpsession_end(true);
		}

		if ($insecure_admin) {
			print('<div class="alert alert-danger">' .
				sprintf(gettext('%sWARNING:%s%s The \'admin\' account password is insecure. %s%s' .
				' %sChange the password in the User Manager as soon as possible.%s'),
				'<strong>', '</strong>', '<br/>',
				$insecure_admin, '<br/>',
				'<a href="/system_usermanager.php?act=edit&userid=' . $userindex['admin'] . '">', '</a>') .
				'</div>');
		}
	}

	/* When logging in as any user, check the password for their account and cache the result in the session. */
	$insecure_user = $_SESSION['insecure_user'];
	if (!isset($insecure_user)) {
		$insecure_user = check_current_password($_SESSION['Username']);
		phpsession_begin();
		$_SESSION['insecure_user'] = $insecure_user;
		phpsession_end(true);
	}

	/* Determine where to send the user to change their password
	 * based on their account privileges. */
	$target_page = "";
	if (isAllowedPage('system_usermanager_passwordmg.php')) {
		/* User has access to the self-service password manager, which
		 * is the ideal destination. */
		$target_page = 'system_usermanager_passwordmg.php';
	} elseif (isAllowedPage('system_usermanager.php')) {
		/* User does not have access to the self-service page but has
		 * access to the main user manager page, so use that instead. */
		$target_page = 'system_usermanager.php?act=edit&userid=' . $userindex[$_SESSION['Username']];
	}

	/* Print a warning only if the user has sufficient access to change
	 * their own password (non-empty target page). */
	if ($insecure_user &&
	    !empty($target_page)) {
		print('<div class="alert alert-danger">' .
			sprintf(gettext('%sWARNING:%s%s The password for this account is insecure. %s%s' .
			' %sChange the password as soon as possible.%s'),
			'<strong>', '</strong>', '<br/>',
			$insecure_user, '<br/>',
			'<a href="/' . $target_page . '">', '</a>') .
			'</div>');
	}

/* End Password Security Warnings */
?>

	<header class="header">

<?php
	// If you set $notitle = true BEFORE including head.inc, the page title will be supressed
	if (isset($notitle)) {
		print('<br />');
		unset($notitle);
	} else {
		if (isset($pglinks)) {
			print(genhtmltitle($pgtitle, $pglinks));
		} else {
			print(genhtmltitle($pgtitle));
		}
	}
?>
		<ul class="context-links">

	<?php if (isset($widgets)): ?>
		<li>
			<a href="#" title="<?=gettext("Save dashboard layout")?>" id="btnstore" class="invisible">
				<i class="fa-solid fa-save icon-pointer"></i>
			</a>
		</li>
	<?php endif?>

	<?php if ($dashboard_available_widgets_hidden): ?>
		<li>
			<a onclick="$('#widget-available').toggle(360);" title="<?=gettext("Available widgets")?>">
				<i class="fa-solid fa-plus icon-pointer"></i>
			</a>
		</li>
	<?php endif?>

	<?php if ($system_logs_filter_form_hidden): ?>
		<li>
			<a onclick="$('#filter-form').toggle(360)" title="<?=gettext("Log filter")?>">
				<i class="fa-solid fa-filter icon-pointer"></i>
			</a>
		</li>
	<?php endif ?>

	<?php if ($system_logs_manage_log_form_hidden):
			/* If the user does not have access to status logs settings page, then exclude the manage log panel icon from the title bar. */
			if (isAllowedPage("status_logs_settings.php")) {
	?>
		<li>
			<a onclick="$('#manage-log-form').toggle(360)" title="<?=gettext("Manage log")?>">
				<i class="fa-solid fa-wrench icon-pointer"></i>
			</a>
		</li>
	<?php	}
		endif
	?>

	<?php if ($monitoring_settings_form_hidden): ?>
		<li>
			<a onclick="$('#monitoring-settings-form').toggle(360);" title="<?=gettext("Settings")?>">
				<i class="fa-solid fa-wrench icon-pointer"></i>
			</a>
		</li>
	<?php endif?>

	<?php if ($status_monitoring): ?>
		<li>
			<a class="update-graph" title="<?=gettext("Refresh Graph")?>">
				<i class="fa-solid fa-arrow-rotate-right icon-pointer"></i>
			</a>
		</li>
		<li>
			<a class="export-graph" id="export-graph" title="<?=gettext("Export Graph")?>">
				<i class="fa-solid fa-download icon-pointer"></i>
			</a>
		</li>
	<?php endif?>

<?php
/* Determine shortcut section for XML-based packages */
if (empty($shortcut_section) && !empty($xmlfile)) {
	$shortcut_section = basename($pagename, '.xml');
}

if (!$hide_service_status && !empty($shortcuts[$shortcut_section]['service']) && isAllowedPage('status_services.php')) {
	$ssvc = array();
	switch ($shortcut_section) {
		case "openvpn":
			$ssvc = find_service_by_openvpn_vpnid($vpnid);
			break;
		case "captiveportal":
			$ssvc = find_service_by_cp_zone($cpzone);
			break;
		default:
			$ssvc = find_service_by_name($shortcuts[$shortcut_section]['service']);
	}
	if (!empty($ssvc)) {
		// echo '<li>'. get_service_status_icon($ssvc, false). '</li>'; TODO: Add missing function
		echo '<li>'. get_service_control_links($ssvc, false). '</li>';
	}
}

if (('' != ($link = get_shortcut_main_link($shortcut_section, false))) && (isAllowedPage($shortcuts[$shortcut_section]['main']))) {
	echo '<li>' . $link . '</li>';
}

if (('' != ($link = get_shortcut_status_link($shortcut_section, false))) && (isAllowedPage($shortcuts[$shortcut_section]['status']))) {
	echo '<li>' . $link . '</li>';
}

if (('' != ($link = get_shortcut_log_link($shortcut_section, false))) && (isAllowedPage($shortcuts[$shortcut_section]['log']))) {
	echo '<li>' . $link . '</li>';
}

?>
	<?php if (!g_get('disablehelpicon') && isAllowedPage("help.php")): ?>
		<li>
			<a href="<?=$helpurl?>" target="_blank" title="<?=gettext("Help for items on this page")?>">
				<i class="fa-solid fa-question-circle"></i>
			</a>
		</li>
	<?php endif?>
		</ul>
	</header>
<?php
/* if upgrade in progress, alert user */
$warning_text = "";
if (file_exists('/conf/needs_package_sync') && is_platform_booting()) {
	$warning_text = sprintf(gettext(
	    '%1$s%3$s is booting, then packages will be reinstalled in the ' .
	    'background.%2$s%1$sDo not make changes in the GUI until this is ' .
	    'complete.%2$s'), '<p>', '</p>', g_get('product_label'));
} elseif (is_subsystem_dirty('packagelock')) {
	$pgtitle = array(gettext("System"), gettext("Package Manager"));
	$warning_text = sprintf(gettext('%1$sPackages are currently being ' .
	    'reinstalled in the background.%2$s%1$sDo not make changes in ' .
	    'the GUI until this is complete.%2$s'), '<p>', '</p>');
	$warning_text .= sprintf(gettext('%1$sIf the above message is still ' .
	    'displayed after a couple of hours, use the \'Clear Package ' .
	    'Lock\' button on the %3$s page and reinstall packages manually.' .
	    '%2$s'), '<p>', '</p>', sprintf('<a href="diag_backup.php" ' .
	    'title="%1$s &gt; %2$s">%1$s &gt; %2$s</a>', gettext('Diagnostics'),
	    htmlspecialchars(gettext('Backup & Restore'))));
}

if (!empty($warning_text)) {
	print_info_box($warning_text);
}

/*	If this page is being remotely managed then do not allow the loading of the contents. */
if (config_get_path('remote_managed_pages/item')) {
	foreach (config_get_path('remote_managed_pages/item', []) as $rmp) {
		if ($rmp == $_SERVER['SCRIPT_NAME']) {
			print_info_box(gettext("This page is currently being managed by a remote machine."));
			include("foot.inc");
			exit;
		}
	}
}

// Modal notices window
// The notices modal needs to be outside of the page display div or things get messy
if ($display_notices):
?>

<div id="notices" class="modal fade" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>

				<h3 class="modal-title" id="myModalLabel"><?=gettext("Notices")?></h3>
			</div>

			<div class="modal-body">
<?php
	$noticeCategories = array();

	if (is_array($notices)) {
		foreach ($notices as $time => $notice) {
			if (!isset($noticeCategories[ $notice['category'] ])) {
				$noticeCategories[ $notice['category'] ] = array();
			}

			$notice['time'] = $time;
			array_push($noticeCategories[ $notice['category'] ], $notice);
		}
	}

	foreach ($noticeCategories as $category => $catNotices):?>
				<h4><?=$category?></h4>
				<ul>
<?php
	foreach ($catNotices as $notice):
?>
					<li>
						<b>
<?php if (!empty($notice['url'])):?>
							<a href="<?=htmlspecialchars($notice['url'])?>"><?=htmlspecialchars($notice['id'])?></a> -
<?php endif;?>
						</b>
						<?=str_replace("\n", "<br/>", htmlspecialchars(htmlspecialchars_decode(htmlspecialchars_decode($notice['notice']))))?>
						<i>@ <?=date('Y-m-d H:i:s', $notice['time'])?></i>
					</li>
<?php	endforeach;?>
				</ul>
<?php endforeach;?>
			</div>

			<div class="modal-footer">
				<button type="button" class="btn btn-info" data-dismiss="modal"><i class="fa-solid fa-times icon-embed-btn"></i><?=gettext("Close")?></button>
<?php if ($allow_clear_notices && isAllowedPage("/index.php")):?>
				<button type="button" id="clearallnotices" class="btn btn-primary"><i class="fa-regular fa-trash-can icon-embed-btn"></i><?=gettext("Mark All as Read")?></button>
<?php endif;?>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
//<![CDATA[
	events.push(function() {
	    $('#clearallnotices').click(function() {
			ajaxRequest = $.ajax({
				url: "/index.php",
				type: "post",
				data: { closenotice: "all"},
				success: function() {
					window.location = window.location.href;
				},
				failure: function() {
					alert("Error clearing notices!");
				}
			});
		});
	});
//]]>
</script>

<?php
endif; // ($display_notices)

// Get the flash Messages
get_flash_message();
