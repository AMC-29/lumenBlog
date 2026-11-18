<?php

function post_body_exceeds_limit(): bool
{
    $contentLength = $_SERVER['CONTENT_LENGTH'] ?? null;
    if (!is_string($contentLength) || !preg_match('/\A[0-9]+\z/', $contentLength)) {
        return false;
    }

    $limit = trim((string) ini_get('post_max_size'));
    if ($limit === '' || $limit === '0') {
        return false;
    }
    $unit = strtolower(substr($limit, -1));
    $number = (float) $limit;
    if ($unit === 'g') {
        $number *= 1024;
        $unit = 'm';
    }
    if ($unit === 'm') {
        $number *= 1024;
        $unit = 'k';
    }
    if ($unit === 'k') {
        $number *= 1024;
    }
    return (float) $contentLength > $number;
}

function upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The image is larger than the server upload limit.',
        UPLOAD_ERR_PARTIAL => 'The image upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'The server could not save the uploaded image.',
        UPLOAD_ERR_EXTENSION => 'The server blocked this image upload.',
        default => 'The image upload failed.',
    };
}

/** Validate, decode, normalize, and save one cover or avatar image. */
function save_image_upload($file, string $kind): ?string
{
    if (!in_array($kind, ['covers', 'avatars'], true)) {
        throw new InvalidArgumentException('Invalid image destination.');
    }
    if ($file === null) {
        return null;
    }
    if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Invalid image upload.');
    }
    $error = (int) $file['error'];
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_message($error));
    }
    if (!isset($file['tmp_name']) || !is_string($file['tmp_name'])) {
        throw new RuntimeException('Invalid image upload.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('The uploaded image could not be verified.');
    }
    $size = filesize($file['tmp_name']);
    if ($size === false || $size <= 0 || $size > 4 * 1024 * 1024) {
        throw new RuntimeException('Images must be no larger than 4 MB.');
    }

    $info = @getimagesize($file['tmp_name']);
    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
    if (defined('IMAGETYPE_WEBP')) {
        $allowed[IMAGETYPE_WEBP] = 'webp';
    }
    if (!$info || !isset($allowed[$info[2]])) {
        throw new RuntimeException('Use a JPEG, PNG, or WebP image.');
    }
    [$width, $height] = $info;
    if ($width < 1 || $height < 1 || $width * $height > 16000000) {
        throw new RuntimeException('Images must be no larger than 16 megapixels.');
    }
    if (!function_exists('imagecreatefromstring')) {
        throw new RuntimeException('Image processing is unavailable. Please enable the PHP GD extension.');
    }
    $bytes = file_get_contents($file['tmp_name']);
    $source = $bytes === false ? false : @imagecreatefromstring($bytes);
    if (!$source) {
        throw new RuntimeException('The uploaded file is not a valid image.');
    }

    try {
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($file['tmp_name'], 'IFD0');
            $orientation = (int) ($exif['Orientation'] ?? 1);
            $source = orient_uploaded_image($source, $orientation);
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        if ($kind === 'avatars') {
            $side = min($sourceWidth, $sourceHeight);
            $sourceX = (int) floor(($sourceWidth - $side) / 2);
            $sourceY = (int) floor(($sourceHeight - $side) / 2);
            $targetWidth = $targetHeight = 256;
        } else {
            $scale = min(1, 1600 / max($sourceWidth, $sourceHeight));
            $sourceX = $sourceY = 0;
            $side = 0;
            $targetWidth = max(1, (int) round($sourceWidth * $scale));
            $targetHeight = max(1, (int) round($sourceHeight * $scale));
        }

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        if (!$target) {
            throw new RuntimeException('The server could not process this image.');
        }
        if ($info[2] === IMAGETYPE_PNG) {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefill($target, 0, 0, $transparent);
        }
        $copied = $kind === 'avatars'
            ? imagecopyresampled($target, $source, 0, 0, $sourceX, $sourceY, 256, 256, $side, $side)
            : imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
        if (!$copied) {
            imagedestroy($target);
            throw new RuntimeException('The server could not process this image.');
        }

        $extension = $allowed[$info[2]];
        if ($extension === 'webp' && !function_exists('imagewebp')) {
            imagedestroy($target);
            throw new RuntimeException('WebP images are not supported by this server.');
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $directory = dirname(__DIR__) . '/uploads/' . $kind;
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            imagedestroy($target);
            throw new RuntimeException('The server could not save the uploaded image.');
        }
        $destination = $directory . '/' . $filename;
        $saved = match ($extension) {
            'jpg' => imagejpeg($target, $destination, 86),
            'png' => imagepng($target, $destination, 6),
            'webp' => imagewebp($target, $destination, 86),
        };
        imagedestroy($target);
        if (!$saved) {
            if (is_file($destination)) {
                unlink($destination);
            }
            throw new RuntimeException('The server could not save the uploaded image.');
        }
        return $filename;
    } finally {
        imagedestroy($source);
    }
}

function orient_uploaded_image($image, int $orientation)
{
    if ($orientation === 2 || $orientation === 5 || $orientation === 7) {
        imageflip($image, IMG_FLIP_HORIZONTAL);
    } elseif ($orientation === 4) {
        imageflip($image, IMG_FLIP_VERTICAL);
    }
    $degrees = match ($orientation) {
        3 => 180,
        5, 8 => 90,
        6, 7 => -90,
        default => 0,
    };
    if ($degrees !== 0) {
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        $rotated = imagerotate($image, $degrees, $transparent);
        if ($rotated === false) {
            throw new RuntimeException('The server could not rotate this image.');
        }
        imagedestroy($image);
        return $rotated;
    }
    return $image;
}

function delete_image_file(string $kind, ?string $filename): void
{
    if (!in_array($kind, ['covers', 'avatars'], true) || !valid_image_filename($filename)) {
        return;
    }
    $path = dirname(__DIR__) . '/uploads/' . $kind . '/' . $filename;
    if (is_file($path) && !unlink($path)) {
        throw new RuntimeException('The old image file could not be removed.');
    }
}
