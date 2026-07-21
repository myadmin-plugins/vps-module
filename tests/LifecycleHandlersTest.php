<?php
/**
 * Behavioral unit tests for the VPS service-lifecycle handlers registered by
 * Detain\MyAdminVps\Plugin::loadProcessing().
 *
 * This is the PE-U1 template: unlike the reflection/source-pattern coverage in
 * PluginTest, these tests EXECUTE the enable/terminate closures against a
 * FakeDb that implements the shared MyAdmin\App\Contracts\DatabaseInterface
 * (from detain/myadmin-contracts), capturing the exact SQL, App::history()
 * calls, and reverse_dns() side effects. No live database, dispatcher, or mail
 * is touched — the closures reach only the injected fakes + the no-op globals
 * defined in tests/bootstrap.php.
 *
 * The closures are captured by driving loadProcessing() with a fake
 * ServiceHandler that records each fluent setEnable/…/setTerminate callback.
 *
 * setReactivate() is intentionally NOT executed here: after its DB/history work
 * it constructs a real TFSmarty and \MyAdmin\Mail and sends admin mail, with no
 * seam to stub — that money/mail-adjacent path stays reflection-only (PluginTest)
 * and is logged as a deferral in the core DEFERRED register.
 *
 * @package Detain\MyAdminVps\Tests
 */

namespace Detain\MyAdminVps\Tests;

use Detain\MyAdminVps\Plugin;
use Detain\MyAdminVps\Tests\Fakes\FakeDb;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\GenericEvent;

class LifecycleHandlersTest extends TestCase
{
    /** @var array<string,\Closure> Captured lifecycle callbacks keyed by phase. */
    private array $handlers = [];

    protected function setUp(): void
    {
        // The lifecycle closures call the real get_module_settings() (autoloaded
        // from detain/myadmin-plugin-installer), which reads $GLOBALS['modules']
        // directly — seed the vps module settings it returns.
        $GLOBALS['modules']['vps'] = [
            'PREFIX' => 'vps',
            'TABLE' => 'vps',
            'TBLNAME' => 'VPS',
            'TITLE_FIELD' => 'vps_hostname',
            'TITLE_FIELD2' => 'vps_ip',
        ];

        // Capture the closures Plugin::loadProcessing registers.
        $service = $this->makeCapturingServiceHandler();
        Plugin::loadProcessing(new GenericEvent($service));

        // Reset the captured-history + reverse_dns sinks between tests.
        if (isset($GLOBALS['__vps_test_tf'])) {
            $GLOBALS['__vps_test_tf']->history->calls = [];
        }
        $GLOBALS['__vps_test_reverse_dns_calls'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['vps_dbh'], $GLOBALS['__vps_test_reverse_dns_calls'], $GLOBALS['modules']);
        if (isset($GLOBALS['__vps_test_tf'])) {
            $GLOBALS['__vps_test_tf']->history->calls = [];
        }
    }

    // -- setEnable ------------------------------------------------------------

    /**
     * enable() flips the service to pending-setup and records the two queue
     * history entries that drive the initial install.
     */
    public function testEnableSetsPendingSetupAndQueuesInstall(): void
    {
        // get_module_db('vps') returns `clone $GLOBALS['vps_dbh']`.
        $db = new FakeDb();
        $GLOBALS['vps_dbh'] = $db;

        ($this->handlers['enable'])($this->fakeService([
            'vps_id' => 100,
            'vps_custid' => 42,
        ]));

        $this->assertSame(
            "update vps set vps_status='pending-setup' where vps_id='100'",
            $db->lastQuery()
        );

        $calls = $this->historyCalls();
        $this->assertSame(['vps', 'change_status', 'pending-setup', 100, 42], $calls[0]);
        $this->assertSame(['vpsqueue', 100, 'initial_install', '', 42], $calls[1]);
        $this->assertCount(2, $calls);
    }

    // -- setTerminate ---------------------------------------------------------

    /**
     * terminate() collects the DISTINCT IPs bound to the service, releases them
     * in the ips table, removes reverse DNS for each VALID ip, and queues the
     * destroy action.
     */
    public function testTerminateReleasesDistinctValidIpsAndQueuesDestroy(): void
    {
        // A duplicate valid IP (dedup) + an invalid string (validIp filter).
        $db = new FakeDb([
            ['ips_ip' => '192.0.2.10'],
            ['ips_ip' => '192.0.2.10'],
            ['ips_ip' => 'not-an-ip'],
        ]);
        $GLOBALS['vps_dbh'] = $db;

        ($this->handlers['terminate'])($this->fakeService([
            'vps_id' => 100,
            'vps_custid' => 42,
        ]));

        // The SELECT then the release UPDATE, both scoped to the service id.
        $queries = $db->queries();
        $this->assertSame(
            "select * from vps_ips where ips_vps='100'",
            $queries[0]
        );
        $this->assertSame(
            "update vps_ips set ips_main=0,ips_usable=1,ips_used=0,ips_vps=0 where ips_vps='100'",
            $queries[1]
        );

        // reverse_dns removed only for the DISTINCT, VALID ip (invalid filtered).
        $this->assertSame(
            [['192.0.2.10', '', 'remove_reverse']],
            $GLOBALS['__vps_test_reverse_dns_calls']
        );

        // terminate() records exactly one history entry: the destroy queue action.
        $calls = $this->historyCalls();
        $this->assertSame([['vpsqueue', 100, 'destroy', '', 42]], $calls);
    }

    // -- helpers --------------------------------------------------------------

    /** @return array<int,array> the captured App::history()->add() argument lists. */
    private function historyCalls(): array
    {
        return $GLOBALS['__vps_test_tf']->history->calls ?? [];
    }

    /** A fake ServiceHandler whose getServiceInfo() returns the seeded row. */
    private function fakeService(array $serviceInfo): object
    {
        return new class ($serviceInfo) {
            private array $info;
            public function __construct(array $info)
            {
                $this->info = $info;
            }
            public function getServiceInfo(): array
            {
                return $this->info;
            }
        };
    }

    /**
     * A fluent ServiceHandler double that records the enable/reactivate/disable/
     * terminate closures into $this->handlers so tests can invoke them directly.
     */
    private function makeCapturingServiceHandler(): object
    {
        $test = $this;
        return new class ($test) {
            private $test;
            public function __construct($test)
            {
                $this->test = $test;
            }
            public function setModule($m)
            {
                return $this;
            }
            public function setEnable($cb)
            {
                $this->test->captureHandler('enable', $cb);
                return $this;
            }
            public function setReactivate($cb)
            {
                $this->test->captureHandler('reactivate', $cb);
                return $this;
            }
            public function setDisable($cb)
            {
                $this->test->captureHandler('disable', $cb);
                return $this;
            }
            public function setTerminate($cb)
            {
                $this->test->captureHandler('terminate', $cb);
                return $this;
            }
            public function register()
            {
                return $this;
            }
        };
    }

    /** @internal used by the capturing ServiceHandler double. */
    public function captureHandler(string $phase, $cb): void
    {
        $this->handlers[$phase] = $cb;
    }
}
