---
name: plugin-hook
description: Adds a new event hook to Plugin::getHooks() and its static handler method, and registers any lazy-loaded api.php functions via Plugin::getRequirements(). Use when user says 'add hook', 'register event', 'add plugin handler', or needs to wire a new event into the Symfony EventDispatcher. Do NOT use for adding API endpoints (api_register calls in apiRegister), lifecycle closures (setEnable/setDisable/setTerminate in loadProcessing), or addon handlers (getAddon).
---
# plugin-hook

## Critical

- ALL handler methods MUST be `public static function` — the EventDispatcher calls them statically via `[__CLASS__, 'methodName']`.
- Handler methods MUST type-hint `GenericEvent $event` and import `use Symfony\Component\EventDispatcher\GenericEvent;` (already at top of `src/Plugin.php`).
- Hook keys that are module-scoped MUST use `self::$module` concatenation (e.g., `self::$module.'.your_event'`) — never hardcode `'vps'` as the prefix.
- `getRequirements()` maps function names to `src/api.php` — use that exact path string for any new function from `src/api.php`.
- Do NOT add hooks in any file other than `src/Plugin.php`.

## Instructions

### Step 1 — Add the hook key to `getHooks()`

Open `src/Plugin.php`. In `getHooks()` (line 50–59), add a new entry to the returned array.

For **global events** (not module-scoped):
```php
'event.name' => [__CLASS__, 'handlerMethodName'],
```

For **module-scoped events**:
```php
self::$module.'.event_name' => [__CLASS__, 'handlerMethodName'],
```

Existing entries for reference:
```php
'api.register'                       => [__CLASS__, 'apiRegister'],
'function.requirements'              => [__CLASS__, 'getRequirements'],
self::$module.'.load_processing'     => [__CLASS__, 'loadProcessing'],
self::$module.'.load_addons'         => [__CLASS__, 'getAddon'],
self::$module.'.settings'            => [__CLASS__, 'getSettings'],
```

Verify the new key does not duplicate an existing key before proceeding.

### Step 2 — Add the handler method to `Plugin`

Add a `public static` method to the `Plugin` class in `src/Plugin.php`. Retrieve the subject from the event using `$event->getSubject()`.

```php
/**
 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
 */
public static function handlerMethodName(GenericEvent $event)
{
    /**
     * @var \SubjectType $subject
     */
    $subject = $event->getSubject();
    $settings = get_module_settings(self::$module);
    // handler logic here
}
```

Verify:
- Method name exactly matches the string used in Step 1.
- No `$this` usage — method is static throughout.
- Uses `self::$module` (not the string `'vps'`) wherever the module slug is needed.

### Step 3 — Register any new `src/api.php` functions in `getRequirements()` (only if needed)

If the handler calls `function_requirements('some_api_function')` and `some_api_function` is defined in `src/api.php`, add a line to `getRequirements()` (lines 64–70):

```php
$loader->add_requirement('some_api_function', 'src/api.php');
```

Existing pattern:
```php
public static function getRequirements(GenericEvent $event)
{
    $loader = $event->getSubject();
    $loader->add_requirement('api_validate_buy_vps', 'src/api.php');
    $loader->add_requirement('api_buy_vps',          'src/api.php');
    $loader->add_requirement('api_buy_vps_admin',    'src/api.php');
}
```

Verify: only add entries for functions in `src/api.php`; functions in MyAdmin core are registered by their own modules.

### Step 4 — Run tests

Run the full test suite using the project's composer script (config: `phpunit.xml.dist`):

```
composer test
```

Verify all tests pass before considering the work complete.

## Examples

**User says:** "Add a hook for `vps.get_status` that reads the VPS status from the DB."

**Step 1** — add to `getHooks()`:
```php
self::$module.'.get_status' => [__CLASS__, 'getStatus'],
```

**Step 2** — add handler method:
```php
/**
 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
 */
public static function getStatus(GenericEvent $event)
{
    /**
     * @var \ServiceHandler $subject
     */
    $subject = $event->getSubject();
    $settings = get_module_settings(self::$module);
    $db = get_module_db(self::$module);
    $id = intval($subject->getId());
    $db->query("SELECT {$settings['PREFIX']}_status FROM {$settings['TABLE']} WHERE {$settings['PREFIX']}_id='{$id}'", __LINE__, __FILE__);
    if ($db->num_rows() > 0) {
        $db->next_record(MYSQL_ASSOC);
        $event['status'] = $db->Record[$settings['PREFIX'].'_status'];
    }
}
```

**Step 3** — no new `src/api.php` functions needed, skip.

**Step 4** — `composer test` passes.

**Result:** `getHooks()` now returns 6 entries; the new static method appears in `src/Plugin.php`.

## Common Issues

**Handler never called / event fires but method not reached:**
- Confirm the key in `getHooks()` exactly matches the event name dispatched by the platform (check `run_event()` calls in the codebase with `grep -r "run_event" src/`).
- Confirm the value is `[__CLASS__, 'handlerMethodName']` — a two-element array, NOT a closure.

**`Call to undefined function some_api_function()`:**
- The function is in `src/api.php` but not registered. Add `$loader->add_requirement('some_api_function', 'src/api.php');` to `getRequirements()`.

**`Non-static method Plugin::handlerMethodName() cannot be called statically`:**
- Remove the `public` keyword and re-declare as `public static function handlerMethodName(GenericEvent $event)`.

**`Class 'Symfony\Component\EventDispatcher\GenericEvent' not found` in tests:**
- Confirm `composer install` has run and `vendor/symfony/event-dispatcher` is present.
- Check `tests/bootstrap.php` stubs are not overriding the real class.

**Accidentally hardcoded `'vps'` instead of `self::$module`:**
- Search: `grep -n "'vps\.'" src/Plugin.php` — any match that is not inside a string for a DB table name should use `self::$module.'.'` instead.
