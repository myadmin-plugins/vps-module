<?php
/**
 * FakeDb — a test double for the MyAdmin database handle, implementing the
 * shared MyAdmin\App\Contracts\DatabaseInterface published in the
 * detain/myadmin-contracts package.
 *
 * This is the PE-U1 template artifact: because the module now dev-depends on
 * detain/myadmin-contracts, module unit tests can write a real
 * `implements DatabaseInterface` fake (type-checked against the same contract
 * the core codebase uses) instead of an ad-hoc anonymous stub. Seed result
 * rows via the constructor; every query()/qr() is captured for assertions.
 *
 * NOTE on cloning: the production get_module_db() returns `clone $GLOBALS[
 * $module.'_dbh']`, so callers operate on a CLONE of the injected fake. The
 * captured-query sink is therefore an ArrayObject (an object property, shared
 * by handle across a shallow clone) so a test holding the ORIGINAL fake still
 * sees the queries the cloned handle recorded. Seed rows ($rows/$cursor) are
 * plain arrays, copied into the clone at clone time — correct, since each
 * clone iterates its own cursor.
 *
 * @package Detain\MyAdminVps\Tests\Fakes
 */

namespace Detain\MyAdminVps\Tests\Fakes;

use MyAdmin\App\Contracts\DatabaseInterface;

class FakeDb implements DatabaseInterface
{
    /** Current fetched row after next_record(). @var array */
    public array $Record = [];

    /** Every SQL string passed to query()/qr(), shared across clones. */
    public \ArrayObject $queries;

    /** Rows a SELECT will iterate over via next_record(). @var array<int,array> */
    private array $rows;

    /** Cursor into $rows. */
    private int $cursor = 0;

    /** @param array<int,array> $rows Result rows returned by next_record()/qr(). */
    public function __construct(array $rows = [])
    {
        $this->rows = array_values($rows);
        $this->queries = new \ArrayObject();
    }

    public function query($query, $line = '', $file = '')
    {
        $this->queries->append($query);
        $this->cursor = 0;
        return true;
    }

    public function next_record($type = MYSQLI_ASSOC)
    {
        if ($this->cursor < count($this->rows)) {
            $this->Record = $this->rows[$this->cursor];
            $this->cursor++;
            return true;
        }
        return false;
    }

    public function num_rows()
    {
        return count($this->rows);
    }

    public function real_escape($str)
    {
        return addslashes((string) $str);
    }

    public function getLastInsertId($table, $column)
    {
        return 0;
    }

    public function qr($query)
    {
        $this->queries->append($query);
        return $this->rows[0] ?? false;
    }

    public function f($field)
    {
        return $this->Record[$field] ?? null;
    }

    // ---- test helpers -------------------------------------------------------

    /** @return string[] All captured SQL, in call order. */
    public function queries(): array
    {
        return $this->queries->getArrayCopy();
    }

    /** The most recent SQL string, or '' if none. */
    public function lastQuery(): string
    {
        $all = $this->queries->getArrayCopy();
        return $all === [] ? '' : (string) end($all);
    }

    /** All captured SQL joined by newlines (handy for assertStringContainsString). */
    public function allQueries(): string
    {
        return implode("\n", $this->queries->getArrayCopy());
    }
}
