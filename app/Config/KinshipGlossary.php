<?php

declare(strict_types=1);

namespace App\Config;

/**
 * ALT-013: глоссарий терминов родства — перевод на en/es и расшифровка-подсказка (kk/ru/en/es).
 *
 * Все тексты хранятся здесь (а не в шаблонах). Термин из KinshipService (term_kk + term_ru)
 * сопоставляется с «понятием» (concept) по правилам rules(); дальше берутся
 * название и подсказка на нужном языке.
 */
class KinshipGlossary
{
    public const LANGS = ['kk', 'ru', 'en', 'es'];

    /**
     * Понятия. 'en'/'es' — [муж., жен., (общ.)]; если форма одна — [одна].
     * 'hint' — пояснение на kk/ru/en/es.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function concepts(): array
    {
        return [
            'self' => ['en' => ['Me'], 'es' => ['Yo'], 'hint' => [
                'kk' => 'Сіз өзіңіз.', 'ru' => 'Это вы.', 'en' => 'This is you.', 'es' => 'Es usted.']],
            'father' => ['en' => ['Father'], 'es' => ['Padre'], 'hint' => [
                'kk' => 'Әке — өз әкеңіз.', 'ru' => 'Отец — ваш родной отец.', 'en' => 'Your own father.', 'es' => 'Su padre biológico.']],
            'mother' => ['en' => ['Mother'], 'es' => ['Madre'], 'hint' => [
                'kk' => 'Ана (шеше) — өз анаңыз.', 'ru' => 'Мать — ваша родная мать.', 'en' => 'Your own mother.', 'es' => 'Su madre biológica.']],
            'son' => ['en' => ['Son'], 'es' => ['Hijo'], 'hint' => [
                'kk' => 'Ұл — өз ұлыңыз.', 'ru' => 'Сын — ваш родной сын.', 'en' => 'Your own son.', 'es' => 'Su propio hijo.']],
            'daughter' => ['en' => ['Daughter'], 'es' => ['Hija'], 'hint' => [
                'kk' => 'Қыз — өз қызыңыз.', 'ru' => 'Дочь — ваша родная дочь.', 'en' => 'Your own daughter.', 'es' => 'Su propia hija.']],
            'grandfather_p' => ['en' => ['Paternal grandfather'], 'es' => ['Abuelo paterno'], 'hint' => [
                'kk' => 'Ата — әкенің әкесі.', 'ru' => 'Ата — отец отца (дедушка по отцу).', 'en' => 'Ata — your father\'s father.', 'es' => 'Ata — el padre de su padre (abuelo paterno).']],
            'grandmother_p' => ['en' => ['Paternal grandmother'], 'es' => ['Abuela paterna'], 'hint' => [
                'kk' => 'Әже — әкенің анасы.', 'ru' => 'Әже — мать отца (бабушка по отцу).', 'en' => 'Äje — your father\'s mother.', 'es' => 'Äje — la madre de su padre (abuela paterna).']],
            'grandfather_m' => ['en' => ['Maternal grandfather'], 'es' => ['Abuelo materno'], 'hint' => [
                'kk' => 'Нағашы ата — шешенің әкесі.', 'ru' => 'Нағашы ата — отец матери (дедушка по матери).', 'en' => 'Nagashy ata — your mother\'s father.', 'es' => 'Nagashy ata — el padre de su madre (abuelo materno).']],
            'grandmother_m' => ['en' => ['Maternal grandmother'], 'es' => ['Abuela materna'], 'hint' => [
                'kk' => 'Нағашы әже — шешенің анасы.', 'ru' => 'Нағашы әже — мать матери (бабушка по матери).', 'en' => 'Nagashy äje — your mother\'s mother.', 'es' => 'Nagashy äje — la madre de su madre (abuela materna).']],
            'great_grandfather' => ['en' => ['Great-grandfather'], 'es' => ['Bisabuelo'], 'hint' => [
                'kk' => 'Баба — атаның әкесі (3-ші буын).', 'ru' => 'Баба — отец дедушки (прадед, 3-е колено).', 'en' => 'Baba — your grandfather\'s father (3rd generation).', 'es' => 'Baba — el padre del abuelo (bisabuelo, 3.ª generación).']],
            'great_grandmother' => ['en' => ['Great-grandmother'], 'es' => ['Bisabuela'], 'hint' => [
                'kk' => 'Баба — әженің анасы (3-ші буын).', 'ru' => 'Мать бабушки или дедушки (прабабушка, 3-е колено).', 'en' => 'The mother of a grandparent (3rd generation).', 'es' => 'La madre de un abuelo o abuela (bisabuela, 3.ª generación).']],
            'ancestor' => ['en' => ['Ancestor'], 'es' => ['Antepasado'], 'hint' => [
                'kk' => 'Баба — жеті атаның бірі, тікелей ата-баба.', 'ru' => 'Предок по прямой линии (одно из «семи колен» — жеті ата).', 'en' => 'A direct ancestor (one of the "seven fathers" — zheti ata).', 'es' => 'Antepasado directo (uno de los «siete padres» — zheti ata).']],
            'grandchild' => ['en' => ['Grandson', 'Granddaughter', 'Grandchild'], 'es' => ['Nieto', 'Nieta', 'Nieto/a'], 'hint' => [
                'kk' => 'Немере — баланың (ұл-қыздың) баласы.', 'ru' => 'Немере — ребёнок вашего сына или дочери (внук/внучка).', 'en' => 'Nemere — the child of your son or daughter.', 'es' => 'Nemere — el hijo o la hija de su hijo o hija (nieto/a).']],
            'grandchild_daughter' => ['en' => ['Grandson (daughter\'s son)', 'Granddaughter (daughter\'s daughter)', 'Grandchild (daughter\'s child)'], 'es' => ['Nieto (hijo de la hija)', 'Nieta (hija de la hija)', 'Nieto/a (hijo/a de la hija)'], 'hint' => [
                'kk' => 'Жиен — қыздан туған бала (немере).', 'ru' => 'Жиен — ребёнок дочери (внук/внучка по дочери).', 'en' => 'Zhien — a daughter\'s child.', 'es' => 'Zhien — hijo/a de su hija (nieto/a por parte de la hija).']],
            'great_grandchild' => ['en' => ['Great-grandson', 'Great-granddaughter', 'Great-grandchild'], 'es' => ['Bisnieto', 'Bisnieta', 'Bisnieto/a'], 'hint' => [
                'kk' => 'Шөбере — немеренің баласы (3-ші буын).', 'ru' => 'Шөбере — ребёнок внука или внучки (правнук/правнучка).', 'en' => 'Shöbere — the child of your grandchild.', 'es' => 'Shöbere — el hijo o la hija de su nieto/a (bisnieto/a).']],
            'great_great_grandchild' => ['en' => ['Great-great-grandson', 'Great-great-granddaughter', 'Great-great-grandchild'], 'es' => ['Tataranieto', 'Tataranieta', 'Tataranieto/a'], 'hint' => [
                'kk' => 'Шөпшек — шөберенің баласы (4-ші буын).', 'ru' => 'Шөпшек — ребёнок правнука (праправнук/праправнучка, 4-е колено).', 'en' => 'Shöpshek — the child of your great-grandchild (4th generation).', 'es' => 'Shöpshek — el hijo o la hija de su bisnieto/a (tataranieto/a, 4.ª generación).']],
            'descendant_5' => ['en' => ['Descendant (5th generation)'], 'es' => ['Descendiente (5.ª generación)'], 'hint' => [
                'kk' => 'Немене — шөпшектің баласы (5-ші буын).', 'ru' => 'Немене — потомок в 5-м колене, ребёнок шөпшека.', 'en' => 'Nemene — a descendant in the 5th generation.', 'es' => 'Nemene — descendiente de 5.ª generación (hijo/a del tataranieto/a).']],
            'descendant_6' => ['en' => ['Descendant (6th generation)'], 'es' => ['Descendiente (6.ª generación)'], 'hint' => [
                'kk' => 'Туажат — 6-шы буын ұрпақ.', 'ru' => 'Туажат — потомок в 6-м колене.', 'en' => 'Tuazhat — a descendant in the 6th generation.', 'es' => 'Tuazhat — descendiente de 6.ª generación.']],
            'descendant_7' => ['en' => ['Descendant (7th generation)'], 'es' => ['Descendiente (7.ª generación)'], 'hint' => [
                'kk' => 'Жүрежат — 7-ші буын ұрпақ.', 'ru' => 'Жүрежат — потомок в 7-м колене.', 'en' => 'Zhürezhat — a descendant in the 7th generation.', 'es' => 'Zhürezhat — descendiente de 7.ª generación.']],
            'brother_older' => ['en' => ['Older brother'], 'es' => ['Hermano mayor'], 'hint' => [
                'kk' => 'Аға — өзіңізден үлкен ер туысқан (ағайынды).', 'ru' => 'Аға — старший брат (также уважительно — старший родственник-мужчина).', 'en' => 'Agha — an older brother (also a respectful form for an older male relative).', 'es' => 'Agha — hermano mayor (también trato respetuoso a un pariente varón mayor).']],
            'brother_younger' => ['en' => ['Younger brother'], 'es' => ['Hermano menor'], 'hint' => [
                'kk' => 'Іні — өзіңізден кіші ер туысқан.', 'ru' => 'Іні — младший брат.', 'en' => 'Ini — a younger brother.', 'es' => 'Ini — hermano menor.']],
            'sister_older' => ['en' => ['Older sister'], 'es' => ['Hermana mayor'], 'hint' => [
                'kk' => 'Әпке (апа) — өзіңізден үлкен әйел туысқан.', 'ru' => 'Әпке (апа) — старшая сестра.', 'en' => 'Äpke (apa) — an older sister.', 'es' => 'Äpke (apa) — hermana mayor.']],
            'sister_younger' => ['en' => ['Younger sister'], 'es' => ['Hermana menor'], 'hint' => [
                'kk' => 'Қарындас (ер адамға) / сіңлі (әйел адамға) — кіші әйел туысқан.', 'ru' => 'Қарындас (для брата) / сіңлі (для сестры) — младшая сестра.', 'en' => 'Qaryndas (said by a man) / sinli (said by a woman) — a younger sister.', 'es' => 'Qaryndas (dicho por un hombre) / sinli (dicho por una mujer) — hermana menor.']],
            'uncle_p' => ['en' => ['Paternal uncle'], 'es' => ['Tío paterno'], 'hint' => [
                'kk' => 'Әкенің ағасы немесе інісі.', 'ru' => 'Брат отца (дядя по отцу).', 'en' => 'Your father\'s brother.', 'es' => 'El hermano de su padre.']],
            'aunt_p' => ['en' => ['Paternal aunt'], 'es' => ['Tía paterna'], 'hint' => [
                'kk' => 'Әкенің әпкесі немесе қарындасы.', 'ru' => 'Сестра отца (тётя по отцу).', 'en' => 'Your father\'s sister.', 'es' => 'La hermana de su padre.']],
            'uncle_m' => ['en' => ['Maternal uncle'], 'es' => ['Tío materno'], 'hint' => [
                'kk' => 'Нағашы аға — шешенің ағасы немесе інісі (нағашы жұрт).', 'ru' => 'Нағашы аға — брат матери (дядя по матери, «нагашы»-род).', 'en' => 'Nagashy agha — your mother\'s brother (maternal side).', 'es' => 'Nagashy agha — el hermano de su madre (línea materna).']],
            'aunt_m' => ['en' => ['Maternal aunt'], 'es' => ['Tía materna'], 'hint' => [
                'kk' => 'Нағашы апа — шешенің әпкесі немесе сіңлісі.', 'ru' => 'Нағашы апа — сестра матери (тётя по матери).', 'en' => 'Nagashy apa — your mother\'s sister.', 'es' => 'Nagashy apa — la hermana de su madre.']],
            'uncle_m_husband' => ['en' => ['Uncle by marriage (maternal aunt\'s husband)'], 'es' => ['Tío político (esposo de la tía materna)'], 'hint' => [
                'kk' => 'Нағашы жезде — шешенің әпке-сіңлісінің күйеуі.', 'ru' => 'Нағашы жезде — муж сестры матери.', 'en' => 'Nagashy zhezde — the husband of your mother\'s sister.', 'es' => 'Nagashy zhezde — el esposo de la hermana de su madre.']],
            'nephew_brother' => ['en' => ['Nephew (brother\'s son)'], 'es' => ['Sobrino (hijo del hermano)'], 'hint' => [
                'kk' => 'Бауырдың ұлы — іні деп аталады.', 'ru' => 'Сын брата (называется «іні»).', 'en' => 'A brother\'s son (called "ini").', 'es' => 'El hijo de un hermano (se llama «ini»).']],
            'niece_brother' => ['en' => ['Niece (brother\'s daughter)'], 'es' => ['Sobrina (hija del hermano)'], 'hint' => [
                'kk' => 'Бауырдың қызы — қарындас / сіңлі деп аталады.', 'ru' => 'Дочь брата (называется «қарындас» / «сіңлі»).', 'en' => 'A brother\'s daughter (called "qaryndas" / "sinli").', 'es' => 'La hija de un hermano (se llama «qaryndas» / «sinli»).']],
            'cousin' => ['en' => ['Cousin', 'Cousin', 'Cousin'], 'es' => ['Primo', 'Prima', 'Primo/a'], 'hint' => [
                'kk' => 'Ағайынды (бауырлас) ата-аналардың баласы — немере аға/іні/әпке/қарындас.', 'ru' => 'Двоюродный брат или сестра — ребёнок брата или сестры отца/матери.', 'en' => 'A cousin — the child of your parent\'s sibling.', 'es' => 'Primo/a — hijo/a de un hermano o hermana de su padre o madre.']],
            'cousin_maternal_far' => ['en' => ['Maternal cousin (nagashy)', 'Maternal cousin (nagashy)', 'Maternal cousin (nagashy)'], 'es' => ['Primo por parte de madre', 'Prima por parte de madre', 'Primo/a por parte de madre'], 'hint' => [
                'kk' => 'Нағашы аға / апа — нағашы жұрттағы құрдас туыс (шешенің бауырының баласы).', 'ru' => 'Двоюродный брат или сестра по линии матери (нагашы): ребёнок брата или сестры матери.', 'en' => 'A cousin on your mother\'s side (nagashy): the child of your mother\'s sibling.', 'es' => 'Primo/a por parte de la madre (nagashy): hijo/a de un hermano o hermana de su madre.']],
            'cousin_paternal_nephew' => ['en' => ['Cousin (father\'s nephew)', 'Cousin (father\'s niece)', 'Cousin (father\'s nephew/niece)'], 'es' => ['Primo (sobrino de mi padre)', 'Prima (sobrina de mi padre)', 'Primo/a (sobrino/a de mi padre)'], 'hint' => [
                'kk' => 'Әкесінің жиені — әкенің әпке-қарындасынан туған бала.', 'ru' => 'Әкесінің жиені — ребёнок сестры отца.', 'en' => 'Äkesinin zhieni — a child of your father\'s sister.', 'es' => 'Äkesinin zhieni — hijo/a de la hermana de su padre.']],
            'nephew_sister' => ['en' => ['Nephew (sister\'s son)', 'Niece (sister\'s daughter)', 'Nephew/niece (sister\'s child)'], 'es' => ['Sobrino (hijo de la hermana)', 'Sobrina (hija de la hermana)', 'Sobrino/a (hijo/a de la hermana)'], 'hint' => [
                'kk' => 'Жиен — қарындас, әпке немесе қыздан туған бала.', 'ru' => 'Жиен — ребёнок сестры или дочери.', 'en' => 'Zhien — the child of a sister or a daughter.', 'es' => 'Zhien — hijo/a de una hermana o de una hija.']],
            'zhienshar' => ['en' => ['Zhien\'s child (great-nephew/niece)'], 'es' => ['Hijo/a del sobrino (de la hermana)'], 'hint' => [
                'kk' => 'Жиеншар — жиеннің баласы.', 'ru' => 'Жиеншар — ребёнок жиена (внучатый племянник/племянница).', 'en' => 'Zhienshar — the child of a zhien (a sister\'s grandchild).', 'es' => 'Zhienshar — hijo/a de un zhien (sobrino/a nieto/a de la hermana).']],
            'bole' => ['en' => ['Maternal cousin (bole)'], 'es' => ['Primo/a por parte de madre (hijos de dos hermanas)'], 'hint' => [
                'kk' => 'Бөле — екі әпке-сіңлінің балалары, яғни шеше жағынан екі туыс.', 'ru' => 'Бөле — двоюродные брат или сестра по матери: дети двух сестёр.', 'en' => 'Bole — maternal cousins: children of two sisters.', 'es' => 'Bole — primos por parte de madre: hijos de dos hermanas.']],
            'boleshik' => ['en' => ['Bole\'s child'], 'es' => ['Hijo/a de la prima (bole)'], 'hint' => [
                'kk' => 'Бөлешік — бөленің баласы.', 'ru' => 'Бөлешік — ребёнок бөле (двоюродного брата или сестры по матери).', 'en' => 'Boleshik — the child of a bole (maternal cousin).', 'es' => 'Boleshik — hijo/a de un primo o prima por parte de madre (bole).']],
            'husband' => ['en' => ['Husband'], 'es' => ['Esposo'], 'hint' => [
                'kk' => 'Күйеу (жұбайы) — ері.', 'ru' => 'Муж (супруг).', 'en' => 'A husband (spouse).', 'es' => 'Esposo (cónyuge).']],
            'wife' => ['en' => ['Wife'], 'es' => ['Esposa'], 'hint' => [
                'kk' => 'Әйелі (жұбайы).', 'ru' => 'Жена (супруга).', 'en' => 'A wife (spouse).', 'es' => 'Esposa (cónyuge).']],
            'baja' => ['en' => ['Co-brother-in-law (husband of wife\'s sister)'], 'es' => ['Concuñado (esposo de la hermana de mi esposa)'], 'hint' => [
                'kk' => 'Бажа — әйелдің әпке-сіңлілерінің күйеулері (бір-біріне бажа).', 'ru' => 'Бажа — мужья сестёр жены (свояки).', 'en' => 'Bazha — the husbands of a wife\'s sisters (to each other).', 'es' => 'Bazha — los esposos de las hermanas de una esposa (concuñados entre sí).']],
            'baldyz_m' => ['en' => ['Wife\'s younger brother'], 'es' => ['Cuñado menor (hermano menor de la esposa)'], 'hint' => [
                'kk' => 'Балдыз — әйелдің інісі немесе сіңлісі.', 'ru' => 'Балдыз — младший брат или сестра жены.', 'en' => 'Baldyz — the younger brother or sister of your wife.', 'es' => 'Baldyz — el hermano o la hermana menor de su esposa.']],
            'baldyz_f' => ['en' => ['Wife\'s younger sister'], 'es' => ['Cuñada menor (hermana menor de la esposa)'], 'hint' => [
                'kk' => 'Балдыз — әйелдің інісі немесе сіңлісі.', 'ru' => 'Балдыз — младший брат или сестра жены.', 'en' => 'Baldyz — the younger brother or sister of your wife.', 'es' => 'Baldyz — el hermano o la hermana menor de su esposa.']],
            'abysyn' => ['en' => ['Sister-in-law (wife of husband\'s brother)'], 'es' => ['Concuñada (esposa del hermano de mi esposo)'], 'hint' => [
                'kk' => 'Абысын — ағайынды ер адамдардың әйелдері (бір-біріне абысын).', 'ru' => 'Абысын — жёны братьев (по отношению друг к другу).', 'en' => 'Abysyn — the wives of brothers (to each other).', 'es' => 'Abysyn — las esposas de hermanos (entre sí).']],
            'qayyn_agha' => ['en' => ['Older brother-in-law'], 'es' => ['Cuñado mayor'], 'hint' => [
                'kk' => 'Қайын аға — жұбайыңыздың ағасы.', 'ru' => 'Қайын аға — старший брат жены или мужа.', 'en' => 'Qayyn agha — the older brother of your spouse.', 'es' => 'Qayyn agha — el hermano mayor de su cónyuge.']],
            'qayyn_bike' => ['en' => ['Older sister-in-law'], 'es' => ['Cuñada mayor'], 'hint' => [
                'kk' => 'Қайын бике (қайын апа) — жұбайыңыздың әпкесі.', 'ru' => 'Қайын бике — старшая сестра жены или мужа.', 'en' => 'Qayyn bike — the older sister of your spouse.', 'es' => 'Qayyn bike — la hermana mayor de su cónyuge.']],
            'qayyn_ini' => ['en' => ['Younger brother-in-law'], 'es' => ['Cuñado menor'], 'hint' => [
                'kk' => 'Қайын іні — күйеуіңіздің інісі.', 'ru' => 'Қайын іні — младший брат мужа.', 'en' => 'Qayyn ini — your husband\'s younger brother.', 'es' => 'Qayyn ini — el hermano menor de su esposo.']],
            'qayyn_sinli' => ['en' => ['Younger sister-in-law'], 'es' => ['Cuñada menor'], 'hint' => [
                'kk' => 'Қайын сіңлі — күйеуіңіздің сіңлісі.', 'ru' => 'Қайын сіңлі — младшая сестра мужа.', 'en' => 'Qayyn sinli — your husband\'s younger sister.', 'es' => 'Qayyn sinli — la hermana menor de su esposo.']],
            'zhezde' => ['en' => ['Brother-in-law (older sister\'s husband)'], 'es' => ['Cuñado (esposo de la hermana mayor)'], 'hint' => [
                'kk' => 'Жезде — әпкенің немесе үлкен әйел туысқанның күйеуі.', 'ru' => 'Жезде — муж старшей сестры (или старшей родственницы).', 'en' => 'Zhezde — the husband of an older sister (or older female relative).', 'es' => 'Zhezde — el esposo de una hermana mayor (o pariente mayor).']],
            'zhenge' => ['en' => ['Sister-in-law (older brother\'s wife)'], 'es' => ['Cuñada (esposa del hermano mayor)'], 'hint' => [
                'kk' => 'Жеңге — ағаның әйелі.', 'ru' => 'Жеңге — жена старшего брата.', 'en' => 'Zhenge — the wife of an older brother.', 'es' => 'Zhenge — la esposa del hermano mayor.']],
            'kelin' => ['en' => ['Daughter-in-law'], 'es' => ['Nuera'], 'hint' => [
                'kk' => 'Келін — ұлдың немесе інінің әйелі.', 'ru' => 'Келін — жена сына или младшего родственника (сноха, невестка).', 'en' => 'Kelin — the wife of a son or of a younger male relative.', 'es' => 'Kelin — la esposa de un hijo o de un pariente varón menor (nuera).']],
            'kuyeu_bala' => ['en' => ['Son-in-law'], 'es' => ['Yerno'], 'hint' => [
                'kk' => 'Күйеу бала — қыздың немесе сіңлінің күйеуі.', 'ru' => 'Күйеу бала — муж дочери или младшей сестры (зять).', 'en' => 'Küyeu bala — the husband of a daughter or younger sister.', 'es' => 'Küyeu bala — el esposo de una hija o hermana menor (yerno).']],
            'qayyn_ata' => ['en' => ['Father-in-law'], 'es' => ['Suegro'], 'hint' => [
                'kk' => 'Қайын ата — жұбайыңыздың әкесі.', 'ru' => 'Қайын ата — отец жены (тесть) или мужа (свёкор).', 'en' => 'Qayyn ata — the father of your spouse.', 'es' => 'Qayyn ata — el padre de su cónyuge.']],
            'qayyn_ene' => ['en' => ['Mother-in-law'], 'es' => ['Suegra'], 'hint' => [
                'kk' => 'Қайын ене — жұбайыңыздың анасы.', 'ru' => 'Қайын ене — мать жены (тёща) или мужа (свекровь).', 'en' => 'Qayyn ene — the mother of your spouse.', 'es' => 'Qayyn ene — la madre de su cónyuge.']],
            'quda' => ['en' => ['Co-father-in-law (quda)'], 'es' => ['Consuegro (quda)'], 'hint' => [
                'kk' => 'Құда — балаңыздың немесе бауырыңыздың жұбайының әкесі мен жақындары.', 'ru' => 'Құда — родители и родня мужа/жены вашего ребёнка или брата.', 'en' => 'Quda — the parents and relatives of the spouse of your child or brother.', 'es' => 'Quda — los padres y parientes del esposo o esposa de su hijo o hermano (consuegros).']],
            'qudagi' => ['en' => ['Co-mother-in-law (qudagi)'], 'es' => ['Consuegra (qudagi)'], 'hint' => [
                'kk' => 'Құдағи — құданың әйелі, баласының қайын енесі.', 'ru' => 'Құдағи — мать мужа/жены вашего ребёнка или брата (сватья).', 'en' => 'Qudagi — the mother of the spouse of your child or brother.', 'es' => 'Qudagi — la madre del esposo o esposa de su hijo o hermano (consuegra).']],
            'qudasha' => ['en' => ['Female relative of the in-laws'], 'es' => ['Parienta política (de los consuegros)'], 'hint' => [
                'kk' => 'Құдаша — құда жақтағы қыз немесе әйел туыс.', 'ru' => 'Құдаша — родственница со стороны құда (младшая).', 'en' => 'Qudasha — a younger female relative on the quda side.', 'es' => 'Qudasha — parienta (más joven) del lado de los consuegros.']],
            'quda_bala' => ['en' => ['Male relative of the in-laws'], 'es' => ['Pariente político (de los consuegros)'], 'hint' => [
                'kk' => 'Құда бала — құда жақтағы жас ер туыс.', 'ru' => 'Құда бала — родственник со стороны құда (младший).', 'en' => 'Quda bala — a younger male relative on the quda side.', 'es' => 'Quda bala — pariente varón (más joven) del lado de los consuegros.']],
            'quda_relative' => ['en' => ['In-law relative (quda)'], 'es' => ['Pariente político (quda)'], 'hint' => [
                'kk' => 'Құда жақтың туысы.', 'ru' => 'Родственник со стороны құда (сватов).', 'en' => 'A relative on the quda (in-law) side.', 'es' => 'Pariente del lado de los consuegros (quda).']],
            'clan_relative' => ['en' => ['Clan relative'], 'es' => ['Pariente del clan'], 'hint' => [
                'kk' => 'Ағайын — бір рудың (шежіренің) туысы.', 'ru' => 'Ағайын — родственник по роду (шежіре).', 'en' => 'Aghayin — a relative of the same clan (shezhire).', 'es' => 'Aghayin — pariente del mismo clan (shezhire).']],
            'relative' => ['en' => ['Relative'], 'es' => ['Pariente'], 'hint' => [
                'kk' => 'Туысқан — жақын-алыс туыс.', 'ru' => 'Родственник (степень родства точнее не определена).', 'en' => 'A relative (the exact degree is not determined).', 'es' => 'Pariente (el grado exacto no está determinado).']],
        ];
    }

    /**
     * Правила: [regex по строке term_ru, regex по нормализованному term_kk, понятие] — любое из двух.
     * Порядок важен: сверху вниз, первое совпадение.
     *
     * @return array<int, array{0:?string,1:?string,2:string}>
     */
    private static function rules(): array
    {
        return [
            [null, '/^(мен|өзі)$/u', 'self'],
            ['/^(Я|Сам\(а\))$/u', null, 'self'],
            // составные по ru — раньше однословных
            ['/^Двоюродн|^Троюродн|^Четверо|^Пятиюродн|юродн(ый|ая|ое)\s+(брат|сестра)/u', null, 'cousin'],
            ['/дядя по отцу/iu', null, 'uncle_p'],
            ['/тётя по отцу|тетя по отцу/iu', null, 'aunt_p'],
            ['/сын брата/iu', null, 'nephew_brother'],
            ['/дочь брата/iu', null, 'niece_brother'],
            [null, '/^нағашы\s+жезде/u', 'uncle_m_husband'],
            [null, '/^әкесінің\s+жиені/u', 'cousin_paternal_nephew'],
            ['/дядя по матери/iu', null, 'uncle_m'],
            ['/тётя по матери|тетя по матери/iu', null, 'aunt_m'],
            [null, '/^нағашы\s+(аға|іні)/u', 'cousin_maternal_far'],
            [null, '/^нағашы\s+(апа|әпке|қарындас|сіңлі)/u', 'cousin_maternal_far'],
            [null, '/^нағашы\s+ата/u', 'grandfather_m'],
            [null, '/^нағашы\s+әже/u', 'grandmother_m'],
            ['/по дочери/u', '/^жиен/u', 'grandchild_daughter'],
            [null, '/^жиеншар/u', 'zhienshar'],
            [null, '/^жиен/u', 'nephew_sister'],
            ['/./u', '/^бөлешік/u', 'boleshik'],
            ['/./u', '/^бөле/u', 'bole'],
            [null, '/^қайын\s+аға/u', 'qayyn_agha'],
            [null, '/^қайын\s+бике|^қайын\s+апа/u', 'qayyn_bike'],
            [null, '/^қайын\s+іні/u', 'qayyn_ini'],
            [null, '/^қайын\s+сіңлі/u', 'qayyn_sinli'],
            [null, '/^қайын\s+ата/u', 'qayyn_ata'],
            [null, '/^қайын\s+ене/u', 'qayyn_ene'],
            [null, '/^құда\s+бала/u', 'quda_bala'],
            [null, '/^құдаша/u', 'qudasha'],
            [null, '/^құдағи/u', 'qudagi'],
            [null, '/^құда/u', 'quda'],
            [null, '/^бажа/u', 'baja'],
            ['/младший\s+брат/iu', '/^балдыз/u', 'baldyz_m'],
            [null, '/^балдыз/u', 'baldyz_f'],
            [null, '/^абысын/u', 'abysyn'],
            [null, '/^жезде/u', 'zhezde'],
            [null, '/^жеңге/u', 'zhenge'],
            [null, '/^келін/u', 'kelin'],
            [null, '/^күйеу\s+бала/u', 'kuyeu_bala'],
            [null, '/^күйеу(\s|$)/u', 'husband'],
            [null, '/^әйелі/u', 'wife'],
            [null, '/^немене/u', 'descendant_5'],
            [null, '/^туажат/u', 'descendant_6'],
            [null, '/^жүрежат/u', 'descendant_7'],
            [null, '/^шөпшек/u', 'great_great_grandchild'],
            [null, '/^шөбере/u', 'great_grandchild'],
            [null, '/^немере/u', 'grandchild'],
            ['/прабабушка/iu', '/^баба/u', 'great_grandmother'],
            [null, '/^баба(\s|$)/u', 'great_grandfather'],
            [null, '/^ата(\s|$)/u', 'grandfather_p'],
            [null, '/^әже(\s|$)/u', 'grandmother_p'],
            [null, '/^әке(\s|$)/u', 'father'],
            [null, '/^(ана|шеше)(\s|$)/u', 'mother'],
            [null, '/^ұл$/u', 'son'],
            [null, '/^қыз$/u', 'daughter'],
            [null, '/^аға/u', 'brother_older'],
            [null, '/^іні/u', 'brother_younger'],
            [null, '/^(әпке|апа)/u', 'sister_older'],
            [null, '/^(қарындас|сіңлі)/u', 'sister_younger'],
            [null, '/^ағайын/u', 'clan_relative'],
            [null, '/^туысқан/u', 'relative'],
        ];
    }

    /** Нормализация казахского термина: нижний регистр, без скобок «(…)». */
    public static function normalizeKk(string $kk): string
    {
        $kk = trim(preg_replace('/\s*\([^)]*\)/u', '', $kk) ?? $kk);
        return trim(mb_strtolower(preg_replace('/\s+/u', ' ', $kk) ?? $kk));
    }

    /** Определить понятие (concept id) по паре term_kk / term_ru; null — не опознан. */
    public static function conceptId(string $termKk, ?string $termRu = null): ?string
    {
        $kk = self::normalizeKk($termKk);
        $ru = trim((string)$termRu);
        // Основное: правило с ru и kk одновременно, затем только ru, затем только kk
        foreach (self::rules() as [$reRu, $reKk, $id]) {
            if ($reRu !== null && $reKk !== null) {
                if ($ru !== '' && preg_match($reRu, $ru) && preg_match($reKk, $kk)) {
                    return $id;
                }
            }
        }
        foreach (self::rules() as [$reRu, $reKk, $id]) {
            if ($reRu !== null && $reKk === null && $ru !== '' && preg_match($reRu, $ru)) {
                return $id;
            }
        }
        foreach (self::rules() as [$reRu, $reKk, $id]) {
            if ($reKk !== null && $reRu === null && $kk !== '' && preg_match($reKk, $kk)) {
                return $id;
            }
        }
        return null;
    }

    /** Пол из русской подписи: 'm' | 'f' | 'b' (оба/неизвестно). */
    private static function genderFromRu(string $ru, string $kk): string
    {
        $hasF = (bool)preg_match('/племянниц|внучк|двоюродная|троюродная|правнучк|\bсестр[аы]\b|\bтёт|дочь/iu', $ru);
        $hasM = (bool)preg_match('/племянник|\bвнук\b|двоюродный|троюродный|правнук\b|праправнук\b|\bбрат\b|\bсын\b|\bдядя\b/iu', $ru);
        if ($hasF && !$hasM) {
            return 'f';
        }
        if ($hasM && !$hasF) {
            return 'm';
        }
        return 'b';
    }

    /**
     * Название термина на языке $lang.
     * kk — исходный казахский термин; ru — как раньше; en/es — из глоссария (или null, если не опознан).
     */
    public static function label(string $termKk, ?string $termRu, string $lang): ?string
    {
        if ($lang === 'kk') {
            return trim($termKk) !== '' ? trim($termKk) : null;
        }
        $id = self::conceptId($termKk, $termRu);
        if ($id === null) {
            return null;
        }
        $c = self::concepts()[$id] ?? null;
        if ($c === null) {
            return null;
        }
        if ($lang === 'ru') {
            return null; // русский выводит I18n::formatKinship как прежде
        }
        $forms = $c[$lang] ?? $c['en'] ?? [];
        if ($forms === []) {
            return null;
        }
        if ($id === 'cousin') {
            return self::cousinLabel((string)$termRu, $lang, $forms);
        }
        if (count($forms) === 1) {
            return (string)$forms[0];
        }
        $g = self::genderFromRu((string)$termRu, $termKk);
        return (string)($g === 'm' ? $forms[0] : ($g === 'f' ? $forms[1] : ($forms[2] ?? $forms[0])));
    }

    /** Двоюродные/троюродные… с указанием линии. */
    private static function cousinLabel(string $ru, string $lang, array $forms): string
    {
        $g = self::genderFromRu($ru, '');
        $base = (string)($g === 'm' ? $forms[0] : ($g === 'f' ? $forms[1] : $forms[2]));
        $es = ($lang === 'es');
        $deg = 1;
        if (preg_match('/^Трою/u', $ru)) {
            $deg = 2;
        } elseif (preg_match('/^Четверо/u', $ru)) {
            $deg = 3;
        } elseif (preg_match('/^Пятию|^Шестию|^Семию/u', $ru)) {
            $deg = 4;
        }
        if ($es) {
            $a = $g === 'f' ? 'a' : ($g === 'm' ? 'o' : 'o/a');
            $base = $deg === 1 ? $base : ($deg === 2 ? "Primo$a segundo$a" : ($deg === 3 ? "Primo$a tercero$a" : "Primo$a lejano$a"));
            if ($g === 'f' && $deg > 1) {
                $base = $deg === 2 ? 'Prima segunda' : ($deg === 3 ? 'Prima tercera' : 'Prima lejana');
            } elseif ($g === 'm' && $deg > 1) {
                $base = $deg === 2 ? 'Primo segundo' : ($deg === 3 ? 'Primo tercero' : 'Primo lejano');
            } elseif ($deg > 1) {
                $base = $deg === 2 ? 'Primo/a segundo/a' : ($deg === 3 ? 'Primo/a tercero/a' : 'Primo/a lejano/a');
            }
            if (preg_match('/по отцу/u', $ru)) {
                $base .= ' por parte de padre';
            } elseif (preg_match('/по матери/u', $ru)) {
                $base .= ' por parte de madre';
            }
            return $base;
        }
        if ($deg > 1) {
            $base = $deg === 2 ? 'Second cousin' : ($deg === 3 ? 'Third cousin' : 'Distant cousin');
        }
        if (preg_match('/по отцу/u', $ru)) {
            $base .= ' (paternal)';
        } elseif (preg_match('/по матери/u', $ru)) {
            $base .= ' (maternal)';
        }
        return $base;
    }

    /** Подсказка-расшифровка на языке $lang; null — понятие не опознано. */
    public static function hint(string $termKk, ?string $termRu, string $lang): ?string
    {
        $id = self::conceptId($termKk, $termRu);
        if ($id === null) {
            return null;
        }
        $lang = in_array($lang, self::LANGS, true) ? $lang : 'kk';
        $h = self::concepts()[$id]['hint'][$lang] ?? null;
        return $h !== null ? (string)$h : null;
    }

    /**
     * Добавить к записи родства поля term_en, term_es и hint_kk/hint_ru/hint_en/hint_es.
     *
     * @param array<string, mixed> $kin
     * @return array<string, mixed>
     */
    public static function annotate(array $kin): array
    {
        $kk = (string)($kin['term_kk'] ?? '');
        $ru = (string)($kin['term_ru'] ?? '');
        $kin['term_en'] = self::label($kk, $ru, 'en') ?? '';
        $kin['term_es'] = self::label($kk, $ru, 'es') ?? '';
        foreach (self::LANGS as $l) {
            $kin['hint_' . $l] = self::hint($kk, $ru, $l) ?? '';
        }
        return $kin;
    }
}
