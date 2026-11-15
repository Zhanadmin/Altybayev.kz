<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

/**
 * TASK-014: публичные страницы для проверки Google OAuth (доступны без входа).
 */
class LegalController
{
    public function privacy(Request $request): void
    {
        Response::view('legal', ['page' => 'privacy']);
    }

    public function terms(Request $request): void
    {
        Response::view('legal', ['page' => 'terms']);
    }
}
