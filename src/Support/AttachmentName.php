<?php

namespace Padmission\Tickets\Support;

/*
 * An attachment's name comes from the uploader and becomes the last part of
 * its storage key, so only its last part is kept: a name with folders or
 * dots in it must not reach another file on the disk.
 */
class AttachmentName
{
    public static function safe(string $name): string
    {
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name));
        $name = trim(basename(str_replace('\\', '/', $name)));

        return in_array($name, ['', '.', '..'], true) ? 'file' : $name;
    }
}
