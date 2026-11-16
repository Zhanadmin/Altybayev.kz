<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * TASK-030: справочник ру («Ру таңбалары»).
 * Использует существующую таблицу `clans`; после миграции 2026_11_17_clans_tamga_registry.sql
 * в ней есть name_ru, summary, tamga_file. До миграции сервис работает «вхолостую» (hasRegistry() = false).
 */
class ClanService
{
    public const UPLOAD_DIR_REL = '/uploads/tamga/';
    public const MAX_FILE_SIZE = 512 * 1024; // 512 КБ
    public const MAX_DIMENSION = 1024;
    public const ZHUZ = ['senior', 'middle', 'junior', 'other'];

    private const SVG_ELEMENTS = [
        'svg', 'g', 'path', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'rect', 'defs', 'title', 'desc',
    ];
    private const SVG_ATTRS = [
        'xmlns', 'version', 'viewbox', 'width', 'height', 'x', 'y', 'cx', 'cy', 'r', 'rx', 'ry', 'x1', 'y1', 'x2', 'y2',
        'd', 'points', 'transform', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin',
        'stroke-miterlimit', 'stroke-dasharray', 'stroke-opacity', 'fill-opacity', 'opacity', 'fill-rule',
        'clip-rule', 'preserveaspectratio',
    ];

    private PDO $db;
    private string $uploadDir;
    private static ?bool $hasRegistry = null;

    public function __construct(?PDO $db = null, ?string $uploadDir = null)
    {
        $this->db = $db ?? Database::getConnection();
        $this->uploadDir = $uploadDir ?? (__DIR__ . '/../../public/uploads/tamga');
    }

    /** Есть ли в таблице clans колонки справочника (миграция применена). */
    public function hasRegistry(): bool
    {
        if (self::$hasRegistry === null) {
            try {
                self::$hasRegistry = $this->db->query("SHOW COLUMNS FROM `clans` LIKE 'tamga_file'")->rowCount() > 0;
            } catch (\Throwable $e) {
                self::$hasRegistry = false;
            }
        }
        return self::$hasRegistry;
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        $sql = "SELECT c.*, (SELECT COUNT(*) FROM persons p WHERE p.clan_id = c.id AND p.is_deleted = 0) AS persons_count
                FROM clans c ORDER BY c.name ASC";
        $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['tamga_url'] = $this->urlFor($r['tamga_file'] ?? null);
        }
        return $rows;
    }

    public function find(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM clans WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            return null;
        }
        $r['tamga_url'] = $this->urlFor($r['tamga_file'] ?? null);
        return $r;
    }

    /**
     * Карта «название ру (қаз/рус, в нижнем регистре) → URL таңба» для значков рядом с ру.
     * @return array<string,string>
     */
    public function tamgaMap(): array
    {
        if (!$this->hasRegistry()) {
            return [];
        }
        $map = [];
        $rows = $this->db->query("SELECT name, name_ru, tamga_file FROM clans WHERE tamga_file IS NOT NULL AND tamga_file <> ''")
            ->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $url = $this->urlFor($r['tamga_file']);
            foreach ([$r['name'] ?? '', $r['name_ru'] ?? ''] as $n) {
                $n = mb_strtolower(trim((string)$n));
                if ($n !== '') {
                    $map[$n] = $url;
                }
            }
        }
        return $map;
    }

    /**
     * Найти ру в справочнике по названию (қаз или рус, без учёта регистра).
     * @return array{id:int,name:string}|null
     */
    public function resolveByName(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $st = $this->db->prepare('SELECT id, name FROM clans WHERE LOWER(name) = LOWER(?) OR LOWER(name_ru) = LOWER(?) LIMIT 1');
        $st->execute([$name, $name]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ? ['id' => (int)$r['id'], 'name' => (string)$r['name']] : null;
    }

    public function urlFor(?string $file): ?string
    {
        $file = (string)$file;
        if ($file === '' || !preg_match('/^[A-Za-z0-9_\-]+\.(svg|png)$/', $file)) {
            return null;
        }
        return self::UPLOAD_DIR_REL . $file;
    }

    /**
     * Создать/обновить ру. $id = 0 — создать.
     * @return int id
     */
    public function save(int $id, array $in): int
    {
        $name = trim((string)($in['name'] ?? ''));
        $nameRu = trim((string)($in['name_ru'] ?? ''));
        $zhuz = (string)($in['zhuz'] ?? 'other');
        $summary = trim((string)($in['summary'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            throw new RuntimeException('Ру атауы міндетті (≤100) / Название ру обязательно (≤100)');
        }
        if (mb_strlen($nameRu) > 100) {
            throw new RuntimeException('Орыс атауы тым ұзын / Русское название слишком длинное');
        }
        if (!in_array($zhuz, self::ZHUZ, true)) {
            $zhuz = 'other';
        }
        if (mb_strlen($summary) > 255) {
            $summary = mb_substr($summary, 0, 255);
        }
        $nameRu = $nameRu !== '' ? $nameRu : null;
        $summary = $summary !== '' ? $summary : null;

        $dup = $this->db->prepare('SELECT id FROM clans WHERE LOWER(name) = LOWER(?) AND id <> ? LIMIT 1');
        $dup->execute([$name, $id]);
        if ($dup->fetchColumn()) {
            throw new RuntimeException('Мұндай ру бар / Такой ру уже есть');
        }

        if ($id > 0) {
            if (!$this->find($id)) {
                throw new RuntimeException('Ру табылмады / Ру не найден');
            }
            $this->db->prepare('UPDATE clans SET name = ?, name_ru = ?, zhuz = ?, summary = ? WHERE id = ?')
                ->execute([$name, $nameRu, $zhuz, $summary, $id]);
            return $id;
        }
        $this->db->prepare('INSERT INTO clans (name, name_ru, zhuz, summary) VALUES (?, ?, ?, ?)')
            ->execute([$name, $nameRu, $zhuz, $summary]);
        return (int)$this->db->lastInsertId();
    }

    /** Удаление — только ру без людей. */
    public function delete(int $id): void
    {
        $c = $this->find($id);
        if (!$c) {
            throw new RuntimeException('Ру табылмады / Ру не найден');
        }
        $st = $this->db->prepare('SELECT COUNT(*) FROM persons WHERE clan_id = ? AND is_deleted = 0');
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) {
            throw new RuntimeException('Бұл ру адамдарға байланған, өшіруге болмайды / К роду привязаны люди — удалить нельзя');
        }
        $this->removeFile($c['tamga_file'] ?? null);
        $this->db->prepare('DELETE FROM clans WHERE id = ?')->execute([$id]);
    }

    /** Сохранить таңба из $_FILES-элемента (SVG или PNG). */
    public function saveTamgaUpload(int $id, array $file): string
    {
        $c = $this->find($id);
        if (!$c) {
            throw new RuntimeException('Ру табылмады / Ру не найден');
        }
        if (!isset($file['error']) || is_array($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Файл жүктелмеді / Ошибка загрузки файла');
        }
        $tmp = (string)$file['tmp_name'];
        if (!is_file($tmp)) {
            throw new RuntimeException('Уақытша файл жоқ / Временный файл не найден');
        }
        $data = (string)file_get_contents($tmp, false, null, 0, self::MAX_FILE_SIZE + 1);
        $name = $this->storeBytes($id, $data);
        $this->db->prepare('UPDATE clans SET tamga_file = ? WHERE id = ?')->execute([$name, $id]);
        $this->removeFile($c['tamga_file'] ?? null, $name);
        return $name;
    }

    public function deleteTamga(int $id): void
    {
        $c = $this->find($id);
        if (!$c) {
            throw new RuntimeException('Ру табылмады / Ру не найден');
        }
        $this->db->prepare('UPDATE clans SET tamga_file = NULL WHERE id = ?')->execute([$id]);
        $this->removeFile($c['tamga_file'] ?? null);
    }

    /** Проверка типа/размера, очистка SVG, запись на диск. Возвращает имя файла. */
    public function storeBytes(int $id, string $data): string
    {
        $len = strlen($data);
        if ($len === 0 || $len > self::MAX_FILE_SIZE) {
            throw new RuntimeException('Файл тым үлкен (≤512 КБ) / Файл слишком большой (до 512 КБ)');
        }
        if (substr($data, 0, 8) === "\x89PNG\r\n\x1a\n") {
            $content = $this->cleanPng($data);
            $ext = 'png';
        } elseif (stripos($data, '<svg') !== false) {
            $content = self::sanitizeSvg($data);
            $ext = 'svg';
        } else {
            throw new RuntimeException('Тек SVG немесе PNG / Разрешены только SVG и PNG');
        }
        if (!is_dir($this->uploadDir) && !@mkdir($this->uploadDir, 0755, true) && !is_dir($this->uploadDir)) {
            throw new RuntimeException('Қалтаға жазу мүмкін емес / Нельзя создать папку загрузки');
        }
        $name = 'clan_' . $id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (file_put_contents($this->uploadDir . DIRECTORY_SEPARATOR . $name, $content) === false) {
            throw new RuntimeException('Файлды сақтау қатесі / Не удалось сохранить файл');
        }
        return $name;
    }

    private function cleanPng(string $data): string
    {
        $info = @getimagesizefromstring($data);
        if ($info === false || $info[2] !== IMAGETYPE_PNG) {
            throw new RuntimeException('PNG бүлінген / PNG повреждён');
        }
        if ($info[0] > self::MAX_DIMENSION || $info[1] > self::MAX_DIMENSION || $info[0] < 1 || $info[1] < 1) {
            throw new RuntimeException('Сурет өлшемі ≤1024 px / Размер изображения до 1024 px');
        }
        if (!function_exists('imagecreatefromstring')) {
            return $data; // нет GD — PNG по сигнатуре и getimagesize уже проверен
        }
        $im = @imagecreatefromstring($data);
        if ($im === false) {
            throw new RuntimeException('PNG бүлінген / PNG повреждён');
        }
        imagealphablending($im, false);
        imagesavealpha($im, true);
        ob_start();
        imagepng($im);
        $out = (string)ob_get_clean();
        imagedestroy($im);
        return $out;
    }

    /**
     * Очистка SVG по белому списку элементов и атрибутов (без script, style, foreignObject, use, image, on*, href, внешних ссылок).
     * @throws RuntimeException если файл не разбирается как SVG
     */
    public static function sanitizeSvg(string $raw): string
    {
        if (preg_match('/<!(DOCTYPE|ENTITY)/i', $raw)) {
            throw new RuntimeException('SVG-да DOCTYPE/ENTITY рұқсат етілмейді / В SVG запрещены DOCTYPE и ENTITY');
        }
        $prev = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $ok = $doc->loadXML($raw, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok || !$doc->documentElement || strtolower($doc->documentElement->localName) !== 'svg') {
            throw new RuntimeException('SVG дұрыс емес / Некорректный SVG');
        }

        self::cleanNode($doc->documentElement);
        $root = $doc->documentElement;
        $root->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        $out = $doc->saveXML($root);
        if ($out === false || strlen($out) > self::MAX_FILE_SIZE) {
            throw new RuntimeException('SVG өңделмеді / Не удалось обработать SVG');
        }
        return $out;
    }

    private static function cleanNode(\DOMElement $el): void
    {
        // атрибуты
        $remove = [];
        foreach ($el->attributes as $attr) {
            $n = strtolower($attr->nodeName);
            $v = (string)$attr->nodeValue;
            if ($n === 'xmlns') {
                continue;
            }
            $bad = !in_array($n, self::SVG_ATTRS, true)
                || preg_match('/(javascript|data:|url\s*\(|expression|<|@import)/i', $v);
            if ($bad) {
                $remove[] = $attr->nodeName;
            }
        }
        foreach ($remove as $n) {
            $el->removeAttribute($n);
        }
        // дети
        $kids = [];
        foreach ($el->childNodes as $c) {
            $kids[] = $c;
        }
        foreach ($kids as $c) {
            if ($c instanceof \DOMElement) {
                if (!in_array(strtolower($c->localName), self::SVG_ELEMENTS, true)) {
                    $el->removeChild($c);
                } else {
                    self::cleanNode($c);
                }
            } elseif (!($c instanceof \DOMText)) {
                $el->removeChild($c); // комментарии, PI, CDATA
            }
        }
    }

    private function removeFile(?string $file, ?string $keep = null): void
    {
        $file = (string)$file;
        if ($file === '' || $file === $keep || !preg_match('/^[A-Za-z0-9_\-]+\.(svg|png)$/', $file)) {
            return;
        }
        $p = $this->uploadDir . DIRECTORY_SEPARATOR . $file;
        if (is_file($p)) {
            @unlink($p);
        }
    }
}
