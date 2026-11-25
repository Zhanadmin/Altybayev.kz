<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\ClanService;
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
     * ALT-092: страница «Жеті ата» — GET /zheti-ata[?id=ID]
     * По умолчанию выбран тот, кто вошёл (или человек из ?id=).
     */
    public function page(Request $request): void
    {
        $user = Auth::user();
        $defaultId = !empty($user['person_id']) ? (int)$user['person_id'] : 0;
        $reqId = (int)$request->get('id', 0);

        $persons = Database::getConnection()
            ->query('SELECT id, first_name, last_name, maiden_name, birth_year FROM persons WHERE is_deleted = 0 ORDER BY first_name, last_name')
            ->fetchAll(\PDO::FETCH_ASSOC);

        $ids = array_map(static fn ($p) => (int)$p['id'], $persons);
        $selectedId = in_array($reqId, $ids, true) ? $reqId : (in_array($defaultId, $ids, true) ? $defaultId : 0);

        Response::view('zheti_ata', [
            'user'       => $user,
            'persons'    => $persons,
            'selectedId' => $selectedId,
            'canEdit'    => Auth::canEdit(),
            'isAdmin'    => Auth::isAdmin(),
        ]);
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
            'clan'      => $this->clanForPerson($this->service->getPerson($personId) ?? []),
        ]);
    }

    /**
     * ALT-101: данные ру для герба — GET /api/zheti-ata/clan?name=НАЗВАНИЕ
     */
    public function clan(Request $request): void
    {
        $clan = $this->lookupClan((string)$request->get('name', ''));
        if ($clan === null) {
            Response::error('Ру табылмады / Ру не найден.', 404);
            return;
        }
        Response::json(['success' => true, 'clan' => $clan]);
    }

    /** ALT-103: ру/таңба показываются только казахам (пустая национальность — как раньше). */
    protected function clanForPerson(array $person): ?array
    {
        $nat = trim((string)($person['nationality'] ?? ''));
        if ($nat !== '' && !\App\Config\Nationalities::isKazakh($nat)) {
            return null;
        }
        return $this->lookupClan((string)($person['clan_ru'] ?? ''));
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
        // ALT-090: SELECT * — колонка «uran» может ещё не существовать; регистр не важен
        $stmt = Database::getConnection()->prepare('SELECT * FROM clans WHERE LOWER(name_ru) = LOWER(?) OR LOWER(name) = LOWER(?) LIMIT 1');
        $stmt->execute([$name, $name]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $uran = trim((string)($row['uran'] ?? ''));
        if ($uran === '') {
            $uran = self::uranFromSummary((string)($row['summary'] ?? ''));
        }
        return [
            'name'      => $row['name'],
            'name_ru'   => $row['name_ru'] ?? null,
            'zhuz'      => $row['zhuz'] ?? 'other',
            'tamga_url' => (new ClanService())->urlFor($row['tamga_file'] ?? null), // настоящая таңба из «Ру таңбалары»
            'uran'      => $uran,
        ];
    }

    /** ALT-090: ұран из описания ру («… Ұран: Қалтағай. Источник …»); пусто, если не указан. */
    public static function uranFromSummary(string $summary): string
    {
        if (!preg_match('/Ұран:\s*([^.;\n]+)/u', $summary, $m)) {
            return '';
        }
        $u = trim($m[1]);
        return preg_match('/^(не указан|жоқ|—|-)/ui', $u) ? '' : $u;
    }
}
