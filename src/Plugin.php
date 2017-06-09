<?php

namespace Detain\MyAdminVps;

use Symfony\Component\EventDispatcher\GenericEvent;

class Plugin {

	public function __construct() {
	}

	public static function Load(GenericEvent $event) {
		$service = $event->getSubject();
		$service->set_module('vps')
			->set_enable(function($service) {
				$service_info = $service->get_service_info();
				$settings = get_module_settings($service->get_module());
				$GLOBALS['tf']->history->add($service->get_module().'queue', $service_info[$settings['PREFIX'].'_id'], 'initial_install', '', $service_info[$settings['PREFIX'].'_custid']);
				$GLOBALS['tf']->history->add($service->get_module().'queue', $service_info[$settings['PREFIX'].'_id'], 'initial_install', '', $service_info[$settings['PREFIX'].'_custid']);
				admin_email_vps_pending_setup($service_info[$settings['PREFIX'].'_id']);
			})->set_reactivate(function($service) {
				$service_types = run_event('get_service_types', false, $service->get_module());
				$service_info = $service->get_service_info();
				$settings = get_module_settings($service->get_module());
				$db = get_module_db($service->get_module());
				if ($service_info[$settings['PREFIX'].'_server_status'] === 'deleted' || $service_info[$settings['PREFIX'].'_ip'] == '') {
					$GLOBALS['tf']->history->add($settings['PREFIX'], 'change_status', 'pending-setup', $service_info[$settings['PREFIX'].'_id'], $service_info[$settings['PREFIX'].'_custid']);
					$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='pending-setup' where {$settings['PREFIX']}_id='{$service_info[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
					$GLOBALS['tf']->history->add($service->get_module().'queue', $service_info[$settings['PREFIX'].'_id'], 'initial_install', '', $service_info[$settings['PREFIX'].'_custid']);
				} else {
					$GLOBALS['tf']->history->add($settings['PREFIX'], 'change_status', 'active', $service_info[$settings['PREFIX'].'_id'], $service_info[$settings['PREFIX'].'_custid']);
					$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='active' where {$settings['PREFIX']}_id='{$service_info[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
					$GLOBALS['tf']->history->add($service->get_module().'queue', $service_info[$settings['PREFIX'].'_id'], 'enable', '', $service_info[$settings['PREFIX'].'_custid']);
					$GLOBALS['tf']->history->add($service->get_module().'queue', $service_info[$settings['PREFIX'].'_id'], 'start', '', $service_info[$settings['PREFIX'].'_custid']);
				}
				$smarty = new \TFSmarty;
				$smarty->assign('vps_name', $service_types[$service_info[$settings['PREFIX'] . '_type']]['services_name']);
				$email = $smarty->fetch('email/admin_email_vps_reactivated.tpl');
				$subject = $service_info[$settings['TITLE_FIELD']].' '.$service_types[$service_info[$settings['PREFIX'] . '_type']]['services_name'].' '.$settings['TBLNAME'].' Re-Activated';
				$headers = '';
				$headers .= 'MIME-Version: 1.0' . EMAIL_NEWLINE;
				$headers .= 'Content-type: text/html; charset=UTF-8' . EMAIL_NEWLINE;
				$headers .= 'From: ' . TITLE . ' <' . EMAIL_FROM . '>' . EMAIL_NEWLINE;
				admin_mail($subject, $email, $headers, false, 'admin_email_vps_reactivated.tpl');
			})->set_disable(function($service) {
			})->register();
	}

	public static function Settings(GenericEvent $event) {
		$module = 'vps';
		$settings = $event->getSubject();
		$settings->add_text_setting($module, 'Credentials', 'vps_hyperv_password', 'HyperV Administrator Password:', 'Administrative password to login to the HyperV server', $settings->get_setting('VPS_HYPERV_PASSWORD'));
		$settings->add_text_setting($module, 'Credentials', 'webuzo_license_key', 'Webuzo License Key:', 'API Credentials for Webuozo', $settings->get_setting('WEBUZO_LICENSE_KEY'));
		$settings->add_text_setting($module, 'Slice Costs', 'vps_slice_ovz_cost', 'OpenVZ VPS Cost Per Slice:', 'OpenVZ VPS will cost this much for 1 slice.', $settings->get_setting('VPS_SLICE_OVZ_COST'));
		$settings->add_text_setting($module, 'Slice Costs', 'vps_slice_ssd_ovz_cost', 'SSD OpenVZ VPS Cost Per Slice:', 'SSD OpenVZ VPS will cost this much for 1 slice.', $settings->get_setting('VPS_SLICE_SSD_OVZ_COST'));
		$settings->add_text_setting($module, 'Slice Costs', 'vps_slice_virtuozzo_cost', 'Virtuozzo VPS Cost Per Slice:', 'OpenVZ VPS will cost this much for 1 slice.', $settings->get_setting('VPS_SLICE_VIRTUOZZO_COST'));
		$settings->add_text_setting($module, 'Slice Costs', 'vps_slice_ssd_virtuozzo_cost', 'SSD Virtuozzo VPS Cost Per Slice:', 'SSD OpenVZ VPS will cost this much for 1 slice.', $settings->get_setting('VPS_SLICE_SSD_VIRTUOZZO_COST'));
		$settings->add_text_setting($module, 'Slice Costs', 'vps_slice_kvm_l_cost', 'KVM Linux VPS Cost Per Slice:', 'KVM Linux VPS will cost this much for 1 slice.', $settings->get_setting('VPS_SLICE_KVM_L_COST'));
		$settings->add_text_setting($module, 'Slice Costs', 'vps_slice_kvm_w_cost', 'KVM Windows VPS Cost Per Slice:', 'KVM Windows VPS will cost this much for 1 slice.', $settings->get_setting('VPS_SLICE_KVM_W_COST'));
		$settings->add_text_setting($module, 'Slice Costs', 'vps_slice_cloud_kvm_l_cost', 'Cloud KVM Linux VPS Cost Per Slice:', 'Cloud KVM Linux VPS will cost this much for 1 slice.', $settings->get_setting('VPS_SLICE_CLOUD_KVM_L_COST'));
		$settings->add_text_setting($module, 'Slice Costs', 'vps_slice_cloud_kvm_w_cost', 'Cloud KVM Windows VPS Cost Per Slice:', 'Cloud KVM Windows VPS will cost this much for 1 slice.', $settings->get_setting('VPS_SLICE_CLOUD_KVM_W_COST'));
		$settings->add_text_setting($module, 'Slice Costs', 'vps_slice_hyperv_cost', 'HyperV VPS Cost Per Slice:', 'HyperV VPS will cost this much for 1 slice.', $settings->get_setting('VPS_SLICE_HYPERV_COST'));
		$settings->add_text_setting($module, 'Slice Costs', 'vps_slice_xen_cost', 'XEN VPS Cost Per Slice:', 'XEN VPS will cost this much for 1 slice.', $settings->get_setting('VPS_SLICE_XEN_COST'));
		$settings->add_text_setting($module, 'Slice Costs', 'vps_slice_lxc_cost', 'LXC VPS Cost Per Slice:', 'LXC VPS will cost this much for 1 slice.', $settings->get_setting('VPS_SLICE_LXC_COST'));
		$settings->add_text_setting($module, 'Slice Costs', 'vps_slice_vmware_cost', 'VMWare VPS Cost Per Slice:', 'VMWare VPS will cost this much for 1 slice.', $settings->get_setting('VPS_SLICE_VMWARE_COST'));
		$settings->add_text_setting($module, 'Slice Costs', 'vps_ny_cost', 'VPS NY4 Multiplier:', 'This is the multiplier to a normal cost for an item to be hosted in NY.', $settings->get_setting('VPS_NY_COST'));
		$settings->add_text_setting($module, 'Slice Amounts', 'vps_slice_ram', 'Ram Per Slice:', 'Amount of ram in MB per VPS Slice', $settings->get_setting('VPS_SLICE_RAM'));
		$settings->add_text_setting($module, 'Slice Amounts', 'vps_slice_hd', 'HD Space Per Slice:', 'Amount of HD space in GB per VPS Slice', $settings->get_setting('VPS_SLICE_HD'));
		$settings->add_text_setting($module, 'Slice Amounts', 'vps_bw_type', 'Bandwidth Limited by Total Traffic or Throttling', 'Enable/Disable Sales Of This Type', $settings->get_setting('VPS_BW_TYPE'), array('1', '2'), array('Throttled in mbps', 'Total GBytes Used', ));
		$settings->add_text_setting($module, 'Slice Amounts', 'vps_slice_bw', 'Bandwidth Limit Per Slice in Mbits/s  or Gbytes:', 'Amount of Bandwidth per slice.', $settings->get_setting('VPS_SLICE_BW'));
		$settings->add_text_setting($module, 'Slice Amounts', 'vps_slice_max', 'Max Slices Per Order:', 'Maximum amount of slices any one VPS can be.', $settings->get_setting('VPS_SLICE_MAX'));
		$settings->add_text_setting($module, 'Slice OpenVZ Amounts', 'vps_slice_openvz_avnumproc', 'avnumproc', 'The average number of processes and threads. ', $settings->get_setting('VPS_SLICE_OPENVZ_AVNUMPROC'));
		$settings->add_text_setting($module, 'Slice OpenVZ Amounts', 'vps_slice_openvz_numproc', 'numproc', 'The maximal number of processes and threads the VE may create. ', $settings->get_setting('VPS_SLICE_OPENVZ_NUMPROC'));
		$settings->add_text_setting($module, 'Slice OpenVZ Amounts', 'vps_slice_openvz_numtcpsock', 'numtcpsock', 'The number of TCP sockets (PF_INET family, SOCK_STREAM type). This parameter limits the number of TCP connections and, thus, the number of clients the server application can handle in parallel. ', $settings->get_setting('VPS_SLICE_OPENVZ_NUMTCPSOCK'));
		$settings->add_text_setting($module, 'Slice OpenVZ Amounts', 'vps_slice_openvz_numothersock', 'numothersock', ' The number of sockets other than TCP ones. Local (UNIX-domain) sockets are used for communications inside the system. UDP sockets are used, for example, for Domain Name Service (DNS) queries. UDP and other sockets may also be used in some very specialized applications (SNMP agents and others). ', $settings->get_setting('VPS_SLICE_OPENVZ_NUMOTHERSOCK'));
		$settings->add_text_setting($module, 'Slice OpenVZ Amounts', 'vps_slice_openvz_cpuunits', 'cpuunits', '', $settings->get_setting('VPS_SLICE_OPENVZ_CPUUNITS'));
		$settings->add_text_setting($module, 'Slice OpenVZ Amounts', 'vps_slice_openvz_cpus', 'slices per core', '', $settings->get_setting('VPS_SLICE_OPENVZ_CPUS'));
		$settings->add_text_setting($module, 'Slice OpenVZ Amounts', 'vps_slice_openvz_dgramrcvbuf', 'dgramrcvbuf', 'The total size of receive buffers of UDP and other datagram protocols. ', $settings->get_setting('VPS_SLICE_OPENVZ_DGRAMRCVBUF'));
		$settings->add_text_setting($module, 'Slice OpenVZ Amounts', 'vps_slice_openvz_tcprcvbuf', 'tcprcvbuf', 'The total size of receive buffers for TCP sockets, i.e. the amount of kernel memory allocated for the data received from the remote side, but not read by the local application yet. ', $settings->get_setting('VPS_SLICE_OPENVZ_TCPRCVBUF'));
		$settings->add_text_setting($module, 'Slice OpenVZ Amounts', 'vps_slice_openvz_tcpsndbuf', 'tcpsndbuf', 'The total size of send buffers for TCP sockets, i.e. the amount of kernel memory allocated for the data sent from an application to a TCP socket, but not acknowledged by the remote side yet. ', $settings->get_setting('VPS_SLICE_OPENVZ_TCPSNDBUF'));
		$settings->add_text_setting($module, 'Slice OpenVZ Amounts', 'vps_slice_openvz_othersockbuf', 'othersockbuf', 'The total size of UNIX-domain socket buffers, UDP, and other datagram protocol send buffers. ', $settings->get_setting('VPS_SLICE_OPENVZ_OTHERSOCKBUF'));
		$settings->add_text_setting($module, 'Slice OpenVZ Amounts', 'vps_slice_openvz_numflock', 'numflock', 'The number of file locks created by all VE processes. ', $settings->get_setting('VPS_SLICE_OPENVZ_NUMFLOCK'));
		$settings->add_text_setting($module, 'Slice OpenVZ Amounts', 'vps_slice_openvz_numpty_base', 'numpty_base', 'This setting is multiplied by the number of slices. This parameter is usually used to limit the number of simultaneous shell sessions.', $settings->get_setting('VPS_SLICE_OPENVZ_NUMPTY_BASE'));
		$settings->add_text_setting($module, 'Slice OpenVZ Amounts', 'vps_slice_openvz_numpty', 'numpty', 'This parameter is usually used to limit the number of simultaneous shell sessions.', $settings->get_setting('VPS_SLICE_OPENVZ_NUMPTY'));
		$settings->add_text_setting($module, 'Slice OpenVZ Amounts', 'vps_slice_openvz_shmpages', 'shmpages', 'The total size of shared memory (IPC, shared anonymous mappings and tmpfs objects). ', $settings->get_setting('VPS_SLICE_OPENVZ_SHMPAGES'));
		$settings->add_text_setting($module, 'Slice OpenVZ Amounts', 'vps_slice_openvz_numiptent', 'numiptent', 'The number of IP packet filtering entries. ', $settings->get_setting('VPS_SLICE_OPENVZ_NUMIPTENT'));
		$settings->add_select_master($module, 'Default Servers', $module, 'new_vps_openvz_server', 'OpenVZ NJ Server', NEW_VPS_OPENVZ_SERVER, 6, 1);
		$settings->add_select_master($module, 'Default Servers', $module, 'new_vps_ssd_openvz_server', 'SSD OpenVZ NJ Server', NEW_VPS_SSD_OPENVZ_SERVER, 5, 1);
		$settings->add_select_master($module, 'Default Servers', $module, 'new_vps_virtuozzo_server', 'Virtuozzo NJ Server', NEW_VPS_VIRTUOZZO_SERVER, 12, 1);
		$settings->add_select_master($module, 'Default Servers', $module, 'new_vps_ssd_virtuozzo_server', 'SSD Virtuozzo NJ Server', NEW_VPS_SSD_VIRTUOZZO_SERVER, 13, 1);
		$settings->add_select_master($module, 'Default Servers', $module, 'new_vps_kvm_win_server', 'KVM Windows NJ Server', NEW_VPS_KVM_WIN_SERVER, 1, 1);
		$settings->add_select_master($module, 'Default Servers', $module, 'new_vps_kvm_linux_server', 'KVM Linux NJ Server', NEW_VPS_KVM_LINUX_SERVER, 2, 1);
		$settings->add_select_master($module, 'Default Servers', $module, 'new_vps_la_openvz_server', 'OpenVZ LA Server', NEW_VPS_LA_OPENVZ_SERVER, 6, 2);
		$settings->add_select_master($module, 'Default Servers', $module, 'new_vps_la_kvm_win_server', 'KVM LA Windows Server', NEW_VPS_LA_KVM_WIN_SERVER, 1, 2);
		$settings->add_select_master($module, 'Default Servers', $module, 'new_vps_la_kvm_linux_server', 'KVM LA Linux Server', NEW_VPS_LA_KVM_LINUX_SERVER, 2, 2);
		//$settings->add_select_master($module, 'Default Servers', $module, 'new_vps_ny_openvz_server', 'OpenVZ NY4 Server', NEW_VPS_NY_OPENVZ_SERVER, 0, 3);
		$settings->add_select_master($module, 'Default Servers', $module, 'new_vps_ny_kvm_win_server', 'KVM NY4 Windows Server', NEW_VPS_NY_KVM_WIN_SERVER, 1, 3);
		//$settings->add_select_master($module, 'Default Servers', $module, 'new_vps_ny_kvm_linux_server', 'KVM NY4 Linux Server', NEW_VPS_NY_KVM_LINUX_SERVER, 2, 3);
		$settings->add_select_master($module, 'Default Servers', $module, 'new_vps_cloud_kvm_win_server', 'Cloud KVM Windows Server', (defined('NEW_VPS_CLOUD_KVM_WIN_SERVER') ? NEW_VPS_CLOUD_KVM_WIN_SERVER : ''), 3);
		$settings->add_select_master($module, 'Default Servers', $module, 'new_vps_cloud_kvm_linux_server', 'Cloud KVM Linux Server', (defined('NEW_VPS_CLOUD_KVM_LINUX_SERVER') ? NEW_VPS_CLOUD_KVM_LINUX_SERVER : ''), 3);
		$settings->add_select_master($module, 'Default Servers', $module, 'new_vps_xen_server', 'Xen NJ Server', (defined('NEW_VPS_XEN_SERVER') ? NEW_VPS_XEN_SERVER : ''), 8, 1);
		//$settings->add_select_master($module, 'Default Servers', $module, 'new_vps_lxc_server', 'LXC NJ Server', NEW_VPS_LXC_SERVER, 9, 1);
		//$settings->add_select_master($module, 'Default Servers', $module, 'new_vps_vmware_server', 'VMWare NJ Server', NEW_VPS_VMWARE_SERVER, 10, 1);
		$settings->add_select_master($module, 'Default Servers', $module, 'new_vps_hyperv_server', 'HyperV NJ Server', NEW_VPS_HYPERV_SERVER, 11, 1);
		$settings->add_select_master_autosetup($module, 'Auto-Setup Servers', $module, 'setup_servers', 'Auto-Setup Servers:', '<p>Choose which servers are used for auto-server Setups.</p>');
		$settings->add_dropdown_setting($module, 'Out of Stock', 'outofstock_vps', 'Out Of Stock VPS', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_VPS'), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting($module, 'Out of Stock', 'outofstock_openvz', 'Out Of Stock OpenVZ Secaucus', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_OPENVZ'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting($module, 'Out of Stock', 'outofstock_ssd_openvz', 'Out Of Stock SSD OpenVZ Secaucus', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_SSD_OPENVZ'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting($module, 'Out of Stock', 'outofstock_virtuozzo', 'Out Of Stock Virtuozzo Secaucus', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_VIRTUOZZO'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting($module, 'Out of Stock', 'outofstock_ssd_virtuozzo', 'Out Of Stock SSD Virtuozzo Secaucus', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_SSD_VIRTUOZZO'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting($module, 'Out of Stock', 'outofstock_kvm_linux', 'Out Of Stock KVM Linux Secaucus', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_KVM_LINUX'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting($module, 'Out of Stock', 'outofstock_kvm_win', 'Out Of Stock KVM Windows Secaucus', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_KVM_WIN'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting($module, 'Out of Stock', 'outofstock_openvz_la', 'Out Of Stock OpenVZ Los Angeles', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_OPENVZ_LA'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting($module, 'Out of Stock', 'outofstock_kvm_linux_la', 'Out Of Stock KVM Linux Los Angeles', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_KVM_LINUX_LA'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting($module, 'Out of Stock', 'outofstock_kvm_win_la', 'Out Of Stock KVM Windows Los Angeles', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_KVM_WIN_LA'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting($module, 'Out of Stock', 'outofstock_openvz_ny', 'Out Of Stock OpenVZ Equinix NY4', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_OPENVZ_NY'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting($module, 'Out of Stock', 'outofstock_ssd_openvz_ny', 'Out Of Stock SSD OpenVZ Equinix NY4', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_SSD_OPENVZ_NY'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting($module, 'Out of Stock', 'outofstock_kvm_linux_ny', 'Out Of Stock KVM Linux Equinix NY4', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_KVM_LINUX_NY'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting($module, 'Out of Stock', 'outofstock_kvm_win_ny', 'Out Of Stock KVM Windows Equinix NY4', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_KVM_WIN_NY'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting($module, 'Out of Stock', 'outofstock_cloudkvm', 'Out Of Stock Cloud KVM', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_CLOUDKVM'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting($module, 'Out of Stock', 'outofstock_xen', 'Out Of Stock Xen Secaucus', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_XEN'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting($module, 'Out of Stock', 'outofstock_lxc', 'Out Of Stock LXC Secaucus', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_LXC'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting($module, 'Out of Stock', 'outofstock_vmware', 'Out Of Stock VMWare Secaucus', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_VMWARE'), array('0', '1'), array('No', 'Yes', ));
		$settings->add_dropdown_setting($module, 'Out of Stock', 'outofstock_hyperv', 'Out Of Stock HyperV Secaucus', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_HYPERV'), array('0', '1'), array('No', 'Yes', ));
	}
}
