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
     * @param string $relationshipType Тип связи ('parent', 'child', 'spouse', 'sibling', 'paternal_ancestor', etc.)
     * @return array{term_kk: string, term_ru: string, code: string, explanation: string, badge_class: string}
     */
    public function determineKinship(array $targetPerson, array $basePerson, string $relationshipType = 'auto'): array
    {
        $targetGender = $targetPerson['gender'] ?? 'M';
        $baseGender = $basePerson['gender'] ?? 'M';

        $targetYear = $this->extractYear($targetPerson['birth_year'] ?? $targetPerson['birth_date'] ?? null);
        $baseYear = $this->extractYear($basePerson['birth_year'] ?? $basePerson['birth_date'] ?? null);
        $isOlder = ($targetYear !== null && $baseYear !== null) ? ($targetYear < $baseYear) : false;

        // Если это один и тот же человек
        if (($targetPerson['id'] ?? null) === ($basePerson['id'] ?? null) && !empty($targetPerson['id'])) {
            return [
                'term_kk' => 'Өзі / Өзіңіз',
                'term_ru' => 'Вы сами / Тот же человек',
                'code' => 'self',
                'explanation' => 'Сен осындасың / Вы здесь',
                'badge_class' => 'badge-self',
            ];
        }

        // 1. Ата-ана (Родители)
        if ($relationshipType === 'parent' || (($basePerson['father_id'] ?? null) === ($targetPerson['id'] ?? null) || ($basePerson['mother_id'] ?? null) === ($targetPerson['id'] ?? null))) {
            if ($targetGender === 'F') {
                return $this->formatResult($this->terms['mother'], 'Анасы');
            }
            return $this->formatResult($this->terms['father'], 'Әкесі');
        }

        // 2. Балалары (Дети)
        if ($relationshipType === 'child' || (($targetPerson['father_id'] ?? null) === ($basePerson['id'] ?? null) || ($targetPerson['mother_id'] ?? null) === ($basePerson['id'] ?? null))) {
            if ($targetGender === 'F') {
                return $this->formatResult($this->terms['daughter'], 'Туған қызы');
            }
            return $this->formatResult($this->terms['son'], 'Туған ұлы');
        }

        // 3. Жұбайы (Супруг/а)
        if ($relationshipType === 'spouse') {
            if ($targetGender === 'F') {
                return $this->formatResult($this->terms['wife'], 'Өмірлік жары, келін');
            }
            return $this->formatResult($this->terms['husband'], 'Отағасы, күйеуі');
        }

        // 4. Бауырлар (Братья / Сестры)
        if ($relationshipType === 'sibling' || $this->areSiblings($targetPerson, $basePerson)) {
            if ($targetGender === 'M') {
                // Брат
                if ($isOlder) {
                    return $this->formatResult($this->terms['brother_older'], 'Өзінен үлкен ағасы');
                }
                return $this->formatResult($this->terms['brother_younger'], 'Өзінен кіші інісі');
            } else {
                // Сестра
                if ($isOlder) {
                    return $this->formatResult($this->terms['sister_older'], 'Өзінен үлкен әпкесі');
                }
                // Для младшей сестры учитываем пол говорящего!
                if ($baseGender === 'M') {
                    return $this->formatResult($this->terms['sister_younger_for_male'], 'Ер азаматтың қарындасы');
                }
                return $this->formatResult($this->terms['sister_younger_for_female'], 'Әйел адамның сіңлісі');
            }
        }

        // 5. Ата және Әже (Дедушки и бабушки)
        if ($relationshipType === 'grandfather' || $relationshipType === 'paternal_grandparent') {
            if ($targetGender === 'F') {
                return $this->formatResult($this->terms['grandmother_paternal'], 'Әкесінің анасы');
            }
            return $this->formatResult($this->terms['grandfather_paternal'], 'Әкесінің әкесі');
        }

        // 6. Нағашы жұрт (По матери)
        if ($relationshipType === 'maternal_uncle' || str_contains($relationshipType, 'maternal')) {
            if ($targetGender === 'F') {
                return $this->formatResult($this->terms['aunt_maternal'], 'Анасының сіңлісі/әпкесі');
            }
            return $this->formatResult($this->terms['uncle_maternal'], 'Анасының ағасы/інісі');
        }

        // 7. Келін / Жеңге / Жезде / Күйеу бала
        if ($relationshipType === 'zhenge') {
            return $this->formatResult($this->terms['zhenge'], 'Ағасының әйелі');
        }
        if ($relationshipType === 'kelin') {
            return $this->formatResult($this->terms['kelin'], 'Ұлының немесе інісінің әйелі');
        }
        if ($relationshipType === 'zhezde') {
            return $this->formatResult($this->terms['zhezde'], 'Әпкесінің күйеуі');
        }
        if ($relationshipType === 'kuyeubala') {
            return $this->formatResult($this->terms['kuyeubala'], 'Қызының немесе қарындасының күйеуі');
        }

        // Жалпы туыс (По умолчанию)
        return [
            'term_kk' => $targetGender === 'F' ? 'Ағайын / Қарындас' : 'Ағайын / Бауыр',
            'term_ru' => 'Родственник(ца)',
            'code' => 'relative',
            'explanation' => 'Шежіредегі туысқандық желі',
            'badge_class' => 'badge-relative',
        ];
    }

    /**
     * Қолданушыға («Сіз осындасыз») қатысты түсініктеме сөйлем құрау
     */
    public function formatUserKinshipPhrase(array $targetPerson, array $userPerson): string
    {
        $kin = $this->determineKinship($targetPerson, $userPerson);
        if ($kin['code'] === 'self') {
            return '🎯 Бұл сіздің өзіңіздің карточкаңыз / Это ваша карточка в шежіре';
        }

        return "🔗 Бұл сіздің — <strong>{$kin['term_kk']}</strong> ({$kin['term_ru']})";
    }

    protected function areSiblings(array $p1, array $p2): bool
    {
        $f1 = $p1['father_id'] ?? null;
        $m1 = $p1['mother_id'] ?? null;
        $f2 = $p2['father_id'] ?? null;
        $m2 = $p2['mother_id'] ?? null;

        if ($f1 && $f2 && $f1 === $f2) {
            return true;
        }
        if ($m1 && $m2 && $m1 === $m2) {
            return true;
        }

        return false;
    }

    protected function extractYear(?string $raw): ?int
    {
        if (!$raw) {
            return null;
        }
        if (preg_match('/\b(1[6-9]\d{2}|20\d{2})\b/', $raw, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }

    protected function formatResult(array $term, string $extraNote): array
    {
        return [
            'term_kk' => $term['kk'],
            'term_ru' => $term['ru'],
            'code' => $term['code'],
            'explanation' => $term['desc'] . ($extraNote ? " ({$extraNote})" : ''),
            'badge_class' => 'badge-' . ($term['line'] ?? 'kin'),
        ];
    }
}
