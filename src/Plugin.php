<?php

namespace Detain\MyAdminVps;

use Symfony\Component\EventDispatcher\GenericEvent;
use Punic\Currency;
use Brick\Money\Money;

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
            'api.register' => [__CLASS__, 'apiRegister'],
            'function.requirements' => [__CLASS__, 'getRequirements'],
            self::$module.'.load_processing' => [__CLASS__, 'loadProcessing'],
            self::$module.'.load_addons' => [__CLASS__, 'getAddon'],
            self::$module.'.settings' => [__CLASS__, 'getSettings']
        ];
    }

    /**
     * @param \Symfony\Component\EventDispatcher\GenericEvent $event
     */
    public static function getRequirements(GenericEvent $event)
    {
        $loader = $event->getSubject();
        $loader->add_requirement('api_validate_buy_vps', '/../vendor/detain/myadmin-vps-module/src/api.php');
        $loader->add_requirement('api_buy_vps', '/../vendor/detain/myadmin-vps-module/src/api.php');
        $loader->add_requirement('api_buy_vps_admin', '/../vendor/detain/myadmin-vps-module/src/api.php');
    }

    /**
     * @param \Symfony\Component\EventDispatcher\GenericEvent $event
     */
    public static function apiRegister(GenericEvent $event)
    {
        /**
         * @var \ServiceHandler $subject
         */
        //$subject = $event->getSubject();
        api_register_array('vps_slice_type', ['name' => 'string', 'type' => 'int', 'cost' => 'float', 'buyable' => 'int']);
        api_register_array('idNameArray', ['id' => 'int', 'name' => 'string']);
        api_register_array('idNameSizeUrlArray', ['id' => 'int', 'name' => 'string', 'size' => 'int', 'url' => 'string']);
        api_register_array('vps_template', ['type' => 'int', 'virtulization' => 'string', 'bits' => 'int', 'os' => 'string', 'version' => 'string', 'file' => 'string', 'title' => 'string']);
        api_register_array('vps_platform', ['platform' => 'string', 'name' => 'string']);
        api_register_array('vps_screenshot_return', ['status' => 'string', 'status_text' => 'string', 'url' => 'string', 'link' => 'string', 'js' => 'string']);
        api_register_array('buy_vps_result_status', ['status' => 'string', 'status_text' => 'string', 'invoices' => 'string', 'cost' => 'float']);
        api_register_array('validate_buy_vps_result_status', ['coupon_code' => 'int', 'service_cost' => 'float', 'slice_cost' => 'float', 'service_type' => 'int', 'repeat_slice_cost' => 'float', 'original_slice_cost' => 'float', 'original_cost' => 'float', 'repeat_service_cost' => 'float', 'monthly_service_cost' => 'float', 'custid' => 'int', 'os' => 'string', 'slices' => 'int', 'platform' => 'string', 'controlpanel' => 'string', 'period' => 'int', 'location' => 'int', 'version' => 'string', 'hostname' => 'string', 'coupon' => 'string', 'rootpass' => 'string', 'status_text' => 'string', 'status' => 'string']);
        //api_register('vps_queue_stop', ['sid' => 'string', 'id' => 'int'], ['return' => 'result_status'], 'Cancel a License.', true, false);
        api_register('vps_queue_stop', ['id' => 'int'], ['return' => 'result_status'], 'stops a vps', true);
        api_register('vps_queue_start', ['id' => 'int'], ['return' => 'result_status'], 'start a vps', true);
        api_register('vps_queue_restart', ['id' => 'int'], ['return' => 'result_status'], 'restart a vps', true);

        // VPS Backups Related Code
        api_register('vps_queue_backup', ['id' => 'int', 'name' => 'string'], ['return' => 'result_status'], 'initializes a backup of a vps calling the backup the name parameter or "snap" if blank', true);
        api_register('vps_backup_delete', ['id' => 'int', 'name' => 'string'], ['return' => 'result_status'], 'deletes one of the vps backups', true);
        api_register('get_vps_backups', [], ['return' => 'array:idNameSizeUrlArray'], 'Returns a list of all the current VPS backups indicating the VPS ID, the Name of the backup, file size, and a download URL', true);
        //api_register('vps_queue_restore', ['id' => 'int', 'name' => 'string'], ['return' => 'result_status'], 'initializes a restoration of a vps calling the backup the name parameter or "snap" if blank', true);
        //api_register('get_vps_backup', ['id' => 'int', 'name' => 'string'], ['return' => 'string'], 'Returns a downloaded copy of the backup', true);
        //api_register('get_vps_backup_url', ['id' => 'int', 'name' => 'string'], ['return' => 'string'], 'Returns a sharable HTTP link to the backup downloaded', true);

        api_register('get_vps_slice_types', [], ['return' => 'array:vps_slice_type'], 'We have several types of Servers available for use with VPS Hosting. You can get a list of the types available and  there cost per slice/unit by making a call to this function', false);
        api_register('get_vps_locations_array', [], ['return' => 'array:idNameArray'], 'Use this function to get a list of the Locations available for ordering. The id field in the return value is also needed to pass to the buy_vps functions.', false);
        api_register('get_vps_templates', [], ['return' => 'array:vps_template'], 'Get the currently available VPS templates for each server type.', false);
        //api_register('get_vps_platforms', [], ['return' => 'array'], 'Get the currently available VPS platforms.', FALSE);
        api_register('get_vps_platforms_array', [], ['return' => 'array:vps_platform'], 'Use this function to get a list of the various platforms available for ordering. The platform field in the return value is also needed to pass to the buy_vps functions.', false);
        api_register('api_validate_buy_vps', ['os' => 'string', 'slices' => 'int', 'platform' => 'string', 'controlpanel' => 'string', 'period' => 'int', 'location' => 'int', 'version' => 'string', 'hostname' => 'string', 'coupon' => 'string', 'rootpass' => 'string'], ['return' => 'validate_buy_vps_result_status'], 'Checks if the parameters for your order pass validation and let you know if there are any errors. It will also give you information on the pricing breakdown.');
        api_register('api_buy_vps', ['os' => 'string', 'slices' => 'int', 'platform' => 'string', 'controlpanel' => 'string', 'period' => 'int', 'location' => 'int', 'version' => 'string', 'hostname' => 'string', 'coupon' => 'string', 'rootpass' => 'string'], ['return' => 'buy_vps_result_status'], 'Places a VPS order in our system. These are the same parameters as api_validate_buy_vps..   Returns a comma seperated list of invoices if any need paid.');
        api_register('api_buy_vps_admin', ['os' => 'string', 'slices' => 'int', 'platform' => 'string', 'controlpanel' => 'string', 'period' => 'int', 'location' => 'int', 'version' => 'int', 'hostname' => 'string', 'coupon' => 'string', 'rootpass' => 'string', 'server' => 'int'], ['return' => 'buy_vps_result_status'], 'Purchase a VPS (admins only).   Returns a comma seperated list of invoices if any need paid.  Same as client function but allows specifying which server to install to if there are resources available on the specified server.');
        api_register('vps_screenshot', ['id' => 'int'], ['return' => 'vps_screenshot_return'], 'This command returns a link to an animated screenshot of your VPS.   Only works currently with KVM VPS servers');
        api_register('vps_get_server_name', ['id' => 'int'], ['return' => 'string'], 'Get the name of the vps master/host server your giving the id for');
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
        $slices = (int)$regexMatch;
        myadmin_log(self::$module, 'info', self::$name." Setting {$slices} Slices for {$settings['TBLNAME']} {$serviceInfo[$settings['PREFIX'].'_id']}", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
        function_requirements('get_coupon_cost');
        $slice_cost = $serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_cost'];
        $slice_cost = get_coupon_cost($slice_cost, $serviceInfo[$settings['PREFIX'].'_coupon']);
        $costInfo = get_service_cost($serviceInfo, self::$module);
        $slice_cost = round($slice_cost * get_frequency_discount($costInfo['frequency']), 2);
        $db = get_module_db(self::$module);
        $db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_slices='{$slices}' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
        $q1 = "update {$settings['TABLE']} set {$settings['PREFIX']}_slices='{$slices}' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'";
        $GLOBALS['tf']->history->add('query_log', 'update', $serviceInfo[$settings['PREFIX'].'_id'], $q1, $serviceInfo[$settings['PREFIX'].'_custid']);
        $repeatInvoiceObj = new \MyAdmin\Orm\Repeat_Invoice();
        $repeatInvoiceObj->load_real($serviceInfo[$settings['PREFIX'].'_invoice']);
        if ($repeatInvoiceObj->loaded === true) {
            $repCurrency = $repeatInvoiceObj->getCurrency();
            $repeat_cost = $slice_cost * $slices;
            $repeatInvoiceObj->setDescription($serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name'].' '.$slices.' Slices')
                ->setCost(convertCurrency($repeat_cost, $repCurrency, 'USD')->getAmount()->toFloat())
                ->save();
        }
        $invoiceObj = new \MyAdmin\Orm\Invoice();
        $invoices = $invoiceObj->find([['type','=',1],['paid','=',0],['extra','=',$serviceInfo[$settings['PREFIX'].'_invoice']]]);
        foreach ($invoices as $invoiceId) {
            $invoiceObj->load_real($invoiceId);
            if ($invoiceObj->loaded === true) {
                $invCurrency = $invoiceObj->getCurrency();
                $inv_cost = $slice_cost * $slices;
                $invoiceObj->setDescription('(Repeat Invoice: '.$serviceInfo[$settings['PREFIX'].'_invoice'].') '.$serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name'].' '.$slices.' Slices')
                    ->setAmount(convertCurrency($inv_cost, $invCurrency, 'USD')->getAmount()->toFloat())
                    ->save();
            }
        }
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
                $GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'pending-setup', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
                $GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'initial_install', '', $serviceInfo[$settings['PREFIX'].'_custid']);
                admin_email_vps_pending_setup($serviceInfo[$settings['PREFIX'].'_id']);
            })->setReactivate(function ($service) {
                $serviceTypes = run_event('get_service_types', false, self::$module);
                $serviceInfo = $service->getServiceInfo();
                $settings = get_module_settings(self::$module);
                $db = get_module_db(self::$module);
                if ($serviceInfo[$settings['PREFIX'].'_server_status'] === 'deleted' || $serviceInfo[$settings['PREFIX'].'_ip'] == '') {
                    $GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'pending-setup', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
                    $db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='pending-setup' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
                    $GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'initial_install', '', $serviceInfo[$settings['PREFIX'].'_custid']);
                } else {
                    $GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
                    $db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='active' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
                    $GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'enable', '', $serviceInfo[$settings['PREFIX'].'_custid']);
                    $GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'start', '', $serviceInfo[$settings['PREFIX'].'_custid']);
                }
                $smarty = new \TFSmarty();
                $smarty->assign('vps_name', $serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name']);
                $email = $smarty->fetch('email/admin/vps_reactivated.tpl');
                $subject = $serviceInfo[$settings['TITLE_FIELD']].' '.$serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name'].' '.$settings['TBLNAME'].' Reactivated';
                (new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/vps_reactivated.tpl');
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
        /**
         * @var \MyAdmin\Settings $settings
         **/
        $settings = $event->getSubject();
        $settings->setTarget('module');
        $settings->add_dropdown_setting(self::$module, _('Out of Stock'), 'outofstock_vps', _('Out Of Stock VPS'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_VPS'), ['0', '1'], ['No', 'Yes']);
        $settings->add_password_setting(self::$module, _('Webuzo Credentials'), 'webuzo_license_key', _('Webuzo License Key'), _('API Credentials for Webuozo'), $settings->get_setting('WEBUZO_LICENSE_KEY'));
        $settings->add_text_setting(self::$module, _('Slice Costs'), 'vps_ny_cost', _('VPS NY4 Multiplier'), _('This is the multiplier to a normal cost for an item to be hosted in NY.'), $settings->get_setting('VPS_NY_COST'));
        $settings->add_text_setting(self::$module, _('Slice Amounts'), 'vps_slice_ram', _('Ram Per Slice'), _('Amount of ram in MB per VPS Slice'), $settings->get_setting('VPS_SLICE_RAM'));
        $settings->add_text_setting(self::$module, _('Slice Amounts'), 'vps_slice_hd', _('GB HD Space Per Slice'), _('Amount of HD space in GB per VPS Slice'), $settings->get_setting('VPS_SLICE_HD'));
        $settings->add_dropdown_setting(self::$module, _('Slice Amounts'), 'vps_bw_type', _('Bandwidth Limited by Total Traffic or Throttling'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('VPS_BW_TYPE'), ['1', '2'], ['Throttled in mbps', 'Total GBytes Used']);
        $settings->add_text_setting(self::$module, _('Slice Amounts'), 'vps_slice_bw', _('Bandwidth Limit Per Slice in Mbits/s  or Gbytes'), _('Amount of Bandwidth per slice.'), $settings->get_setting('VPS_SLICE_BW'));
        $settings->add_text_setting(self::$module, _('Slice Amounts'), 'vps_slice_max', _('Max Slices Per Order'), _('Maximum amount of slices any one VPS can be.'), $settings->get_setting('VPS_SLICE_MAX'));
        $settings->add_master_checkbox_setting(self::$module, 'Server Settings', self::$module, 'available', 'vps_available', 'Auto-Setup', '<p>Choose which servers are used for auto-server Setups.</p>');
        //$settings->add_master_text_setting(self::$module, 'Server Settings', self::$module, 'root', 'vps_root', 'VPS Root', '<p>Password to connect to server</p>');
        $settings->add_master_label(self::$module, 'Server Settings', self::$module, 'free_ips', 'Free IPS', '<p>The current number of free IPS.</p>', '(SELECT COUNT(ips_ip) AS free_ips FROM vps_ips WHERE ips_used = 0 AND ips_usable = 1 and ips_server=vps_masters.vps_id GROUP BY ips_server) free_ips');
        $settings->add_master_label(self::$module, 'Server Settings', self::$module, 'active_services', 'Active VPS', '<p>The current number of active VPS.</p>', 'count(vps.vps_id) as active_services');
        $settings->add_master_text_setting(self::$module, 'Server Settings', self::$module, 'server_max', 'vps_server_max', 'Max VPS', '<p>The Maximum number of VPS that can be running on each server.</p>');
        $settings->add_master_label(self::$module, 'Server Settings', self::$module, 'active_slices', 'Active Slices', '<p>The current total slices from active VPS.</p>', 'sum(vps.vps_slices) as active_slices');
        $settings->add_master_text_setting(self::$module, 'Server Settings', self::$module, 'server_max_slices', 'vps_server_max_slices', 'Max Slices', '<p>The Maximum number of total slices that can be running on each server.</p>');
        $settings->setTarget('global');
    }
}
