<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use RuntimeException;

/**
 * TASK-018: безопасная обработка фото и видео для альбомов.
 *
 * Фото: сигнатурная проверка → finfo + getimagesize → пересохранение через GD (новый холст, без EXIF/GPS,
 *       поворот по EXIF применяется) → полноразмерный JPEG (до 4096 px) + превью (до 480 px). Исходник не хранится.
 * Видео: только mp4 / mov / webm; тип по содержимому (ftyp / EBML + finfo), поиск скриптов в начале и конце файла,
 *        лимит размера ALBUM_VIDEO_MAX_MB (по умолчанию 500). Имя случайное, расширение задаёт сервер.
 * Все файлы лежат в storage/uploads/albums (вне public) и выдаются только через контроллер с проверкой входа.
 */
class AlbumMediaService
{
    public const PHOTO_MAX_BYTES = 30 * 1024 * 1024;   // 30 МБ (фото с телефона)
    public const PHOTO_MAX_DIMENSION = 12000;
    public const PHOTO_MAX_PIXELS = 50_000_000;         // защита от «бомб» распаковки (50 Мп)
    public const FULL_MAX_EDGE = 4096;
    public const THUMB_MAX_EDGE = 480;

    public const PHOTO_EXTS = ['jpg', 'jpeg', 'png', 'webp'];
    public const VIDEO_EXTS = ['mp4', 'm4v', 'mov', 'webm'];

    /**
     * Строки, которых не может быть в настоящем фото (без учёта регистра). Только длинные метки:
     * короткие (<?=, eval() случайно встречаются в сжатых данных и дают ложные отказы; фото всё равно пересохраняется GD.
     */
    private const DANGEROUS_STRINGS = [
        '<?php', '<script', '<svg', '<html', '<iframe', '<!doctype',
        'base64_decode', 'shell_exec(', 'passthru(', 'proc_open(',
    ];
    /** Для видео — только длинные метки (короткие могут случайно встретиться в сжатых данных) */
    private const VIDEO_DANGEROUS_STRINGS = ['<?php', '<script', '<html', '<svg', '<iframe', '<!doctype', 'base64_decode'];

    /** Бренды ftyp, которые считаются видео (HEIC/AVIF-картинки с тем же контейнером отклоняются) */
    private const VIDEO_FTYP_BRANDS = [
        'isom', 'iso2', 'iso3', 'iso4', 'iso5', 'iso6', 'mp41', 'mp42', 'avc1', 'm4v ', 'm4vh', 'm4vp',
        'qt  ', 'mmp4', 'msnv', 'dash', '3gp4', '3gp5', '3gp6', '3g2a', 'f4v ', 'xavc', 'mp4v',
    ];
    private const VIDEO_FINFO_MIMES = [
        'video/mp4', 'video/quicktime', 'video/webm', 'video/x-m4v', 'video/3gpp', 'video/3gpp2',
        'application/octet-stream', 'audio/mp4', 'audio/webm', 'video/x-matroska',
    ];

    private string $baseDir;

    public function __construct(?string $baseDir = null)
    {
        $this->baseDir = rtrim($baseDir ?? (dirname(__DIR__, 2) . '/storage/uploads/albums'), '/\\');
    }

    public function baseDir(): string
    {
        return $this->baseDir;
    }

    public static function videoMaxBytes(): int
    {
        $mb = (int)Config::get('ALBUM_VIDEO_MAX_MB', 500);
        if ($mb < 10) {
            $mb = 10;
        }
        if ($mb > 4096) {
            $mb = 4096;
        }
        return $mb * 1024 * 1024;
    }

    /** 'photo' | 'video' | null по расширению имени (только первичный фильтр; тип проверяется по содержимому) */
    public static function kindByName(string $name): ?string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, self::PHOTO_EXTS, true)) {
            return 'photo';
        }
        if (in_array($ext, self::VIDEO_EXTS, true)) {
            return 'video';
        }
        return null;
    }

    public static function maxBytesFor(string $kind): int
    {
        return $kind === 'video' ? self::videoMaxBytes() : self::PHOTO_MAX_BYTES;
    }

    /** Папка для временных частей загрузки */
    public function tmpDir(): string
    {
        $dir = $this->baseDir . DIRECTORY_SEPARATOR . '_tmp';
        $this->ensureDir($dir);
        return $dir;
    }

    /** Удалить брошенные временные загрузки старше $maxAge секунд */
    public function cleanupTmp(int $maxAge = 86400): void
    {
        $dir = $this->tmpDir();
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
            if (is_file($f) && (time() - (int)@filemtime($f)) > $maxAge) {
                @unlink($f);
            }
        }
    }

    /**
     * Фото: проверить и пересохранить. Возвращает пути относительно baseDir.
     * @return array{file_path:string, thumb_path:string, ext:string, width:int, height:int, file_size:int}
     */
    public function processPhoto(string $srcPath, string $lang = 'kk'): array
    {
        $this->assertReadable($srcPath, $lang);
        $size = (int)filesize($srcPath);
        if ($size <= 0 || $size > self::PHOTO_MAX_BYTES) {
            throw new RuntimeException($this->msg($lang, 'Фото тым үлкен (30 МБ дейін)', 'Фото слишком большое (до 30 МБ)'));
        }

        $this->scanSignatures($srcPath, self::DANGEROUS_STRINGS, $lang);
        $info = $this->inspectImage($srcPath, $lang);

        $this->raiseMemoryLimit();
        $img = $this->decodeImage($srcPath, $info['type'], $lang);
        $img = $this->applyOrientation($img, $srcPath, $info['type']);

        $relDir = date('Y') . '/' . date('m');
        $absDir = $this->baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relDir);
        $this->ensureDir($absDir);

        $uuid = self::uuid();
        $fullRel = $relDir . '/p_' . $uuid . '.jpg';
        $thumbRel = $relDir . '/t_' . $uuid . '.jpg';

        try {
            [$fw, $fh] = $this->saveScaledJpeg($img, $this->abs($fullRel), self::FULL_MAX_EDGE, 90);
            $this->saveScaledJpeg($img, $this->abs($thumbRel), self::THUMB_MAX_EDGE, 82);
        } catch (\Throwable $e) {
            @unlink($this->abs($fullRel));
            @unlink($this->abs($thumbRel));
            throw $e;
        } finally {
            imagedestroy($img);
        }

        return [
            'file_path'  => $fullRel,
            'thumb_path' => $thumbRel,
            'ext'        => 'jpg',
            'width'      => $fw,
            'height'     => $fh,
            'file_size'  => (int)filesize($this->abs($fullRel)),
        ];
    }

    /**
     * Превью видео (кадр, снятый браузером) — пересохраняется как обычное фото, но только маленькое.
     * Ошибка не критична: вызывающий код может сохранить видео без превью.
     */
    public function processPoster(string $srcPath, string $lang = 'kk'): string
    {
        $this->assertReadable($srcPath, $lang);
        if ((int)filesize($srcPath) > 5 * 1024 * 1024) {
            throw new RuntimeException('poster too big');
        }
        $this->scanSignatures($srcPath, self::DANGEROUS_STRINGS, $lang);
        $info = $this->inspectImage($srcPath, $lang);
        $img = $this->decodeImage($srcPath, $info['type'], $lang);

        $relDir = date('Y') . '/' . date('m');
        $this->ensureDir($this->baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relDir));
        $rel = $relDir . '/v_' . self::uuid() . '.jpg';
        try {
            $this->saveScaledJpeg($img, $this->abs($rel), 720, 82);
        } finally {
            imagedestroy($img);
        }
        return $rel;
    }

    /**
     * Видео: проверить тип по содержимому и перенести в хранилище.
     * @return array{file_path:string, ext:string, file_size:int, mime:string}
     */
    public function processVideo(string $srcPath, string $declaredName, string $lang = 'kk'): array
    {
        $this->assertReadable($srcPath, $lang);
        $size = (int)filesize($srcPath);
        $max = self::videoMaxBytes();
        if ($size <= 0 || $size > $max) {
            $mb = (int)round($max / 1048576);
            throw new RuntimeException($this->msg($lang, "Бейне тым үлкен ({$mb} МБ дейін)", "Видео слишком большое (до {$mb} МБ)"));
        }

        $ext = $this->detectVideoExt($srcPath, $declaredName);
        if ($ext === null) {
            throw new RuntimeException($this->msg($lang,
                'Бейне қабылданбады: тек MP4, MOV, WebM рұқсат етілген',
                'Видео отклонено: разрешены только MP4, MOV, WebM'));
        }

        $this->scanSignatures($srcPath, self::VIDEO_DANGEROUS_STRINGS, $lang);

        $relDir = date('Y') . '/' . date('m');
        $this->ensureDir($this->baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relDir));
        $rel = $relDir . '/m_' . self::uuid() . '.' . $ext;
        $dest = $this->abs($rel);

        if (!@rename($srcPath, $dest)) {
            if (!@copy($srcPath, $dest)) {
                throw new RuntimeException($this->msg($lang, 'Файлды сақтау мүмкін болмады', 'Не удалось сохранить файл'));
            }
            @unlink($srcPath);
        }

        return [
            'file_path' => $rel,
            'ext'       => $ext,
            'file_size' => (int)filesize($dest),
            'mime'      => self::mimeForExt($ext),
        ];
    }

    /**
     * Тип видео по содержимому: 'mp4' | 'mov' | 'webm' | null.
     * MP4/MOV — блок ftyp с видеобрендом; WebM — заголовок EBML с DocType webm; плюс сверка с finfo.
     */
    public function detectVideoExt(string $path, string $declaredName = ''): ?string
    {
        $fp = @fopen($path, 'rb');
        if (!$fp) {
            return null;
        }
        $head = (string)fread($fp, 64);
        fclose($fp);
        if (strlen($head) < 12) {
            return null;
        }

        $result = null;
        if (substr($head, 4, 4) === 'ftyp') {
            $boxSize = unpack('N', substr($head, 0, 4))[1] ?? 0;
            if ($boxSize < 16 && $boxSize !== 1) {
                return null; // блок ftyp короче минимального
            }
            $brand = strtolower(substr($head, 8, 4));
            if (!in_array($brand, self::VIDEO_FTYP_BRANDS, true)) {
                return null; // heic, avif, mif1 и прочие картинки в контейнере ISO BMFF
            }
            $result = ($brand === 'qt  ') ? 'mov' : 'mp4';
            $declExt = strtolower(pathinfo($declaredName, PATHINFO_EXTENSION));
            if ($declExt === 'mov' && $result === 'mp4') {
                $result = 'mov'; // iPhone .mov с брендом isom/mp42 — сохраняем как mov
            }
        } elseif (substr($head, 0, 4) === "\x1A\x45\xDF\xA3") {
            if (strpos($head, 'webm') === false) {
                return null; // Matroska (mkv) не принимаем
            }
            $result = 'webm';
        } else {
            return null;
        }

        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $fi ? (string)finfo_file($fi, $path) : '';
            if ($fi) {
                finfo_close($fi);
            }
            if ($mime !== '' && !in_array($mime, self::VIDEO_FINFO_MIMES, true)) {
                return null;
            }
        }
        return $result;
    }

    public static function mimeForExt(string $ext): string
    {
        return match ($ext) {
            'jpg'  => 'image/jpeg',
            'webm' => 'video/webm',
            'mov'  => 'video/quicktime',
            default => 'video/mp4',
        };
    }

    /** Абсолютный путь по относительному, без выхода за пределы хранилища */
    public function abs(string $rel): string
    {
        $rel = str_replace('\\', '/', $rel);
        if ($rel === '' || str_contains($rel, '..') || str_starts_with($rel, '/') || !preg_match('#^[A-Za-z0-9_/.\-]+$#', $rel)) {
            throw new RuntimeException('Invalid media path');
        }
        return $this->baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    }

    public function deleteFiles(?string ...$rels): void
    {
        foreach ($rels as $rel) {
            if ($rel === null || $rel === '') {
                continue;
            }
            try {
                $p = $this->abs($rel);
                if (is_file($p)) {
                    @unlink($p);
                }
            } catch (\Throwable $e) {
                // неверный путь — пропускаем
            }
        }
    }

    /**
     * Сделать аватар: квадрат 600 px из фото альбома в public/uploads/photos (как аватары TASK-007).
     * @return string публичный URL
     */
    /**
     * Квадратный аватар 600 px из фото альбома.
     * $crop (TASK-018, необязательно): ['x' => доля ширины, 'y' => доля высоты, 's' => доля меньшей стороны] —
     * квадрат, выбранный пользователем; значения зажимаются в границы фото.
     */
    public function makeAvatar(string $photoRel, string $publicDir, string $publicPrefix = '/uploads/photos/', ?array $crop = null): string
    {
        $src = $this->abs($photoRel);
        $info = $this->inspectImage($src, 'kk');
        $this->raiseMemoryLimit();
        $img = $this->decodeImage($src, $info['type'], 'kk');
        $w = imagesx($img);
        $h = imagesy($img);
        $min = min($w, $h);
        if ($crop !== null) {
            $fs = max(0.05, min(1.0, (float)$crop['s']));
            $side = max(16, (int)round($min * $fs));
            $side = min($side, $min);
            $sx = (int)round($w * max(0.0, min(1.0, (float)$crop['x'])));
            $sy = (int)round($h * max(0.0, min(1.0, (float)$crop['y'])));
            $sx = max(0, min($sx, $w - $side));
            $sy = max(0, min($sy, $h - $side));
        } else {
            $side = $min;
            $sx = (int)(($w - $side) / 2);
            $sy = (int)max(0, ($h - $side) / 3); // чуть выше центра — лица обычно в верхней части
        }
        $out = min(600, $side);
        $canvas = imagecreatetruecolor($out, $out);
        imagecopyresampled($canvas, $img, 0, 0, $sx, $sy, $out, $out, $side, $side);
        imagedestroy($img);

        if (!is_dir($publicDir)) {
            @mkdir($publicDir, 0755, true);
        }
        $name = 'photo_' . self::uuid() . '.jpg';
        $ok = imagejpeg($canvas, rtrim($publicDir, '/\\') . DIRECTORY_SEPARATOR . $name, 88);
        imagedestroy($canvas);
        if (!$ok) {
            throw new RuntimeException('avatar save failed');
        }
        return rtrim($publicPrefix, '/') . '/' . $name;
    }

    /**
     * TASK-022: обложка карточки человека — фото альбома, уменьшенное до ширины 1600 px (пропорции сохраняются).
     * @return string публичный URL
     */
    public function makeCover(string $photoRel, string $publicDir, string $publicPrefix = '/uploads/photos/'): string
    {
        $src = $this->abs($photoRel);
        $info = $this->inspectImage($src, 'kk');
        $this->raiseMemoryLimit();
        $img = $this->decodeImage($src, $info['type'], 'kk');
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1.0, 1600 / max(1, $w));
        $ow = max(1, (int)round($w * $scale));
        $oh = max(1, (int)round($h * $scale));
        $canvas = imagecreatetruecolor($ow, $oh);
        imagecopyresampled($canvas, $img, 0, 0, 0, 0, $ow, $oh, $w, $h);
        imagedestroy($img);

        if (!is_dir($publicDir)) {
            @mkdir($publicDir, 0755, true);
        }
        $name = 'cover_' . self::uuid() . '.jpg';
        $ok = imagejpeg($canvas, rtrim($publicDir, '/\\') . DIRECTORY_SEPARATOR . $name, 86);
        imagedestroy($canvas);
        if (!$ok) {
            throw new RuntimeException('cover save failed');
        }
        return rtrim($publicPrefix, '/') . '/' . $name;
    }

    public static function uuid(): string
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }

    // ---------------------------------------------------------------------------------------------

    private function assertReadable(string $path, string $lang): void
    {
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException($this->msg($lang, 'Файл табылмады', 'Файл не найден'));
        }
    }

    /** Поиск скриптов: весь файл до 2 МБ, иначе первые и последние 512 КБ */
    private function scanSignatures(string $path, array $needles, string $lang): void
    {
        clearstatcache(true, $path);
        $size = (int)@filesize($path);
        $fp = @fopen($path, 'rb');
        if (!$fp) {
            throw new RuntimeException($this->msg($lang, 'Файлды оқу мүмкін болмады', 'Не удалось прочитать файл'));
        }
        if ($size <= 2 * 1024 * 1024) {
            $buf = (string)stream_get_contents($fp);
        } else {
            $buf = (string)fread($fp, 524288);
            fseek($fp, max(0, $size - 524288));
            $buf .= "\n" . (string)fread($fp, 524288);
        }
        fclose($fp);

        foreach ($needles as $needle) {
            if (stripos($buf, $needle) !== false) {
                throw new RuntimeException($this->msg($lang,
                    'Файл қабылданбады: ішінде скрипт немесе код табылды',
                    'Файл отклонён: внутри найден скрипт или программный код'));
            }
        }
    }

    /** @return array{type:int, width:int, height:int} */
    private function inspectImage(string $path, string $lang): array
    {
        $mime = '';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $fi ? (string)finfo_file($fi, $path) : '';
            if ($fi) {
                finfo_close($fi);
            }
        }
        $expected = match ($mime) {
            'image/jpeg', 'image/pjpeg' => IMAGETYPE_JPEG,
            'image/png'  => IMAGETYPE_PNG,
            'image/webp' => defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : 18,
            default      => null,
        };
        if ($expected === null) {
            throw new RuntimeException($this->msg($lang,
                'Тек JPEG, PNG, WebP фото қабылданады (SVG-ге тыйым салынған)',
                'Принимаются только фото JPEG, PNG, WebP (SVG запрещён)'));
        }

        $gi = @getimagesize($path);
        if ($gi === false || (int)$gi[2] !== $expected) {
            throw new RuntimeException($this->msg($lang, 'Файл бүлінген немесе сурет емес', 'Файл повреждён или не является фото'));
        }
        $w = (int)$gi[0];
        $h = (int)$gi[1];
        if ($w <= 0 || $h <= 0 || $w > self::PHOTO_MAX_DIMENSION || $h > self::PHOTO_MAX_DIMENSION || $w * $h > self::PHOTO_MAX_PIXELS) {
            throw new RuntimeException($this->msg($lang, 'Фото өлшемі тым үлкен', 'Слишком большое разрешение фото'));
        }
        return ['type' => $expected, 'width' => $w, 'height' => $h];
    }

    /** @return \GdImage */
    private function decodeImage(string $path, int $type, string $lang)
    {
        $img = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            default        => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        };
        if (!$img) {
            throw new RuntimeException($this->msg($lang, 'Суретті ашу мүмкін болмады', 'Не удалось открыть изображение'));
        }
        return $img;
    }

    /** Поворот/отражение по EXIF Orientation (сами EXIF при пересохранении не сохраняются) */
    private function applyOrientation($img, string $path, int $type)
    {
        if ($type !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) {
            return $img;
        }
        $exif = @exif_read_data($path);
        $o = (int)($exif['Orientation'] ?? 1);
        if ($o <= 1 || $o > 8) {
            return $img;
        }
        // imagerotate крутит против часовой стрелки: сначала поворот, затем отражение
        // 2: отражение по горизонтали; 3: 180°; 4: по вертикали; 5: -90° + гориз.; 6: -90°; 7: +90° + гориз.; 8: +90°
        $deg = match ($o) {
            3 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };
        if ($deg !== 0) {
            $r = imagerotate($img, $deg, 0);
            if ($r !== false) {
                imagedestroy($img);
                $img = $r;
            }
        }
        if (function_exists('imageflip')) {
            if (in_array($o, [2, 5, 7], true)) {
                imageflip($img, IMG_FLIP_HORIZONTAL);
            } elseif ($o === 4) {
                imageflip($img, IMG_FLIP_VERTICAL);
            }
        }
        return $img;
    }

    /** Новый холст (белая подложка), масштаб до $maxEdge, сохранение JPEG. @return array{0:int,1:int} */
    private function saveScaledJpeg($img, string $dest, int $maxEdge, int $quality): array
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1.0, $maxEdge / max($w, $h));
        $nw = max(1, (int)round($w * $scale));
        $nh = max(1, (int)round($h * $scale));

        $canvas = imagecreatetruecolor($nw, $nh);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $nw, $nh, $white);
        imagecopyresampled($canvas, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imageinterlace($canvas, true);
        $ok = imagejpeg($canvas, $dest, $quality);
        imagedestroy($canvas);
        if (!$ok || !is_file($dest)) {
            throw new RuntimeException('Не удалось сохранить изображение');
        }
        return [$nw, $nh];
    }

    private function raiseMemoryLimit(): void
    {
        $cur = (string)ini_get('memory_limit');
        if ($cur === '-1') {
            return;
        }
        $bytes = (int)$cur;
        $unit = strtolower(substr($cur, -1));
        if ($unit === 'g') {
            $bytes *= 1024 ** 3;
        } elseif ($unit === 'm') {
            $bytes *= 1024 ** 2;
        } elseif ($unit === 'k') {
            $bytes *= 1024;
        }
        if ($bytes < 768 * 1024 * 1024) {
            @ini_set('memory_limit', '768M');
        }
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create storage directory');
        }
    }

    private function msg(string $lang, string $kk, string $ru): string
    {
        return $lang === 'ru' ? $ru : $kk;
    }
}
