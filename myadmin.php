<?php
/* TODO:
 - service type, category, and services  adding
 - dealing with the SERVICE_TYPES_vps define
 - add way to call/hook into install/uninstall
*/
return [
	'name' => 'MyAdmin VPS Module',
	'description' => 'Allows selling of Vps Module',
	'help' => '',
	'module' => 'vps',
	'author' => 'detain@interserver.net',
	'home' => 'https://github.com/detain/myadmin-vps-module',
	'repo' => 'https://github.com/detain/myadmin-vps-module',
	'version' => '1.0.0',
	'type' => 'module',
	'hooks' => [
		'vps.load_processing' => ['Detain\MyAdminVps\Plugin', 'Load'],
		'vps.settings' => ['Detain\MyAdminVps\Plugin', 'Settings'],
		/* 'function.requirements' => ['Detain\MyAdminVps\Plugin', 'Requirements'],
		'vps.activate' => ['Detain\MyAdminVps\Plugin', 'Activate'],
		'vps.change_ip' => ['Detain\MyAdminVps\Plugin', 'ChangeIp'],
		'ui.menu' => ['Detain\MyAdminVps\Plugin', 'Menu'] */
	],
];
