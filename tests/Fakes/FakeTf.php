<?php
/**
 * FakeTf — the tf-like stub every \MyAdmin\App accessor proxies through in the
 * test suite.
 *
 * A single instance is built in tests/bootstrap.php and exposed as
 * $GLOBALS['__vps_test_tf'] so tests can both DRIVE it (set ->ima to 'admin' vs
 * 'client') and READ what production code did through it (->history->calls).
 *
 * Because it is process-global, every test that touches it MUST call reset()
 * in setUp() and tearDown(); reset() restores the exact state bootstrap left it
 * in, so no test can leak an 'admin' role or a stale history entry into the
 * next one.
 *
 * @package Detain\MyAdminVps\Tests\Fakes
 */

namespace Detain\MyAdminVps\Tests\Fakes;

class FakeTf
{
    /** Default request role. \MyAdmin\App::ima() reads this. @var string */
    public const DEFAULT_IMA = 'client';

    /** Default logged-in account. \MyAdmin\App::session()->account_id reads this. */
    public const DEFAULT_ACCOUNT_ID = 1;

    /** Request role: 'admin' | 'client' | ''. @var string */
    public string $ima = self::DEFAULT_IMA;

    /** Stands in for \MyAdmin\Session; exposes ->account_id. @var object */
    public $session;

    /** Records every ->add() call for behavioural assertions. @var object */
    public $history;

    /** Stands in for \MyAdmin\Accounts; exposes cross_reference(). @var object */
    public $accounts;

    public function __construct()
    {
        $this->session = new class {
            public $account_id = FakeTf::DEFAULT_ACCOUNT_ID;
        };
        $this->history = new class {
            /** Every add() call, captured for behavioral assertions. @var array<int,array> */
            public array $calls = [];
            public function add(...$args)
            {
                $this->calls[] = $args;
            }
        };
        $this->accounts = new class {
            public function cross_reference($id)
            {
                return $id;
            }
        };
    }

    /**
     * The real function_requirements() global (from detain/myadmin-plugin-installer)
     * delegates to \MyAdmin\App::functionRequirements(), which proxies here; no-op
     * it so lazy-load calls in executed code paths succeed.
     *
     * @param string $function
     */
    public function function_requirements($function)
    {
        return true;
    }

    /**
     * Restore the pristine bootstrap state. Call from both setUp() and
     * tearDown() of any test that mutates this stub.
     */
    public function reset(): void
    {
        $this->ima = self::DEFAULT_IMA;
        $this->session->account_id = self::DEFAULT_ACCOUNT_ID;
        $this->history->calls = [];
    }
}
