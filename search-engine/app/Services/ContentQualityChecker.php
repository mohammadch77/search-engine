<?php

namespace App\Services;

class ContentQualityChecker
{
    /**
     * Common phrases found on "soft 404" pages — real HTTP 200 responses
     * whose content is actually a generic not-found/error page.
     */
    protected const SOFT_404_PHRASES = [
        'صفحه مورد نظر یافت نشد', 'صفحه یافت نشد', 'یافت نشد',
        'page not found', 'not found', '404 error', 'page does not exist',
        'this page doesn\'t exist', 'content not found',
    ];

    /**
     * @param  array{title: ?string, content_text: ?string}  $parsed
     */
    public function isSoft404(array $parsed): bool
    {
        $haystack = mb_strtolower(($parsed['title'] ?? '').' '.mb_substr($parsed['content_text'] ?? '', 0, 500));

        foreach (self::SOFT_404_PHRASES as $phrase) {
            if (mb_strpos($haystack, mb_strtolower($phrase)) !== false) {
                return true;
            }
        }

        return false;
    }
}
