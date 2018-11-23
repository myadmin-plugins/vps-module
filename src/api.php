<?php
/**
* API VPS Functions
* @author    Joe Huss <detain@interserver.net>
* @copyright 2019
* @package   MyAdmin
* @category  API
*/

/**
 * Checks if the parameters for your order pass validation and let you know if there are any errors. It will also give you information on the pricing breakdown.
 *
 * @param string $os file field from [get_vps_templates](#get_vps_templates)
 * @param int $slices 	1 to 16 specifying the scale of the VPS resources you want (a 3 slice has 3x the resources of a 1 slice vps)
 * @param string $platform platform field from the [get_vps_platforms_array](#get_vps_platforms_array)
 * @param string $controlpanel none, cpanel, or da for None, cPanel, or DirectAdmin control panel addons, only available with CentOS
 * @param int $period 1-36, How frequently to be billed in months. Some discounts as given based on the period
 * @param int $location id field from the [get_vps_locations_array](#get_vps_locations_array)
 * @param string $version os field from [get_vps_templates](#get_vps_templates)
 * @param string $hostname Desired Hostname for the VPS
 * @param string $coupon Optional Coupon to pass
 * @param string $rootpass Desired Root Password (unused for windows, send a blank string)
 * @return array parsed order parameters and the validation result
 */
function api_validate_buy_vps($os, $slices, $platform, $controlpanel, $period, $location, $version, $hostname, $coupon, $rootpass)
{
	//if ($GLOBALS['tf']->ima == 'admin')
	$custid = get_custid($GLOBALS['tf']->session->account_id, 'vps');
	function_requirements('validate_buy_vps');
	$return = validate_buy_vps($custid, $os, $slices, $platform, $controlpanel, $period, $location, $version, $hostname, $coupon, $rootpass);
	$return['status_text'] = implode("\n", $return['errors']);
	if ($return['continue'] === true) {
		$return['status'] = 'ok';
	} else {
		$return['status'] = 'error';
	}
	unset($return['continue']);
	unset($return['errors']);
	return $return;
}

/**
 * Places a VPS order in our system. These are the same parameters as api_validate_buy_vps.
 *
 * @param string $os file field from [get_vps_templates](#get_vps_templates)
 * @param int $slices 	1 to 16 specifying the scale of the VPS resources you want (a 3 slice has 3x the resources of a 1 slice vps)
 * @param string $platform platform field from the [get_vps_platforms_array](#get_vps_platforms_array)
 * @param string $controlpanel none, cpanel, or da for None, cPanel, or DirectAdmin control panel addons, only available with CentOS
 * @param int $period 1-36, How frequently to be billed in months. Some discounts as given based on the period
 * @param int $location id field from the [get_vps_locations_array](#get_vps_locations_array)
 * @param string $version os field from [get_vps_templates](#get_vps_templates)
 * @param string $hostname Desired Hostname for the VPS
 * @param string $coupon Optional Coupon to pass
 * @param string $rootpass Desired Root Password (unused for windows, send a blank string)
 * @return array array containing order result information
 */
function api_buy_vps($os, $slices, $platform, $controlpanel, $period, $location, $version, $hostname, $coupon, $rootpass)
{
	$custid = get_custid($GLOBALS['tf']->session->account_id, 'vps');
	function_requirements('validate_buy_vps');
	$validation = validate_buy_vps($custid, $os, $slices, $platform, $controlpanel, $period, $location, $version, $hostname, $coupon, $rootpass);
	$continue = $validation['continue'];
	$errors = $validation['errors'];
	$coupon_code = $validation['coupon_code'];
	$service_cost = $validation['service_cost'];
	$slice_cost = $validation['slice_cost'];
	$service_type = $validation['service_type'];
	$repeat_slice_cost = $validation['repeat_slice_cost'];
	$original_slice_cost = $validation['original_slice_cost'];
	$original_cost = $validation['original_cost'];
	$repeat_service_cost = $validation['repeat_service_cost'];
	$monthly_service_cost = $validation['monthly_service_cost'];
	$custid = $validation['custid'];
	$os = $validation['os'];
	$slices = $validation['slices'];
	$platform = $validation['platform'];
	$controlpanel = $validation['controlpanel'];
	$period = $validation['period'];
	$location = $validation['location'];
	$version = $validation['version'];
	$hostname = $validation['hostname'];
	$coupon = $validation['coupon'];
	$rootpass = $validation['rootpass'];
	$return = [];
	$return['invoices'] = '';
	$return['cost'] = $service_cost;
	if ($continue === true) {
		function_requirements('place_buy_vps');
		$order_response = place_buy_vps($coupon_code, $service_cost, $slice_cost, $service_type, $original_slice_cost, $original_cost, $repeat_service_cost, $custid, $os, $slices, $platform, $controlpanel, $period, $location, $version, $hostname, $rootpass);
		$total_cost = $order_response['total_cost'];
		$real_iids = $order_response['real_iids'];
		$serviceid = $order_response['serviceid'];
		$return['status'] = 'ok';
		$return['status_text'] = $serviceid;
		$return['invoices'] = implode(',', $real_iids);
		$return['cost'] = $total_cost;
	} else {
		$return['status'] = 'error';
		$return['status_text'] = implode("\n", $errors);
	}
	return $return;
}

/**
 * Places a VPS order in our system. These are the same parameters as api_validate_buy_vps with the addition of a server parameter.  This function is for admins only.
 *
 * @param string $os file field from [get_vps_templates](#get_vps_templates)
 * @param int $slices 	1 to 16 specifying the scale of the VPS resources you want (a 3 slice has 3x the resources of a 1 slice vps)
 * @param string $platform platform field from the [get_vps_platforms_array](#get_vps_platforms_array)
 * @param string $controlpanel none, cpanel, or da for None, cPanel, or DirectAdmin control panel addons, only available with CentOS
 * @param int $period 1-36, How frequently to be billed in months. Some discounts as given based on the period
 * @param int $location id field from the [get_vps_locations_array](#get_vps_locations_array)
 * @param string $version os field from [get_vps_templates](#get_vps_templates)
 * @param string $hostname Desired Hostname for the VPS
 * @param string $coupon Optional Coupon to pass
 * @param string $rootpass Desired Root Password (unused for windows, send a blank string)
 * @param int $server 0 for auto assign otherwise the id of the vps master to put this on
 * @return array array containing order result information
 */
function api_buy_vps_admin($os, $slices, $platform, $controlpanel, $period, $location, $version, $hostname, $coupon, $rootpass, $server = 0)
{
	if ($GLOBALS['tf']->ima != 'admin') {
		$server = 0;
	} else {
		$server = (int)$server;
	}
	$custid = get_custid($GLOBALS['tf']->session->account_id, 'vps');
	function_requirements('validate_buy_vps');
	$validation = validate_buy_vps($custid, $os, $slices, $platform, $controlpanel, $period, $location, $version, $hostname, $coupon, $rootpass);
	$continue = $validation['continue'];
	$errors = $validation['errors'];
	$coupon_code = $validation['coupon_code'];
	$service_cost = $validation['service_cost'];
	$slice_cost = $validation['slice_cost'];
	$service_type = $validation['service_type'];
	$repeat_slice_cost = $validation['repeat_slice_cost'];
	$original_slice_cost = $validation['original_slice_cost'];
	$original_cost = $validation['original_cost'];
	$repeat_service_cost = $validation['repeat_service_cost'];
	$monthly_service_cost = $validation['monthly_service_cost'];
	$custid = $validation['custid'];
	$os = $validation['os'];
	$slices = $validation['slices'];
	$platform = $validation['platform'];
	$controlpanel = $validation['controlpanel'];
	$period = $validation['period'];
	$location = $validation['location'];
	$version = $validation['version'];
	$hostname = $validation['hostname'];
	$coupon = $validation['coupon'];
	$rootpass = $validation['rootpass'];
	$return = [];
	$return['invoices'] = '';
	$return['cost'] = $service_cost;
	if ($continue === true) {
		function_requirements('place_buy_vps');
		$order_response = place_buy_vps($coupon_code, $service_cost, $slice_cost, $service_type, $original_slice_cost, $original_cost, $repeat_service_cost, $custid, $os, $slices, $platform, $controlpanel, $period, $location, $version, $hostname, $rootpass, $server);
		$total_cost = $order_response['total_cost'];
		$real_iids = $order_response['real_iids'];
		$serviceid = $order_response['serviceid'];
		$return['status'] = 'ok';
		$return['status_text'] = $serviceid;
		$return['invoices'] = implode(',', $real_iids);
		$return['cost'] = $total_cost;
	} else {
		$return['status'] = 'error';
		$return['status_text'] = implode("\n", $errors);
	}
	return $return;
}
