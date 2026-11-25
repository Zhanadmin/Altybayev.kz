<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\ZhetiAtaService;

/**
 * Контроллер проверки «Жеті ата» и родословных цепочек
 */
class ZhetiAtaController
{
    protected ZhetiAtaService $service;

    public function __construct()
    {
        $this->service = new ZhetiAtaService();
    }

    /**
     * API: Проверка Жеті ата между двумя персонами
     * GET/POST /api/zheti-ata?a=ID&b=ID
     */
    public function check(Request $request): void
    {
        $personAId = (int)($request->get('a', $request->post('a', 0)));
        $personBId = (int)($request->get('b', $request->post('b', 0)));

        if ($personAId <= 0 || $personBId <= 0) {
            Response::error('Екі адамның ID-і көрсетілуі тиіс / Укажите ID двух персон для проверки (параметры a и b).', 400);
            return;
        }

        $result = $this->service->checkZhetiAta($personAId, $personBId);
        Response::json($result);
    }

    /**
     * API: Получить 7 ата персоны
     * GET /api/zheti-ata/seven?id=ID
     */
    public function sevenAncestors(Request $request): void
    {
        $personId = (int)($request->get('id', 0));
        if ($personId <= 0) {
            // Если ID не передан, берем персону текущего пользователя
            $user = Auth::user();
            $personId = (int)($user['person_id'] ?? 6); // По умолчанию Жандос (ID 6)
        }

        if ($this->service->getPerson($personId) === null) {
            Response::error('Тұлға табылмады / Персона не найдена.', 404);
            return;
        }

        $chain = $this->service->getSevenAncestors($personId);
        Response::json([
            'success'   => true,
            'person_id' => $personId,
            'ancestors' => $chain,
            'fathers'   => $this->service->getSevenFathers($personId), // ALT-089
            'clan'      => $this->lookupClan((string)($this->service->getPerson($personId)['clan_ru'] ?? '')),
        ]);
    }

    /**
     * ALT-089: ру и жүз для таблички (по названию ру из карточки человека)
     */
    protected function lookupClan(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $stmt = Database::getConnection()->prepare('SELECT name, name_ru, zhuz FROM clans WHERE name_ru = ? OR name = ? LIMIT 1');
        $stmt->execute([$name, $name]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
