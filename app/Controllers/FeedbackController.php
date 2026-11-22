<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\FeedbackService;

/**
 * TASK-027: форма обратной связи на приветственной странице (публичная) и управление ею в админке.
 */
class FeedbackController
{
    /** POST /api/feedback — гость отправляет обращение. Защита: CSRF, honeypot, «ловушка времени», лимит по IP. */
    public function submit(Request $request): void
    {
        if (!Session::validateCsrf((string)$request->post('csrf_token', ''))) {
            Response::json(['success' => false, 'error' => 'Сессия устарела, обновите страницу / Сессия ескірді, бетті жаңартыңыз'], 419);
        }

        // honeypot: боты заполняют скрытое поле — делаем вид, что всё хорошо
        if (trim((string)$request->post('website', '')) !== '') {
            Response::json(['success' => true]);
        }
        // ловушка времени: форма отправлена быстрее 3 секунд после показа — бот
        $t = (int)Session::get('fb_form_ts', 0);
        if ($t > 0 && (time() - $t) < 3) {
            Response::json(['success' => false, 'error' => 'Слишком быстро, попробуйте ещё раз / Тым жылдам, қайталап көріңіз'], 429);
        }

        $name = trim(preg_replace('/\s+/u', ' ', (string)$request->post('name', '')) ?? '');
        $contact = trim((string)$request->post('contact', ''));
        $message = trim((string)$request->post('message', ''));

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            Response::json(['success' => false, 'error' => 'Укажите имя / Атыңызды жазыңыз'], 422);
        }
        $isEmail = filter_var($contact, FILTER_VALIDATE_EMAIL) !== false;
        $digits = strlen(FeedbackService::normalizeWhatsapp($contact));
        if (mb_strlen($contact) > 190 || (!$isEmail && ($digits < 7 || $digits > 15))) {
            Response::json(['success' => false, 'error' => 'Укажите телефон или e-mail / Телефон немесе e-mail жазыңыз'], 422);
        }
        if (mb_strlen($message) < 5 || mb_strlen($message) > 2000) {
            Response::json(['success' => false, 'error' => 'Сообщение: от 5 до 2000 символов / Хабарлама: 5–2000 таңба'], 422);
        }

        try {
            $db = Database::getConnection();
            $svc = new FeedbackService($db);
            $svc->ensureSchema();
            $ip = FeedbackService::ipHash();
            if (!$svc->underLimit($ip)) {
                Response::json(['success' => false, 'error' => 'Слишком много обращений, попробуйте позже / Тым көп өтініш, кейінірек көріңіз'], 429);
            }
            $id = $svc->save($name, $contact, $message, $ip);

            $contacts = FeedbackService::publicContacts($db);
            if ($contacts['email'] !== '') {
                $body = "Новое обращение с сайта (приветственная страница)\n\nИмя: {$name}\nКонтакт: {$contact}\n\n{$message}\n";
                if ($svc->sendMail($contacts['email'], 'Шежіре: обращение от ' . $name, $body, $isEmail ? $contact : '')) {
                    $svc->markMailSent($id);
                }
            }
        } catch (\Throwable $e) {
            Logger::error('feedback submit: ' . $e->getMessage());
            Response::json(['success' => false, 'error' => 'Не удалось отправить, попробуйте позже / Жіберу мүмкін болмады'], 500);
        }

        Response::json(['success' => true]);
    }

    /** POST /api/admin/contacts/save — контакты владельца (имя, e-mail, WhatsApp). */
    public function saveContacts(Request $request): void
    {
        $this->guardAdmin($request);
        $name = mb_substr(trim((string)$request->post('name', '')), 0, 120);
        $email = trim((string)$request->post('email', ''));
        $wa = FeedbackService::normalizeWhatsapp((string)$request->post('whatsapp', ''));

        if ($email !== '' && (mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            Response::json(['error' => 'Некорректный e-mail / E-mail қате'], 422);
        }
        if ($wa !== '' && (strlen($wa) < 7 || strlen($wa) > 15)) {
            Response::json(['error' => 'WhatsApp: номер в международном формате, 7–15 цифр'], 422);
        }
        try {
            (new FeedbackService(Database::getConnection()))->saveContacts(['name' => $name, 'email' => $email, 'whatsapp' => $wa]);
        } catch (\Throwable $e) {
            Logger::error('contacts save: ' . $e->getMessage());
            Response::json(['error' => 'Ошибка сохранения'], 500);
        }
        Response::json(['success' => true]);
    }

    /** POST /api/admin/feedback/read — id (0 = все), read = 1|0. */
    public function markRead(Request $request): void
    {
        $this->guardAdmin($request);
        $id = max(0, (int)$request->post('id', 0));
        $read = (string)$request->post('read', '1') !== '0';
        try {
            (new FeedbackService(Database::getConnection()))->markRead($id, $read);
        } catch (\Throwable $e) {
            Response::json(['error' => 'Ошибка'], 500);
        }
        Response::json(['success' => true]);
    }

    private function guardAdmin(Request $request): void
    {
        if (!Auth::isAdmin()) {
            Response::json(['error' => 'Тек әкімшіге рұқсат / Доступно только администратору'], 403);
        }
        if (!Session::validateCsrf((string)$request->post('csrf_token', ''))) {
            Response::json(['error' => 'Сессия ескірді, бетті жаңартыңыз / Сессия устарела, обновите страницу'], 419);
        }
    }
}
