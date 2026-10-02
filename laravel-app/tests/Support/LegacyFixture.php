<?php

namespace Tests\Support;

use App\Actions\Import\LegacySchema;

final class LegacyFixture
{
    public static function rows(): array
    {
        $rows = array_fill_keys(array_keys(LegacySchema::KEYS), []);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jZioAAAAASUVORK5CYII=');
        $user = [
            'user_id' => 1, 'company_name' => 'Synthetic company', 'company_address' => 'Fixture address', 'company_phone' => null,
            'first_name' => 'Test', 'last_name' => 'Person', 'phone1' => null, 'phone1_type' => 'Mobile',
            'phone2' => '', 'phone2_type' => 'Mobile', 'email' => 'admin@example.test',
            'password_hash' => password_hash('fixture-known-password', PASSWORD_BCRYPT, ['cost' => 10]),
            'profile_photo' => null, 'is_admin' => 1, 'is_approved' => 1, 'approved_date' => '2025-01-02 12:00:00',
            'created_at' => '2025-01-01 12:00:00', 'priority' => 0,
        ];
        $rows['Users'] = [$user, array_replace($user, ['user_id' => 2, 'is_admin' => 0, 'is_approved' => 2, 'email' => 'contractor@example.test', 'approved_date' => null, 'profile_photo' => $png]), array_replace($user, ['user_id' => 3, 'is_admin' => 0, 'is_approved' => 0, 'email' => null, 'approved_date' => null])];
        $rows['Services'] = [['service_id' => 3, 'service_name' => 'Fixture service']];
        $rows['Templates'] = [['template_id' => 2, 'name' => 'Fixture template', 'type' => 'Job', 'html_content' => '<p>Original &amp; HTML</p>', 'created_at' => '2025-01-01 01:02:03', 'updated_at' => '2025-01-02 01:02:03']];
        $rows['ContractorServices'] = [['user_id' => 2, 'service_id' => 3]];
        $rows['Jobs'] = [[
            'job_id' => 4, 'entered_datetime' => '2025-01-01 12:00:00', 'due_date' => '2025-02-01',
            'job_name' => 'Fixture job', 'job_description' => "Original <script>kept in storage</script>; 'quote' \\ slash →\nnext line",
            'is_accepted' => 1, 'accepted_datetime' => '2025-01-03 10:00:00', 'terms' => null,
            'contractor_id' => 2, 'is_completed' => 1, 'completed_datetime' => '2025-01-04 10:00:00',
            'approved_price' => '1234.05', 'deposit_amount' => '0.00', 'who_supplies_materials' => 'Contractor',
        ]];
        $rows['Files'] = [[
            'file_id' => 2, 'user_id' => 2, 'unique_file_id' => str_repeat('a', 32), 'file_type' => 'Insurance',
            'file_name' => 'fixture.pdf', 'file_data' => "%PDF-1.4\n%%EOF\n", 'mime_type' => 'application/pdf',
            'upload_date' => '2025-01-02 12:00:00', 'expiry_date' => null, 'archived' => 0,
        ]];
        $rows['Notes'] = [['note_id' => 1, 'user_id' => 2, 'admin_id' => 1, 'note_text' => 'Private fixture note', 'created_at' => '2025-01-02 12:00:00']];
        $rows['ActivityLogs'] = [
            ['log_id' => 1, 'user_id' => 2, 'action' => "Fixture → 'quote'; slash \\ newline\nNUL\0control\x1a", 'timestamp' => '2025-01-02 12:00:00'],
            ['log_id' => 2, 'user_id' => 11, 'action' => 'Deleted fixture user association', 'timestamp' => '2025-01-02 12:00:00'],
        ];
        $rows['Job_Services'] = [['job_service_id' => 12, 'job_id' => 4, 'service_id' => 3]];
        $rows['Job_Notes'] = [['note_id' => 1, 'job_id' => 4, 'note_text' => "Shared fixture\nnote", 'created_datetime' => '2025-01-02 12:00:00', 'created_by' => 2]];
        $rows['Job_Media'] = [['media_id' => 5, 'job_id' => 4, 'file_name' => 'fixture.png', 'file_data' => $png, 'mime_type' => 'image/png', 'tag' => 'During', 'upload_datetime' => '2025-01-02 12:00:00', 'uploaded_by' => 2]];

        return $rows;
    }

    public static function write(string $path, array $rows): void
    {
        $sql = "SET FOREIGN_KEY_CHECKS=0;\n";
        foreach (LegacySchema::definitions() as $table => $ddl) {
            $sql .= 'DROP TABLE IF EXISTS `'.$table.'`;'."\n".$ddl."\n";
            foreach ($rows[$table] as $row) {
                $ordered = array_map(fn ($column) => $row[$column], array_keys(LegacySchema::columns($table)));
                $sql .= 'INSERT INTO `'.$table.'` VALUES ('.implode(',', array_map(self::literal(...), $ordered)).");\n";
            }
        }
        file_put_contents($path, $sql."SET FOREIGN_KEY_CHECKS=1;\n");
    }

    private static function literal(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return "'".strtr((string) $value, ['\\' => '\\\\', "\0" => '\\0', "\n" => '\\n', "\r" => '\\r', "'" => "\\'", '"' => '\\"', "\x1a" => '\\Z'])."'";
    }
}
