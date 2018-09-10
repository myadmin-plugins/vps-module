<?php

namespace Detain\MyAdminVps;

use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Plugin
 *
 * @package Detain\MyAdminVps
 */
class Plugin
{
	public static $name = 'VPS Servers';
	public static $description = 'Allows selling of Vps Module';
	public static $help = '';
	public static $module = 'vps';
	public static $type = 'module';
	public static $settings = [
		'SERVICE_ID_OFFSET' => 0,
		'USE_REPEAT_INVOICE' => true,
		'USE_PACKAGES' => true,
		'BILLING_DAYS_OFFSET' => 0,
		'IMGNAME' => 'root-server.png',
		'REPEAT_BILLING_METHOD' => PRORATE_BILLING,
		'DELETE_PENDING_DAYS' => 45,
		'SUSPEND_DAYS' => 14,
		'SUSPEND_WARNING_DAYS' => 7,
		'TITLE' => 'VPS',
		'MENUNAME' => 'VPS',
		'EMAIL_FROM' => 'support@interserver.net',
		'TBLNAME' => 'VPS',
		'TABLE' => 'vps',
		'TITLE_FIELD' => 'vps_hostname',
		'TITLE_FIELD2' => 'vps_ip',
		'PREFIX' => 'vps'];

	/**
	 * Plugin constructor.
	 */
	public function __construct()
	{
	}

	/**
	 * @return array
	 */
	public static function getHooks()
	{
		return [
			self::$module.'.load_processing' => [__CLASS__, 'loadProcessing'],
			self::$module.'.load_addons' => [__CLASS__, 'getAddon'],
			self::$module.'.settings' => [__CLASS__, 'getSettings']
		];
	}


	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getAddon(GenericEvent $event)
	{
		/**
		 * @var \ServiceHandler $service
		 */
		$service = $event->getSubject();
		function_requirements('class.AddonHandler');
		$addon = new \AddonHandler();
		$addon->setModule(self::$module)
			->set_text('Slice Upgrade')
			->set_text_match('(.*) Slice Upgrade')
			->set_require_ip(false)
			->setOneTime(true)
			->setEnable([__CLASS__, 'doSliceEnable'])
			->register();
		$service->addAddon($addon);
	}

	/**
	 * @param \ServiceHandler $serviceOrder
	 * @param                $repeatInvoiceId
	 * @param bool           $regexMatch
	 */
	public static function doSliceEnable(\ServiceHandler $serviceOrder, $repeatInvoiceId, $regexMatch = false)
	{
		$deferUpgradeViaTicket = true;
		$serviceInfo = $serviceOrder->getServiceInfo();
		$serviceTypes = run_event('get_service_types', false, self::$module);
		$settings = get_module_settings(self::$module);
		$slices = $regexMatch;
		myadmin_log(self::$module, 'info', self::$name." Setting {$slices} Slices for {$settings['TBLNAME']} {$serviceInfo[$settings['PREFIX'].'_id']}", __LINE__, __FILE__);
		function_requirements('get_coupon_cost');
		$slice_cost = $serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_cost'];
		$slice_cost = get_coupon_cost($slice_cost, $serviceInfo[$settings['PREFIX'].'_coupon']);
		$slice_cost = round($slice_cost * get_frequency_discount($serviceInfo[$settings['PREFIX'].'_frequency']), 2);
		$db = get_module_db(self::$module);
		$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_cost='".($slice_cost * $slices)."', {$settings['PREFIX']}_slices='{$slices}' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
		$db->query("update repeat_invoices set repeat_invoices_description='{$serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name']} {$slices} Slices', repeat_invoices_cost='".($slice_cost * $slices)."' where repeat_invoices_id='{$serviceInfo[$settings['PREFIX'].'_invoice']}'", __LINE__, __FILE__);
		$db->query("update invoices set invoices_description='(Repeat Invoice: {$serviceInfo[$settings['PREFIX'].'_invoice']}) {$serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name']} {$slices} Slices', invoices_amount='".($slice_cost * $slices)."' where invoices_type=1 and invoices_paid=0 and invoices_extra='{$serviceInfo[$settings['PREFIX'].'_invoice']}'", __LINE__, __FILE__);
		if (!in_array($serviceInfo['vps_status'], ['pending'])) {
			if ($deferUpgradeViaTicket == true) {
				add_output('Thank you for your upgrade request. A ticket has been automatically opened for you. Please allow us 24 hours to complete your upgrade. You can check the status of your ticket here');
				function_requirements('create_ticket');
				create_ticket($GLOBALS['tf']->accounts->cross_reference($serviceInfo[$settings['PREFIX'].'_custid']), "VPS {$serviceInfo[$settings['PREFIX'].'_id']} has paid for and needs a slice upgrade to {$slices} slices.", "VPS {$serviceInfo[$settings['PREFIX'].'_id']} Slice Upgrade");
			} else {
				$GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'set_slices', $slices, $serviceInfo[$settings['PREFIX'].'_custid']);
				add_output('Update has been sent to the server');
			}
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function loadProcessing(GenericEvent $event)
	{
		/**
		 * @var \ServiceHandler $service
		 */
		$service = $event->getSubject();
		$service->setModule(self::$module)
			->setEnable(function ($service) {
				$serviceInfo = $service->getServiceInfo();
				$settings = get_module_settings(self::$module);
				$db = get_module_db(self::$module);
				$db->query('update '.$settings['TABLE'].' set '.$settings['PREFIX']."_status='pending-setup' where ".$settings['PREFIX']."_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
				$GLOBALS['tf']->history->add($settings['PREFIX'], 'change_status', 'pending-setup', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
				$GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'initial_install', '', $serviceInfo[$settings['PREFIX'].'_custid']);
				admin_email_vps_pending_setup($serviceInfo[$settings['PREFIX'].'_id']);
			})->setReactivate(function ($service) {
				$serviceTypes = run_event('get_service_types', false, self::$module);
				$serviceInfo = $service->getServiceInfo();
				$settings = get_module_settings(self::$module);
				$db = get_module_db(self::$module);
				if ($serviceInfo[$settings['PREFIX'].'_server_status'] === 'deleted' || $serviceInfo[$settings['PREFIX'].'_ip'] == '') {
					$GLOBALS['tf']->history->add($settings['PREFIX'], 'change_status', 'pending-setup', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
					$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='pending-setup' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
					$GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'initial_install', '', $serviceInfo[$settings['PREFIX'].'_custid']);
				} else {
					$GLOBALS['tf']->history->add($settings['PREFIX'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
					$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='active' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
					$GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'enable', '', $serviceInfo[$settings['PREFIX'].'_custid']);
					$GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'start', '', $serviceInfo[$settings['PREFIX'].'_custid']);
				}
				$smarty = new \TFSmarty;
				$smarty->assign('vps_name', $serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name']);
				$email = $smarty->fetch('email/admin/vps_reactivated.tpl');
				$subject = $serviceInfo[$settings['TITLE_FIELD']].' '.$serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name'].' '.$settings['TBLNAME'].' Re-Activated';
				$headers = '';
				$headers .= 'MIME-Version: 1.0'.PHP_EOL;
				$headers .= 'Content-type: text/html; charset=UTF-8'.PHP_EOL;
				$headers .= 'From: '.TITLE.' <'.EMAIL_FROM.'>'.PHP_EOL;
				admin_mail($subject, $email, $headers, false, 'admin/vps_reactivated.tpl');
			})->setDisable(function ($service) {
			})->setTerminate(function ($service) {
				$serviceInfo = $service->getServiceInfo();
				$settings = get_module_settings(self::$module);
				$serviceTypes = run_event('get_service_types', false, self::$module);
				$settings = get_module_settings(self::$module);
				$class = '\\MyAdmin\\Orm\\'.get_orm_class_from_table($settings['TABLE']);
				$ips = [];
				$db = get_module_db(self::$module);
				$db->query("select * from {$settings['PREFIX']}_ips where ips_{$settings['PREFIX']}='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
				while ($db->next_record(MYSQL_ASSOC)) {
					if (!in_array($db->Record['ips_ip'], $ips)) {
						$ips[] = $db->Record['ips_ip'];
					}
				}
				$db->query("update {$settings['PREFIX']}_ips set ips_main=0,ips_usable=1,ips_used=0,ips_{$settings['PREFIX']}=0 where ips_{$settings['PREFIX']}='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
				function_requirements('reverse_dns');
				foreach ($ips as $ip) {
					if (validIp($ip)) {
						reverse_dns($ip, '', 'remove_reverse');
					}
				}
				$GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'destroy', '', $serviceInfo[$settings['PREFIX'].'_custid']);
			})->register();
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getSettings(GenericEvent $event)
	{
		$settings = $event->getSubject();
		$settings->add_text_setting(self::$module, 'Credentials', 'webuzo_license_key', 'Webuzo License Key:', 'API Credentials for Webuozo', $settings->get_setting('WEBUZO_LICENSE_KEY'));
		$settings->add_text_setting(self::$module, 'Slice Costs', 'vps_ny_cost', 'VPS NY4 Multiplier:', 'This is the multiplier to a normal cost for an item to be hosted in NY.', $settings->get_setting('VPS_NY_COST'));
		$settings->add_text_setting(self::$module, 'Slice Amounts', 'vps_slice_ram', 'Ram Per Slice:', 'Amount of ram in MB per VPS Slice', $settings->get_setting('VPS_SLICE_RAM'));
		$settings->add_text_setting(self::$module, 'Slice Amounts', 'vps_slice_hd', 'HD Space Per Slice:', 'Amount of HD space in GB per VPS Slice', $settings->get_setting('VPS_SLICE_HD'));
		$settings->add_dropdown_setting(self::$module, 'Slice Amounts', 'vps_bw_type', 'Bandwidth Limited by Total Traffic or Throttling', 'Enable/Disable Sales Of This Type', $settings->get_setting('VPS_BW_TYPE'), ['1', '2'], ['Throttled in mbps', 'Total GBytes Used']);
		$settings->add_text_setting(self::$module, 'Slice Amounts', 'vps_slice_bw', 'Bandwidth Limit Per Slice in Mbits/s  or Gbytes:', 'Amount of Bandwidth per slice.', $settings->get_setting('VPS_SLICE_BW'));
		$settings->add_text_setting(self::$module, 'Slice Amounts', 'vps_slice_max', 'Max Slices Per Order:', 'Maximum amount of slices any one VPS can be.', $settings->get_setting('VPS_SLICE_MAX'));
		$settings->add_master_checkbox_setting(self::$module, 'Server Settings', self::$module, 'available', 'vps_available', 'Auto-Setup', '<p>Choose which servers are used for auto-server Setups.</p>');
		$settings->add_master_label(self::$module, 'Server Settings', self::$module, 'active_services', 'Active VPS', '<p>The current number of active VPS.</p>', 'count(vps.vps_id) as active_services');
		$settings->add_master_text_setting(self::$module, 'Server Settings', self::$module, 'server_max', 'vps_server_max', 'Max VPS', '<p>The Maximum number of VPS that can be running on each server.</p>');
		$settings->add_master_label(self::$module, 'Server Settings', self::$module, 'active_slices', 'Active Slices', '<p>The current total slices from active VPS.</p>', 'sum(vps.vps_slices) as active_slices');
		$settings->add_master_text_setting(self::$module, 'Server Settings', self::$module, 'server_max_slices', 'vps_server_max_slices', 'Max Slices', '<p>The Maximum number of total slices that can be running on each server.</p>');
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_vps', 'Out Of Stock VPS', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_VPS'), ['0', '1'], ['No', 'Yes']);
	}
}
