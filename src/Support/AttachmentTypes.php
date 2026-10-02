<?php

namespace Padmission\Tickets\Support;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
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

    /*
     * The type a file claims is only the browser's say, and a download keeps
     * the file's name, so the name must be an allowed type too: Payroll.exe
     * sent as text/plain would otherwise download as a program.
     */
    public static function nameIsAllowed(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return $extension !== '' && array_intersect(MimeTypes::getDefault()->getMimeTypes($extension), static::allowed()) !== [];
    }

    public static function usualExtension(string $mimeType): ?string
    {
        return MimeTypes::getDefault()->getExtensions(strtolower($mimeType))[0] ?? null;
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
    public static function downloadOptions(?string $mimeType, ?string $filename = null): array
    {
        if (! static::isAllowed($mimeType)) {
            return [
                'ResponseContentType' => 'application/octet-stream',
                'ResponseContentDisposition' => static::attachmentDisposition($filename),
            ];
        }

        $mimeType = strtolower((string) $mimeType);

        if (static::opensInBrowser($mimeType)) {
            return ['ResponseContentType' => $mimeType];
        }

        return [
            'ResponseContentType' => $mimeType,
            'ResponseContentDisposition' => static::attachmentDisposition($filename),
        ];
    }

    // Named by the file's own name, not the stored key it would otherwise be saved as.
    protected static function attachmentDisposition(?string $filename): string
    {
        if (blank($filename)) {
            return 'attachment';
        }

        $fallback = str_replace(['%', '/', '\\'], '_', Str::ascii($filename)) ?: 'file';

        return HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename, $fallback);
    }

    protected static function opensInBrowser(string $mimeType): bool
    {
        return (str_starts_with($mimeType, 'image/') && $mimeType !== 'image/svg+xml')
            || str_starts_with($mimeType, 'video/')
            || $mimeType === 'application/pdf';
    }
}
