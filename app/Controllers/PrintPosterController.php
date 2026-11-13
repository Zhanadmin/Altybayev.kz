<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\PrintPosterService;

/**
 * Контроллер генерации и вывода печатного плаката шежіре
 */
class PrintPosterController
{
    protected PrintPosterService $service;

    public function __construct()
    {
        $this->service = new PrintPosterService();
    }

    /**
     * Веб-страница плаката шежіре
     * GET /tree/print?root=ID&gens=7&lang=kk
     */
    public function index(Request $request): void
    {
        $user = Auth::user();

        $patriarchs = $this->service->getPotentialPatriarchs();
        $defaultRoot = !empty($patriarchs) ? $patriarchs[0]['id'] : 65; // Алтыбай или первый корень

        $rootId = (int)$request->get('root', $defaultRoot);
        $gens = (int)$request->get('gens', 7);
        $lang = (string)$request->get('lang', 'kk');
        if (!in_array($lang, ['kk', 'ru'], true)) {
            $lang = 'kk';
        }

        $posterData = $this->service->getPosterData($rootId, $gens, $lang);

        Response::view('print_poster', [
            'user'         => $user,
            'patriarchs'   => $patriarchs,
            'rootId'       => $rootId,
            'gens'         => $gens,
            'lang'         => $lang,
            'posterData'   => $posterData,
        ]);
    }

    /**
     * API данных плаката
     * GET /api/tree/print-data?root=ID&gens=7
     */
    public function apiData(Request $request): void
    {
        $rootId = (int)$request->get('root', 65);
        $gens = (int)$request->get('gens', 7);
        $lang = (string)$request->get('lang', 'kk');

        $data = $this->service->getPosterData($rootId, $gens, $lang);
        Response::json($data);
    }

    /**
     * Скачать чистый векторный SVG плакат
     * GET /tree/print/svg?root=ID&gens=7
     */
    public function downloadSvg(Request $request): void
    {
        $rootId = (int)$request->get('root', 65);
        $gens = (int)$request->get('gens', 7);
        $lang = (string)$request->get('lang', 'kk');

        $svg = $this->service->generateSvgPoster($rootId, $gens, 'gold_scroll', $lang);

        header('Content-Type: image/svg+xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="shezhire_altybayev_poster_' . date('Ymd') . '.svg"');
        header('Content-Length: ' . strlen($svg));
        echo $svg;
        exit;
    }
}
