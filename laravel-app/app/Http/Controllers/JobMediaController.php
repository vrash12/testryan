<?php

namespace App\Http\Controllers;

use App\Models\JobMedia;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class JobMediaController extends Controller
{
    public function preview(JobMedia $media)
    {
        Gate::authorize('view', $media);

        return $this->binary($media, true);
    }

    public function download(JobMedia $media)
    {
        Gate::authorize('view', $media);

        return $this->binary($media, false);
    }

    public function thumbnail(JobMedia $media)
    {
        Gate::authorize('view', $media);
        abort_unless(str_starts_with((string) $media->mime_type, 'image/'), 404);

        $media->load(['privateUpload', 'privateFile']);
        $metadata = $media->privateUpload ?: $media->privateFile;
        $disk = $metadata?->disk ?: 'private';
        $path = $metadata?->path;
        $bytes = $path && Storage::disk($disk)->exists($path)
            ? Storage::disk($disk)->get($path)
            : $media->file_data;
        abort_if($bytes === null, 404);
        $source = @imagecreatefromstring($bytes);
        abort_if($source === false, 404);
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, 120 / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $thumb = imagecreatetruecolor($targetWidth, $targetHeight);
        $white = imagecolorallocate($thumb, 255, 255, 255);
        imagefill($thumb, 0, 0, $white);
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        ob_start();
        imagejpeg($thumb, null, 80);
        $output = ob_get_clean();
        imagedestroy($source);
        imagedestroy($thumb);

        return response($output, 200, [
            'Content-Type' => 'image/jpeg',
            'Content-Disposition' => 'inline; filename="thumbnail.jpg"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function binary(JobMedia $media, bool $inline)
    {
        $media->load(['privateUpload', 'privateFile']);
        $metadata = $media->privateUpload ?: $media->privateFile;
        $path = $metadata?->path;
        $disk = $metadata?->disk ?: 'private';
        $mime = $metadata?->mime_type ?: $media->mime_type ?: 'application/octet-stream';
        $name = basename(str_replace('\\', '/', $media->file_name ?: $metadata?->original_name ?: 'job-media'));
        $headers = [
            'Content-Type' => $mime,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.addcslashes($name, '\\"').'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];
        if ($path && Storage::disk($disk)->exists($path)) {
            return response()->file(Storage::disk($disk)->path($path), $headers);
        }
        abort_if($media->file_data === null, 404);

        return response($media->file_data, 200, $headers);
    }
}
