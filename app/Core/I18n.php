<?php

declare(strict_types=1);

namespace App\Core;

class I18n
{
    private static ?string $currentLang = null;

    /**
     * Получить текущий язык ('kk' или 'ru')
     */
    public static function lang(): string
    {
        if (self::$currentLang !== null) {
            return self::$currentLang;
        }

        // 1. Проверяем параметр GET ?lang=
        if (!empty($_GET['lang']) && in_array($_GET['lang'], ['kk', 'ru'], true)) {
            self::$currentLang = (string)$_GET['lang'];
            self::setCookieLang(self::$currentLang);
            return self::$currentLang;
        }

        // 2. Проверяем Cookie
        if (!empty($_COOKIE['shezhire_lang']) && in_array($_COOKIE['shezhire_lang'], ['kk', 'ru'], true)) {
            self::$currentLang = (string)$_COOKIE['shezhire_lang'];
            return self::$currentLang;
        }

        // 3. Проверяем сессию
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['shezhire_lang']) && in_array($_SESSION['shezhire_lang'], ['kk', 'ru'], true)) {
            self::$currentLang = (string)$_SESSION['shezhire_lang'];
            return self::$currentLang;
        }

        // По умолчанию — казахский язык
        self::$currentLang = 'kk';
        return self::$currentLang;
    }

    /**
     * Установить язык приложения
     */
    public static function setLang(string $lang): void
    {
        $lang = in_array($lang, ['kk', 'ru'], true) ? $lang : 'kk';
        self::$currentLang = $lang;
        self::setCookieLang($lang);
    }

    private static function setCookieLang(string $lang): void
    {
        $_COOKIE['shezhire_lang'] = $lang;
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['shezhire_lang'] = $lang;
        }
        if (!headers_sent()) {
            setcookie('shezhire_lang', $lang, [
                'expires'  => time() + (365 * 86400),
                'path'     => '/',
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
    }

    /**
     * Название сайта в соответствии с требованиями Жандоса (13.11):
     * Қаз: «Шежіре», Рус: «Родословная» (без фамилии!)
     */
    public static function siteTitle(): string
    {
        return self::lang() === 'ru' ? 'Родословная' : 'Шежіре';
    }

    /**
     * Полный перевод строки по ключу
     */
    public static function t(string $key, array $params = []): string
    {
        $lang = self::lang();
        $dict = self::getDictionary();

        $text = $dict[$key][$lang] ?? $dict[$key]['kk'] ?? $key;

        if (!empty($params)) {
            foreach ($params as $k => $v) {
                $text = str_replace('{' . $k . '}', (string)$v, $text);
            }
        }

        return $text;
    }

    /**
     * Склонение казахского предиката 2-го лица вежливого обращения («Сіз — ...сыз / ...сіз»)
     * на основе гармонии гласных (сингармонизм) и конечного звука
     */
    public static function kazakhYouAre(string $name): string
    {
        if (trim($name) === '') {
            return 'Сіз';
        }

        $lastChar = mb_substr($name, -1);
        $vowelsHard = ['а', 'о', 'ұ', 'ы', 'я'];
        $vowelsSoft = ['ә', 'ө', 'ү', 'і', 'е', 'э', 'и', 'ю'];

        $isSoft = false;
        $len = mb_strlen($name);
        for ($i = $len - 1; $i >= 0; $i--) {
            $ch = mb_strtolower(mb_substr($name, $i, 1));
            if (in_array($ch, $vowelsSoft, true)) {
                $isSoft = true;
                break;
            }
            if (in_array($ch, $vowelsHard, true)) {
                $isSoft = false;
                break;
            }
        }

        $suffix = $isSoft ? 'сіз' : 'сыз';
        return "Сіз — {$name}{$suffix}";
    }

    /**
     * Қазақ тілінде ілік септік жалғауларын дұрыс жалғау (Ілік септік: кімнің? ненің?)
     * Мысалы: Ғабит -> Ғабиттің, Жандос -> Жандостың, Кенен -> Кененнің, Алтыбай -> Алтыбайдың
     */
    public static function kazakhGenitive(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        $lastChar = mb_strtolower(mb_substr($name, -1));
        $vowelsHard = ['а', 'о', 'ұ', 'ы', 'я'];
        $vowelsSoft = ['ә', 'ө', 'ү', 'і', 'е', 'э', 'и', 'ю'];

        $isSoft = false;
        $len = mb_strlen($name);
        for ($i = $len - 1; $i >= 0; $i--) {
            $ch = mb_strtolower(mb_substr($name, $i, 1));
            if (in_array($ch, $vowelsSoft, true)) {
                $isSoft = true;
                break;
            }
            if (in_array($ch, $vowelsHard, true)) {
                $isSoft = false;
                break;
            }
        }

        $deaf = ['к', 'қ', 'п', 'с', 'т', 'ш', 'щ', 'ф', 'х', 'ц', 'ч', 'h'];
        $sonor = ['б', 'в', 'г', 'ғ', 'д', 'ж', 'з', 'л', 'р', 'й', 'у'];

        if (in_array($lastChar, $deaf, true)) {
            return $name . ($isSoft ? 'тің' : 'тың');
        }
        if (in_array($lastChar, $sonor, true)) {
            return $name . ($isSoft ? 'дің' : 'дың');
        }
        return $name . ($isSoft ? 'нің' : 'ның');
    }

    /**
     * Форматирование термина родства:
     * На казахском — чистый казахский термин.
     * На русском — казахский термин с русским переводом в скобках, например: «Әкесі (Отец)».
     */
    public static function formatKinship(string $termKk, ?string $termRu = null): string
    {
        $termKk = trim($termKk);
        $termRu = trim((string)$termRu);

        if (self::lang() === 'kk') {
            return $termKk;
        }

        if ($termRu !== '' && mb_strtolower($termRu) !== mb_strtolower($termKk)) {
            return "{$termKk} ({$termRu})";
        }

        return $termKk;
    }

    /**
     * Словарь локализации интерфейса
     */
    public static function getDictionary(): array
    {
        return [
            // Общие элементы шапки и навигации
            'site_title' => [
                'kk' => 'Шежіре',
                'ru' => 'Родословная',
            ],
            'nav_home' => [
                'kk' => 'Басты',
                'ru' => 'Главная',
            ],
            'nav_persons' => [
                'kk' => 'Адамдар',
                'ru' => 'Люди',
            ],
            'nav_families' => [
                'kk' => 'Отбасылар',
                'ru' => 'Семьи',
            ],
            'nav_tree' => [
                'kk' => 'Шежіре',
                'ru' => 'Древо',
            ],
            'nav_dates' => [
                'kk' => 'Есте қаларлық күндер',
                'ru' => 'Памятные даты',
            ],
            'nav_history' => [
                'kk' => 'Тарих',
                'ru' => 'История правок',
            ],
            'nav_admin' => [
                'kk' => 'Әкімші',
                'ru' => 'Админка',
            ],
            'nav_logout' => [
                'kk' => 'Шығу',
                'ru' => 'Выход',
            ],
            'nav_login' => [
                'kk' => 'Кіру',
                'ru' => 'Вход',
            ],

            // Роли
            'role_admin' => [
                'kk' => 'Әкімші',
                'ru' => 'Администратор',
            ],
            'role_editor' => [
                'kk' => 'Редактор',
                'ru' => 'Редактор',
            ],
            'role_viewer' => [
                'kk' => 'Оқырман',
                'ru' => 'Читатель',
            ],

            // Главная страница
            'welcome_heading' => [
                'kk' => 'Қош келдіңіз!',
                'ru' => 'Добро пожаловать!',
            ],
            'connected_person_badge' => [
                'kk' => 'Байланысты тұлға',
                'ru' => 'Связанная персона',
            ],
            'quick_links' => [
                'kk' => 'Жылдам сілтемелер',
                'ru' => 'Быстрые ссылки',
            ],
            'stat_total_persons' => [
                'kk' => 'Барлық тұлғалар',
                'ru' => 'Всего персон',
            ],
            'stat_total_families' => [
                'kk' => 'Шаңырақтар',
                'ru' => 'Семей',
            ],
            'stat_total_generations' => [
                'kk' => 'Буындар',
                'ru' => 'Поколений',
            ],
            'upcoming_birthdays' => [
                'kk' => 'Жақында болатын туған күндер',
                'ru' => 'Ближайшие дни рождения',
            ],
            'memorial_anniversaries' => [
                'kk' => 'Ата-бабаларды еске алу күндері',
                'ru' => 'Дни памяти предков',
            ],
            'no_upcoming_birthdays' => [
                'kk' => 'Алдағы 45 күнде туған күндер жоқ',
                'ru' => 'В ближайшие 45 дней дней рождения нет',
            ],
            'no_memorial_dates' => [
                'kk' => 'Алдағы 45 күнде атаулы еске алу күндері жоқ',
                'ru' => 'В ближайшие 45 дней памятных дат нет',
            ],

            // Раздел «Адамдар»
            'persons_title' => [
                'kk' => 'Адамдар тізімі',
                'ru' => 'Список людей',
            ],
            'persons_subtitle' => [
                'kk' => 'Әулет шежіресіндегі барлық тұлғалар, іздеу және өңдеу',
                'ru' => 'Все персоны родословной, поиск и редактирование',
            ],
            'search_placeholder' => [
                'kk' => 'Аты-жөнін, туған жылын немесе жерін іздеу...',
                'ru' => 'Поиск по имени, году или месту рождения...',
            ],
            'filter_all_clans' => [
                'kk' => 'Барлық рулар',
                'ru' => 'Все роды',
            ],
            'filter_gender_all' => [
                'kk' => 'Жынысы: Барлығы',
                'ru' => 'Пол: Все',
            ],
            'filter_gender_m' => [
                'kk' => 'Ер',
                'ru' => 'Мужской',
            ],
            'filter_gender_f' => [
                'kk' => 'Әйел',
                'ru' => 'Женский',
            ],
            'filter_status_all' => [
                'kk' => 'Күйі: Барлығы',
                'ru' => 'Статус: Все',
            ],
            'filter_status_living' => [
                'kk' => 'Тірі ұрпақтар',
                'ru' => 'Живые',
            ],
            'filter_status_deceased' => [
                'kk' => 'Уақыттан озғандар',
                'ru' => 'Ушедшие предки',
            ],
            'sort_name_asc' => [
                'kk' => 'Аты-жөні (А-Я)',
                'ru' => 'По имени (А-Я)',
            ],
            'sort_name_desc' => [
                'kk' => 'Аты-жөні (Я-А)',
                'ru' => 'По имени (Я-А)',
            ],
            'sort_birth_asc' => [
                'kk' => 'Туған жылы (өсу ретімен)',
                'ru' => 'По году рождения (возр.)',
            ],
            'sort_birth_desc' => [
                'kk' => 'Туған жылы (кему ретімен)',
                'ru' => 'По году рождения (убыв.)',
            ],

            // Таблица
            'th_num' => ['kk' => '#', 'ru' => '#'],
            'th_person' => ['kk' => 'Тұлға', 'ru' => 'Персона'],
            'th_gender' => ['kk' => 'Жынысы', 'ru' => 'Пол'],
            'th_clan' => ['kk' => 'Руы', 'ru' => 'Род'],
            'th_years' => ['kk' => 'Өмір сүрген жылдары', 'ru' => 'Годы жизни'],
            'th_parents' => ['kk' => 'Ата-анасы', 'ru' => 'Родители'],
            'th_spouses' => ['kk' => 'Жұбайы', 'ru' => 'Супруг(а)'],
            'th_kinship' => ['kk' => 'Үш жұрт / Туыстығы', 'ru' => 'Родство к вам'],
            'th_actions' => ['kk' => 'Әрекеттер', 'ru' => 'Действия'],

            // Действия
            'btn_edit' => ['kk' => 'Өзгерту', 'ru' => 'Редактировать'],
            'btn_view_profile' => ['kk' => 'Парақшасы', 'ru' => 'Карточка'],
            'btn_show_in_tree' => ['kk' => 'Древода көрсету', 'ru' => 'Показать в древе'],
            'btn_add_relative' => ['kk' => '+ Туыс қосу', 'ru' => '+ Добавить родню'],
            'btn_add_son' => ['kk' => '+ Ұл', 'ru' => '+ Сын'],
            'btn_add_daughter' => ['kk' => '+ Қыз', 'ru' => '+ Дочь'],
            'btn_save' => ['kk' => 'Сақтау', 'ru' => 'Сохранить'],
            'btn_cancel' => ['kk' => 'Бас тарту', 'ru' => 'Отмена'],
            'btn_delete' => ['kk' => 'Өшіру', 'ru' => 'Удалить'],
            'btn_upload_photo' => ['kk' => 'Сурет жүктеу', 'ru' => 'Загрузить фото'],

            // Карточка персоны
            'person_details_title' => ['kk' => 'Тұлға парақшасы', 'ru' => 'Карточка персоны'],
            'personal_info' => ['kk' => 'Жеке мәліметтер', 'ru' => 'Личные данные'],
            'kinship_info' => ['kk' => 'Туыстық байланыстар', 'ru' => 'Родственные связи'],
            'father_label' => ['kk' => 'Әкесі', 'ru' => 'Отец'],
            'mother_label' => ['kk' => 'Анасы', 'ru' => 'Мать'],
            'spouses_label' => ['kk' => 'Жұбайлары', 'ru' => 'Супруги'],
            'children_label' => ['kk' => 'Балалары', 'ru' => 'Дети'],
            'brothers_sisters' => ['kk' => 'Бауырлары', 'ru' => 'Братья и сестры'],
            'biography_and_memories' => ['kk' => 'Өмірбаяны мен естеліктер', 'ru' => 'Биография и воспоминания'],
            'audio_and_photos' => ['kk' => 'Дыбыс жазбалары мен архивтік суреттер', 'ru' => 'Аудиозаписи и фотографии'],
            'no_media_yet' => ['kk' => 'Әзірге фотосуреттер мен естеліктер жүктелмеген', 'ru' => 'Пока нет загруженных фото и воспоминаний'],

            // Раздел «Отбасылар»
            'families_title' => ['kk' => 'Отбасылар мен шаңырақтар', 'ru' => 'Семьи и союзы'],
            'families_subtitle' => ['kk' => 'Барлық некелер, ерлі-зайыптылар мен олардың ұрпақтары', 'ru' => 'Все браки, супружеские союзы и их потомство'],

            // Раздел «Тарих»
            'history_title' => ['kk' => 'Өзгерістер тарихы', 'ru' => 'История изменений'],
            'history_subtitle' => ['kk' => 'Шежіреге енгізілген барлық түзетулер мен толықтырулар журналы', 'ru' => 'Журнал всех правок и обновлений родословной'],

            // Раздел «Админка»
            'admin_title' => ['kk' => 'Әкімшілік басқару', 'ru' => 'Панель администратора'],
            'admin_subtitle' => ['kk' => 'Пайдаланушылар, рөлдер, шақырулар және деректер импорты', 'ru' => 'Пользователи, роли, приглашения и импорт данных'],

            // Ошибки и доступ
            'access_denied' => ['kk' => 'Қолжетімділік шектелген', 'ru' => 'Доступ ограничен'],
            'access_denied_desc' => ['kk' => 'Бұл бетті көру үшін сәйкес рұқсат деңгейі қажет', 'ru' => 'Для просмотра этой страницы требуются соответствующие права доступа'],
            'login_required' => ['kk' => 'Жүйеге кіру қажет', 'ru' => 'Необходима авторизация'],
            'login_google' => ['kk' => 'Google арқылы кіру', 'ru' => 'Войти через Google'],
        ];
    }
}
