<?php
declare(strict_types=1);

use App\Core\I18n;

/** @var array $requests */
/** @var array $attempts */
/** @var array $persons */
/** @var string $csrf */

$lang = I18n::lang();
// ALT-104: тексты на всех языках сайта (kk, ru, en, es)
$T = static function (array $t) use ($lang): string {
    return $t[$lang] ?? $t['ru'];
};
$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$title = $T(['kk' => 'Қолжетімділік сұраныстары', 'ru' => 'Запросы на доступ', 'en' => 'Access requests', 'es' => 'Solicitudes de acceso']);
$activeNav = 'access';
$dsThemeReady = true;

require __DIR__ . '/../layout/master_start.php';

$statusLabel = [
    'pending'  => ['kk' => 'Күтуде', 'ru' => 'Ожидает', 'en' => 'Pending', 'es' => 'Pendiente'],
    'approved' => ['kk' => 'Мақұлданды', 'ru' => 'Одобрен', 'en' => 'Approved', 'es' => 'Aprobada'],
    'rejected' => ['kk' => 'Қабылданбады', 'ru' => 'Отклонён', 'en' => 'Rejected', 'es' => 'Rechazada'],
    'blocked'  => ['kk' => 'Бұғатталды', 'ru' => 'Заблокирован', 'en' => 'Blocked', 'es' => 'Bloqueada'],
];
$resultLabel = [
    'oauth_start'  => ['kk' => 'Кіру басталды', 'ru' => 'Начат вход', 'en' => 'Sign-in started', 'es' => 'Inicio de sesión'],
    'request'      => ['kk' => 'Сұраныс', 'ru' => 'Запрос на доступ', 'en' => 'Access request', 'es' => 'Solicitud'],
    'blocked'      => ['kk' => 'Бұғатталған', 'ru' => 'Заблокированный', 'en' => 'Blocked person', 'es' => 'Bloqueado'],
    'rate_limited' => ['kk' => 'Лимит асты', 'ru' => 'Превышен лимит', 'en' => 'Rate limit hit', 'es' => 'Límite superado'],
];
?>
<style>
.ar-wrap { display: flex; flex-direction: column; gap: 20px; font-size: var(--fs-lg, 16px); line-height: 1.5; }
.ar-card { border: 1px solid var(--c-border, #ddd); border-radius: 12px; padding: 14px 16px; display: flex; flex-direction: column; gap: 8px; }
.ar-card.pending { border-color: var(--c-accent, #2a6df4); }
.ar-head { display: flex; flex-wrap: wrap; gap: 8px 16px; align-items: baseline; justify-content: space-between; }
.ar-meta { opacity: .75; font-size: 14px; }
.ar-msg { white-space: pre-wrap; padding: 8px 10px; border-radius: 8px; background: rgba(128,128,128,.08); }
.ar-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.ar-actions select, .ar-actions input { height: 40px; font: inherit; max-width: 100%; }
.ar-table { width: 100%; border-collapse: collapse; }
.ar-table th, .ar-table td { padding: 8px 10px; text-align: left; border-bottom: 1px solid var(--c-border, #e3e3e3); font-weight: 400; }
.ar-table th { opacity: .7; white-space: nowrap; }
.ar-note { min-height: 1.4em; }
</style>

<div class="ds-page-head">
    <div>
        <h1><?= $e($title) ?></h1>
        <p class="sub"><?= $e($T([
            'kk' => 'Белгісіз адамдар Google арқылы кіргенде осында түседі. Мақұлдағанша шежіре көрінбейді.',
            'ru' => 'Сюда попадают незнакомые люди, вошедшие через Google. До одобрения шежіре им не видно.',
            'en' => 'Unknown people who sign in with Google appear here. They see no family data until approved.',
            'es' => 'Aquí aparecen las personas desconocidas que entran con Google. No ven datos hasta ser aprobadas.',
        ])) ?></p>
    </div>
</div>

<div class="ar-wrap">
    <p class="ar-note" id="ar-note" role="status"></p>

    <?php if (!$requests): ?>
        <p id="ar-empty"><?= $e($T(['kk' => 'Сұраныстар жоқ', 'ru' => 'Запросов пока нет', 'en' => 'No requests yet', 'es' => 'Aún no hay solicitudes'])) ?></p>
    <?php endif; ?>

    <?php foreach ($requests as $r):
        $st = (string)$r['status'];
    ?>
    <div class="ar-card <?= $e($st) ?>" data-id="<?= (int)$r['id'] ?>" data-status="<?= $e($st) ?>">
        <div class="ar-head">
            <div>
                <strong><?= $e($r['name'] !== '' ? $r['name'] : $r['email']) ?></strong>
                <span class="ar-meta"><?= $e($r['email']) ?></span>
            </div>
            <span class="badge" data-role="status"><?= $e($T($statusLabel[$st] ?? $statusLabel['pending'])) ?></span>
        </div>
        <div class="ar-meta">
            <?= $e($T(['kk' => 'Уақыты', 'ru' => 'Время', 'en' => 'Time', 'es' => 'Hora'])) ?>: <?= $e($r['last_at']) ?>
            · IP: <?= $e($r['ip']) ?><?= $r['place'] !== '' ? ' (' . $e($r['place']) . ')' : '' ?>
            · <?= $e($T(['kk' => 'Әрекет', 'ru' => 'Попыток', 'en' => 'Attempts', 'es' => 'Intentos'])) ?>: <?= (int)$r['attempts'] ?>
            · <?= $e($T(['kk' => 'Хат', 'ru' => 'Письмо', 'en' => 'Email', 'es' => 'Correo'])) ?>: <?= !empty($r['mail_sent']) ? '✓' : '—' ?>
        </div>
        <div class="ar-msg"><?= (string)$r['message'] !== '' ? $e($r['message']) : '<em>' . $e($T(['kk' => 'Хабарлама жоқ', 'ru' => 'Сообщения нет', 'en' => 'No message', 'es' => 'Sin mensaje'])) . '</em>' ?></div>

        <div class="ar-actions">
            <?php if ($st !== 'approved' && $st !== 'blocked'): ?>
                <select class="input" data-f="role" aria-label="<?= $e($T(['kk' => 'Рөл', 'ru' => 'Роль', 'en' => 'Role', 'es' => 'Rol'])) ?>">
                    <option value="viewer"><?= $e($T(['kk' => 'Оқырман', 'ru' => 'Читатель', 'en' => 'Viewer', 'es' => 'Lector'])) ?></option>
                    <option value="editor"><?= $e($T(['kk' => 'Редактор', 'ru' => 'Редактор', 'en' => 'Editor', 'es' => 'Editor'])) ?></option>
                    <option value="admin"><?= $e($T(['kk' => 'Әкімші', 'ru' => 'Администратор', 'en' => 'Administrator', 'es' => 'Administrador'])) ?></option>
                </select>
                <select class="input" data-f="person" aria-label="<?= $e($T(['kk' => 'Ағаштағы адам', 'ru' => 'Человек в древе', 'en' => 'Person in the tree', 'es' => 'Persona en el árbol'])) ?>">
                    <option value="0"><?= $e($T(['kk' => '— байламау —', 'ru' => '— не привязывать —', 'en' => '— do not link —', 'es' => '— no vincular —'])) ?></option>
                    <?php foreach ($persons as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"><?= $e($p['n'] !== '' ? $p['n'] : ('#' . $p['id'])) ?> (#<?= (int)$p['id'] ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-primary" data-act="approve"><?= $e($T(['kk' => 'Мақұлдау', 'ru' => 'Одобрить', 'en' => 'Approve', 'es' => 'Aprobar'])) ?></button>
            <?php endif; ?>
            <?php if ($st === 'pending'): ?>
                <button type="button" class="btn" data-act="reject"><?= $e($T(['kk' => 'Қабылдамау', 'ru' => 'Отклонить', 'en' => 'Reject', 'es' => 'Rechazar'])) ?></button>
            <?php endif; ?>
            <?php if ($st !== 'blocked'): ?>
                <button type="button" class="btn" data-act="block"><?= $e($T(['kk' => 'Бұғаттау', 'ru' => 'Заблокировать', 'en' => 'Block', 'es' => 'Bloquear'])) ?></button>
            <?php else: ?>
                <button type="button" class="btn" data-act="reset"><?= $e($T(['kk' => 'Бұғаттан шығару', 'ru' => 'Разблокировать', 'en' => 'Unblock', 'es' => 'Desbloquear'])) ?></button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <h2><?= $e($T(['kk' => 'Кіру әрекеттерінің журналы', 'ru' => 'Журнал попыток входа', 'en' => 'Sign-in attempts log', 'es' => 'Registro de intentos'])) ?></h2>
    <?php if (!$attempts): ?>
        <p><?= $e($T(['kk' => 'Әзірге бос', 'ru' => 'Пока пусто', 'en' => 'Empty so far', 'es' => 'Vacío por ahora'])) ?></p>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table class="ar-table" id="ar-attempts">
        <thead><tr>
            <th><?= $e($T(['kk' => 'Уақыты', 'ru' => 'Время', 'en' => 'Time', 'es' => 'Hora'])) ?></th>
            <th>E-mail</th>
            <th>IP</th>
            <th><?= $e($T(['kk' => 'Нәтиже', 'ru' => 'Результат', 'en' => 'Result', 'es' => 'Resultado'])) ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($attempts as $a): ?>
            <tr>
                <td><?= $e($a['created_at']) ?></td>
                <td><?= $e($a['email'] !== '' ? $a['email'] : '—') ?></td>
                <td><?= $e($a['ip']) ?><?= $a['place'] !== '' ? ' (' . $e($a['place']) . ')' : '' ?></td>
                <td><?= $e(isset($resultLabel[$a['result']]) ? $T($resultLabel[$a['result']]) : $a['result']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var csrf = <?= json_encode($csrf) ?>;
    var note = document.getElementById('ar-note');
    document.querySelectorAll('.ar-card [data-act]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var card = btn.closest('.ar-card');
            var act = btn.getAttribute('data-act');
            var fd = new FormData();
            fd.append('csrf_token', csrf);
            fd.append('id', card.getAttribute('data-id'));
            fd.append('action', act);
            var role = card.querySelector('[data-f="role"]');
            var person = card.querySelector('[data-f="person"]');
            if (role) fd.append('role', role.value);
            if (person) fd.append('person_id', person.value);
            btn.disabled = true;
            fetch('/api/admin/access-requests/decide', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (res.ok && res.j.success) { location.reload(); return; }
                    note.textContent = (res.j && res.j.error) || 'Error';
                    btn.disabled = false;
                })
                .catch(function () { note.textContent = 'Error'; btn.disabled = false; });
        });
    });
})();
</script>
<?php require __DIR__ . '/../layout/master_end.php'; ?>
