#!/usr/bin/env php
<?php
$webpage = false;
define('VERBOSE_MODE', false);
require_once __DIR__.'/../../include/functions.inc.php';
function_requirements('ipcalc');
$module = 'vps';
$db = get_module_db($module);
$db2 = get_module_db($module);
$settings = get_module_settings($module);
$db->query("select * from vlans");
$ips = [];
$vlans = [];
while ($db->next_record(MYSQL_ASSOC)) {
	$vlan = str_replace(':', '', $db->Record['vlans_networks']);
	$ipinfo = ipcalc($vlan);
	$min = ip2long($ipinfo['hostmin']);
	$max = ip2long($ipinfo['hostmax']);
	$vlans[$db->Record['vlans_id']] = $db->Record;
	for ($x = $min; $x <= $max; $x++) {
		$ip = sprintf("%u", $x);
		$ips[$ip] = $db->Record['vlans_id'];
	}
}
echo count($ips)." IPs parsed from ".count($vlans)." VLANs\n";
foreach (['vps', 'qs'] as $prefix) {
	$db->query("select * from {$prefix}_ips");
	while ($db->next_record(MYSQL_ASSOC)) {
		$ip = sprintf("%u", ip2long($db->Record['ips_ip']));
		if (!array_key_exists($ip, $ips)) {
			echo "No match for {$prefix} IP ".$db->Record['ips_ip']."\n";
			print_r($db->Record);
			$db2->query("delete from {$prefix}_ips where ips_ip='{$db->Record['ips_ip']}'");
		}
	}
}