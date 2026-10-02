<?php

namespace App\Actions\Import;

use Brick\Math\BigDecimal;

final class LegacySchema
{
    private static ?array $definitions = null;

    private static array $columns = [];

    public const KEYS = [
        'Users' => ['user_id'], 'Services' => ['service_id'], 'Templates' => ['template_id'],
        'ContractorServices' => ['user_id', 'service_id'], 'Jobs' => ['job_id'],
        'Files' => ['file_id'], 'Notes' => ['note_id'], 'ActivityLogs' => ['log_id'],
        'Job_Services' => ['job_service_id'], 'Job_Notes' => ['note_id'], 'Job_Media' => ['media_id'],
    ];

    public const BLOBS = ['Users' => ['profile_photo'], 'Files' => ['file_data'], 'Job_Media' => ['file_data']];

    public const REFERENCES = [
        ['ContractorServices', 'user_id', 'Users', 'user_id'],
        ['ContractorServices', 'service_id', 'Services', 'service_id'],
        ['Jobs', 'contractor_id', 'Users', 'user_id'],
        ['Files', 'user_id', 'Users', 'user_id'],
        ['Notes', 'user_id', 'Users', 'user_id'], ['Notes', 'admin_id', 'Users', 'user_id'],
        ['Job_Services', 'job_id', 'Jobs', 'job_id'], ['Job_Services', 'service_id', 'Services', 'service_id'],
        ['Job_Notes', 'job_id', 'Jobs', 'job_id'], ['Job_Notes', 'created_by', 'Users', 'user_id'],
        ['Job_Media', 'job_id', 'Jobs', 'job_id'], ['Job_Media', 'uploaded_by', 'Users', 'user_id'],
        // ActivityLogs.user_id deliberately retains historical orphan IDs without a FK.
    ];

    public static function definitions(): array
    {
        return self::$definitions ??= require database_path('schema/legacy.php');
    }

    public static function columns(string $table): array
    {
        if (isset(self::$columns[$table])) {
            return self::$columns[$table];
        }
        preg_match_all('/^  `([^`]+)` ([^\r\n]+)/m', self::definitions()[$table], $matches, PREG_SET_ORDER);

        return self::$columns[$table] = array_column($matches, 2, 1);
    }

    public static function canonical(string $table, string $column, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $type = self::columns($table)[$column];
        if (preg_match('/^(?:tinyint|int)\b/', $type)) {
            return (string) (int) $value;
        }
        if (str_starts_with($type, 'decimal(')) {
            return (string) BigDecimal::of((string) $value)->toScale(2);
        }

        return (string) $value;
    }

    public static function rowKey(string $table, array $row): string
    {
        return json_encode(array_map(fn ($key) => self::canonical($table, $key, $row[$key]), self::KEYS[$table]), JSON_THROW_ON_ERROR);
    }

    public static function checksum(string $table, array $row): string
    {
        $hash = hash_init('sha256');
        foreach (self::columns($table) as $column => $type) {
            $value = self::canonical($table, $column, $row[$column]);
            hash_update($hash, $column.':'.($value === null ? 'NULL;' : strlen($value).':'.$value.';'));
        }

        return hash_final($hash);
    }

    public static function nextId(string $table): ?int
    {
        preg_match('/AUTO_INCREMENT=(\d+)/', self::definitions()[$table], $match);

        return isset($match[1]) ? (int) $match[1] : null;
    }
}
