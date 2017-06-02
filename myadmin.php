<?php
/* TODO:
 - service type, category, and services  adding
 - dealing with the SERVICE_TYPES_vps define
 - add way to call/hook into install/uninstall
*/
return [
	'name' => 'Vps Licensing VPS Addon',
	'description' => 'Allows selling of Vps Server and VPS License Types.  More info at https://www.netenberg.com/vps.php',
	'help' => 'It provides more than one million end users the ability to quickly install dozens of the leading open source content management systems into their web space.  	Must have a pre-existing cPanel license with cPanelDirect to purchase a vps license. Allow 10 minutes for activation.',
	'module' => 'vps',
	'author' => 'detain@interserver.net',
	'home' => 'https://github.com/detain/myadmin-vps-vps-addon',
	'repo' => 'https://github.com/detain/myadmin-vps-vps-addon',
	'version' => '1.0.0',
	'type' => 'addon',
	'hooks' => [
		'vps.load_processing' => ['Detain\MyAdminVps\Plugin', 'Load'],
		/* 'function.requirements' => ['Detain\MyAdminVps\Plugin', 'Requirements'],
		'licenses.settings' => ['Detain\MyAdminVps\Plugin', 'Settings'],
		'licenses.activate' => ['Detain\MyAdminVps\Plugin', 'Activate'],
		'licenses.change_ip' => ['Detain\MyAdminVps\Plugin', 'ChangeIp'],
		'ui.menu' => ['Detain\MyAdminVps\Plugin', 'Menu'] */
	],
];
