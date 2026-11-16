<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AlbumService;
use App\Services\FaceRecognitionService;
use RuntimeException;

/**
 * TASK-019: экран «Лица» и API распознавания лиц.
 *
 * Страница: GET /faces
 * API (POST — CSRF): сохранить отпечатки, подписать/снять кластер, убрать лицо,
 *                    настройки (админ), запрет человека, удаление отпечатков.
 * GET: /api/face/clusters, /api/face/media/{id}
 */
class FaceController
{
    private ?FaceRecognitionService $svc = null;
    private ?AlbumService $albums = null;

    private function svc(): FaceRecognitionService
    {
        return $this->svc ??= new FaceRecognitionService();
    }

    private function albums(): AlbumService
    {
        return $this->albums ??= new AlbumService();
    }

    private function lang(): string
    {
        return \App\Core\I18n::lang();
    }

    private function tr(string $kk, string $ru): string
    {
        return $this->lang() === 'ru' ? $ru : $kk;
    }

    private static function str(Request $request, string $key): string
    {
        $v = $request->input($key);
        return is_string($v) || is_int($v) ? (string)$v : '';
    }

    private function requireReady(): void
    {
        if (!$this->svc()->isReady()) {
            Response::json(['success' => false, 'error' => $this->tr('Бет танысу әлі қосылмаған (миграция керек)', 'Распознавание лиц ещё не включено (нужна миграция)')], 503);
        }
    }

    private function requireCsrf(Request $request): void
    {
        $token = self::str($request, 'csrf_token') ?: (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'error' => $this->tr('Бет ескірді — жаңартыңыз', 'Страница устарела — обновите её')], 419);
        }
    }

    private function fail(string $kk, string $ru, int $code = 400): void
    {
        Response::json(['success' => false, 'error' => $this->tr($kk, $ru)], $code);
    }

    /** Право на прогон распознавания: editor/admin или участник альбома */
    private function canRunOnMedia(int $mediaId): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        if (($user['role'] ?? '') === 'admin' || ($user['role'] ?? '') === 'editor') {
            return true;
        }
        $media = $this->albums()->getMedia($mediaId);
        if (!$media) {
            return false;
        }
        $album = $this->albums()->getAlbum((int)$media['album_id']);
        return $album ? $this->albums()->canContributeToAlbum($album, $user) : false;
    }

    // ======================================================= страница

    public function faces(Request $request): void
    {
        $ready = $this->svc()->isReady();
        $user = Auth::user();
        Response::view('faces/index', [
            'user'    => $user,
            'ready'   => $ready,
            'summary' => $ready ? $this->svc()->summary() : ['unlabeled' => [], 'labeled' => []],
            'people'  => $ready ? $this->albums()->peopleList() : [],
            'enabled' => $ready ? $this->svc()->enabled() : true,
            'isAdmin' => Auth::isAdmin(),
            'csrf'    => Session::csrfToken(),
        ]);
    }

    // ======================================================= API

    /** POST /api/face/save — сохранить найденные лица фото */
    public function apiSave(Request $request): void
    {
        $this->requireReady();
        $this->requireCsrf($request);
        $mediaId = (int)$request->input('media_id', 0);
        if ($mediaId <= 0) {
            $this->fail('Фото көрсетілмеді', 'Фото не указано');
        }
        if (!$this->canRunOnMedia($mediaId)) {
            $this->fail('Құқық жоқ', 'Нет прав', 403);
        }
        $faces = $request->input('faces');
        if (!is_array($faces)) {
            $this->fail('Беттер дерегі дұрыс емес', 'Некорректные данные лиц');
        }
        try {
            $result = $this->svc()->saveFaces($mediaId, $faces, Auth::id());
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'media_not_found') {
                $this->fail('Фото табылмады', 'Фото не найдено', 404);
            }
            if ($e->getMessage() === 'face_recognition_disabled') {
                $this->fail('Бет танысу өшірілген', 'Распознавание лиц выключено', 403);
            }
            $this->fail('Беттерді сақтау мүмкін болмады', 'Не удалось сохранить лица');
        }
        Response::success($result);
    }

    /** GET /api/face/clusters */
    public function apiSummary(Request $request): void
    {
        $this->requireReady();
        Response::success($this->svc()->summary());
    }

    /** POST /api/face/cluster/assign — подписать группу (выбрать человека из шежіре) */
    public function apiAssign(Request $request): void
    {
        $this->requireReady();
        $this->requireCsrf($request);
        if (!Auth::isEditor()) {
            $this->fail('Құқық жоқ', 'Нет прав', 403);
        }
        $clusterId = (int)$request->input('cluster_id', 0);
        $personId = (int)$request->input('person_id', 0);
        if ($clusterId <= 0 || $personId <= 0) {
            $this->fail('Деректер толық емес', 'Неполные данные');
        }
        try {
            Response::success($this->svc()->assignCluster($clusterId, $personId, Auth::id()));
        } catch (RuntimeException $e) {
            $this->mapError($e);
        }
    }

    /** POST /api/face/cluster/unassign — снять подпись */
    public function apiUnassign(Request $request): void
    {
        $this->requireReady();
        $this->requireCsrf($request);
        if (!Auth::isEditor()) {
            $this->fail('Құқық жоқ', 'Нет прав', 403);
        }
        $clusterId = (int)$request->input('cluster_id', 0);
        try {
            Response::success($this->svc()->unassignCluster($clusterId, Auth::id()));
        } catch (RuntimeException $e) {
            $this->mapError($e);
        }
    }

    /** POST /api/face/remove — убрать ошибочное лицо из группы */
    public function apiRemove(Request $request): void
    {
        $this->requireReady();
        $this->requireCsrf($request);
        if (!Auth::isEditor()) {
            $this->fail('Құқық жоқ', 'Нет прав', 403);
        }
        $faceId = (int)$request->input('face_id', 0);
        try {
            Response::success($this->svc()->removeFace($faceId, Auth::id()));
        } catch (RuntimeException $e) {
            $this->mapError($e);
        }
    }

    /** GET /api/face/media/{id} — рамки лиц на фото */
    public function apiMediaFaces(Request $request, string $id): void
    {
        $this->requireReady();
        Response::success($this->svc()->facesForMedia((int)$id));
    }

    /** POST /api/face/settings — глобальный переключатель (только админ) */
    public function apiSettings(Request $request): void
    {
        $this->requireReady();
        $this->requireCsrf($request);
        if (!Auth::isAdmin()) {
            $this->fail('Тек әкімшіге', 'Только администратор', 403);
        }
        $on = filter_var($request->input('enabled', '1'), FILTER_VALIDATE_BOOLEAN);
        $this->svc()->setEnabled($on);
        Response::success(['enabled' => $this->svc()->enabled()]);
    }

    /** POST /api/face/delete-person — удалить все отпечатки человека (сам человек или админ) */
    public function apiDeletePerson(Request $request): void
    {
        $this->requireReady();
        $this->requireCsrf($request);
        $personId = (int)$request->input('person_id', 0);
        if ($personId <= 0) {
            $this->fail('Адам көрсетілмеді', 'Человек не указан');
        }
        $user = Auth::user();
        $own = (int)($user['person_id'] ?? 0) === $personId;
        if (!$own && !Auth::isAdmin()) {
            $this->fail('Құқық жоқ', 'Нет прав', 403);
        }
        $count = $this->svc()->deletePersonEmbeddings($personId);
        Response::success(['deleted' => $count]);
    }

    /** POST /api/face/opt-out — запретить/разрешить распознавание человека (сам или админ) */
    public function apiOptOut(Request $request): void
    {
        $this->requireReady();
        $this->requireCsrf($request);
        $personId = (int)$request->input('person_id', 0);
        if ($personId <= 0) {
            $this->fail('Адам көрсетілмеді', 'Человек не указан');
        }
        $user = Auth::user();
        $own = (int)($user['person_id'] ?? 0) === $personId;
        if (!$own && !Auth::isAdmin()) {
            $this->fail('Құқық жоқ', 'Нет прав', 403);
        }
        $out = filter_var($request->input('opt_out', '1'), FILTER_VALIDATE_BOOLEAN);
        $deleted = $this->svc()->setPersonOptOut($personId, $out);
        Response::success(['opt_out' => $out, 'deleted' => $deleted]);
    }

    private function mapError(RuntimeException $e): void
    {
        $map = [
            'cluster_not_found' => ['Топ табылмады', 'Группа не найдена', 404],
            'person_not_found'  => ['Адам табылмады', 'Человек не найден', 404],
            'face_not_found'    => ['Бет табылмады', 'Лицо не найдено', 404],
            'person_opt_out'    => ['Бұл адамға тануға тыйым салынған', 'Распознавание этого человека запрещено', 403],
        ];
        $msg = $e->getMessage();
        if (isset($map[$msg])) {
            $this->fail($map[$msg][0], $map[$msg][1], $map[$msg][2]);
        }
        $this->fail('Әрекет орындалмады', 'Действие не выполнено');
    }
}
