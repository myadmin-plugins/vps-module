<?php

namespace Detain\MyAdminVpsVps;

use Symfony\Component\EventDispatcher\GenericEvent;

class Plugin {

	public function __construct() {
	}

	public static function Load(GenericEvent $event) {
		$service = $event->getSubject();
		$service->set_module('vps')
			->set_enable(function() {
				$GLOBALS['tf']->history->add($this->get_module().'queue', $db->Record[$settings['PREFIX'].'_id'], 'initial_install', '', $db->Record[$settings['PREFIX'].'_custid']);
				$GLOBALS['tf']->history->add($this->get_module().'queue', $db->Record[$settings['PREFIX'].'_id'], 'initial_install', '', $db->Record[$settings['PREFIX'].'_custid']);
				admin_email_vps_pending_setup($db->Record[$settings['PREFIX'].'_id']);
			})->set_reactivate(function() {
				if ($db->Record[$settings['PREFIX'].'_server_status'] === 'deleted' || $db->Record[$settings['PREFIX'].'_ip'] == '') {
					$GLOBALS['tf']->history->add($settings['PREFIX'], 'change_status', 'pending-setup', $db->Record[$settings['PREFIX'].'_id'], $db->Record[$settings['PREFIX'].'_custid']);
					$db2->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='pending-setup' where {$settings['PREFIX']}_id='{$db->Record[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
					$GLOBALS['tf']->history->add($this->get_module().'queue', $db->Record[$settings['PREFIX'].'_id'], 'initial_install', '', $db->Record[$settings['PREFIX'].'_custid']);
				} else {
					$GLOBALS['tf']->history->add($settings['PREFIX'], 'change_status', 'active', $db->Record[$settings['PREFIX'].'_id'], $db->Record[$settings['PREFIX'].'_custid']);
					$db2->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='active' where {$settings['PREFIX']}_id='{$db->Record[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
					$GLOBALS['tf']->history->add($this->get_module().'queue', $db->Record[$settings['PREFIX'].'_id'], 'enable', '', $db->Record[$settings['PREFIX'].'_custid']);
					$GLOBALS['tf']->history->add($this->get_module().'queue', $db->Record[$settings['PREFIX'].'_id'], 'start', '', $db->Record[$settings['PREFIX'].'_custid']);
				}
				$smarty = new TFSmarty;
				$smarty->assign('vps_name', $service_name);
				$email = $smarty->fetch('email/admin_email_vps_reactivated.tpl');
				$subject = $db->Record[$settings['TITLE_FIELD']].' '.$service_name.' '.$settings['TBLNAME'].' Re-Activated';
				$headers = '';
				$headers .= 'MIME-Version: 1.0' . EMAIL_NEWLINE;
				$headers .= 'Content-type: text/html; charset=UTF-8' . EMAIL_NEWLINE;
				$headers .= 'From: ' . TITLE . ' <' . EMAIL_FROM . '>' . EMAIL_NEWLINE;
				admin_mail($subject, $email, $headers, false, 'admin_email_vps_reactivated.tpl');
			})->set_disable(function() {
			})->register();
	}

}
