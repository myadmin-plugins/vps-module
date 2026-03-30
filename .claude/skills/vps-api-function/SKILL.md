---
name: vps-api-function
description: Creates a new procedural API function in `src/api.php` following the validate→place pattern with `get_custid()`, `function_requirements()`, validation unpacking, and `$return` array with `status`/`status_text`/`invoices`/`cost` keys. Use when user says 'add API function', 'new vps api', 'add buy function', or adds entries to `Plugin::apiRegister()`. Do NOT use for modifying Plugin.php hook registration or lifecycle handlers (enable/disable/terminate).
---
# VPS API Function

## Critical

- **Never** interpolate `$_GET`/`$_POST` directly — all user input must pass through validation helpers.
- **Always** initialize `$return['invoices'] = ''` and `$return['cost'] = $service_cost` before the `if ($continue)` branch so callers always receive these keys.
- **Never** return `$return['continue']` or `$return['errors']` to the caller — unset them or never populate them in the public function.
- Return array must always include exactly: `status`, `status_text`, `invoices`, `cost`.
- `$GLOBALS['tf']->session->account_id` is the only source of the current user — never accept custid as a parameter in client-facing functions.

## Instructions

### Step 1 — Add the function to `src/api.php`

Append the new function at the end of `src/api.php`. Follow the exact file header style (PHPDoc block, no namespace):

```php
/**
 * One-line description of what this function does.
 *
 * @param string $os         file field from [get_vps_templates](#get_vps_templates)
 * @param int    $slices     1 to 16 scale of VPS resources
 * @param string $platform   platform field from [get_vps_platforms_array](#get_vps_platforms_array)
 * @param string $controlpanel  none, cpanel, or da
 * @param int    $period     1-36 billing months
 * @param int    $location   id from [get_vps_locations_array](#get_vps_locations_array)
 * @param string $version    os field from [get_vps_templates](#get_vps_templates)
 * @param string $hostname   desired hostname
 * @param string $coupon     optional coupon code
 * @param string $rootpass   desired root password
 * @return array array containing order result information
 */
function api_buy_vps_example($os, $slices, $platform, $controlpanel, $period, $location, $version, $hostname, $coupon, $rootpass, $comment = '', $ipv6only = false)
{
    $custid = get_custid($GLOBALS['tf']->session->account_id, 'vps');
    function_requirements('validate_buy_vps');
    $validation = validate_buy_vps($custid, $os, $slices, $platform, $controlpanel, $period, $location, $version, $hostname, $coupon, $rootpass, (bool)$ipv6only);
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
        $order_response = place_buy_vps($coupon_code, $service_cost, $slice_cost, $service_type, $original_slice_cost, $original_cost, $repeat_service_cost, $custid, $os, $slices, $platform, $controlpanel, $period, $location, $version, $hostname, $rootpass, 0, $comment, (bool)$ipv6only);
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
```

Verify the function name follows `api_<verb>_vps[_qualifier]` before proceeding.

### Step 2 — Register the function requirement in `src/Plugin.php`

In `Plugin::getRequirements()`, add a `$loader->add_requirement()` line for the new function name. The path is always the same `src/api.php`:

```php
public static function getRequirements(GenericEvent $event)
{
    $loader = $event->getSubject();
    $loader->add_requirement('api_validate_buy_vps', 'src/api.php');
    $loader->add_requirement('api_buy_vps',          'src/api.php');
    $loader->add_requirement('api_buy_vps_admin',    'src/api.php');
    // ADD:
    $loader->add_requirement('api_buy_vps_example',  'src/api.php');
}
```

Verify the string in `add_requirement()` exactly matches the PHP function name.

### Step 3 — Register a return type array (if new shape)

If the return shape differs from the existing `buy_vps_result_status`, add a new `api_register_array()` call at the top of `Plugin::apiRegister()`. Reuse `buy_vps_result_status` when the function returns `status`, `status_text`, `invoices`, `cost`:

```php
// Only needed for a new return shape:
api_register_array('my_result_type', ['status' => 'string', 'status_text' => 'string', 'invoices' => 'string', 'cost' => 'float']);
```

### Step 4 — Call `api_register()` in `Plugin::apiRegister()`

Add the registration call below the existing `api_buy_vps_admin` line:

```php
api_register(
    'api_buy_vps_example',
    ['os' => 'string', 'slices' => 'int', 'platform' => 'string', 'controlpanel' => 'string',
     'period' => 'int', 'location' => 'int', 'version' => 'string', 'hostname' => 'string',
     'coupon' => 'string', 'rootpass' => 'string', 'comment' => 'string', 'ipv6only' => 'boolean'],
    ['return' => 'buy_vps_result_status'],
    'One-line description shown in API docs.',
    true   // requires auth
);
```

Verify the parameter type map matches the PHP function signature exactly.

### Step 5 — Run tests

Run the full test suite using the project's composer script (config: `phpunit.xml.dist`):

```
composer test
```

All tests in `tests/ApiFunctionsTest.php` must pass. The test suite checks function existence, parameter counts/names/defaults, return array keys, and docblock presence.

## Examples

**User says:** "Add an admin-only API function that buys a VPS on a specific server"

**Actions:**
1. In `src/api.php`: add `api_buy_vps_admin(...)` with the same validate→unpack→place flow, plus a guard at the top: `if ($GLOBALS['tf']->ima != 'admin') { $server = 0; } else { $server = (int)$server; }`
2. In `Plugin::getRequirements()`: add `$loader->add_requirement('api_buy_vps_admin', 'src/api.php');`
3. In `Plugin::apiRegister()`: add `api_register('api_buy_vps_admin', [..., 'server' => 'int', ...], ['return' => 'buy_vps_result_status'], '...', true);`

**Result:** Matches `api_buy_vps_admin` in `src/api.php:120`.

## Common Issues

**Function not found at runtime:**
If you see `Call to undefined function api_buy_vps_example`: the `add_requirement()` call in `Plugin::getRequirements()` is missing or the function name string is misspelled. Verify the string matches the PHP function name exactly — they are case-sensitive.

**Tests fail with "function does not exist":**
`tests/ApiFunctionsTest.php` calls `function_exists('api_buy_vps_example')`. The bootstrap stubs `function_requirements()` as a no-op, so the function must be defined at the top level of `src/api.php` without any conditional wrapping.

**Tests fail with "return array missing key":**
The test suite asserts `status`, `status_text`, `invoices`, `cost` are all present. If you omit `$return['invoices'] = ''` or `$return['cost'] = $service_cost` before the `if ($continue)` branch, the error path will be missing those keys.

**`$validation` keys missing:**
If `validate_buy_vps` returns an unexpected shape, check that you are calling `function_requirements('validate_buy_vps')` before invoking it. Do not call the function directly without loading it first.

**Admin guard bypass:**
If an admin-variant function accepts a `$server` param, always coerce it: `$server = ($GLOBALS['tf']->ima != 'admin') ? 0 : (int)$server;`. Omitting this allows non-admins to specify arbitrary servers.
