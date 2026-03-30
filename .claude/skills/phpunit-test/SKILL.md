---
name: phpunit-test
description: Writes PHPUnit test classes under tests/ in namespace Detain\MyAdminVps\Tests using ReflectionFunction/ReflectionClass for parameter assertions and assertStringContainsString for source-level checks. Stubs rely on tests/bootstrap.php. Use when user says 'write tests', 'add test for', 'test this function', or adds new functions to src/api.php or src/Plugin.php. Do NOT use for integration tests that require a live DB or the full MyAdmin application stack.
---
# PHPUnit Test

## Critical

- **Never execute DB-touching code directly.** All code that calls `get_module_db()`, `myadmin_log()`, `run_event()`, or `function_requirements()` must be tested via static analysis (reflection + source string assertions), not live execution.
- **All stubs live in `tests/bootstrap.php`.** Do not redefine stubs inside test files. If the function under test requires a new global function, add a `if (!function_exists(...))` guard to `tests/bootstrap.php` only.
- **Namespace must be `Detain\MyAdminVps\Tests`** — matches the autoload entry in `composer.json`.
- **Run tests with** `composer test` (config: `phpunit.xml.dist` at repo root).

## Instructions

### Step 1 — Identify what to test

Determine whether the target is:
- A **procedural function** in `src/api.php` → use `tests/ApiFunctionsTest.php` pattern
- A **Plugin class method** in `src/Plugin.php` → use `tests/PluginTest.php` pattern

Verify the function/method exists in source before writing tests.

### Step 2 — Cache the source file in `setUpBeforeClass` (api.php functions)

For procedural functions in `src/api.php`, add a `setUpBeforeClass` that:
1. Resolves the absolute path: `dirname(__DIR__) . '/src/api.php'`
2. Calls `self::fail()` if the file is missing
3. Reads source into a `private static string $source` property
4. Guards the `require_once` with `function_exists()` to avoid re-declaration

```php
public static function setUpBeforeClass(): void
{
    self::$apiSourcePath = dirname(__DIR__) . '/src/api.php';
    if (!file_exists(self::$apiSourcePath)) {
        self::fail('src/api.php not found');
    }
    self::$source = file_get_contents(self::$apiSourcePath);
    if (!function_exists('my_new_function')) {
        require_once self::$apiSourcePath;
    }
}
```

Verify `self::$source` is populated before proceeding.

### Step 3 — Write existence tests

One test per public function:

```php
public function testMyNewFunctionExists(): void
{
    $this->assertTrue(
        function_exists('my_new_function'),
        'Function my_new_function() should exist'
    );
}
```

### Step 4 — Write parameter-signature tests via `ReflectionFunction`

For each function, assert total parameter count, required parameter count, parameter names in order, and default values for optional params:

```php
public function testMyNewFunctionParameterCount(): void
{
    $ref = new ReflectionFunction('my_new_function');
    $this->assertSame(3, $ref->getNumberOfParameters());
    $this->assertSame(2, $ref->getNumberOfRequiredParameters());
}

public function testMyNewFunctionParameterNames(): void
{
    $ref = new ReflectionFunction('my_new_function');
    $names = array_map(fn($p) => $p->getName(), $ref->getParameters());
    $this->assertSame(['custid', 'vps_id', 'comment'], $names);
}

public function testMyNewFunctionCommentDefaultsToEmpty(): void
{
    $ref = new ReflectionFunction('my_new_function');
    $param = $ref->getParameters()[2];
    $this->assertTrue($param->isDefaultValueAvailable());
    $this->assertSame('', $param->getDefaultValue());
}
```

Verify the `ReflectionFunction` constructor does not throw (function must exist).

### Step 5 — Write source-level assertions

For patterns the function must contain (validate → place flow, status keys, required calls):

```php
public function testMyNewFunctionCallsFunctionRequirements(): void
{
    $this->assertStringContainsString(
        "function_requirements('validate_something')",
        self::$source
    );
}

public function testMyNewFunctionReturnsStatusFields(): void
{
    $this->assertStringContainsString("'status'] = 'ok'", self::$source);
    $this->assertStringContainsString("'status'] = 'error'", self::$source);
}
```

Use `preg_match_all` when asserting multiple occurrences (e.g., `get_custid` called 3+ times).

### Step 6 — Write functional tests using stubs from bootstrap.php

Only call functions whose global dependencies are already stubbed in `tests/bootstrap.php`. Stubs available: `validate_buy_vps`, `place_buy_vps`, `get_custid`, `get_module_db`, `function_requirements`, `myadmin_log`, `run_event`, `$GLOBALS['tf']`.

If a new stub is needed, add it to `tests/bootstrap.php` with an `if (!function_exists(...))` guard.

```php
public function testMyNewFunctionReturnsOkStatus(): void
{
    $result = my_new_function('centos-7-x86_64.tar.gz', 1, 'kvm', 'none',
        1, 1, 'centos7', 'test.example.com', '', 'testpass123');

    $this->assertIsArray($result);
    $this->assertArrayHasKey('status', $result);
    $this->assertSame('ok', $result['status']);
    $this->assertArrayHasKey('invoices', $result);
    $this->assertArrayHasKey('cost', $result);
}
```

### Step 7 — For Plugin class tests, use `ReflectionClass`

Initialise in `setUp()`, not `setUpBeforeClass()`:

```php
protected function setUp(): void
{
    $this->reflection = new ReflectionClass(Plugin::class);
}
```

Then assert method visibility, static modifier, parameter count, and type hints:

```php
public function testMyMethodIsPublicStatic(): void
{
    $method = $this->reflection->getMethod('myMethod');
    $this->assertTrue($method->isPublic());
    $this->assertTrue($method->isStatic());
    $this->assertSame(1, $method->getNumberOfRequiredParameters());
}
```

For event handler signatures, assert the first parameter type is `GenericEvent`:

```php
$type = $method->getParameters()[0]->getType();
$this->assertSame(GenericEvent::class, $type->getName());
```

### Step 8 — Run and verify

Run the full suite or target a single class (config: `phpunit.xml.dist`):

```
composer test
```

To filter to a single test class, run directly:

```
composer test:unit -- --filter MyNewFunctionTest
```

All tests must pass with no errors or warnings before committing.

## Examples

**User says:** "Add tests for a new `api_cancel_vps` function in src/api.php"

**Actions taken:**
1. Confirm `api_cancel_vps` exists in `src/api.php`; note its signature: `($vps_id, $custid, $reason = '')`
2. Create `tests/ApiCancelVpsTest.php`:

```php
<?php
/**
 * Unit tests for api_cancel_vps in src/api.php
 *
 * @package Detain\MyAdminVps\Tests
 */

namespace Detain\MyAdminVps\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionFunction;

class ApiCancelVpsTest extends TestCase
{
    private static string $apiSourcePath;
    private static string $source;

    public static function setUpBeforeClass(): void
    {
        self::$apiSourcePath = dirname(__DIR__) . '/src/api.php';
        if (!file_exists(self::$apiSourcePath)) {
            self::fail('src/api.php not found');
        }
        self::$source = file_get_contents(self::$apiSourcePath);
        if (!function_exists('api_cancel_vps')) {
            require_once self::$apiSourcePath;
        }
    }

    public function testApiCancelVpsFunctionExists(): void
    {
        $this->assertTrue(function_exists('api_cancel_vps'));
    }

    public function testApiCancelVpsParameterCount(): void
    {
        $ref = new ReflectionFunction('api_cancel_vps');
        $this->assertSame(3, $ref->getNumberOfParameters());
        $this->assertSame(2, $ref->getNumberOfRequiredParameters());
    }

    public function testApiCancelVpsParameterNames(): void
    {
        $ref = new ReflectionFunction('api_cancel_vps');
        $names = array_map(fn($p) => $p->getName(), $ref->getParameters());
        $this->assertSame(['vps_id', 'custid', 'reason'], $names);
    }

    public function testApiCancelVpsReasonDefaultsToEmpty(): void
    {
        $ref = new ReflectionFunction('api_cancel_vps');
        $param = $ref->getParameters()[2];
        $this->assertTrue($param->isDefaultValueAvailable());
        $this->assertSame('', $param->getDefaultValue());
    }

    public function testApiCancelVpsReturnsStatusFields(): void
    {
        $this->assertStringContainsString("'status'] = 'ok'", self::$source);
        $this->assertStringContainsString("'status'] = 'error'", self::$source);
    }
}
```

3. Run `composer test:unit -- --filter ApiCancelVpsTest` — all green.

**Result:** Test class in `tests/ApiCancelVpsTest.php`, namespace `Detain\MyAdminVps\Tests`, no DB or framework dependencies.

## Common Issues

**`Cannot redeclare function api_cancel_vps`**
You included `src/api.php` unconditionally in `setUpBeforeClass`. Wrap with:
```php
if (!function_exists('api_cancel_vps')) {
    require_once self::$apiSourcePath;
}
```

**`ReflectionFunction: Function api_cancel_vps() does not exist`**
The `require_once` guard above prevented loading because an earlier test suite already loaded a *different* version of the file. Ensure `tests/bootstrap.php` loads `src/api.php` once, or remove competing `require_once` calls from other test files.

**`Class 'Detain\MyAdminVps\Tests\...' not found`**
Namespace does not match directory. The autoload entry in `composer.json` maps `Detain\MyAdminVps\Tests\` → `tests/`. File must be in `tests/` (not a subdirectory) unless you update `composer.json` and re-run `composer dump-autoload`.

**`Call to undefined function get_custid()`**
The stub is missing. Add to `tests/bootstrap.php`:
```php
if (!function_exists('get_custid')) {
    function get_custid($account_id, $module) { return $account_id; }
}
```

**`Undefined index: tf` / `$GLOBALS['tf']` error**
`tests/bootstrap.php` sets up `$GLOBALS['tf']` — verify the bootstrap file is loaded. Check `phpunit.xml.dist` has the `bootstrap` attribute pointing to `tests/bootstrap.php`.

**Tests pass locally but fail in CI**
CI runs `composer test` from the repo root. Confirm `phpunit.xml.dist` is at the repo root (it is) and that `composer install` ran before the test step (see `.github/workflows/tests.yml`).
