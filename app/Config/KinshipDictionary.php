<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Қазақтың дәстүрлі туыстық атауларының анықтамалығы (Словарь казахских терминов родства)
 * 
 * Жандос пен оның әкесі тексеріп, жаңа терминдер қоса алатын ашық құрылым.
 * Ескерілетін жайттар:
 * 1. Говорящийдің (viewer) жынысы (ер адам немесе әйел адам).
 * 2. Жас ерекшелігі мен туған жылы бойынша үлкен/кішілік (старшинство).
 * 3. Тікелей жұрттар: Өз жұрты (әке жағы), Нағашы жұрт (шеше жағы), Қайын жұрт (жұбай жағы).
 */
class KinshipDictionary
{
    /**
     * Негізгі терминдер кестесі
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getTerms(): array
    {
        return [
            // ========================================================
            // 1. Тікелей ата-баба мен ұрпақтар (Жеті ата және өсу желісі)
            // ========================================================
            'father' => [
                'code' => 'father',
                'kk' => 'Әке',
                'ru' => 'Отец',
                'desc' => 'Өз әкесі (ер адамның немесе әйелдің туған әкесі)',
                'line' => 'direct',
                'generation_diff' => -1,
                'gender' => 'M',
            ],
            'mother' => [
                'code' => 'mother',
                'kk' => 'Ана (Шеше)',
                'ru' => 'Мать',
                'desc' => 'Өз анасы',
                'line' => 'direct',
                'generation_diff' => -1,
                'gender' => 'F',
            ],
            'son' => [
                'code' => 'son',
                'kk' => 'Ұл',
                'ru' => 'Сын',
                'desc' => 'Өз ұлы',
                'line' => 'direct',
                'generation_diff' => 1,
                'gender' => 'M',
            ],
            'daughter' => [
                'code' => 'daughter',
                'kk' => 'Қыз',
                'ru' => 'Дочь',
                'desc' => 'Өз қызы (шаңырақтың қыз баласы, болашақта өзге елге келін)',
                'line' => 'direct',
                'generation_diff' => 1,
                'gender' => 'F',
            ],
            'grandfather_paternal' => [
                'code' => 'grandfather_paternal',
                'kk' => 'Ата',
                'ru' => 'Дедушка (по отцу)',
                'desc' => 'Әкенің әкесі',
                'line' => 'paternal',
                'generation_diff' => -2,
                'gender' => 'M',
            ],
            'grandmother_paternal' => [
                'code' => 'grandmother_paternal',
                'kk' => 'Әже',
                'ru' => 'Бабушка (по отцу)',
                'desc' => 'Әкенің анасы',
                'line' => 'paternal',
                'generation_diff' => -2,
                'gender' => 'F',
            ],
            'great_grandfather' => [
                'code' => 'great_grandfather',
                'kk' => 'Баба (Үлкен ата)',
                'ru' => 'Прадедушка',
                'desc' => 'Атаның әкесі (3-ші буын)',
                'line' => 'paternal',
                'generation_diff' => -3,
                'gender' => 'M',
            ],
            'grandson_son' => [
                'code' => 'grandson_son',
                'kk' => 'Немере (ұлдан)',
                'ru' => 'Внук / Внучка (от сына)',
                'desc' => 'Ұлдың баласы (текті жалғастырушы тікелей ұрпақ)',
                'line' => 'paternal',
                'generation_diff' => 2,
                'gender' => 'any',
            ],
            'great_grandson' => [
                'code' => 'great_grandson',
                'kk' => 'Шөбере',
                'ru' => 'Правнук / Правнучка',
                'desc' => 'Немеренің баласы',
                'line' => 'paternal',
                'generation_diff' => 3,
                'gender' => 'any',
            ],
            'great_great_grandson' => [
                'code' => 'great_great_grandson',
                'kk' => 'Шөпшек',
                'ru' => 'Праправнук',
                'desc' => 'Шөберенің баласы (4-ші буын ұрпақ)',
                'line' => 'paternal',
                'generation_diff' => 4,
                'gender' => 'any',
            ],
            'nemene' => [
                'code' => 'nemene',
                'kk' => 'Немене',
                'ru' => 'Потомок в 5-м колене',
                'desc' => 'Шөпшектің баласы',
                'line' => 'paternal',
                'generation_diff' => 5,
                'gender' => 'any',
            ],

            // ========================================================
            // 2. Бауырлар (Братья и сестры с учетом возраста и пола)
            // ========================================================
            'brother_older' => [
                'code' => 'brother_older',
                'kk' => 'Аға',
                'ru' => 'Старший брат',
                'desc' => 'Өзінен жасы үлкен ер бауыр',
                'line' => 'sibling',
                'generation_diff' => 0,
                'gender' => 'M',
                'seniority' => 'older',
            ],
            'brother_younger' => [
                'code' => 'brother_younger',
                'kk' => 'Іні',
                'ru' => 'Младший брат',
                'desc' => 'Өзінен жасы кіші ер бауыр',
                'line' => 'sibling',
                'generation_diff' => 0,
                'gender' => 'M',
                'seniority' => 'younger',
            ],
            'sister_older' => [
                'code' => 'sister_older',
                'kk' => 'Әпке (Апа)',
                'ru' => 'Старшая сестра',
                'desc' => 'Өзінен жасы үлкен қыз бауыр',
                'line' => 'sibling',
                'generation_diff' => 0,
                'gender' => 'F',
                'seniority' => 'older',
            ],
            'sister_younger_for_male' => [
                'code' => 'sister_younger_for_male',
                'kk' => 'Қарындас',
                'ru' => 'Младшая сестра (для брата)',
                'desc' => 'Ер адамның өзінен кіші қыз бауыры. Тек ер адамдар «қарындасым» деп атайды.',
                'line' => 'sibling',
                'generation_diff' => 0,
                'gender' => 'F',
                'seniority' => 'younger',
                'speaker_gender' => 'M',
            ],
            'sister_younger_for_female' => [
                'code' => 'sister_younger_for_female',
                'kk' => 'Сіңлі',
                'ru' => 'Младшая сестра (для сестры)',
                'desc' => 'Әйел адамның өзінен кіші қыз бауыры. Тек әйелдер «сіңлім» дейді.',
                'line' => 'sibling',
                'generation_diff' => 0,
                'gender' => 'F',
                'seniority' => 'younger',
                'speaker_gender' => 'F',
            ],

            // ========================================================
            // 3. Нағашы жұрт (Родственники по линии матери)
            // ========================================================
            'grandfather_maternal' => [
                'code' => 'grandfather_maternal',
                'kk' => 'Нағашы ата',
                'ru' => 'Дедушка по матери',
                'desc' => 'Анасының әкесі',
                'line' => 'maternal',
                'generation_diff' => -2,
                'gender' => 'M',
            ],
            'grandmother_maternal' => [
                'code' => 'grandmother_maternal',
                'kk' => 'Нағашы әже',
                'ru' => 'Бабушка по матери',
                'desc' => 'Анасының анасы',
                'line' => 'maternal',
                'generation_diff' => -2,
                'gender' => 'F',
            ],
            'uncle_maternal' => [
                'code' => 'uncle_maternal',
                'kk' => 'Нағашы аға (Нағашы)',
                'ru' => 'Дядя по матери',
                'desc' => 'Анасының ер бауыры (ағасы немесе інісі)',
                'line' => 'maternal',
                'generation_diff' => -1,
                'gender' => 'M',
            ],
            'aunt_maternal' => [
                'code' => 'aunt_maternal',
                'kk' => 'Нағашы апа (Нағашы әпке)',
                'ru' => 'Тетя по матери',
                'desc' => 'Анасының қыз бауыры (әпкесі немесе сіңлісі)',
                'line' => 'maternal',
                'generation_diff' => -1,
                'gender' => 'F',
            ],
            'zhien' => [
                'code' => 'zhien',
                'kk' => 'Жиен',
                'ru' => 'Племянник / Внук по женской линии',
                'desc' => 'Тұрмысқа шыққан қыздың немесе әпке-қарындастың баласы',
                'line' => 'maternal_descendant',
                'generation_diff' => 1,
                'gender' => 'any',
            ],
            'zhien_shar' => [
                'code' => 'zhien_shar',
                'kk' => 'Жиеншар',
                'ru' => 'Ребенок жиена',
                'desc' => 'Жиеннің баласы',
                'line' => 'maternal_descendant',
                'generation_diff' => 2,
                'gender' => 'any',
            ],
            'bole' => [
                'code' => 'bole',
                'kk' => 'Бөле',
                'ru' => 'Кузен / Кузина (дети сестер)',
                'desc' => 'Апалы-сіңлілі аналардың балалары бір-біріне бөле болады',
                'line' => 'maternal_cousin',
                'generation_diff' => 0,
                'gender' => 'any',
            ],

            // ========================================================
            // 4. Жұбайлар және Қайын жұрт (Супруги и свойство)
            // ========================================================
            'husband' => [
                'code' => 'husband',
                'kk' => 'Күйеу (Жұбайы)',
                'ru' => 'Муж (Супруг)',
                'desc' => 'Некелі өмірлік жолдасы',
                'line' => 'marriage',
                'generation_diff' => 0,
                'gender' => 'M',
            ],
            'wife' => [
                'code' => 'wife',
                'kk' => 'Әйелі (Зайыбы / Келіншек)',
                'ru' => 'Жена (Супруга)',
                'desc' => 'Некелі өмірлік жолдасы',
                'line' => 'marriage',
                'generation_diff' => 0,
                'gender' => 'F',
            ],
            'zhezde' => [
                'code' => 'zhezde',
                'kk' => 'Жезде',
                'ru' => 'Зять (муж старшей сестры)',
                'desc' => 'Өзінен жасы үлкен әпкенің күйеуі',
                'line' => 'in_law',
                'generation_diff' => 0,
                'gender' => 'M',
            ],
            'zhenge' => [
                'code' => 'zhenge',
                'kk' => 'Жеңге',
                'ru' => 'Сноха (жена старшего брата)',
                'desc' => 'Өзінен жасы үлкен ағаның әйелі',
                'line' => 'in_law',
                'generation_diff' => 0,
                'gender' => 'F',
            ],
            'kelin' => [
                'code' => 'kelin',
                'kk' => 'Келін',
                'ru' => 'Невестка / Сноха',
                'desc' => 'Ұлдың немесе інінің әйелі (шаңыраққа жаңа түскен келін)',
                'line' => 'in_law',
                'generation_diff' => 1,
                'gender' => 'F',
            ],
            'kuyeubala' => [
                'code' => 'kuyeubala',
                'kk' => 'Күйеу бала',
                'ru' => 'Зять (муж дочери или младшей сестры)',
                'desc' => 'Қыздың немесе сіңлінің/қарындастың күйеуі',
                'line' => 'in_law',
                'generation_diff' => 1,
                'gender' => 'M',
            ],
            'bazha' => [
                'code' => 'bazha',
                'kk' => 'Бажа',
                'ru' => 'Свояк (мужья родных сестер)',
                'desc' => 'Бір үйдің қыздарына үйленген ер адамдар бір-біріне бажа болады',
                'line' => 'in_law',
                'generation_diff' => 0,
                'gender' => 'M',
            ],
            'abysyn' => [
                'code' => 'abysyn',
                'kk' => 'Абысын',
                'ru' => 'Снохи одного рода (жены братьев)',
                'desc' => 'Бір атаның немесе туған бауырлардың әйелдері бір-біріне абысын',
                'line' => 'in_law',
                'generation_diff' => 0,
                'gender' => 'F',
            ],
            'baldyz' => [
                'code' => 'baldyz',
                'kk' => 'Балдыз',
                'ru' => 'Младший брат / сестра жены',
                'desc' => 'Әйелінің өзінен кіші інісі немесе сіңлісі (күйеу үшін балдыз)',
                'line' => 'in_law',
                'generation_diff' => 0,
                'gender' => 'any',
            ],
            'kayin_ata' => [
                'code' => 'kayin_ata',
                'kk' => 'Қайын ата',
                'ru' => 'Тесть / Свекор',
                'desc' => 'Жұбайының (күйеуінің немесе әйелінің) әкесі',
                'line' => 'in_law',
                'generation_diff' => -1,
                'gender' => 'M',
            ],
            'kayin_ene' => [
                'code' => 'kayin_ene',
                'kk' => 'Қайын ене',
                'ru' => 'Теща / Свекровь',
                'desc' => 'Жұбайының анасы',
                'line' => 'in_law',
                'generation_diff' => -1,
                'gender' => 'F',
            ],
            'kuda' => [
                'code' => 'kuda',
                'kk' => 'Құда',
                'ru' => 'Сват',
                'desc' => 'Ұл мен қызды қосып, құдаласқан ер адам',
                'line' => 'kudalyk',
                'generation_diff' => 0,
                'gender' => 'M',
            ],
            'kudaghy' => [
                'code' => 'kudaghy',
                'kk' => 'Құдағи',
                'ru' => 'Сватья',
                'desc' => 'Құдаласушы жақтың анасы/әйелі',
                'line' => 'kudalyk',
                'generation_diff' => 0,
                'gender' => 'F',
            ],
        ];
    }
}
