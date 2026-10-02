<?php

namespace Tests\Unit;

use App\Actions\Import\LegacySchema;
use App\Actions\Import\SqlDump;
use RuntimeException;
use Tests\Support\LegacyFixture;
use Tests\TestCase;

class SqlDumpTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = tempnam(sys_get_temp_dir(), 'nestcrafters-synthetic-');
        LegacyFixture::write($this->path, LegacyFixture::rows());
    }

    protected function tearDown(): void
    {
        unlink($this->path);
        parent::tearDown();
    }

    public function test_mysql_quotes_control_characters_unicode_and_binary_payloads_round_trip(): void
    {
        $reader = new SqlDump($this->path);
        $expected = LegacyFixture::rows();
        // The second fixture has a random hash; compare independent known non-password literals.
        $this->assertSame($expected['ActivityLogs'][0]['action'], $reader->rows['ActivityLogs']['["1"]']['action']);
        $this->assertSame($expected['Job_Media'][0]['file_data'], $reader->rows['Job_Media']['["5"]']['file_data']);
        $this->assertSame($expected['Jobs'][0]['job_description'], $reader->rows['Jobs']['["4"]']['job_description']);
        $this->assertNull($reader->rows['Users']['["3"]']['email']);
        $this->assertSame('', $reader->rows['Users']['["3"]']['phone2']);
        $this->assertSame('0.00', LegacySchema::canonical('Jobs', 'deposit_amount', $reader->rows['Jobs']['["4"]']['deposit_amount']));
    }

    public function test_unreviewed_sql_cannot_be_executed(): void
    {
        file_put_contents($this->path, "DROP DATABASE unrelated;\n", FILE_APPEND);
        $this->expectException(RuntimeException::class);
        new SqlDump($this->path);
    }

    public function test_unexpected_schema_is_rejected(): void
    {
        $sql = file_get_contents($this->path);
        file_put_contents($this->path, str_replace('`company_name` varchar(255)', '`company_name` varchar(254)', $sql));
        $this->expectException(RuntimeException::class);
        new SqlDump($this->path);
    }

    public function test_truncated_export_is_rejected(): void
    {
        file_put_contents($this->path, substr(file_get_contents($this->path), 0, -12));
        $this->expectException(RuntimeException::class);
        new SqlDump($this->path);
    }
}
