<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\KinshipService;
use App\Services\MemorialService;

/**
 * Контроллер памятных дат, дней рождения и поминальных годовщин
 */
class MemorialController
{
    protected MemorialService $service;

    public function __construct()
    {
        $kinship = new KinshipService();
        $this->service = new MemorialService($kinship);
    }

    /**
     * Раздел «Есте қаларлық күндер / Памятные даты» (веб-страница)
     */
    public function index(Request $request): void
    {
        $user = Auth::user();
        $focusPersonId = !empty($user['person_id']) ? (int)$user['person_id'] : 6;

        $daysAhead = (int)($request->get('days', 90));
        if ($daysAhead <= 0 || $daysAhead > 365) {
            $daysAhead = 90;
        }

        $birthdays = $this->service->getUpcomingBirthdays($daysAhead, $focusPersonId);
        $anniversaries = $this->service->getMemorialAnniversaries($daysAhead, $focusPersonId);

        Response::view('dates/index', [
            'user'          => $user,
            'userPersonId'  => $focusPersonId,
            'birthdays'     => $birthdays,
            'anniversaries' => $anniversaries,
            'daysAhead'     => $daysAhead,
            'canEdit'       => Auth::canEdit(),
            'isAdmin'       => Auth::isAdmin(),
        ]);
    }

    /**
     * API: Сводка всех памятных дат (дни рождения + годовщины)
     * GET /api/memorial-dates?days=60
     */
    public function apiSummary(Request $request): void
    {
        $daysAhead = (int)($request->get('days', 60));
        if ($daysAhead <= 0 || $daysAhead > 365) {
            $daysAhead = 60;
        }

        $user = Auth::user();
        $focusPersonId = !empty($user['person_id']) ? (int)$user['person_id'] : 6;

        $summary = $this->service->getMemorialSummary($daysAhead, $focusPersonId);
        Response::json($summary);
    }

    /**
     * API: Только дни рождения живых
     * GET /api/memorial-dates/birthdays?days=60
     */
    public function apiBirthdays(Request $request): void
    {
        $daysAhead = (int)($request->get('days', 60));
        $user = Auth::user();
        $focusPersonId = !empty($user['person_id']) ? (int)$user['person_id'] : 6;

        $birthdays = $this->service->getUpcomingBirthdays($daysAhead, $focusPersonId);
        Response::json([
            'success'   => true,
            'count'     => count($birthdays),
            'birthdays' => $birthdays,
        ]);
    }

    /**
     * API: Только годовщины ушедших
     * GET /api/memorial-dates/anniversaries?days=60
     */
    public function apiAnniversaries(Request $request): void
    {
        $daysAhead = (int)($request->get('days', 60));
        $user = Auth::user();
        $focusPersonId = !empty($user['person_id']) ? (int)$user['person_id'] : 6;

        $anniversaries = $this->service->getMemorialAnniversaries($daysAhead, $focusPersonId);
        Response::json([
            'success'       => true,
            'count'         => count($anniversaries),
            'anniversaries' => $anniversaries,
        ]);
    }
}
