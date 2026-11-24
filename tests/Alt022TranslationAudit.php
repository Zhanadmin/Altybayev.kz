<?php
declare(strict_types=1);
// ALT-022: аудит перевода. Собирает кириллические строки из views/ и app/, прогоняет через слой перевода en/es
// и выдаёт: (1) остаток кириллицы, (2) строки, которые только транслитерированы, а не переведены.
// Запуск: php tests\Alt022TranslationAudit.php
require_once __DIR__ . '/../app/Core/I18n.php';

use App\Core\I18n;

$root = dirname(__DIR__);
$files = [];
foreach (['views', 'app/Controllers', 'app/Services'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$dir", FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && $f->getExtension() === 'php') { $files[] = $f->getPathname(); }
    }
}

// Строки: литералы в кавычках и текстовые узлы HTML, содержащие кириллицу.
$strings = [];
foreach ($files as $file) {
    $src = (string)file_get_contents($file);
    $rel = str_replace('\\', '/', substr($file, strlen($root) + 1));
    if (preg_match_all("/'((?:[^'\\\\\\n]|\\\\.){2,200})'|\"((?:[^\"\\\\\\n]|\\\\.){2,200})\"/u", $src, $m)) {
        foreach (array_merge($m[1], $m[2]) as $s) {
            $s = stripcslashes((string)$s);
            if (preg_match('/[\x{0400}-\x{04FF}]/u', $s) && !str_contains($s, '<?') && !str_contains($s, '$')) {
                $strings[trim($s)][$rel] = true;
            }
        }
    }
    if (preg_match_all('/>([^<>{}$?]*[\x{0400}-\x{04FF}][^<>{}$?]*)</u', $src, $m)) {
        foreach ($m[1] as $s) {
            $s = trim(html_entity_decode($s));
            if ($s !== '') { $strings[$s][$rel] = true; }
        }
    }
}

$report = [];
foreach (['en', 'es'] as $lang) {
    I18n::setLang($lang);
    $found = 0; $residual = []; $translit = [];
    foreach ($strings as $s => $where) {
        // Казахская ветка шаблона на en/es не выводится (берётся русская) — пропускаем строки с казахскими буквами.
        if (preg_match('/[әғқңөұүһіӘҒҚҢӨҰҮҺІ]/u', $s)) { continue; }
        $found++;
        $out = I18n::translateText($s, true, $lang);
        $loc = implode(',', array_keys($where));
        if (preg_match('/[\x{0400}-\x{04FF}]/u', $out)) {
            $residual[] = "$s  [$loc]";
            continue;
        }
        // Транслитерация вместо перевода: результат совпадает с чистой схемой, а слов >= 2 и не все с заглавной (не имя).
        $words = preg_split('/\s+/u', $s, -1, PREG_SPLIT_NO_EMPTY);
        $allCap = true;
        foreach ($words as $w) {
            if (!preg_match('/^[\p{Lu}\d]/u', $w)) { $allCap = false; break; }
        }
        if (count($words) >= 2 && !$allCap && $out === I18n::latinize($s, $lang)) {
            $translit[] = "$s → $out  [$loc]";
        }
    }
    $report[$lang] = [$found, $residual, $translit];
}

echo "=== АУДИТ ПЕРЕВОДА ===\nСтрок с кириллицей в шаблонах: " . count($strings) . "\n";
foreach ($report as $lang => [$found, $residual, $translit]) {
    echo "\n--- $lang: русских строк проверено: $found, остаток кириллицы: " . count($residual) . ", транслит вместо перевода: " . count($translit) . "\n";
    foreach ($residual as $r) { echo "  CYR  $r\n"; }
    foreach ($translit as $t) { echo "  TRL  $t\n"; }
}
