<?php

declare(strict_types=1);

namespace App\Services;

/**
 * ALT-084: поколения считаются «на лету» от живых.
 * Первое поколение — самое старшее, где есть хотя бы один живой; всё выше — «ancestors».
 * Относительные уровни строятся от опорной персоны (родители опоры = уровень 1),
 * вверх — уровни 0, -1, ...; вниз — +1; супруги — уровень мужа/жены.
 */
class GenerationService
{
    public static function isAlive(array $p): bool
    {
        return empty($p['is_deceased'])
            && empty($p['death_date'])
            && empty($p['death_year']);
    }

    /**
     * Сырые уровни (могут быть <= 0). Пусто, если опорной точки нет.
     *
     * @param array<int|string,array> $byId        id => строка персоны
     * @param array<int|string,array> $spouseLists id => [['spouse_id'=>..], ...]
     * @return array<int,int>
     */
    public static function rawLevels(array $byId, array $spouseLists, int $anchorId): array
    {
        $anchor = $byId[$anchorId] ?? null;
        if (!$anchor) {
            return [];
        }
        $level = [];
        foreach (['father_id', 'mother_id'] as $pk) {
            $pid = (int)($anchor[$pk] ?? 0);
            if ($pid > 0 && isset($byId[$pid]) && !isset($level[$pid])) {
                $level[$pid] = 1;
            }
        }
        if (!$level) {
            return [];
        }

        do {
            $changed = false;
            // вниз и к супругам до насыщения
            do {
                $step = false;
                foreach ($byId as $oid => $o) {
                    $oid = (int)$oid;
                    if (isset($level[$oid])) {
                        continue;
                    }
                    $best = null;
                    foreach (['father_id', 'mother_id'] as $pk) {
                        $pid = (int)($o[$pk] ?? 0);
                        if ($pid > 0 && isset($level[$pid])) {
                            $best = $best === null ? $level[$pid] + 1 : max($best, $level[$pid] + 1);
                        }
                    }
                    if ($best !== null) {
                        $level[$oid] = $best;
                        $step = true;
                        $changed = true;
                    }
                }
                foreach ($spouseLists as $mid => $mlist) {
                    $mid = (int)$mid;
                    if (!isset($level[$mid])) {
                        continue;
                    }
                    foreach ($mlist as $mm) {
                        $sid = (int)($mm['spouse_id'] ?? 0);
                        if ($sid > 0 && isset($byId[$sid]) && !isset($level[$sid])) {
                            $level[$sid] = $level[$mid];
                            $step = true;
                            $changed = true;
                        }
                    }
                }
            } while ($step);

            // на один шаг вверх: неразмеченные родители размеченных людей
            $up = [];
            foreach ($level as $id => $lv) {
                foreach (['father_id', 'mother_id'] as $pk) {
                    $pid = (int)($byId[$id][$pk] ?? 0);
                    if ($pid > 0 && isset($byId[$pid]) && !isset($level[$pid])) {
                        $up[$pid] = isset($up[$pid]) ? min($up[$pid], $lv - 1) : $lv - 1;
                    }
                }
            }
            foreach ($up as $pid => $lv) {
                $level[$pid] = $lv;
                $changed = true;
            }
        } while ($changed);

        return $level;
    }

    /**
     * Карта id => 'ancestors' | int (1, 2, 3, ...). Пусто, если нет опорной точки или живых.
     *
     * @return array<int,int|string>
     */
    public static function generationMap(array $byId, array $spouseLists, int $anchorId): array
    {
        $level = self::rawLevels($byId, $spouseLists, $anchorId);
        if (!$level) {
            return [];
        }
        $minAlive = null;
        foreach ($level as $id => $lv) {
            if (isset($byId[$id]) && self::isAlive($byId[$id])) {
                $minAlive = $minAlive === null ? $lv : min($minAlive, $lv);
            }
        }
        if ($minAlive === null) {
            return [];
        }
        $map = [];
        foreach ($level as $id => $lv) {
            $g = $lv - $minAlive + 1;
            $map[(int)$id] = $g >= 1 ? $g : 'ancestors';
        }
        return $map;
    }

    /** Заголовок группы поколения. */
    public static function group(int|string $key): array
    {
        if ($key === 'ancestors') {
            return ['key' => 'ancestors', 'title_kk' => 'Ата-бабалар', 'title_ru' => 'Предки', 'badge' => '🏛️'];
        }
        $n = (int)$key;
        $badge = $n === 1 ? '🌳' : ($n === 2 ? '🌿' : '🌱');
        return ['key' => $n, 'title_kk' => $n . '-ұрпақ', 'title_ru' => $n . '-е поколение', 'badge' => $badge];
    }
}
