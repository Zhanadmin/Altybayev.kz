<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\ClanService;
use PDO;

/**
 * TASK-030: «Ру таңбалары» — справочник ру со знаками. Доступен editor и admin.
 *  GET  /admin/clans             — страница
 *  GET  /api/clans               — список для выбора ру (любой вошедший)
 *  POST /api/clans/save          — создать/изменить ру
 *  POST /api/clans/delete        — удалить ру (только без людей)
 *  POST /api/clans/tamga/upload  — загрузить таңба (SVG/PNG, в т.ч. нарисованный в редакторе)
 *  POST /api/clans/tamga/delete  — убрать таңба
 */
class ClanController
{
    public function index(Request $request): void
    {
        if (!Auth::isEditor()) {
            Response::redirect('/?error=forbidden');
        }
        $svc = new ClanService();
        $ready = $svc->hasRegistry();
        Response::view('admin/clans', [
            'clans' => $ready ? $svc->all() : [],
            'registryReady' => $ready,
        ]);
    }

    public function list(Request $request): void
    {
        $svc = new ClanService();
        if (!$svc->hasRegistry()) {
            Response::json(['success' => true, 'ready' => false, 'clans' => []]);
        }
        $out = [];
        foreach ($svc->all() as $c) {
            $out[] = [
                'id' => (int)$c['id'],
                'name' => $c['name'],
                'name_ru' => $c['name_ru'] ?? null,
                'zhuz' => $c['zhuz'],
                'tamga_url' => $c['tamga_url'],
            ];
        }
        Response::json(['success' => true, 'ready' => true, 'clans' => $out]);
    }

    private function guard(Request $request): ClanService
    {
        if (!Auth::check() || !Auth::isEditor()) {
            Response::json(['success' => false, 'error' => 'Рұқсат жоқ / Нет прав'], 403);
        }
        if (!Session::validateCsrf((string)$request->post('csrf_token', ''))) {
            Response::json(['success' => false, 'error' => 'Сессия ескірді, бетті жаңартыңыз / Сессия устарела'], 419);
        }
        $svc = new ClanService();
        if (!$svc->hasRegistry()) {
            Response::json(['success' => false, 'error' => 'Миграция әлі қолданылмаған / Миграция справочника ещё не применена'], 409);
        }
        return $svc;
    }

    private function audit(string $action, int $id, string $summary, ?array $old, ?array $new): void
    {
        $u = Auth::user();
        try {
            Database::getConnection()->prepare("
                INSERT INTO audit_logs (user_id, user_name, user_email, action, entity_table, entity_id, summary, old_values, new_values, created_at)
                VALUES (?, ?, ?, ?, 'clans', ?, ?, ?, ?, NOW())
            ")->execute([
                $u['id'] ?? null, $u['name'] ?? '', $u['email'] ?? null, $action, $id,
                mb_substr($summary, 0, 255),
                $old !== null ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
                $new !== null ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Throwable $e) {
            // аудит не должен ломать сохранение
        }
    }

    private static function brief(?array $c): ?array
    {
        return $c ? array_intersect_key($c, array_flip(['name', 'name_ru', 'zhuz', 'summary', 'tamga_file'])) : null;
    }

    public function save(Request $request): void
    {
        $svc = $this->guard($request);
        $id = (int)$request->post('id', 0);
        $old = $id > 0 ? $svc->find($id) : null;
        try {
            $id = $svc->save($id, [
                'name' => $request->post('name', ''),
                'name_ru' => $request->post('name_ru', ''),
                'zhuz' => $request->post('zhuz', 'other'),
                'summary' => $request->post('summary', ''),
            ]);
        } catch (\RuntimeException $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()], 422);
        }
        $new = $svc->find($id);
        $this->audit($old ? 'UPDATE' : 'CREATE', $id, 'Ру: ' . ($new['name'] ?? ''), self::brief($old), self::brief($new));
        Response::json(['success' => true, 'clan' => $new]);
    }

    public function delete(Request $request): void
    {
        $svc = $this->guard($request);
        $id = (int)$request->post('id', 0);
        $old = $svc->find($id);
        try {
            $svc->delete($id);
        } catch (\RuntimeException $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()], 422);
        }
        $this->audit('DELETE', $id, 'Ру өшірілді: ' . ($old['name'] ?? ''), self::brief($old), null);
        Response::json(['success' => true]);
    }

    public function tamgaUpload(Request $request): void
    {
        $svc = $this->guard($request);
        $id = (int)$request->post('id', 0);
        $file = $_FILES['tamga'] ?? null;
        if (!$file) {
            Response::json(['success' => false, 'error' => 'Файл таңдалмады / Файл не выбран'], 422);
        }
        $old = $svc->find($id);
        try {
            $svc->saveTamgaUpload($id, $file);
        } catch (\RuntimeException $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()], 422);
        }
        $new = $svc->find($id);
        $this->audit('UPDATE', $id, 'Таңба жаңартылды: ' . ($new['name'] ?? ''), self::brief($old), self::brief($new));
        Response::json(['success' => true, 'tamga_url' => $new['tamga_url'] ?? null]);
    }

    public function tamgaDelete(Request $request): void
    {
        $svc = $this->guard($request);
        $id = (int)$request->post('id', 0);
        $old = $svc->find($id);
        try {
            $svc->deleteTamga($id);
        } catch (\RuntimeException $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()], 422);
        }
        $this->audit('UPDATE', $id, 'Таңба өшірілді: ' . ($old['name'] ?? ''), self::brief($old), null);
        Response::json(['success' => true]);
    }
}
