<?php
/**
 * Миграция: Очистка биографий и заметок от HTML-кода, GEDCOM CONC/CONT и тегов
 * Проект: altybayev.kz (TASK-007, замечание Жандоса от 13.11 11:53)
 */

declare(strict_types=1);

if (!isset($db) || !($db instanceof PDO)) {
    throw new RuntimeException("PDO \$db недоступен для миграции биографий");
}

function cleanBiographyTextMigration(string $text): string {
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Склеиваем CONC и CONT из GEDCOM
    $text = preg_replace('/^[0-9]+\s+CONC\s+/m', '', $text);
    $text = preg_replace('/^[0-9]+\s+CONT\s+/m', "\n", $text);
    $text = preg_replace('/\bCONC\s+/i', '', $text);
    $text = preg_replace('/\bCONT\s+/i', "\n", $text);
    
    // Преобразуем блочные HTML теги в переносы строк
    $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
    $text = preg_replace('/<\/p>/i', "\n\n", $text);
    $text = preg_replace('/<\/div>/i', "\n\n", $text);
    $text = preg_replace('/<\/li>/i', "\n", $text);
    $text = preg_replace('/<li[^>]*>/i', "• ", $text);

    // Удаляем все оставшиеся теги HTML
    $text = strip_tags($text);

    // Удаляем служебные теги GEDCOM
    $text = preg_replace('/@[A-Z0-9_]+@/', '', $text);

    // Нормализуем пробелы и переносы
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace("/[ \t]+/", " ", $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);

    return trim($text);
}

$stmt = $db->query("SELECT id, biography FROM persons WHERE biography IS NOT NULL AND TRIM(biography) != ''");
$personsWithBio = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updateStmt = $db->prepare("UPDATE persons SET biography = ? WHERE id = ?");

$cleanedCount = 0;
foreach ($personsWithBio as $p) {
    $cleaned = cleanBiographyTextMigration((string)$p['biography']);
    $updateStmt->execute([$cleaned, $p['id']]);
    $cleanedCount++;
}

// Запись в аудит-лог
$aStmt = $db->prepare("
    INSERT INTO `audit_logs` (`user_id`, `user_name`, `user_email`, `action`, `entity_table`, `entity_id`, `summary`, `created_at`)
    VALUES (1, 'Жандос Алтыбаев', 'system@altybayev.kz', 'UPDATE', 'persons', 0, ?, NOW())
");
$aStmt->execute(["Миграция 2026_11_13_clean_biographies: очищены биографии {$cleanedCount} персон от HTML/GEDCOM тегов"]);
