<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

class HomeController
{
    /**
     * Главная страница Шежіре (для авторизованных пользователей)
     */
    public function index(Request $request): void
    {
        $user = Auth::user();

        Response::view('home', [
            'user' => $user
        ]);
    }
}
