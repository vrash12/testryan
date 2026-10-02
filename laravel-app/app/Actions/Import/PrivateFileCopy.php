<?php

namespace App\Actions\Import;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PrivateFileCopy
{
    private array $created = [];

    public function copy(string $dumpHash, string $table, array $row): array
    {
        $column = LegacySchema::BLOBS[$table][0];
        $bytes = $row[$column];
        $id = (int) $row[LegacySchema::KEYS[$table][0]];
        $sha256 = hash('sha256', $bytes);
        $path = 'imports/'.$dumpHash.'/'.$table.'/'.$id.'/'.$sha256.'.bin';
        $disk = Storage::disk('private');
        if (! $disk->exists($path)) {
            // Track before put so a partially written new file is also cleaned up.
            $this->created[] = $path;
            $disk->put($path, $bytes);
        }
        $stored = $disk->get($path);
        if (strlen($stored) !== strlen($bytes) || hash('sha256', $stored) !== $sha256) {
            throw new RuntimeException('Private file verification failed. Existing content was not overwritten.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $legacyMime = $row['mime_type'] ?? null;

        return [
            'source_table' => $table, 'source_id' => $id, 'source_column' => $column,
            'disk' => 'private', 'path' => $path, 'byte_size' => strlen($bytes), 'sha256' => $sha256,
            'detected_mime_type' => $mime, 'legacy_mime_type' => $legacyMime,
            'original_name' => $row['file_name'] ?? null,
            'needs_review' => ! in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'], true)
                || ($legacyMime !== null && $legacyMime !== $mime),
        ];
    }

    public function verify(array $metadata): void
    {
        $bytes = Storage::disk($metadata['disk'])->get($metadata['path']);
        if (strlen($bytes) !== (int) $metadata['byte_size'] || hash('sha256', $bytes) !== $metadata['sha256']) {
            throw new RuntimeException('An imported private file is missing or changed.');
        }
    }

    public function rollback(): void
    {
        foreach ($this->created as $path) {
            try {
                if (! Storage::disk('private')->delete($path)) {
                    throw new RuntimeException('Private import file cleanup failed.');
                }
            } catch (Throwable $exception) {
                Log::error('Import rolled back; a private copy needs cleanup.', ['path' => $path, 'exception' => $exception]);
            }
        }
        $this->created = [];
    }

    public function commit(): void
    {
        $this->created = [];
    }
}
