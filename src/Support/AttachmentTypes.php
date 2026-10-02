<?php

namespace Padmission\Tickets\Support;

use Symfony\Component\Mime\MimeTypes;

class AttachmentTypes
{
    /**
     * @return list<string>
     */
    public static function allowed(): array
    {
        return array_values(array_map('strtolower', (array) config('padmission-tickets.attachments.allowed_mime_types', [])));
    }

    /*
     * For a file picker's accept attribute: each type with its usual
     * extension, since some pickers match a file by its extension alone.
     */
    public static function accept(): string
    {
        $mimeTypes = MimeTypes::getDefault();

        return collect(static::allowed())
            ->flatMap(fn (string $type): array => array_filter([$type, ($extension = $mimeTypes->getExtensions($type)[0] ?? null) === null ? null : '.'.$extension]))
            ->unique()
            ->implode(',');
    }

    public static function isAllowed(?string $mimeType): bool
    {
        return $mimeType !== null && in_array(strtolower($mimeType), static::allowed(), true);
    }

    /*
     * A presigned upload does not sign the type the file is sent with, so a
     * download names the type it is served as. Only an allowed image, video
     * or PDF opens in the browser; an allowed document is served as its type
     * but downloaded, and anything else is a download of unknown bytes, so
     * nothing a browser would run opens in the app's origin.
     *
     * @return array<string, string>
     */
    public static function downloadOptions(?string $mimeType): array
    {
        if (! static::isAllowed($mimeType)) {
            return [
                'ResponseContentType' => 'application/octet-stream',
                'ResponseContentDisposition' => 'attachment',
            ];
        }

        $mimeType = strtolower((string) $mimeType);

        if (static::opensInBrowser($mimeType)) {
            return ['ResponseContentType' => $mimeType];
        }

        return [
            'ResponseContentType' => $mimeType,
            'ResponseContentDisposition' => 'attachment',
        ];
    }

    protected static function opensInBrowser(string $mimeType): bool
    {
        return (str_starts_with($mimeType, 'image/') && $mimeType !== 'image/svg+xml')
            || str_starts_with($mimeType, 'video/')
            || $mimeType === 'application/pdf';
    }
}
