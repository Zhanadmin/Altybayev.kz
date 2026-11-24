<?php
/**
 * Выборочное применение миграций (НЕ трогает остальные ожидающие).
 *   php database/migrate_only.php --mark=ИМЯ.sql        — записать как применённую, не выполняя
 *   php database/migrate_only.php --run=ИМЯ.sql ...     — выполнить и записать (по порядку аргументов)
 *   php database/migrate_only.php --list                — список ожидающих
 *   php database/migrate_only.php --clans               — ТОЛЬКО роды (clans) + копия SVG таңб в public/uploads/tamga
 *   php database/migrate_only.php --copy-tamga          — только скопировать SVG таңб из database/seed/tamga
 */
declare(strict_types=1);
if (php_sapi_name() !== 'cli') { exit('CLI only'); }
require_once __DIR__ . '/../app/Core/Config.php';
require_once __DIR__ . '/../app/Core/Database.php';
use App\Core\Config;
use App\Core\Database;
Config::load(__DIR__ . '/../.env');
$db = Database::getConnection();
$applied = array_flip($db->query("SELECT migration FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN));
$batch = (int)$db->query("SELECT COALESCE(MAX(batch),0)+1 FROM schema_migrations")->fetchColumn();
$dir = __DIR__ . '/migrations/';
$ins = $db->prepare("INSERT INTO schema_migrations (migration, batch, applied_at) VALUES (?, ?, NOW())");
$args = array_slice($argv, 1);
if (in_array('--clans', $args, true)) {
    // Только роды: бэкап → схема → seed → verified_* → очистка заглушки (ALT-028)
    $args = ['--run=2026_11_17_clans_000_backup_before_verified.sql', '--run=2026_11_17_clans_tamga_registry.sql',
             '--run=2026_11_17_clans_seed_kazakh_clans.sql', '--run=2026_11_17_clans_remove_schematic_tamga.sql'];
    $v = glob($dir . '2026_11_17_clans_verified_*.sql');
    sort($v, SORT_STRING);
    foreach ($v as $f) { $args[] = '--run=' . basename($f); }
    $args[] = '--run=2026_11_24_clans_clear_placeholder_summary.sql';
    $args[] = '--run=2026_11_24_clans_remove_clarify_phrases.sql';
    $args[] = '--copy-tamga';
}
foreach ($args as $a) {
    if ($a === '--list') {
        foreach (glob($dir . '*.sql') as $f) { if (!isset($applied[basename($f)])) echo "PENDING " . basename($f) . "\n"; }
    } elseif ($a === '--copy-tamga') {
        // ALT-031: public/uploads/* в .gitignore, поэтому SVG таңб приезжают через git только в database/seed/tamga/.
        // Копируем реальные (clan_verified_*) в public/uploads/tamga/; существующие файлы не перезаписываем.
        $src = __DIR__ . '/seed/tamga/';
        $dst = __DIR__ . '/../public/uploads/tamga/';
        if (!is_dir($dst)) { mkdir($dst, 0775, true); }
        $copied = 0; $kept = 0;
        foreach (glob($src . 'clan_verified_*.svg') ?: [] as $f) {
            $t = $dst . basename($f);
            if (is_file($t)) { $kept++; continue; }
            if (copy($f, $t)) { $copied++; } else { echo "FAIL copy " . basename($f) . "\n"; }
        }
        echo "TAMGA copied=$copied kept=$kept\n";
    } elseif (str_starts_with($a, '--mark=')) {
        $n = substr($a, 7);
        if (!isset($applied[$n])) { $ins->execute([$n, $batch]); echo "MARKED $n\n"; }
    } elseif (str_starts_with($a, '--run=')) {
        $n = substr($a, 6);
        if (isset($applied[$n])) { echo "SKIP $n\n"; continue; }
        $sql = file_get_contents($dir . $n);
        $db->exec($sql);
        $ins->execute([$n, $batch]);
        $applied[$n] = true;
        echo "OK $n\n";
    }
}
