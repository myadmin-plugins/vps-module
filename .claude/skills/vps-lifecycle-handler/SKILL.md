---
name: vps-lifecycle-handler
description: Implements a VPS lifecycle closure (setEnable/setReactivate/setDisable/setTerminate) inside Plugin::loadProcessing() in src/Plugin.php. Use when user says 'add lifecycle', 'handle termination', 'on reactivate', 'on enable', 'handle disable', or modifies service state transitions in the VPS module. Do NOT use for changes to API functions in src/api.php or for non-VPS modules.
---
# VPS Lifecycle Handler

## Critical

- All lifecycle closures live **only** inside `Plugin::loadProcessing()` in `src/Plugin.php` — never in `src/api.php`.
- Every closure **must** call `$service->getServiceInfo()` and `get_module_settings(self::$module)` before any DB or history operation.
- Always use `get_module_db(self::$module)` — never PDO or global `$db`.
- All DB queries must pass `__LINE__, __FILE__` as trailing arguments.
- Queue entries go to `self::$module.'queue'` (i.e. `'vpsqueue'`), not to the service table.
- Status change history entries go to `$settings['TABLE']` (i.e. `'vps'`).
- Never interpolate `$_GET`/`$_POST` into queries — all values must come from `$serviceInfo` or `$settings`.

## Instructions

### Step 1 — Locate the chain inside `loadProcessing()`

Open `src/Plugin.php` and find the method `loadProcessing(GenericEvent $event)`. The lifecycle chain is built on `$processing`:

```php
public static function loadProcessing(GenericEvent $event) {
    $processing = $event->getSubject();
    $processing
        ->setEnable(function ($service) { ... })
        ->setReactivate(function ($service) { ... })
        ->setDisable(function ($service) { ... })
        ->setTerminate(function ($service) { ... });
    $event->stopPropagation();
}
```

Verify the chain ends with `$event->stopPropagation()` before proceeding.

### Step 2 — Bootstrap every closure identically

Every non-empty closure must open with these exact lines:

```php
function ($service) {
    $serviceInfo = $service->getServiceInfo();
    $settings    = get_module_settings(self::$module);
    $db          = get_module_db(self::$module);
    // ...
}
```

If the closure needs service types (e.g. for email subjects), add:

```php
$serviceTypes = run_event('get_service_types', false, self::$module);
```

Verify `$settings['PREFIX']`, `$settings['TABLE']`, and `$settings['TBLNAME']` resolve before using them in queries.

### Step 3 — Write the status update DB query

Use the exact interpolation pattern from existing handlers:

```php
$db->query(
    "update {$settings['TABLE']} set {$settings['PREFIX']}_status='new-status'"
    ." where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'",
    __LINE__, __FILE__
);
```

Valid status strings observed in the codebase: `'pending-setup'`, `'active'`.

### Step 4 — Log a status-change history entry

Immediately after the DB update, add a history entry against the service table:

```php
$GLOBALS['tf']->history->add(
    $settings['TABLE'],
    'change_status',
    'new-status',                                   // must match DB update above
    $serviceInfo[$settings['PREFIX'].'_id'],
    $serviceInfo[$settings['PREFIX'].'_custid']
);
```

### Step 5 — Queue the vpsqueue action

After the status history entry, queue the provisioning action:

```php
$GLOBALS['tf']->history->add(
    self::$module.'queue',                          // always 'vpsqueue'
    $serviceInfo[$settings['PREFIX'].'_id'],
    'action_name',                                  // see table below
    '',
    $serviceInfo[$settings['PREFIX'].'_custid']
);
```

| Handler       | Action(s)                  |
|---------------|----------------------------|
| setEnable     | `'initial_install'`        |
| setReactivate | `'initial_install'` OR `'enable'` + `'start'` (see Step 6) |
| setTerminate  | `'destroy'`                |
| setDisable    | *(empty closure)*          |

### Step 6 — Reactivate: branch on server status

`setReactivate` must check whether the VPS was fully deleted or just suspended:

```php
->setReactivate(function ($service) {
    $serviceTypes = run_event('get_service_types', false, self::$module);
    $serviceInfo  = $service->getServiceInfo();
    $settings     = get_module_settings(self::$module);
    $db           = get_module_db(self::$module);
    if ($serviceInfo[$settings['PREFIX'].'_server_status'] === 'deleted'
        || $serviceInfo[$settings['PREFIX'].'_ip'] == '') {
        // full re-provision
        $db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='pending-setup' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
        $GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'pending-setup', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
        $GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'initial_install', '', $serviceInfo[$settings['PREFIX'].'_custid']);
    } else {
        // resume existing VPS
        $db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='active' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
        $GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
        $GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'enable', '', $serviceInfo[$settings['PREFIX'].'_custid']);
        $GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'start', '', $serviceInfo[$settings['PREFIX'].'_custid']);
    }
    // send admin email (Step 7)
})
```

### Step 7 — Send admin email (Enable / Reactivate)

For `setEnable`, call the dedicated helper:

```php
admin_email_vps_pending_setup($serviceInfo[$settings['PREFIX'].'_id']);
```

For `setReactivate`, render a Smarty template and send via `\MyAdmin\Mail`:

```php
$smarty = new \TFSmarty();
$smarty->assign('vps_name', $serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name']);
$email   = $smarty->fetch('email/admin/vps_reactivated.tpl');
$subject = $serviceInfo[$settings['TITLE_FIELD']].' '
         . $serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name']
         . ' '.$settings['TBLNAME'].' Reactivated';
(new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/vps_reactivated.tpl');
```

### Step 8 — Terminate: release IPs and remove reverse DNS

`setTerminate` must free associated IPs before queuing destroy:

```php
->setTerminate(function ($service) {
    $serviceInfo  = $service->getServiceInfo();
    $settings     = get_module_settings(self::$module);
    $db           = get_module_db(self::$module);
    $ips = [];
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
})
```

### Step 9 — Log with `myadmin_log` for operational messages

When adding informational logging beyond history (e.g. slice changes, custom actions):

```php
myadmin_log(self::$module, 'info', self::$name." Your message for {$settings['TBLNAME']} {$serviceInfo[$settings['PREFIX'].'_id']}", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
```

Verify the log entry appears in the module log before running tests.

### Step 10 — Run tests

Run the full test suite using the project's composer script (config: `phpunit.xml.dist`):

```
composer test
```

Verify `tests/PluginTest.php` passes. If adding new history/queue action names, add a corresponding assertion in `tests/PluginTest.php`.

## Examples

**User says:** "Add handling so that when a VPS is enabled it queues a custom `post_install` action instead of `initial_install`"

**Actions taken:**
1. Open `src/Plugin.php`, locate `setEnable` closure inside `loadProcessing()`.
2. Replace the `history->add` queue call:
   ```php
   // Before
   $GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'initial_install', '', $serviceInfo[$settings['PREFIX'].'_custid']);
   // After
   $GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'post_install', '', $serviceInfo[$settings['PREFIX'].'_custid']);
   ```
3. Run `composer test` — verify `tests/PluginTest.php` still passes.

**Result:** `vpsqueue` receives `post_install` instead of `initial_install` when a service is enabled.

## Common Issues

- **`Call to undefined function get_module_settings()`** — the closure is running outside the MyAdmin bootstrap. Confirm the test bootstrap at `tests/bootstrap.php` stubs `get_module_settings` and `get_module_db`.
- **`Undefined index: PREFIX`** — `get_module_settings()` returned an unexpected shape. Add `var_dump($settings)` to verify the module string passed is `'vps'` (matches `self::$module`).
- **History entry appears on wrong table** — status-change entries must use `$settings['TABLE']` (`'vps'`); queue entries must use `self::$module.'queue'` (`'vpsqueue'`). Swapping these is the most common mistake.
- **IP update query affects wrong rows** — the terminate IP query uses `ips_{$settings['PREFIX']}` as both the column name and the bind value. Confirm `$settings['PREFIX']` is `'vps'`, making the column `ips_vps`.
- **`validIp()` undefined in terminate closure** — add `function_requirements('validIp')` before the foreach, or confirm the bootstrap already loads `include/validations.php`.
- **`\TFSmarty` class not found in reactivate email** — ensure `TFSmarty` is autoloaded by the parent MyAdmin platform; this class is not in this module's `composer.json`.
