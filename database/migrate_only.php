<?php
/**
 * Выборочное применение миграций (НЕ трогает остальные ожидающие).
 *   php database/migrate_only.php --mark=ИМЯ.sql        — записать как применённую, не выполняя
 *   php database/migrate_only.php --run=ИМЯ.sql ...     — выполнить и записать (по порядку аргументов)
 *   php database/migrate_only.php --list                — список ожидающих
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
}
foreach ($args as $a) {
    if ($a === '--list') {
        foreach (glob($dir . '*.sql') as $f) { if (!isset($applied[basename($f)])) echo "PENDING " . basename($f) . "\n"; }
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
