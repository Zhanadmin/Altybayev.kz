<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\I18n;

$lang = I18n::lang();
$ru = ($lang === 'ru');
$notFound = !empty($notFound);
$title = $notFound ? ($ru ? 'Не найдено' : 'Табылмады') : ($ru ? 'Фотоальбомы' : 'Фотоальбомдар');
$activeNav = 'events';
$dsThemeReady = true;

require __DIR__ . '/../layout/master_start.php';
?>
<div class="island" style="text-align:center;padding:40px 20px;margin-top:16px">
    <svg viewBox="0 0 140 90" style="width:140px;height:90px;color:var(--c-accent)" aria-hidden="true" focusable="false"><use href="#o-sprout"/></svg>
    <?php if ($notFound): ?>
        <h2 style="margin-top:12px"><?= $ru ? 'Альбом или мероприятие не найдено' : 'Альбом немесе іс-шара табылмады' ?></h2>
        <p style="margin-top:8px"><a href="/events"><?= $ru ? 'К мероприятиям' : 'Іс-шараларға' ?></a> · <a href="/families"><?= $ru ? 'К семьям' : 'Отбасыларға' ?></a></p>
    <?php else: ?>
        <h2 style="margin-top:12px"><?= $ru ? 'Фотоальбомы скоро появятся' : 'Фотоальбомдар жақында ашылады' ?></h2>
        <p class="muted" style="margin-top:8px"><?= $ru ? 'Раздел готовится к запуску.' : 'Бөлім іске қосылуға дайындалуда.' ?></p>
        <?php if (Auth::isAdmin()): ?>
            <p class="small muted" style="margin-top:8px"><?= $ru ? 'Администратору: запустите database/migrate.php (миграция 2026_11_15_add_albums_and_events.sql).' : 'Әкімшіге: database/migrate.php іске қосыңыз (2026_11_15_add_albums_and_events.sql миграциясы).' ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../layout/master_end.php'; ?>
