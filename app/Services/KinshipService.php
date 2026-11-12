<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\KinshipDictionary;

/**
 * Қазақтың туыстық қатынастарын есептеу сервисі (Калькулятор казахских степеней родства)
 */
class KinshipService
{
    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $terms;

    public function __construct()
    {
        $this->terms = KinshipDictionary::getTerms();
    }

    /**
     * Барлық туыстық терминдер сөздігін қайтарады
     */
    public function getAllTerms(): array
    {
        return $this->terms;
    }

    /**
     * Екі адамның арасындағы туыстық атауды анықтау
     *
     * @param array $targetPerson Персона, кем является (кого оцениваем)
     * @param array $basePerson Персона, относительно кого смотрим (говорящий или выбранный человек)
     * @param string $relationshipType Тип связи ('auto', 'parent', 'child', 'spouse', 'sibling', etc.)
     * @param array<int, array> $allPersonsById Карта всех персон по ID для глубокого графового анализа
     * @param array<int, array> $marriages Список браков
     * @return array{term_kk: string, term_ru: string, code: string, explanation: string, badge_class: string}
     */
    public function determineKinship(
        array $targetPerson,
        array $basePerson,
        string $relationshipType = 'auto',
        array $allPersonsById = [],
        array $marriages = []
    ): array {
        $targetId = (int)($targetPerson['id'] ?? 0);
        $baseId = (int)($basePerson['id'] ?? 0);

        if ($targetId && $baseId && $targetId === $baseId) {
            return [
                'term_kk' => 'Өзіңіз',
                'term_ru' => 'Өзіңіз (Вы сами)',
                'code' => 'self',
                'explanation' => 'Сен осындасың / Вы здесь',
                'badge_class' => 'badge-self',
            ];
        }

        $targetGender = $targetPerson['gender'] ?? 'M';
        $baseGender = $basePerson['gender'] ?? 'M';

        $targetYear = $this->extractYear($targetPerson['birth_year'] ?? $targetPerson['birth_date'] ?? null);
        $baseYear = $this->extractYear($basePerson['birth_year'] ?? $basePerson['birth_date'] ?? null);
        $isOlder = ($targetYear !== null && $baseYear !== null) ? ($targetYear < $baseYear) : false;

        $targetFatherId = (int)($targetPerson['father_id'] ?? 0);
        $targetMotherId = (int)($targetPerson['mother_id'] ?? 0);
        $baseFatherId = (int)($basePerson['father_id'] ?? 0);
        $baseMotherId = (int)($basePerson['mother_id'] ?? 0);

        // 1. Ата-ана (Родители basePerson)
        if ($baseFatherId && $baseFatherId === $targetId) {
            return $this->formatResult('father', 'Әкесі', 'Әке', 'Отец');
        }
        if ($baseMotherId && $baseMotherId === $targetId) {
            return $this->formatResult('mother', 'Анасы', 'Ана', 'Мать');
        }

        // 2. Балалары (Дети basePerson)
        if (($targetFatherId && $targetFatherId === $baseId) || ($targetMotherId && $targetMotherId === $baseId)) {
            if ($targetGender === 'F') {
                return $this->formatResult('daughter', 'Туған қызы', 'Қыз', 'Дочь');
            }
            return $this->formatResult('son', 'Туған ұлы', 'Ұл', 'Сын');
        }

        // 3. Жұбайы (Супруг / Супруга)
        if ($this->areMarried($targetId, $baseId, $marriages, $targetPerson, $basePerson, $allPersonsById)) {
            if ($targetGender === 'F') {
                return $this->formatResult('wife', 'Өмірлік жары', 'Жұбайы', 'Супруга');
            }
            return $this->formatResult('husband', 'Отағасы, күйеуі', 'Күйеуі', 'Супруг');
        }

        // 4. Бауырлар (Братья / Сестры)
        if ($this->areSiblings($targetPerson, $basePerson)) {
            if ($targetGender === 'M') {
                if ($isOlder) {
                    return $this->formatResult('brother_older', 'Өзінен үлкен ағасы', 'Аға', 'Старший брат');
                }
                return $this->formatResult('brother_younger', 'Өзінен кіші інісі', 'Іні', 'Младший брат');
            } else {
                if ($isOlder) {
                    return $this->formatResult('sister_older', 'Өзінен үлкен әпкесі', 'Әпке', 'Старшая сестра');
                }
                if ($baseGender === 'M') {
                    return $this->formatResult('sister_younger_for_male', 'Ер азаматтың қарындасы', 'Қарындас', 'Младшая сестра');
                }
                return $this->formatResult('sister_younger_for_female', 'Әйел адамның сіңлісі', 'Сіңлі', 'Младшая сестра');
            }
        }

        // 5. Ата және Әже (Дедушки и бабушки)
        if (!empty($allPersonsById)) {
            $baseFather = $baseFatherId ? ($allPersonsById[$baseFatherId] ?? null) : null;
            $baseMother = $baseMotherId ? ($allPersonsById[$baseMotherId] ?? null) : null;

            // Атасы / Әжесі (по отцу)
            if ($baseFather) {
                if ((int)($baseFather['father_id'] ?? 0) === $targetId) {
                    return $this->formatResult('grandfather_paternal', 'Әкесінің әкесі', 'Ата', 'Дедушка');
                }
                if ((int)($baseFather['mother_id'] ?? 0) === $targetId) {
                    return $this->formatResult('grandmother_paternal', 'Әкесінің анасы', 'Әже', 'Бабушка');
                }
                // Бабасы (прадед)
                $paternalGrandfatherId = (int)($baseFather['father_id'] ?? 0);
                if ($paternalGrandfatherId && isset($allPersonsById[$paternalGrandfatherId])) {
                    $pGrandfather = $allPersonsById[$paternalGrandfatherId];
                    if ((int)($pGrandfather['father_id'] ?? 0) === $targetId) {
                        return $this->formatResult('great_grandfather', 'Атасының әкесі (3-ші буын)', 'Баба', 'Прадедушка');
                    }
                }
            }

            // Нағашы атасы / Нағашы әжесі (по матери)
            if ($baseMother) {
                if ((int)($baseMother['father_id'] ?? 0) === $targetId) {
                    return $this->formatResult('grandfather_maternal', 'Анасының әкесі', 'Нағашы ата', 'Дедушка по матери');
                }
                if ((int)($baseMother['mother_id'] ?? 0) === $targetId) {
                    return $this->formatResult('grandmother_maternal', 'Анасының анасы', 'Нағашы әже', 'Бабушка по матери');
                }
            }
        }

        // 6. Немере және Шөбере (Внуки и правнуки)
        if (!empty($allPersonsById)) {
            $targetFather = $targetFatherId ? ($allPersonsById[$targetFatherId] ?? null) : null;
            $targetMother = $targetMotherId ? ($allPersonsById[$targetMotherId] ?? null) : null;

            // Немере (от сына)
            if ($targetFather && ((int)($targetFather['father_id'] ?? 0) === $baseId || (int)($targetFather['mother_id'] ?? 0) === $baseId)) {
                return $this->formatResult('grandson_son', 'Ұлының баласы', 'Немере', 'Внук / Внучка');
            }
            // Жиен (от дочери)
            if ($targetMother && ((int)($targetMother['father_id'] ?? 0) === $baseId || (int)($targetMother['mother_id'] ?? 0) === $baseId)) {
                return $this->formatResult('zhien_grandchild', 'Қызының баласы', 'Жиен', 'Внук / Внучка по дочери');
            }

            // Шөбере (правнук)
            if ($targetFather) {
                $tGrandfatherId = (int)($targetFather['father_id'] ?? 0);
                if ($tGrandfatherId && isset($allPersonsById[$tGrandfatherId])) {
                    $tGrandfather = $allPersonsById[$tGrandfatherId];
                    if ((int)($tGrandfather['father_id'] ?? 0) === $baseId || (int)($tGrandfather['mother_id'] ?? 0) === $baseId) {
                        return $this->formatResult('great_grandson', 'Немересінің баласы', 'Шөбере', 'Правнук / Правнучка');
                    }
                }
            }
        }

        // 7. Келін / Жеңге / Жезде / Күйеу бала
        if (!empty($allPersonsById) && !empty($marriages)) {
            // Келін: жена сына
            foreach ($marriages as $m) {
                $hId = (int)($m['husband_id'] ?? 0);
                $wId = (int)($m['wife_id'] ?? 0);
                if ($wId === $targetId && isset($allPersonsById[$hId])) {
                    $husband = $allPersonsById[$hId];
                    if ((int)($husband['father_id'] ?? 0) === $baseId || (int)($husband['mother_id'] ?? 0) === $baseId) {
                        return $this->formatResult('kelin', 'Ұлының әйелі', 'Келін', 'Сноха');
                    }
                    // Келін: жена младшего брата
                    if ($this->areSiblings($husband, $basePerson) && !$this->isOlderThan($husband, $basePerson)) {
                        return $this->formatResult('kelin_sibling', 'Інісінің әйелі', 'Келін', 'Невестка');
                    }
                    // Жеңге: жена старшего брата
                    if ($this->areSiblings($husband, $basePerson) && $this->isOlderThan($husband, $basePerson)) {
                        return $this->formatResult('zhenge', 'Ағасының әйелі', 'Жеңге', 'Жена старшего брата');
                    }
                }
                // Күйеу бала / Жезде: муж дочери или сестры
                if ($hId === $targetId && isset($allPersonsById[$wId])) {
                    $wife = $allPersonsById[$wId];
                    if ((int)($wife['father_id'] ?? 0) === $baseId || (int)($wife['mother_id'] ?? 0) === $baseId) {
                        return $this->formatResult('kuyeubala', 'Қызының күйеуі', 'Күйеу бала', 'Зять');
                    }
                    if ($this->areSiblings($wife, $basePerson)) {
                        return $this->formatResult('zhezde', 'Әпке/қарындасының күйеуі', 'Жезде', 'Зять / муж сестры');
                    }
                }
            }
        }

        // 8. Нағашы жұрт (Братья / сестры матери)
        if (!empty($allPersonsById) && $baseMotherId && isset($allPersonsById[$baseMotherId])) {
            $baseMother = $allPersonsById[$baseMotherId];
            if ($this->areSiblings($targetPerson, $baseMother)) {
                if ($targetGender === 'F') {
                    return $this->formatResult('aunt_maternal', 'Анасының сіңлісі/әпкесі', 'Нағашы апа', 'Тетя по матери');
                }
                return $this->formatResult('uncle_maternal', 'Анасының ағасы/інісі', 'Нағашы аға', 'Дядя по матери');
            }
        }

        // 9. Жиендер (Дети сестры/дочери)
        if (!empty($allPersonsById)) {
            if ($targetMotherId && isset($allPersonsById[$targetMotherId])) {
                $targetMother = $allPersonsById[$targetMotherId];
                if ($this->areSiblings($targetMother, $basePerson)) {
                    return $this->formatResult('zhien', 'Әпке/қарындасының баласы', 'Жиен', 'Племянник / Племянница');
                }
            }
        }

        // 10. Түп бабалар желісі (Прямые предки по отцу)
        if (!empty($targetPerson['is_trunk']) || !empty($targetPerson['is_founder']) || ($targetYear !== null && $targetYear < 1900 && $targetGender === 'M')) {
            return [
                'term_kk' => 'Баба',
                'term_ru' => 'Баба (Предок-основатель)',
                'code' => 'ancestor',
                'explanation' => 'Тікелей ата-баба желісі / Предок рода',
                'badge_class' => 'badge-ancestor',
            ];
        }

        // 11. Рулас туыс (Один род / клан)
        $targetClanId = (int)($targetPerson['clan_id'] ?? 0);
        $baseClanId = (int)($basePerson['clan_id'] ?? 0);
        if ($targetClanId && $baseClanId && $targetClanId === $baseClanId) {
            return [
                'term_kk' => $targetGender === 'F' ? 'Қарындас / Бауыр' : 'Рулас бауыр',
                'term_ru' => $targetGender === 'F' ? 'Қарындас / Бауыр (Сородич)' : 'Рулас бауыр (Сородич)',
                'code' => 'clan_relative',
                'explanation' => 'Бір рудың туысы / Родственник по роду',
                'badge_class' => 'badge-relative',
            ];
        }

        // 12. По умолчанию
        return [
            'term_kk' => $targetGender === 'F' ? 'Ағайын / Қарындас' : 'Ағайын / Бауыр',
            'term_ru' => $targetGender === 'F' ? 'Ағайын / Қарындас (Родственница)' : 'Ағайын / Бауыр (Родственник)',
            'code' => 'relative',
            'explanation' => 'Шежіредегі туысқандық желі',
            'badge_class' => 'badge-relative',
        ];
    }

    /**
     * Вычислить степени родства для ВСЕХ персон относительно базовой персоны (baseId)
     *
     * @param int $baseId
     * @param array<int, array> $persons
     * @param array<int, array> $marriages
     * @return array<int, array{term_kk: string, term_ru: string, code: string, explanation: string, badge_class: string}>
     */
    public function calculateAllKinships(int $baseId, array $persons, array $marriages): array
    {
        $allPersonsById = [];
        foreach ($persons as $p) {
            $allPersonsById[(int)$p['id']] = $p;
        }

        $basePerson = $allPersonsById[$baseId] ?? null;
        if (!$basePerson) {
            return [];
        }

        $result = [];
        foreach ($persons as $p) {
            $pId = (int)$p['id'];
            $result[$pId] = $this->determineKinship($p, $basePerson, 'auto', $allPersonsById, $marriages);
        }

        return $result;
    }

    /**
     * Қолданушыға («Сіз осындасыз») қатысты түсініктеме сөйлем құрау
     */
    public function formatUserKinshipPhrase(array $targetPerson, array $userPerson, array $allPersonsById = [], array $marriages = []): string
    {
        $kin = $this->determineKinship($targetPerson, $userPerson, 'auto', $allPersonsById, $marriages);

        if ($kin['code'] === 'self') {
            return '🎯 <strong>Сіз осындасыз</strong> (Бұл сіздің жеке карточкаңыз)';
        }

        return "🔗 Бұл кісі сізге: <strong>{$kin['term_kk']}</strong> <span style=\"opacity:0.85; font-size:12px;\">({$kin['term_ru']})</span>";
    }

    protected function areSiblings(array $p1, array $p2): bool
    {
        $f1 = (int)($p1['father_id'] ?? 0);
        $m1 = (int)($p1['mother_id'] ?? 0);
        $f2 = (int)($p2['father_id'] ?? 0);
        $m2 = (int)($p2['mother_id'] ?? 0);

        if ($f1 && $f2 && $f1 === $f2) {
            return true;
        }
        if ($m1 && $m2 && $m1 === $m2) {
            return true;
        }

        return false;
    }

    protected function areMarried(int $id1, int $id2, array $marriages, array $p1, array $p2, array $allPersonsById): bool
    {
        foreach ($marriages as $m) {
            $hId = (int)($m['husband_id'] ?? 0);
            $wId = (int)($m['wife_id'] ?? 0);
            if (($hId === $id1 && $wId === $id2) || ($hId === $id2 && $wId === $id1)) {
                return true;
            }
        }
        return false;
    }

    protected function isOlderThan(array $p1, array $p2): bool
    {
        $y1 = $this->extractYear($p1['birth_year'] ?? $p1['birth_date'] ?? null);
        $y2 = $this->extractYear($p2['birth_year'] ?? $p2['birth_date'] ?? null);
        return ($y1 !== null && $y2 !== null) ? ($y1 < $y2) : false;
    }

    protected function extractYear(?string $raw): ?int
    {
        if (!$raw) {
            return null;
        }
        if (preg_match('/\b(1[3-9]\d{2}|20\d{2})\b/', $raw, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }

    protected function formatResult(string $termKey, string $extraNote, string $fallbackKk = '', string $fallbackRu = ''): array
    {
        $t = $this->terms[$termKey] ?? null;
        $kk = $t['kk'] ?? $fallbackKk;
        $ru = $t['ru'] ?? $fallbackRu;
        $desc = $t['desc'] ?? $extraNote;
        $line = $t['line'] ?? 'kin';

        return [
            'term_kk' => $kk,
            'term_ru' => "{$kk} ({$ru})",
            'code' => $termKey,
            'explanation' => $desc . ($extraNote ? " — {$extraNote}" : ''),
            'badge_class' => 'badge-' . $line,
        ];
    }
}
