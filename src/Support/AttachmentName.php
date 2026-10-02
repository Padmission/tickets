<?php

namespace Padmission\Tickets\Support;

/*
 * An attachment's name comes from the uploader and becomes the last part of
 * its storage key, so only its last part is kept: a name with folders or
 * dots in it must not reach another file on the disk.
 */
class AttachmentName
{
    /*
     * The name ends a stored path of at most 255 characters, so a long one is
     * shortened to its room. Shortening never changes the extension, which is
     * what decides whether the file may be attached: it keeps the extension
     * whole, or, when even that leaves no room, keeps none.
     */
    public static function safe(string $name, int $room = 255): string
    {
        // Control characters, and invisible formatting such as a right-to-left override that hides an extension.
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F\p{Cf}]/u', '', $name));
        $name = trim(basename(str_replace('\\', '/', $name)));
        $name = in_array($name, ['', '.', '..'], true) ? 'file' : $name;

        if (mb_strlen($name) <= $room) {
            return $name;
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);

        if ($extension === '') {
            return mb_substr($name, 0, max(1, $room));
        }

        if (mb_strlen($extension) + 2 > $room) {
            return 'file';
        }

        return mb_substr($name, 0, $room - mb_strlen($extension) - 1).'.'.$extension;
    }
}
