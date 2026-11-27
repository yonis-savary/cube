<?php

namespace Cube\Data\Database\Migration\Plans;

/**
 * The body of a SQLite `CREATE TABLE` statement, as SQLite keeps it in `sqlite_master`, split into
 * its top-level definitions (columns, then table constraints) so that a rebuild rewrites the ones
 * it changes and copies every other one verbatim : collations, checks, generated columns and
 * table options survive without being understood.
 */
class SQLiteTableDefinition
{
    public const TABLE_CONSTRAINT_KEYWORDS = ['CONSTRAINT', 'PRIMARY', 'UNIQUE', 'CHECK', 'FOREIGN'];

    /** What opens a string, a quoted identifier or a comment => what closes it */
    public const ENCLOSINGS = ['--' => "\n", '/*' => '*/', "'" => "'", '"' => '"', '`' => '`', '[' => ']'];

    /** Doubled inside the quotes they open, these stand for themselves */
    public const SELF_ESCAPING_QUOTES = ["'", '"', '`'];

    /**
     * @param string[] $definitions
     * @param string   $options     what follows the closing parenthesis (`WITHOUT ROWID`, `STRICT`)
     */
    public function __construct(
        public array $definitions,
        public string $options = ''
    ) {}

    public static function fromSQL(string $sql): self
    {
        $definitions = [];
        $depth = 0;
        $start = null;
        $length = strlen($sql);

        for ($i = 0; $i < $length; ++$i) {
            $skipped = self::skipQuotedOrComment($sql, $i);
            if ($skipped !== $i) {
                $i = $skipped - 1;
                continue;
            }

            $char = $sql[$i];

            if ('(' === $char && 0 === $depth++) {
                $start = $i + 1;
                continue;
            }

            if (',' === $char && 1 === $depth) {
                $definitions[] = trim(substr($sql, $start, $i - $start));
                $start = $i + 1;
                continue;
            }

            if (')' === $char && 0 === --$depth) {
                $definitions[] = trim(substr($sql, $start, $i - $start));
                return new self($definitions, trim(substr($sql, $i + 1)));
            }
        }

        throw new \InvalidArgumentException("Could not find the definitions of [{$sql}], expected a CREATE TABLE statement");
    }

    /**
     * @return int the offset after the string, quoted identifier or comment starting at `$offset`,
     *             `$offset` itself when none starts there
     */
    protected static function skipQuotedOrComment(string $sql, int $offset): int
    {
        foreach (self::ENCLOSINGS as $opening => $closing) {
            if (!str_starts_with(substr($sql, $offset, strlen($opening)), $opening))
                continue;
            $from = $offset + strlen($opening);
            while (true) {
                $end = strpos($sql, $closing, $from);
                if (false === $end)
                    return strlen($sql);
                $from = $end + strlen($closing);

                if (!in_array($closing, self::SELF_ESCAPING_QUOTES) || ($sql[$from] ?? null) !== $closing)
                    return $from;
                ++$from;
            }
        }

        return $offset;
    }

    /**
     * Identifiers and keywords of a definition, unquoted, comments and string literals left out.
     *
     * @return array<array{string,bool}> every token with `true` when it was quoted
     */
    public static function tokenize(string $definition): array
    {
        $tokens = [];
        $length = strlen($definition);

        for ($i = 0; $i < $length; ++$i) {
            $skipped = self::skipQuotedOrComment($definition, $i);

            if ($skipped !== $i) {
                $opening = $definition[$i];
                if (in_array($opening, ['"', '`', '[']))
                    $tokens[] = [str_replace($opening.$opening, $opening, substr($definition, $i + 1, $skipped - $i - 2)), true];

                $i = $skipped - 1;
                continue;
            }

            if (!preg_match('/\G[a-z_][a-z0-9_$]*/i', $definition, $match, 0, $i))
                continue;
            $tokens[] = [$match[0], false];
            $i += strlen($match[0]) - 1;
        }

        return $tokens;
    }

    public static function isTableConstraint(string $definition): bool
    {
        [$first, $quoted] = self::tokenize($definition)[0] ?? ['', true];

        return !$quoted && in_array(strtoupper($first), self::TABLE_CONSTRAINT_KEYWORDS);
    }

    public static function getColumnName(string $definition): ?string
    {
        if (self::isTableConstraint($definition))
            return null;
        return self::tokenize($definition)[0][0] ?? null;
    }

    public static function hasKeyword(string $definition, string $keyword): bool
    {
        foreach (self::tokenize($definition) as [$token, $quoted]) {
            if (!$quoted && 0 === strcasecmp($token, $keyword))
                return true;
        }

        return false;
    }

    public static function mentions(string $definition, string $identifier): bool
    {
        foreach (self::tokenize($definition) as [$token]) {
            if (0 === strcasecmp($token, $identifier))
                return true;
        }

        return false;
    }

    protected function findColumn(string $column): ?int
    {
        foreach ($this->definitions as $index => $definition) {
            if (0 === strcasecmp(self::getColumnName($definition) ?? '', $column))
                return $index;
        }

        return null;
    }

    public function getColumn(string $column): ?string
    {
        $index = $this->findColumn($column);

        return null === $index ? null : $this->definitions[$index];
    }

    public function replaceColumn(string $column, string $definition): self
    {
        $index = $this->findColumn($column)
            ?? throw new \InvalidArgumentException("Could not find the [{$column}] column definition");

        $this->definitions[$index] = $definition;

        return $this;
    }

    /** Columns come before table constraints in SQLite's grammar : the column goes after the last one */
    public function addColumn(string $definition): self
    {
        $position = 0;
        foreach ($this->definitions as $index => $existing) {
            if (!self::isTableConstraint($existing))
                $position = $index + 1;
        }

        array_splice($this->definitions, $position, 0, [$definition]);

        return $this;
    }

    public function addConstraint(string $definition): self
    {
        $this->definitions[] = $definition;

        return $this;
    }

    /** Removes the column and every table constraint naming it */
    public function removeColumn(string $column): self
    {
        $this->definitions = array_values(array_filter(
            $this->definitions,
            fn ($definition) => self::isTableConstraint($definition)
                ? !self::mentions($definition, $column)
                : 0 !== strcasecmp(self::getColumnName($definition) ?? '', $column)
        ));

        return $this;
    }

    public function removeNamedConstraint(string $name): bool
    {
        foreach ($this->definitions as $index => $definition) {
            $tokens = self::tokenize($definition);

            if (!self::isTableConstraint($definition) || 0 !== strcasecmp($tokens[0][0], 'CONSTRAINT'))
                continue;
            if (0 !== strcasecmp($tokens[1][0] ?? '', $name))
                continue;
            array_splice($this->definitions, $index, 1);
            return true;
        }

        return false;
    }

    /** @param string $table already quoted */
    public function toSQL(string $table): string
    {
        // A definition can end with a `--` comment, which would swallow the comma written after it
        $definitions = array_map(fn ($definition) => str_contains($definition, '--') ? "{$definition}\n" : $definition, $this->definitions);

        return trim("CREATE TABLE {$table} (\n    ".join(",\n    ", $definitions)."\n) {$this->options}");
    }
}
