# Шежіре Алтыбаевтар (altybayev.kz)

Жабық отбасылық шежіре сайты / Закрытый семейный сайт Шежіре рода Алтыбаевых.

---

## 📌 Архитектура и технологии

* **Хостинг:** PS.kz с панелью управления **Plesk**.
* **Деплой:** Автоматический / полуавтоматический pull из GitHub (ветка `main`).
* **Бэкенд:** PHP 8.2+ (FastCGI / PHP-FPM), MySQL 8 / MariaDB (utf8mb4).
* **Безопасность и доступ:**
  * Вход **только через Google-аккаунт** (OAuth 2.0).
  * Доступ **только по приглашениям** (Whitelist по email, управление через админ-панель).
  * Роли:
    * `admin` (Главный администратор — Жандос): управление инвайтами, ролями, полный аудит правок и откат любых изменений.
    * `editor` (Редакторы): добавление и редактирование персон, связей, фотографий.
    * `viewer` (Зрители / Родственники): просмотр древа, поиск, чтение карточек и историй.
* **Шежіре и родословная:**
  * Интерактивное масштабируемое древо (D3.js / SVG) с поддержкой мобильных устройств и жестов зума.
  * Режим **Жеті ата** (динамический расчет 7 колен отцовской линии от выбранной персоны).
  * Импорт из **MyHeritage (GEDCOM .ged)** с предварительным просмотром перед сохранением.
  * Полный журнал истории изменений с возможностью отката (Rollback).
  * Двуязычный интерфейс (**Қазақша / Русский**).

---

## 🚀 Инструкция по настройке на хостинге PS.kz (Plesk)

### 1. Настройка базы данных в Plesk
1. Откройте панель **Plesk** на PS.kz.
2. Перейдите в **Базы данных** (Databases) -> **Добавить базу данных**.
3. Имя базы: `altybayev_shezhire` (или любое удобное).
4. Создайте пользователя базы данных и надежный пароль.
5. Убедитесь, что кодировка базы — `utf8mb4`.

### 2. Подключение репозитория Git в Plesk
1. В Plesk в настройках домена `altybayev.kz` выберите **Git**.
2. Укажите репозиторий: `https://github.com/Zhanadmin/Altybayev.kz.git`.
3. Ветка: `main`.
4. Режим развертывания: **Автоматический** (Automatic) или **Вручную** (Manual).
5. Путь развертывания: `/httpdocs`.

### 3. Настройка файла окружения (.env)
В диспетчере файлов Plesk или по SSH скопируйте `.env.example` в `.env` и укажите ваши данные:
```bash
cp .env.example .env
```
Заполните параметры:
* `DB_NAME`, `DB_USER`, `DB_PASS` — реквизиты базы из шага 1.
* `GOOGLE_CLIENT_ID` и `GOOGLE_CLIENT_SECRET` — ключи авторизации Google.
* `SUPER_ADMIN_EMAIL=zhandos.alt06@gmail.com`.

### 4. Запуск миграций базы данных
В консоли/терминале Plesk или по SSH выполните:
```bash
php database/migrate.php
```
Или откройте в браузере: `https://altybayev.kz/install` (доступно только при первой инициализации).

---

## 🔑 Инструкция: Получение Google OAuth 2.0 Client ID

1. Перейдите в [Google Cloud Console](https://console.cloud.google.com/).
2. В верхнем меню выберите или создайте новый проект (например: `altybayev-shezhire`).
3. В боковом меню откройте **APIs & Services** -> **OAuth consent screen** (Окно согласия OAuth):
   * Выберите User Type: **External** (Внешний) и нажмите *Create*.
   * Укажите App name: `Шежіре Алтыбаевтар`.
   * User support email: `zhandos.alt06@gmail.com`.
   * Developer contact information: `zhandos.alt06@gmail.com`.
   * На шаге **Scopes** нажмите *Add or Remove Scopes* и выберите `userinfo.email` и `userinfo.profile`.
   * Нажмите *Save and Continue*.
   * В разделе **Publishing status** нажмите кнопку **Publish App** (Опубликовать), чтобы родственники могли входить без ограничений тестового режима.
4. В боковом меню перейдите в **Credentials** (Учетные данные):
   * Нажмите **+ CREATE CREDENTIALS** -> выберите **OAuth client ID**.
   * Application type: **Web application**.
   * Name: `Altybayev Shezhire Web`.
   * **Authorized JavaScript origins:** `https://altybayev.kz`
   * **Authorized redirect URIs:** `https://altybayev.kz/auth/google/callback`
   * Нажмите *Create*.
5. Скопируйте **Client ID** и **Client Secret** и вставьте их в файл `.env` на сервере.
