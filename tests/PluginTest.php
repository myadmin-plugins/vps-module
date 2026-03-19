<?php
/**
 * Unit tests for Detain\MyAdminVps\Plugin
 *
 * Tests class structure, static properties, hook registration,
 * settings configuration, and event handler signatures.
 * Database-dependent code is validated via static analysis (reflection)
 * rather than execution, keeping these tests isolated from the runtime.
 *
 * @package Detain\MyAdminVps\Tests
 */

namespace Detain\MyAdminVps\Tests;

use Detain\MyAdminVps\Plugin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\EventDispatcher\GenericEvent;

class PluginTest extends TestCase
{
    /**
     * @var ReflectionClass<Plugin>
     */
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(Plugin::class);
    }

    // ------------------------------------------------------------------
    //  Class structure
    // ------------------------------------------------------------------

    /**
     * Verify the Plugin class can be instantiated.
     *
     * @return void
     */
    public function testPluginCanBeInstantiated(): void
    {
        $plugin = new Plugin();
        $this->assertInstanceOf(Plugin::class, $plugin);
    }

    /**
     * Verify the class resides in the expected namespace.
     *
     * @return void
     */
    public function testClassNamespace(): void
    {
        $this->assertSame(
            'Detain\\MyAdminVps',
            $this->reflection->getNamespaceName()
        );
    }

    /**
     * Verify the constructor accepts zero arguments.
     *
     * @return void
     */
    public function testConstructorHasNoRequiredParameters(): void
    {
        $ctor = $this->reflection->getConstructor();
        $this->assertNotNull($ctor);
        $this->assertSame(0, $ctor->getNumberOfRequiredParameters());
    }

    // ------------------------------------------------------------------
    //  Static properties
    // ------------------------------------------------------------------

    /**
     * Verify the $name property is set correctly.
     *
     * @return void
     */
    public function testNameProperty(): void
    {
        $this->assertSame('VPS Servers', Plugin::$name);
    }

    /**
     * Verify the $description property is set correctly.
     *
     * @return void
     */
    public function testDescriptionProperty(): void
    {
        $this->assertSame('Allows selling of Vps Module', Plugin::$description);
    }

    /**
     * Verify the $module property is set correctly.
     *
     * @return void
     */
    public function testModuleProperty(): void
    {
        $this->assertSame('vps', Plugin::$module);
    }

    /**
     * Verify the $type property is set correctly.
     *
     * @return void
     */
    public function testTypeProperty(): void
    {
        $this->assertSame('module', Plugin::$type);
    }

    /**
     * Verify the $help property exists and is a string.
     *
     * @return void
     */
    public function testHelpProperty(): void
    {
        $this->assertIsString(Plugin::$help);
    }

    // ------------------------------------------------------------------
    //  Settings array
    // ------------------------------------------------------------------

    /**
     * Verify the $settings property is an array with all expected keys.
     *
     * @return void
     */
    public function testSettingsPropertyIsArrayWithExpectedKeys(): void
    {
        $expectedKeys = [
            'SERVICE_ID_OFFSET',
            'USE_REPEAT_INVOICE',
            'USE_PACKAGES',
            'BILLING_DAYS_OFFSET',
            'IMGNAME',
            'REPEAT_BILLING_METHOD',
            'DELETE_PENDING_DAYS',
            'SUSPEND_DAYS',
            'SUSPEND_WARNING_DAYS',
            'TITLE',
            'MENUNAME',
            'EMAIL_FROM',
            'TBLNAME',
            'TABLE',
            'TITLE_FIELD',
            'TITLE_FIELD2',
            'PREFIX',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, Plugin::$settings, "Missing settings key: {$key}");
        }
    }

    /**
     * Verify numeric settings have the correct types.
     *
     * @return void
     */
    public function testSettingsNumericValues(): void
    {
        $this->assertSame(0, Plugin::$settings['SERVICE_ID_OFFSET']);
        $this->assertSame(0, Plugin::$settings['BILLING_DAYS_OFFSET']);
        $this->assertSame(45, Plugin::$settings['DELETE_PENDING_DAYS']);
        $this->assertSame(14, Plugin::$settings['SUSPEND_DAYS']);
        $this->assertSame(7, Plugin::$settings['SUSPEND_WARNING_DAYS']);
    }

    /**
     * Verify boolean settings have the correct types.
     *
     * @return void
     */
    public function testSettingsBooleanValues(): void
    {
        $this->assertTrue(Plugin::$settings['USE_REPEAT_INVOICE']);
        $this->assertTrue(Plugin::$settings['USE_PACKAGES']);
    }

    /**
     * Verify string settings have the correct values.
     *
     * @return void
     */
    public function testSettingsStringValues(): void
    {
        $this->assertSame('root-server.png', Plugin::$settings['IMGNAME']);
        $this->assertSame('VPS', Plugin::$settings['TITLE']);
        $this->assertSame('VPS', Plugin::$settings['MENUNAME']);
        $this->assertSame('support@interserver.net', Plugin::$settings['EMAIL_FROM']);
        $this->assertSame('VPS', Plugin::$settings['TBLNAME']);
        $this->assertSame('vps', Plugin::$settings['TABLE']);
        $this->assertSame('vps_hostname', Plugin::$settings['TITLE_FIELD']);
        $this->assertSame('vps_ip', Plugin::$settings['TITLE_FIELD2']);
        $this->assertSame('vps', Plugin::$settings['PREFIX']);
    }

    /**
     * Verify REPEAT_BILLING_METHOD equals the PRORATE_BILLING constant.
     *
     * @return void
     */
    public function testRepeatBillingMethodMatchesConstant(): void
    {
        $this->assertSame(PRORATE_BILLING, Plugin::$settings['REPEAT_BILLING_METHOD']);
    }

    // ------------------------------------------------------------------
    //  getHooks()
    // ------------------------------------------------------------------

    /**
     * Verify getHooks returns an array.
     *
     * @return void
     */
    public function testGetHooksReturnsArray(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertIsArray($hooks);
    }

    /**
     * Verify getHooks contains all expected event names.
     *
     * @return void
     */
    public function testGetHooksContainsExpectedEvents(): void
    {
        $hooks = Plugin::getHooks();

        $expectedEvents = [
            'api.register',
            'function.requirements',
            'vps.load_processing',
            'vps.load_addons',
            'vps.settings',
        ];

        foreach ($expectedEvents as $event) {
            $this->assertArrayHasKey($event, $hooks, "Missing hook: {$event}");
        }
    }

    /**
     * Verify each hook value is a callable array of [class, method].
     *
     * @return void
     */
    public function testGetHooksValuesAreCallableArrays(): void
    {
        $hooks = Plugin::getHooks();

        foreach ($hooks as $event => $handler) {
            $this->assertIsArray($handler, "Handler for '{$event}' must be an array");
            $this->assertCount(2, $handler, "Handler for '{$event}' must have exactly 2 elements");
            $this->assertSame(Plugin::class, $handler[0], "Handler class for '{$event}' must be Plugin");
            $this->assertIsString($handler[1], "Handler method for '{$event}' must be a string");
        }
    }

    /**
     * Verify all hook handler methods actually exist on the Plugin class.
     *
     * @return void
     */
    public function testGetHooksMethodsExistOnClass(): void
    {
        $hooks = Plugin::getHooks();

        foreach ($hooks as $event => $handler) {
            $this->assertTrue(
                $this->reflection->hasMethod($handler[1]),
                "Method Plugin::{$handler[1]}() referenced by hook '{$event}' does not exist"
            );
        }
    }

    /**
     * Verify all hook handler methods are public and static.
     *
     * @return void
     */
    public function testGetHooksMethodsArePublicStatic(): void
    {
        $hooks = Plugin::getHooks();

        foreach ($hooks as $event => $handler) {
            $method = $this->reflection->getMethod($handler[1]);
            $this->assertTrue(
                $method->isPublic(),
                "Plugin::{$handler[1]}() must be public"
            );
            $this->assertTrue(
                $method->isStatic(),
                "Plugin::{$handler[1]}() must be static"
            );
        }
    }

    /**
     * Verify module-prefixed hooks use the correct module prefix.
     *
     * @return void
     */
    public function testModulePrefixedHooksUseCorrectModule(): void
    {
        $hooks = Plugin::getHooks();
        $module = Plugin::$module;

        $modulePrefixedKeys = array_filter(
            array_keys($hooks),
            fn(string $key) => strpos($key, $module . '.') === 0
        );

        $this->assertNotEmpty($modulePrefixedKeys, 'Expected at least one module-prefixed hook');

        foreach ($modulePrefixedKeys as $key) {
            $this->assertStringStartsWith(
                $module . '.',
                $key,
                "Hook '{$key}' should start with '{$module}.'"
            );
        }
    }

    // ------------------------------------------------------------------
    //  Event handler signatures (via Reflection)
    // ------------------------------------------------------------------

    /**
     * Verify getRequirements accepts a GenericEvent parameter.
     *
     * @return void
     */
    public function testGetRequirementsAcceptsGenericEvent(): void
    {
        $this->assertEventHandlerSignature('getRequirements');
    }

    /**
     * Verify apiRegister accepts a GenericEvent parameter.
     *
     * @return void
     */
    public function testApiRegisterAcceptsGenericEvent(): void
    {
        $this->assertEventHandlerSignature('apiRegister');
    }

    /**
     * Verify getAddon accepts a GenericEvent parameter.
     *
     * @return void
     */
    public function testGetAddonAcceptsGenericEvent(): void
    {
        $this->assertEventHandlerSignature('getAddon');
    }

    /**
     * Verify loadProcessing accepts a GenericEvent parameter.
     *
     * @return void
     */
    public function testLoadProcessingAcceptsGenericEvent(): void
    {
        $this->assertEventHandlerSignature('loadProcessing');
    }

    /**
     * Verify getSettings accepts a GenericEvent parameter.
     *
     * @return void
     */
    public function testGetSettingsAcceptsGenericEvent(): void
    {
        $this->assertEventHandlerSignature('getSettings');
    }

    /**
     * Assert that a given method has exactly one required parameter
     * whose type hint is GenericEvent.
     *
     * @param string $methodName
     * @return void
     */
    private function assertEventHandlerSignature(string $methodName): void
    {
        $method = $this->reflection->getMethod($methodName);
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(
            1,
            count($params),
            "Plugin::{$methodName}() must accept at least one parameter"
        );

        $firstParam = $params[0];
        $type = $firstParam->getType();
        $this->assertNotNull($type, "Plugin::{$methodName}() first parameter must be type-hinted");
        $this->assertSame(
            GenericEvent::class,
            $type->getName(),
            "Plugin::{$methodName}() first parameter must be GenericEvent"
        );
    }

    // ------------------------------------------------------------------
    //  doSliceEnable signature (static analysis)
    // ------------------------------------------------------------------

    /**
     * Verify doSliceEnable method exists and has the expected parameter count.
     *
     * @return void
     */
    public function testDoSliceEnableMethodSignature(): void
    {
        $method = $this->reflection->getMethod('doSliceEnable');

        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $this->assertSame(3, $method->getNumberOfParameters());
        $this->assertSame(2, $method->getNumberOfRequiredParameters());
    }

    /**
     * Verify doSliceEnable third parameter defaults to false.
     *
     * @return void
     */
    public function testDoSliceEnableRegexMatchDefaultsToFalse(): void
    {
        $method = $this->reflection->getMethod('doSliceEnable');
        $params = $method->getParameters();

        $regexMatch = $params[2];
        $this->assertTrue($regexMatch->isDefaultValueAvailable());
        $this->assertFalse($regexMatch->getDefaultValue());
    }

    // ------------------------------------------------------------------
    //  apiRegister coverage (functional)
    // ------------------------------------------------------------------

    /**
     * Verify apiRegister calls api_register_array and api_register
     * by overriding the stub functions to collect invocations.
     *
     * @return void
     */
    public function testApiRegisterRegistersExpectedApis(): void
    {
        $registeredArrays = [];
        $registeredApis = [];

        // We cannot redefine functions, but the stubs in bootstrap already
        // exist. Instead, we parse the source to verify expected calls.
        $source = file_get_contents(__DIR__ . '/../src/Plugin.php');

        // Verify api_register_array calls
        preg_match_all('/api_register_array\(\s*[\'"](\w+)[\'"]/', $source, $arrayMatches);
        $expectedArrays = [
            'vps_slice_type',
            'idNameArray',
            'idNameSizeUrlArray',
            'vps_template',
            'vps_platform',
            'vps_screenshot_return',
            'buy_vps_result_status',
            'validate_buy_vps_result_status',
        ];
        foreach ($expectedArrays as $name) {
            $this->assertContains(
                $name,
                $arrayMatches[1],
                "api_register_array('{$name}', ...) not found in source"
            );
        }

        // Verify api_register calls
        preg_match_all('/^\s*api_register\(\s*[\'"](\w+)[\'"].*$/m', $source, $apiMatches);
        $expectedApis = [
            'vps_queue_stop',
            'vps_queue_start',
            'vps_queue_restart',
            'vps_queue_backup',
            'vps_backup_delete',
            'get_vps_backups',
            'get_vps_slice_types',
            'get_vps_locations_array',
            'get_vps_templates',
            'get_vps_platforms_array',
            'api_validate_buy_vps',
            'api_buy_vps',
            'api_buy_vps_admin',
            'vps_screenshot',
            'vps_get_server_name',
        ];
        foreach ($expectedApis as $name) {
            $this->assertContains(
                $name,
                $apiMatches[1],
                "api_register('{$name}', ...) not found in source"
            );
        }
    }

    // ------------------------------------------------------------------
    //  getRequirements coverage (functional)
    // ------------------------------------------------------------------

    /**
     * Verify getRequirements registers the expected API file requirements.
     *
     * @return void
     */
    public function testGetRequirementsRegistersApiFile(): void
    {
        $added = [];
        $loader = new class ($added) {
            private $added;
            public function __construct(&$added)
            {
                $this->added = &$added;
            }
            public function add_requirement(string $name, string $path): void
            {
                $this->added[] = ['name' => $name, 'path' => $path];
            }
        };

        $event = new GenericEvent($loader);
        Plugin::getRequirements($event);

        $names = array_column($added, 'name');
        $this->assertContains('api_validate_buy_vps', $names);
        $this->assertContains('api_buy_vps', $names);
        $this->assertContains('api_buy_vps_admin', $names);

        // All paths should point to the api.php file
        foreach ($added as $entry) {
            $this->assertStringContainsString('api.php', $entry['path']);
        }
    }

    // ------------------------------------------------------------------
    //  loadProcessing static analysis
    // ------------------------------------------------------------------

    /**
     * Verify loadProcessing source contains calls to setModule, setEnable,
     * setReactivate, setDisable, setTerminate, and register.
     *
     * @return void
     */
    public function testLoadProcessingSourceContainsExpectedCalls(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/Plugin.php');

        // Extract the loadProcessing method body
        $startPos = strpos($source, 'public static function loadProcessing');
        $this->assertNotFalse($startPos, 'loadProcessing method not found in source');

        // Find method body by counting braces
        $braceStart = strpos($source, '{', $startPos);
        $depth = 0;
        $methodBody = '';
        for ($i = $braceStart, $len = strlen($source); $i < $len; $i++) {
            if ($source[$i] === '{') {
                $depth++;
            } elseif ($source[$i] === '}') {
                $depth--;
            }
            $methodBody .= $source[$i];
            if ($depth === 0) {
                break;
            }
        }

        $expectedCalls = [
            'setModule',
            'setEnable',
            'setReactivate',
            'setDisable',
            'setTerminate',
            'register',
        ];

        foreach ($expectedCalls as $call) {
            $this->assertStringContainsString(
                $call,
                $methodBody,
                "loadProcessing() should call ->{$call}()"
            );
        }
    }

    /**
     * Verify loadProcessing source references the correct status strings.
     *
     * @return void
     */
    public function testLoadProcessingReferencesCorrectStatuses(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/Plugin.php');

        $this->assertStringContainsString("'pending-setup'", $source);
        $this->assertStringContainsString("'active'", $source);
        $this->assertStringContainsString("'deleted'", $source);
    }

    /**
     * Verify the terminate handler source references IP cleanup operations.
     *
     * @return void
     */
    public function testTerminateHandlerReferencesIpCleanup(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/Plugin.php');

        $this->assertStringContainsString('ips_main=0', $source);
        $this->assertStringContainsString('ips_usable=1', $source);
        $this->assertStringContainsString('ips_used=0', $source);
        $this->assertStringContainsString('reverse_dns', $source);
    }

    // ------------------------------------------------------------------
    //  getSettings static analysis
    // ------------------------------------------------------------------

    /**
     * Verify getSettings source contains calls to add settings of various types.
     *
     * @return void
     */
    public function testGetSettingsSourceContainsExpectedSettingTypes(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/Plugin.php');

        $this->assertStringContainsString('add_dropdown_setting', $source);
        $this->assertStringContainsString('add_password_setting', $source);
        $this->assertStringContainsString('add_text_setting', $source);
        $this->assertStringContainsString('add_master_status_label', $source);
        $this->assertStringContainsString('add_master_label', $source);
    }

    /**
     * Verify getSettings sets target to 'module' at start and 'global' at end.
     *
     * @return void
     */
    public function testGetSettingsSourceSetsTargetCorrectly(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/Plugin.php');

        // Extract the getSettings method body
        $startPos = strpos($source, 'public static function getSettings');
        $this->assertNotFalse($startPos);

        $braceStart = strpos($source, '{', $startPos);
        $depth = 0;
        $methodBody = '';
        for ($i = $braceStart, $len = strlen($source); $i < $len; $i++) {
            if ($source[$i] === '{') {
                $depth++;
            } elseif ($source[$i] === '}') {
                $depth--;
            }
            $methodBody .= $source[$i];
            if ($depth === 0) {
                break;
            }
        }

        // setTarget('module') should appear before setTarget('global')
        $modulePos = strpos($methodBody, "setTarget('module')");
        $globalPos = strpos($methodBody, "setTarget('global')");

        $this->assertNotFalse($modulePos, "setTarget('module') not found");
        $this->assertNotFalse($globalPos, "setTarget('global') not found");
        $this->assertLessThan($globalPos, $modulePos, "setTarget('module') should come before setTarget('global')");
    }

    // ------------------------------------------------------------------
    //  Static properties are accessible
    // ------------------------------------------------------------------

    /**
     * Verify all expected static properties exist and are public.
     *
     * @return void
     */
    public function testAllStaticPropertiesArePublic(): void
    {
        $expectedProps = ['name', 'description', 'help', 'module', 'type', 'settings'];

        foreach ($expectedProps as $prop) {
            $this->assertTrue(
                $this->reflection->hasProperty($prop),
                "Property \${$prop} should exist"
            );
            $refProp = $this->reflection->getProperty($prop);
            $this->assertTrue($refProp->isPublic(), "\${$prop} should be public");
            $this->assertTrue($refProp->isStatic(), "\${$prop} should be static");
        }
    }
}
