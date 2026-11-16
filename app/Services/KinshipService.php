<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\KinshipDictionary;

/**
 * Қазақтың туыстық қатынастарын есептеудің толық академиялық сервисі
 * (Академический калькулятор традиционной казахской системы родства)
 */
class KinshipService
{
    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $terms;

    /**
     * @var array<int, array<int, array>>
     */
    protected array $kinshipCache = [];

    /**
     * @var array<int, array>|null
     */
    protected ?array $cachedPersons = null;

    /**
     * @var array<int, array>|null
     */
    protected ?array $cachedMarriages = null;

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
     * @return array{term_kk: string, term_ru: string, code: string, zhurt: string, explanation: string, badge_class: string, secondary_relation?: string}
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
                'term_kk' => 'Мен',
                'term_ru' => 'Я',
                'code' => 'self',
                'zhurt' => '',
                'explanation' => 'Мен / Я',
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

        // 1. АТА-АНА (Родители basePerson)
        if ($baseFatherId && $baseFatherId === $targetId) {
            return $this->formatResult('father', 'Әкесі', 'Әке', 'Отец', $basePerson, 'Өз жұрты', 'Төркіні');
        }
        if ($baseMotherId && $baseMotherId === $targetId) {
            return $this->formatResult('mother', 'Анасы', 'Ана', 'Мать', $basePerson, 'Өз жұрты', 'Төркіні');
        }

        // 2. БАЛАЛАРЫ (Дети basePerson)
        if (($targetFatherId && $targetFatherId === $baseId) || ($targetMotherId && $targetMotherId === $baseId)) {
            if ($targetGender === 'F') {
                return $this->formatResult('daughter', 'Туған қызы', 'Қыз', 'Дочь', $basePerson, 'Өз жұрты', 'Өз жұрты');
            }
            return $this->formatResult('son', 'Туған ұлы', 'Ұл', 'Сын', $basePerson, 'Өз жұрты', 'Өз жұрты');
        }

        // 3. ЖҰБАЙЫ (Супруг / Супруга)
        // Супруг(а) — это своя семья, термин «Жұбай / Әйелі» (рус. «Жена»), НЕ қайын жұрт. Не попадает ни в один из трёх жұрт.
        if ($this->areMarried($targetId, $baseId, $marriages, $targetPerson, $basePerson, $allPersonsById)) {
            if ($targetGender === 'F') {
                return $this->formatResult('wife', 'Өмірлік жары', 'Жұбайы', 'Супруга', $basePerson, '', '');
            }
            return $this->formatResult('husband', 'Отағасы, күйеуі', 'Күйеуі', 'Супруг', $basePerson, '', '');
        }

        // 4. ТУҒАН БАУЫРЛАР (Братья / Сестры)
        if ($this->areSiblings($targetPerson, $basePerson)) {
            if ($targetGender === 'M') {
                if ($isOlder) {
                    return $this->formatResult('brother_older', 'Өзінен үлкен ағасы', 'Аға', 'Старший брат', $basePerson, 'Өз жұрты', 'Төркіні');
                }
                return $this->formatResult('brother_younger', 'Өзінен кіші інісі', 'Іні', 'Младший брат', $basePerson, 'Өз жұрты', 'Төркіні');
            } else {
                if ($isOlder) {
                    return $this->formatResult('sister_older', 'Өзінен үлкен әпкесі', 'Әпке', 'Старшая сестра', $basePerson, 'Өз жұрты', 'Төркіні');
                }
                if ($baseGender === 'M') {
                    return $this->formatResult('sister_younger_for_male', 'Ер азаматтың қарындасы', 'Қарындас', 'Младшая сестра (сестрёнка)', $basePerson, 'Өз жұрты', 'Төркіні');
                }
                return $this->formatResult('sister_younger_for_female', 'Әйел адамның сіңлісі', 'Сіңлі', 'Младшая сестра (сестрёнка)', $basePerson, 'Өз жұрты', 'Төркіні');
            }
        }

        // 5. БАЖА (Мужья родных сестер друг другу) — КРИТИЧЕСКИ ВАЖНО, ТОЛЬКО ДЛЯ ДВУХ МУЖЧИН!
        if ($baseGender === 'M' && $targetGender === 'M' && !empty($allPersonsById) && !empty($marriages)) {
            $baseWives = $this->getSpouseIds($baseId, $marriages);
            $targetWives = $this->getSpouseIds($targetId, $marriages);

            if (!empty($baseWives) && !empty($targetWives)) {
                foreach ($baseWives as $bwId) {
                    $bWife = $allPersonsById[$bwId] ?? null;
                    if (!$bWife) continue;
                    foreach ($targetWives as $twId) {
                        $tWife = $allPersonsById[$twId] ?? null;
                        if (!$tWife) continue;
                        if ($bwId !== $twId && $this->areSiblings($bWife, $tWife)) {
                            return [
                                'term_kk' => 'Бажа',
                                'term_ru' => 'Бажа (муж сестры жены / свояк)',
                                'code' => 'bazha',
                                'zhurt' => $this->getZhurtForPerson($basePerson, 'in_law'),
                                'explanation' => 'Апалы-сіңлілі қыздарға үйленген ер адамдар бір-біріне бажа болады',
                                'badge_class' => 'badge-inlaw',
                            ];
                        }
                    }
                }
            }
        }

        // 6. АБЫСЫН (Жёны братьев друг другу)
        if ($baseGender === 'F' && $targetGender === 'F' && !empty($allPersonsById) && !empty($marriages)) {
            $baseHusbands = $this->getSpouseIds($baseId, $marriages);
            $targetHusbands = $this->getSpouseIds($targetId, $marriages);
            foreach ($baseHusbands as $bhId) {
                $bHusb = $allPersonsById[$bhId] ?? null;
                if (!$bHusb) continue;
                foreach ($targetHusbands as $thId) {
                    $tHusb = $allPersonsById[$thId] ?? null;
                    if (!$tHusb) continue;
                    if ($bhId !== $thId && $this->areSiblings($bHusb, $tHusb)) {
                        return [
                            'term_kk' => 'Абысын',
                            'term_ru' => 'Абысын (жёны братьев)',
                            'code' => 'abysyn',
                            'zhurt' => $this->getZhurtForPerson($basePerson, 'in_law'),
                            'explanation' => 'Ағайынды бауырлардың әйелдері бір-біріне абысын',
                            'badge_class' => 'badge-inlaw',
                        ];
                    }
                }
            }
        }

        // 7. БАЛДЫЗ, ҚАЙЫН АҒА, ҚАЙЫН БИКЕ, ҚАЙЫН ІНІ, ҚАЙЫН СІҢЛІ (Родственники супруга/супруги)
        if (!empty($allPersonsById) && !empty($marriages)) {
            $spouses = $this->getSpouseIds($baseId, $marriages);
            foreach ($spouses as $spId) {
                $sp = $allPersonsById[$spId] ?? null;
                if (!$sp) continue;

                // Если target является братом/сестрой супруга basePerson
                if ($this->areSiblings($targetPerson, $sp)) {
                    $targetOlderThanSpouse = $this->isOlderThan($targetPerson, $sp);

                    // Если basePerson — мужчина (смотрим родственников жены)
                    if ($baseGender === 'M') {
                        if ($targetOlderThanSpouse) {
                            if ($targetGender === 'M') {
                                return [
                                    'term_kk' => 'Қайын аға',
                                    'term_ru' => 'Қайын аға (старший брат жены)',
                                    'code' => 'kayin_aga',
                                    'zhurt' => 'Қайын жұрты',
                                    'explanation' => 'Әйелінің үлкен ағасы',
                                    'badge_class' => 'badge-inlaw',
                                ];
                            }
                            return [
                                'term_kk' => 'Қайын бике',
                                'term_ru' => 'Қайын бике (старшая сестра жены)',
                                'code' => 'kayin_bike',
                                'zhurt' => 'Қайын жұрты',
                                'explanation' => 'Әйелінің үлкен әпкесі',
                                'badge_class' => 'badge-inlaw',
                            ];
                        } else {
                            // Младшие братья и сестры жены — БАЛДЫЗ
                            return [
                                'term_kk' => 'Балдыз',
                                'term_ru' => $targetGender === 'M' ? 'Балдыз (младший брат жены)' : 'Балдыз (младшая сестра жены)',
                                'code' => 'baldyz',
                                'zhurt' => 'Қайын жұрты',
                                'explanation' => 'Әйелінің өзінен жасы кіші бауыры',
                                'badge_class' => 'badge-inlaw',
                            ];
                        }
                    } else {
                        // Если basePerson — женщина (смотрим родственников мужа)
                        if ($targetOlderThanSpouse) {
                            if ($targetGender === 'M') {
                                return [
                                    'term_kk' => 'Қайын аға',
                                    'term_ru' => 'Қайын аға (старший брат мужа)',
                                    'code' => 'kayin_aga',
                                    'zhurt' => 'Өз жұрты',
                                    'explanation' => 'Күйеуінің үлкен ағасы',
                                    'badge_class' => 'badge-inlaw',
                                ];
                            }
                            return [
                                'term_kk' => 'Қайын бике',
                                'term_ru' => 'Қайын бике (старшая сестра мужа)',
                                'code' => 'kayin_bike',
                                'zhurt' => 'Өз жұрты',
                                'explanation' => 'Күйеуінің үлкен әпкесі',
                                'badge_class' => 'badge-inlaw',
                            ];
                        } else {
                            if ($targetGender === 'M') {
                                return [
                                    'term_kk' => 'Қайын іні',
                                    'term_ru' => 'Қайын іні (младший брат мужа)',
                                    'code' => 'kayin_ini',
                                    'zhurt' => 'Өз жұрты',
                                    'explanation' => 'Күйеуінің кіші інісі',
                                    'badge_class' => 'badge-inlaw',
                                ];
                            }
                            return [
                                'term_kk' => 'Қайын сіңлі',
                                'term_ru' => 'Қайын сіңлі (младшая сестра мужа)',
                                'code' => 'kayin_sinli',
                                'zhurt' => 'Өз жұрты',
                                'explanation' => 'Күйеуінің кіші қарындасы/сіңлісі',
                                'badge_class' => 'badge-inlaw',
                            ];
                        }
                    }
                }

                // Родители супруга (Қайын ата, Қайын ене)
                $spFatherId = (int)($sp['father_id'] ?? 0);
                $spMotherId = (int)($sp['mother_id'] ?? 0);
                if ($spFatherId === $targetId) {
                    return [
                        'term_kk' => 'Қайын ата',
                        'term_ru' => $baseGender === 'M' ? 'Тесть (Қайын ата)' : 'Свёкор (Қайын ата)',
                        'code' => 'kayin_ata',
                        'zhurt' => $baseGender === 'M' ? 'Қайын жұрты' : 'Өз жұрты',
                        'explanation' => 'Жұбайының әкесі',
                        'badge_class' => 'badge-inlaw',
                    ];
                }
                if ($spMotherId === $targetId) {
                    return [
                        'term_kk' => 'Қайын ене',
                        'term_ru' => $baseGender === 'M' ? 'Тёща (Қайын ене)' : 'Свекровь (Қайын ене)',
                        'code' => 'kayin_ene',
                        'zhurt' => $baseGender === 'M' ? 'Қайын жұрты' : 'Өз жұрты',
                        'explanation' => 'Жұбайының анасы',
                        'badge_class' => 'badge-inlaw',
                    ];
                }
            }
        }

        // 8. ҚҰДА, ҚҰДАҒИ, ҚҰДАША, ҚҰДА БАЛА (Сватовство через брак детей)
        if (!empty($allPersonsById) && !empty($marriages)) {
            $childIds = $this->getChildrenIds($baseId, $allPersonsById);
            foreach ($childIds as $cId) {
                $childSpouses = $this->getSpouseIds($cId, $marriages);
                foreach ($childSpouses as $csId) {
                    $cSpouse = $allPersonsById[$csId] ?? null;
                    if (!$cSpouse) continue;

                    $csFatherId = (int)($cSpouse['father_id'] ?? 0);
                    $csMotherId = (int)($cSpouse['mother_id'] ?? 0);

                    if ($csFatherId === $targetId) {
                        return [
                            'term_kk' => 'Құда',
                            'term_ru' => 'Құда (отец зятя/снохи)',
                            'code' => 'kuda',
                            'zhurt' => $this->getZhurtForPerson($basePerson, 'in_law'),
                            'explanation' => 'Баласының жұбайының әкесі',
                            'badge_class' => 'badge-inlaw',
                        ];
                    }
                    if ($csMotherId === $targetId) {
                        return [
                            'term_kk' => 'Құдағи',
                            'term_ru' => 'Құдағи (мать зятя/снохи)',
                            'code' => 'kudaghy',
                            'zhurt' => $this->getZhurtForPerson($basePerson, 'in_law'),
                            'explanation' => 'Баласының жұбайының анасы',
                            'badge_class' => 'badge-inlaw',
                        ];
                    }
                    if ($this->areSiblings($targetPerson, $cSpouse)) {
                        if ($targetGender === 'F') {
                            return [
                                'term_kk' => 'Құдаша',
                                'term_ru' => 'Құдаша (родственница зятя/снохи)',
                                'code' => 'kudasha',
                                'zhurt' => $this->getZhurtForPerson($basePerson, 'in_law'),
                                'explanation' => 'Құда жақтың өзінен жасы кіші қыз туысы',
                                'badge_class' => 'badge-inlaw',
                            ];
                        } else {
                            return [
                                'term_kk' => 'Құда бала',
                                'term_ru' => 'Құда бала (родственник зятя/снохи)',
                                'code' => 'kuda_bala',
                                'zhurt' => $this->getZhurtForPerson($basePerson, 'in_law'),
                                'explanation' => 'Құда жақтың жас ер туысы',
                                'badge_class' => 'badge-inlaw',
                            ];
                        }
                    }
                }
            }
        }

        // 9. ЖЕҢГЕ, ЖЕЗДЕ, КЕЛІН, КҮЙЕУ БАЛА (Супруги кровных родственников)
        if (!empty($allPersonsById) && !empty($marriages)) {
            // Келін / Жеңге: жена кого-то из нашей семьи
            foreach ($marriages as $m) {
                $hId = (int)($m['husband_id'] ?? 0);
                $wId = (int)($m['wife_id'] ?? 0);

                if ($wId === $targetId && isset($allPersonsById[$hId])) {
                    $husband = $allPersonsById[$hId];

                    // Келін: жена сына
                    if ((int)($husband['father_id'] ?? 0) === $baseId || (int)($husband['mother_id'] ?? 0) === $baseId) {
                        return [
                            'term_kk' => 'Келін',
                            'term_ru' => 'Келін (сноха / невестка)',
                            'code' => 'kelin',
                            'zhurt' => $this->getZhurtForPerson($basePerson, 'paternal'),
                            'explanation' => 'Ұлының әйелі',
                            'badge_class' => 'badge-inlaw',
                        ];
                    }

                    // Жеңге: жена старшего брата
                    if ($this->areSiblings($husband, $basePerson) && $this->isOlderThan($husband, $basePerson)) {
                        return [
                            'term_kk' => 'Жеңге',
                            'term_ru' => 'Жеңге (жена старшего брата)',
                            'code' => 'zhenge',
                            'zhurt' => $this->getZhurtForPerson($basePerson, 'paternal'),
                            'explanation' => 'Ағасының әйелі',
                            'badge_class' => 'badge-inlaw',
                        ];
                    }

                    // Келін: жена младшего брата
                    if ($this->areSiblings($husband, $basePerson) && !$this->isOlderThan($husband, $basePerson)) {
                        return [
                            'term_kk' => 'Келін',
                            'term_ru' => 'Келін (сноха / невестка)',
                            'code' => 'kelin_brother',
                            'zhurt' => $this->getZhurtForPerson($basePerson, 'paternal'),
                            'explanation' => 'Інісінің әйелі',
                            'badge_class' => 'badge-inlaw',
                        ];
                    }
                }

                // Күйеу бала / Жезде: муж кого-то из нашей семьи
                if ($hId === $targetId && isset($allPersonsById[$wId])) {
                    $wife = $allPersonsById[$wId];

                    // Күйеу бала: муж дочери
                    if ((int)($wife['father_id'] ?? 0) === $baseId || (int)($wife['mother_id'] ?? 0) === $baseId) {
                        return [
                            'term_kk' => 'Күйеу бала',
                            'term_ru' => 'Күйеу бала (зять)',
                            'code' => 'kuyeubala',
                            'zhurt' => $this->getZhurtForPerson($basePerson, 'paternal'),
                            'explanation' => 'Қызының күйеуі',
                            'badge_class' => 'badge-inlaw',
                        ];
                    }

                    // Жезде: муж старшей сестры
                    if ($this->areSiblings($wife, $basePerson) && $this->isOlderThan($wife, $basePerson)) {
                        return [
                            'term_kk' => 'Жезде',
                            'term_ru' => 'Жезде (муж старшей сестры)',
                            'code' => 'zhezde',
                            'zhurt' => $this->getZhurtForPerson($basePerson, 'paternal'),
                            'explanation' => 'Әпкесінің күйеуі',
                            'badge_class' => 'badge-inlaw',
                        ];
                    }

                    // Күйеу бала: муж младшей сестры (қарындас/сіңлі)
                    if ($this->areSiblings($wife, $basePerson) && !$this->isOlderThan($wife, $basePerson)) {
                        return [
                            'term_kk' => 'Күйеу бала',
                            'term_ru' => 'Күйеу бала (зять / муж младшей сестры)',
                            'code' => 'kuyeubala_sister',
                            'zhurt' => $this->getZhurtForPerson($basePerson, 'paternal'),
                            'explanation' => 'Қарындасының немесе сіңлісінің күйеуі',
                            'badge_class' => 'badge-inlaw',
                        ];
                    }
                }
            }
        }

        // 10. БРАТ ОТЦА (Дядя по отцу) — ЖАНДОС ШЕШІМІ: «Аға (дядя по отцу)»
        if (!empty($allPersonsById) && $baseFatherId && isset($allPersonsById[$baseFatherId])) {
            $baseFather = $allPersonsById[$baseFatherId];
            if ($this->areSiblings($targetPerson, $baseFather) && $targetGender === 'M') {
                return [
                    'term_kk' => 'Аға',
                    'term_ru' => 'Аға (дядя по отцу)',
                    'code' => 'paternal_uncle',
                    'zhurt' => $this->getZhurtForPerson($basePerson, 'paternal'),
                    'explanation' => 'Әкесінің ағасы немесе інісі',
                    'badge_class' => 'badge-relative',
                ];
            }
            if ($this->areSiblings($targetPerson, $baseFather) && $targetGender === 'F') {
                return [
                    'term_kk' => 'Әпке (Апа)',
                    'term_ru' => 'Әпке (тётя по отцу)',
                    'code' => 'paternal_aunt',
                    'zhurt' => $this->getZhurtForPerson($basePerson, 'paternal'),
                    'explanation' => 'Әкесінің әпкесі немесе қарындасы',
                    'badge_class' => 'badge-relative',
                ];
            }
        }

        // 11. СЫН БРАТА / ДОЧЬ БРАТА — ЖАНДОС ШЕШІМІ: Іні / Қарындас / Сіңлі
        if (!empty($allPersonsById) && $targetFatherId && isset($allPersonsById[$targetFatherId])) {
            $targetFather = $allPersonsById[$targetFatherId];
            if ($this->areSiblings($targetFather, $basePerson)) {
                if ($targetGender === 'M') {
                    return [
                        'term_kk' => 'Іні',
                        'term_ru' => 'Іні (братишка / сын брата)',
                        'code' => 'brother_son',
                        'zhurt' => $this->getZhurtForPerson($basePerson, 'paternal'),
                        'explanation' => 'Бауырының ұлы',
                        'badge_class' => 'badge-relative',
                    ];
                } else {
                    $termKk = $baseGender === 'M' ? 'Қарындас' : 'Сіңлі';
                    $termRu = $baseGender === 'M' ? 'Қарындас (сестрёнка / дочь брата)' : 'Сіңлі (сестрёнка / дочь брата)';
                    return [
                        'term_kk' => $termKk,
                        'term_ru' => $termRu,
                        'code' => 'brother_daughter',
                        'zhurt' => $this->getZhurtForPerson($basePerson, 'paternal'),
                        'explanation' => 'Бауырының қызы',
                        'badge_class' => 'badge-relative',
                    ];
                }
            }
        }

        // 12. ДЕТИ РОДНЫХ БРАТЬЕВ ДРУГ ДРУГУ (Двоюродные братья и сестры по отцу)
        if (!empty($allPersonsById) && $targetFatherId && $baseFatherId && isset($allPersonsById[$targetFatherId]) && isset($allPersonsById[$baseFatherId])) {
            $tFather = $allPersonsById[$targetFatherId];
            $bFather = $allPersonsById[$baseFatherId];
            if ($this->areSiblings($tFather, $bFather)) {
                if ($targetGender === 'M') {
                    $termKk = $isOlder ? 'Аға' : 'Іні';
                    $termRu = $isOlder ? 'Двоюродный брат по отцу (немере аға)' : 'Двоюродный брат по отцу (немере іні)';
                    return [
                        'term_kk' => $termKk,
                        'term_ru' => $termRu,
                        'code' => 'cousin_paternal',
                        'zhurt' => $this->getZhurtForPerson($basePerson, 'paternal'),
                        'explanation' => 'Ағайынды әкелердің балалары',
                        'badge_class' => 'badge-relative',
                    ];
                } else {
                    if ($isOlder) {
                        return [
                            'term_kk' => 'Әпке',
                            'term_ru' => 'Двоюродная сестра по отцу (немере әпке)',
                            'code' => 'cousin_paternal',
                            'zhurt' => $this->getZhurtForPerson($basePerson, 'paternal'),
                            'explanation' => 'Ағайынды әкелердің қыздары',
                            'badge_class' => 'badge-relative',
                        ];
                    }
                    $termKk = $baseGender === 'M' ? 'Қарындас' : 'Сіңлі';
                    $termRu = $baseGender === 'M' ? 'Двоюродная сестра по отцу (немере қарындас)' : 'Двоюродная сестра по отцу (немере сіңлі)';
                    return [
                        'term_kk' => $termKk,
                        'term_ru' => $termRu,
                        'code' => 'cousin_paternal',
                        'zhurt' => $this->getZhurtForPerson($basePerson, 'paternal'),
                        'explanation' => 'Ағайынды әкелердің қыздары',
                        'badge_class' => 'badge-relative',
                    ];
                }
            }
        }

        // 13. АТА МЕН ӘЖЕ (Дедушки и бабушки)
        if (!empty($allPersonsById)) {
            $baseFather = $baseFatherId ? ($allPersonsById[$baseFatherId] ?? null) : null;
            $baseMother = $baseMotherId ? ($allPersonsById[$baseMotherId] ?? null) : null;

            if ($baseFather) {
                if ((int)($baseFather['father_id'] ?? 0) === $targetId) {
                    return $this->formatResult('grandfather_paternal', 'Әкесінің әкесі', 'Ата', 'Дедушка (по отцу)', $basePerson, 'Өз жұрты', 'Төркіні');
                }
                if ((int)($baseFather['mother_id'] ?? 0) === $targetId) {
                    return $this->formatResult('grandmother_paternal', 'Әкесінің анасы', 'Әже', 'Бабушка (по отцу)', $basePerson, 'Өз жұрты', 'Төркіні');
                }

                // Баба (прадед)
                $pGrandfatherId = (int)($baseFather['father_id'] ?? 0);
                if ($pGrandfatherId && isset($allPersonsById[$pGrandfatherId])) {
                    $pGrandfather = $allPersonsById[$pGrandfatherId];
                    if ((int)($pGrandfather['father_id'] ?? 0) === $targetId) {
                        return $this->formatResult('great_grandfather', 'Атасының әкесі', 'Баба', 'Прадедушка', $basePerson, 'Өз жұрты', 'Төркіні');
                    }
                }
            }

            // Нағашы ата мен әже
            if ($baseMother) {
                if ((int)($baseMother['father_id'] ?? 0) === $targetId) {
                    return $this->formatResult('grandfather_maternal', 'Анасының әкесі', 'Нағашы ата', 'Дедушка по матери', $basePerson, 'Нағашы жұрты', 'Нағашы жұрты');
                }
                if ((int)($baseMother['mother_id'] ?? 0) === $targetId) {
                    return $this->formatResult('grandmother_maternal', 'Анасының анасы', 'Нағашы әже', 'Бабушка по матери', $basePerson, 'Нағашы жұрты', 'Нағашы жұрты');
                }
            }
        }

        // 14. НАҒАШЫ ЖҰРТ (Нағашы аға, Нағашы апа)
        if (!empty($allPersonsById) && $baseMotherId && isset($allPersonsById[$baseMotherId])) {
            $baseMother = $allPersonsById[$baseMotherId];
            if ($this->areSiblings($targetPerson, $baseMother)) {
                if ($targetGender === 'F') {
                    return $this->formatResult('aunt_maternal', 'Анасының сіңлісі/әпкесі', 'Нағашы апа', 'Тётя по матери (нағашы апа)', $basePerson, 'Нағашы жұрты', 'Нағашы жұрты');
                }
                return $this->formatResult('uncle_maternal', 'Анасының ағасы/інісі', 'Нағашы аға', 'Дядя по матери (нағашы аға)', $basePerson, 'Нағашы жұрты', 'Нағашы жұрты');
            }
        }

        // 15. БӨЛЕ (Дети родных сестер матерей друг другу)
        if (!empty($allPersonsById) && $baseMotherId && $targetMotherId && isset($allPersonsById[$baseMotherId]) && isset($allPersonsById[$targetMotherId])) {
            $bMother = $allPersonsById[$baseMotherId];
            $tMother = $allPersonsById[$targetMotherId];
            if ($this->areSiblings($bMother, $tMother)) {
                return [
                    'term_kk' => 'Бөле',
                    'term_ru' => 'Бөле (двоюродный брат/сестра по матери)',
                    'code' => 'bole',
                    'zhurt' => 'Нағашы жұрты',
                    'explanation' => 'Апалы-сіңлілі аналардың балалары',
                    'badge_class' => 'badge-relative',
                ];
            }
        }

        // 16. ЖИЕНДЕР (По сестре ИЛИ по дочери) — НАЗВАНИЕ ПО ФАКТИЧЕСКОМУ ПУТИ
        if (!empty($allPersonsById)) {
            // Жиен по сестре (племянник / племянница)
            if ($targetMotherId && isset($allPersonsById[$targetMotherId])) {
                $tMother = $allPersonsById[$targetMotherId];
                if ($this->areSiblings($tMother, $basePerson)) {
                    $genderWord = $targetGender === 'F' ? 'племянница' : 'племянник';
                    return [
                        'term_kk' => 'Жиен',
                        'term_ru' => "Жиен ({$genderWord} по сестре)",
                        'code' => 'zhien_sister',
                        'zhurt' => $this->getZhurtForPerson($basePerson, 'paternal'),
                        'explanation' => 'Әпкесінің немесе қарындасының (сіңлісінің) баласы',
                        'badge_class' => 'badge-relative',
                    ];
                }
            }

            // Жиен по дочери (внук / внучка по дочери)
            if ($targetMotherId && isset($allPersonsById[$targetMotherId])) {
                $tMother = $allPersonsById[$targetMotherId];
                if ((int)($tMother['father_id'] ?? 0) === $baseId || (int)($tMother['mother_id'] ?? 0) === $baseId) {
                    $genderWord = $targetGender === 'F' ? 'внучка' : 'внук';
                    return [
                        'term_kk' => 'Жиен',
                        'term_ru' => "Жиен ({$genderWord} по дочери)",
                        'code' => 'zhien_daughter',
                        'zhurt' => $this->getZhurtForPerson($basePerson, 'paternal'),
                        'explanation' => 'Өз қызынан туған жиен немересі',
                        'badge_class' => 'badge-relative',
                    ];
                }
            }

            // Жиеншар
            if ($targetMotherId && isset($allPersonsById[$targetMotherId])) {
                $tMother = $allPersonsById[$targetMotherId];
                $tmMotherId = (int)($tMother['mother_id'] ?? 0);
                if ($tmMotherId && isset($allPersonsById[$tmMotherId])) {
                    $tmMother = $allPersonsById[$tmMotherId];
                    if ($this->areSiblings($tmMother, $basePerson) || (int)($tmMother['father_id'] ?? 0) === $baseId) {
                        return [
                            'term_kk' => 'Жиеншар',
                            'term_ru' => 'Жиеншар (ребенок жиена)',
                            'code' => 'zhien_shar',
                            'zhurt' => $this->getZhurtForPerson($basePerson, 'paternal'),
                            'explanation' => 'Жиеннен туған ұрпақ',
                            'badge_class' => 'badge-relative',
                        ];
                    }
                }
            }
        }

        // 17. НЕМЕРЕ, ШӨБЕРЕ, ШӨПШЕК (Прямые потомки по сыну)
        if (!empty($allPersonsById)) {
            $targetFather = $targetFatherId ? ($allPersonsById[$targetFatherId] ?? null) : null;
            if ($targetFather && ((int)($targetFather['father_id'] ?? 0) === $baseId || (int)($targetFather['mother_id'] ?? 0) === $baseId)) {
                $genderWord = $targetGender === 'F' ? 'Внучка' : 'Внук';
                return [
                    'term_kk' => 'Немере',
                    'term_ru' => "Немере ({$genderWord} от сына)",
                    'code' => 'grandson_son',
                    'zhurt' => $this->getZhurtForPerson($basePerson, 'paternal'),
                    'explanation' => 'Ұлының баласы',
                    'badge_class' => 'badge-relative',
                ];
            }
            if ($targetFather) {
                $tfId = (int)($targetFather['father_id'] ?? 0);
                if ($tfId && isset($allPersonsById[$tfId])) {
                    $grandFa = $allPersonsById[$tfId];
                    if ((int)($grandFa['father_id'] ?? 0) === $baseId || (int)($grandFa['mother_id'] ?? 0) === $baseId) {
                        return [
                            'term_kk' => 'Шөбере',
                            'term_ru' => 'Шөбере (правнук / правнучка)',
                            'code' => 'great_grandson',
                            'zhurt' => $this->getZhurtForPerson($basePerson, 'paternal'),
                            'explanation' => 'Немересінің баласы',
                            'badge_class' => 'badge-relative',
                        ];
                    }
                }
            }
        }

        // 18. ПРЯМЫЕ ПРЕДКИ-ОСНОВАТЕЛИ (Керей баба, Алтыбай баба и др.)
        if (!empty($targetPerson['is_trunk']) || !empty($targetPerson['is_founder']) || ($targetYear !== null && $targetYear < 1910 && $targetGender === 'M')) {
            return [
                'term_kk' => 'Баба',
                'term_ru' => 'Баба (Предок-основатель)',
                'code' => 'ancestor',
                'zhurt' => $this->getZhurtForPerson($basePerson, 'paternal'),
                'explanation' => 'Тікелей ата-баба желісі',
                'badge_class' => 'badge-ancestor',
            ];
        }

        // 19. ЕСЛИ ИЗ ОДНОГО РОДА (Керей руы) — СТРОГО БЕЗ «СОРОДИЧ / СОПЛЕМЕННИК»!
        $targetClan = trim((string)($targetPerson['clan_ru'] ?? ''));
        $baseClan = trim((string)($basePerson['clan_ru'] ?? ''));
        if ($targetClan !== '' && $targetClan !== 'белгісіз' && $targetClan === $baseClan) {
            return [
                'term_kk' => 'Ағайын',
                'term_ru' => "Родственник по роду {$targetClan}",
                'code' => 'clan_relative',
                'zhurt' => $this->getZhurtForPerson($basePerson, 'paternal'),
                'explanation' => "{$targetClan} руының туысы",
                'badge_class' => 'badge-relative',
            ];
        }

        // 20. ДАЛЬНИЕ РОДСТВЕННИКИ (Без заглушек)
        return [
            'term_kk' => 'Туысқан',
            'term_ru' => 'Родственник',
            'code' => 'relative',
            'zhurt' => $this->getZhurtForPerson($basePerson, 'paternal'),
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
     * @return array<int, array>
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

        // TASK-020: предки и жёны/мужья предков — термин по пути родства и степени
        foreach ($this->ancestorTerms($baseId, $allPersonsById, $marriages) as $aId => $t) {
            if (!isset($result[$aId])) {
                continue;
            }
            $code = $result[$aId]['code'] ?? '';
            // Супруг(а) предка перекрываем только общими «заглушками», точные термины не трогаем
            if (!empty($t['spouse']) && !in_array($code, ['ancestor', 'relative', 'clan_relative'], true)) {
                continue;
            }
            $result[$aId]['term_kk'] = $t['term_kk'];
            $result[$aId]['term_ru'] = $t['term_ru'];
            $result[$aId]['code'] = $t['code'];
            $result[$aId]['explanation'] = $t['explanation'];
        }

        // TASK-013: жұрт определяется по ПУТИ родства, а не по термину/фамилии/роду.
        $sides = $this->computeZhurtSides($baseId, $allPersonsById, $marriages);
        foreach ($result as $pId => $kin) {
            if ($pId === $baseId) {
                continue;
            }
            $code = $kin['code'] ?? '';
            if ($code === 'wife' || $code === 'husband') {
                $result[$pId]['zhurt'] = '';
                $result[$pId]['zhurt_key'] = '';
                continue;
            }
            $side = $sides[$pId] ?? null;
            if ($side === null) {
                // Пути родства нет (не связан с человеком в шежіре) — ни в один жұрт не попадает.
                $result[$pId]['zhurt'] = '';
                $result[$pId]['zhurt_key'] = '';
                continue;
            }
            $result[$pId]['zhurt'] = self::ZHURT_LABELS[$side['side']]['kk'];
            $result[$pId]['zhurt_key'] = $side['side'];
            $result[$pId]['zhurt_dist'] = $side['dist'];
            if (!empty($side['second'])) {
                $result[$pId]['zhurt_second'] = self::ZHURT_LABELS[$side['second']]['kk'];
                $result[$pId]['zhurt_second_key'] = $side['second'];
            }
            if (!empty($side['uzatylgan'])) {
                $result[$pId]['uzatylgan'] = $side['uzatylgan'];
            }
        }

        return $result;
    }

    /**
     * TASK-020: термины для прямых предков (по пути отец/мать и степени) и их супругов.
     * Степень 2 — ата/әже, 3 — үлкен ата/үлкен әже, 4+ — баба/үлкен әже (N-е колено).
     * Через мать — с префиксом «Нағашы».
     *
     * @return array<int, array{term_kk:string, term_ru:string, code:string, explanation:string, spouse?:bool}>
     */
    protected function ancestorTerms(int $baseId, array $personsById, array $marriages): array
    {
        $base = $personsById[$baseId] ?? null;
        if (!$base) {
            return [];
        }
        // Обход вверх: [id => [степень, через_мать]]
        $anc = [];
        $queue = [];
        foreach (['father_id' => false, 'mother_id' => true] as $col => $viaMother) {
            $pid = (int)($base[$col] ?? 0);
            if ($pid && isset($personsById[$pid]) && !isset($anc[$pid])) {
                $anc[$pid] = [1, $viaMother];
                $queue[] = $pid;
            }
        }
        for ($h = 0; $h < count($queue); $h++) {
            $cur = $queue[$h];
            [$deg, $viaM] = $anc[$cur];
            foreach (['father_id', 'mother_id'] as $col) {
                $pid = (int)($personsById[$cur][$col] ?? 0);
                if ($pid && $pid !== $baseId && isset($personsById[$pid]) && !isset($anc[$pid])) {
                    $anc[$pid] = [$deg + 1, $viaM];
                    $queue[] = $pid;
                }
            }
        }

        $make = function (string $gender, int $deg, bool $viaM, bool $spouse): ?array {
            $f = ($gender === 'F');
            if ($deg < 2) {
                return null; // родители обработаны основным правилом
            }
            if ($deg === 2) {
                $kk = $f ? 'Әже' : 'Ата';
                $ru = $f ? 'Бабушка' : 'Дедушка';
            } elseif ($deg === 3) {
                $kk = $f ? 'Үлкен әже' : 'Үлкен ата';
                $ru = $f ? 'Прабабушка' : 'Прадедушка';
            } else {
                $kk = $f ? 'Үлкен әже' : 'Баба';
                $ru = ($f ? 'Прародительница' : 'Предок') . ", {$deg}-е колено";
            }
            if ($viaM) {
                $kk = 'Нағашы ' . mb_strtolower(mb_substr($kk, 0, 1)) . mb_substr($kk, 1);
                $ru .= ' по матери';
            } else {
                $ru .= ' по отцу';
            }
            return [
                'term_kk' => $kk,
                'term_ru' => $ru,
                'code' => ($f ? 'ancestress_' : 'ancestor_') . $deg . ($viaM ? '_m' : '_p'),
                'explanation' => $ru,
                'spouse' => $spouse,
            ];
        };

        $out = [];
        foreach ($anc as $aId => [$deg, $viaM]) {
            $t = $make((string)($personsById[$aId]['gender'] ?? 'M'), $deg, $viaM, false);
            if ($t) {
                $out[$aId] = $t;
            }
        }
        // Супруги предков (не кровные предки сами): әже/ата по степени супруга
        foreach ($marriages as $m) {
            $h = (int)($m['husband_id'] ?? 0);
            $w = (int)($m['wife_id'] ?? 0);
            foreach ([[$h, $w], [$w, $h]] as [$a, $s]) {
                if ($a && $s && isset($anc[$a]) && !isset($anc[$s]) && $s !== $baseId && isset($personsById[$s]) && !isset($out[$s])) {
                    $t = $make((string)($personsById[$s]['gender'] ?? 'F'), $anc[$a][0], $anc[$a][1], true);
                    if ($t) {
                        $out[$s] = $t;
                    }
                }
            }
        }

        // Потомки: бала → немере → шөбере → шөпшек → немене → туажат → жүрежат; дети дочери — жиен, жиеншар
        $childrenOf = [];
        foreach ($personsById as $p) {
            foreach (['father_id', 'mother_id'] as $col) {
                $pid = (int)($p[$col] ?? 0);
                if ($pid && isset($personsById[$pid])) {
                    $childrenOf[$pid][] = (int)$p['id'];
                }
            }
        }
        $desc = [];
        $dq = [];
        foreach (array_unique($childrenOf[$baseId] ?? []) as $c) {
            $desc[$c] = [1, ($personsById[$c]['gender'] ?? 'M') === 'F'];
            $dq[] = $c;
        }
        for ($h = 0; $h < count($dq); $h++) {
            $cur = $dq[$h];
            [$deg, $viaDau] = $desc[$cur];
            foreach (array_unique($childrenOf[$cur] ?? []) as $c) {
                if ($c !== $baseId && !isset($desc[$c])) {
                    $desc[$c] = [$deg + 1, $viaDau];
                    $dq[] = $c;
                }
            }
        }
        $ladder = [2 => 'Немере', 3 => 'Шөбере', 4 => 'Шөпшек', 5 => 'Немене', 6 => 'Туажат', 7 => 'Жүрежат'];
        $ladderRu = [2 => 'внук', 3 => 'правнук', 4 => 'праправнук', 5 => 'потомок 5-го колена', 6 => 'потомок 6-го колена', 7 => 'потомок 7-го колена'];
        foreach ($desc as $dId => [$deg, $viaDau]) {
            if ($deg < 2) {
                continue;
            }
            $f = (($personsById[$dId]['gender'] ?? 'M') === 'F');
            if ($viaDau && $deg <= 3) {
                $kk = $deg === 2 ? 'Жиен' : 'Жиеншар';
                $ru = ($deg === 2 ? ($f ? 'внучка' : 'внук') : ($f ? 'правнучка' : 'правнук')) . ' по дочери';
            } elseif (isset($ladder[$deg])) {
                $kk = $ladder[$deg];
                $ru = $ladderRu[$deg];
                if ($deg <= 4 && $f) {
                    $ru = str_replace('внук', 'внучка', $ru);
                }
            } else {
                $kk = 'Ұрпақ';
                $ru = "потомок, {$deg}-е колено";
            }
            $out[$dId] = [
                'term_kk' => $kk,
                'term_ru' => $ru,
                'code' => 'descendant_' . $deg . ($viaDau ? '_d' : ''),
                'explanation' => $ru,
                'spouse' => false,
            ];
        }
        return $out;
    }

    /**
     * Названия жұрт (TASK-013). Қайын жұрты бывает только у мужчины.
     */
    public const ZHURT_LABELS = [
        'oz' => ['kk' => 'Өз жұрты', 'ru' => 'Өз жұрты'],
        'torkin' => ['kk' => 'Төркіні', 'ru' => 'Төркіні'],
        'nagashy' => ['kk' => 'Нағашы жұрты', 'ru' => 'Нағашы жұрты'],
        'kaiyn' => ['kk' => 'Қайын жұрты', 'ru' => 'Қайын жұрты'],
    ];

    /**
     * Замужем ли / женат ли человек (есть хотя бы один брак в шежіре).
     */
    public function hasSpouse(int $personId, array $marriages): bool
    {
        // TASK-020: расторгнутый брак — не текущий (дочь после развода снова незамужняя)
        $active = array_filter($marriages, fn($m) => !$this->isDissolvedMarriage($m));
        return !empty($this->getSpouseIds($personId, $active));
    }

    /** Брак расторгнут: статус divorced, либо указана дата развода/окончания, либо is_current=0 без статуса. */
    protected function isDissolvedMarriage(array $m): bool
    {
        $status = $m['status'] ?? null;
        if ($status !== null && $status !== '') {
            if ($status !== 'married') return true;
        } elseif (array_key_exists('is_current', $m) && $m['is_current'] !== null && empty($m['is_current'])) {
            return true;
        }
        return !empty($m['divorce_date']) || !empty($m['end_date']);
    }

    /**
     * TASK-013: стороны жұрт по пути родства от выбранного человека.
     *
     * Обход графа (родители, дети, супруги) отдельно для каждой «ветки» первого шага:
     *  - paternal — через отца (и мать как жену отца, без её родителей);
     *  - maternal — через родителей матери;
     *  - spouse   — через мужа/жену;
     *  - children — через своих детей (их супруги, внуки; родители супругов детей — құда).
     * По кровным связям обход идёт дальше (вверх — по отцу; у матери любого предка её родители
     * не обходятся — это чужой нағашы жұрт); супруг(а) кровного родственника (келін, жезде) входит,
     * но его/её родня дальше не обходится. Поэтому замужняя дочь рода — «ұзатылған»: её муж и дети
     * видны, а семья мужа — нет.
     *
     * Сторона = ветка с ближайшим путём (при равенстве: дети, отец, супруг, мать).
     * Вторая сторона (двойная связь) — ближайшая ветка с другой стороной.
     *
     * @return array<int, array{side: string, group: string, dist: int, second: ?string, uzatylgan: ?array}>
     */
    public function computeZhurtSides(int $baseId, array $personsById, array $marriages): array
    {
        $base = $personsById[$baseId] ?? null;
        if (!$base) {
            return [];
        }

        // Граф
        $parentsOf = [];
        $childrenOf = [];
        $spousesOf = [];
        foreach ($personsById as $p) {
            $id = (int)$p['id'];
            foreach (['father_id', 'mother_id'] as $col) {
                $pid = (int)($p[$col] ?? 0);
                if ($pid && isset($personsById[$pid])) {
                    $parentsOf[$id][] = $pid;
                    $childrenOf[$pid][] = $id;
                }
            }
        }
        foreach ($marriages as $m) {
            $h = (int)($m['husband_id'] ?? 0);
            $w = (int)($m['wife_id'] ?? 0);
            if ($h && $w && isset($personsById[$h], $personsById[$w]) && !$this->isDissolvedMarriage($m)) {
                $spousesOf[$h][] = $w;
                $spousesOf[$w][] = $h;
            }
        }

        $isFemale = (($base['gender'] ?? 'M') === 'F');
        $isMarried = !empty($spousesOf[$baseId]);
        $fatherId = (int)($base['father_id'] ?? 0);
        $motherId = (int)($base['mother_id'] ?? 0);
        if (!isset($personsById[$fatherId])) $fatherId = 0;
        if (!isset($personsById[$motherId])) $motherId = 0;
        $spouseIds = array_values(array_unique($spousesOf[$baseId] ?? []));
        $childIds = array_values(array_unique($childrenOf[$baseId] ?? []));

        // Ядро: сам человек, родители, супруги, дети — в чужие ветки не входят
        $core = [$baseId => true];
        if ($fatherId) $core[$fatherId] = true;
        if ($motherId) $core[$motherId] = true;
        foreach ($spouseIds as $s) $core[$s] = true;
        foreach ($childIds as $c) $core[$c] = true;

        // Семена веток: [id, режим, расстояние]
        $seeds = ['children' => [], 'paternal' => [], 'spouse' => [], 'maternal' => []];
        foreach ($childIds as $c) $seeds['children'][] = [$c, 'blood', 1];
        if ($fatherId) $seeds['paternal'][] = [$fatherId, 'blood', 1];
        if ($motherId) $seeds['paternal'][] = [$motherId, 'blood_noup', 1];
        foreach ($spouseIds as $s) $seeds['spouse'][] = [$s, 'blood', 1];
        if ($motherId) {
            $mother = $personsById[$motherId];
            foreach (['father_id', 'mother_id'] as $col) {
                $gp = (int)($mother[$col] ?? 0);
                if ($gp && isset($personsById[$gp]) && !isset($core[$gp])) {
                    $seeds['maternal'][] = [$gp, 'blood', 2];
                }
            }
        }

        // Обход каждой ветки
        $reach = []; // [personId][group] = ['dist' => n, 'mode' => m]
        foreach ($seeds as $group => $groupSeeds) {
            $visited = [];
            $queue = [];
            foreach ($groupSeeds as [$sid, $mode, $dist]) {
                if (isset($visited[$sid])) continue;
                $visited[$sid] = ['dist' => $dist, 'mode' => $mode];
                $queue[] = $sid;
            }
            $ownSeeds = array_column($groupSeeds, 0);
            $head = 0;
            while ($head < count($queue)) {
                $cur = $queue[$head++];
                $curMode = $visited[$cur]['mode'];
                $curDist = $visited[$cur]['dist'];
                $next = [];
                switch ($curMode) {
                    case 'blood':
                        // Вверх по отцу — кровная линия; мать (жена линии) — без её родителей:
                        // её родня — чужой нағашы жұрт, не наша сторона.
                        $curMotherId = (int)($personsById[$cur]['mother_id'] ?? 0);
                        foreach ($parentsOf[$cur] ?? [] as $x) $next[] = [$x, $x === $curMotherId ? 'blood_noup' : 'blood'];
                        foreach ($childrenOf[$cur] ?? [] as $x) $next[] = [$x, 'blood'];
                        foreach ($spousesOf[$cur] ?? [] as $x) $next[] = [$x, 'inlaw'];
                        break;
                    case 'blood_noup':
                        // Мать как жена отца: её родители — Нағашы; её дети от другого брака не ведут к чужому отцу
                        foreach ($childrenOf[$cur] ?? [] as $x) $next[] = [$x, 'blood_noup'];
                        foreach ($spousesOf[$cur] ?? [] as $x) $next[] = [$x, 'inlaw'];
                        break;
                    case 'inlaw':
                        // Родня супруга(и) кровного родственника не обходится.
                        // Исключение — супруги своих детей: их родители и братья/сёстры — құда.
                        if ($group === 'children') {
                            foreach ($parentsOf[$cur] ?? [] as $x) $next[] = [$x, 'quda'];
                        }
                        break;
                    case 'quda':
                        foreach ($childrenOf[$cur] ?? [] as $x) $next[] = [$x, 'quda_end'];
                        break;
                    default:
                        break;
                }
                foreach ($next as [$nid, $nmode]) {
                    if ($nid === $baseId || isset($visited[$nid])) continue;
                    if (isset($core[$nid]) && !in_array($nid, $ownSeeds, true)) continue;
                    $visited[$nid] = ['dist' => $curDist + 1, 'mode' => $nmode];
                    $queue[] = $nid;
                }
            }
            foreach ($visited as $pid => $info) {
                $reach[$pid][$group] = $info;
            }
        }

        // Сторона ветки
        $sideOf = function (string $group, string $mode) use ($isFemale, $isMarried): string {
            if (!$isFemale) {
                if ($group === 'paternal') return 'oz';
                if ($group === 'maternal') return 'nagashy';
                if ($group === 'spouse') return 'kaiyn';
                return ($mode === 'quda' || $mode === 'quda_end') ? 'kaiyn' : 'oz';
            }
            if ($group === 'paternal') return 'torkin';
            if ($group === 'maternal') return 'nagashy';
            if ($group === 'spouse') return 'oz';
            // Свои дети и их семьи: у замужней — дом мужа (Өз жұрты), у незамужней — Төркіні
            return $isMarried ? 'oz' : 'torkin';
        };

        $priority = ['children' => 0, 'paternal' => 1, 'spouse' => 2, 'maternal' => 3];
        $result = [];
        foreach ($reach as $pid => $groups) {
            uksort($groups, function ($a, $b) use ($groups, $priority) {
                $d = $groups[$a]['dist'] <=> $groups[$b]['dist'];
                return $d !== 0 ? $d : ($priority[$a] <=> $priority[$b]);
            });
            $primaryGroup = (string)array_key_first($groups);
            $primary = $groups[$primaryGroup];
            $side = $sideOf($primaryGroup, $primary['mode']);
            $second = null;
            foreach ($groups as $g => $info) {
                $s = $sideOf($g, $info['mode']);
                if ($s !== $side) {
                    $second = $s;
                    break;
                }
            }

            // След ұзатылған: замужняя женщина, пришедшая в эту сторону по крови (дочь/сестра рода),
            // её муж — не кровный в этой же ветке. Её основное место — у мужа.
            $uzatylgan = null;
            $p = $personsById[$pid];
            if (($p['gender'] ?? 'M') === 'F' && in_array($primary['mode'], ['blood', 'blood_noup'], true) && !empty($spousesOf[$pid])) {
                $husbandId = 0;
                foreach (array_unique($spousesOf[$pid]) as $hId) {
                    $hInfo = $reach[$hId][$primaryGroup] ?? null;
                    if ($hInfo && in_array($hInfo['mode'], ['blood', 'blood_noup'], true)) {
                        $husbandId = 0; // муж — тоже кровный родственник этой ветки
                        break;
                    }
                    if ($hId !== $baseId && !$husbandId) {
                        $husbandId = $hId;
                    }
                }
                if ($husbandId) {
                    $h = $personsById[$husbandId];
                    $uzatylgan = [
                        'husband_id' => $husbandId,
                        'husband_name' => trim(($h['first_name'] ?? '') . ' ' . ($h['last_name'] ?? '')),
                        'husband_last_name' => (string)($h['last_name'] ?? ''),
                        'husband_clan' => (string)($h['clan_ru'] ?? ''),
                    ];
                }
            }

            $result[$pid] = [
                'side' => $side,
                'group' => $primaryGroup,
                'dist' => $primary['dist'],
                'second' => $second,
                'uzatylgan' => $uzatylgan,
            ];
        }

        // Мать — жена отца, её место в семье отца, не след
        if ($motherId && isset($result[$motherId])) {
            $result[$motherId]['uzatylgan'] = null;
        }
        // Супруг(а) показывается отдельно, вне таблиц
        foreach ($spouseIds as $s) {
            unset($result[$s]);
        }
        unset($result[$baseId]);

        return $result;
    }

    /**
     * Загрузить данные из базы данных при их доступности
     */
    protected function loadDatasetIfAvailable(): void
    {
        if ($this->cachedPersons !== null) {
            return;
        }

        $this->cachedPersons = [];
        $this->cachedMarriages = [];

        try {
            if (class_exists(\App\Core\Database::class)) {
                $db = \App\Core\Database::getConnection();
                $stmtP = $db->query("SHOW TABLES LIKE 'persons'");
                if ($stmtP && $stmtP->rowCount() > 0) {
                    $stmt = $db->query("SELECT * FROM `persons` WHERE `is_deleted` = 0 ORDER BY `id` ASC");
                    $persons = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    foreach ($persons as $p) {
                        $this->cachedPersons[(int)$p['id']] = $p;
                    }
                }
                $stmtM = $db->query("SHOW TABLES LIKE 'marriages'");
                if ($stmtM && $stmtM->rowCount() > 0) {
                    $mStmt = $db->query("SELECT * FROM `marriages` ORDER BY `id` ASC");
                    $this->cachedMarriages = $mStmt->fetchAll(\PDO::FETCH_ASSOC);
                }
            }
        } catch (\Throwable $e) {
            // В автономных тестах или без БД тихо продолжаем с пустыми данными
        }
    }

    /**
     * Установить контекст данных вручную (для тестов или предварительно загруженных наборов)
     *
     * @param array<int, array> $persons
     * @param array<int, array> $marriages
     */
    public function setDataset(array $persons, array $marriages = []): void
    {
        $this->cachedPersons = [];
        foreach ($persons as $p) {
            $this->cachedPersons[(int)$p['id']] = $p;
        }
        $this->cachedMarriages = $marriages;
        $this->kinshipCache = [];
    }

    /**
     * Получить описание родства между базовой персоной и целевой
     *
     * @param int $baseId Базовая персона (вошедший пользователь / фокус)
     * @param int $targetId Персона, для которой определяется статус
     * @param array<int, array> $persons Опциональный список всех персон
     * @param array<int, array> $marriages Опциональный список браков
     * @return array{term_kk: string, term_ru: string, code: string, zhurt: string, explanation: string, badge_class: string}|null
     */
    public function getKinshipDescription(int $baseId, int $targetId, array $persons = [], array $marriages = []): ?array
    {
        if ($baseId <= 0 || $targetId <= 0) {
            return null;
        }

        if ($baseId === $targetId) {
            return [
                'term_kk' => 'Мен',
                'term_ru' => 'Я',
                'code' => 'self',
                'zhurt' => '',
                'explanation' => 'Мен / Я',
                'badge_class' => 'badge-self',
            ];
        }

        if (!empty($persons) && ($this->cachedPersons === null || empty($this->cachedPersons))) {
            $this->setDataset($persons, $marriages);
        }

        if (isset($this->kinshipCache[$baseId][$targetId])) {
            return $this->kinshipCache[$baseId][$targetId];
        }

        $this->loadDatasetIfAvailable();

        $basePerson = $this->cachedPersons[$baseId] ?? null;
        $targetPerson = $this->cachedPersons[$targetId] ?? null;

        if (!$basePerson || !$targetPerson) {
            return null;
        }

        if (!isset($this->kinshipCache[$baseId])) {
            $this->kinshipCache[$baseId] = $this->calculateAllKinships(
                $baseId,
                $this->cachedPersons,
                $this->cachedMarriages ?? []
            );
        }

        return $this->kinshipCache[$baseId][$targetId] ?? null;
    }

    /**
     * Қолданушыға қатысты түсініктеме сөйлем құрау (Үш жұртты көрсете отырып)
     */
    public function formatUserKinshipPhrase(array $targetPerson, array $userPerson, array $allPersonsById = [], array $marriages = []): string
    {
        $kin = $this->determineKinship($targetPerson, $userPerson, 'auto', $allPersonsById, $marriages);

        if ($kin['code'] === 'self') {
            return '<strong>Сіз осындасыз</strong> (Бұл сіздің жеке карточкаңыз)';
        }

        $zhurtTag = !empty($kin['zhurt']) ? "<span class=\"zhurt-pill\">{$kin['zhurt']}</span> " : "";

        return "{$zhurtTag}Бұл кісі сізге: <strong>{$kin['term_kk']}</strong> <span style=\"opacity:0.85; font-size:12px;\">({$kin['term_ru']})</span>";
    }

    /**
     * Определение Үш жұрт для персоны
     */
    public function getZhurtForPerson(array $basePerson, string $category = 'paternal'): string
    {
        $isFemale = ($basePerson['gender'] ?? 'M') === 'F';

        switch ($category) {
            case 'self':
            case 'spouse':
                return '';
            case 'maternal':
            case 'maternal_cousin':
                return 'Нағашы жұрты';
            case 'in_law':
            case 'in_law_special':
                // TASK-013: у замужней женщины семья мужа — её Өз жұрты, қайын жұрта у неё нет
                return $isFemale ? 'Өз жұрты' : 'Қайын жұрты';
            case 'paternal':
            case 'direct':
            default:
                return $isFemale ? 'Төркіні' : 'Өз жұрты';
        }
    }

    protected function formatResult(
        string $code,
        string $explanation,
        string $termKk,
        string $termRu,
        array $basePerson,
        string $zhurtMale = 'Өз жұрты',
        string $zhurtFemale = 'Төркіні'
    ): array {
        $zhurt = (($basePerson['gender'] ?? 'M') === 'F') ? $zhurtFemale : $zhurtMale;
        return [
            'term_kk' => $termKk,
            'term_ru' => $termRu,
            'code' => $code,
            'zhurt' => $zhurt,
            'explanation' => $explanation,
            'badge_class' => 'badge-relative',
        ];
    }

    public function areSiblings(array $p1, array $p2): bool
    {
        $p1Id = (int)($p1['id'] ?? 0);
        $p2Id = (int)($p2['id'] ?? 0);
        if ($p1Id && $p2Id && $p1Id === $p2Id) {
            return false;
        }

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
            $h = (int)($m['husband_id'] ?? 0);
            $w = (int)($m['wife_id'] ?? 0);
            if (($h === $id1 && $w === $id2) || ($h === $id2 && $w === $id1)) {
                return true;
            }
        }
        return false;
    }

    protected function getSpouseIds(int $personId, array $marriages): array
    {
        $spouses = [];
        foreach ($marriages as $m) {
            $h = (int)($m['husband_id'] ?? 0);
            $w = (int)($m['wife_id'] ?? 0);
            if ($h === $personId && $w) {
                $spouses[] = $w;
            } elseif ($w === $personId && $h) {
                $spouses[] = $h;
            }
        }
        return array_unique($spouses);
    }

    protected function getChildrenIds(int $personId, array $allPersonsById): array
    {
        $children = [];
        foreach ($allPersonsById as $p) {
            $fId = (int)($p['father_id'] ?? 0);
            $mId = (int)($p['mother_id'] ?? 0);
            if ($fId === $personId || $mId === $personId) {
                $children[] = (int)$p['id'];
            }
        }
        return array_unique($children);
    }

    protected function isOlderThan(array $p1, array $p2): bool
    {
        $y1 = $this->extractYear($p1['birth_year'] ?? $p1['birth_date'] ?? null);
        $y2 = $this->extractYear($p2['birth_year'] ?? $p2['birth_date'] ?? null);
        if ($y1 !== null && $y2 !== null) {
            return $y1 < $y2;
        }
        return (int)($p1['id'] ?? 0) < (int)($p2['id'] ?? 0);
    }

    protected function extractYear(mixed $val): ?int
    {
        if (empty($val)) {
            return null;
        }
        if (preg_match('/(1[0-9]{3}|20[0-9]{2})/', (string)$val, $m)) {
            return (int)$m[1];
        }
        return null;
    }

    /**
     * TASK-013: порядок таблиц жұрт.
     * Мужчина: Өз жұрты, Қайын жұрты, Нағашы жұрты.
     * Женщина: Төркіні, Өз жұрты (семья мужа), Нағашы жұрты — қайын жұрта нет.
     *
     * @return string[]
     */
    public function zhurtOrder(bool $isFemale): array
    {
        return $isFemale ? ['torkin', 'oz', 'nagashy'] : ['oz', 'kaiyn', 'nagashy'];
    }

    /**
     * TASK-013: подписи таблицы жұрт на двух языках.
     *
     * @return array{title_kk: string, title_ru: string, desc_kk: string, desc_ru: string, empty_kk: string, empty_ru: string}
     */
    public function zhurtMeta(string $key, bool $isFemale, bool $isMarried): array
    {
        $empty = ['empty_kk' => 'Әзірге ешкім қосылмаған', 'empty_ru' => 'Пока никого не добавили'];
        switch ($key) {
            case 'torkin':
                return [
                    'title_kk' => 'Төркіні',
                    'title_ru' => 'Төркіні (родная семья)',
                    'desc_kk' => 'Туған әке-шешесі, бауырлары және олардың отбасылары, әкесінің руы',
                    'desc_ru' => 'Родная семья по рождению: отец, мать, братья и сёстры с семьями, род отца',
                ] + $empty;
            case 'nagashy':
                return [
                    'title_kk' => 'Нағашы жұрты',
                    'title_ru' => 'Нағашы жұрты (родня матери)',
                    'desc_kk' => 'Анасының туған-туыстары (нағашылар)',
                    'desc_ru' => 'Родня матери (нағашылар)',
                ] + $empty;
            case 'kaiyn':
                return [
                    'title_kk' => 'Қайын жұрты',
                    'title_ru' => 'Қайын жұрты (родня жены)',
                    'desc_kk' => 'Әйелінің туған-туыстары',
                    'desc_ru' => 'Родня жены',
                ] + $empty;
            case 'oz':
            default:
                if (!$isFemale) {
                    return [
                        'title_kk' => 'Өз жұрты',
                        'title_ru' => 'Өз жұрты (родня по отцу)',
                        'desc_kk' => 'Әкесі жағынан туыстары, өз руы',
                        'desc_ru' => 'Родня по отцу, свой род',
                    ] + $empty;
                }
                if (!$isMarried) {
                    return [
                        'title_kk' => 'Өз жұрты',
                        'title_ru' => 'Өз жұрты (семья мужа)',
                        'desc_kk' => 'Күйеуінің отбасы — тұрмысқа шыққаннан кейін толады',
                        'desc_ru' => 'Семья мужа — заполнится после замужества',
                        'empty_kk' => 'Тұрмысқа шыққаннан кейін пайда болады',
                        'empty_ru' => 'Появится после замужества',
                    ];
                }
                return [
                    'title_kk' => 'Өз жұрты',
                    'title_ru' => 'Өз жұрты (семья мужа)',
                    'desc_kk' => 'Күйеуінің отбасы — енді өз жұрты: қайын ата, қайын ене, күйеуінің бауырлары',
                    'desc_ru' => 'Семья мужа — теперь её свои: свёкор, свекровь, братья и сёстры мужа с семьями',
                ] + $empty;
        }
    }

    /**
     * TASK-013: разложить людей по сторонам жұрт (по zhurt_key, вычисленному по пути родства).
     *
     * @return array{spouses: int[], children: int[], oz: int[], torkin: int[], nagashy: int[], kaiyn: int[]}
     */
    protected function splitByZhurt(int $baseId, array $allKinships): array
    {
        $out = ['spouses' => [], 'children' => [], 'oz' => [], 'torkin' => [], 'nagashy' => [], 'kaiyn' => []];
        foreach ($allKinships as $targetId => $kin) {
            if ($targetId === $baseId) continue;
            $code = $kin['code'] ?? '';
            if ($code === 'wife' || $code === 'husband') {
                $out['spouses'][] = $targetId;
                continue;
            }
            if ($code === 'son' || $code === 'daughter' || $code === 'child') {
                $out['children'][] = $targetId;
                continue;
            }
            $key = $kin['zhurt_key'] ?? '';
            if ($key !== '' && isset($out[$key])) {
                $out[$key][] = $targetId;
            }
        }
        return $out;
    }

    /**
     * Қазақтың «Үш жұрт» жүйесін кестелік түрде құру (TASK-011):
     * 1. Өз жұрты (әйел адам үшін — Төркін жұрты)
     * 2. Қайын жұрты (әйел адам үшін — Қайын жұрты / Күйеуінің жағы)
     * 3. Нағашы жұрты
     *
     * Әр кестеде: адамның аты-жөні, туған-өлген жылдары, туыстық атауы (Kinship).
     */
    public function getUshZhurtTables(int $baseId, array $personsById = [], array $marriages = []): array
    {
        if ($baseId <= 0) {
            return [];
        }

        if (!empty($personsById)) {
            $personsById = array_column($personsById, null, 'id');
        } else {
            $this->loadDatasetIfAvailable();
            $personsById = $this->cachedPersons ?? [];
            $marriages = $this->cachedMarriages ?? [];
        }

        $base = $personsById[$baseId] ?? null;
        if (!$base) {
            return [];
        }

        $isFemale = (($base['gender'] ?? 'M') === 'F');
        $isMarried = $this->hasSpouse($baseId, $marriages);
        $allKinships = $this->calculateAllKinships($baseId, $personsById, $marriages);
        $buckets = $this->splitByZhurt($baseId, $allKinships);
        $ownSpouses = $buckets['spouses'];
        $ownChildren = $buckets['children'];

        $formatPersonRow = function(int $pId) use ($personsById, $allKinships) {
            $p = $personsById[$pId] ?? null;
            if (!$p) return null;

            $bYear = !empty($p['birth_year']) ? (int)$p['birth_year'] : null;
            $dYear = !empty($p['death_year']) ? (int)$p['death_year'] : null;
            $isDeceased = (int)($p['is_deceased'] ?? 0);

            $yearsDisplay = '—';
            if ($bYear && $dYear) {
                $yearsDisplay = "{$bYear}–{$dYear}";
            } elseif ($bYear) {
                $yearsDisplay = $isDeceased ? "{$bYear}–?" : "{$bYear}–...";
            } elseif ($dYear) {
                $yearsDisplay = "?–{$dYear}";
            }

            $kin = $allKinships[$pId] ?? [];
            $termKk = $kin['term_kk'] ?? 'Туысқан';
            $termRu = $kin['term_ru'] ?? 'Родственник';
            $fullName = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));

            return [
                'id' => $pId,
                'first_name' => $p['first_name'] ?? '',
                'last_name' => $p['last_name'] ?? '',
                'full_name' => $fullName,
                'maiden_name' => $p['maiden_name'] ?? null,
                'gender' => $p['gender'] ?? 'M',
                'birth_year' => $bYear,
                'birth_date' => $p['birth_date'] ?? null, // TASK-013 (шаг 10): для возраста «38 жас»
                'death_year' => $dYear,
                'death_date' => $p['death_date'] ?? null,
                'years_display' => $yearsDisplay,
                'is_deceased' => $isDeceased,
                'clan_ru' => $p['clan_ru'] ?? 'белгісіз',
                'term_kk' => $termKk,
                'term_ru' => $termRu,
                'code' => $kin['code'] ?? '',
                'explanation' => $kin['explanation'] ?? '',
                'badge_class' => $kin['badge_class'] ?? 'badge-relative',
                'zhurt_key' => $kin['zhurt_key'] ?? '',
                'zhurt_second' => $kin['zhurt_second'] ?? null,
                'zhurt_second_key' => $kin['zhurt_second_key'] ?? null,
                'uzatylgan' => $kin['uzatylgan'] ?? null,
                'seniority' => $bYear ?: 9999,
            ];
        };

        $buildTableData = function(array $ids, string $key, string $titleKk, string $titleRu, string $descKk, string $descRu, string $emptyKk = '', string $emptyRu = '') use ($formatPersonRow) {
            $items = [];
            foreach ($ids as $id) {
                $row = $formatPersonRow($id);
                if ($row) {
                    $items[] = $row;
                }
            }

            usort($items, function($a, $b) {
                $sA = $a['seniority'] ?: 9999;
                $sB = $b['seniority'] ?: 9999;
                if ($sA !== $sB) {
                    return $sA <=> $sB;
                }
                return $a['id'] <=> $b['id'];
            });

            return [
                'key' => $key,
                'title_kk' => $titleKk,
                'title_ru' => $titleRu,
                'desc_kk' => $descKk,
                'desc_ru' => $descRu,
                'empty_kk' => $emptyKk !== '' ? $emptyKk : 'Әзірге ешкім қосылмаған',
                'empty_ru' => $emptyRu !== '' ? $emptyRu : 'Пока никого не добавили',
                'total_count' => count($items),
                'items' => $items,
            ];
        };

        $tablesByKey = [];
        foreach ($this->zhurtOrder($isFemale) as $key) {
            $meta = $this->zhurtMeta($key, $isFemale, $isMarried);
            $tablesByKey[$key] = $buildTableData(
                $buckets[$key] ?? [],
                $key,
                $meta['title_kk'],
                $meta['title_ru'],
                $meta['desc_kk'],
                $meta['desc_ru'],
                $meta['empty_kk'],
                $meta['empty_ru']
            );
        }

        $ownSpousesFormatted = array_values(array_filter(array_map($formatPersonRow, $ownSpouses)));
        $ownChildrenFormatted = array_values(array_filter(array_map($formatPersonRow, $ownChildren)));

        $baseFormatted = $formatPersonRow($baseId);
        if ($baseFormatted) {
            $baseFormatted['term_kk'] = 'Өзі';
            $baseFormatted['term_ru'] = 'Сам(а)';
        }

        return [
            'base_person' => $baseFormatted,
            'is_female' => $isFemale,
            'is_married' => $isMarried,
            'order' => array_keys($tablesByKey),
            'groups' => $tablesByKey,
            'tables' => array_values($tablesByKey),
            'own_family' => [
                'spouses' => $ownSpousesFormatted,
                'children' => $ownChildrenFormatted,
            ],
        ];
    }

    /**
     * Қазақтың «Үш жұрт» жүйесін отбасылық бумалармен («папкалармен») құру (TASK-007):
     * 1. «Өз жұрты» (ер адамдар үшін) немесе «Төркіні» (әйелдер үшін)
     * 2. «Нағашы жұрты»
     * 3. «Қайын жұрты» (ер адамдар үшін) немесе «Күйеуінің жағы» (әйелдер үшін)
     * Жұбайы мен өз балалары жұрттан бөлек, дербес шығарылады.
     *
     * @param int $baseId Бастапқы/таңдалған адамның ID-і
     * @param array<int, array> $personsById Барлық адамдар
     * @param array<int, array> $marriages Барлық некелер
     * @return array
     */
    public function getUshZhurtFamilies(int $baseId, array $personsById = [], array $marriages = []): array
    {
        if ($baseId <= 0) {
            return [];
        }

        if (!empty($personsById)) {
            $personsById = array_column($personsById, null, 'id');
        } else {
            $this->loadDatasetIfAvailable();
            $personsById = $this->cachedPersons ?? [];
            $marriages = $this->cachedMarriages ?? [];
        }

        $base = $personsById[$baseId] ?? null;
        if (!$base) {
            return [];
        }

        $isFemale = (($base['gender'] ?? 'M') === 'F');
        $isMarried = $this->hasSpouse($baseId, $marriages);
        $allKinships = $this->calculateAllKinships($baseId, $personsById, $marriages);
        $buckets = $this->splitByZhurt($baseId, $allKinships);
        $ownSpouses = $buckets['spouses'];
        $ownChildren = $buckets['children'];

        // Карта детей по парам
        $childrenByCouple = [];
        foreach ($personsById as $p) {
            $f = (int)($p['father_id'] ?? 0);
            $m = (int)($p['mother_id'] ?? 0);
            if ($f || $m) {
                $key = "{$f}_{$m}";
                $childrenByCouple[$key][] = $p['id'];
            }
        }

        // Построитель семейных папок
        $buildFamilies = function(array $personIdSet) use ($marriages, $personsById, $allKinships, $childrenByCouple, $baseId) {
            $families = [];
            $handledChildren = [];
            $handledSpouses = [];

            foreach ($marriages as $m) {
                $hId = (int)($m['husband_id'] ?? 0);
                $wId = (int)($m['wife_id'] ?? 0);

                if (isset($personIdSet[$hId]) || isset($personIdSet[$wId])) {
                    $h = $personsById[$hId] ?? null;
                    $w = $personsById[$wId] ?? null;

                    $coupleKey = "{$hId}_{$wId}";
                    $cIds = $childrenByCouple[$coupleKey] ?? [];

                    $childrenList = [];
                    foreach ($cIds as $cId) {
                        $cPerson = $personsById[$cId] ?? null;
                        if ($cPerson) {
                            $childrenList[] = [
                                'person' => $cPerson,
                                'kinship' => $allKinships[$cId] ?? null,
                                'seniority' => (int)($cPerson['birth_year'] ?? 9999),
                            ];
                            $handledChildren[$cId] = true;
                        }
                    }

                    // Сортировка детей внутри семьи по старшинству (по году рождения өсу ретімен)
                    usort($childrenList, fn($a, $b) => $a['seniority'] <=> $b['seniority']);

                    // Старшинство семьи по возрасту мужа (если нет — жены, затем дата брака)
                    $hSeniority = (int)($h['birth_year'] ?? 0);
                    $wSeniority = (int)($w['birth_year'] ?? 0);
                    $seniority = $hSeniority ?: ($wSeniority ?: 9999);

                    $families[] = [
                        'marriage' => $m,
                        'husband' => $h,
                        'wife' => $w,
                        'husband_kinship' => $hId ? ($allKinships[$hId] ?? null) : null,
                        'wife_kinship' => $wId ? ($allKinships[$wId] ?? null) : null,
                        'seniority' => $seniority,
                        'children' => $childrenList,
                    ];

                    if ($hId) $handledSpouses[$hId] = true;
                    if ($wId) $handledSpouses[$wId] = true;
                }
            }

            // Сортировка семей внутри жұрт строго по старшинству мужа
            usort($families, fn($a, $b) => $a['seniority'] <=> $b['seniority']);

            // Отдельные родственники (бойдақ/ата-анасыз тұлғалар)
            $standalone = [];
            foreach (array_keys($personIdSet) as $pId) {
                if (!isset($handledSpouses[$pId]) && !isset($handledChildren[$pId])) {
                    $p = $personsById[$pId] ?? null;
                    if ($p) {
                        $standalone[] = [
                            'person' => $p,
                            'kinship' => $allKinships[$pId] ?? null,
                            'seniority' => (int)($p['birth_year'] ?? 9999),
                        ];
                    }
                }
            }
            usort($standalone, fn($a, $b) => $a['seniority'] <=> $b['seniority']);

            return [
                'families' => $families,
                'standalone' => $standalone,
                'total_count' => count($personIdSet),
            ];
        };

        // TASK-013: стороны по порядку; ключи «{key}_zhurt» (oz_zhurt, torkin_zhurt, nagashy_zhurt, kaiyn_zhurt)
        $result = [
            'base_person' => $base,
            'is_female' => $isFemale,
            'is_married' => $isMarried,
            'order' => [],
        ];
        foreach ($this->zhurtOrder($isFemale) as $key) {
            $meta = $this->zhurtMeta($key, $isFemale, $isMarried);
            $set = array_fill_keys($buckets[$key] ?? [], true);
            $result[$key . '_zhurt'] = array_merge(['key' => $key] + $meta, $buildFamilies($set));
            $result['order'][] = $key;
        }

        return $result + [
            'own_family' => [
                'spouses' => array_values(array_filter(array_map(fn($id) => $personsById[$id] ?? null, $ownSpouses))),
                'children' => array_values(array_filter(array_map(fn($id) => $personsById[$id] ?? null, $ownChildren))),
            ]
        ];
    }
}
