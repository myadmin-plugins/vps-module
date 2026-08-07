<?php
/**
 * Test double for the core \MyAdmin\App static service locator.
 *
 * WHY THIS EXISTS
 * ---------------
 * src/api.php and src/Plugin.php call \MyAdmin\App::ima(), ::session(),
 * ::history() and ::accounts(). \MyAdmin\App lives in the MyAdmin core
 * (include/App.php in interserver/my), which is NOT a Composer package, so this
 * standalone module cannot require it. Without a double, every test that
 * EXECUTES those code paths dies with `Class "MyAdmin\App" not found`.
 *
 * The double mirrors the real class's proxy semantics for the four accessors
 * this module actually uses: each one reads off a single injected tf-like
 * object, exactly as core's App does (App::history() === App::tf()->history,
 * App::ima() === (string)(App::tf()->ima ?? ''), etc.). Only App::session()
 * and App::accounts() differ: core resolves those from its PSR-11 container
 * while this double takes them off the same tf stub — behaviourally identical
 * from the caller's point of view, and it keeps the whole fake to one seam.
 *
 * COLLISION SAFETY
 * ----------------
 * This file is require_once'd from tests/bootstrap.php ONLY when the real
 * \MyAdmin\App is not already autoloadable (i.e. when the suite runs
 * standalone, not inside a core checkout). Declaring a production symbol from
 * the test side is exactly the kind of thing that fatals with "cannot
 * redeclare" once the whole suite runs in one process, so the guard lives at
 * the single require site and this file is never included twice.
 *
 * @package Detain\MyAdminVps\Tests
 */

namespace MyAdmin;

final class App
{
    /**
     * The injected tf-like stub every accessor proxies through.
     *
     * @var object|null
     */
    private static $tf = null;

    /**
     * Bind the tf-like stub. Mirrors the role of core's
     * App::setContainer(TestContainerBuilder::make()->withTf($stub)->build()).
     *
     * @param object $tf
     */
    public static function setTf($tf): void
    {
        self::$tf = $tf;
    }

    /**
     * Drop the binding. Mirrors core's App::resetContainer().
     */
    public static function resetContainer(): void
    {
        self::$tf = null;
    }

    /**
     * @return object
     */
    public static function tf()
    {
        if (self::$tf === null) {
            throw new \RuntimeException('MyAdmin\App test double not initialized. Call App::setTf() in tests/bootstrap.php.');
        }
        return self::$tf;
    }

    /**
     * @return object
     */
    public static function session()
    {
        return self::tf()->session;
    }

    /**
     * @return object
     */
    public static function accounts()
    {
        return self::tf()->accounts;
    }

    /**
     * @return object
     */
    public static function history()
    {
        return self::tf()->history;
    }

    /**
     * Request user role: 'admin' | 'client' | ''.
     */
    public static function ima(): string
    {
        return (string) (self::tf()->ima ?? '');
    }

    public static function isAdmin(): bool
    {
        return self::ima() === 'admin';
    }

    /**
     * The global function_requirements() shipped by detain/myadmin-plugin-installer
     * delegates here, so the double has to answer it.
     *
     * @param string $function
     */
    public static function functionRequirements($function): bool
    {
        return (bool) self::tf()->function_requirements($function);
    }
}
