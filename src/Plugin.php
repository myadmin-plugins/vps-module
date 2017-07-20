<?php

namespace Detain\MyAdminVps;

use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Plugin
 *
 * @package Detain\MyAdminVps
 */
class Plugin {

	public static $name = 'VPS Servers';
	public static $description = 'Allows selling of Vps Module';
	public static $help = '';
	public static $module = 'vps';
	public static $type = 'module';
	public static $settings = [
		'SERVICE_ID_OFFSET' => 0,
		'USE_REPEAT_INVOICE' => TRUE,
		'USE_PACKAGES' => TRUE,
		'BILLING_DAYS_OFFSET' => 0,
		'IMGNAME' => 'server_add_48.png',
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
	public function __construct() {
	}

	/**
	 * @return array
	 */
	public static function getHooks() {
		return [
			self::$module.'.load_processing' => [__CLASS__, 'loadProcessing'],
			self::$module.'.settings' => [__CLASS__, 'getSettings']
		];
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function loadProcessing(GenericEvent $event) {
		$service = $event->getSubject();
		$service->setModule(self::$module)
			->set_enable(function($service) {
				$serviceInfo = $service->getServiceInfo();
				$settings = get_module_settings(self::$module);
				$db = get_module_db(self::$module);
				$db->query('update ' . $settings['TABLE']. ' set ' . $settings['PREFIX']."_status='pending-setup' where ". $settings['PREFIX']."_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
				$GLOBALS['tf']->history->add($settings['PREFIX'], 'change_status', 'pending-setup', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
				$GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'initial_install', '', $serviceInfo[$settings['PREFIX'].'_custid']);
				admin_email_vps_pending_setup($serviceInfo[$settings['PREFIX'].'_id']);
			})->set_reactivate(function($service) {
				$serviceTypes = run_event('get_service_types', FALSE, self::$module);
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
				$email = $smarty->fetch('email/admin_email_vps_reactivated.tpl');
				$subject = $serviceInfo[$settings['TITLE_FIELD']].' '.$serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name'].' '.$settings['TBLNAME'].' Re-Activated';
				$headers = '';
				$headers .= 'MIME-Version: 1.0'.EMAIL_NEWLINE;
				$headers .= 'Content-type: text/html; charset=UTF-8'.EMAIL_NEWLINE;
				$headers .= 'From: '.TITLE.' <'.EMAIL_FROM.'>'.EMAIL_NEWLINE;
				admin_mail($subject, $email, $headers, FALSE, 'admin_email_vps_reactivated.tpl');
			})->set_disable(function($service) {
			})->register();
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getSettings(GenericEvent $event) {
		$settings = $event->getSubject();
		$settings->add_text_setting(self::$module, 'Credentials', 'webuzo_license_key', 'Webuzo License Key:', 'API Credentials for Webuozo', $settings->get_setting('WEBUZO_LICENSE_KEY'));
		$settings->add_text_setting(self::$module, 'Slice Costs', 'vps_ny_cost', 'VPS NY4 Multiplier:', 'This is the multiplier to a normal cost for an item to be hosted in NY.', $settings->get_setting('VPS_NY_COST'));
		$settings->add_text_setting(self::$module, 'Slice Amounts', 'vps_slice_ram', 'Ram Per Slice:', 'Amount of ram in MB per VPS Slice', $settings->get_setting('VPS_SLICE_RAM'));
		$settings->add_text_setting(self::$module, 'Slice Amounts', 'vps_slice_hd', 'HD Space Per Slice:', 'Amount of HD space in GB per VPS Slice', $settings->get_setting('VPS_SLICE_HD'));
		$settings->add_text_setting(self::$module, 'Slice Amounts', 'vps_bw_type', 'Bandwidth Limited by Total Traffic or Throttling', 'Enable/Disable Sales Of This Type', $settings->get_setting('VPS_BW_TYPE'), ['1', '2'], ['Throttled in mbps', 'Total GBytes Used']);
		$settings->add_text_setting(self::$module, 'Slice Amounts', 'vps_slice_bw', 'Bandwidth Limit Per Slice in Mbits/s  or Gbytes:', 'Amount of Bandwidth per slice.', $settings->get_setting('VPS_SLICE_BW'));
		$settings->add_text_setting(self::$module, 'Slice Amounts', 'vps_slice_max', 'Max Slices Per Order:', 'Maximum amount of slices any one VPS can be.', $settings->get_setting('VPS_SLICE_MAX'));
		$settings->add_select_master_autosetup(self::$module, 'Auto-Setup Servers', self::$module, 'vps_setup_servers', 'Auto-Setup Servers:', '<p>Choose which servers are used for auto-server Setups.</p>');
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_vps', 'Out Of Stock VPS', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_VPS'), ['0', '1'], ['No', 'Yes']);
	}
}
