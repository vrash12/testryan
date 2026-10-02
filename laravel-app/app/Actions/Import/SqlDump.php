<?php

namespace App\Actions\Import;

use RuntimeException;

final class SqlDump
{
    public array $rows = [];

    public array $statements = [];

    public string $sha256;

    public function __construct(string $path)
    {
        if (! is_file($path) || filesize($path) > 64 * 1024 * 1024) {
            throw new RuntimeException('Dump is missing or exceeds the supported 64 MiB limit.');
        }
        $bytes = file_get_contents($path);
        $this->sha256 = hash('sha256', $bytes);
        $this->rows = array_fill_keys(array_keys(LegacySchema::KEYS), []);
        $definitions = [];
        $drops = [];
        $toggles = [];
        foreach ($this->split($bytes) as $statement) {
            if (preg_match('/^SET FOREIGN_KEY_CHECKS=([01])$/', $statement, $match)) {
                $toggles[] = (int) $match[1];
            } elseif (preg_match('/^DROP TABLE IF EXISTS `([A-Za-z_]+)`$/', $statement, $match)) {
                $this->knownTable($match[1]);
                $drops[] = $match[1]; // Recognized, but never executed.
            } elseif (preg_match('/^CREATE TABLE `([A-Za-z_]+)` /', $statement, $match)) {
                $table = $match[1];
                $this->knownTable($table);
                if (isset($definitions[$table]) || trim($statement) !== rtrim(trim(LegacySchema::definitions()[$table]), ';')) {
                    throw new RuntimeException('Dump schema differs from the reviewed legacy definitions.');
                }
                $definitions[$table] = true;
            } elseif (preg_match('/^INSERT INTO `([A-Za-z_]+)` (?:\(([^)]+)\) )?VALUES \((.*)\)$/s', $statement, $match)) {
                $table = $match[1];
                $this->knownTable($table);
                $columns = $match[2] === '' ? array_keys(LegacySchema::columns($table)) : array_map(fn ($column) => trim($column, " `\t\r\n"), explode(',', $match[2]));
                if ($columns !== array_keys(LegacySchema::columns($table))) {
                    throw new RuntimeException('Dump INSERT columns differ from the reviewed schema.');
                }
                $values = $this->values($match[3]);
                if (count($columns) !== count($values)) {
                    throw new RuntimeException('Dump INSERT has an invalid column count.');
                }
                $row = array_combine($columns, $values);
                $key = LegacySchema::rowKey($table, $row);
                if (isset($this->rows[$table][$key])) {
                    throw new RuntimeException('Dump contains a duplicate primary key.');
                }
                $this->rows[$table][$key] = $row;
                // Only validated literal INSERTs are available to the isolated native restore.
                $this->statements[] = [$table, $key, $statement];
            } else {
                throw new RuntimeException('Unsupported dump statement. No SQL was executed.');
            }
        }
        $expected = array_keys(LegacySchema::KEYS);
        sort($expected);
        $actual = array_keys($definitions);
        sort($actual);
        sort($drops);
        if ($expected !== $actual || $expected !== $drops || $toggles !== [0, 1]) {
            throw new RuntimeException('Dump is incomplete or has unexpected control statements.');
        }
    }

    private function knownTable(string $table): void
    {
        if (! isset(LegacySchema::KEYS[$table])) {
            throw new RuntimeException('Unrecognized dump table.');
        }
    }

    private function split(string $bytes): array
    {
        $statements = [];
        $start = 0;
        $quoted = false;
        $length = strlen($bytes);
        for ($i = 0; $i < $length; $i++) {
            if ($quoted && $bytes[$i] === '\\') {
                $i++;
            } elseif ($bytes[$i] === "'") {
                if ($quoted && ($bytes[$i + 1] ?? '') === "'") {
                    $i++;
                } else {
                    $quoted = ! $quoted;
                }
            } elseif (! $quoted && $bytes[$i] === ';') {
                $statement = trim(substr($bytes, $start, $i - $start));
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $start = $i + 1;
            }
        }
        if ($quoted || trim(substr($bytes, $start)) !== '') {
            throw new RuntimeException('Dump has an unterminated statement.');
        }

        return $statements;
    }

    private function values(string $input): array
    {
        $values = [];
        $length = strlen($input);
        $i = 0;
        while ($i < $length) {
            while ($i < $length && ctype_space($input[$i])) {
                $i++;
            }
            if (($input[$i] ?? '') === "'") {
                $i++;
                $value = '';
                $closed = false;
                while ($i < $length) {
                    $byte = $input[$i++];
                    if ($byte === '\\') {
                        if ($i >= $length) {
                            throw new RuntimeException('Incomplete SQL escape.');
                        }
                        $escaped = $input[$i++];
                        $value .= match ($escaped) {
                            '0' => "\0", 'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'Z' => "\x1a",
                            '%', '_' => '\\'.$escaped,
                            default => $escaped,
                        };
                    } elseif ($byte === "'") {
                        if (($input[$i] ?? '') === "'") {
                            $value .= "'";
                            $i++;
                        } else {
                            $closed = true;
                            break;
                        }
                    } else {
                        $value .= $byte;
                    }
                }
                if (! $closed) {
                    throw new RuntimeException('Unterminated SQL string.');
                }
            } else {
                $start = $i;
                while ($i < $length && $input[$i] !== ',') {
                    $i++;
                }
                $literal = trim(substr($input, $start, $i - $start));
                if ($literal === 'NULL') {
                    $value = null;
                } elseif (preg_match('/^-?\d+(?:\.\d+)?$/', $literal)) {
                    $value = $literal;
                } else {
                    throw new RuntimeException('Only strings, numbers and NULL are supported in dump rows.');
                }
            }
            $values[] = $value;
            while ($i < $length && ctype_space($input[$i])) {
                $i++;
            }
            if ($i < $length && $input[$i++] !== ',') {
                throw new RuntimeException('Invalid SQL literal separator.');
            }
            if ($i === $length && str_ends_with(rtrim($input), ',')) {
                throw new RuntimeException('Trailing SQL literal separator.');
            }
        }

        return $values;
    }
}
