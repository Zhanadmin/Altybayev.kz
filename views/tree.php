<?php
declare(strict_types=1);
/**
 * @var array|null $user
 * @var int $userPersonId
 * @var array $kinshipTerms
 */
$userName = $user['name'] ?? 'Жандос Алтыбаев';
$userEmail = $user['email'] ?? 'zhandos.alt06@gmail.com';
?>
<!DOCTYPE html>
<html lang="kk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Шежіре Алтыбаевтар — Интерактивті Древо (MyHeritage және Орман)</title>
    <style>
        :root {
            --bg-canvas: #0b1320;
            --bg-card: #ffffff;
            --text-dark: #1e293b;
            --text-light: #f8fafc;
            --primary: #1e3a8a;
            --primary-accent: #3b82f6;
            --gold: #d97706;
            --gold-glow: rgba(245, 158, 11, 0.4);
            --male-color: #2563eb;
            --female-color: #ec4899;
            --forest-green: #15803d;
            --border-color: #cbd5e1;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            user-select: none;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--bg-canvas);
            color: var(--text-light);
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Верхняя панель управления */
        header.tree-nav {
            background: linear-gradient(90deg, #0d1b2a, #1b263b);
            border-bottom: 2px solid #d4af37;
            padding: 10px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            z-index: 100;
            box-shadow: 0 4px 15px rgba(0,0,0,0.4);
            flex-shrink: 0;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .back-link {
            color: #94a3b8;
            text-decoration: none;
            font-size: 13px;
            padding: 6px 10px;
            border-radius: 6px;
            background: rgba(255,255,255,0.06);
            transition: all 0.2s;
        }
        .back-link:hover {
            color: #fff;
            background: rgba(255,255,255,0.15);
        }

        .brand-title {
            font-size: 17px;
            font-weight: 700;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .brand-title span {
            color: #d4af37;
        }

        /* Переключатель режимов */
        .mode-switcher {
            display: flex;
            background: rgba(0, 0, 0, 0.4);
            padding: 3px;
            border-radius: 30px;
            border: 1px solid rgba(212, 175, 55, 0.4);
        }

        .mode-btn {
            padding: 7px 16px;
            border-radius: 25px;
            border: none;
            background: transparent;
            color: #cbd5e1;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .mode-btn.active {
            background: linear-gradient(135deg, #d4af37, #b45309);
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(212, 175, 55, 0.5);
        }

        /* Поиск и быстрые действия */
        .nav-right {
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
        }

        .search-box {
            position: relative;
            width: 250px;
        }

        .search-input {
            width: 100%;
            padding: 7px 12px 7px 32px;
            border-radius: 20px;
            border: 1px solid #334155;
            background: rgba(15, 23, 42, 0.8);
            color: #fff;
            font-size: 13px;
            outline: none;
        }
        .search-input:focus {
            border-color: #d4af37;
            box-shadow: 0 0 10px rgba(212, 175, 55, 0.3);
        }
        .search-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 12px;
            opacity: 0.6;
        }

        .search-results {
            position: absolute;
            top: 40px;
            left: 0;
            width: 320px;
            background: #1e293b;
            border: 1px solid #475569;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            max-height: 350px;
            overflow-y: auto;
            display: none;
            z-index: 200;
        }
        .search-item {
            padding: 8px 12px;
            border-bottom: 1px solid #334155;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
        }
        .search-item:hover {
            background: #334155;
        }
        .search-item .item-meta {
            font-size: 11px;
            color: #94a3b8;
        }

        .btn-action {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            color: #fff;
            padding: 7px 14px;
            border-radius: 20px;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-action:hover {
            background: rgba(255,255,255,0.2);
            border-color: #d4af37;
        }
        .btn-self {
            background: linear-gradient(135deg, #1e3a8a, #0284c7);
            border-color: #38bdf8;
            font-weight: 600;
        }
        .btn-self:hover {
            box-shadow: 0 0 12px rgba(56, 189, 248, 0.5);
        }

        /* Главная рабочая область — Canvas / Viewport */
        .viewport-container {
            flex: 1;
            position: relative;
            overflow: hidden;
            background: radial-gradient(circle at 50% 30%, #172554 0%, #0b1320 65%, #050811 100%);
            cursor: grab;
        }
        .viewport-container:active {
            cursor: grabbing;
        }

        .tree-world {
            position: absolute;
            top: 0;
            left: 0;
            width: 4500px;
            height: 3800px;
            transform-origin: 0 0;
            transition: transform 0.05s linear;
        }

        /* ------------------------------------------------------------- */
        /* РЕЖИМ 1: MYHERITAGE РАБОЧИЙ ВИД                              */
        /* ------------------------------------------------------------- */
        .myheritage-view {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: block;
        }

        /* Карточка человека MyHeritage */
        .mh-card {
            position: absolute;
            width: 250px;
            background: #ffffff;
            border-radius: 10px;
            color: #1e293b;
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
            border: 2px solid #e2e8f0;
            padding: 12px;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
            z-index: 10;
        }
        .mh-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 30px rgba(0,0,0,0.5);
            border-color: #3b82f6;
            z-index: 25;
        }
        .mh-card.male {
            border-top: 5px solid #2563eb;
        }
        .mh-card.female {
            border-top: 5px solid #ec4899;
        }
        .mh-card.is-self {
            border: 3px solid #f59e0b;
            box-shadow: 0 0 25px rgba(245, 158, 11, 0.6);
        }
        .mh-card.selected {
            border-color: #f59e0b;
            box-shadow: 0 0 20px rgba(245, 158, 11, 0.5);
            z-index: 30;
        }
        .mh-card.highlight-kin {
            border-color: #10b981;
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.7);
            z-index: 20;
        }

        .mh-card-header {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 8px;
        }
        .mh-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            color: #475569;
            border: 2px solid #cbd5e1;
            flex-shrink: 0;
            position: relative;
        }
        .mh-card.male .mh-avatar {
            border-color: #93c5fd;
            background: #eff6ff;
            color: #1d4ed8;
        }
        .mh-card.female .mh-avatar {
            border-color: #fbcfe8;
            background: #fdf2f8;
            color: #be185d;
        }

        .mh-card-info {
            flex: 1;
            overflow: hidden;
        }
        .mh-name {
            font-size: 14px;
            font-weight: 700;
            line-height: 1.2;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .mh-maiden {
            font-size: 11px;
            color: #be185d;
            font-style: italic;
        }
        .mh-dates {
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
        }
        .mh-place {
            font-size: 11px;
            color: #475569;
            margin-top: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .mh-badge-clan {
            display: inline-block;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 10px;
            background: #f1f5f9;
            color: #334155;
            margin-top: 6px;
        }

        .mh-kin-badge {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 12px;
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
            margin-top: 4px;
        }

        /* Радиальные кнопки добавления (MyHeritage Style вокруг выбранной карточки) */
        .radial-action-wrap {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            display: none;
        }
        .mh-card.selected .radial-action-wrap {
            display: block;
        }

        .radial-btn {
            position: absolute;
            pointer-events: auto;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #2563eb;
            color: #ffffff;
            border: 2px solid #ffffff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s, background 0.2s;
            z-index: 40;
        }
        .radial-btn:hover {
            transform: scale(1.2);
            background: #1d4ed8;
        }
        .radial-btn::after {
            content: attr(data-tooltip);
            position: absolute;
            background: #0f172a;
            color: #fff;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            white-space: nowrap;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s;
            z-index: 50;
        }
        .radial-btn:hover::after {
            opacity: 1;
        }

        /* Позиционирование радиальных кнопок вокруг карточки */
        .radial-top-father { top: -16px; left: 35%; background: #2563eb; }
        .radial-top-father::after { bottom: 35px; left: 50%; transform: translateX(-50%); }

        .radial-top-mother { top: -16px; right: 35%; background: #ec4899; }
        .radial-top-mother::after { bottom: 35px; left: 50%; transform: translateX(-50%); }

        .radial-left-spouse { top: 40%; left: -16px; background: #8b5cf6; }
        .radial-left-spouse::after { right: 35px; top: 50%; transform: translateY(-50%); }

        .radial-right-sibling { top: 40%; right: -16px; background: #10b981; }
        .radial-right-sibling::after { left: 35px; top: 50%; transform: translateY(-50%); }

        .radial-bottom-son { bottom: -16px; left: 35%; background: #2563eb; }
        .radial-bottom-son::after { top: 35px; left: 50%; transform: translateX(-50%); }

        .radial-bottom-daughter { bottom: -16px; right: 35%; background: #ec4899; }
        .radial-bottom-daughter::after { top: 35px; left: 50%; transform: translateX(-50%); }

        /* Линии связей (SVG) */
        .svg-connections {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 5;
        }

        /* ------------------------------------------------------------- */
        /* РЕЖИМ 2: ШЕЖІРЕ-ОРМАН («ЛЕС» В ВИДЕ ЖИВЫХ ДЕРЕВЬЕВ)           */
        /* ------------------------------------------------------------- */
        .forest-view {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: none;
        }

        /* Контейнер дерева в лесу */
        .tree-stand {
            position: absolute;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Главное дерево Керей -> Алтыбаев */
        .stand-kerey {
            left: 1700px;
            top: 400px;
            width: 1100px;
        }

        /* Соседнее дерево: Жағалбайлы */
        .stand-zhagalbayly {
            left: 600px;
            top: 800px;
            width: 800px;
            opacity: 0.85;
        }

        /* Соседнее дерево: Қоңырат */
        .stand-konyrat {
            left: 3000px;
            top: 800px;
            width: 800px;
            opacity: 0.85;
        }

        /* Ствол и корень */
        .tree-trunk-layer {
            position: relative;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Крона (живые наверху) */
        .tree-crown {
            background: radial-gradient(circle, rgba(34, 197, 94, 0.25) 0%, rgba(21, 128, 61, 0.08) 60%, transparent 100%);
            border-radius: 50%;
            padding: 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 30px;
            margin-bottom: 20px;
            position: relative;
        }

        .crown-badge {
            background: linear-gradient(135deg, #22c55e, #15803d);
            color: #fff;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(34, 197, 94, 0.4);
            margin-bottom: 10px;
        }

        .crown-level {
            display: flex;
            gap: 25px;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Ветка (Мужчины) */
        .forest-branch-node {
            background: #1e293b;
            border: 2px solid #22c55e;
            border-radius: 25px;
            padding: 8px 16px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(0,0,0,0.5);
            transition: all 0.25s;
            position: relative;
        }
        .forest-branch-node:hover {
            transform: scale(1.08);
            border-color: #4ade80;
            box-shadow: 0 0 20px rgba(74, 222, 128, 0.6);
            z-index: 50;
        }
        .forest-branch-node.self-node {
            border: 3px solid #f59e0b;
            background: linear-gradient(135deg, #1e3a8a, #0369a1);
            box-shadow: 0 0 25px rgba(245, 158, 11, 0.8);
            animation: pulse-gold 2s infinite;
        }

        @keyframes pulse-gold {
            0% { box-shadow: 0 0 15px rgba(245, 158, 11, 0.5); }
            50% { box-shadow: 0 0 30px rgba(245, 158, 11, 0.9); }
            100% { box-shadow: 0 0 15px rgba(245, 158, 11, 0.5); }
        }

        /* Цветочек (Девочки / Женщины) */
        .forest-flower-node {
            background: #fdf2f8;
            border: 2px solid #ec4899;
            border-radius: 25px;
            padding: 8px 16px;
            color: #9d174d;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(236, 72, 153, 0.3);
            transition: all 0.25s;
            position: relative;
        }
        .forest-flower-node:hover {
            transform: scale(1.08);
            box-shadow: 0 0 20px rgba(236, 72, 153, 0.7);
            z-index: 50;
        }
        .flower-icon {
            font-size: 16px;
            animation: rotate-flower 10s infinite linear;
        }
        @keyframes rotate-flower {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* След ушедшей в другой род дочери (Ghost Trace) */
        .ghost-trace {
            background: rgba(253, 242, 248, 0.35);
            border: 2px dashed #f472b6;
            color: #cbd5e1;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .ghost-trace:hover {
            background: rgba(253, 242, 248, 0.7);
            color: #831843;
            transform: scale(1.05);
        }

        /* Высокий вертикальный ствол (Single tall trunk) */
        .tall-trunk {
            width: 28px;
            height: 600px;
            background: linear-gradient(180deg, #78350f, #451a03);
            border-radius: 14px;
            position: relative;
            box-shadow: inset 0 0 10px rgba(0,0,0,0.6);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-around;
        }

        .trunk-ancestor {
            background: #271406;
            border: 2px solid #b45309;
            color: #fef3c7;
            padding: 6px 14px;
            border-radius: 18px;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
            box-shadow: 0 4px 12px rgba(0,0,0,0.7);
            cursor: pointer;
            transition: transform 0.2s;
        }
        .trunk-ancestor:hover {
            transform: scale(1.1);
            border-color: #fbbf24;
        }

        /* Корень (Керей в самом низу) */
        .tree-root {
            margin-top: 20px;
            background: radial-gradient(circle, #3e1f07 0%, #1c0e03 70%, transparent 100%);
            padding: 30px 60px;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }
        .root-ancestor-card {
            background: linear-gradient(135deg, #78350f, #271406);
            border: 3px solid #d4af37;
            padding: 12px 24px;
            border-radius: 30px;
            color: #fef08a;
            font-size: 17px;
            font-weight: 800;
            text-align: center;
            box-shadow: 0 0 30px rgba(212, 175, 55, 0.5);
            cursor: pointer;
        }
        .root-label {
            font-size: 11px;
            color: #cbd5e1;
            margin-top: 4px;
        }

        /* Боковая панель профиля (Profile Side Panel) */
        .side-profile-panel {
            position: fixed;
            top: 60px;
            right: -420px;
            width: 400px;
            height: calc(100vh - 60px);
            background: #0f172a;
            border-left: 2px solid #334155;
            box-shadow: -10px 0 30px rgba(0,0,0,0.6);
            z-index: 150;
            display: flex;
            flex-direction: column;
            transition: right 0.3s ease;
            overflow-y: auto;
        }
        .side-profile-panel.open {
            right: 0;
        }

        .side-header {
            padding: 20px;
            border-bottom: 1px solid #1e293b;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            background: #1e293b;
        }
        .close-panel-btn {
            background: transparent;
            border: none;
            color: #94a3b8;
            font-size: 20px;
            cursor: pointer;
        }
        .close-panel-btn:hover {
            color: #fff;
        }

        .side-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .kin-callout {
            background: linear-gradient(135deg, #1e3a8a, #065f46);
            border: 1px solid #10b981;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 13px;
            color: #e2e8f0;
        }
        .kin-callout strong {
            color: #fef08a;
            font-size: 15px;
        }

        .profile-kv {
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 13px;
        }
        .profile-kv-row {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid #1e293b;
            padding-bottom: 6px;
        }
        .profile-kv-label {
            color: #94a3b8;
        }
        .profile-kv-val {
            color: #f8fafc;
            font-weight: 600;
        }

        .bio-box {
            background: #1e293b;
            border-radius: 8px;
            padding: 12px;
            font-size: 13px;
            line-height: 1.5;
            color: #cbd5e1;
        }

        /* Модальное окно быстрого добавления родственника */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 300;
        }
        .modal-overlay.active {
            display: flex;
        }

        .modal-card {
            background: #1e293b;
            border: 2px solid #d4af37;
            border-radius: 14px;
            width: 520px;
            max-width: 90vw;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.8);
            color: #fff;
            padding: 24px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            border-bottom: 1px solid #334155;
            padding-bottom: 10px;
        }
        .modal-header h3 {
            font-size: 18px;
            color: #fef08a;
        }

        .form-group {
            margin-bottom: 14px;
        }
        .form-group label {
            display: block;
            font-size: 12px;
            color: #94a3b8;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .form-control {
            width: 100%;
            padding: 9px 12px;
            border-radius: 8px;
            border: 1px solid #475569;
            background: #0f172a;
            color: #fff;
            font-size: 13px;
            outline: none;
        }
        .form-control:focus {
            border-color: #d4af37;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .modal-footer {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn-cancel {
            background: #334155;
            color: #fff;
            border: none;
            padding: 9px 18px;
            border-radius: 8px;
            cursor: pointer;
        }
        .btn-save {
            background: linear-gradient(135deg, #d4af37, #b45309);
            color: #fff;
            border: none;
            padding: 9px 20px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
        }

        /* Информационная подсказка внизу экрана */
        .bottom-hint {
            position: fixed;
            bottom: 14px;
            left: 20px;
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(8px);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 90;
        }
        .bottom-hint span {
            color: #38bdf8;
            font-weight: 600;
        }

        /* Словарь терминов родства Modal */
        .dict-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-top: 10px;
        }
        .dict-table th, .dict-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #334155;
            text-align: left;
        }
        .dict-table th {
            color: #94a3b8;
            background: #0f172a;
        }
    </style>
</head>
<body>

    <!-- ВЕРХНЕЕ МЕНЮ И УПРАВЛЕНИЕ -->
    <header class="tree-nav">
        <div class="nav-left">
            <a href="/" class="back-link">← Басты бет / На главную</a>
            <div class="brand-title">
                🌳 Шежіре <span>Алтыбаевтар</span>
            </div>
            <!-- Переключатель двух видов -->
            <div class="mode-switcher">
                <button class="mode-btn active" id="btnModeMyHeritage" onclick="switchMode('myheritage')">
                    🏢 MyHeritage (Жұмыс түрі)
                </button>
                <button class="mode-btn" id="btnModeForest" onclick="switchMode('forest')">
                    🌲 Шежіре-Орман («Лес»)
                </button>
            </div>
        </div>

        <div class="nav-right">
            <!-- Поиск с подсказками -->
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" class="search-input" id="treeSearchInput" placeholder="Адамды немесе руды іздеу..." autocomplete="off">
                <div class="search-results" id="searchResultsBox"></div>
            </div>

            <!-- Кнопка «Сіз осындасыз» -->
            <button class="btn-action btn-self" onclick="flyToSelf()" title="Сіздің карточкаңызға өту">
                🎯 Сіз осындасыз (Жандос)
            </button>

            <!-- Кнопка словаря терминов -->
            <button class="btn-action" onclick="openDictionaryModal()">
                📖 Қазақтың туыстық атаулары
            </button>

            <?php if (($user['role'] ?? '') === 'admin'): ?>
            <!-- Быстрый переход в импорт GEDCOM для админа -->
            <a href="/admin/import" class="btn-action" style="background:rgba(212,175,55,0.2); border:1px solid #d4af37; color:#fef08a; text-decoration:none;" title="GEDCOM импорты және предпросмотр">
                📥 GEDCOM импорты
            </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- ОСНОВНАЯ ОБЛАСТЬ ПРОСМОТРА И ПЕРЕМЕЩЕНИЯ (VIEWPORT) -->
    <div class="viewport-container" id="viewportContainer">
        <div class="tree-world" id="treeWorld">
            
            <!-- SVG СЛОЙ ДЛЯ ЛИНИЙ И ПЕРЕЛЕТА ДЕВОЧЕК -->
            <svg class="svg-connections" id="svgConnections" width="4500" height="3800"></svg>

            <!-- 1. РЕЖИМ MYHERITAGE -->
            <div class="myheritage-view" id="myheritageView">
                <!-- Карточки людей рендерятся скриптом -->
            </div>

            <!-- 2. РЕЖИМ ШЕЖІРЕ-ОРМАН -->
            <div class="forest-view" id="forestView">
                
                <!-- СОСЕДНЕЕ ДЕРЕВО: ЖАҒАЛБАЙЛЫ (КІШІ ЖҮЗ) -->
                <div class="tree-stand stand-zhagalbayly" id="standZhagalbayly">
                    <div class="crown-badge" style="background:#8b2635;">🌳 Жағалбайлы ағашы (Кіші жүз)</div>
                    <div style="font-size:12px; color:#cbd5e1; max-width:300px; text-align:center; margin-bottom:15px;">
                        Алтыбаевтар әулетіне келін болып келген асыл аналарымыздың туған руы
                    </div>
                    <div class="forest-flower-node" onclick="selectPerson(5)">
                        <span class="flower-icon">🌸</span>
                        <span>Бибінұр Сейітқызы (1904)</span>
                    </div>
                    <div style="height:15px;"></div>
                    <div class="forest-flower-node" onclick="selectPerson(8)">
                        <span class="flower-icon">🌸</span>
                        <span>Әлия Нұрланқызы (1987)</span>
                    </div>
                </div>

                <!-- ГЛАВНОЕ ДЕРЕВО: КЕРЕЙ -> АЛТЫБАЕВТАР -->
                <div class="tree-stand stand-kerey" id="standKerey">
                    
                    <!-- КРОНА (ЖИВЫЕ НАВЕРХУ) -->
                    <div class="tree-crown" id="treeCrown">
                        <div class="crown-badge">🍃 Крона — Жас ұрпақтар мен Тірілер (Живые)</div>
                        
                        <!-- Самые младшие (дети) -->
                        <div class="crown-level">
                            <div class="forest-branch-node" onclick="selectPerson(10)">
                                <span>🌿</span>
                                <div><strong>Арнұр Алтыбаев</strong> (2014)</div>
                            </div>
                            <div class="forest-flower-node" onclick="selectPerson(11)">
                                <span class="flower-icon">🌸</span>
                                <div><strong>Медина Алтыбаева</strong> (2019)</div>
                            </div>
                        </div>

                        <!-- Поколение Жандоса -->
                        <div class="crown-level">
                            <div class="forest-branch-node self-node" onclick="selectPerson(7)">
                                <span>🎯</span>
                                <div>
                                    <strong>Жандос Алтыбаев</strong> (1985)
                                    <div style="font-size:11px; color:#fef08a;">★ Сіз осындасыз</div>
                                </div>
                            </div>
                            <div class="forest-flower-node" onclick="selectPerson(8)" title="Жағалбайлы руынан келген келін">
                                <span class="flower-icon">🌸</span>
                                <div>
                                    <strong>Әлия</strong> (1987)
                                    <div style="font-size:10px; color:#be185d;">Жағалбайлы қызы ↗</div>
                                </div>
                            </div>
                            <!-- Ушедшая в род Конырат дочь Айнұр -->
                            <div class="ghost-trace" onclick="flyToKonyrat()" title="Қоңыратқа келін болып кетті (клик — көшу)">
                                <span>🕊️</span>
                                <div>
                                    <strong>Айнұр Алтыбаева</strong>
                                    <div style="font-size:10px; color:#f472b6;">Қоңыратқа келін болып кетті ➔</div>
                                </div>
                            </div>
                        </div>

                        <!-- Поколение отца (главный редактор шежіре) -->
                        <div class="crown-level">
                            <div class="forest-branch-node" onclick="selectPerson(6)">
                                <span>📜</span>
                                <div>
                                    <strong>Қасым Сейітқалиұлы</strong> (1955)
                                    <div style="font-size:11px; color:#86efac;">Бас редактор (Әкесі)</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ВЫСОКИЙ СТВОЛ (ОТ КЕРЕЯ ДО АЛТЫБАЯ — ОДНА ПРЯМАЯ ЛИНИЯ) -->
                    <div class="tree-trunk-layer">
                        <div style="font-size:12px; color:#fef3c7; font-weight:700; margin-bottom:10px;">
                            🏛️ Тікелей негізгі діңгек (Түп аталар желісі)
                        </div>
                        <div class="tall-trunk">
                            <div class="trunk-ancestor" onclick="selectPerson(4)">
                                Сейітқали Алтыбайұлы (1898 — 1972)
                            </div>
                            <div class="trunk-ancestor" onclick="selectPerson(3)" style="border-color:#f59e0b; color:#fef08a;">
                                👑 Алтыбай Баба (ок. 1795) — Ру басы
                            </div>
                            <div class="trunk-ancestor" onclick="selectPerson(2)">
                                Самай Би (ок. 1620)
                            </div>
                        </div>
                    </div>

                    <!-- КОРЕНЬ (КЕРЕЙ В САМОМ НИЗУ В ЗЕМЛЕ) -->
                    <div class="tree-root" id="treeRoot">
                        <div class="root-ancestor-card" onclick="selectPerson(1)">
                            🌱 КЕРЕЙ БАБА (ок. 1380)
                            <div class="root-label">Шежіренің ең түпкі қасиетті тамыры (Корень внизу)</div>
                        </div>
                    </div>

                </div>

                <!-- СОСЕДНЕЕ ДЕРЕВО: ҚОҢЫРАТ (ОРТА ЖҮЗ) -->
                <div class="tree-stand stand-konyrat" id="standKonyrat">
                    <div class="crown-badge" style="background:#2b580c;">🌳 Қоңырат ағашы (Орта жүз)</div>
                    <div style="font-size:12px; color:#cbd5e1; max-width:300px; text-align:center; margin-bottom:15px;">
                        Алтыбаевтардың қызы Айнұр келін болып түскен құдалар ағашы
                    </div>
                    <div style="display:flex; gap:12px; align-items:center;">
                        <div class="forest-branch-node" onclick="selectPerson(12)">
                            <span>🌿</span>
                            <div>Дәулет Төлегенов (1987)</div>
                        </div>
                        <div class="forest-flower-node" onclick="selectPerson(9)">
                            <span class="flower-icon">🌸</span>
                            <div>
                                <strong>Айнұр</strong> (Керей қызы)
                                <div style="font-size:10px; color:#be185d;">← Керейден келген келін</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <!-- БОКОВАЯ ПАНЕЛЬ ПРОФИЛЯ ПЕРСОНЫ -->
    <aside class="side-profile-panel" id="sideProfilePanel">
        <div class="side-header">
            <div>
                <h3 id="panelPersonName" style="color:#fef08a; font-size:18px;">—</h3>
                <div id="panelPersonClan" style="font-size:12px; color:#94a3b8; margin-top:2px;">—</div>
            </div>
            <button class="close-panel-btn" onclick="closeSidePanel()">✕</button>
        </div>
        <div class="side-body">
            <!-- Блок кем приходится вошедшему Жандосу -->
            <div class="kin-callout" id="panelKinshipCallout">
                Жүктелуде...
            </div>

            <!-- Данные о персоне -->
            <div class="profile-kv">
                <div class="profile-kv-row">
                    <span class="profile-kv-label">Жынысы / Пол:</span>
                    <span class="profile-kv-val" id="panelGender">—</span>
                </div>
                <div class="profile-kv-row">
                    <span class="profile-kv-label">Өмір сүрген жылдары:</span>
                    <span class="profile-kv-val" id="panelYears">—</span>
                </div>
                <div class="profile-kv-row">
                    <span class="profile-kv-label">Туған жері / Место:</span>
                    <span class="profile-kv-val" id="panelPlace">—</span>
                </div>
                <div class="profile-kv-row">
                    <span class="profile-kv-label">Күйі / Статус:</span>
                    <span class="profile-kv-val" id="panelStatus">—</span>
                </div>
            </div>

            <!-- Биография и заметки -->
            <div>
                <div style="font-size:12px; color:#94a3b8; margin-bottom:6px; font-weight:700;">ӨМІРБАЯНЫ МЕН ТАРИХЫ:</div>
                <div class="bio-box" id="panelBiography">—</div>
            </div>

            <!-- Кнопки действий -->
            <div style="display:flex; gap:10px; margin-top:10px;">
                <button class="btn-action" style="flex:1; justify-content:center; background:#2563eb;" onclick="openAddModal('child')">
                    + Бала қосу
                </button>
                <button class="btn-action" style="flex:1; justify-content:center; background:#8b5cf6;" onclick="openAddModal('spouse')">
                    + Жұбай қосу
                </button>
            </div>
        </div>
    </aside>

    <!-- МОДАЛЬНОЕ ОКНО ДОБАВЛЕНИЯ ЧЕЛОВЕКА (КАК В MYHERITAGE) -->
    <div class="modal-overlay" id="addPersonModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="modalAddTitle">Адам қосу / Добавить родственника</h3>
                <button class="close-panel-btn" onclick="closeAddModal()">✕</button>
            </div>
            <form id="addPersonForm" onsubmit="handleSavePerson(event)">
                <input type="hidden" id="formRelationType" value="child">
                <input type="hidden" id="formBasePersonId" value="0">

                <div class="form-row">
                    <div class="form-group">
                        <label>Аты / Имя *</label>
                        <input type="text" class="form-control" id="formFirstName" required placeholder="Мысалы: Бауыржан">
                    </div>
                    <div class="form-group">
                        <label>Тегі / Фамилия</label>
                        <input type="text" class="form-control" id="formLastName" placeholder="Мысалы: Алтыбаев">
                    </div>
                </div>

                <div class="form-group" id="maidenNameGroup">
                    <label>Қыз кезіндегі тегі / Девичья фамилия (әйел адам үшін)</label>
                    <input type="text" class="form-control" id="formMaidenName" placeholder="Мысалы: Сейітқызы">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Жынысы / Пол *</label>
                        <select class="form-control" id="formGender">
                            <option value="M">Ер адам (Мужской)</option>
                            <option value="F">Әйел адам (Женский)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Күйі / Статус *</label>
                        <select class="form-control" id="formIsDeceased" onchange="toggleDeathInputs()">
                            <option value="0">Тірі (Жив)</option>
                            <option value="1">Дүниеден өткен (Ушел из жизни)</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Туған жылы / Год рождения</label>
                        <input type="text" class="form-control" id="formBirthYear" placeholder="Мысалы: 1985 немесе ок. 1890">
                    </div>
                    <div class="form-group" id="deathYearGroup" style="display:none;">
                        <label>Қайтыс болған жылы</label>
                        <input type="text" class="form-control" id="formDeathYear" placeholder="Мысалы: 1972">
                    </div>
                </div>

                <div class="form-group">
                    <label>Туған жері / Место рождения</label>
                    <input type="text" class="form-control" id="formBirthPlace" placeholder="Мысалы: ШҚО, Зайсан ауданы">
                </div>

                <div class="form-group">
                    <label>Руы / Род</label>
                    <input type="text" class="form-control" id="formClanRu" placeholder="Мысалы: Орта жүз, Керей">
                </div>

                <div class="form-group">
                    <label>Өмірбаяны мен естеліктер / Заметки</label>
                    <textarea class="form-control" id="formBiography" rows="3" placeholder="Қысқаша естелік немесе тарихи деректер..."></textarea>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeAddModal()">Бас тарту / Отмена</button>
                    <button type="submit" class="btn-save">Сақтау / Сохранить</button>
                </div>
            </form>
        </div>
    </div>

    <!-- МОДАЛЬНОЕ ОКНО СЛОВАРЯ КАЗАХСКИХ ТЕРМИНОВ РОДСТВА -->
    <div class="modal-overlay" id="dictModal">
        <div class="modal-card" style="width: 780px;">
            <div class="modal-header">
                <h3>Қазақтың дәстүрлі туыстық атауларының сөздігі</h3>
                <button class="close-panel-btn" onclick="closeDictionaryModal()">✕</button>
            </div>
            <div style="font-size:13px; color:#cbd5e1; margin-bottom:12px;">
                💡 Жандос пен әкесіне арналған анықтамалық. Говорящийдің жынысы мен жас айырмашылығы ескеріледі:
            </div>
            <div style="max-height: 480px; overflow-y: auto;">
                <table class="dict-table">
                    <thead>
                        <tr>
                            <th>Қазақша атауы</th>
                            <th>Орысша аудармасы</th>
                            <th>Түсіндірмесі мен дәстүрі</th>
                            <th>Буын / Желісі</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kinshipTerms as $term): ?>
                        <tr>
                            <td style="font-weight:700; color:#fef08a;"><?= htmlspecialchars($term['kk']) ?></td>
                            <td><?= htmlspecialchars($term['ru']) ?></td>
                            <td style="color:#94a3b8; font-size:12px;"><?= htmlspecialchars($term['desc']) ?></td>
                            <td><span style="font-size:11px; padding:2px 6px; border-radius:4px; background:#334155;"><?= htmlspecialchars($term['line']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeDictionaryModal()">Жабу</button>
            </div>
        </div>
    </div>

    <!-- ПОДСКАЗКА ВНИЗУ ЭКРАНА -->
    <div class="bottom-hint" id="bottomHint">
        <span>💡 Навигация:</span> Тінтуірмен сүйреп жылжыту, дөңгелегімен үлкейту/кішірейту (Zoom/Pan). Адамды басқанда айналасында қосу түймелері мен туыстық деңгейі шығады.
    </div>

    <script>
        // Инициализация глобального состояния приложения
        let currentMode = 'myheritage'; // 'myheritage' | 'forest'
        let treeData = null;
        let selectedPersonId = 7; // По умолчанию выбран Жандос
        const userPersonId = <?= (int)$userPersonId ?>;

        // Переменные зума и панорамирования
        let scale = 1.0;
        let posX = -1350;
        let posY = -200;
        let isDragging = false;
        let startX, startY;

        const viewport = document.getElementById('viewportContainer');
        const world = document.getElementById('treeWorld');

        // Загрузка данных древа
        async function loadTreeData() {
            try {
                const res = await fetch('/api/tree');
                const data = await res.json();
                treeData = data;
                renderMyHeritageView();
                // Автоматически центрируемся на Жандосе
                flyToPerson(userPersonId);
            } catch (err) {
                console.error("Деректерді жүктеу қатесі:", err);
            }
        }

        // Переключение режимов (MyHeritage <-> Лес)
        function switchMode(mode) {
            currentMode = mode;
            const btnMh = document.getElementById('btnModeMyHeritage');
            const btnForest = document.getElementById('btnModeForest');
            const viewMh = document.getElementById('myheritageView');
            const viewForest = document.getElementById('forestView');

            if (mode === 'myheritage') {
                btnMh.classList.add('active');
                btnForest.classList.remove('active');
                viewMh.style.display = 'block';
                viewForest.style.display = 'none';
                renderConnectionsMyHeritage();
                flyToPerson(selectedPersonId || userPersonId);
            } else {
                btnForest.classList.add('active');
                btnMh.classList.remove('active');
                viewMh.style.display = 'none';
                viewForest.style.display = 'block';
                // В режиме «Лес» ориентируемся на крону (живые сверху)
                flyToCrown();
            }
        }

        // Рендеринг режима MyHeritage (карточки и радиальные кнопки)
        function renderMyHeritageView() {
            const container = document.getElementById('myheritageView');
            container.innerHTML = '';

            if (!treeData || !treeData.persons) return;

            // Расставляем персоны по поколениям
            // Позиции карточек в пространстве
            const layoutMap = {
                1: { x: 2000, y: 1600 }, // Керей баба (корень)
                2: { x: 2000, y: 1300 }, // Самай би
                3: { x: 2000, y: 1000 }, // Алтыбай баба
                4: { x: 1800, y: 700 },  // Сейітқали
                5: { x: 2100, y: 700 },  // Бибінұр (жұбайы)
                6: { x: 1950, y: 440 },  // Қасым (әкесі)
                7: { x: 1850, y: 180 },  // Жандос (Сіз осындасыз)
                8: { x: 2150, y: 180 },  // Әлия (жұбайы)
                9: { x: 2500, y: 180 },  // Айнұр (қарындасы)
                10: { x: 1750, y: -80 }, // Арнұр (ұлы)
                11: { x: 2050, y: -80 }, // Медина (қызы)
                12: { x: 2800, y: 180 }, // Дәулет (күйеу бала)
            };

            treeData.persons.forEach(p => {
                const pos = layoutMap[p.id] || { x: 1500 + p.id * 80, y: 500 };
                const isMale = p.gender === 'M';
                const isSelf = p.id === userPersonId;
                const isSelected = p.id === selectedPersonId;

                const card = document.createElement('div');
                card.className = `mh-card ${isMale ? 'male' : 'female'} ${isSelf ? 'is-self' : ''} ${isSelected ? 'selected' : ''}`;
                card.id = `card-${p.id}`;
                card.style.left = `${pos.x}px`;
                card.style.top = `${pos.y}px`;

                const initials = (p.first_name ? p.first_name[0] : '') + (p.last_name ? p.last_name[0] : '');

                card.innerHTML = `
                    <div class="mh-card-header">
                        <div class="mh-avatar">${initials}</div>
                        <div class="mh-card-info">
                            <div class="mh-name" title="${p.last_name || ''} ${p.first_name}">${p.last_name || ''} ${p.first_name}</div>
                            ${p.maiden_name ? `<div class="mh-maiden">қыз кезінде: ${p.maiden_name}</div>` : ''}
                            <div class="mh-dates">${p.birth_year || '—'} ${p.death_year ? '— ' + p.death_year : (p.is_deceased ? '†' : '(тірі)')}</div>
                            <div class="mh-place">${p.birth_place || ''}</div>
                        </div>
                    </div>
                    <div>
                        <span class="mh-badge-clan">${p.clan_ru || 'Керей'}</span>
                        ${isSelf ? `<span class="mh-kin-badge" style="background:#fef3c7; color:#b45309; border-color:#fde68a;">★ Сіз осындасыз</span>` : ''}
                    </div>

                    <!-- Радиальные кнопки вокруг карточки (MyHeritage) -->
                    <div class="radial-action-wrap">
                        <button class="radial-btn radial-top-father" data-tooltip="+ Әке қосу" onclick="event.stopPropagation(); openAddModal('father', ${p.id})">+</button>
                        <button class="radial-btn radial-top-mother" data-tooltip="+ Ана қосу" onclick="event.stopPropagation(); openAddModal('mother', ${p.id})">+</button>
                        <button class="radial-btn radial-left-spouse" data-tooltip="+ Жұбай қосу" onclick="event.stopPropagation(); openAddModal('spouse', ${p.id})">+</button>
                        <button class="radial-btn radial-right-sibling" data-tooltip="+ Бауыр қосу" onclick="event.stopPropagation(); openAddModal('sibling', ${p.id})">+</button>
                        <button class="radial-btn radial-bottom-son" data-tooltip="+ Ұл қосу" onclick="event.stopPropagation(); openAddModal('son', ${p.id})">+</button>
                        <button class="radial-btn radial-bottom-daughter" data-tooltip="+ Қыз қосу" onclick="event.stopPropagation(); openAddModal('daughter', ${p.id})">+</button>
                    </div>
                `;

                card.onclick = (e) => {
                    e.stopPropagation();
                    selectPerson(p.id);
                };

                container.appendChild(card);
            });

            renderConnectionsMyHeritage();
        }

        // Отрисовка связей (линий) в режиме MyHeritage
        function renderConnectionsMyHeritage() {
            const svg = document.getElementById('svgConnections');
            svg.innerHTML = '';

            if (currentMode !== 'myheritage' || !treeData) return;

            // Рисуем линии от отцов к детям
            treeData.persons.forEach(p => {
                if (p.father_id) {
                    const fatherCard = document.getElementById(`card-${p.father_id}`);
                    const childCard = document.getElementById(`card-${p.id}`);
                    if (fatherCard && childCard) {
                        const x1 = parseInt(fatherCard.style.left) + 125;
                        const y1 = parseInt(fatherCard.style.top) + 110;
                        const x2 = parseInt(childCard.style.left) + 125;
                        const y2 = parseInt(childCard.style.top);

                        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                        const midY = (y1 + y2) / 2;
                        path.setAttribute('d', `M ${x1} ${y1} C ${x1} ${midY}, ${x2} ${midY}, ${x2} ${y2}`);
                        path.setAttribute('stroke', '#475569');
                        path.setAttribute('stroke-width', '2.5');
                        path.setAttribute('fill', 'none');
                        path.setAttribute('stroke-dasharray', 'none');
                        svg.appendChild(path);
                    }
                }
            });

            // Линии браков (между супругами)
            treeData.marriages.forEach(m => {
                const hCard = document.getElementById(`card-${m.husband_id}`);
                const wCard = document.getElementById(`card-${m.wife_id}`);
                if (hCard && wCard) {
                    const x1 = parseInt(hCard.style.left) + 250;
                    const y1 = parseInt(hCard.style.top) + 55;
                    const x2 = parseInt(wCard.style.left);
                    const y2 = parseInt(wCard.style.top) + 55;

                    const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                    line.setAttribute('x1', x1);
                    line.setAttribute('y1', y1);
                    line.setAttribute('x2', x2);
                    line.setAttribute('y2', y2);
                    line.setAttribute('stroke', '#ec4899');
                    line.setAttribute('stroke-width', '2');
                    line.setAttribute('stroke-dasharray', '4 4');
                    svg.appendChild(line);
                }
            });
        }

        // Выбор персоны и открытие боковой панели профиля
        async function selectPerson(id) {
            selectedPersonId = id;

            // Снимаем выделение со всех карточек
            document.querySelectorAll('.mh-card').forEach(c => c.classList.remove('selected'));
            const selectedCard = document.getElementById(`card-${id}`);
            if (selectedCard) {
                selectedCard.classList.add('selected');
            }

            if (!treeData) return;
            const p = treeData.persons.find(x => x.id === id);
            if (!p) return;

            // Заполняем панель профиля
            document.getElementById('panelPersonName').innerText = `${p.last_name || ''} ${p.first_name} ${p.middle_name || ''}`;
            document.getElementById('panelPersonClan').innerText = p.clan_ru || 'Керей';
            document.getElementById('panelGender').innerText = p.gender === 'M' ? 'Ер адам (Мужской)' : 'Әйел адам (Женский)';
            document.getElementById('panelYears').innerText = `${p.birth_year || '—'} ${p.death_year ? '— ' + p.death_year : ''}`;
            document.getElementById('panelPlace').innerText = p.birth_place || 'ШҚО';
            document.getElementById('panelStatus').innerText = p.is_deceased ? 'Дүниеден өткен' : 'Тірі';
            document.getElementById('panelBiography').innerText = p.biography || 'Өмірбаяны жазылмаған.';

            // Запрашиваем казахский термин родства относительно вошедшего пользователя (Жандоса)
            try {
                const kinRes = await fetch(`/api/kinship?target_id=${p.id}&base_id=${userPersonId}`);
                const kinData = await kinRes.json();
                if (kinData && kinData.phrase) {
                    document.getElementById('panelKinshipCallout').innerHTML = kinData.phrase;
                }
            } catch (e) {
                document.getElementById('panelKinshipCallout').innerHTML = `🔗 Туыстық желісі анықталуда`;
            }

            openSidePanel();
        }

        function openSidePanel() {
            document.getElementById('sideProfilePanel').classList.add('open');
        }
        function closeSidePanel() {
            document.getElementById('sideProfilePanel').classList.remove('open');
        }

        // Плавное перемещение камеры (Fly to)
        function flyTo(targetX, targetY, targetScale = 1.0) {
            const vpWidth = viewport.clientWidth;
            const vpHeight = viewport.clientHeight;

            posX = vpWidth / 2 - targetX * targetScale;
            posY = vpHeight / 2 - targetY * targetScale;
            scale = targetScale;

            world.style.transform = `translate(${posX}px, ${posY}px) scale(${scale})`;
        }

        function flyToPerson(id) {
            selectPerson(id);
            if (currentMode === 'myheritage') {
                const card = document.getElementById(`card-${id}`);
                if (card) {
                    const cx = parseInt(card.style.left) + 125;
                    const cy = parseInt(card.style.top) + 60;
                    flyTo(cx, cy, 1.0);
                }
            } else {
                flyToCrown();
            }
        }

        function flyToSelf() {
            flyToPerson(userPersonId);
        }

        // Перемещение в крону (живые сверху)
        function flyToCrown() {
            // Центрируемся на кроне главного дерева Керей -> Алтыбаев
            flyTo(2250, 750, 0.9);
        }

        // Перемещение к дереву Конырат (куда ушла дочь Айнур)
        function flyToKonyrat() {
            flyTo(3400, 1000, 1.0);
        }

        // Модальное окно добавления
        function openAddModal(relationType, baseId = null) {
            const basePerson = treeData ? treeData.persons.find(x => x.id === (baseId || selectedPersonId)) : null;
            const baseName = basePerson ? `${basePerson.first_name} ${basePerson.last_name || ''}` : '';

            document.getElementById('formRelationType').value = relationType;
            document.getElementById('formBasePersonId').value = baseId || selectedPersonId;

            const titles = {
                father: `Әке қосу (үшін: ${baseName})`,
                mother: `Ана қосу (үшін: ${baseName})`,
                spouse: `Жұбай қосу (үшін: ${baseName})`,
                sibling: `Бауыр қосу (үшін: ${baseName})`,
                son: `Ұл қосу (үшін: ${baseName})`,
                daughter: `Қыз қосу (үшін: ${baseName})`,
                child: `Бала қосу (үшін: ${baseName})`,
            };

            document.getElementById('modalAddTitle').innerText = titles[relationType] || 'Адам қосу';
            
            // Автоматически подставляем пол
            if (relationType === 'father' || relationType === 'son') {
                document.getElementById('formGender').value = 'M';
                document.getElementById('maidenNameGroup').style.display = 'none';
            } else if (relationType === 'mother' || relationType === 'daughter') {
                document.getElementById('formGender').value = 'F';
                document.getElementById('maidenNameGroup').style.display = 'block';
            } else {
                document.getElementById('maidenNameGroup').style.display = 'block';
            }

            document.getElementById('addPersonModal').classList.add('active');
        }

        function closeAddModal() {
            document.getElementById('addPersonModal').classList.remove('active');
        }

        function toggleDeathInputs() {
            const isDeceased = document.getElementById('formIsDeceased').value === '1';
            document.getElementById('deathYearGroup').style.display = isDeceased ? 'block' : 'none';
        }

        function handleSavePerson(e) {
            e.preventDefault();
            const newPerson = {
                id: treeData.persons.length + 1,
                first_name: document.getElementById('formFirstName').value,
                last_name: document.getElementById('formLastName').value,
                maiden_name: document.getElementById('formMaidenName').value,
                gender: document.getElementById('formGender').value,
                is_deceased: parseInt(document.getElementById('formIsDeceased').value),
                birth_year: document.getElementById('formBirthYear').value,
                death_year: document.getElementById('formDeathYear').value,
                birth_place: document.getElementById('formBirthPlace').value,
                clan_ru: document.getElementById('formClanRu').value || 'Керей',
                biography: document.getElementById('formBiography').value,
                father_id: document.getElementById('formRelationType').value === 'son' || document.getElementById('formRelationType').value === 'daughter' ? parseInt(document.getElementById('formBasePersonId').value) : null,
            };

            treeData.persons.push(newPerson);
            closeAddModal();
            renderMyHeritageView();
            flyToPerson(newPerson.id);
            alert(`«${newPerson.first_name}» шежіреге сәтті қосылды!`);
        }

        // Словарь терминов родства
        function openDictionaryModal() {
            document.getElementById('dictModal').classList.add('active');
        }
        function closeDictionaryModal() {
            document.getElementById('dictModal').classList.remove('active');
        }

        // Обработка Drag & Zoom на Canvas
        viewport.onmousedown = (e) => {
            isDragging = true;
            startX = e.clientX - posX;
            startY = e.clientY - posY;
        };

        window.onmousemove = (e) => {
            if (!isDragging) return;
            posX = e.clientX - startX;
            posY = e.clientY - startY;
            world.style.transform = `translate(${posX}px, ${posY}px) scale(${scale})`;
        };

        window.onmouseup = () => {
            isDragging = false;
        };

        viewport.onwheel = (e) => {
            e.preventDefault();
            const zoomFactor = 1.1;
            const delta = e.deltaY > 0 ? (1 / zoomFactor) : zoomFactor;
            const newScale = Math.min(Math.max(0.3, scale * delta), 2.5);

            const rect = viewport.getBoundingClientRect();
            const mouseX = e.clientX - rect.left;
            const mouseY = e.clientY - rect.top;

            posX = mouseX - (mouseX - posX) * (newScale / scale);
            posY = mouseY - (mouseY - posY) * (newScale / scale);
            scale = newScale;

            world.style.transform = `translate(${posX}px, ${posY}px) scale(${scale})`;
        };

        // Живой поиск
        const searchInput = document.getElementById('treeSearchInput');
        const resultsBox = document.getElementById('searchResultsBox');

        searchInput.oninput = async () => {
            const query = searchInput.value.trim();
            if (query.length < 2) {
                resultsBox.style.display = 'none';
                return;
            }

            try {
                const res = await fetch(`/api/tree/search?q=${encodeURIComponent(query)}`);
                const data = await res.json();
                resultsBox.innerHTML = '';

                if (data.results && data.results.length > 0) {
                    data.results.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'search-item';
                        div.innerHTML = `
                            <span>${item.gender === 'M' ? '🌿' : '🌸'}</span>
                            <div>
                                <div><strong>${item.name}</strong></div>
                                <div class="item-meta">${item.clan} • ${item.years}</div>
                            </div>
                        `;
                        div.onclick = () => {
                            flyToPerson(item.id);
                            resultsBox.style.display = 'none';
                            searchInput.value = '';
                        };
                        resultsBox.appendChild(div);
                    });
                    resultsBox.style.display = 'block';
                } else {
                    resultsBox.innerHTML = `<div style="padding:10px; font-size:12px; color:#94a3b8;">Табылмады / Ничего не найдено</div>`;
                    resultsBox.style.display = 'block';
                }
            } catch (e) {
                console.error(e);
            }
        };

        document.onclick = (e) => {
            if (!e.target.closest('.search-box')) {
                resultsBox.style.display = 'none';
            }
        };

        // Запуск при старте страницы
        window.onload = loadTreeData;
    </script>
</body>
</html>
