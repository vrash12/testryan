<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final class JobContent
{
    public static function render(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return (new HtmlSanitizer(
            (new HtmlSanitizerConfig)->allowSafeElements()
        ))->sanitize($html);
    }
}
