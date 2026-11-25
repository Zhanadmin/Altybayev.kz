<?php
declare(strict_types=1);

// ALT-098 / ALT-107: общий набор подписей и помощников для страницы «Что не заполнено» и её строки (missing_row.php).
// Возвращает массив; подключается через require и в missing.php, и в MissingDataController при отдаче одной строки.

use App\Core\I18n;

$lang = I18n::lang();
$T = static function (array $t) use ($lang): string {
    return $t[$lang] ?? $t['ru'];
};

return [
    'lang' => $lang,
    'T'    => $T,
    'e'    => static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'),
    'gapLabels' => [
        'birth'       => ['kk' => 'Туған күні', 'ru' => 'Дата рождения', 'en' => 'Birth date', 'es' => 'Fecha de nacimiento'],
        'photo'       => ['kk' => 'Фото', 'ru' => 'Фото', 'en' => 'Photo', 'es' => 'Foto'],
        'clan'        => ['kk' => 'Ру', 'ru' => 'Ру (род)', 'en' => 'Clan', 'es' => 'Clan'],
        'nationality' => ['kk' => 'Ұлты', 'ru' => 'Национальность', 'en' => 'Nationality', 'es' => 'Nacionalidad'],
        'father'      => ['kk' => 'Әкесі', 'ru' => 'Отец', 'en' => 'Father', 'es' => 'Padre'],
        'mother'      => ['kk' => 'Шешесі', 'ru' => 'Мать', 'en' => 'Mother', 'es' => 'Madre'],
        'gender'      => ['kk' => 'Жынысы', 'ru' => 'Пол', 'en' => 'Gender', 'es' => 'Sexo'],
        'marriage'    => ['kk' => 'Үйлену күні', 'ru' => 'Дата брака', 'en' => 'Marriage date', 'es' => 'Fecha de boda'],
        'death'       => ['kk' => 'Қайтыс болған күні', 'ru' => 'Дата смерти', 'en' => 'Death date', 'es' => 'Fecha de fallecimiento'],
        'maiden'      => ['kk' => 'Қыз кезіндегі тегі', 'ru' => 'Девичья фамилия', 'en' => 'Maiden name', 'es' => 'Apellido de soltera'],
        'contacts'    => ['kk' => 'Байланыс', 'ru' => 'Контакты', 'en' => 'Contacts', 'es' => 'Contactos'],
        'zheti'       => ['kk' => 'Жеті ата', 'ru' => 'Жеті ата (цепочка < 7)', 'en' => 'Seven fathers (chain < 7)', 'es' => 'Siete padres (cadena < 7)'],
    ],
    'yearOnly' => $T(['kk' => 'тек жылы', 'ru' => 'только год', 'en' => 'year only', 'es' => 'solo el año']),
    'editLabel' => 'Өзгерту',
    'genLabel' => static function (string $k) use ($T): string {
        if ($k === 'ancestors') {
            return $T(['kk' => 'Ата-бабалар', 'ru' => 'Предки', 'en' => 'Ancestors', 'es' => 'Antepasados']);
        }
        return $T(['kk' => $k . '-ұрпақ', 'ru' => $k . '-е поколение', 'en' => 'Generation ' . $k, 'es' => 'Generación ' . $k]);
    },
];
