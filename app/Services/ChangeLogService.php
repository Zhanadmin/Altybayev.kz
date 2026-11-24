<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * ALT-004: журнал изменений (поверх audit_logs).
 * Читает записи аудита, строит «поле: было → стало», ссылки на объект и план отката.
 * Записей не меняет и не удаляет — только добавляет строки ROLLBACK через HistoryController.
 */
class ChangeLogService
{
    public const PER_PAGE = 50;

    /** Таблицы, для которых разрешён откат правки (UPDATE/DELETE). */
    public const ROLLBACK_TABLES = ['persons', 'marriages', 'person_media', 'clans', 'albums', 'events', 'album_media'];

    /** Служебные поля — в «было → стало» не показываем. */
    private const HIDDEN = ['id', 'created_at', 'updated_at', 'created_by', 'import_batch_id', 'gedcom_id', 'clan_id',
        'cover_pos_y', 'cover_dim', 'cover_path'];

    private const LABELS = [
        'first_name'   => ['ru' => 'Имя', 'kk' => 'Аты'],
        'last_name'    => ['ru' => 'Фамилия', 'kk' => 'Тегі'],
        'middle_name'  => ['ru' => 'Отчество', 'kk' => 'Әкесінің аты'],
        'maiden_name'  => ['ru' => 'Девичья фамилия', 'kk' => 'Қыз тегі'],
        'gender'       => ['ru' => 'Пол', 'kk' => 'Жынысы'],
        'father_id'    => ['ru' => 'Отец', 'kk' => 'Әкесі'],
        'mother_id'    => ['ru' => 'Мать', 'kk' => 'Анасы'],
        'birth_date'   => ['ru' => 'Дата рождения', 'kk' => 'Туған күні'],
        'birth_year'   => ['ru' => 'Год рождения', 'kk' => 'Туған жылы'],
        'birth_place'  => ['ru' => 'Место рождения', 'kk' => 'Туған жері'],
        'is_deceased'  => ['ru' => 'Умер(ла)', 'kk' => 'Қайтыс болған'],
        'death_date'   => ['ru' => 'Дата смерти', 'kk' => 'Қайтыс болған күні'],
        'death_year'   => ['ru' => 'Год смерти', 'kk' => 'Қайтыс болған жылы'],
        'death_place'  => ['ru' => 'Место смерти', 'kk' => 'Қайтыс болған жері'],
        'clan_ru'      => ['ru' => 'Ру', 'kk' => 'Руы'],
        'clan_subru'   => ['ru' => 'Ветвь ру', 'kk' => 'Тармағы'],
        'biography'    => ['ru' => 'Биография', 'kk' => 'Өмірбаяны'],
        'avatar_path'  => ['ru' => 'Главное фото', 'kk' => 'Басты фото'],
        'private_contacts' => ['ru' => 'Контакты', 'kk' => 'Байланыс'],
        'is_deleted'   => ['ru' => 'Удалён', 'kk' => 'Жойылған'],
        'status'       => ['ru' => 'Статус брака', 'kk' => 'Неке мәртебесі'],
        'start_date'   => ['ru' => 'Начало брака', 'kk' => 'Некенің басы'],
        'marriage_date' => ['ru' => 'Дата брака', 'kk' => 'Неке күні'],
        'end_date'     => ['ru' => 'Конец брака', 'kk' => 'Неке соңы'],
        'divorce_date' => ['ru' => 'Дата развода', 'kk' => 'Ажырасу күні'],
        'is_current'   => ['ru' => 'Действующий', 'kk' => 'Қолданыстағы'],
        'marriage_place' => ['ru' => 'Место брака', 'kk' => 'Неке жері'],
        'husband_id'   => ['ru' => 'Муж', 'kk' => 'Күйеуі'],
        'wife_id'      => ['ru' => 'Жена', 'kk' => 'Әйелі'],
        'notes'        => ['ru' => 'Примечание', 'kk' => 'Ескертпе'],
        'title'        => ['ru' => 'Название', 'kk' => 'Атауы'],
        'description'  => ['ru' => 'Описание', 'kk' => 'Сипаттама'],
        'caption'      => ['ru' => 'Подпись', 'kk' => 'Сипаттама'],
        'album_date'   => ['ru' => 'Дата альбома', 'kk' => 'Альбом күні'],
        'event_date'   => ['ru' => 'Дата мероприятия', 'kk' => 'Іс-шара күні'],
        'taken_date'   => ['ru' => 'Дата съёмки', 'kk' => 'Түсірілген күні'],
        'name'         => ['ru' => 'Название', 'kk' => 'Атауы'],
        'name_ru'      => ['ru' => 'Название (рус.)', 'kk' => 'Атауы (орыс.)'],
        'summary'      => ['ru' => 'Описание', 'kk' => 'Сипаттама'],
        'tamga_file'   => ['ru' => 'Таңба', 'kk' => 'Таңба'],
        'zhuz'         => ['ru' => 'Жуз', 'kk' => 'Жүз'],
        'parent_id'    => ['ru' => 'Родительский ру', 'kk' => 'Бас ру'],
    ];

    private const TABLES = [
        'persons'      => ['ru' => 'Человек', 'kk' => 'Адам'],
        'marriages'    => ['ru' => 'Семья / брак', 'kk' => 'Отбасы / неке'],
        'person_media' => ['ru' => 'Фото / материал', 'kk' => 'Фото / материал'],
        'album_media'  => ['ru' => 'Фото в альбоме', 'kk' => 'Альбомдағы фото'],
        'albums'       => ['ru' => 'Альбом', 'kk' => 'Альбом'],
        'events'       => ['ru' => 'Мероприятие', 'kk' => 'Іс-шара'],
        'clans'        => ['ru' => 'Ру / таңба', 'kk' => 'Ру / таңба'],
    ];

    private PDO $db;
    private string $lang;

    public function __construct(PDO $db, string $lang = 'ru')
    {
        $this->db = $db;
        $this->lang = $lang === 'kk' ? 'kk' : 'ru';
    }

    public function isReady(): bool
    {
        try {
            return $this->db->query("SHOW TABLES LIKE 'audit_logs'")->rowCount() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function tableLabels(string $lang): array
    {
        $l = $lang === 'kk' ? 'kk' : 'ru';
        $out = [];
        foreach (self::TABLES as $k => $v) {
            $out[$k] = $v[$l];
        }
        return $out;
    }

    /** Пользователи, у которых есть записи (для фильтра). */
    public function users(): array
    {
        $st = $this->db->query(
            "SELECT a.user_id, COALESCE(MAX(u.name), MAX(a.user_name), CONCAT('#', a.user_id)) AS name
               FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
              WHERE a.user_id IS NOT NULL GROUP BY a.user_id ORDER BY name"
        );
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Фильтры: user (id), date_from, date_to (Y-m-d), person (id), action, table, q (поиск по имени).
     * @return array{rows: array, total: int}
     */
    public function search(array $f, int $page = 1, int $perPage = self::PER_PAGE): array
    {
        [$from, $p] = $this->build($f, true);

        $st = $this->db->prepare("SELECT COUNT(*) $from");
        $st->execute($p);
        $total = (int)$st->fetchColumn();

        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT a.*, COALESCE(u.name, a.user_name) AS who,
                       TRIM(CONCAT_WS(' ', p.last_name, p.first_name, p.middle_name)) AS person_name,
                       p.is_deleted AS person_is_deleted,
                       m.husband_id, m.wife_id,
                       TRIM(CONCAT_WS(' ', hp.last_name, hp.first_name)) AS husband_name,
                       TRIM(CONCAT_WS(' ', wp.last_name, wp.first_name)) AS wife_name,
                       pm.person_id AS media_person_id,
                       EXISTS(SELECT 1 FROM audit_logs r
                               WHERE r.action = 'ROLLBACK' AND r.entity_table = a.entity_table AND r.entity_id = a.entity_id
                                 AND (r.summary LIKE CONCAT('%#', a.id, ' для%') OR r.summary LIKE CONCAT('%#', a.id, ':%'))) AS rolled_back
                $from ORDER BY a.id DESC LIMIT " . (int)$perPage . ' OFFSET ' . (int)$offset;
        $st = $this->db->prepare($sql);
        $st->execute($p);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return ['rows' => $this->enrich($rows), 'total' => $total];
    }

    /** ALT-024: виды действий для вкладок. */
    public const KINDS = ['create', 'update', 'delete', 'restore', 'account'];

    /** SQL-условие вида действий. */
    private static function kindSql(string $kind): ?string
    {
        switch ($kind) {
            case 'create':  return "a.action = 'CREATE'";
            case 'update':  return "a.action = 'UPDATE'";
            case 'delete':  return "a.action = 'DELETE'";
            case 'restore': return "a.action IN ('RESTORE', 'ROLLBACK')";
            case 'account': return "a.action NOT IN ('CREATE', 'UPDATE', 'DELETE', 'RESTORE', 'ROLLBACK')";
        }
        return null;
    }

    /**
     * ALT-024: счётчики по видам при текущих остальных фильтрах (без учёта вида и действия).
     * @return array<string,int> ключи: all + KINDS
     */
    public function kindCounts(array $f): array
    {
        $f['kind'] = '';
        $f['action'] = '';
        [$from, $p] = $this->build($f, false);
        $st = $this->db->prepare("SELECT SUM(1) AS n_all,
                SUM(a.action = 'CREATE') AS n_create, SUM(a.action = 'UPDATE') AS n_update, SUM(a.action = 'DELETE') AS n_delete,
                SUM(a.action IN ('RESTORE', 'ROLLBACK')) AS n_restore,
                SUM(a.action NOT IN ('CREATE', 'UPDATE', 'DELETE', 'RESTORE', 'ROLLBACK')) AS n_account
                $from");
        $st->execute($p);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $out = ['all' => (int)($r['n_all'] ?? 0)];
        foreach (self::KINDS as $k) {
            $out[$k] = (int)($r['n_' . $k] ?? 0);
        }
        return $out;
    }

    /** @return array{0:string, 1:array} FROM ... WHERE и параметры */
    private function build(array $f, bool $withKind): array
    {
        $where = ['1=1'];
        $p = [];

        $user = (int)($f['user'] ?? 0);
        if ($user > 0) {
            $where[] = 'a.user_id = ?';
            $p[] = $user;
        }
        $from = (string)($f['date_from'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where[] = 'a.created_at >= ?';
            $p[] = $from . ' 00:00:00';
        }
        $to = (string)($f['date_to'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where[] = 'a.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
            $p[] = $to . ' 00:00:00';
        }
        $person = (int)($f['person'] ?? 0);
        if ($person > 0) {
            $where[] = "((a.entity_table = 'persons' AND a.entity_id = ?)
                     OR (a.entity_table = 'marriages' AND (m.husband_id = ? OR m.wife_id = ?))
                     OR (a.entity_table = 'person_media' AND pm.person_id = ?))";
            array_push($p, $person, $person, $person, $person);
        }
        $action = (string)($f['action'] ?? '');
        if ($action === 'MOVE') {
            // «перенёс» — у человека сменился отец или мать
            $where[] = "(a.action = 'UPDATE' AND a.entity_table = 'persons'
                     AND (NOT (JSON_EXTRACT(a.old_values, '\$.father_id') <=> JSON_EXTRACT(a.new_values, '\$.father_id'))
                       OR NOT (JSON_EXTRACT(a.old_values, '\$.mother_id') <=> JSON_EXTRACT(a.new_values, '\$.mother_id'))))";
        } elseif (in_array($action, ['CREATE', 'UPDATE', 'DELETE', 'ROLLBACK', 'IMPORT_GEDCOM', 'RESTORE'], true)) {
            $where[] = 'a.action = ?';
            $p[] = $action;
        }
        $kindSql = $withKind ? self::kindSql((string)($f['kind'] ?? '')) : null;
        if ($kindSql !== null) {
            $where[] = $kindSql;
        }
        $table = (string)($f['table'] ?? '');
        if ($table !== '' && isset(self::TABLES[$table])) {
            $where[] = 'a.entity_table = ?';
            $p[] = $table;
        }
        $q = trim((string)($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($q, 0, 80)) . '%';
            $where[] = "(COALESCE(u.name, a.user_name) LIKE ?
                     OR CONCAT_WS(' ', p.last_name, p.first_name, p.middle_name) LIKE ?
                     OR CONCAT_WS(' ', hp.last_name, hp.first_name) LIKE ?
                     OR CONCAT_WS(' ', wp.last_name, wp.first_name) LIKE ?
                     OR a.summary LIKE ?)";
            array_push($p, $like, $like, $like, $like, $like);
        }

        $from = "FROM audit_logs a
                 LEFT JOIN users u ON u.id = a.user_id
                 LEFT JOIN persons p ON a.entity_table = 'persons' AND p.id = a.entity_id
                 LEFT JOIN marriages m ON a.entity_table = 'marriages' AND m.id = a.entity_id
                 LEFT JOIN persons hp ON hp.id = m.husband_id
                 LEFT JOIN persons wp ON wp.id = m.wife_id
                 LEFT JOIN person_media pm ON a.entity_table = 'person_media' AND pm.id = a.entity_id
                 WHERE " . implode(' AND ', $where);

        return [$from, $p];
    }

    /** История одного человека (для карточки). */
    public function forPerson(int $personId, int $limit = 50): array
    {
        $r = $this->search(['person' => $personId], 1, $limit);
        return $r['rows'];
    }

    /** Добавляет к строкам: changes, verb, object_label, object_name, link, can_rollback. */
    private function enrich(array $rows): array
    {
        // имена людей из father_id / mother_id / husband_id / wife_id — одним запросом
        $ids = [];
        foreach ($rows as $r) {
            foreach (['old_values', 'new_values'] as $k) {
                $v = $this->decode($r[$k] ?? null);
                foreach (['father_id', 'mother_id', 'husband_id', 'wife_id'] as $fk) {
                    if (isset($v[$fk]) && is_numeric($v[$fk])) {
                        $ids[(int)$v[$fk]] = true;
                    }
                }
            }
        }
        $names = $this->personNames(array_keys($ids));

        foreach ($rows as &$r) {
            $old = $this->decode($r['old_values'] ?? null);
            $new = $this->decode($r['new_values'] ?? null);
            $r['changes'] = $this->diff((string)$r['action'], $old, $new, $names);
            $r['verb'] = $this->verb($r, $r['changes']);
            $r['object_label'] = self::TABLES[$r['entity_table']][$this->lang] ?? (string)$r['entity_table'];
            [$r['object_name'], $r['link']] = $this->objectOf($r, $old, $new);
            $r['can_rollback'] = empty($r['rolled_back'])
                && in_array($r['action'], ['UPDATE', 'DELETE', 'CREATE'], true)
                && ($r['action'] === 'CREATE' ? $r['entity_table'] === 'persons' : $this->rollbackPlan($r) !== null);
        }
        unset($r);
        return $rows;
    }

    private function decode($json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }
        $v = json_decode($json, true);
        return is_array($v) ? $v : [];
    }

    private function personNames(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->db->prepare("SELECT id, TRIM(CONCAT_WS(' ', last_name, first_name)) AS n FROM persons WHERE id IN ($in)");
        $st->execute($ids);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int)$r['id']] = $r['n'];
        }
        return $out;
    }

    private function label(string $key): string
    {
        return self::LABELS[$key][$this->lang] ?? $key;
    }

    private function fmt(string $key, $v, array $names): string
    {
        if ($v === null || $v === '') {
            return '—';
        }
        if (in_array($key, ['father_id', 'mother_id', 'husband_id', 'wife_id', 'parent_id'], true) && is_numeric($v)) {
            return $names[(int)$v] ?? ('#' . (int)$v);
        }
        if ($key === 'gender') {
            return ['M' => $this->lang === 'ru' ? 'мужской' : 'ер', 'F' => $this->lang === 'ru' ? 'женский' : 'әйел'][$v] ?? (string)$v;
        }
        if (in_array($key, ['is_deceased', 'is_deleted', 'is_current'], true)) {
            return ((int)$v === 1) ? ($this->lang === 'ru' ? 'да' : 'иә') : ($this->lang === 'ru' ? 'нет' : 'жоқ');
        }
        if ($key === 'private_contacts') {
            return $this->lang === 'ru' ? '(данные скрыты)' : '(деректер жасырылған)';
        }
        if (is_array($v)) {
            $v = json_encode($v, JSON_UNESCAPED_UNICODE);
        }
        $s = (string)$v;
        return mb_strlen($s) > 140 ? mb_substr($s, 0, 140) . '…' : $s;
    }

    /** @return array<int, array{field:string, key:string, old:string, new:string}> */
    private function diff(string $action, array $old, array $new, array $names): array
    {
        $out = [];
        $skip = static fn(string $k): bool => in_array($k, self::HIDDEN, true);

        if ($action === 'CREATE') {
            foreach ($new as $k => $v) {
                if ($skip((string)$k) || $v === null || $v === '' || $v === 0 || $v === '0') {
                    continue;
                }
                $out[] = ['field' => $this->label((string)$k), 'key' => (string)$k, 'old' => '—', 'new' => $this->fmt((string)$k, $v, $names)];
            }
            return $out;
        }

        // UPDATE / ROLLBACK / DELETE: сравниваем по ключам нового состояния (если его нет — старого)
        $keys = $new ? array_keys($new) : array_keys($old);
        foreach ($keys as $k) {
            $k = (string)$k;
            if ($skip($k)) {
                continue;
            }
            $ov = $old[$k] ?? null;
            $nv = $new ? ($new[$k] ?? null) : null;
            if ($new && (string)$ov === (string)$nv) {
                continue;
            }
            if (!$new && ($ov === null || $ov === '')) {
                continue; // удаление: показываем только заполненные поля прежней записи
            }
            $out[] = ['field' => $this->label($k), 'key' => $k, 'old' => $this->fmt($k, $ov, $names), 'new' => $this->fmt($k, $nv, $names)];
        }
        return $out;
    }

    private function verb(array $r, array $changes): string
    {
        $ru = $this->lang === 'ru';
        switch ($r['action']) {
            case 'CREATE':
                return $ru ? 'Добавил' : 'Қосты';
            case 'DELETE':
                return $ru ? 'Удалил' : 'Жойды';
            case 'ROLLBACK':
                return $ru ? 'Откатил' : 'Кері қайтарды';
            case 'IMPORT_GEDCOM':
                return $ru ? 'Импорт' : 'Импорт';
            case 'UPDATE':
                foreach ($changes as $c) {
                    if (in_array($c['key'], ['father_id', 'mother_id'], true)) {
                        return $ru ? 'Перенёс' : 'Көшірді';
                    }
                }
                return $ru ? 'Изменил' : 'Өзгертті';
        }
        return (string)$r['action'];
    }

    /** @return array{0:string, 1:?string} название объекта и ссылка на него в древе */
    private function objectOf(array $r, array $old, array $new): array
    {
        $id = (int)$r['entity_id'];
        $t = (string)$r['entity_table'];
        $name = '';
        $link = null;
        switch ($t) {
            case 'persons':
                $name = (string)($r['person_name'] ?? '');
                if ($name === '') {
                    $src = $old ?: $new;
                    $name = trim(($src['last_name'] ?? '') . ' ' . ($src['first_name'] ?? ''));
                }
                $link = '/person/' . $id;
                break;
            case 'marriages':
                $h = (string)($r['husband_name'] ?? '');
                $w = (string)($r['wife_name'] ?? '');
                $name = trim($h . ($h !== '' && $w !== '' ? ' + ' : '') . $w);
                if (!empty($r['husband_id'])) {
                    $link = '/person/' . (int)$r['husband_id'];
                }
                break;
            case 'person_media':
                if (!empty($r['media_person_id'])) {
                    $link = '/person/' . (int)$r['media_person_id'];
                }
                $name = (string)(($old ?: $new)['title'] ?? '');
                break;
            case 'albums':
                $name = (string)(($old ?: $new)['title'] ?? '');
                if ($r['action'] !== 'DELETE') {
                    $link = '/album/' . $id;
                }
                break;
            case 'events':
                $name = (string)(($old ?: $new)['title'] ?? '');
                if ($r['action'] !== 'DELETE') {
                    $link = '/event/' . $id;
                }
                break;
            case 'clans':
                $name = (string)(($old ?: $new)['name'] ?? '');
                $link = '/admin/clans';
                break;
        }
        if ($name === '') {
            $name = $t . ' #' . $id;
        }
        return [$name, $link];
    }

    /**
     * План отката: какие колонки вернуть. null — откатить нельзя.
     * UPDATE — только реально изменённые поля (чтобы не затереть более поздние правки других полей).
     * @return array{table:string, id:int, values:array<string,mixed>}|null
     */
    public function rollbackPlan(array $log): ?array
    {
        $table = (string)$log['entity_table'];
        $action = (string)$log['action'];
        if (!in_array($table, self::ROLLBACK_TABLES, true) || !in_array($action, ['UPDATE', 'DELETE'], true)) {
            return null;
        }
        $old = $this->decode($log['old_values'] ?? null);
        $new = $this->decode($log['new_values'] ?? null);
        if (!$old) {
            return null;
        }
        if ($action === 'DELETE' && !$new) {
            return null; // запись удалена физически — вернуть нечем
        }
        $values = [];
        $keys = $new ? array_keys($new) : array_keys($old);
        foreach ($keys as $k) {
            $k = (string)$k;
            if (in_array($k, ['id', 'created_at', 'updated_at', 'created_by'], true) || !array_key_exists($k, $old)) {
                continue;
            }
            if ($new && (string)($old[$k] ?? '') === (string)($new[$k] ?? '')) {
                continue;
            }
            $values[$k] = $old[$k];
        }
        if (!$values) {
            return null;
        }
        // только реально существующие колонки
        try {
            $cols = array_column($this->db->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC), 'Field');
        } catch (\Throwable $e) {
            return null;
        }
        $values = array_intersect_key($values, array_flip($cols));
        return $values ? ['table' => $table, 'id' => (int)$log['entity_id'], 'values' => $values] : null;
    }
}
