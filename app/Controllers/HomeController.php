<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\KinshipService;
use App\Services\MemorialService;

class HomeController
{
    /**
     * Главная страница Шежіре (для авторизованных пользователей)
     */
    public function index(Request $request): void
    {
        $user = Auth::user();
        $userPersonId = !empty($user['person_id']) ? (int)$user['person_id'] : 6;

        $kinship = new KinshipService();
        $memorialService = new MemorialService($kinship);

        $birthdays = $memorialService->getUpcomingBirthdays(45, $userPersonId);
        $anniversaries = $memorialService->getMemorialAnniversaries(45, $userPersonId);

        Response::view('home', [
            'user'          => $user,
            'userPersonId'  => $userPersonId,
            'birthdays'     => $birthdays,
            'anniversaries' => $anniversaries,
        ]);
    }
}
