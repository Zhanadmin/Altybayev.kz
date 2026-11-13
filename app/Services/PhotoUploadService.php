<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\I18n;
use PDO;
use RuntimeException;

class PhotoUploadService
{
    private const MAX_FILE_SIZE = 20 * 1024 * 1024; // 20 МБ
    private const MAX_DIMENSION = 8000;              // 8000 x 8000 пикселей
    private const MAX_RAW_PIXELS = 64_000_000;       // Защита от декомпрессионных бомб (64 Мп)

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/pjpeg'=> 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private const DANGEROUS_STRINGS = [
        '<?php',
        '<?=',
        '<script',
        '<svg',
        '<html',
        '<body',
        '<iframe',
        'base64_decode',
        'eval(',
        'eval (',
        'passthru(',
        'system(',
        'shell_exec(',
        'proc_open(',
        'popen(',
        'onload=',
        'onerror=',
    ];

    private string $uploadDir;
    private string $publicUrlPrefix;

    public function __construct(?string $uploadDir = null, string $publicUrlPrefix = '/uploads/photos/')
    {
        $this->uploadDir = $uploadDir ?: (__DIR__ . '/../../public/uploads/photos');
        $this->publicUrlPrefix = rtrim($publicUrlPrefix, '/') . '/';

        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Валидация и безопасное сохранение загруженной фотографии
     *
     * @param array $file Элемент массива $_FILES['photo']
     * @param int|null $personId ID персоны (для аудита и привязки)
     * @param int|null $userId ID пользователя, выполняющего загрузку
     * @return array Результат сохранения
     * @throws RuntimeException при любой угрозе безопасности или нарушении лимитов
     */
    public function processUpload(array $file, ?int $personId = null, ?int $userId = null): array
    {
        $lang = I18n::lang();

        // 1. Проверка базовых ошибок загрузки
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException($lang === 'ru' ? 'Некорректные параметры загрузки файла' : 'Файлды жүктеу параметрлері дұрыс емес');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $msg = match ($file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => ($lang === 'ru' ? 'Размер файла превышает лимит сервера (макс. 20 МБ)' : 'Файл көлемі сервер шегінен асады (макс. 20 МБ)'),
                UPLOAD_ERR_PARTIAL => ($lang === 'ru' ? 'Файл был загружен только частично' : 'Файл толық жүктелмеді'),
                UPLOAD_ERR_NO_FILE => ($lang === 'ru' ? 'Файл не был выбран' : 'Файл таңдалмады'),
                default => ($lang === 'ru' ? 'Ошибка загрузки файла на сервер' : 'Серверге файлды жүктеу кезінде қате орын алды'),
            };
            throw new RuntimeException($msg);
        }

        $tempPath = (string)$file['tmp_name'];
        $tempPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $tempPath);
        if (!is_uploaded_file($tempPath) && !file_exists($tempPath)) {
            throw new RuntimeException($lang === 'ru' ? 'Временный файл не найден' : 'Уақытша файл табылмады');
        }
        $tempPath = realpath($tempPath) ?: $tempPath;

        // 2. Лимит размера файла (20 МБ)
        $fileSize = (int)$file['size'];
        if ($fileSize <= 0 || $fileSize > self::MAX_FILE_SIZE) {
            throw new RuntimeException($lang === 'ru' ? 'Файл слишком большой. Разрешено до 20 МБ' : 'Файл тым үлкен. 20 МБ дейін рұқсат етілген');
        }

        // 3. Сигнатурный анализ до декодирования (поиск скрытых скриптов, PHP, HTML, SVG)
        $this->scanForMaliciousSignatures($tempPath, $lang);

        // 4. Определение MIME-типа через fileinfo (по содержимому, а не по расширению)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = $finfo ? finfo_file($finfo, $tempPath) : null;
        if ($finfo) {
            finfo_close($finfo);
        }

        if (!$realMime || !isset(self::ALLOWED_MIME_TYPES[$realMime])) {
            throw new RuntimeException($lang === 'ru' 
                ? 'Недопустимый формат файла. Разрешены только JPEG, PNG, WebP (SVG строго запрещен)' 
                : 'Қабылданбайтын файл түрі. Тек JPEG, PNG, WebP рұқсат етілген (SVG-ге қатаң тыйым салынады)');
        }

        // 5. Проверка через getimagesize() и защита от декомпрессионных бомб
        $imgInfo = @getimagesize($tempPath);
        if ($imgInfo === false) {
            throw new RuntimeException($lang === 'ru' ? 'Файл поврежден или не является валидным изображением' : 'Файл бүлінген немесе сурет емес');
        }

        $width = (int)$imgInfo[0];
        $height = (int)$imgInfo[1];
        $imageType = (int)$imgInfo[2];

        if ($width <= 0 || $height <= 0) {
            throw new RuntimeException($lang === 'ru' ? 'Некорректные размеры изображения' : 'Сурет өлшемдері дұрыс емес');
        }

        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw new RuntimeException($lang === 'ru' 
                ? "Разрешение изображения превышает 8000x8000 пикселей ({$width}x{$height})" 
                : "Сурет өлшемі 8000x8000 пикселден асады ({$width}x{$height})");
        }

        if (($width * $height) > self::MAX_RAW_PIXELS) {
            throw new RuntimeException($lang === 'ru' ? 'Обнаружена декомпрессионная бомба (слишком много пикселей)' : 'Декомпрессиялық шабуыл қаупі анықталды');
        }

        // Проверяем соответствие типа getimagesize и finfo
        $expectedType = match ($realMime) {
            'image/jpeg', 'image/jpg', 'image/pjpeg' => IMAGETYPE_JPEG,
            'image/png' => IMAGETYPE_PNG,
            'image/webp' => defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : 18,
            default => null,
        };

        if ($expectedType !== null && $imageType !== $expectedType) {
            throw new RuntimeException($lang === 'ru' ? 'Несоответствие структуры изображения заявленному типу' : 'Сурет құрылымы жарияланған түрге сәйкес келмейді');
        }

        // 6. Чтение EXIF ориентации до пересохранения (только для JPEG)
        $orientation = 1;
        if ($imageType === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($tempPath);
            if (!empty($exif['Orientation'])) {
                $orientation = (int)$exif['Orientation'];
            }
        }

        // 7. Полное пересохранение через GD (создание нового чистого битмапа)
        $targetExtension = self::ALLOWED_MIME_TYPES[$realMime];
        $uuid = $this->generateUuidV4();
        $safeFileName = "photo_{$uuid}.{$targetExtension}";
        $destinationPath = $this->uploadDir . DIRECTORY_SEPARATOR . $safeFileName;

        $this->reencodeImage($tempPath, $destinationPath, $imageType, $orientation, $targetExtension);

        // Получаем итоговые данные пересохраненного файла
        $finalSize = filesize($destinationPath);
        $finalDims = @getimagesize($destinationPath);
        $finalWidth = $finalDims ? (int)$finalDims[0] : $width;
        $finalHeight = $finalDims ? (int)$finalDims[1] : $height;
        $relativeUrl = $this->publicUrlPrefix . $safeFileName;

        // 8. Запись в audit_logs и сохранение в БД
        $this->logUploadAudit($safeFileName, $personId, $userId, $finalSize, $finalWidth, $finalHeight);

        return [
            'success'      => true,
            'file_name'    => $safeFileName,
            'file_path'    => $destinationPath,
            'relative_url' => $relativeUrl,
            'mime_type'    => $realMime,
            'width'        => $finalWidth,
            'height'       => $finalHeight,
            'file_size'    => $finalSize,
        ];
    }

    /**
     * Поиск потенциально вредоносных сигнатур в теле файла
     */
    private function scanForMaliciousSignatures(string $path, string $lang): void
    {
        clearstatcache(true, $path);
        $fileSize = @filesize($path) ?: 0;
        $combined = '';

        if ($fileSize <= 2 * 1024 * 1024) {
            $combined = (string)@file_get_contents($path);
        } else {
            $fp = @fopen($path, 'rb');
            if ($fp) {
                $headerChunk = (string)fread($fp, 524288);
                fseek($fp, max(0, $fileSize - 524288), SEEK_SET);
                $footerChunk = (string)fread($fp, 524288);
                fclose($fp);
                $combined = $headerChunk . "\n" . $footerChunk;
            }
        }

        foreach (self::DANGEROUS_STRINGS as $badString) {
            if (stripos($combined, $badString) !== false) {
                throw new RuntimeException($lang === 'ru' 
                    ? 'Файл отклонен: в содержимом обнаружен запрещенный скриптовый код или PHP/HTML тег' 
                    : 'Файл қабылданбады: мазмұнында тыйым салынған скрипт немесе PHP/HTML коды табылды');
            }
        }
    }

    /**
     * Полное пересохранение изображения через GD:
     * Очищает абсолютно все метаданные, комментарии, EXIF, GPS, трейлеры и скрытый мусор.
     */
    private function reencodeImage(
        string $sourcePath, 
        string $destPath, 
        int $imageType, 
        int $orientation, 
        string $ext
    ): void {
        $srcImage = match ($imageType) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG  => @imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : null,
            default => null,
        };

        if (!$srcImage) {
            throw new RuntimeException('Не удалось декодировать графический файл');
        }

        // Применяем EXIF-поворот, если требуется
        if ($orientation > 1) {
            $degrees = match ($orientation) {
                3 => 180,
                6 => -90,
                8 => 90,
                default => 0,
            };
            if ($degrees !== 0) {
                $rotated = imagerotate($srcImage, $degrees, 0);
                if ($rotated !== false) {
                    imagedestroy($srcImage);
                    $srcImage = $rotated;
                }
            }
        }

        $w = imagesx($srcImage);
        $h = imagesy($srcImage);

        // Создаем абсолютно новое полотно TrueColor
        $cleanCanvas = imagecreatetruecolor($w, $h);

        // Обработка прозрачности для PNG и WebP
        if ($ext === 'png' || $ext === 'webp') {
            imagealphablending($cleanCanvas, false);
            imagesavealpha($cleanCanvas, true);
            $transparent = imagecolorallocatealpha($cleanCanvas, 255, 255, 255, 127);
            imagefilledrectangle($cleanCanvas, 0, 0, $w, $h, $transparent);
        } else {
            // Белая подложка для JPEG
            $white = imagecolorallocate($cleanCanvas, 255, 255, 255);
            imagefilledrectangle($cleanCanvas, 0, 0, $w, $h, $white);
        }

        // Копируем пиксели без метаданных
        imagecopy($cleanCanvas, $srcImage, 0, 0, 0, 0, $w, $h);
        imagedestroy($srcImage);

        // Сохраняем в целевой файл
        $saved = match ($ext) {
            'png'  => imagepng($cleanCanvas, $destPath, 8),
            'webp' => imagewebp($cleanCanvas, $destPath, 88),
            default => imagejpeg($cleanCanvas, $destPath, 88),
        };

        imagedestroy($cleanCanvas);

        if (!$saved || !file_exists($destPath)) {
            throw new RuntimeException('Не удалось пересохранить чистое изображение');
        }
    }

    /**
     * Генерация случайного безопасного UUID v4
     */
    private function generateUuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant RFC 4122
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Фиксация операции в таблице audit_logs
     */
    private function logUploadAudit(string $fileName, ?int $personId, ?int $userId, int $size, int $w, int $h): void
    {
        try {
            $db = Database::getConnection();
            $stmtTables = $db->query("SHOW TABLES LIKE 'audit_logs'");
            if ($stmtTables->rowCount() === 0) {
                return;
            }

            $user = Auth::user();
            $uId = $userId ?: ($user['id'] ?? null);
            $uName = $user['name'] ?? 'Система';
            $uEmail = $user['email'] ?? null;

            $summary = "Загрузка фото: {$fileName} ({$w}x{$h}, " . round($size / 1024, 1) . " КБ)";
            if ($personId) {
                $summary .= " для персоны ID {$personId}";
            }

            $stmt = $db->prepare("
                INSERT INTO `audit_logs` 
                (`user_id`, `user_name`, `user_email`, `action`, `entity_table`, `entity_id`, `summary`, `new_values`, `created_at`)
                VALUES (?, ?, ?, 'CREATE', 'person_media', ?, ?, ?, NOW())
            ");

            $newVals = json_encode([
                'file_name' => $fileName,
                'file_size' => $size,
                'width'     => $w,
                'height'    => $h,
                'person_id' => $personId,
            ], JSON_UNESCAPED_UNICODE);

            $stmt->execute([$uId, $uName, $uEmail, $personId, $summary, $newVals]);
        } catch (\Throwable $e) {
            // Логирование аудита не должно ломать основную операцию
            error_log('Ошибка записи в audit_logs при загрузке фото: ' . $e->getMessage());
        }
    }
}
