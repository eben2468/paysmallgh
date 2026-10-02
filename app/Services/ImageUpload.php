<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Saves a customer-uploaded photo safely:
 *  - only real JPEG / PNG / WebP images (checked by content, not file name),
 *    up to a size and pixel limit;
 *  - camera metadata removed (EXIF/XMP — phone photos often carry the GPS
 *    location of where they were taken, i.e. the customer's home);
 *  - a random, unguessable file name.
 *
 * Pure PHP — works without the GD extension.
 */
final class ImageUpload
{
    private const MAX_PIXELS = 40_000_000; // ~40 megapixels

    /**
     * Store an uploaded file under public/uploads/{$dir}/. Returns the public
     * path ("uploads/reviews/…jpg") or null if it isn't an acceptable image.
     */
    public static function store(string $tmp, int $size, string $dir, string $prefix, int $maxBytes = 5 * 1024 * 1024): ?string
    {
        if ($tmp === '' || !is_uploaded_file($tmp) || $size <= 0 || $size > $maxBytes) {
            return null;
        }
        $info = @getimagesize($tmp);
        $ext = match ($info[2] ?? null) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            default => null,
        };
        if ($ext === null || ($info[0] ?? 0) * ($info[1] ?? 0) > self::MAX_PIXELS) {
            return null;
        }

        $bytes = (string) file_get_contents($tmp);
        $clean = match ($ext) {
            'jpg' => self::stripJpeg($bytes),
            'png' => self::stripPng($bytes),
            'webp' => self::stripWebp($bytes),
        };
        if ($clean === null) {
            return null; // damaged file — don't keep what we can't clean
        }

        $folder = BASE_PATH . '/public/uploads/' . $dir;
        if (!is_dir($folder) && !@mkdir($folder, 0775, true) && !is_dir($folder)) {
            return null;
        }
        $name = $prefix . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (file_put_contents($folder . '/' . $name, $clean) === false) {
            return null;
        }
        return 'uploads/' . $dir . '/' . $name;
    }

    /** Delete a stored upload by its public path (only inside public/uploads). */
    public static function delete(string $path): void
    {
        if (!preg_match('#^uploads/[a-z0-9_/-]+\.(jpg|png|webp)$#i', $path) || str_contains($path, '..')) {
            return;
        }
        $file = BASE_PATH . '/public/' . $path;
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * JPEG: drop APP1–APP15 (EXIF, XMP, maker notes…) and comment segments.
     * Keeps APP0 (JFIF) and everything the decoder needs. Null if malformed.
     */
    public static function stripJpeg(string $b): ?string
    {
        $len = strlen($b);
        if ($len < 4 || substr($b, 0, 2) !== "\xFF\xD8") {
            return null;
        }
        $out = "\xFF\xD8";
        $i = 2;
        while ($i + 4 <= $len) {
            if ($b[$i] !== "\xFF") {
                return null;
            }
            $marker = ord($b[$i + 1]);
            if ($marker === 0xFF) { // fill byte
                $i++;
                continue;
            }
            if ($marker === 0xDA) { // start of scan: the rest is image data
                return $out . substr($b, $i);
            }
            if ($marker === 0xD8 || ($marker >= 0xD0 && $marker <= 0xD7) || $marker === 0x01) {
                $out .= substr($b, $i, 2); // markers without a length
                $i += 2;
                continue;
            }
            $segLen = (ord($b[$i + 2]) << 8) | ord($b[$i + 3]);
            if ($segLen < 2 || $i + 2 + $segLen > $len) {
                return null;
            }
            $isMetadata = ($marker >= 0xE1 && $marker <= 0xEF) || $marker === 0xFE;
            if (!$isMetadata) {
                $out .= substr($b, $i, 2 + $segLen);
            }
            $i += 2 + $segLen;
        }
        return null; // no image data found
    }

    /** PNG: drop eXIf and text chunks (tEXt/zTXt/iTXt, which can hold XMP/EXIF). */
    public static function stripPng(string $b): ?string
    {
        $sig = "\x89PNG\r\n\x1a\n";
        if (strncmp($b, $sig, 8) !== 0) {
            return null;
        }
        $out = $sig;
        $i = 8;
        $len = strlen($b);
        while ($i + 12 <= $len) {
            $chunkLen = unpack('N', substr($b, $i, 4))[1];
            $type = substr($b, $i + 4, 4);
            $total = 12 + $chunkLen;
            if ($i + $total > $len) {
                return null;
            }
            if (!in_array($type, ['eXIf', 'tEXt', 'zTXt', 'iTXt'], true)) {
                $out .= substr($b, $i, $total);
            }
            $i += $total;
            if ($type === 'IEND') {
                return $out;
            }
        }
        return null;
    }

    /** WebP: drop EXIF and XMP chunks and clear their flags in the VP8X header. */
    public static function stripWebp(string $b): ?string
    {
        if (strlen($b) < 12 || substr($b, 0, 4) !== 'RIFF' || substr($b, 8, 4) !== 'WEBP') {
            return null;
        }
        $body = 'WEBP';
        $i = 12;
        $len = strlen($b);
        while ($i + 8 <= $len) {
            $type = substr($b, $i, 4);
            $chunkLen = unpack('V', substr($b, $i + 4, 4))[1];
            $total = 8 + $chunkLen + ($chunkLen % 2); // chunks are padded to even sizes
            if ($i + 8 + $chunkLen > $len) {
                return null;
            }
            $chunk = substr($b, $i, min($total, $len - $i));
            if ($type === 'VP8X' && $chunkLen >= 1) {
                $flags = ord($chunk[8]) & ~0x0C; // clear EXIF (0x08) and XMP (0x04) bits
                $chunk[8] = chr($flags);
            }
            if ($type !== 'EXIF' && $type !== 'XMP ') {
                $body .= $chunk;
            }
            $i += $total;
        }
        return 'RIFF' . pack('V', strlen($body)) . $body;
    }
}
