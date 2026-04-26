<?php
/**
 * Unit tests for the VPS API functions in src/api.php
 *
 * These tests validate function existence, parameter signatures,
 * and source-level patterns. The actual functions rely heavily on
 * global state (MyAdmin\App::tf()) and external function calls, so
 * we use static analysis / reflection rather than direct execution
 * for database-touching code paths.
 *
 * @package Detain\MyAdminVps\Tests
 */

namespace Detain\MyAdminVps\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionFunction;

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
     * The stubs for validate_buy_vps, place_buy_vps, and MyAdmin\App::tf()
     * are defined in bootstrap.php (global namespace).
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
    //  Parameter signatures
    // ------------------------------------------------------------------

    /**
     * Verify api_validate_buy_vps has exactly 10 required parameters.
     *
     * @return void
     */
    public function testApiValidateBuyVpsParameterCount(): void
    {
        $ref = new ReflectionFunction('api_validate_buy_vps');
        $this->assertSame(10, $ref->getNumberOfParameters());
        $this->assertSame(10, $ref->getNumberOfRequiredParameters());
    }

    /**
     * Verify api_validate_buy_vps parameter names match the expected order.
     *
     * @return void
     */
    public function testApiValidateBuyVpsParameterNames(): void
    {
        $ref = new ReflectionFunction('api_validate_buy_vps');
        $names = array_map(
            fn($p) => $p->getName(),
            $ref->getParameters()
        );

        $expected = [
            'os', 'slices', 'platform', 'controlpanel', 'period',
            'location', 'version', 'hostname', 'coupon', 'rootpass',
        ];

        $this->assertSame($expected, $names);
    }

    /**
     * Verify api_buy_vps has exactly 10 required parameters.
     *
     * @return void
     */
    public function testApiBuyVpsParameterCount(): void
    {
        $ref = new ReflectionFunction('api_buy_vps');
        $this->assertSame(10, $ref->getNumberOfParameters());
        $this->assertSame(10, $ref->getNumberOfRequiredParameters());
    }

    /**
     * Verify api_buy_vps parameter names match the expected order.
     *
     * @return void
     */
    public function testApiBuyVpsParameterNames(): void
    {
        $ref = new ReflectionFunction('api_buy_vps');
        $names = array_map(
            fn($p) => $p->getName(),
            $ref->getParameters()
        );

        $expected = [
            'os', 'slices', 'platform', 'controlpanel', 'period',
            'location', 'version', 'hostname', 'coupon', 'rootpass',
        ];

        $this->assertSame($expected, $names);
    }

    /**
     * Verify api_buy_vps_admin has 11 parameters with 10 required.
     *
     * @return void
     */
    public function testApiBuyVpsAdminParameterCount(): void
    {
        $ref = new ReflectionFunction('api_buy_vps_admin');
        $this->assertSame(11, $ref->getNumberOfParameters());
        $this->assertSame(10, $ref->getNumberOfRequiredParameters());
    }

    /**
     * Verify api_buy_vps_admin parameter names include the server param.
     *
     * @return void
     */
    public function testApiBuyVpsAdminParameterNames(): void
    {
        $ref = new ReflectionFunction('api_buy_vps_admin');
        $names = array_map(
            fn($p) => $p->getName(),
            $ref->getParameters()
        );

        $expected = [
            'os', 'slices', 'platform', 'controlpanel', 'period',
            'location', 'version', 'hostname', 'coupon', 'rootpass', 'server',
        ];

        $this->assertSame($expected, $names);
    }

    /**
     * Verify api_buy_vps_admin $server parameter defaults to 0.
     *
     * @return void
     */
    public function testApiBuyVpsAdminServerDefaultsToZero(): void
    {
        $ref = new ReflectionFunction('api_buy_vps_admin');
        $params = $ref->getParameters();
        $serverParam = $params[10];

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
     * Verify api_buy_vps_admin checks for admin role.
     *
     * @return void
     */
    public function testApiBuyVpsAdminChecksAdminRole(): void
    {
        $this->assertStringContainsString(
            "->ima != 'admin'",
            self::$source,
            'api_buy_vps_admin should check admin role'
        );
    }

    /**
     * Verify api_buy_vps_admin resets server to 0 for non-admins.
     *
     * @return void
     */
    public function testApiBuyVpsAdminResetsServerForNonAdmin(): void
    {
        // The source should have $server = 0 in the non-admin branch
        $this->assertStringContainsString(
            '$server = 0',
            self::$source,
            'Non-admin branch should set $server = 0'
        );

        // And cast to int for admin branch
        $this->assertStringContainsString(
            '$server = (int)$server',
            self::$source,
            'Admin branch should cast $server to int'
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
