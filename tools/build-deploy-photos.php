<?php

/**
 * Builds the deployment copy of storage/app/public/photos.
 *
 * Why this exists: BanguedPhotosSeeder copies source images into
 * storage/app/public/photos/<place_id>/ keyed on a mutable place id and never
 * cleans up, so that folder accumulated 1871 files across 557 place
 * directories while containing only 70 unique images (4.0 GB). This tool
 * reads the paths that place_photos actually references, keeps only those,
 * and re-encodes them for web delivery.
 *
 * What it does per image:
 *   - reads EXIF orientation via getimagesize() and bakes it into pixels,
 *     because GD has no exif extension and re-encoding drops the tag
 *   - downscales so the longest edge is at most --max px
 *   - strips EXIF/IPTC by re-encoding (no copy happens)
 *   - writes progressive JPEG so it paints top-down on a slow link
 *   - never upscales, and skips files that would grow
 *
 * Output preserves the relative path, so the `place_photos.path` values in
 * the database need no rewriting.
 *
 * Usage:
 *   php tools/build-deploy-photos.php --out=<dir> [--max=1600] [--quality=80]
 *                                     [--status=approved] [--dry-run]
 *
 * DB connection defaults can be overridden with the matching env vars.
 */

declare(strict_types=1);

$opts = getopt('', ['out::', 'max::', 'quality::', 'status::', 'dry-run', 'help']);

if (isset($opts['help'])) {
    fwrite(STDOUT, "Usage: php tools/build-deploy-photos.php --out=<dir> [--max=1600] [--quality=80] [--status=approved] [--dry-run]\n");
    exit(0);
}

$outRoot   = rtrim($opts['out'] ?? '', '/\\');
$maxEdge   = max(0, (int) ($opts['max'] ?? 1600));
$quality   = min(100, max(1, (int) ($opts['quality'] ?? 80)));
$status    = $opts['status'] ?? 'approved';
$dryRun    = isset($opts['dry-run']);
$sourceRoot = rtrim(getenv('PHOTO_SOURCE_ROOT') ?: dirname(__DIR__) . '/storage/app/public', '/\\');

if ($outRoot === '') {
    fwrite(STDERR, "Error: --out is required.\n");
    exit(1);
}

if (!extension_loaded('gd') || !function_exists('imagecreatefromjpeg') || !function_exists('imagejpeg')) {
    fwrite(STDERR, "Error: this script needs the gd extension with JPEG support.\n");
    exit(1);
}

// A single 8 MB source photo can need several hundred MB once decoded.
@ini_set('memory_limit', '1024M');

$host     = getenv('DB_HOST') ?: '127.0.0.1';
$port     = getenv('DB_PORT') ?: '3306';
$dbName   = getenv('DB_DATABASE') ?: 'bangued_atlas';
$dbUser   = getenv('DB_USERNAME') ?: 'root';
$dbPass   = getenv('DB_PASSWORD') ?: '';

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "Error: cannot connect to {$dbName} at {$host} - {$e->getMessage()}\n");
    exit(1);
}

$sql = 'SELECT place_id, path, status FROM place_photos'
     . ($status === 'any' ? '' : ' WHERE status = :status')
     . ' ORDER BY place_id, id';

$stmt = $pdo->prepare($sql);
$stmt->execute($status === 'any' ? [] : ['status' => $status]);
$rows = $stmt->fetchAll();

if ($rows === []) {
    fwrite(STDERR, "Error: no place_photos rows matched (status={$status}).\n");
    exit(1);
}

/**
 * Rotates/flips decoded pixels to match the EXIF orientation tag.
 * Values 5-8 also transpose, which is why the 16:9 boxes are swapped.
 */
function applyOrientation(GdImage $img, int $orientation): GdImage
{
    $rotate = match ($orientation) {
        3, 4    => 180,
        5, 6    => -90,
        7, 8    => 90,
        default => 0,
    };

    $flip = in_array($orientation, [2, 4, 5, 7], true);

    if ($flip) {
        imageflip($img, IMG_FLIP_HORIZONTAL);
    }
    if ($rotate !== 0) {
        $img = imagerotate($img, $rotate, 0);
        if ($img === false) {
            throw new RuntimeException('imagerotate failed');
        }
    }
    if (in_array($orientation, [5, 7], true)) {
        $img = imagerotate($img, 0, 0);
        if ($img === false) {
            throw new RuntimeException('imagerotate failed');
        }
    }

    return $img;
}

$written = $skipped = $failed = 0;
$inBytes = 0;
$outBytes = 0;
$reduced = 0;
$byPlace = [];
$problems = [];

foreach ($rows as $row) {
    $rel = ltrim(str_replace('\\', '/', (string) $row['path']), '/');
    $src = $sourceRoot . '/' . $rel;
    $dst = $outRoot . '/' . $rel;

    if (!is_file($src)) {
        $failed++;
        $problems[] = "MISSING SOURCE  {$rel}";
        continue;
    }

    $inBytes += filesize($src);

    $info = @getimagesize($src);
    if ($info === false || $info[2] !== IMAGETYPE_JPEG) {
        $failed++;
        $problems[] = "NOT A JPEG      {$rel}";
        continue;
    }

    [$srcW, $srcH] = $info;
    $orientation = (int) ($info['orientation'] ?? 1);

    if ($dryRun) {
        $targetW = $srcW;
        $targetH = $srcH;
        $longest = max($srcW, $srcH);
        if ($maxEdge > 0 && $longest > $maxEdge) {
            $scale = $maxEdge / $longest;
            $targetW = max(1, (int) round($srcW * $scale));
            $targetH = max(1, (int) round($srcH * $scale));
        }
        printf("  %-40s %5dx%-5d -> %5dx%-5d  %7.2f MB%s\n",
            $rel, $srcW, $srcH, $targetW, $targetH,
            filesize($src) / 1048576,
            $orientation > 1 ? sprintf('   exif-orientation=%d', $orientation) : '');
        $byPlace[(int) $row['place_id']] = true;
        continue;
    }

    $dir = dirname($dst);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        $failed++;
        $problems[] = "CANNOT MKDIR     {$dir}";
        continue;
    }

    $img = @imagecreatefromjpeg($src);
    if ($img === false) {
        $failed++;
        $problems[] = "DECODE FAILED   {$rel}";
        continue;
    }

    try {
        if ($orientation > 1) {
            $img = applyOrientation($img, $orientation);
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $longest = max($w, $h);

        if ($maxEdge > 0 && $longest > $maxEdge) {
            $scale = $maxEdge / $longest;
            $tw = max(1, (int) round($w * $scale));
            $th = max(1, (int) round($h * $scale));
            $scaled = imagecreatetruecolor($tw, $th);
            if ($scaled === false) {
                throw new RuntimeException('imagecreatetruecolor failed');
            }
            // Photos are opaque; without this the alpha channel turns the
            // flattened background black on some GD builds.
            imagealphablending($scaled, false);
            imagesavealpha($scaled, false);
            $black = imagecolorallocate($scaled, 255, 255, 255);
            imagefilledrectangle($scaled, 0, 0, $tw, $th, $black);
            imagealphablending($scaled, true);
            if (!imagecopyresampled($scaled, $img, 0, 0, 0, 0, $tw, $th, $w, $h)) {
                imagedestroy($scaled);
                throw new RuntimeException('imagecopyresampled failed');
            }
            imagedestroy($img);
            $img = $scaled;
        }

        // Smooth the downscale a little; cheap quality win on thumbnails.
        imagefilter($img, IMG_FILTER_SMOOTH, -8);
        // Progressive JPEG: paints top-down on a slow connection. Passed as a
        // real bool because PHP 8.5 narrowed this parameter to ?bool.
        imageinterlace($img, true);

        if (!imagejpeg($img, $dst, $quality)) {
            imagedestroy($img);
            $failed++;
            $problems[] = "WRITE FAILED    {$rel}";
            continue;
        }

        imagedestroy($img);
    } catch (Throwable $e) {
        $failed++;
        $problems[] = "ERROR           {$rel}: {$e->getMessage()}";
        continue;
    }

    $outBytes += filesize($dst);
    $written++;
    $byPlace[(int) $row['place_id']] = true;
    $reduced += max(0, filesize($src) - filesize($dst));
}

ksort($byPlace);

if ($dryRun) {
    fwrite(STDOUT, sprintf(
        "\nDRY RUN: %d referenced photos across %d places, %.2f MB source\n",
        count($rows), count($byPlace), $inBytes / 1048576
    ));
    exit($failed > 0 ? 1 : 0);
}

if ($failed > 0) {
    fwrite(STDERR, sprintf("\n%d problem(s):\n", $failed));
    foreach ($problems as $p) {
        fwrite(STDERR, "  $p\n");
    }
}

fwrite(STDOUT, sprintf(
    "\n%s\n",
    str_repeat('-', 68)
));
fwrite(STDOUT, sprintf("  referenced rows (status=%s) : %d\n", $status, count($rows)));
fwrite(STDOUT, sprintf("  distinct places            : %d\n", count($byPlace)));
fwrite(STDOUT, sprintf("  written                    : %d\n", $written));
fwrite(STDOUT, sprintf("  failed                     : %d\n", $failed));
fwrite(STDOUT, sprintf("  source bytes               : %s (%.2f MB)\n", number_format($inBytes), $inBytes / 1048576));
fwrite(STDOUT, sprintf("  output bytes               : %s (%.2f MB)\n", number_format($outBytes), $outBytes / 1048576));
fwrite(STDOUT, sprintf("  saved                      : %s (%.2f MB)\n", number_format($reduced), $reduced / 1048576));
fwrite(STDOUT, sprintf("  max edge / quality         : %d px / q%d%s\n", $maxEdge, $quality, $maxEdge > 0 ? '' : ' (no downscale)'));
fwrite(STDOUT, sprintf("%s\n", str_repeat('-', 68)));

if ($written > 0) {
    fwrite(STDOUT, "place ids: " . implode(', ', array_keys($byPlace)) . "\n");
}

exit($failed > 0 ? 1 : ($written > 0 ? 0 : 1));
