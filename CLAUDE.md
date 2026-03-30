# MyAdmin VPS Module

Composer plugin package `detain/myadmin-vps-module` — VPS provisioning, lifecycle management, slice-based scaling, and SOAP/REST API for the MyAdmin billing platform.

## Commands

```bash
composer install              # install deps including phpunit
composer test                 # run all tests (config: phpunit.xml.dist)
composer test:unit            # run unit tests only
```

## Structure

- **Plugin class**: `src/Plugin.php` — `Detain\MyAdminVps\Plugin` · registers hooks, API endpoints, settings, addon handlers
- **API functions**: `src/api.php` — procedural functions loaded on-demand via `function_requirements()`
- **CLI utility**: `bin/check_vlan_ips.php` — validates VLAN IP ranges against `vps_ips`/`qs_ips` tables
- **Tests**: `tests/ApiFunctionsTest.php` · `tests/PluginTest.php` · bootstrap stubs in `tests/bootstrap.php`
- **Autoload**: `Detain\MyAdminVps\` → `src/` · `Detain\MyAdminVps\Tests\` → `tests/`
- **CI/CD**: `.github/` — contains `workflows/` with automated test and deployment pipelines
- **IDE config**: `.idea/` — JetBrains project files including `inspectionProfiles/`, `deployment.xml`, `encodings.xml`

## Plugin Architecture

`Plugin::getHooks()` returns hook map consumed by MyAdmin's EventDispatcher:

```php
public static function getHooks() {
    return [
        'api.register'               => [__CLASS__, 'apiRegister'],
        'function.requirements'      => [__CLASS__, 'getRequirements'],
        self::$module.'.load_processing' => [__CLASS__, 'loadProcessing'],
        self::$module.'.load_addons'     => [__CLASS__, 'getAddon'],
        self::$module.'.settings'        => [__CLASS__, 'getSettings'],
    ];
}
```

`Plugin::getRequirements()` lazy-loads `src/api.php` functions:

```php
public static function getRequirements(GenericEvent $event) {
    $loader = $event->getSubject();
    $loader->add_requirement('api_validate_buy_vps', 'src/api.php');
    $loader->add_requirement('api_buy_vps',          'src/api.php');
}
```

## API Function Pattern (`src/api.php`)

All public API functions follow validate → place flow:

```php
function api_buy_vps($os, $slices, $platform, $controlpanel, $period,
                     $location, $version, $hostname, $coupon, $rootpass,
                     $comment = '', $ipv6only = false) {
    $custid = get_custid($GLOBALS['tf']->session->account_id, 'vps');
    function_requirements('validate_buy_vps');
    $validation = validate_buy_vps($custid, ...);
    if ($validation['continue'] === true) {
        function_requirements('place_buy_vps');
        $order_response = place_buy_vps(...);
        $return['status'] = 'ok';
        $return['invoices'] = implode(',', $order_response['real_iids']);
    } else {
        $return['status'] = 'error';
        $return['status_text'] = implode("\n", $validation['errors']);
    }
    return $return;
}
```

Return arrays always include: `status`, `status_text`, `invoices`, `cost`.

## Lifecycle Handlers (`Plugin::loadProcessing`)

Closures passed to `setEnable`/`setReactivate`/`setDisable`/`setTerminate`:

```php
->setTerminate(function ($service) {
    $serviceInfo = $service->getServiceInfo();
    $settings = get_module_settings(self::$module);   // PREFIX, TABLE, TBLNAME
    $db = get_module_db(self::$module);
    $db->query("update {$settings['TABLE']} set ...", __LINE__, __FILE__);
    myadmin_log(self::$module, 'info', 'message', __LINE__, __FILE__, self::$module, $id);
    $GLOBALS['tf']->history->add(self::$module.'queue', $id, 'destroy', '', $custid);
})
```

## Module Settings (`Plugin::$settings`)

Key constants used throughout: `PREFIX` = `'vps'` · `TABLE` = `'vps'` · `TBLNAME` = `'VPS'` · `TITLE_FIELD` = `'vps_hostname'` · `TITLE_FIELD2` = `'vps_ip'`

Access pattern: `$settings['PREFIX'].'_id'` → `vps_id`, `$settings['TABLE']` → `vps`.

## API Registration (`Plugin::apiRegister`)

```php
api_register_array('buy_vps_result_status', [
    'status' => 'string', 'status_text' => 'string',
    'invoices' => 'string', 'cost' => 'float'
]);
api_register('api_buy_vps', ['os'=>'string',...], ['return'=>'buy_vps_result_status'], 'desc');
```

## Testing Conventions

- Config: `phpunit.xml.dist` · Bootstrap: `tests/bootstrap.php`
- Use `ReflectionFunction` to assert parameter names/counts without calling functions
- `tests/bootstrap.php` stubs: `myadmin_log`, `function_requirements`, `get_module_db`, `get_module_settings`, `run_event`, `validate_buy_vps`, `place_buy_vps`, `$GLOBALS['tf']`
- Tests extend `PHPUnit\Framework\TestCase` under namespace `Detain\MyAdminVps\Tests`

## Coding Conventions

- Tabs for indentation (enforced by `.scrutinizer.yml`)
- `camelCase` for parameters and properties
- `UPPERCASE` for constants
- Always pass `__LINE__, __FILE__` as last args to `$db->query()` and `myadmin_log()`
- Admin-only operations: gate with `$GLOBALS['tf']->ima != 'admin'`
- Cast user-supplied server IDs: `$server = (int)$server`
- IP validation: use `validIp($ip)` before acting on IP strings
- Before committing: `caliber refresh && git add CLAUDE.md .claude/`

<!-- caliber:managed:pre-commit -->
## Before Committing

**IMPORTANT:** Before every git commit, you MUST ensure Caliber syncs agent configs with the latest code changes.

First, check if the pre-commit hook is already installed:
```bash
grep -q "caliber" .git/hooks/pre-commit 2>/dev/null && echo "hook-active" || echo "no-hook"
```

- If **hook-active**: the hook handles sync automatically — just commit normally. Tell the user: "Caliber will sync your agent configs automatically via the pre-commit hook."
- If **no-hook**: run Caliber manually before committing:
  1. Tell the user: "Caliber: Syncing agent configs with your latest changes..."
  2. Run: `caliber refresh && git add CLAUDE.md .claude/ .cursor/ .cursorrules .github/copilot-instructions.md .github/instructions/ AGENTS.md CALIBER_LEARNINGS.md .agents/ .opencode/ 2>/dev/null`
  3. After it completes, briefly tell the user what Caliber updated. Then proceed with the commit.

If `caliber` is not found, tell the user: "This project uses Caliber for agent config sync. Run /setup-caliber to get set up."
<!-- /caliber:managed:pre-commit -->

<!-- caliber:managed:learnings -->
## Session Learnings

Read `CALIBER_LEARNINGS.md` for patterns and anti-patterns learned from previous sessions.
These are auto-extracted from real tool usage — treat them as project-specific rules.
<!-- /caliber:managed:learnings -->

<!-- caliber:managed:sync -->
## Context Sync

This project uses [Caliber](https://github.com/caliber-ai-org/ai-setup) to keep AI agent configs in sync across Claude Code, Cursor, Copilot, and Codex.
Configs update automatically before each commit via `caliber refresh`.
If the pre-commit hook is not set up, run `/setup-caliber` to configure everything automatically.
<!-- /caliber:managed:sync -->
