<?php
/**
 * Unit tests for the VPS API functions in src/api.php
 *
 * The functions reach the outside world through \MyAdmin\App (session, role)
 * and through the validate_buy_vps()/place_buy_vps() globals, all of which are
 * doubled in tests/bootstrap.php — so these tests EXECUTE the functions and
 * assert on behaviour and on captured arguments.
 *
 * Signature/contract coverage is derived from the source of truth rather than
 * pinned to literals: the SOAP parameter lists registered by
 * Plugin::apiRegister() are compared against the reflected signatures, so a
 * backward-compatible parameter addition needs no test edit while a one-sided
 * change to either fails.
 *
 * @package Detain\MyAdminVps\Tests
 */

namespace Detain\MyAdminVps\Tests;

use Detain\MyAdminVps\Plugin;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use Symfony\Component\EventDispatcher\GenericEvent;

class ApiFunctionsTest extends TestCase
{
    /**
     * Path to the api.php source file.
     *
     * @var string
     */
    private static string $apiSourcePath;

    /**
     * Cached source contents.
     *
     * @var string
     */
    private static string $source;

    /**
     * Load the api.php file once for all tests.
     *
     * The validate_buy_vps()/place_buy_vps() stubs and the \MyAdmin\App double
     * (bound to the shared Fakes\FakeTf) are set up in tests/bootstrap.php.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        self::$apiSourcePath = dirname(__DIR__) . '/src/api.php';

        // Ensure the source file exists
        if (!file_exists(self::$apiSourcePath)) {
            self::fail('src/api.php not found');
        }

        self::$source = file_get_contents(self::$apiSourcePath);

        // Include the file if the functions are not already defined.
        if (!function_exists('api_validate_buy_vps')) {
            require_once self::$apiSourcePath;
        }
    }

    /**
     * The tf stub reached through \MyAdmin\App is process-global, so restore it
     * to its pristine bootstrap state around every test — otherwise a test that
     * flips ima() to 'admin' silently grants admin to whatever runs next.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->tf()->reset();
        $GLOBALS['__vps_test_place_buy_vps_calls'] = [];
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->tf()->reset();
        unset(
            $GLOBALS['__vps_test_place_buy_vps_calls'],
            $GLOBALS['__vps_test_api_register_calls']
        );
    }

    /**
     * The shared tf stub built in tests/bootstrap.php.
     *
     * @return \Detain\MyAdminVps\Tests\Fakes\FakeTf
     */
    private function tf()
    {
        return $GLOBALS['__vps_test_tf'];
    }

    // ------------------------------------------------------------------
    //  Function existence
    // ------------------------------------------------------------------

    /**
     * Verify api_validate_buy_vps function exists.
     *
     * @return void
     */
    public function testApiValidateBuyVpsFunctionExists(): void
    {
        $this->assertTrue(
            function_exists('api_validate_buy_vps'),
            'Function api_validate_buy_vps() should exist'
        );
    }

    /**
     * Verify api_buy_vps function exists.
     *
     * @return void
     */
    public function testApiBuyVpsFunctionExists(): void
    {
        $this->assertTrue(
            function_exists('api_buy_vps'),
            'Function api_buy_vps() should exist'
        );
    }

    /**
     * Verify api_buy_vps_admin function exists.
     *
     * @return void
     */
    public function testApiBuyVpsAdminFunctionExists(): void
    {
        $this->assertTrue(
            function_exists('api_buy_vps_admin'),
            'Function api_buy_vps_admin() should exist'
        );
    }

    // ------------------------------------------------------------------
    //  SOAP contract <-> implementation signature agreement
    // ------------------------------------------------------------------

    /**
     * The three api_*_buy_vps functions are a PUBLIC SOAP API. Their parameter
     * lists are declared to the SOAP layer by Plugin::apiRegister() via
     * api_register(); if that declaration and the actual PHP signature ever
     * drift, SOAP callers silently pass the wrong argument into the wrong slot.
     *
     * So rather than pinning literal names/counts here (which just goes stale
     * every time a parameter is legitimately appended, as it did for $comment
     * and $ipv6only), assert the real invariant: the registered parameter list
     * equals the reflected signature, same names in the same order. Both sides
     * are read from the source of truth, so a compatible signature change needs
     * no test edit — but a one-sided change fails loudly.
     *
     * @dataProvider soapRegisteredApiFunctions
     *
     * @param string $function
     * @return void
     */
    public function testApiRegisterParameterListMatchesFunctionSignature(string $function): void
    {
        $registered = $this->captureApiRegistrations();

        $this->assertArrayHasKey(
            $function,
            $registered,
            "Plugin::apiRegister() no longer registers {$function}() with the SOAP layer"
        );

        $declared = array_keys($registered[$function]);
        $actual = array_map(
            fn($p) => $p->getName(),
            (new ReflectionFunction($function))->getParameters()
        );

        $this->assertSame(
            $actual,
            $declared,
            "The SOAP parameter list registered for {$function}() in Plugin::apiRegister() "
            . 'has drifted from the function signature in src/api.php. '
            . 'Registered: [' . implode(', ', $declared) . '] '
            . 'Actual: [' . implode(', ', $actual) . ']'
        );
    }

    /**
     * The API functions whose SOAP registration must track their signature.
     *
     * @return array<string,array{0:string}>
     */
    public static function soapRegisteredApiFunctions(): array
    {
        return [
            'api_validate_buy_vps' => ['api_validate_buy_vps'],
            'api_buy_vps' => ['api_buy_vps'],
            'api_buy_vps_admin' => ['api_buy_vps_admin'],
        ];
    }

    /**
     * Drive Plugin::apiRegister() with the capturing api_register() stub from
     * tests/bootstrap.php and return the registered parameter lists keyed by
     * API function name.
     *
     * @return array<string,array<string,string>>
     */
    private function captureApiRegistrations(): array
    {
        $GLOBALS['__vps_test_api_register_calls'] = [];
        Plugin::apiRegister(new GenericEvent(new \stdClass()));
        $captured = $GLOBALS['__vps_test_api_register_calls'];
        unset($GLOBALS['__vps_test_api_register_calls']);
        return $captured;
    }

    /**
     * Verify api_buy_vps_admin $server parameter defaults to 0.
     *
     * @return void
     */
    public function testApiBuyVpsAdminServerDefaultsToZero(): void
    {
        $ref = new ReflectionFunction('api_buy_vps_admin');
        $serverParam = null;
        foreach ($ref->getParameters() as $param) {
            if ($param->getName() === 'server') {
                $serverParam = $param;
                break;
            }
        }

        $this->assertNotNull($serverParam, 'api_buy_vps_admin() should take a $server parameter');
        $this->assertTrue($serverParam->isDefaultValueAvailable());
        $this->assertSame(0, $serverParam->getDefaultValue());
    }

    // ------------------------------------------------------------------
    //  Source-level analysis
    // ------------------------------------------------------------------

    /**
     * Verify api_validate_buy_vps source calls validate_buy_vps.
     *
     * @return void
     */
    public function testApiValidateBuyVpsCallsValidation(): void
    {
        $this->assertStringContainsString(
            'validate_buy_vps(',
            self::$source,
            'api_validate_buy_vps should call validate_buy_vps()'
        );
    }

    /**
     * Verify api_validate_buy_vps returns status 'ok' or 'error'.
     *
     * @return void
     */
    public function testApiValidateBuyVpsReturnsStatusFields(): void
    {
        $this->assertStringContainsString("'status'] = 'ok'", self::$source);
        $this->assertStringContainsString("'status'] = 'error'", self::$source);
    }

    /**
     * Verify api_buy_vps calls place_buy_vps on success.
     *
     * @return void
     */
    public function testApiBuyVpsCallsPlaceBuyVps(): void
    {
        $this->assertStringContainsString(
            'place_buy_vps(',
            self::$source,
            'api_buy_vps should call place_buy_vps() on success'
        );
    }

    /**
     * Verify all three API functions call function_requirements.
     *
     * @return void
     */
    public function testAllApiFunctionsCallFunctionRequirements(): void
    {
        preg_match_all(
            '/function_requirements\(\s*[\'"](\w+)[\'"]\s*\)/',
            self::$source,
            $matches
        );

        $required = $matches[1];
        $this->assertContains('validate_buy_vps', $required);
        $this->assertContains('place_buy_vps', $required);
    }

    /**
     * Verify all three API functions call get_custid.
     *
     * @return void
     */
    public function testAllApiFunctionsCallGetCustid(): void
    {
        preg_match_all('/get_custid\(/', self::$source, $matches);
        // Should have 3 calls - one per function
        $this->assertGreaterThanOrEqual(3, count($matches[0]));
    }

    /**
     * Verify the return arrays contain the expected keys.
     *
     * @return void
     */
    public function testReturnArraysContainExpectedKeys(): void
    {
        // All buy functions should return arrays with 'status', 'status_text', 'invoices', 'cost'
        $this->assertStringContainsString("'invoices']", self::$source);
        $this->assertStringContainsString("'cost']", self::$source);
        $this->assertStringContainsString("'status']", self::$source);
        $this->assertStringContainsString("'status_text']", self::$source);
    }

    // ------------------------------------------------------------------
    //  Functional tests with stubbed globals
    // ------------------------------------------------------------------

    /**
     * Verify api_validate_buy_vps returns 'ok' status when validation passes.
     *
     * @return void
     */
    public function testApiValidateBuyVpsReturnsOkOnSuccess(): void
    {
        $result = api_validate_buy_vps(
            'centos-7-x86_64.tar.gz', 1, 'kvm', 'none',
            1, 1, 'centos7', 'test.example.com', '', 'testpass123'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertSame('ok', $result['status']);
        $this->assertArrayHasKey('status_text', $result);
    }

    /**
     * Verify api_validate_buy_vps does not include 'continue' or 'errors' keys.
     *
     * @return void
     */
    public function testApiValidateBuyVpsStripsInternalKeys(): void
    {
        $result = api_validate_buy_vps(
            'centos-7-x86_64.tar.gz', 1, 'kvm', 'none',
            1, 1, 'centos7', 'test.example.com', '', 'testpass123'
        );

        $this->assertArrayNotHasKey('continue', $result);
        $this->assertArrayNotHasKey('errors', $result);
    }

    /**
     * Verify api_buy_vps returns expected keys on success.
     *
     * @return void
     */
    public function testApiBuyVpsReturnsExpectedKeysOnSuccess(): void
    {
        $result = api_buy_vps(
            'centos-7-x86_64.tar.gz', 1, 'kvm', 'none',
            1, 1, 'centos7', 'test.example.com', '', 'testpass123'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('status_text', $result);
        $this->assertArrayHasKey('invoices', $result);
        $this->assertArrayHasKey('cost', $result);
        $this->assertSame('ok', $result['status']);
    }

    /**
     * Verify api_buy_vps returns invoices as comma-separated string.
     *
     * @return void
     */
    public function testApiBuyVpsReturnsInvoicesAsString(): void
    {
        $result = api_buy_vps(
            'centos-7-x86_64.tar.gz', 1, 'kvm', 'none',
            1, 1, 'centos7', 'test.example.com', '', 'testpass123'
        );

        $this->assertIsString($result['invoices']);
    }

    /**
     * Verify api_buy_vps_admin returns expected keys on success.
     *
     * @return void
     */
    public function testApiBuyVpsAdminReturnsExpectedKeysOnSuccess(): void
    {
        $result = api_buy_vps_admin(
            'centos-7-x86_64.tar.gz', 1, 'kvm', 'none',
            1, 1, 'centos7', 'test.example.com', '', 'testpass123', 5
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('status_text', $result);
        $this->assertArrayHasKey('invoices', $result);
        $this->assertArrayHasKey('cost', $result);
        $this->assertSame('ok', $result['status']);
    }

    // ------------------------------------------------------------------
    //  Admin gate on api_buy_vps_admin()  (behavioural)
    // ------------------------------------------------------------------

    /**
     * The security property of api_buy_vps_admin(): a caller who is NOT admin
     * cannot choose which VPS master their order lands on — the requested
     * $server is discarded and 0 (auto-assign) is what reaches place_buy_vps().
     *
     * This is asserted behaviourally, by capturing the real place_buy_vps()
     * argument, rather than by grepping src/api.php for the shape of the
     * check: the source-text version of this test passed happily while
     * asserting nothing about behaviour, and broke the moment the check was
     * migrated from $GLOBALS['tf']->ima to \MyAdmin\App::ima().
     *
     * @return void
     */
    public function testApiBuyVpsAdminForcesServerToZeroForNonAdmin(): void
    {
        $this->tf()->ima = 'client';

        api_buy_vps_admin(
            'centos-7-x86_64.tar.gz',
            1,
            'kvm',
            'none',
            1,
            1,
            'centos7',
            'test.example.com',
            '',
            'testpass123',
            5
        );

        $calls = $GLOBALS['__vps_test_place_buy_vps_calls'];
        $this->assertCount(1, $calls);
        $this->assertSame(
            0,
            $calls[0]['server'],
            'A non-admin caller must not be able to pick the target VPS master'
        );
    }

    /**
     * The other half of the gate: an admin caller's requested server IS
     * honoured, cast to int.
     *
     * @return void
     */
    public function testApiBuyVpsAdminHonoursRequestedServerForAdmin(): void
    {
        $this->tf()->ima = 'admin';

        api_buy_vps_admin(
            'centos-7-x86_64.tar.gz',
            1,
            'kvm',
            'none',
            1,
            1,
            'centos7',
            'test.example.com',
            '',
            'testpass123',
            '5'
        );

        $calls = $GLOBALS['__vps_test_place_buy_vps_calls'];
        $this->assertCount(1, $calls);
        $this->assertSame(
            5,
            $calls[0]['server'],
            'An admin caller\'s server choice must be passed through, cast to int'
        );
    }

    /**
     * Verify api_buy_vps_admin with default server parameter.
     *
     * @return void
     */
    public function testApiBuyVpsAdminDefaultServerParam(): void
    {
        $result = api_buy_vps_admin(
            'centos-7-x86_64.tar.gz', 1, 'kvm', 'none',
            1, 1, 'centos7', 'test.example.com', '', 'testpass123'
        );

        $this->assertIsArray($result);
        $this->assertSame('ok', $result['status']);
    }

    // ------------------------------------------------------------------
    //  File-level checks
    // ------------------------------------------------------------------

    /**
     * Verify api.php starts with a PHP open tag.
     *
     * @return void
     */
    public function testApiFileStartsWithPhpTag(): void
    {
        $this->assertStringStartsWith('<?php', self::$source);
    }

    /**
     * Verify api.php has a file-level docblock.
     *
     * @return void
     */
    public function testApiFileHasDocblock(): void
    {
        $this->assertStringContainsString('/**', self::$source);
        $this->assertStringContainsString('@author', self::$source);
        $this->assertStringContainsString('@package', self::$source);
    }

    /**
     * Verify each function in api.php has a docblock.
     *
     * @return void
     */
    public function testEachFunctionHasDocblock(): void
    {
        $functions = ['api_validate_buy_vps', 'api_buy_vps', 'api_buy_vps_admin'];

        foreach ($functions as $func) {
            $pattern = '/\/\*\*[^*]*\*+([^\/*][^*]*\*+)*\/\s*function\s+' . preg_quote($func, '/') . '/';
            $this->assertMatchesRegularExpression(
                $pattern,
                self::$source,
                "Function {$func}() should have a docblock"
            );
        }
    }
}
