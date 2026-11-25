<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\GenerationService;
use App\Services\PersonContactService;
use PDO;

/**
 * ALT-098: «Толтырылмаған / Что не заполнено» — у кого каких данных не хватает.
 * Только админ и редактор (проверка и в маршруте, и здесь). Только чтение.
 */
class MissingDataController
{
    /** Виды пробелов (порядок = порядок счётчиков) */
    public const GAPS = ['birth', 'photo', 'clan', 'father', 'mother', 'gender', 'marriage', 'death', 'maiden', 'contacts', 'zheti'];

    public function index(Request $request): void
    {
        if (!Auth::isEditor()) {
            Response::redirect('/?error=forbidden');
        }

        $db = Database::getConnection();
        $rows = $db->query('SELECT * FROM persons WHERE is_deleted = 0 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $byId = [];
        foreach ($rows as $r) {
            $byId[(int)$r['id']] = $r;
        }

        // Браки: по ним — дата брака и девичья фамилия
        $spouseLists = [];
        $marriageGaps = []; // person_id => 'none' | 'year'
        try {
            $mRows = $db->query('SELECT * FROM marriages')->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $mRows = [];
        }
        foreach ($mRows as $m) {
            $h = (int)($m['husband_id'] ?? 0);
            $w = (int)($m['wife_id'] ?? 0);
            if (!isset($byId[$h]) || !isset($byId[$w])) {
                continue;
            }
            $spouseLists[$h][] = ['spouse_id' => $w];
            $spouseLists[$w][] = ['spouse_id' => $h];
            $date = trim((string)($m['marriage_date'] ?? $m['start_date'] ?? ''));
            $state = null;
            if ($date === '') {
                $state = 'none';
            } elseif (preg_match('/^\D*\d{4}\D*$/u', $date)) {
                $state = 'year';
            }
            if ($state !== null) {
                foreach ([$h, $w] as $pid) {
                    if (($marriageGaps[$pid] ?? '') !== 'none') {
                        $marriageGaps[$pid] = $state;
                    }
                }
            }
        }

        $genMap = [];
        try {
            $genMap = GenerationService::generationMap($byId, $spouseLists, 6);
        } catch (\Throwable $e) {
            $genMap = [];
        }

        $items = [];
        $counts = array_fill_keys(self::GAPS, 0);
        $generations = [];
        foreach ($byId as $id => $p) {
            $gaps = [];
            $detail = [];

            if (trim((string)($p['birth_date'] ?? '')) === '' && trim((string)($p['birth_year'] ?? '')) === '') {
                $gaps[] = 'birth';
                $detail['birth'] = 'none';
            } elseif (trim((string)($p['birth_date'] ?? '')) === '') {
                $gaps[] = 'birth';
                $detail['birth'] = 'year';
            }
            if (trim((string)($p['avatar_path'] ?? '')) === '') {
                $gaps[] = 'photo';
            }
            if (trim((string)($p['clan_ru'] ?? '')) === '') {
                $gaps[] = 'clan';
            }
            if (empty($p['father_id'])) {
                $gaps[] = 'father';
            }
            if (empty($p['mother_id'])) {
                $gaps[] = 'mother';
            }
            if (!in_array($p['gender'] ?? '', ['M', 'F'], true)) {
                $gaps[] = 'gender';
            }
            if (isset($marriageGaps[$id])) {
                $gaps[] = 'marriage';
                $detail['marriage'] = $marriageGaps[$id];
            }
            if (!empty($p['is_deceased']) && trim((string)($p['death_date'] ?? '')) === '' && trim((string)($p['death_year'] ?? '')) === '') {
                $gaps[] = 'death';
            }
            if (($p['gender'] ?? '') === 'F' && !empty($spouseLists[$id]) && trim((string)($p['maiden_name'] ?? '')) === '') {
                $gaps[] = 'maiden';
            }
            if (empty($p['is_deceased'])) {
                $c = PersonContactService::decodeContacts($p['private_contacts'] ?? null);
                if (!$c['phones'] && !$c['emails'] && !$c['addresses'] && !$c['socials']) {
                    $gaps[] = 'contacts';
                }
            }
            // Жеті ата: цепочка отцов короче семи (сам человек не считается)
            $depth = 0;
            $seen = [$id => true];
            $cur = $p;
            while ($depth < 7 && !empty($cur['father_id']) && isset($byId[(int)$cur['father_id']]) && !isset($seen[(int)$cur['father_id']])) {
                $cur = $byId[(int)$cur['father_id']];
                $seen[(int)$cur['id']] = true;
                $depth++;
            }
            if ($depth < 7) {
                $gaps[] = 'zheti';
                $detail['zheti'] = (string)$depth;
            }

            foreach ($gaps as $g) {
                $counts[$g]++;
            }
            $gen = $genMap[$id] ?? 'ancestors';
            $generations[(string)$gen] = true;
            if ($gaps) {
                $items[] = [
                    'id'     => $id,
                    'p'      => $p,
                    'gaps'   => $gaps,
                    'detail' => $detail,
                    'gen'    => (string)$gen,
                ];
            }
        }

        // Фильтры (значения проверяются по белому списку)
        $gapFilter = (string)$request->get('gap', '');
        if (!in_array($gapFilter, self::GAPS, true)) {
            $gapFilter = '';
        }
        $genFilter = (string)$request->get('gen', '');
        if ($genFilter !== 'ancestors' && !ctype_digit($genFilter)) {
            $genFilter = '';
        }
        $genKeys = array_keys($generations);
        usort($genKeys, static fn($a, $b) => ($a === 'ancestors' ? 0 : (int)$a) <=> ($b === 'ancestors' ? 0 : (int)$b));

        $filtered = array_values(array_filter($items, static function ($it) use ($gapFilter, $genFilter) {
            if ($gapFilter !== '' && !in_array($gapFilter, $it['gaps'], true)) {
                return false;
            }
            return $genFilter === '' || $it['gen'] === $genFilter;
        }));

        Response::view('admin/missing', [
            'items'     => $filtered,
            'counts'    => $counts,
            'totalAll'  => count($byId),
            'totalGaps' => count($items),
            'genKeys'   => $genKeys,
            'gapFilter' => $gapFilter,
            'genFilter' => $genFilter,
        ]);
    }
}
