<?php
declare(strict_types=1);
/**
 * @var array|null $user
 * @var int $userPersonId
 * @var array $kinshipTerms
 * @var string $conceptArtUrl
 */
$userName = $user['name'] ?? 'Жандос Алтыбаев';
$userEmail = $user['email'] ?? 'zhandos.alt06@gmail.com';
$conceptArt = $conceptArtUrl ?? '/assets/img/shezhire_living_forest_concept.jpg';
?>
<!DOCTYPE html>
<html lang="kk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Шежіре Алтыбаевтар — Интерактивті Древо («Өңдеу» және «Шежіре-Орман»)</title>
    <style>
        :root {
            --bg-canvas: #090e17;
            --bg-card: #ffffff;
            --text-dark: #0f172a;
            --text-light: #f8fafc;
            --primary: #1e3a8a;
            --primary-accent: #3b82f6;
            --gold: #d97706;
            --gold-light: #fef3c7;
            --gold-glow: rgba(245, 158, 11, 0.45);
            --male-color: #2563eb;
            --male-light: #eff6ff;
            --female-color: #ec4899;
            --female-light: #fdf2f8;
            --memory-gold: #b45309;
            --memory-bg: #fffbeb;
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

        /* ========================================================
           ЖОҒАРҒЫ БАСҚАРУ ПАНЕЛІ (HEADER)
           ======================================================== */
        header.tree-nav {
            background: linear-gradient(90deg, #09121d, #142132);
            border-bottom: 2px solid #d4af37;
            padding: 10px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
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
            padding: 6px 12px;
            border-radius: 8px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .back-link:hover {
            color: #fff;
            background: rgba(255,255,255,0.15);
            border-color: #d4af37;
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

        /* Режимдер ауыстырғышы («Өңдеу» / «Шежіре-Орман») */
        .mode-switcher {
            display: flex;
            background: rgba(0, 0, 0, 0.5);
            padding: 3px;
            border-radius: 30px;
            border: 1px solid rgba(212, 175, 55, 0.45);
        }

        .mode-btn {
            padding: 7px 18px;
            border-radius: 25px;
            border: none;
            background: transparent;
            color: #cbd5e1;
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .mode-btn.active {
            background: linear-gradient(135deg, #d4af37, #b45309);
            color: #ffffff;
            box-shadow: 0 2px 10px rgba(212, 175, 55, 0.5);
        }

        /* Көлем ауыстырғышы (Окрестность / Барлығы 270) */
        .scope-switcher {
            display: flex;
            background: rgba(0, 0, 0, 0.5);
            padding: 3px;
            border-radius: 30px;
            border: 1px solid rgba(148, 163, 184, 0.35);
        }
        .scope-btn {
            padding: 6px 14px;
            border-radius: 20px;
            border: none;
            background: transparent;
            color: #94a3b8;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .scope-btn:hover {
            color: #f8fafc;
        }
        .scope-btn.active {
            background: rgba(255, 255, 255, 0.15);
            color: #fef08a;
            border: 1px solid rgba(212, 175, 55, 0.4);
            box-shadow: 0 2px 8px rgba(0,0,0,0.4);
        }

        /* Тіл таңдау және оң жақ құралдар */
        .nav-right {
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
        }

        .lang-switcher {
            display: flex;
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid #334155;
            border-radius: 20px;
            padding: 2px;
        }

        .lang-btn {
            background: transparent;
            border: none;
            color: #94a3b8;
            padding: 5px 11px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }

        .lang-btn.active {
            background: #d4af37;
            color: #0f172a;
        }

        .search-box {
            position: relative;
            width: 240px;
        }

        .search-input {
            width: 100%;
            padding: 7px 12px 7px 32px;
            border-radius: 20px;
            border: 1px solid #334155;
            background: rgba(15, 23, 42, 0.9);
            color: #fff;
            font-size: 13px;
            outline: none;
            transition: border-color 0.2s;
        }
        .search-input:focus {
            border-color: #d4af37;
        }
        .search-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 13px;
            color: #64748b;
        }

        .search-dropdown {
            position: absolute;
            top: 110%;
            left: 0;
            width: 100%;
            background: #0f172a;
            border: 1px solid #d4af37;
            border-radius: 10px;
            max-height: 300px;
            overflow-y: auto;
            display: none;
            z-index: 200;
            box-shadow: 0 10px 25px rgba(0,0,0,0.7);
        }
        .search-dropdown.active {
            display: block;
        }
        .search-item {
            padding: 9px 12px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            cursor: pointer;
            font-size: 13px;
            color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .search-item:hover {
            background: rgba(212, 175, 55, 0.2);
            color: #fff;
        }

        .btn-tool {
            background: rgba(255,255,255,0.07);
            color: #f8fafc;
            border: 1px solid rgba(255,255,255,0.15);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-tool:hover {
            background: rgba(212, 175, 55, 0.25);
            border-color: #d4af37;
            color: #fef08a;
        }

        .btn-tool.active {
            background: rgba(212, 175, 55, 0.35);
            border-color: #d4af37;
            color: #fef08a;
            box-shadow: 0 0 12px rgba(212, 175, 55, 0.5);
        }

        /* БОС КҮЙ (EMPTY STATE) — ДЕРЕКТЕР БОЛМАҒАНДА */
        .tree-empty-state {
            position: absolute;
            left: 1700px;
            top: 450px;
            width: 620px;
            max-width: 90vw;
            background: rgba(15, 23, 42, 0.96);
            border: 2px solid #d4af37;
            border-radius: 20px;
            padding: 38px 32px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.85);
            color: #f8fafc;
            z-index: 100;
            backdrop-filter: blur(12px);
        }
        .tree-empty-state .empty-icon {
            font-size: 54px;
            margin-bottom: 14px;
        }
        .tree-empty-state h2 {
            color: #fef08a;
            font-size: 21px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        .tree-empty-state p {
            color: #cbd5e1;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .empty-actions {
            display: flex;
            justify-content: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        .btn-empty-action {
            padding: 11px 22px;
            border-radius: 12px;
            font-size: 13.5px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-empty-action.btn-import {
            background: linear-gradient(135deg, #d4af37, #b45309);
            color: #0f172a;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4);
        }
        .btn-empty-action.btn-import:hover {
            background: linear-gradient(135deg, #fef08a, #d4af37);
            transform: translateY(-2px);
        }

        /* ========================================================
           КАНВАС ЖӘНЕ КЕҢІСТІК (VIEWPORT & WORLD)
           ======================================================== */
        .canvas-container {
            flex: 1;
            position: relative;
            overflow: hidden;
            background: radial-gradient(circle at 50% 20%, #152238 0%, #080d16 100%);
            cursor: grab;
        }
        .canvas-container:active {
            cursor: grabbing;
        }

        .world {
            position: absolute;
            transform-origin: 0 0;
            transition: transform 0.05s linear;
            will-change: transform;
            width: 7000px;
            height: 5000px;
        }

        /* ========================================================
           1. «ӨҢДЕУ» ЖҰМЫС РЕЖИМІ (ДӘСТҮРЛІ КАРТОЧКАЛАР МЕН СВЯЗИ)
           ======================================================== */
        .editing-view {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        svg.connections-layer {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 10;
        }

        /* Персона карточкасы */
        .person-card {
            position: absolute;
            width: 250px;
            background: var(--bg-card);
            color: var(--text-dark);
            border-radius: 12px;
            padding: 12px 14px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.35);
            border: 2px solid #cbd5e1;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
            z-index: 20;
        }

        .person-card:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 12px 28px rgba(0,0,0,0.5);
            z-index: 35;
        }

        .person-card.male {
            border-top: 4px solid var(--male-color);
        }
        .person-card.female {
            border-top: 4px solid var(--female-color);
        }

        /* ҰШҚАН / ДҮНИЕДЕН ӨТКЕН ТҰЛҒАЛАР — ЕСТЕ БОЛУ АЛТЫН РЕҢКІ (СҰР ЕМЕС!) */
        .person-card.deceased {
            border-color: #d97706;
            background: linear-gradient(180deg, #ffffff 0%, #fffbeb 100%);
            box-shadow: 0 6px 22px rgba(217, 119, 6, 0.25);
        }
        .person-card.deceased .card-years {
            color: #b45309;
            font-weight: 700;
        }

        .person-card.selected {
            border: 2.5px solid #f59e0b;
            box-shadow: 0 0 25px var(--gold-glow);
            z-index: 40;
        }

        .person-card.is-self {
            border: 2.5px solid #3b82f6;
            box-shadow: 0 0 25px rgba(59, 130, 246, 0.4);
        }

        /* Тармақтарды ашу / жабу батырмалары (Expand / Collapse branch buttons) */
        .btn-branch-expand {
            position: absolute;
            background: #0f172a;
            color: #fef08a;
            border: 1.5px solid #d4af37;
            border-radius: 14px;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.6);
            z-index: 45;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }
        .btn-branch-expand:hover {
            background: #d4af37;
            color: #0f172a;
            transform: translateX(-50%) scale(1.08);
            box-shadow: 0 0 16px rgba(212, 175, 55, 0.8);
        }
        .btn-branch-top {
            top: -14px;
            left: 50%;
            transform: translateX(-50%);
        }
        .btn-branch-bottom {
            bottom: -14px;
            left: 50%;
            transform: translateX(-50%);
        }

        /* Неке байланысының белгісі (Marriage Link Badge) */
        .marriage-edge-badge {
            position: absolute;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #ffffff;
            border: 2px solid #ec4899;
            color: #ec4899;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transform: translate(-50%, -50%);
            box-shadow: 0 3px 10px rgba(236, 72, 153, 0.4);
            z-index: 25;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .marriage-edge-badge:hover {
            transform: translate(-50%, -50%) scale(1.25);
            box-shadow: 0 0 14px rgba(236, 72, 153, 0.8);
        }

        /* Туыстық бейджигі (динамикалық түрде таңдалған адамға қатысты өзгереді) */
        .card-kinship-badge {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 12px;
            margin-bottom: 8px;
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        .card-kinship-badge.active-rel {
            background: #fef3c7;
            color: #92400e;
            border-color: #fde68a;
            box-shadow: 0 2px 6px rgba(217, 119, 6, 0.2);
            animation: badge-pop 0.3s ease;
        }
        .card-kinship-badge.self-rel {
            background: #dbeafe;
            color: #1e40af;
            border-color: #bfdbfe;
        }

        @keyframes badge-pop {
            0% { transform: scale(0.9); }
            50% { transform: scale(1.06); }
            100% { transform: scale(1); }
        }

        .card-header-inner {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .card-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            color: #475569;
            flex-shrink: 0;
            border: 2px solid #cbd5e1;
        }
        .person-card.male .card-avatar {
            background: var(--male-light);
            color: var(--male-color);
            border-color: var(--male-color);
        }
        .person-card.female .card-avatar {
            background: var(--female-light);
            color: var(--female-color);
            border-color: var(--female-color);
        }
        .person-card.deceased .card-avatar {
            background: #fef3c7;
            color: #b45309;
            border-color: #d97706;
        }

        .card-info {
            flex: 1;
            min-width: 0;
        }
        .card-name {
            font-size: 13.5px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #0f172a;
        }
        .card-maiden {
            font-size: 11px;
            color: #ec4899;
            font-style: italic;
        }
        .card-years {
            font-size: 11.5px;
            color: #64748b;
            margin-top: 2px;
        }
        .card-place {
            font-size: 11px;
            color: #94a3b8;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .card-clan-tag {
            font-size: 10.5px;
            padding: 2px 7px;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            color: #475569;
            font-weight: 600;
            margin-top: 6px;
            display: inline-block;
        }

        /* Карточка төңірегіндегі радиалды батырмалар */
        .radial-action-wrap {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        .person-card.selected .radial-action-wrap {
            opacity: 1;
            pointer-events: auto;
        }

        .radial-btn {
            position: absolute;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #1e293b;
            border: 2px solid #d4af37;
            color: #fef08a;
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0,0,0,0.5);
            transition: all 0.2s;
        }
        .radial-btn:hover {
            transform: scale(1.22);
            background: #d4af37;
            color: #0f172a;
        }

        .radial-btn::after {
            content: attr(data-tooltip);
            position: absolute;
            background: #0f172a;
            color: #fff;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 10px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.15s;
            border: 1px solid #d4af37;
            z-index: 50;
        }
        .radial-btn:hover::after {
            opacity: 1;
        }

        .radial-top-father { top: -14px; left: 25%; transform: translate(-50%, -50%); }
        .radial-top-father::after { bottom: 125%; left: 50%; transform: translateX(-50%); }

        .radial-top-mother { top: -14px; left: 75%; transform: translate(-50%, -50%); }
        .radial-top-mother::after { bottom: 125%; left: 50%; transform: translateX(-50%); }

        .radial-left-spouse { top: 50%; left: -14px; transform: translate(-50%, -50%); }
        .radial-left-spouse::after { right: 125%; top: 50%; transform: translateY(-50%); }

        .radial-right-sibling { top: 50%; right: -14px; transform: translate(50%, -50%); }
        .radial-right-sibling::after { left: 125%; top: 50%; transform: translateY(-50%); }

        .radial-bottom-son { bottom: -14px; left: 35%; transform: translate(-50%, 50%); }
        .radial-bottom-son::after { top: 125%; left: 50%; transform: translateX(-50%); }

        .radial-bottom-daughter { bottom: -14px; left: 65%; transform: translate(-50%, 50%); }
        .radial-bottom-daughter::after { top: 125%; left: 50%; transform: translateX(-50%); }


        /* ========================================================
           2. «ШЕЖІРЕ-ОРМАН» КӨРКЕМ РЕЖИМІ (НАҒЫЗ ТІРІ КАРТИНА)
           ======================================================== */
        .forest-view {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: none;
            background: linear-gradient(180deg, 
                #071526 0%, 
                #102a45 25%, 
                #234b6e 45%, 
                #4e7a82 65%, 
                #d4a373 85%, 
                #2d5029 95%, 
                #1b3519 100%);
        }

        /* Артқы жарық күн (Солнце сзади светит сквозь кроны) */
        .forest-sun {
            position: absolute;
            top: 250px;
            left: 2300px;
            width: 900px;
            height: 900px;
            border-radius: 50%;
            background: radial-gradient(circle, 
                rgba(255, 243, 176, 0.95) 0%, 
                rgba(253, 216, 53, 0.6) 25%, 
                rgba(245, 158, 11, 0.3) 50%, 
                rgba(217, 119, 6, 0.08) 75%, 
                transparent 100%);
            filter: blur(15px);
            pointer-events: none;
            animation: sun-pulse 8s ease-in-out infinite alternate;
        }

        @keyframes sun-pulse {
            0% { transform: scale(0.96); opacity: 0.85; }
            100% { transform: scale(1.05); opacity: 1; }
        }

        /* Күн сәулелері (God rays / Sunbeams) */
        .forest-god-rays {
            position: absolute;
            top: 0;
            left: 1700px;
            width: 2100px;
            height: 2500px;
            pointer-events: none;
            background: repeating-linear-gradient(
                70deg,
                rgba(255, 255, 230, 0.08) 0px,
                rgba(255, 255, 230, 0.08) 40px,
                transparent 40px,
                transparent 100px
            );
            mask-image: radial-gradient(ellipse at 50% 20%, rgba(0,0,0,1) 0%, transparent 75%);
            -webkit-mask-image: radial-gradient(ellipse at 50% 20%, rgba(0,0,0,1) 0%, transparent 75%);
        }

        /* Төменгі шөпті алаңқай (Поляна с полевыми цветами) */
        .forest-meadow-floor {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 7000px;
            height: 800px;
            background: linear-gradient(180deg, 
                rgba(52, 98, 47, 0.3) 0%, 
                #234b1e 25%, 
                #163313 70%, 
                #0d200b 100%);
            border-top: 3px solid rgba(134, 239, 172, 0.3);
            pointer-events: none;
        }

        /* Ағаштардың тоғайы (Лес родов: Керей, Жағалбайлы, Қоңырат) */
        .clan-tree-stand {
            position: absolute;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Орталық басты ағаш: Керей -> Алтыбаевтар */
        .stand-main-kerey {
            left: 2100px;
            top: 400px;
            width: 1400px;
        }

        /* Көрші ағаш: Жағалбайлы (сол жақта) */
        .stand-clan-left {
            left: 800px;
            top: 750px;
            width: 950px;
            opacity: 0.9;
        }

        /* Көрші ағаш: Қоңырат (оң жақта) */
        .stand-clan-right {
            left: 3800px;
            top: 750px;
            width: 950px;
            opacity: 0.9;
        }

        /* Ағаштың қасиетті тамыры (Корень внизу) */
        .tree-sacred-root {
            position: relative;
            margin-top: 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 25;
        }

        .root-ancestor-badge {
            background: linear-gradient(135deg, #3d2314, #1c1009);
            border: 3px solid #d4af37;
            border-radius: 35px;
            padding: 14px 28px;
            color: #fef08a;
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.8), 0 0 25px rgba(212, 175, 55, 0.4);
            cursor: pointer;
            transition: all 0.25s;
        }
        .root-ancestor-badge:hover {
            transform: scale(1.08);
            border-color: #fef08a;
            box-shadow: 0 0 35px rgba(212, 175, 55, 0.8);
        }

        /* Биік қасиетті діңгек (Высокий ствол с корой) */
        .organic-trunk {
            position: relative;
            width: 90px;
            min-height: 550px;
            background: linear-gradient(90deg, 
                #1f120a 0%, 
                #3b2214 25%, 
                #5a351d 50%, 
                #3b2214 75%, 
                #1f120a 100%);
            border-radius: 45px;
            box-shadow: inset 0 0 15px rgba(0,0,0,0.8), 0 15px 35px rgba(0,0,0,0.7);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-around;
            padding: 30px 0;
            margin: 20px 0;
            z-index: 15;
            border-left: 2px solid rgba(212, 175, 55, 0.2);
            border-right: 2px solid rgba(0, 0, 0, 0.6);
        }

        /* Тұлғалардың діңгектегі түйіндері (Узлы предков на стволе) */
        .trunk-ancestor-node {
            background: rgba(15, 23, 42, 0.95);
            border: 2px solid #d4af37;
            border-radius: 20px;
            padding: 8px 16px;
            color: #fef3c7;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
            box-shadow: 0 4px 15px rgba(0,0,0,0.7);
            cursor: pointer;
            transition: all 0.25s;
            z-index: 20;
        }
        .trunk-ancestor-node:hover {
            transform: scale(1.1);
            background: #d4af37;
            color: #0f172a;
            box-shadow: 0 0 25px rgba(212, 175, 55, 0.9);
        }

        /* КРОНА (ЖОҒАРҒЫ ҚАТАРДА ТІРІЛЕР — ЖАС ҰРПАҚТАР) */
        .organic-crown-container {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 25px;
            z-index: 20;
            padding: 30px;
        }

        .crown-living-banner {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.25), rgba(21, 128, 61, 0.15));
            border: 1px solid rgba(74, 222, 128, 0.4);
            border-radius: 30px;
            padding: 8px 24px;
            font-size: 14px;
            font-weight: 700;
            color: #86efac;
            box-shadow: 0 6px 20px rgba(34, 197, 94, 0.25);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* БҰТАҚТАР (СЕМЬИ = ВЕТКИ) */
        .family-branch {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            padding: 20px 30px;
            border-radius: 40px;
            background: radial-gradient(ellipse at center, rgba(34, 197, 94, 0.12) 0%, transparent 70%);
            margin: 10px 0;
        }

        .branch-couple-node {
            display: flex;
            align-items: center;
            gap: 12px;
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border: 2px solid #4ade80;
            border-radius: 30px;
            padding: 8px 18px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.6);
            cursor: pointer;
            transition: all 0.25s;
            z-index: 25;
        }
        .branch-couple-node:hover {
            transform: scale(1.06);
            border-color: #fef08a;
            box-shadow: 0 0 25px rgba(254, 240, 138, 0.6);
        }
        .branch-couple-node.is-self-node {
            border: 3px solid #f59e0b;
            background: linear-gradient(135deg, #1e3a8a, #0369a1);
            box-shadow: 0 0 30px rgba(245, 158, 11, 0.7);
            animation: pulse-self 2.5s infinite;
        }

        @keyframes pulse-self {
            0% { box-shadow: 0 0 15px rgba(245, 158, 11, 0.5); }
            50% { box-shadow: 0 0 35px rgba(245, 158, 11, 0.95); }
            100% { box-shadow: 0 0 15px rgba(245, 158, 11, 0.5); }
        }

        /* БҰТАҚТА ӨСКЕН БАЛАЛАР (ЛИСТИКИ И ЦВЕТОЧКИ) */
        .branch-children-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        /* ЛИСТИК — ҰЛ БАЛА (СЫН ДО ЖЕНИТЬБЫ) */
        .leaf-son-node {
            display: flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #14532d, #166534);
            border: 2px solid #86efac;
            border-radius: 25px 5px 25px 5px; /* форма листика */
            padding: 8px 16px;
            color: #f0fdf4;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 6px 16px rgba(0,0,0,0.4);
            transition: all 0.25s;
            animation: leaf-sway 4s ease-in-out infinite alternate;
        }
        .leaf-son-node:hover {
            transform: scale(1.12) rotate(-2deg);
            border-color: #fef08a;
            box-shadow: 0 0 22px rgba(134, 239, 172, 0.8);
        }

        /* ҰШҚАН АТА-БАБАЛАРДЫҢ ЛИСТИГІ — АЛТЫНДАЙ ЕСТЕ САҚТАУ КҮЗГІ ЖАПЫРАҚ (СҰР ЕМЕС!) */
        .leaf-son-node.deceased-leaf {
            background: linear-gradient(135deg, #78350f, #92400e);
            border-color: #f59e0b;
            color: #fef3c7;
            box-shadow: 0 6px 18px rgba(245, 158, 11, 0.3);
        }
        .leaf-son-node.deceased-leaf:hover {
            box-shadow: 0 0 25px rgba(245, 158, 11, 0.8);
        }

        @keyframes leaf-sway {
            0% { transform: rotate(0deg); }
            50% { transform: rotate(3deg); }
            100% { transform: rotate(-2deg); }
        }

        /* ГҮЛ — ҚЫЗ БАЛА (ДОЧЬ) */
        .flower-daughter-node {
            display: flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #831843, #9d174d);
            border: 2px solid #f472b6;
            border-radius: 25px;
            padding: 8px 18px;
            color: #fdf2f8;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(236, 72, 153, 0.4);
            transition: all 0.25s;
            animation: flower-glow 3s ease-in-out infinite alternate;
        }
        .flower-daughter-node:hover {
            transform: scale(1.12);
            border-color: #fbcfe8;
            box-shadow: 0 0 25px rgba(244, 114, 182, 0.9);
        }

        /* ҰШҚАН ҚЫЗДАР МЕН АНАЛАР — АЛТЫН ТҮСТІ ГҮЛ */
        .flower-daughter-node.deceased-flower {
            background: linear-gradient(135deg, #7c2d12, #9a3412);
            border-color: #fb923c;
            color: #ffedd5;
        }

        @keyframes flower-glow {
            0% { filter: drop-shadow(0 0 4px rgba(244, 114, 182, 0.4)); }
            100% { filter: drop-shadow(0 0 14px rgba(244, 114, 182, 0.85)); }
        }

        /* ҰЗАТЫЛЫП КЕТКЕН ҚЫЗДЫҢ ҚАЛҒАН НӘЗІК ІЗІ (СЛЕД УШЕДШЕЙ В ДРУГОЙ РОД) */
        .out-married-trace-node {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(30, 41, 59, 0.7);
            border: 2px dashed #f472b6;
            border-radius: 25px;
            padding: 8px 16px;
            color: #fbcfe8;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s;
        }
        .out-married-trace-node:hover {
            transform: scale(1.08);
            background: rgba(244, 114, 182, 0.25);
            border-color: #fff;
            color: #fff;
            box-shadow: 0 0 20px rgba(244, 114, 182, 0.7);
        }

        /* СЫРТТАН КЕЛГЕН КЕЛІНДЕР (ЖҰБАЙЛАР) */
        .in-married-flower-node {
            display: flex;
            align-items: center;
            gap: 6px;
            background: #fff;
            color: #9d174d;
            border: 2px solid #ec4899;
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        .in-married-flower-node:hover {
            transform: scale(1.08);
            background: #fdf2f8;
            box-shadow: 0 0 15px rgba(236, 72, 153, 0.6);
        }


        /* ========================================================
           БОЙЫНША ПАНЕЛЬ ЖӘНЕ ДЕТАЛЬДАР (SIDE PROFILE PANEL)
           ======================================================== */
        aside.side-profile-panel {
            position: fixed;
            top: 60px;
            right: -420px;
            width: 400px;
            height: calc(100vh - 60px);
            background: rgba(15, 23, 42, 0.96);
            backdrop-filter: blur(14px);
            border-left: 2px solid #d4af37;
            box-shadow: -10px 0 30px rgba(0,0,0,0.7);
            transition: right 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 150;
            display: flex;
            flex-direction: column;
        }
        aside.side-profile-panel.open {
            right: 0;
        }

        .side-header {
            padding: 18px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .close-panel-btn {
            background: transparent;
            border: none;
            color: #94a3b8;
            font-size: 20px;
            cursor: pointer;
            padding: 4px;
        }
        .close-panel-btn:hover {
            color: #fff;
        }

        .side-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .kin-callout {
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.2), rgba(180, 83, 9, 0.15));
            border: 1px solid #d4af37;
            border-radius: 12px;
            padding: 14px;
            font-size: 13.5px;
            color: #fef08a;
            line-height: 1.5;
        }

        .profile-kv {
            display: flex;
            flex-direction: column;
            gap: 10px;
            background: rgba(255,255,255,0.03);
            border-radius: 10px;
            padding: 14px;
            border: 1px solid rgba(255,255,255,0.06);
        }
        .profile-kv-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
        }
        .profile-kv-label {
            color: #94a3b8;
        }
        .profile-kv-val {
            color: #f8fafc;
            font-weight: 600;
        }

        .bio-box {
            background: rgba(0,0,0,0.3);
            border-radius: 10px;
            padding: 14px;
            font-size: 13px;
            color: #cbd5e1;
            line-height: 1.6;
            max-height: 200px;
            overflow-y: auto;
        }


        /* ========================================================
           МОДАЛЬДЫ ТЕРЕЗЕЛЕР (MODALS)
           ======================================================== */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0,0,0,0.75);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 300;
        }
        .modal-overlay.active {
            display: flex;
        }

        .modal-card {
            background: #0f172a;
            border: 2px solid #d4af37;
            border-radius: 16px;
            width: 520px;
            max-width: 92vw;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 15px 40px rgba(0,0,0,0.8);
            color: #f8fafc;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: 18px 22px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-header h3 {
            color: #fef08a;
            font-size: 17px;
        }

        .modal-body {
            padding: 22px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .form-group label {
            font-size: 12.5px;
            color: #94a3b8;
            font-weight: 600;
        }
        .form-input, .form-select, .form-textarea {
            background: #1e293b;
            border: 1px solid #334155;
            color: #fff;
            padding: 9px 12px;
            border-radius: 8px;
            font-size: 13.5px;
            outline: none;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: #d4af37;
        }

        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .modal-footer {
            padding: 16px 22px;
            border-top: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        .btn-modal-cancel {
            background: transparent;
            border: 1px solid #475569;
            color: #cbd5e1;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-modal-submit {
            background: linear-gradient(135deg, #d4af37, #b45309);
            border: none;
            color: #fff;
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.4);
        }

        /* ========================================================
           КОНЦЕПТ-АРТ МОДАЛЫ (ЭСКИЗ ЖАНДОСҚА АРНАЛҒАН)
           ======================================================== */
        .art-modal-card {
            background: #09121d;
            border: 2px solid #d4af37;
            border-radius: 16px;
            width: 880px;
            max-width: 94vw;
            max-height: 92vh;
            overflow-y: auto;
            color: #f8fafc;
            display: flex;
            flex-direction: column;
        }

        .art-image-wrap {
            position: relative;
            width: 100%;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.8);
            border: 1px solid rgba(212, 175, 55, 0.4);
        }
        .art-image-wrap img {
            width: 100%;
            height: auto;
            display: block;
        }

        /* ========================================================
           МАСШТАБТАУ ПАНЕЛІ (ZOOM CONTROLS)
           ======================================================== */
        .zoom-controls {
            position: fixed;
            bottom: 24px;
            left: 24px;
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid #334155;
            border-radius: 30px;
            padding: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
            z-index: 100;
            box-shadow: 0 8px 20px rgba(0,0,0,0.5);
        }
        .zoom-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            background: transparent;
            color: #cbd5e1;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .zoom-btn:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }
        .zoom-val {
            font-size: 12px;
            font-weight: 700;
            color: #94a3b8;
            padding: 0 8px;
        }

        /* Шартты белгілер легендасы (Legend) */
        .tree-legend {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: rgba(15, 23, 42, 0.92);
            backdrop-filter: blur(10px);
            border: 1px solid #334155;
            border-radius: 14px;
            padding: 10px 16px;
            font-size: 12px;
            color: #cbd5e1;
            display: flex;
            flex-direction: column;
            gap: 6px;
            z-index: 100;
            box-shadow: 0 8px 20px rgba(0,0,0,0.5);
        }
        .legend-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        /* Шаңырақ ореолдары (Family Aura) */
        .family-aura {
            position: absolute;
            border-radius: 20px;
            background: radial-gradient(ellipse at center, rgba(212, 175, 55, 0.05) 0%, rgba(212, 175, 55, 0.01) 70%, transparent 100%);
            border: 1.5px dashed rgba(212, 175, 55, 0.25);
            pointer-events: none;
            z-index: 1;
            transition: all 0.3s ease;
        }
        .family-aura-label {
            position: absolute;
            top: -12px;
            left: 20px;
            padding: 2px 10px;
            font-size: 11px;
            font-weight: 600;
            color: #d4af37;
            background: #0f172a;
            border: 1px solid rgba(212, 175, 55, 0.35);
            border-radius: 10px;
            letter-spacing: 0.5px;
        }

        /* Жұбайлар біртұтас планкасы (Couple Unit Plate) */
        .couple-unit-plate {
            position: absolute;
            border-radius: 18px;
            background: rgba(30, 41, 59, 0.5);
            border: 1.5px solid rgba(236, 72, 153, 0.3);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.35), inset 0 0 15px rgba(236, 72, 153, 0.05);
            pointer-events: none;
            z-index: 2;
        }

        /* ========================================================
           3. «ТІЗІМ / СПИСОК» РЕЖИМІ
           ======================================================== */
        .list-view-container {
            width: 100%;
            height: calc(100vh - 64px);
            overflow-y: auto;
            background: #0b1120;
            padding: 24px 32px;
            box-sizing: border-box;
            color: #f8fafc;
        }

        .list-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(51, 65, 85, 0.7);
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 20px;
            backdrop-filter: blur(8px);
        }

        .list-search-input {
            flex: 1 1 240px;
            min-width: 200px;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 10px;
            padding: 10px 14px;
            color: #f8fafc;
            font-size: 13.5px;
            outline: none;
            transition: border-color 0.2s ease;
        }
        .list-search-input:focus {
            border-color: #d4af37;
            box-shadow: 0 0 0 2px rgba(212, 175, 55, 0.25);
        }

        .list-select-filter {
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 10px;
            padding: 10px 14px;
            color: #e2e8f0;
            font-size: 13px;
            outline: none;
            cursor: pointer;
            transition: border-color 0.2s ease;
        }
        .list-select-filter:focus {
            border-color: #d4af37;
        }

        .list-stats-count {
            font-size: 13px;
            font-weight: 600;
            color: #d4af37;
            padding: 8px 14px;
            background: rgba(212, 175, 55, 0.12);
            border-radius: 8px;
            border: 1px solid rgba(212, 175, 55, 0.25);
            white-space: nowrap;
        }

        .list-table-wrapper {
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(51, 65, 85, 0.7);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.35);
        }

        .list-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        .list-table th {
            background: rgba(30, 41, 59, 0.9);
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            padding: 14px 16px;
            border-bottom: 1px solid #334155;
            white-space: nowrap;
        }

        .list-table td {
            padding: 12px 16px;
            border-bottom: 1px solid rgba(51, 65, 85, 0.4);
            color: #e2e8f0;
            vertical-align: middle;
        }

        .list-table tr:hover td {
            background: rgba(51, 65, 85, 0.3);
        }

        .list-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11.5px;
            font-weight: 700;
            margin-right: 12px;
            flex-shrink: 0;
            border: 1.5px solid transparent;
        }
        .list-avatar.male {
            background: #1e3a5f;
            color: #60a5fa;
            border-color: #2563eb;
        }
        .list-avatar.female {
            background: #4c1d3d;
            color: #f472b6;
            border-color: #ec4899;
        }
        .list-avatar.deceased {
            background: #451a03;
            color: #fcd34d;
            border-color: #d97706;
        }

        .gender-badge-m {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 6px;
            background: rgba(37, 99, 235, 0.15);
            color: #60a5fa;
            font-size: 11px;
            font-weight: 600;
        }
        .gender-badge-f {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 6px;
            background: rgba(236, 72, 153, 0.15);
            color: #f472b6;
            font-size: 11px;
            font-weight: 600;
        }

        .btn-table-action {
            background: #1e293b;
            color: #cbd5e1;
            border: 1px solid #475569;
            border-radius: 6px;
            padding: 4px 9px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-right: 4px;
        }
        .btn-table-action:hover {
            background: #334155;
            color: #f8fafc;
            border-color: #d4af37;
        }
    </style>
</head>
<body>

    <!-- ЖОҒАРҒЫ БАСҚАРУ ПАНЕЛІ -->
    <header class="tree-nav">
        <div class="nav-left">
            <a href="/" class="back-link">
                <span>←</span>
                <span data-i18n="back_to_portal">Басты бет</span>
            </a>
            <div class="brand-title">
                <span data-i18n="brand_title_main">Шежіре</span>
                <span data-i18n="brand_title_sub">Алтыбаевтар</span>
            </div>
        </div>

        <!-- РЕЖИМДЕР АУЫСТЫРҒЫШЫ («ӨҢДЕУ», «ШЕЖІРЕ-ОРМАН» ЖӘНЕ «ТІЗІМ») -->
        <div class="mode-switcher">
            <button class="mode-btn active" id="btnModeEditing" onclick="switchMode('editing')">
                <span>🛠️</span>
                <span data-i18n="mode_editing">Өңдеу</span>
            </button>
            <button class="mode-btn" id="btnModeForest" onclick="switchMode('forest')">
                <span>🌲</span>
                <span data-i18n="mode_forest">Шежіре-Орман</span>
            </button>
            <button class="mode-btn" id="btnModeList" onclick="switchMode('list')">
                <span>📋</span>
                <span data-i18n="mode_list">Тізім</span>
            </button>
        </div>

        <!-- КӨЛЕМ АУЫСТЫРҒЫШЫ (ОКРЕСТНОСТЬ / ВСЕ ДРЕВО 270) -->
        <div class="scope-switcher" id="scopeSwitcher">
            <button class="scope-btn active" id="btnScopeFocal" onclick="switchScope('focal')" title="Окрестность выбранного человека (MyHeritage)">
                <span>🔍</span>
                <span data-i18n="scope_focal">Окрестность</span>
            </button>
            <button class="scope-btn" id="btnScopeFull" onclick="switchScope('full')" title="Показать всё древо (270 человек)">
                <span>🌐</span>
                <span data-i18n="scope_full">Все древо</span>
            </button>
        </div>

        <!-- ТІЛ, ІЗДЕУ ЖӘНЕ ҚОСЫМША ҚҰРАЛДАР -->
        <div class="nav-right">
            <!-- Тіл ауыстырғыш (Қаз / Рус) -->
            <div class="lang-switcher">
                <button class="lang-btn active" id="langBtnKk" onclick="setLanguage('kk')">ҚАЗ</button>
                <button class="lang-btn" id="langBtnRu" onclick="setLanguage('ru')">РУС</button>
            </div>

            <!-- Іздеу жолы -->
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" class="search-input" id="treeSearchInput" placeholder="Адамды іздеу..." autocomplete="off">
                <div class="search-dropdown" id="searchDropdown"></div>
            </div>

            <!-- Батырмалар -->
            <button class="btn-tool" onclick="flyToSelf()" title="Өз карточкаңызға бару">
                <span>🎯</span>
                <span data-i18n="btn_you_are_here">Сіз осындасыз</span>
            </button>

            <button class="btn-tool" onclick="openArtModal()" title="Шежіре-Орман арт-тұжырымдамасы">
                <span>🎨</span>
                <span data-i18n="btn_art_concept">Арт-эскиз</span>
            </button>

            <button class="btn-tool" onclick="openKinshipDictionaryModal()" title="Қазақтың туыстық атаулары">
                <span>📖</span>
                <span data-i18n="btn_kinship_dict">Сөздік</span>
            </button>

            <!-- Жеті ата тексеру және плакат басып шығару (TASK-004) -->
            <button class="btn-tool" onclick="openZhetiAtaTreeModal()" title="Қазақтың «Жеті ата» қағидасын тексеру">
                <span>📜</span>
                <span>Жеті ата</span>
            </button>

            <a href="/tree/print" class="btn-tool" title="Шежірені басып шығару (Подарочный плакат)" style="text-decoration:none; color:inherit;">
                <span>🖨️</span>
                <span>Плакат</span>
            </a>
        </div>
    </header>

    <!-- ИНТЕРАКТИВТІ КАНВАС -->
    <div class="canvas-container" id="canvasViewport">
        <div class="world" id="treeWorld">

            <!-- ========================================================
                 1. «ӨҢДЕУ» РЕЖИМІ (ДӘСТҮРЛІ КАРТОЧКАЛАР МЕН СВЯЗИ)
                 ======================================================== -->
            <div class="editing-view" id="editingView">
                <svg class="connections-layer" id="svgConnections"></svg>
                <div id="editingCardsContainer"></div>
            </div>

            <!-- ========================================================
                 2. «ШЕЖІРЕ-ОРМАН» КӨРКЕМ РЕЖИМІ (ТІРІ КАРТИНА)
                 ======================================================== -->
            <div class="forest-view" id="forestView">
                <!-- Артқы күн сәулесі мен шуағы -->
                <div class="forest-sun"></div>
                <div class="forest-god-rays"></div>

                <!-- Төменгі шөпті алаңқай -->
                <div class="forest-meadow-floor"></div>

                <!-- Контейнер для динамических деревьев родов (Керей, Жағалбайлы, Қоңырат) -->
                <div id="forestTreesContainer"></div>
            </div>

        </div>
    </div>

    <!-- ========================================================
         3. «ТІЗІМ / СПИСОК» РЕЖИМІ (ТАБЛИЦА С ФИЛЬТРАМИ И ПОИСКОМ)
         ======================================================== -->
    <div class="list-view-container" id="listViewContainer" style="display:none;">
        <div class="list-toolbar">
            <input type="text" id="listSearchInput" class="list-search-input" placeholder="Аты-жөнін немесе туған жылын іздеу..." oninput="handleListFilterChange()">
            <select id="listClanFilter" class="list-select-filter" onchange="handleListFilterChange()">
                <option value="0">Барлық рулар / Все роды</option>
            </select>
            <select id="listGenderFilter" class="list-select-filter" onchange="handleListFilterChange()">
                <option value="all">Жынысы: Барлығы / Пол: Все</option>
                <option value="M">Ер / Мужской</option>
                <option value="F">Әйел / Женский</option>
            </select>
            <select id="listStatusFilter" class="list-select-filter" onchange="handleListFilterChange()">
                <option value="all">Күйі: Барлығы / Статус: Все</option>
                <option value="living">Тірі ұрпақтар / Живые</option>
                <option value="deceased">Ұшқан бабалар / Ушедшие</option>
            </select>
            <select id="listSortFilter" class="list-select-filter" onchange="handleListFilterChange()">
                <option value="name_asc">Сұрыптау: А-Я / Сортировка: А-Я</option>
                <option value="name_desc">Сұрыптау: Я-А / Сортировка: Я-А</option>
                <option value="birth_asc">Туған жылы бойынша (өсу) / По году рождения (возр.)</option>
                <option value="birth_desc">Туған жылы бойынша (кему) / По году рождения (убыв.)</option>
                <option value="id_asc">ID бойынша / По ID</option>
            </select>
            <div class="list-stats-count" id="listStatsCount">Табылды: 0 тұлға</div>
        </div>

        <div class="list-table-wrapper">
            <table class="list-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Тұлға / Персона</th>
                        <th style="width:80px;">Жынысы</th>
                        <th style="width:110px;">Руы</th>
                        <th style="width:140px;">Өмір сүрген жылдары</th>
                        <th>Ата-анасы</th>
                        <th>Жұбайы</th>
                        <th>Үш жұрт</th>
                        <th style="width:130px;">Әрекет</th>
                    </tr>
                </thead>
                <tbody id="listTableBody">
                    <!-- Деректер JavaScript арқылы толтырылады -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- МАСШТАБТАУ ПАНЕЛІ -->
    <div class="zoom-controls">
        <button class="zoom-btn" onclick="zoomIn()" title="Жақындату / Приблизить">+</button>
        <span class="zoom-val" id="zoomLevel">100%</span>
        <button class="zoom-btn" onclick="zoomOut()" title="Алыстату / Отдалить">−</button>
        <button class="zoom-btn" onclick="resetZoom()" title="Бастапқы күй / Сброс">⟲</button>
    </div>

    <!-- ШАРТТЫ БЕЛГІЛЕР ЛЕГЕНДАСЫ -->
    <div class="tree-legend">
        <div class="legend-row">
            <span class="legend-dot" style="background:#2563eb;"></span>
            <span data-i18n="legend_male">Ер азамат (бұтақ / карточка)</span>
        </div>
        <div class="legend-row">
            <span class="legend-dot" style="background:#ec4899;"></span>
            <span data-i18n="legend_female">Әйел адам / қыз (гүл)</span>
        </div>
        <div class="legend-row">
            <span class="legend-dot" style="background:#d97706; box-shadow: 0 0 6px rgba(217,119,6,0.8);"></span>
            <span data-i18n="legend_deceased">Ұшқан бабалар (алтын естелік реңкі)</span>
        </div>
        <div class="legend-row">
            <span class="legend-dot" style="background:#22c55e;"></span>
            <span data-i18n="legend_living">Тірі ұрпақтар (жасыл жапырақ / өркен)</span>
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
            <!-- Динамикалық туыстық анықтамасы -->
            <div class="kin-callout" id="panelKinshipCallout">
                Жүктелуде...
            </div>

            <!-- Тұлға туралы ақпарат -->
            <div class="profile-kv">
                <div class="profile-kv-row">
                    <span class="profile-kv-label" data-i18n="profile_gender">Жынысы:</span>
                    <span class="profile-kv-val" id="panelGender">—</span>
                </div>
                <div class="profile-kv-row">
                    <span class="profile-kv-label" data-i18n="profile_years">Өмір сүрген жылдары:</span>
                    <span class="profile-kv-val" id="panelYears">—</span>
                </div>
                <div class="profile-kv-row">
                    <span class="profile-kv-label" data-i18n="profile_place">Туған жері:</span>
                    <span class="profile-kv-val" id="panelPlace">—</span>
                </div>
                <div class="profile-kv-row">
                    <span class="profile-kv-label" data-i18n="profile_status">Күйі:</span>
                    <span class="profile-kv-val" id="panelStatus">—</span>
                </div>
            </div>

            <!-- Өмірбаяны -->
            <div>
                <div style="font-size:12px; color:#94a3b8; margin-bottom:6px; font-weight:700;" data-i18n="profile_biography_title">ӨМІРБАЯНЫ МЕН ТАРИХЫ:</div>
                <div class="bio-box" id="panelBiography">—</div>
            </div>

            <div style="display:flex; flex-direction:column; gap:8px; margin-top:14px;">
                <button type="button" class="btn-profile-action" onclick="setFocalFromProfile()" style="background:linear-gradient(135deg, #d97706, #b45309); color:#fff; border:none; padding:10px 14px; border-radius:8px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 2px 8px rgba(217,119,6,0.3);">
                    <span>🎯</span> <span data-i18n="btn_focus_here">Осы адамға бағыттау / Фокус</span>
                </button>
                <button type="button" class="btn-profile-action" id="btnSetMyPerson" onclick="setMyPersonFromProfile()" style="background:linear-gradient(135deg, #2563eb, #1d4ed8); color:#fff; border:none; padding:10px 14px; border-radius:8px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 2px 8px rgba(37,99,235,0.3);">
                    <span>★</span> <span data-i18n="btn_set_me">Бұл мен (Өз аккаунтыма байлау)</span>
                </button>
                <button type="button" class="btn-profile-action" onclick="openZhetiAtaTreeModal(selectedPersonId)" style="background:linear-gradient(135deg, #1e3a8a, #1d4ed8); color:#fff; border:none; padding:10px 14px; border-radius:8px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 2px 8px rgba(30,58,138,0.3);">
                    <span>📜</span> <span>Жеті атасын көру / тексеру</span>
                </button>
                <button type="button" class="btn-modal-submit" style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); color:#f8fafc;" onclick="openAddModal('child', selectedPersonId)">
                    + <span data-i18n="btn_add_child">Бала қосу</span>
                </button>
            </div>

            <!-- ҮН ЖАЗБАЛАРЫ, ЕСТЕЛІКТЕР ЖӘНЕ ТАРИХИ ФОТОСУРЕТТЕР (TASK-004) -->
            <div style="margin-top:20px; border-top:1px solid rgba(255,255,255,0.12); padding-top:14px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <div style="font-size:12.5px; color:#fef08a; font-weight:700; letter-spacing:0.5px;">
                        🎙️ ДАУЫС ЖӘНЕ ЕСТЕЛІКТЕР:
                    </div>
                    <button type="button" onclick="openAddMediaModal(selectedPersonId)" style="background:rgba(212,175,55,0.2); border:1px solid #d4af37; color:#fef08a; padding:4px 10px; border-radius:6px; font-size:11.5px; font-weight:600; cursor:pointer; transition:all 0.2s ease;">
                        + Жазба қосу
                    </button>
                </div>
                <div id="panelMediaContainer" style="display:flex; flex-direction:column; gap:10px; font-size:13px;">
                    <div style="color:#94a3b8; font-size:12px; font-style:italic;">Жүктелуде...</div>
                </div>
            </div>
        </div>
    </aside>


    <!-- МОДАЛЬДЫ ТЕРЕЗЕ: ТҰЛҒАНЫ ТОЛЫҚ ӨҢДЕУ ЖӘНЕ АУДИТ-ЛОГТАР -->
    <div class="modal-overlay" id="editPersonModal">
        <div class="modal-card" style="width:720px; max-width:95vw;">
            <div class="modal-header">
                <div>
                    <h3 id="modalEditTitle">Тұлға деректерін өңдеу</h3>
                    <div style="font-size:12px; color:#94a3b8;" id="modalEditSubtitle">Барлық түзетулер жүйеде тіркеліп, деректер қорында сақталады</div>
                </div>
                <button class="close-panel-btn" onclick="closeEditPersonModal()">✕</button>
            </div>
            <form id="editPersonForm" onsubmit="handleSavePersonEdit(event)">
                <div class="modal-body" style="max-height:70vh; overflow-y:auto;">
                    <input type="hidden" id="editPersonId" value="">

                    <div class="form-row-2">
                        <div class="form-group">
                            <label data-i18n="form_first_name">Аты *</label>
                            <input type="text" class="form-input" id="editFirstName" required>
                        </div>
                        <div class="form-group">
                            <label data-i18n="form_last_name">Тегі (Фамилия)</label>
                            <input type="text" class="form-input" id="editLastName">
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label data-i18n="form_middle_name">Әкесінің аты</label>
                            <input type="text" class="form-input" id="editMiddleName">
                        </div>
                        <div class="form-group" id="editMaidenNameGroup">
                            <label data-i18n="form_maiden_name">Қыз кезіндегі тегі (Девичья)</label>
                            <input type="text" class="form-input" id="editMaidenName">
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label data-i18n="form_gender">Жынысы *</label>
                            <select class="form-select" id="editGender" required>
                                <option value="M" data-i18n="gender_male">Ер азамат</option>
                                <option value="F" data-i18n="gender_female">Әйел адам</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Руы (Шежіре желісі)</label>
                            <input type="text" class="form-input" id="editClanRu" placeholder="Мысалы: Керей, Жағалбайлы, Қоңырат...">
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label data-i18n="form_birth_year">Туған жылы</label>
                            <input type="text" class="form-input" id="editBirthYear" placeholder="Мысалы: 1985">
                        </div>
                        <div class="form-group">
                            <label>Туған нақты күні (КК.АА.ЖЖЖЖ)</label>
                            <input type="text" class="form-input" id="editBirthDate" placeholder="Мысалы: 12.05.1985">
                        </div>
                    </div>

                    <div class="form-group">
                        <label data-i18n="form_birth_place">Туған жері</label>
                        <input type="text" class="form-input" id="editBirthPlace" placeholder="Мысалы: ШҚО, Үржар ауданы">
                    </div>

                    <div style="background:rgba(255,255,255,0.02); padding:12px; border-radius:10px; border:1px solid #334155; margin:12px 0;">
                        <label class="checkbox-label" style="margin-bottom:8px;">
                            <input type="checkbox" id="editIsDeceased" onchange="toggleEditDeathInputs()">
                            <span data-i18n="form_is_deceased_checkbox">Қайтыс болған / Ушёл из жизни</span>
                        </label>

                        <div id="editDeathSection" style="display:none; margin-top:10px;">
                            <div class="form-row-2">
                                <div class="form-group">
                                    <label data-i18n="form_death_year">Қайтыс болған жылы</label>
                                    <input type="text" class="form-input" id="editDeathYear" placeholder="Мысалы: 1972">
                                </div>
                                <div class="form-group">
                                    <label>Қайтыс болған күні</label>
                                    <input type="text" class="form-input" id="editDeathDate" placeholder="Мысалы: 15.08.1972">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Дүниеден өткен жері</label>
                                <input type="text" class="form-input" id="editDeathPlace" placeholder="Мысалы: Алматы қ.">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label data-i18n="form_biography">Өмірбаяны мен естеліктер</label>
                        <textarea class="form-textarea" id="editBiography" rows="3" placeholder="Өмірбаяны, естеліктері, еңбек жолы..."></textarea>
                    </div>

                    <!-- Аудит-лог тарихы -->
                    <div class="audit-log-section" id="editAuditSection">
                        <div class="audit-log-title">📜 Өзгерістер тарихы (Audit Log)</div>
                        <div id="editAuditLogContainer">
                            <span style="color:#94a3b8; font-size:12px;">Жүктелуде...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <button type="button" class="btn-modal-cancel" id="btnDeletePerson" onclick="handleDeleteCurrentPerson()" style="background:rgba(239,68,68,0.15); border:1px solid rgba(239,68,68,0.3); color:#fca5a5; display:none;">
                            🗑️ Жою / Удалить
                        </button>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <button type="button" class="btn-modal-cancel" onclick="closeEditPersonModal()" data-i18n="btn_cancel">Болдырмау</button>
                        <button type="submit" class="btn-modal-submit" data-i18n="btn_save">Сақтау</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- МОДАЛЬДЫ ТЕРЕЗЕ: НЕКЕ ЖӘНЕ АЖЫРАСУ ДЕРЕКТЕРІН ӨҢДЕУ -->
    <div class="modal-overlay" id="marriageModal">
        <div class="modal-card" style="width:520px;">
            <div class="modal-header">
                <div>
                    <h3>💍 Некелік одақ мәліметтері</h3>
                    <div style="font-size:12px; color:#94a3b8;" id="marriageModalSubtitle">Супружеский союз / Развод</div>
                </div>
                <button class="close-panel-btn" onclick="closeMarriageModal()">✕</button>
            </div>
            <form id="marriageForm" onsubmit="handleSaveMarriage(event)">
                <div class="modal-body">
                    <input type="hidden" id="marriageId" value="">

                    <div style="background:rgba(255,255,255,0.03); border:1px solid #334155; border-radius:10px; padding:12px; margin-bottom:14px; font-size:13px; color:#f8fafc;">
                        <div id="marriageCoupleNames" style="font-weight:700; color:#fef08a; margin-bottom:4px;">—</div>
                        <div style="font-size:11.5px; color:#94a3b8;">Неке жағдайы мен мерзімін өзгерту</div>
                    </div>

                    <div class="form-group">
                        <label>Неке күйі (Статус брака) *</label>
                        <select class="form-select" id="marriageStatus" required>
                            <option value="married">Үйленген / Замужем (В браке) 💍</option>
                            <option value="divorced">Ажырасқан (В разводе) 💔</option>
                            <option value="widowed">Тұл / Вдовец/вдова</option>
                        </select>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label>Неке басталған жылы/күні</label>
                            <input type="text" class="form-input" id="marriageStartDate" placeholder="Мысалы: 2008">
                        </div>
                        <div class="form-group">
                            <label>Аяқталған / Ажырасқан жылы</label>
                            <input type="text" class="form-input" id="marriageEndDate" placeholder="Мысалы: 2016">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Ескертпелер (Примечания)</label>
                        <input type="text" class="form-input" id="marriageNotes" placeholder="Қосымша мәліметтер...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" onclick="closeMarriageModal()" data-i18n="btn_cancel">Болдырмау</button>
                    <button type="submit" class="btn-modal-submit" data-i18n="btn_save">Сақтау</button>
                </div>
            </form>
        </div>
    </div>
    <!-- МОДАЛЬДЫ ТЕРЕЗЕ: АДАМ ҚОСУ (РЕЖИМ ӨҢДЕУ) -->
    <div class="modal-overlay" id="addPersonModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="modalAddTitle">Адам қосу</h3>
                <button class="close-panel-btn" onclick="closeAddModal()">✕</button>
            </div>
            <form id="addPersonForm" onsubmit="handleSavePerson(event)">
                <div class="modal-body">
                    <input type="hidden" id="formRelationType" value="">
                    <input type="hidden" id="formBasePersonId" value="">

                    <div class="form-row-2">
                        <div class="form-group">
                            <label data-i18n="form_first_name">Аты *</label>
                            <input type="text" class="form-input" id="formFirstName" required placeholder="Мысалы: Арнұр">
                        </div>
                        <div class="form-group">
                            <label data-i18n="form_last_name">Тегі (Фамилия)</label>
                            <input type="text" class="form-input" id="formLastName" placeholder="Мысалы: Алтыбаев">
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label data-i18n="form_middle_name">Әкесінің аты</label>
                            <input type="text" class="form-input" id="formMiddleName" placeholder="Мысалы: Жандосұлы">
                        </div>
                        <div class="form-group" id="maidenNameGroup">
                            <label data-i18n="form_maiden_name">Қыз кезіндегі тегі (Девичья)</label>
                            <input type="text" class="form-input" id="formMaidenName" placeholder="Мысалы: Сейітқызы">
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label data-i18n="form_gender">Жынысы *</label>
                            <select class="form-select" id="formGender" required>
                                <option value="M" data-i18n="gender_male">Ер азамат</option>
                                <option value="F" data-i18n="gender_female">Әйел адам</option>
                            </select>
                        </div>
                        <div class="form-group" style="display:flex; align-items:flex-end;">
                            <label class="checkbox-label" style="margin-bottom:8px;">
                                <input type="checkbox" id="formIsDeceased" onchange="toggleDeathInputs()">
                                <span data-i18n="form_is_deceased_checkbox">Қайтыс болған / Ушёл из жизни</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label data-i18n="form_birth_year">Туған жылы</label>
                            <input type="text" class="form-input" id="formBirthYear" placeholder="Мысалы: 1985">
                        </div>
                        <div class="form-group" id="deathYearGroup" style="display:none;">
                            <label data-i18n="form_death_year">Қайтыс болған жылы</label>
                            <input type="text" class="form-input" id="formDeathYear" placeholder="Мысалы: 1972">
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label data-i18n="form_birth_place">Туған жері</label>
                            <input type="text" class="form-input" id="formBirthPlace" placeholder="Мысалы: Алматы қ.">
                        </div>
                        <div class="form-group">
                            <label>Руы (Шежіре тегі)</label>
                            <input type="text" class="form-input" id="formClanRu" placeholder="Мысалы: Керей, Жағалбайлы...">
                        </div>
                    </div>

                    <div class="form-group">
                        <label data-i18n="form_biography">Өмірбаяны мен естеліктер</label>
                        <textarea class="form-textarea" id="formBiography" rows="3" placeholder="Қысқаша естелік немесе тарихы..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" onclick="closeAddModal()" data-i18n="btn_cancel">Болдырмау</button>
                    <button type="submit" class="btn-modal-submit" data-i18n="btn_save">Сақтау</button>
                </div>
            </form>
        </div>
    </div>

    <!-- МОДАЛЬДЫ ТЕРЕЗЕ: ШЕЖІРЕ-ОРМАН АРТ-ТҰЖЫРЫМДАМАСЫ (ЭСКИЗ ЖАНДОСҚА) -->
    <div class="modal-overlay" id="artModal">
        <div class="art-modal-card">
            <div class="modal-header">
                <div>
                    <h3 data-i18n="art_modal_title">🎨 «Шежіре-Орман» көркем тұжырымдамасы (Арт-эскиз)</h3>
                    <div style="font-size:12px; color:#94a3b8; margin-top:2px;" data-i18n="art_modal_sub">
                        Жандос пен оның әкесіне арналған көркем шежіре тұжырымдамасы
                    </div>
                </div>
                <button class="close-panel-btn" onclick="closeArtModal()">✕</button>
            </div>
            <div class="modal-body">
                <div class="art-image-wrap">
                    <img src="<?= htmlspecialchars($conceptArt) ?>" alt="Шежіре-Орман көркем тұжырымдамасы">
                </div>
                <div style="font-size:13.5px; color:#cbd5e1; line-height:1.6; background:rgba(255,255,255,0.03); padding:16px; border-radius:10px; border:1px solid rgba(212,175,55,0.2);">
                    <p style="margin-bottom:8px;">
                        <strong style="color:#fef08a;" data-i18n="art_desc_title">🌳 Көркем Шежіре-Орман мағынасы:</strong>
                    </p>
                    <ul style="padding-left:20px; display:flex; flex-direction:column; gap:6px;">
                        <li data-i18n="art_desc_1"><strong>Әр ру — өз ағашы:</strong> Ортада қасиетті Керей бабадан өрбіген Алтыбаевтар ағашы, жанында құдалар рулары (Жағалбайлы, Қоңырат).</li>
                        <li data-i18n="art_desc_2"><strong>Тірілер жоғарыда (Крона):</strong> Древо төменгі тамырдан емес, жоғарыдан — қазіргі тірі ұрпақтар мен балалардан басталады.</li>
                        <li data-i18n="art_desc_3"><strong>Семьялар — бұтақтар:</strong> Үйленген ұл бұтақ болып өседі, оның басында жұбайы екеуі отырады.</li>
                        <li data-i18n="art_desc_4"><strong>Ұлдар — жапырақтар (🍃):</strong> Әлі шаңырақ көтермеген ұлдар жас өркен-жапырақ болып өседі.</li>
                        <li data-i18n="art_desc_5"><strong>Қыздар — гүлдер (🌸):</strong> Барлық арулар мен қыздар шежіреде заңды әрі әсем орын алады; ұзатылғанда күйеуінің ағашына гүл болып ұшып барады.</li>
                        <li data-i18n="art_desc_6"><strong>Ұшқан бабалар — күзгі алтын реңк:</strong> Дүниеден өткен аруақтар сұр түспен емес, жылы естелік алтын түспен бейнеленеді.</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-modal-submit" onclick="closeArtModal()" data-i18n="btn_close">Түсінікті, жабу</button>
            </div>
        </div>
    </div>

    <!-- МОДАЛЬДЫ ТЕРЕЗЕ: ҚАЗАҚТЫҢ ТУЫСТЫҚ АТАУЛАРЫ СӨЗДІГІ -->
    <div class="modal-overlay" id="kinshipDictModal">
        <div class="modal-card" style="width:680px;">
            <div class="modal-header">
                <div>
                    <h3 data-i18n="dict_modal_title">📖 Қазақтың туыстық атаулары анықтамалығы</h3>
                    <div style="font-size:12px; color:#94a3b8;" data-i18n="dict_modal_sub">Өз жұрты, Нағашы жұрты және Қайын жұрты атаулары</div>
                </div>
                <button class="close-panel-btn" onclick="closeKinshipDictionaryModal()">✕</button>
            </div>
            <div class="modal-body" style="max-height:65vh;">
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <?php foreach ($kinshipTerms as $key => $term): ?>
                        <div style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07); padding:10px 14px; border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <span style="font-weight:700; color:#fef08a; font-size:14px;"><?= htmlspecialchars($term['kk']) ?></span>
                                <span style="color:#94a3b8; font-size:12.5px; margin-left:8px;">(<?= htmlspecialchars($term['ru']) ?>)</span>
                                <div style="font-size:11.5px; color:#cbd5e1; margin-top:2px;"><?= htmlspecialchars($term['desc']) ?></div>
                            </div>
                            <span style="font-size:10.5px; padding:2px 7px; border-radius:8px; background:rgba(212,175,55,0.15); color:#d4af37; border:1px solid rgba(212,175,55,0.3); white-space:nowrap;">
                                <?= htmlspecialchars($term['line'] ?? 'туыстық') ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-modal-submit" onclick="closeKinshipDictionaryModal()" data-i18n="btn_close">Жабу</button>
            </div>
        </div>
    </div>

    <!-- МОДАЛЬДЫ ТЕРЕЗЕ: ҚАЗАҚТЫҢ «ЖЕТІ АТА» САЛТЫН ТЕКСЕРУ (TASK-004) -->
    <div class="modal-overlay" id="zhetiAtaModal">
        <div class="modal-card" style="width:780px; max-width:95vw;">
            <div class="modal-header">
                <div>
                    <h3>📜 Қазақтың «Жеті ата» қағидасын тексеру</h3>
                    <div style="font-size:12px; color:#94a3b8;">Екі адам арасында әкелер желісі бойынша 7 буынды есептеу</div>
                </div>
                <button class="close-panel-btn" onclick="closeZhetiAtaTreeModal()">✕</button>
            </div>
            <div class="modal-body" style="max-height:75vh;">
                <div style="background:rgba(255,255,255,0.03); border:1px solid #334155; border-radius:10px; padding:12px 16px; margin-bottom:16px; font-size:13px; color:#cbd5e1; line-height:1.5;">
                    Қазақ халқының салты бойынша бір атадан тараған жеті атаға дейінгі ұрпақтар бір-бірімен қыз алысып, үйленуіне тыйым салынған. 7 буыннан кейін ғана ақсақалдар батасымен құда болуға рұқсат беріледі.
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:16px;">
                    <div class="form-group">
                        <label>1-ші адам (А тұлғасы):</label>
                        <select class="form-select" id="zhetiAtaPersonA">
                            <option value="">Жүктелуде...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>2-ші адам (Б тұлғасы):</label>
                        <select class="form-select" id="zhetiAtaPersonB">
                            <option value="">Жүктелуде...</option>
                        </select>
                    </div>
                </div>

                <div style="text-align:center; margin-bottom:18px;">
                    <button type="button" class="btn-modal-submit" onclick="runTreeZhetiAtaCheck()" style="padding:10px 28px; font-size:14px;">
                        🔍 Жеті атаны тексеру
                    </button>
                </div>

                <div id="zhetiAtaResultBox" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="closeZhetiAtaTreeModal()">Жабу</button>
            </div>
        </div>
    </div>

    <!-- МОДАЛЬДЫ ТЕРЕЗЕ: ДАУЫС (АУДИО), ЕСТЕЛІК ЖӘНЕ ФОТО ҚОСУ (TASK-004) -->
    <div class="modal-overlay" id="addMediaModal">
        <div class="modal-card" style="width:620px; max-width:95vw;">
            <div class="modal-header">
                <div>
                    <h3 id="addMediaModalTitle">🎙️ Жаңа дауыс немесе естелік қосу</h3>
                    <div style="font-size:12px; color:#94a3b8;" id="addMediaModalSub">Тұлғаның өмірбаяны, ақсақалдың әңгімесі мен архивтік фотосы</div>
                </div>
                <button class="close-panel-btn" onclick="closeAddMediaModal()">✕</button>
            </div>
            <form id="addMediaForm" onsubmit="handleSaveMedia(event)">
                <input type="hidden" id="mediaPersonId" value="">
                
                <div class="modal-body" style="max-height:70vh;">
                    <!-- Переключатель типа медиа -->
                    <div style="display:flex; gap:10px; margin-bottom:16px;">
                        <button type="button" id="tabMediaAudio" class="lang-btn active" style="flex:1; padding:8px;" onclick="setMediaTab('audio')">
                            🎙️ Аудио жазба
                        </button>
                        <button type="button" id="tabMediaStory" class="lang-btn" style="flex:1; padding:8px;" onclick="setMediaTab('story')">
                            📝 Текстік естелік
                        </button>
                        <button type="button" id="tabMediaPhoto" class="lang-btn" style="flex:1; padding:8px;" onclick="setMediaTab('photo')">
                            📷 Архивтік фото
                        </button>
                    </div>

                    <div class="form-group">
                        <label>Тақырыбы (Атауы) *</label>
                        <input type="text" class="form-input" id="mediaTitle" placeholder="Мысалы: 1982 жылғы әкенің естелігі" required>
                    </div>

                    <!-- Поле файла (для аудио и фото) -->
                    <div class="form-group" id="mediaFileGroup">
                        <label id="mediaFileLabel">Файлды таңдаңыз (MP3, M4A, WAV) *</label>
                        <input type="file" class="form-input" id="mediaFileInput" accept="audio/*">
                        <div style="font-size:11px; color:#94a3b8; margin-top:3px;" id="mediaFileHint">
                            Қауіпсіз жабық қоймада сақталады (Git-ке түспейді, тек сайтқа кіргендерге қолжетімді)
                        </div>
                    </div>

                    <!-- Поле текста истории (для story) -->
                    <div class="form-group" id="mediaStoryGroup" style="display:none;">
                        <label>Естелік мәтіні (Хикая, шежірелік деректер) *</label>
                        <textarea class="form-textarea" id="mediaStoryContent" rows="6" placeholder="Ақсақалдың айтқан сөздері, естеліктері, өмір жолы туралы деректер..."></textarea>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label>Кім айтып берді (Айтушы / Автор)</label>
                            <input type="text" class="form-input" id="mediaRecordedBy" placeholder="Мысалы: Ғабит ақсақал">
                        </div>
                        <div class="form-group">
                            <label>Оқиға немесе жазба жылы</label>
                            <input type="text" class="form-input" id="mediaEventYear" placeholder="Мысалы: 1985">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" onclick="closeAddMediaModal()">Болдырмау</button>
                    <button type="submit" class="btn-modal-submit" id="btnSubmitMedia">Сақтау</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================
         JAVASCRIPT ЛОГИКАСЫ МЕН ДИНАМИКАЛЫҚ ҚҰРЫЛЫМ
         ======================================================== -->
    <script>
        // СЕРВЕРДЕН АЛЫНҒАН АҚПАРАТТАР
        let userPersonId = <?= (int)$userPersonId ?>;
        let currentMode = 'editing'; // 'editing' немесе 'forest'
        let currentScope = 'focal'; // 'focal' (окрестность) немесе 'full' (барлық шежіре 270)
        let currentLang = localStorage.getItem('shezhire_lang') || 'kk';

        let treeData = null;
        let selectedPersonId = userPersonId;
        let activeKinships = {}; // person_id -> { term_kk, term_ru, code, badge_class }
        let expandedBranches = []; // ашылған тармақтардың ID тізімі

        // Масштабтау және жылжыту координаталары
        let scale = 1.0;
        let posX = 0;
        let posY = 0;
        let isDragging = false;
        let startX = 0;
        let startY = 0;

        const viewport = document.getElementById('canvasViewport');
        const world = document.getElementById('treeWorld');

        // ТІЛДІК АУДАРМАЛАР СӨЗДІГІ (I18N)
        const i18n = {
            kk: {
                back_to_portal: "Басты бет",
                brand_title_main: "Шежіре",
                brand_title_sub: "Алтыбаевтар",
                mode_editing: "Өңдеу",
                mode_forest: "Шежіре-Орман", mode_list: "Список", btn_edit_person: "Редактировать", form_is_deceased_checkbox: "Ушёл из жизни", mode_list: "Тізім", btn_edit_person: "Өңдеу", form_is_deceased_checkbox: "Қайтыс болған / Ушёл из жизни",
                search_placeholder: "Адамды іздеу...",
                btn_you_are_here: "Сіз осындасыз",
                btn_art_concept: "Арт-эскиз",
                btn_kinship_dict: "Сөздік",
                legend_male: "Ер азамат (бұтақ / карточка)",
                legend_female: "Әйел адам / қыз (гүл)",
                legend_deceased: "Ұшқан бабалар (алтын естелік реңкі)",
                legend_living: "Тірі ұрпақтар (жасыл жапырақ / өркен)",
                profile_gender: "Жынысы:",
                profile_years: "Өмір сүрген жылдары:",
                profile_place: "Туған жері:",
                profile_status: "Күйі:",
                profile_biography_title: "ӨМІРБАЯНЫ МЕН ТАРИХЫ:",
                btn_add_child: "Бала қосу",
                btn_cancel: "Болдырмау",
                btn_save: "Сақтау",
                btn_close: "Жабу",
                form_first_name: "Аты *",
                form_last_name: "Тегі (Фамилия)",
                form_middle_name: "Әкесінің аты",
                form_maiden_name: "Қыз кезіндегі тегі (Девичья)",
                form_gender: "Жынысы *",
                gender_male: "Ер азамат",
                gender_female: "Әйел адам",
                form_is_deceased: "Күйі",
                status_living: "Тірі",
                status_deceased: "Дүниеден өткен",
                form_birth_year: "Туған жылы",
                form_death_year: "Қайтыс болған жылы",
                form_birth_place: "Туған жері",
                form_biography: "Өмірбаяны мен естеліктер",
                art_modal_title: "🎨 «Шежіре-Орман» көркем тұжырымдамасы (Арт-эскиз)",
                art_modal_sub: "Жандос пен оның әкесіне арналған көркем шежіре тұжырымдамасы",
                art_desc_title: "🌳 Көркем Шежіре-Орман мағынасы:",
                art_desc_1: "Әр ру — өз ағашы: Ортада қасиетті Керей бабадан өрбіген Алтыбаевтар ағашы, жанында құдалар рулары (Жағалбайлы, Қоңырат).",
                art_desc_2: "Тірілер жоғарыда (Крона): Древо төменгі тамырдан емес, жоғарыдан — қазіргі тірі ұрпақтар мен балалардан басталады.",
                art_desc_3: "Семьялар — бұтақтар: Үйленген ұл бұтақ болып өседі, оның басында жұбайы екеуі отырады.",
                art_desc_4: "Ұлдар — жапырақтар (🍃): Әлі шаңырақ көтермеген ұлдар жас өркен-жапырақ болып өседі.",
                art_desc_5: "Қыздар — гүлдер (🌸): Барлық арулар мен қыздар шежіреде заңды әрі әсем орын алады; ұзатылғанда күйеуінің ағашына гүл болып ұшып барады.",
                art_desc_6: "Ұшқан бабалар — күзгі алтын реңк: Дүниеден өткен аруақтар сұр түспен емес, жылы естелік алтын түспен бейнеленеді.",
                dict_modal_title: "📖 Қазақтың туыстық атаулары анықтамалығы",
                dict_modal_sub: "Өз жұрты, Нағашы жұрты және Қайын жұрты атаулары",
                clan_main_tree: "🌳 Керей — Алтыбаевтар ағашы",
                clan_zhagalbayly_tree: "🌳 Жағалбайлы ағашы (Аналарымыздың руы)",
                clan_konyrat_tree: "🌳 Қоңырат ағашы (Құдалар руы)",
                crown_title: "🍃 Крона — Тірі ұрпақтар мен балалар",
                trunk_title: "🏛️ Тікелей негізгі діңгек (Түп аталар желісі)",
                root_title: "🌱 Қасиетті түпкі тамыр",
                married_away_arrow: "Қоңыратқа келін болып кетті ➔",
                married_in_tag: "Жағалбайлы қызы ↗",
                you_are_here_badge: "★ Сіз осындасыз",
                selected_badge: "Мен",
                empty_tree_title: "Деректер жоқ — GEDCOM импорттаңыз",
                empty_tree_desc: "Деректер қорында әлі тұлғалар жоқ. MyHeritage жүйесінен GEDCOM файлын импорттаңыз.",
                btn_import_gedcom: "📥 GEDCOM импорттау",
                btn_return_real: "🌳 Негізгі базаға оралу",
                scope_focal: "Окрестность",
                scope_full: "Барлық шежіре",
                btn_focus_here: "Осы адамға бағыттау / Фокус",
                btn_set_me: "★ Бұл мен (Өз аккаунтыма байлау)",
            },
            ru: {
                back_to_portal: "Главная страница",
                brand_title_main: "Шежире",
                brand_title_sub: "Алтыбаевых",
                mode_editing: "Редактирование",
                mode_forest: "Шежіре-Орман", mode_list: "Список", btn_edit_person: "Редактировать", form_is_deceased_checkbox: "Ушёл из жизни", mode_list: "Тізім", btn_edit_person: "Өңдеу", form_is_deceased_checkbox: "Қайтыс болған / Ушёл из жизни",
                search_placeholder: "Поиск родственника...",
                btn_you_are_here: "Вы здесь",
                btn_art_concept: "Арт-концепт",
                btn_kinship_dict: "Словарь родства",
                legend_male: "Мужчина (ветка / карточка)",
                legend_female: "Женщина / дочь (цветок)",
                legend_deceased: "Ушедшие предки (золотой тон памяти)",
                legend_living: "Живые поколения (зелёный лист / росток)",
                profile_gender: "Пол:",
                profile_years: "Годы жизни:",
                profile_place: "Место рождения:",
                profile_status: "Статус:",
                profile_biography_title: "БИОГРАФИЯ И ИСТОРИЯ:",
                btn_add_child: "Добавить ребенка",
                btn_cancel: "Отмена",
                btn_save: "Сохранить",
                btn_close: "Закрыть",
                form_first_name: "Имя *",
                form_last_name: "Фамилия",
                form_middle_name: "Отчество",
                form_maiden_name: "Девичья фамилия",
                form_gender: "Пол *",
                gender_male: "Мужской",
                gender_female: "Женский",
                form_is_deceased: "Статус",
                status_living: "Жив(а)",
                status_deceased: "Умер(ла)",
                form_birth_year: "Год рождения",
                form_death_year: "Год смерти",
                form_birth_place: "Место рождения",
                form_biography: "Биография и воспоминания",
                art_modal_title: "🎨 Художественный концепт «Шежіре-Орман» (Арт-эскиз)",
                art_modal_sub: "Художественная концепция живого леса шежире для Жандоса и его отца",
                art_desc_title: "🌳 Смысл живого леса Шежіре-Орман:",
                art_desc_1: "Каждый род — отдельное дерево: В центре святое древо Алтыбаевых рода Керей, рядом родственные роды (Жагалбайлы, Конырат).",
                art_desc_2: "Живые наверху (Крона): Древо открывается сверху — на живых потомках и детях, освещенное солнцем.",
                art_desc_3: "Семьи — ветки: Создав семью, сын становится крепкой ветвью, у основания которой их пара с супругой.",
                art_desc_4: "Сыновья — листья (🍃): До создания семьи сыновья растут молодыми свежими листьями.",
                art_desc_5: "Дочери — цветы (🌸): Женщины занимают законное почетное место в шежире; выходя замуж, цветок перелетает к древу мужа.",
                art_desc_6: "Ушедшие предки — золотые листья памяти: Предки не серые, а теплые золотисто-янтарные, оставаясь живой частью дерева.",
                dict_modal_title: "📖 Словарь казахских терминов родства",
                dict_modal_sub: "Родственники по отцу, по матери (нағашы) и со стороны супруги (қайын)",
                clan_main_tree: "🌳 Керей — Древо Алтыбаевых",
                clan_zhagalbayly_tree: "🌳 Древо Жағалбайлы (Род наших матерей)",
                clan_konyrat_tree: "🌳 Древо Қоңырат (Род сватов)",
                crown_title: "🍃 Крона — Живые поколения и дети",
                trunk_title: "🏛️ Прямой ствол (Единая линия предков)",
                root_title: "🌱 Священный корень",
                married_away_arrow: "Вышла замуж в род Конырат ➔",
                married_in_tag: "Дочь Жагалбайлы ↗",
                you_are_here_badge: "★ Вы здесь",
                selected_badge: "Я",
                empty_tree_title: "Данных нет — импортируйте GEDCOM",
                empty_tree_desc: "В базе данных пока нет персон. Импортируйте файл GEDCOM из MyHeritage.",
                btn_import_gedcom: "📥 Импортировать GEDCOM",
                btn_return_real: "🌳 Вернуться к реальной базе",
                scope_focal: "Окрестность",
                scope_full: "Все древо (270)",
                btn_focus_here: "Фокус на выбранном",
                btn_set_me: "★ Это я (Привязать аккаунт)",
            }
        };

        // ТІЛДІ ОРНАТУ ЖӘНЕ БАРЛЫҚ ЖАЗБАЛАРДЫ БІР СӘТТЕ АУЫСТЫРУ
        function setLanguage(lang) {
            currentLang = lang;
            localStorage.setItem('shezhire_lang', lang);
            document.cookie = `shezhire_lang=${lang}; path=/; max-age=31536000`;

            document.getElementById('langBtnKk').classList.toggle('active', lang === 'kk');
            document.getElementById('langBtnRu').classList.toggle('active', lang === 'ru');

            // Аудармаларды элементтерге енгізу
            document.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.getAttribute('data-i18n');
                if (i18n[lang] && i18n[lang][key]) {
                    el.innerText = i18n[lang][key];
                }
            });

            // Input placeholder
            document.getElementById('treeSearchInput').placeholder = i18n[lang].search_placeholder;

            // Егер мәліметтер жүктелген болса — қайта бейнелеу
            if (treeData) {
                if (currentMode === 'editing') {
                    renderEditingView();
                } else {
                    renderForestView();
                }
                updateAllKinshipBadges(selectedPersonId);
            }
        }

        // ========================================================
        // ДЕРЕКТЕРДІ СЕРВЕРДЕН ЖҮКТЕУ ЖӘНЕ БАСТАУ
        // ========================================================


        function updateScopeButtonState() {
            const bFocal = document.getElementById('btnScopeFocal');
            const bFull = document.getElementById('btnScopeFull');
            if (bFocal) bFocal.classList.toggle('active', currentScope === 'focal');
            if (bFull) bFull.classList.toggle('active', currentScope === 'full');
        }

        async function switchScope(scope) {
            currentScope = scope;
            updateScopeButtonState();

            try {
                const expParam = expandedBranches.length > 0 ? `&expanded=${expandedBranches.join(',')}` : '';
                const url = `/api/tree/layout?mode=${currentScope}&focal_id=${selectedPersonId || userPersonId || 0}${expParam}`;
                const res = await fetch(url);
                const data = await res.json();
                if (data && data.layout) {
                    treeData.layout = data.layout;
                    renderEditingView();
                    flyToPerson(selectedPersonId || userPersonId);
                }
            } catch (e) {
                console.error("Scope ауыстыру қатесі:", e);
            }
        }

        async function expandBranch(personId, type) {
            if (!expandedBranches.includes(personId)) {
                expandedBranches.push(personId);
            }
            try {
                const expParam = `&expanded=${expandedBranches.join(',')}`;
                const url = `/api/tree/layout?mode=${currentScope}&focal_id=${selectedPersonId || userPersonId || 0}${expParam}`;
                const res = await fetch(url);
                const data = await res.json();
                if (data && data.layout) {
                    treeData.layout = data.layout;
                    renderEditingView();
                }
            } catch (e) {
                console.error("Тармақты ашу қатесі:", e);
            }
        }

        async function setMyPersonFromProfile() {
            if (!selectedPersonId) return;
            try {
                const res = await fetch(`/api/user/set-my-person?person_id=${selectedPersonId}`);
                const data = await res.json();
                if (data && data.status === 'success') {
                    userPersonId = selectedPersonId;
                    updateAllKinshipBadges(selectedPersonId);
                    renderEditingView();
                    alert(currentLang === 'ru' 
                        ? 'Метка «Вы здесь» успешно привязана к выбранной персоне!' 
                        : '«Сіз осындасыз» белгісі осы тұлғаға сәтті орнатылды!');
                }
            } catch (e) {
                alert('Қате: ' + e.message);
            }
        }

        function setFocalFromProfile() {
            if (!selectedPersonId) return;
            switchScope('focal');
        }

        async function initTree() {
            setLanguage(currentLang);
            initEvents();

            try {
                const expParam = expandedBranches.length > 0 ? `&expanded=${expandedBranches.join(',')}` : '';
                const url = `/api/tree?mode=${currentScope}&focal_id=${selectedPersonId || userPersonId || 0}${expParam}`;
                const res = await fetch(url);
                const data = await res.json();
                treeData = data;
                if (data.user_person_id) {
                    userPersonId = data.user_person_id;
                }
                if (!selectedPersonId) {
                    selectedPersonId = userPersonId || data.focal_id;
                }
                activeKinships = data.initial_kinships || {};

                updateScopeButtonState();

                if (currentMode === 'editing') {
                    renderEditingView();
                } else if (currentMode === 'forest') {
                    renderForestView();
                } else if (currentMode === 'list') {
                    loadListViewData();
                }

                if (treeData.is_empty || !treeData.persons || treeData.persons.length === 0) {
                    // Центрируем камеру на карточке пустого состояния
                    posX = (viewport.clientWidth / 2) - 2000 * scale;
                    posY = (viewport.clientHeight / 2) - 600 * scale;
                    updateTransform();
                } else {
                    const targetId = selectedPersonId || userPersonId || (treeData.persons[0] ? treeData.persons[0].id : 1);
                    flyToPerson(targetId);
                }
            } catch (err) {
                console.error("Деректерді жүктеу қатесі:", err);
                treeData = { is_empty: true, error: err.message, persons: [], marriages: [], clans: [] };
                renderEditingView();
            }
        }

        // РЕЖИМДЕРДІ АУЫСТЫРУ («ӨҢДЕУ» <-> «ШЕЖІРЕ-ОРМАН» <-> «ТІЗІМ»)
        function switchMode(mode) {
            currentMode = mode;
            const btnEditing = document.getElementById('btnModeEditing');
            const btnForest = document.getElementById('btnModeForest');
            const btnList = document.getElementById('btnModeList');
            const scopeSwitcher = document.getElementById('scopeSwitcher');
            const viewEditing = document.getElementById('editingView');
            const viewForest = document.getElementById('forestView');
            const viewList = document.getElementById('listViewContainer');
            const canvas = document.getElementById('canvasViewport');
            const zoomControls = document.querySelector('.zoom-controls');
            const treeLegend = document.querySelector('.tree-legend');

            if (btnEditing) btnEditing.classList.toggle('active', mode === 'editing');
            if (btnForest) btnForest.classList.toggle('active', mode === 'forest');
            if (btnList) btnList.classList.toggle('active', mode === 'list');

            if (mode === 'editing') {
                if (canvas) canvas.style.display = 'block';
                if (viewList) viewList.style.display = 'none';
                if (viewEditing) viewEditing.style.display = 'block';
                if (viewForest) viewForest.style.display = 'none';
                if (scopeSwitcher) scopeSwitcher.style.display = 'flex';
                if (zoomControls) zoomControls.style.display = 'flex';
                if (treeLegend) treeLegend.style.display = 'flex';
                renderEditingView();
                if (treeData && treeData.persons && treeData.persons.length > 0) {
                    flyToPerson(selectedPersonId || userPersonId || treeData.persons[0].id);
                }
            } else if (mode === 'forest') {
                if (canvas) canvas.style.display = 'block';
                if (viewList) viewList.style.display = 'none';
                if (viewEditing) viewEditing.style.display = 'none';
                if (viewForest) viewForest.style.display = 'block';
                if (scopeSwitcher) scopeSwitcher.style.display = 'none';
                if (zoomControls) zoomControls.style.display = 'flex';
                if (treeLegend) treeLegend.style.display = 'flex';
                renderForestView();
                if (treeData && treeData.persons && treeData.persons.length > 0) {
                    flyToCrown();
                }
            } else if (mode === 'list') {
                if (canvas) canvas.style.display = 'none';
                if (viewList) viewList.style.display = 'block';
                if (scopeSwitcher) scopeSwitcher.style.display = 'none';
                if (zoomControls) zoomControls.style.display = 'none';
                if (treeLegend) treeLegend.style.display = 'none';
                loadListViewData();
            }
        }

        // ========================================================
        // 1. «ӨҢДЕУ» РЕЖИМІН ДИНАМИКАЛЫҚ ҚҰРУ (MYHERITAGE СТИЛІ, НӨЛ ҚИЫЛЫС)
        // ========================================================
        function renderEditingView() {
            const container = document.getElementById('editingCardsContainer');
            container.innerHTML = '';
            const svg = document.getElementById('svgConnections');
            if (svg) svg.innerHTML = '';

            if (!treeData || !treeData.persons || treeData.persons.length === 0) {
                const emptyCard = document.createElement('div');
                emptyCard.className = 'tree-empty-state';
                const isErr = treeData && treeData.status === 'error';
                const title = isErr 
                    ? (currentLang === 'ru' ? 'Ошибка базы данных' : 'Мәліметтер қоры қатесі')
                    : i18n[currentLang].empty_tree_title;
                const desc = isErr
                    ? (treeData.message || '')
                    : i18n[currentLang].empty_tree_desc;

                emptyCard.innerHTML = `
                    <div class="empty-icon">${isErr ? '⚠️' : '🌳'}</div>
                    <h2>${title}</h2>
                    <p>${desc}</p>
                    <div class="empty-actions">
                        <a href="/admin/import" class="btn-empty-action btn-import">${i18n[currentLang].btn_import_gedcom}</a>
                    </div>
                `;
                container.appendChild(emptyCard);
                return;
            }

            // Динамические размеры канваса и слоя связей SVG для предотвращения обрезки линий
            const bounds = (treeData.layout && treeData.layout.bounds) ? treeData.layout.bounds : null;
            const worldW = Math.max(7000, bounds ? (bounds.maxX + 3000) : 7000);
            const worldH = Math.max(5000, bounds ? (bounds.maxY + 2000) : 5000);
            if (world) {
                world.style.width = worldW + 'px';
                world.style.height = worldH + 'px';
            }
            if (svg) {
                svg.setAttribute('width', worldW);
                svg.setAttribute('height', worldH);
                svg.style.width = worldW + 'px';
                svg.style.height = worldH + 'px';
            }

            // 1. Шаңырақ ореолдары (Family Aura)
            if (treeData.layout && treeData.layout.auras) {
                treeData.layout.auras.forEach(aura => {
                    const auraEl = document.createElement('div');
                    auraEl.className = 'family-aura';
                    auraEl.style.left = `${aura.x}px`;
                    auraEl.style.top = `${aura.y}px`;
                    auraEl.style.width = `${aura.width}px`;
                    auraEl.style.height = `${aura.height}px`;
                    if (aura.label) {
                        const lbl = document.createElement('div');
                        lbl.className = 'family-aura-label';
                        lbl.innerText = aura.label;
                        auraEl.appendChild(lbl);
                    }
                    container.appendChild(auraEl);
                });
            }

            // 2. Жұбайлар біртұтас планкасы (Couple Unit Plate)
            if (treeData.layout && treeData.layout.couples) {
                treeData.layout.couples.forEach(cp => {
                    const plateEl = document.createElement('div');
                    plateEl.className = 'couple-unit-plate';
                    plateEl.style.left = `${cp.x}px`;
                    plateEl.style.top = `${cp.y}px`;
                    plateEl.style.width = `${cp.width}px`;
                    plateEl.style.height = `${cp.height}px`;
                    container.appendChild(plateEl);
                });
            }

            const layoutNodes = (treeData.layout && treeData.layout.nodes && treeData.layout.nodes.length > 0)
                ? treeData.layout.nodes
                : fallbackLayout(treeData.persons, treeData.marriages);

            layoutNodes.forEach(node => {
                const isMale = node.gender === 'M';
                const isDeceased = !!node.is_deceased;
                const isSelf = node.id === userPersonId;
                const isSelected = node.id === selectedPersonId;

                const card = document.createElement('div');
                card.className = `person-card ${isMale ? 'male' : 'female'} ${isDeceased ? 'deceased' : ''} ${isSelf ? 'is-self' : ''} ${isSelected ? 'selected' : ''}`;
                card.id = `card-${node.id}`;
                card.style.left = `${node.x}px`;
                card.style.top = `${node.y}px`;

                const initials = (node.first_name ? node.first_name[0] : '') + (node.last_name ? node.last_name[0] : '');

                // Туыстық атауын алу
                const kin = activeKinships[node.id] || { term_kk: 'Туысқан', term_ru: 'Родственник' };
                const kinLabel = currentLang === 'ru' ? (kin.term_ru || kin.term_kk) : kin.term_kk;

                // Батырмалар жазбалары
                const tFather = currentLang === 'ru' ? '+ Отец' : '+ Әке';
                const tMother = currentLang === 'ru' ? '+ Мать' : '+ Ана';
                const tSpouse = currentLang === 'ru' ? '+ Супруг(а)' : '+ Жұбай';
                const tSibling = currentLang === 'ru' ? '+ Брат/Сестра' : '+ Бауыр';
                const tSon = currentLang === 'ru' ? '+ Сын' : '+ Ұл';
                const tDaughter = currentLang === 'ru' ? '+ Дочь' : '+ Қыз';

                // Скрытые предки / потомки батырмалары
                let expandTopHtml = '';
                if (node.has_hidden_parents) {
                    const lbl = currentLang === 'ru' ? `▲ +${node.hidden_parents_count} предков` : `▲ +${node.hidden_parents_count} аталар`;
                    expandTopHtml = `<button class="btn-branch-expand btn-branch-top" onclick="event.stopPropagation(); expandBranch(${node.id}, 'parents')">${lbl}</button>`;
                }

                let expandBottomHtml = '';
                if (node.has_hidden_children) {
                    const lbl = currentLang === 'ru' ? `▼ +${node.hidden_children_count} потомков` : `▼ +${node.hidden_children_count} балалар`;
                    expandBottomHtml = `<button class="btn-branch-expand btn-branch-bottom" onclick="event.stopPropagation(); expandBranch(${node.id}, 'children')">${lbl}</button>`;
                }

                card.innerHTML = `
                    ${expandTopHtml}
                    <div class="card-kinship-badge ${isSelf ? 'self-rel' : (isSelected ? 'active-rel' : '')}" id="kin-badge-${node.id}">
                        <span>${isSelf && !isSelected ? i18n[currentLang].you_are_here_badge : (isSelected ? i18n[currentLang].selected_badge : kinLabel)}</span>
                        ${isDeceased ? '<span>✨</span>' : '<span>🌿</span>'}
                    </div>

                    <div class="card-header-inner">
                        <div class="card-avatar">${initials}</div>
                        <div class="card-info">
                            <div class="card-name" title="${node.first_name} ${node.last_name || ''}">${node.first_name} ${node.last_name || ''}</div>
                            ${node.maiden_name ? `<div class="card-maiden" style="font-size:11px; color:#f472b6;">(${currentLang === 'ru' ? 'урожд.' : 'қыз кезіндегі —'} ${node.maiden_name})</div>` : ''}
                            <div class="card-years">${isDeceased ? `${node.birth_year || '—'} — ${node.death_year || '†'} ✨` : `${node.birth_year || '—'}`}</div>
                        </div>
                    </div>
                    <div style="display:flex; gap:4px; align-items:center; flex-wrap:wrap; margin-top:2px;">
                        <span class="card-clan-tag">${node.clan_ru || (currentLang === 'ru' ? 'не указан' : 'белгісіз')}</span>
                        ${(() => {
                            const kin = activeKinships[node.id];
                            if (!kin || !kin.zhurt) return '';
                            let zCls = 'zhurt-oz';
                            if (kin.zhurt.includes('Нағашы')) zCls = 'zhurt-nagashy';
                            else if (kin.zhurt.includes('Қайын')) zCls = 'zhurt-kaiyn';
                            else if (kin.zhurt.includes('Төркін')) zCls = 'zhurt-torkin';
                            else if (kin.zhurt.includes('Күйеу')) zCls = 'zhurt-kuyeui';
                            else if (kin.zhurt.includes('Құда')) zCls = 'zhurt-quda';
                            return `<span class="card-zhurt-tag zhurt-pill ${zCls}">${kin.zhurt}</span>`;
                        })()}
                    </div>

                    <!-- Карточканың айналасындағы жылдам қосу радиалды батырмалары -->
                    <div class="radial-action-wrap">
                        <button class="radial-btn radial-top-father" data-tooltip="${tFather}" onclick="event.stopPropagation(); openAddModal('father', ${node.id})">+</button>
                        <button class="radial-btn radial-top-mother" data-tooltip="${tMother}" onclick="event.stopPropagation(); openAddModal('mother', ${node.id})">+</button>
                        <button class="radial-btn radial-left-spouse" data-tooltip="${tSpouse}" onclick="event.stopPropagation(); openAddModal('spouse', ${node.id})">+</button>
                        <button class="radial-btn radial-right-sibling" data-tooltip="${tSibling}" onclick="event.stopPropagation(); openAddModal('sibling', ${node.id})">+</button>
                        <button class="radial-btn radial-bottom-son" data-tooltip="${tSon}" onclick="event.stopPropagation(); openAddModal('son', ${node.id})">+</button>
                        <button class="radial-btn radial-bottom-daughter" data-tooltip="${tDaughter}" onclick="event.stopPropagation(); openAddModal('daughter', ${node.id})">+</button>
                    </div>
                    ${expandBottomHtml}
                `;

                card.onclick = (e) => {
                    e.stopPropagation();
                    selectPerson(node.id);
                };

                container.appendChild(card);
            });

            renderConnectionsEditing();
        }

        // БАЙЛАНЫС ЖЕЛІЛЕРІН СЫЗУ (СВЯЗИ ПОКОЛЕНИЙ И БРАКОВ: ОРТОГОНАЛЬДЫ ШИНАЛАР)
        function renderConnectionsEditing() {
            const svg = document.getElementById('svgConnections');
            svg.innerHTML = '';
            document.querySelectorAll('.marriage-edge-badge').forEach(el => el.remove());

            if (currentMode !== 'editing' || !treeData) return;

            const container = document.getElementById('editingCardsContainer');
            const edges = (treeData.layout && treeData.layout.edges) ? treeData.layout.edges : null;

            if (edges) {
                // 1. Некелік байланыстар (Супруги: розовые линии + бейдж колец)
                (edges.marriages || []).forEach(m => {
                    const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                    line.setAttribute('x1', m.x1);
                    line.setAttribute('y1', m.y1);
                    line.setAttribute('x2', m.x2);
                    line.setAttribute('y2', m.y2);
                    line.setAttribute('stroke', '#ec4899');
                    line.setAttribute('stroke-width', '2.5');
                    line.setAttribute('stroke-dasharray', '5 4');
                    svg.appendChild(line);

                    const badge = document.createElement('div');
                    badge.className = 'marriage-edge-badge';
                    badge.style.left = `${m.mid_x}px`;
                    badge.style.top = `${m.mid_y}px`;
                    badge.innerHTML = '💍';
                    badge.title = currentLang === 'ru' ? 'Супружеский союз' : 'Некелік одақ';
                    container.appendChild(badge);
                });

                // 2. Родительско-детские ортогональные шины связей (без наложений на карточки)
                (edges.parent_child || []).forEach(pc => {
                    // Вертикальная линия от родителей вниз к горизонтальной шине
                    const dropLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                    dropLine.setAttribute('x1', pc.parent_mid_x);
                    dropLine.setAttribute('y1', pc.parent_bottom_y);
                    dropLine.setAttribute('x2', pc.parent_mid_x);
                    dropLine.setAttribute('y2', pc.bus_y);
                    dropLine.setAttribute('stroke', '#64748b');
                    dropLine.setAttribute('stroke-width', '2.5');
                    svg.appendChild(dropLine);

                    // Горизонтальная шина над детьми
                    const busLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                    busLine.setAttribute('x1', pc.bus_min_x);
                    busLine.setAttribute('y1', pc.bus_y);
                    busLine.setAttribute('x2', pc.bus_max_x);
                    busLine.setAttribute('y2', pc.bus_y);
                    busLine.setAttribute('stroke', '#64748b');
                    busLine.setAttribute('stroke-width', '2.5');
                    svg.appendChild(busLine);

                    // Отводки вниз к каждому ребенку
                    (pc.children || []).forEach(c => {
                        const cLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                        cLine.setAttribute('x1', c.x);
                        cLine.setAttribute('y1', c.bus_y);
                        cLine.setAttribute('x2', c.x);
                        cLine.setAttribute('y2', c.top_y);
                        cLine.setAttribute('stroke', '#64748b');
                        cLine.setAttribute('stroke-width', '2.5');
                        svg.appendChild(cLine);
                    });
                });
            }
        }

        // Резервный расчет координат при отсутствии layout от сервера
        function fallbackLayout(persons, marriages) {
            return persons.map((p, idx) => ({
                id: p.id,
                first_name: p.first_name,
                last_name: p.last_name,
                gender: p.gender,
                clan_ru: p.clan_ru,
                birth_year: p.birth_year,
                death_year: p.death_year,
                is_deceased: p.is_deceased,
                x: 1000 + (idx % 8) * 310,
                y: 300 + Math.floor(idx / 8) * 220,
                width: 260,
                height: 120,
            }));
        }


        // ========================================================
        // 2. «ШЕЖІРЕ-ОРМАН» КӨРКЕМ РЕЖИМІН ДИНАМИКАЛЫҚ ҚҰРУ
        // ========================================================
        function renderForestView() {
            const container = document.getElementById('forestTreesContainer');
            container.innerHTML = '';
            if (!treeData || !treeData.persons || treeData.persons.length === 0) {
                const emptyCard = document.createElement('div');
                emptyCard.className = 'tree-empty-state';
                const isErr = treeData && treeData.status === 'error';
                const title = isErr 
                    ? (currentLang === 'ru' ? 'Ошибка базы данных' : 'Мәліметтер қоры қатесі')
                    : i18n[currentLang].empty_tree_title;
                const desc = isErr
                    ? (treeData.message || '')
                    : i18n[currentLang].empty_tree_desc;

                emptyCard.innerHTML = `
                    <div class="empty-icon">${isErr ? '⚠️' : '🌲'}</div>
                    <h2>${title}</h2>
                    <p>${desc}</p>
                    <div class="empty-actions">
                        <a href="/admin/import" class="btn-empty-action btn-import">${i18n[currentLang].btn_import_gedcom}</a>
                    </div>
                `;
                container.appendChild(emptyCard);
                return;
            }

            // Нақты деректер үшін шежіре-орманды динамикалық құру
            renderRealForestView(container);
        }

        // Нақты деректер үшін көркем орман бейнелеуі (қабаттасусыз, нақты Жандос отбасы)
        function renderRealForestView(container) {
            const persons = treeData.persons;
            const marriages = treeData.marriages || [];
            const personMap = new Map();
            persons.forEach(p => personMap.set(p.id, p));

            // Таңдалған немесе негізгі тұлға (Жандос немесе пайдаланушы)
            let focalId = selectedPersonId || userPersonId;
            let focal = focalId ? personMap.get(focalId) : null;
            if (!focal) {
                focal = persons.find(p => p.gedcom_id === '@I6@' || (p.first_name && p.first_name.includes('Жандос'))) || persons[0];
            }

            // Жұбайын табу
            let spouse = null;
            for (const m of marriages) {
                if (m.husband_id === focal.id && m.wife_id && personMap.has(m.wife_id)) {
                    spouse = personMap.get(m.wife_id);
                    break;
                } else if (m.wife_id === focal.id && m.husband_id && personMap.has(m.husband_id)) {
                    spouse = personMap.get(m.husband_id);
                    break;
                }
            }

            // Балаларын табу
            const children = persons.filter(p => 
                p.father_id === focal.id || p.mother_id === focal.id ||
                (spouse && (p.father_id === spouse.id || p.mother_id === spouse.id))
            );
            children.sort((a, b) => (a.birth_year || 0) - (b.birth_year || 0));

            // Ата-аналарын табу
            const father = focal.father_id ? personMap.get(focal.father_id) : null;
            const mother = focal.mother_id ? personMap.get(focal.mother_id) : null;

            // Бауырларын табу
            const siblings = persons.filter(p => 
                p.id !== focal.id && 
                ((father && p.father_id === father.id) || (mother && p.mother_id === mother.id))
            );

            // Ата-баба діңгегі (Әке жағымен тікелей жоғары қарай өрлеу)
            const ancestorsTrunk = [];
            let curAncestor = father;
            const visited = new Set();
            while (curAncestor && !visited.has(curAncestor.id)) {
                visited.add(curAncestor.id);
                ancestorsTrunk.push(curAncestor);
                curAncestor = curAncestor.father_id ? personMap.get(curAncestor.father_id) : null;
            }

            // Түпкі ата немесе Керей баба
            let rootPerson = null;
            const kerey = persons.find(p => (p.first_name && p.first_name.includes('Керей')) || (p.last_name && p.last_name.includes('Керей')));
            if (kerey) {
                rootPerson = kerey;
            } else if (ancestorsTrunk.length > 0) {
                rootPerson = ancestorsTrunk[ancestorsTrunk.length - 1];
            } else {
                rootPerson = focal;
            }

            // 1. ОРТАЛЫҚ БАСТЫ АҒАШ: АЛТЫБАЕВТАР ӘУЛЕТІ
            const mainStand = document.createElement('div');
            mainStand.className = 'clan-tree-stand stand-main-kerey';
            mainStand.id = 'standKerey';

            // КРОНА
            const crown = document.createElement('div');
            crown.className = 'organic-crown-container';
            crown.id = 'treeCrown';

            let crownHtml = `
                <div class="crown-living-banner">
                    <span>☀️</span>
                    <span>${i18n[currentLang].crown_title}</span>
                </div>
            `;

            // Балалар қатары
            if (children.length > 0) {
                crownHtml += `<div class="branch-children-row">`;
                children.forEach(c => {
                    const isGirl = (c.gender === 'F');
                    const icon = isGirl ? '🌸' : '🍃';
                    const cls = isGirl ? 'flower-daughter-node' : 'leaf-son-node';
                    const note = isGirl 
                        ? (currentLang === 'ru' ? 'Цветок рода (дочь)' : 'Нәзік гүл (қызы)')
                        : (currentLang === 'ru' ? 'Листик рода (сын)' : 'Жас жапырақ (ұлы)');
                    crownHtml += `
                        <div class="${cls}" onclick="selectPerson(${c.id})" title="${note}">
                            <span>${icon}</span>
                            <div><strong>${c.first_name || ''} ${c.last_name || ''}</strong> (${c.birth_year || '—'})</div>
                        </div>
                    `;
                });
                crownHtml += `</div>`;
            }

            // Негізгі шаңырақ (Фокальді тұлға + жұбайы)
            crownHtml += `
                <div class="family-branch">
                    <div class="branch-couple-node is-self-node" onclick="selectPerson(${focal.id})">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span>🎯</span>
                            <div>
                                <strong>${focal.last_name || ''} ${focal.first_name}</strong> (${focal.birth_year || '—'})
                                <div style="font-size:11px; color:#fef08a;">${i18n[currentLang].you_are_here_badge}</div>
                            </div>
                        </div>
            `;

            if (spouse) {
                crownHtml += `
                    <div style="color:#d4af37; font-weight:700;">&</div>
                    <div class="in-married-flower-node" onclick="event.stopPropagation(); selectPerson(${spouse.id});" title="${spouse.last_name || ''} ${spouse.first_name}">
                        <span>🌸</span>
                        <div>
                            <strong>${spouse.first_name} ${spouse.last_name || ''}</strong> (${spouse.birth_year || '—'})
                            <div style="font-size:10px; color:#9d174d;">${i18n[currentLang].married_in_tag}</div>
                        </div>
                    </div>
                `;
            }

            crownHtml += `</div>`;

            // Бауырлар қатары (егер бар болса)
            if (siblings.length > 0) {
                crownHtml += `<div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap; margin-top:10px;">`;
                siblings.forEach(s => {
                    const isSister = (s.gender === 'F');
                    crownHtml += `
                        <div class="branch-couple-node" style="padding:4px 10px; font-size:12px;" onclick="selectPerson(${s.id})">
                            <span>${isSister ? '🌸' : '🌿'}</span>
                            <div><strong>${s.first_name}</strong> (${s.birth_year || '—'})</div>
                        </div>
                    `;
                });
                crownHtml += `</div>`;
            }

            // Әкесі мен анасының шаңырағы
            if (father || mother) {
                const parentId = father ? father.id : (mother ? mother.id : focal.id);
                crownHtml += `
                    <div class="family-branch" style="margin-top:14px;">
                        <div class="branch-couple-node" onclick="selectPerson(${parentId})">
                            <span>📜</span>
                            <div>
                                <strong>${father ? (father.first_name + ' ' + (father.last_name || '')) : (mother.first_name + ' ' + (mother.last_name || ''))}</strong> (${(father && father.birth_year) || (mother && mother.birth_year) || '—'})
                                <div style="font-size:11px; color:#86efac;">${currentLang === 'ru' ? 'Родители' : 'Әке-шеше шаңырағы'}</div>
                            </div>
                        </div>
                    </div>
                `;
            }

            crownHtml += `</div>`;
            crown.innerHTML = crownHtml;
            mainStand.appendChild(crown);

            // ДІҢГЕК (АТА-БАБАЛАР ШЕЖІРЕСІ)
            const trunk = document.createElement('div');
            trunk.className = 'organic-trunk';
            let trunkHtml = '';
            const displayTrunk = ancestorsTrunk.filter(a => rootPerson && a.id !== rootPerson.id);
            if (displayTrunk.length > 0) {
                displayTrunk.forEach(anc => {
                    const isAltybay = (anc.first_name && anc.first_name.includes('Алтыбай')) || (anc.last_name && anc.last_name.includes('Алтыбай'));
                    const style = isAltybay ? 'border-color:#f59e0b; background:#29180c; color:#fef08a;' : '';
                    const icon = isAltybay ? '👑' : '✨';
                    const title = isAltybay ? ' — Ру басы' : '';
                    trunkHtml += `
                        <div class="trunk-ancestor-node" onclick="selectPerson(${anc.id})" style="${style}">
                            ${icon} ${anc.first_name} ${anc.last_name || ''} (${anc.birth_year || '—'}${anc.death_year ? ' — ' + anc.death_year : ''})${title}
                        </div>
                    `;
                });
            } else {
                trunkHtml += `<div class="trunk-ancestor-node" onclick="selectPerson(${focal.id})">✨ ${focal.first_name} ${focal.last_name || ''}</div>`;
            }
            trunk.innerHTML = trunkHtml;
            mainStand.appendChild(trunk);

            // ТҮП ТАМЫР
            const root = document.createElement('div');
            root.className = 'tree-sacred-root';
            root.id = 'treeRoot';
            root.innerHTML = `
                <div class="root-ancestor-badge" onclick="selectPerson(${rootPerson.id})">
                    <span style="font-size:22px;">🌱</span>
                    <div>
                        <div>${(rootPerson.first_name + ' ' + (rootPerson.last_name || '')).toUpperCase()} (${rootPerson.birth_year ? 'ок. ' + rootPerson.birth_year : ''})</div>
                        <div style="font-size:11px; color:#d4af37; font-weight:600;">
                            ${currentLang === 'ru' ? 'Священный корень рода в земле (Таңба: Ашамай, Уран: Ошыбай!)' : 'Шежіренің ең түпкі қасиетті тамыры (Таңба: Ашамай, Ұран: Ошыбай!)'}
                        </div>
                    </div>
                </div>
            `;
            mainStand.appendChild(root);
            container.appendChild(mainStand);

            // 2. ДИНАМИКАЛЫҚ КӨРШІ АҒАШТАР: НАҒАШЫ ЖӘНЕ ҚАЙЫН ЖҰРТ РУЛАРЫ (НАҚТЫ ДЕРЕКТЕР)
            const otherClansMap = new Map();
            persons.forEach(p => {
                const c = p.clan_ru ? p.clan_ru.trim() : '';
                if (c && c !== 'Керей' && c !== 'белгісіз' && c !== 'не указан') {
                    if (!otherClansMap.has(c)) {
                        otherClansMap.set(c, []);
                    }
                    otherClansMap.get(c).push(p);
                }
            });

            let clanIdx = 0;
            otherClansMap.forEach((clanPersons, clanName) => {
                clanIdx++;
                const isLeft = (clanIdx % 2 !== 0);
                const stand = document.createElement('div');
                stand.className = `clan-tree-stand ${isLeft ? 'stand-clan-left' : 'stand-clan-right'}`;

                let clanPersonsHtml = '';
                clanPersons.slice(0, 4).forEach(cp => {
                    const isF = (cp.gender === 'F');
                    clanPersonsHtml += `
                        <div class="${isF ? 'flower-daughter-node' : 'leaf-son-node'}" onclick="selectPerson(${cp.id})" style="margin:4px;">
                            <span>${isF ? '🌸' : '🌿'}</span>
                            <div><strong>${cp.first_name} ${cp.last_name || ''}</strong> (${cp.birth_year || '—'})</div>
                        </div>
                    `;
                });

                stand.innerHTML = `
                    <div class="crown-living-banner" style="background:${isLeft ? 'rgba(180,83,9,0.3)' : 'rgba(30,58,138,0.3)'}; border-color:${isLeft ? '#d4af37' : '#38bdf8'}; color:${isLeft ? '#fef08a' : '#bae6fd'};">
                        <span>🌳</span>
                        <span>${clanName} ағашы</span>
                    </div>
                    <div style="font-size:12px; color:#cbd5e1; max-width:320px; text-align:center; margin:8px 0;">
                        ${currentLang === 'ru' ? 'Родственные связи и сваты' : 'Құрметті құдалар мен нағашылар руы'}
                    </div>
                    <div style="display:flex; flex-wrap:wrap; justify-content:center; gap:8px;">
                        ${clanPersonsHtml}
                    </div>
                `;
                container.appendChild(stand);
            });
        }

        // ========================================================
        // ТҰЛҒАНЫ ТАҢДАУ ЖӘНЕ БАРЛЫҚ КАРТОЧКАЛАРДЫҢ ТУЫСТЫҒЫН ЖАҢАРТУ
        // ========================================================
        async function selectPerson(id) {
            selectedPersonId = id;

            // Ескі таңдауларды тазарту
            document.querySelectorAll('.person-card').forEach(c => c.classList.remove('selected'));
            const selectedCard = document.getElementById(`card-${id}`);
            if (selectedCard) {
                selectedCard.classList.add('selected');
            }

            if (!treeData) return;
            const p = treeData.persons.find(x => x.id === id);
            if (!p) return;

            // БАРЛЫҚ КАРТОЧКАЛАРДЫҢ ТУЫСТЫҚ БЕЙДЖИКТЕРІН ЛЕЗДЕ ЖАҢАРТУ (МГНОВЕННО)
            updateAllKinshipBadges(id);

            // БҮЙІРЛІК ПАНЕЛЬДІ ТОЛТЫРУ
            let fullNameDisplay = `${p.first_name} ${p.last_name || ''} ${p.middle_name || ''}`.trim();
            if (p.maiden_name) {
                fullNameDisplay += ` (${currentLang === 'ru' ? 'урожд.' : 'қыз кезіндегі —'} ${p.maiden_name})`;
            }
            document.getElementById('panelPersonName').innerText = fullNameDisplay;
            const clanDisplay = p.clan_ru || (currentLang === 'ru' ? 'не указан' : 'белгісіз');
            document.getElementById('panelPersonClan').innerText = clanDisplay;
            document.getElementById('panelGender').innerText = p.gender === 'M' ? (currentLang === 'ru' ? 'Мужской' : 'Ер азамат') : (currentLang === 'ru' ? 'Женский' : 'Әйел адам');
            
            // Жылдары: живые — только год/дата рождения! Ушедшие — годы жизни
            const birthDisp = p.birth_date ? p.birth_date : (p.birth_year || '—');
            const deathDisp = p.death_date ? p.death_date : (p.death_year || '†');
            if (p.is_deceased) {
                document.getElementById('panelYears').innerText = `${birthDisp} — ${deathDisp} ✨ (${currentLang === 'ru' ? 'ушёл из жизни' : 'дүниеден өткен'})`;
            } else {
                document.getElementById('panelYears').innerText = `${birthDisp}`;
            }
            let placeDisp = p.birth_place || '—';
            if (p.is_deceased && p.death_place) {
                placeDisp += ` (${currentLang === 'ru' ? 'место кончины:' : 'қайтыс болған жері:'} ${p.death_place})`;
            }
            document.getElementById('panelPlace').innerText = placeDisp;
            document.getElementById('panelBiography').innerText = p.biography || (currentLang === 'ru' ? 'Биография не заполнена.' : 'Өмірбаяны жазылмаған.');

            // Үш жұрт бейджі
            const kin = activeKinships[id];
            const zhurtRow = document.getElementById('panelZhurtRow');
            const zhurtBadge = document.getElementById('panelZhurtBadge');
            if (kin && kin.zhurt) {
                if (zhurtRow) zhurtRow.style.display = 'flex';
                let zCls = 'zhurt-oz';
                if (kin.zhurt.includes('Нағашы')) zCls = 'zhurt-nagashy';
                else if (kin.zhurt.includes('Қайын')) zCls = 'zhurt-kaiyn';
                else if (kin.zhurt.includes('Төркін')) zCls = 'zhurt-torkin';
                else if (kin.zhurt.includes('Күйеу')) zCls = 'zhurt-kuyeui';
                else if (kin.zhurt.includes('Құда')) zCls = 'zhurt-quda';
                if (zhurtBadge) zhurtBadge.innerHTML = `<span class="zhurt-pill ${zCls}">${kin.zhurt}</span>`;
            } else if (zhurtRow) {
                zhurtRow.style.display = 'none';
            }

            // Жеке туыстық сөйлемін серверден нақтылау
            try {
                const kinRes = await fetch(`/api/kinship?target_id=${p.id}&base_id=${userPersonId}`);
                const kinData = await kinRes.json();
                if (kinData && kinData.phrase) {
                    document.getElementById('panelKinshipCallout').innerHTML = kinData.phrase;
                }
            } catch (e) {
                document.getElementById('panelKinshipCallout').innerHTML = `🔗 Туыстық желісі анықталды`;
            }

            // Жүктелген дауыс, естеліктер мен фотосуреттерді шығару (TASK-004)
            loadPersonMedia(p.id);

            openSidePanel();
        }

        // БАРЛЫҚ КАРТОЧКАЛАРДЫҢ ТУЫСТЫҚ АТАУЛАРЫН ТАҢДАЛҒАН ТҰЛҒАҒА ҚАТЫСТЫ ҚАЙТА ЕСЕПТЕУ
        async function updateAllKinshipBadges(baseId) {
            if (!treeData || !treeData.persons || treeData.persons.length === 0) return;

            // Серверден барлық туыстықты бірден сұрау (кэштей отырып)
            try {
                const res = await fetch(`/api/kinship-all?base_id=${baseId}`);
                const data = await res.json();
                if (data && data.kinships) {
                    activeKinships = data.kinships;
                }
            } catch (e) {
                // Сервер уақытша қолжетімсіз болса да клиенттік есеп жұмыс істейді
            }

            treeData.persons.forEach(p => {
                const badge = document.getElementById(`kin-badge-${p.id}`);
                if (!badge) return;

                const isSelf = p.id === userPersonId;
                const isSelected = p.id === baseId;

                badge.classList.remove('active-rel', 'self-rel');

                if (isSelected) {
                    badge.classList.add('active-rel');
                    badge.innerHTML = `<span>★ ${i18n[currentLang].selected_badge}</span> <span>🎯</span>`;
                } else if (isSelf) {
                    badge.classList.add('self-rel');
                    const kin = activeKinships[p.id];
                    const label = kin ? (currentLang === 'ru' ? (kin.term_ru || kin.term_kk) : kin.term_kk) : '';
                    badge.innerHTML = `<span>★ ${i18n[currentLang].you_are_here_badge} ${label ? `(${label})` : ''}</span> <span>✨</span>`;
                } else {
                    const kin = activeKinships[p.id];
                    if (kin) {
                        const label = currentLang === 'ru' ? (kin.term_ru || kin.term_kk) : kin.term_kk;
                        badge.innerHTML = `<span>${label}</span> ${p.is_deceased ? '<span>✨</span>' : '<span>🌿</span>'}`;
                    }
                }
            });
        }

        // ПАНЕЛЬДІ АШУ / ЖАБУ
        function openSidePanel() {
            document.getElementById('sideProfilePanel').classList.add('open');
        }
        function closeSidePanel() {
            document.getElementById('sideProfilePanel').classList.remove('open');
        }

        // ========================================================
        // КАМЕРАНЫ БАСҚАРУ ЖӘНЕ ҰШУ (FLY TO)
        // ========================================================
        function flyTo(targetX, targetY, targetScale = 1.0) {
            const vpWidth = viewport.clientWidth;
            const vpHeight = viewport.clientHeight;

            posX = vpWidth / 2 - targetX * targetScale;
            posY = vpHeight / 2 - targetY * targetScale;
            scale = targetScale;

            updateTransform();
        }

        function updateTransform() {
            world.style.transform = `translate(${posX}px, ${posY}px) scale(${scale})`;
            document.getElementById('zoomLevel').innerText = `${Math.round(scale * 100)}%`;
        }

        function flyToPerson(id) {
            selectPerson(id);
            if (currentMode === 'editing') {
                const card = document.getElementById(`card-${id}`);
                if (card) {
                    const cx = parseInt(card.style.left) + 125;
                    const cy = parseInt(card.style.top) + 65;
                    flyTo(cx, cy, 1.0);
                }
            } else {
                flyToCrown();
            }
        }

        function flyToSelf() {
            flyToPerson(userPersonId);
        }

        // ШЕЖІРЕ-ОРМАННЫҢ ЖОҒАРҒЫ КРОНАСЫНА БАРУ (ТІРІЛЕР ЖОҒАРЫДА)
        function flyToCrown() {
            // Керей ағашының кронасы орталықта орналасқан
            flyTo(2800, 750, 0.85);
        }

        // ҚОҢЫРАТ АҒАШЫНА ҰШЫП БАРУ (АЙНҰР ҰЗАТЫЛҒАН АҒАШҚА)
        function flyToKonyrat() {
            flyTo(4250, 950, 0.9);
        }

        // ========================================================
        // МАСШТАБ БАТЫРМАЛАРЫ
        // ========================================================
        function zoomIn() {
            scale = Math.min(scale * 1.25, 2.5);
            updateTransform();
        }
        function zoomOut() {
            scale = Math.max(scale / 1.25, 0.3);
            updateTransform();
        }
        function resetZoom() {
            scale = 1.0;
            flyToSelf();
        }

        // ========================================================
        // ОҚИҒАЛАРДЫ БАСҚАРУ (MOUSE DRAG, ZOOM, RESIZE)
        // ========================================================
        function initEvents() {
            // Mouse Drag
            viewport.addEventListener('mousedown', (e) => {
                if (e.target.closest('.person-card') || e.target.closest('.radial-btn') || e.target.closest('.leaf-son-node') || e.target.closest('.flower-daughter-node')) return;
                isDragging = true;
                startX = e.clientX - posX;
                startY = e.clientY - posY;
            });

            window.addEventListener('mousemove', (e) => {
                if (!isDragging) return;
                posX = e.clientX - startX;
                posY = e.clientY - startY;
                updateTransform();
            });

            window.addEventListener('mouseup', () => {
                isDragging = false;
            });

            // Mouse Wheel Zoom
            viewport.addEventListener('wheel', (e) => {
                e.preventDefault();
                const zoomFactor = e.deltaY < 0 ? 1.1 : 0.9;
                const newScale = Math.min(Math.max(scale * zoomFactor, 0.3), 2.5);

                const mouseX = e.clientX - viewport.getBoundingClientRect().left;
                const mouseY = e.clientY - viewport.getBoundingClientRect().top;

                posX = mouseX - (mouseX - posX) * (newScale / scale);
                posY = mouseY - (mouseY - posY) * (newScale / scale);
                scale = newScale;

                updateTransform();
            }, { passive: false });

            // Іздеу оқиғасы
            const searchInput = document.getElementById('treeSearchInput');
            const searchDropdown = document.getElementById('searchDropdown');

            searchInput.addEventListener('input', async (e) => {
                const q = e.target.value.trim();
                if (q.length < 2) {
                    searchDropdown.classList.remove('active');
                    searchDropdown.innerHTML = '';
                    return;
                }

                try {
                    const res = await fetch(`/api/tree/search?q=${encodeURIComponent(q)}`);
                    const data = await res.json();
                    if (data && data.results && data.results.length > 0) {
                        searchDropdown.innerHTML = '';
                        data.results.forEach(item => {
                            const row = document.createElement('div');
                            row.className = 'search-item';
                            row.innerHTML = `
                                <div>
                                    <strong>${item.name}</strong>
                                    <div style="font-size:11px; color:#94a3b8;">${item.years} &bull; ${item.clan}</div>
                                </div>
                                <span>${item.gender === 'M' ? '🌿' : '🌸'}</span>
                            `;
                            row.onclick = () => {
                                searchDropdown.classList.remove('active');
                                searchInput.value = item.name;
                                flyToPerson(item.id);
                            };
                            searchDropdown.appendChild(row);
                        });
                        searchDropdown.classList.add('active');
                    } else {
                        searchDropdown.classList.remove('active');
                    }
                } catch (err) {
                    console.error("Іздеу қатесі:", err);
                }
            });

            document.addEventListener('click', (e) => {
                if (!e.target.closest('.search-box')) {
                    searchDropdown.classList.remove('active');
                }
            });
        }


        // ========================================================
        // МОДАЛЬДЫ ТЕРЕЗЕЛЕРДІ БАСҚАРУ
        // ========================================================
        function openArtModal() {
            document.getElementById('artModal').classList.add('active');
        }
        function closeArtModal() {
            document.getElementById('artModal').classList.remove('active');
        }

        function openKinshipDictionaryModal() {
            document.getElementById('kinshipDictModal').classList.add('active');
        }
        function closeKinshipDictionaryModal() {
            document.getElementById('kinshipDictModal').classList.remove('active');
        }

        function openAddModal(relationType, baseId = null) {
            const basePerson = treeData ? treeData.persons.find(x => x.id === (baseId || selectedPersonId)) : null;
            const baseName = basePerson ? `${basePerson.first_name} ${basePerson.last_name || ''}` : '';

            document.getElementById('formRelationType').value = relationType;
            document.getElementById('formBasePersonId').value = baseId || selectedPersonId;

            const titles = {
                kk: {
                    father: `Әке қосу (үшін: ${baseName})`,
                    mother: `Ана қосу (үшін: ${baseName})`,
                    spouse: `Жұбай қосу (үшін: ${baseName})`,
                    sibling: `Бауыр қосу (үшін: ${baseName})`,
                    son: `Ұл қосу (үшін: ${baseName})`,
                    daughter: `Қыз қосу (үшін: ${baseName})`,
                    child: `Бала қосу (үшін: ${baseName})`,
                },
                ru: {
                    father: `Добавить отца (для: ${baseName})`,
                    mother: `Добавить мать (для: ${baseName})`,
                    spouse: `Добавить супруга/у (для: ${baseName})`,
                    sibling: `Добавить брата/сестру (для: ${baseName})`,
                    son: `Добавить сына (для: ${baseName})`,
                    daughter: `Добавить дочь (для: ${baseName})`,
                    child: `Добавить ребенка (для: ${baseName})`,
                }
            };

            document.getElementById('modalAddTitle').innerText = (titles[currentLang] && titles[currentLang][relationType]) || 'Адам қосу';

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
            const isDeceased = document.getElementById('formIsDeceased').checked;
            document.getElementById('deathYearGroup').style.display = isDeceased ? 'block' : 'none';
        }

        function toggleEditDeathInputs() {
            const isDeceased = document.getElementById('editIsDeceased').checked;
            document.getElementById('editDeathSection').style.display = isDeceased ? 'block' : 'none';
        }

        // ========================================================
        // РЕАЛЬНОЕ ДОБАВЛЕНИЕ РОДСТВЕННИКА (API -> БАЗА ДАННЫХ)
        // ========================================================
        async function handleSavePerson(e) {
            e.preventDefault();
            const relType = document.getElementById('formRelationType').value;
            const targetId = parseInt(document.getElementById('formBasePersonId').value) || selectedPersonId;

            const isDeceased = document.getElementById('formIsDeceased').checked ? 1 : 0;
            const formData = new URLSearchParams();
            formData.append('target_id', targetId);
            formData.append('relation_type', relType);
            formData.append('first_name', document.getElementById('formFirstName').value.trim());
            formData.append('last_name', document.getElementById('formLastName').value.trim());
            formData.append('middle_name', document.getElementById('formMiddleName').value.trim());
            formData.append('maiden_name', document.getElementById('formMaidenName').value.trim());
            formData.append('gender', document.getElementById('formGender').value);
            formData.append('clan_ru', document.getElementById('formClanRu').value.trim());
            formData.append('birth_year', document.getElementById('formBirthYear').value.trim());
            formData.append('birth_place', document.getElementById('formBirthPlace').value.trim());
            formData.append('is_deceased', isDeceased);
            formData.append('death_year', isDeceased ? document.getElementById('formDeathYear').value.trim() : '');
            formData.append('biography', document.getElementById('formBiography').value.trim());

            try {
                const res = await fetch('/api/person/create-relative', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData.toString()
                });
                const data = await res.json();
                if (data.status === 'success') {
                    closeAddModal();
                    alert(currentLang === 'ru' ? 'Родственник успешно добавлен в базу!' : 'Туыс сәтті қосылды!');
                    await initTree();
                    if (data.new_person_id) {
                        flyToPerson(data.new_person_id);
                    }
                } else {
                    alert('Қате / Ошибка: ' + (data.error || 'Белгісіз қате'));
                }
            } catch (err) {
                alert('Сұраныс қатесі: ' + err.message);
            }
        }

        // ========================================================
        // РЕАЛЬНОЕ РЕДАКТИРОВАНИЕ ПЕРСОНЫ (API -> AUDIT LOGS)
        // ========================================================
        async function openEditPersonModal(id) {
            if (!canEdit) {
                alert(currentLang === 'ru' ? 'У вас режим только чтения' : 'Өңдеу тек рұқсаты бар пайдаланушыларға қолжетімді');
                return;
            }
            try {
                const res = await fetch(`/api/person?id=${id}`);
                const data = await res.json();
                if (!data || !data.person) {
                    alert('Тұлға табылмады / Персона не найдена');
                    return;
                }
                const p = data.person;
                document.getElementById('editPersonId').value = p.id;
                document.getElementById('editFirstName').value = p.first_name || '';
                document.getElementById('editLastName').value = p.last_name || '';
                document.getElementById('editMiddleName').value = p.middle_name || '';
                document.getElementById('editMaidenName').value = p.maiden_name || '';
                document.getElementById('editGender').value = p.gender || 'M';
                document.getElementById('editClanRu').value = p.clan_ru || '';
                document.getElementById('editBirthYear').value = p.birth_year || '';
                document.getElementById('editBirthDate').value = p.birth_date || '';
                document.getElementById('editBirthPlace').value = p.birth_place || '';

                const isDeceased = (p.is_deceased == 1 || p.death_year || p.death_date);
                document.getElementById('editIsDeceased').checked = !!isDeceased;
                document.getElementById('editDeathSection').style.display = isDeceased ? 'block' : 'none';
                document.getElementById('editDeathYear').value = p.death_year || '';
                document.getElementById('editDeathDate').value = p.death_date || '';
                document.getElementById('editDeathPlace').value = p.death_place || '';
                document.getElementById('editBiography').value = p.biography || '';

                const btnDel = document.getElementById('btnDeletePerson');
                if (btnDel) {
                    btnDel.style.display = isAdmin ? 'inline-flex' : 'none';
                }

                const auditContainer = document.getElementById('editAuditLogContainer');
                if (data.audit_logs && data.audit_logs.length > 0) {
                    let aHtml = '<table class="audit-table"><thead><tr><th>Уақыты</th><th>Әрекет</th><th>Өзгеріс</th><th>Пайдаланушы</th></tr></thead><tbody>';
                    data.audit_logs.forEach(al => {
                        aHtml += `
                            <tr>
                                <td style="white-space:nowrap;">${al.created_at || '—'}</td>
                                <td><span style="font-weight:700; color:#d4af37;">${al.action}</span></td>
                                <td>${al.description || '—'}</td>
                                <td>${al.user_name || al.user_email || 'Админ'}</td>
                            </tr>
                        `;
                    });
                    aHtml += '</tbody></table>';
                    auditContainer.innerHTML = aHtml;
                } else {
                    auditContainer.innerHTML = '<span style="color:#64748b; font-size:12px;">Бұл тұлға бойынша өзгерістер тарихы әзірге жоқ</span>';
                }

                document.getElementById('editPersonModal').classList.add('active');
            } catch (err) {
                alert('Жүктеу қатесі: ' + err.message);
            }
        }

        function closeEditPersonModal() {
            document.getElementById('editPersonModal').classList.remove('active');
        }

        async function handleSavePersonEdit(e) {
            e.preventDefault();
            const id = document.getElementById('editPersonId').value;
            const isDeceased = document.getElementById('editIsDeceased').checked ? 1 : 0;

            const formData = new URLSearchParams();
            formData.append('id', id);
            formData.append('first_name', document.getElementById('editFirstName').value.trim());
            formData.append('last_name', document.getElementById('editLastName').value.trim());
            formData.append('middle_name', document.getElementById('editMiddleName').value.trim());
            formData.append('maiden_name', document.getElementById('editMaidenName').value.trim());
            formData.append('gender', document.getElementById('editGender').value);
            formData.append('clan_ru', document.getElementById('editClanRu').value.trim());
            formData.append('birth_year', document.getElementById('editBirthYear').value.trim());
            formData.append('birth_date', document.getElementById('editBirthDate').value.trim());
            formData.append('birth_place', document.getElementById('editBirthPlace').value.trim());
            formData.append('is_deceased', isDeceased);
            formData.append('death_year', isDeceased ? document.getElementById('editDeathYear').value.trim() : '');
            formData.append('death_date', isDeceased ? document.getElementById('editDeathDate').value.trim() : '');
            formData.append('death_place', isDeceased ? document.getElementById('editDeathPlace').value.trim() : '');
            formData.append('biography', document.getElementById('editBiography').value.trim());

            try {
                const res = await fetch('/api/person/update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData.toString()
                });
                const data = await res.json();
                if (data.status === 'success') {
                    closeEditPersonModal();
                    alert(currentLang === 'ru' ? 'Данные успешно обновлены и сохранены в истории!' : 'Өзгерістер сәтті сақталды!');
                    await initTree();
                    selectPerson(parseInt(id));
                    if (currentMode === 'list') {
                        loadListViewData();
                    }
                } else {
                    alert('Қате / Ошибка: ' + (data.error || 'Белгісіз қате'));
                }
            } catch (err) {
                alert('Сұраныс қатесі: ' + err.message);
            }
        }

        async function handleDeleteCurrentPerson() {
            const id = document.getElementById('editPersonId').value;
            if (!confirm(currentLang === 'ru' ? 'Вы уверены, что хотите удалить эту персону?' : 'Бұл тұлғаны өшіруге сенімдісіз бе?')) {
                return;
            }
            try {
                const formData = new URLSearchParams();
                formData.append('id', id);
                const res = await fetch('/api/person/delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData.toString()
                });
                const data = await res.json();
                if (data.status === 'success') {
                    closeEditPersonModal();
                    alert(currentLang === 'ru' ? 'Персона помечена как удаленная' : 'Тұлға өшірілді');
                    await initTree();
                    if (currentMode === 'list') {
                        loadListViewData();
                    }
                } else {
                    alert('Қате: ' + (data.error || 'Белгісіз қате'));
                }
            } catch (err) {
                alert('Қате: ' + err.message);
            }
        }

        // ========================================================
        // НЕКЕ МӘЛІМЕТТЕРІН ӨҢДЕУ (НЕКЕ / АЖЫРАСУ / МЕРЗІМІ)
        // ========================================================
        function openMarriageModal(m) {
            document.getElementById('marriageId').value = m.id;
            document.getElementById('marriageStatus').value = m.status || (m.is_current ? 'married' : 'divorced');
            document.getElementById('marriageStartDate').value = m.start_date || m.marriage_date || '';
            document.getElementById('marriageEndDate').value = m.end_date || m.divorce_date || '';
            document.getElementById('marriageNotes').value = m.notes || '';

            let husbandName = 'Күйеуі';
            let wifeName = 'Жұбайы';
            if (treeData && treeData.persons) {
                const h = treeData.persons.find(x => x.id === m.husband_id);
                const w = treeData.persons.find(x => x.id === m.wife_id);
                if (h) husbandName = `${h.last_name || ''} ${h.first_name}`;
                if (w) wifeName = `${w.last_name || ''} ${w.first_name}`;
            }
            document.getElementById('marriageCoupleNames').innerText = `${husbandName} ❤️ ${wifeName}`;
            document.getElementById('marriageModal').classList.add('active');
        }

        function closeMarriageModal() {
            document.getElementById('marriageModal').classList.remove('active');
        }

        async function handleSaveMarriage(e) {
            e.preventDefault();
            const id = document.getElementById('marriageId').value;
            const formData = new URLSearchParams();
            formData.append('id', id);
            formData.append('status', document.getElementById('marriageStatus').value);
            formData.append('start_date', document.getElementById('marriageStartDate').value.trim());
            formData.append('end_date', document.getElementById('marriageEndDate').value.trim());
            formData.append('notes', document.getElementById('marriageNotes').value.trim());

            try {
                const res = await fetch('/api/marriage/update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData.toString()
                });
                const data = await res.json();
                if (data.status === 'success') {
                    closeMarriageModal();
                    alert(currentLang === 'ru' ? 'Данные брака успешно сохранены!' : 'Неке мәліметтері сақталды!');
                    await initTree();
                } else {
                    alert('Қате: ' + (data.error || 'Белгісіз қате'));
                }
            } catch (err) {
                alert('Қате: ' + err.message);
            }
        }

        // ========================================================
        // 3. «ТІЗІМ / СПИСОК» РЕЖИМІН ЖҮКТЕУ ЖӘНЕ ФИЛЬТРЛЕУ
        // ========================================================
        let listFilterTimer = null;
        function handleListFilterChange() {
            clearTimeout(listFilterTimer);
            listFilterTimer = setTimeout(() => {
                loadListViewData();
            }, 250);
        }

        async function loadListViewData() {
            const q = document.getElementById('listSearchInput').value.trim();
            const clanId = document.getElementById('listClanFilter').value;
            const gender = document.getElementById('listGenderFilter').value;
            const status = document.getElementById('listStatusFilter').value;
            const sort = document.getElementById('listSortFilter').value;

            const params = new URLSearchParams();
            if (q) params.set('q', q);
            if (clanId) params.set('clan_id', clanId);
            if (gender && gender !== 'all') params.set('gender', gender);
            if (status && status !== 'all') params.set('status', status);
            if (sort) params.set('sort', sort);

            const tbody = document.getElementById('listTableBody');
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding:24px; color:#94a3b8;">Іздеу жүргізілуде...</td></tr>';

            try {
                const res = await fetch(`/api/tree/list?${params.toString()}`);
                const data = await res.json();
                if (data && data.results) {
                    listData = data.results;
                    renderListTable(data.results);
                    document.getElementById('listStatsCount').innerText = currentLang === 'ru' 
                        ? `Найдено: ${data.total} персон` 
                        : `Табылды: ${data.total} тұлға`;

                    populateClanFilterOptions();
                }
            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding:20px; color:#ef4444;">Қате: ${err.message}</td></tr>`;
            }
        }

        function populateClanFilterOptions() {
            const select = document.getElementById('listClanFilter');
            if (select.options.length > 1 || !treeData || !treeData.clans) return;

            treeData.clans.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.innerText = c.name_kk || c.name_ru;
                select.appendChild(opt);
            });
        }

        function renderListTable(items) {
            const tbody = document.getElementById('listTableBody');
            tbody.innerHTML = '';

            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding:30px; color:#94a3b8;">Талаптарға сай ешкім табылмады</td></tr>';
                return;
            }

            items.forEach((p, idx) => {
                const tr = document.createElement('tr');
                const isDeceased = (p.is_deceased == 1);
                const isM = (p.gender === 'M');
                const initials = (p.first_name ? p.first_name[0] : '') + (p.last_name ? p.last_name[0] : '');

                // Үш жұрт бейджі
                const kin = activeKinships[p.id];
                let zhurtHtml = '—';
                if (kin && kin.zhurt) {
                    let zCls = 'zhurt-oz';
                    if (kin.zhurt.includes('Нағашы')) zCls = 'zhurt-nagashy';
                    else if (kin.zhurt.includes('Қайын')) zCls = 'zhurt-kaiyn';
                    else if (kin.zhurt.includes('Төркін')) zCls = 'zhurt-torkin';
                    else if (kin.zhurt.includes('Күйеу')) zCls = 'zhurt-kuyeui';
                    else if (kin.zhurt.includes('Құда')) zCls = 'zhurt-quda';
                    zhurtHtml = `<span class="zhurt-pill ${zCls}">${kin.zhurt}</span> <div style="font-size:11px; color:#94a3b8; margin-top:2px;">${currentLang === 'ru' ? (kin.term_ru || kin.term_kk) : kin.term_kk}</div>`;
                }

                // Супруги
                let spousesHtml = '—';
                if (p.spouses && p.spouses.length > 0) {
                    spousesHtml = p.spouses.map(s => {
                        const isDiv = (s.status === 'divorced');
                        return `<span style="font-size:12px; color:${isDiv ? '#94a3b8' : '#fbcfe8'};">${isDiv ? '💔' : '💍'} ${s.name} ${s.years ? `(${s.years})` : ''}</span>`;
                    }).join('<br>');
                }

                // Родители
                let parentsHtml = '—';
                if (p.father_name || p.mother_name) {
                    parentsHtml = `${p.father_name ? 'Әкесі: ' + p.father_name : ''}${p.father_name && p.mother_name ? '<br>' : ''}${p.mother_name ? 'Анасы: ' + p.mother_name : ''}`;
                }

                tr.innerHTML = `
                    <td style="color:#64748b; font-size:11.5px;">${idx + 1}</td>
                    <td>
                        <div style="display:flex; align-items:center;">
                            <div class="list-avatar ${isDeceased ? 'deceased' : (isM ? 'male' : 'female')}">${initials}</div>
                            <div>
                                <div style="font-weight:700; color:#f8fafc; font-size:13.5px;">${p.full_name}</div>
                                ${p.maiden_name ? `<div style="font-size:11px; color:#f472b6;">(${currentLang === 'ru' ? 'урожд.' : 'қыз кезіндегі —'} ${p.maiden_name})</div>` : ''}
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="${isM ? 'gender-badge-m' : 'gender-badge-f'}">${isM ? 'Ер' : 'Әйел'}</span>
                    </td>
                    <td>
                        <span class="clan-pill">${p.clan_ru || 'белгісіз'}</span>
                    </td>
                    <td style="white-space:nowrap;">
                        ${p.years_display || '—'}
                    </td>
                    <td style="font-size:12px; color:#cbd5e1;">
                        ${parentsHtml}
                    </td>
                    <td>
                        ${spousesHtml}
                    </td>
                    <td>
                        ${zhurtHtml}
                    </td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn-table-action" onclick="focusPersonFromList(${p.id})">🎯 Фокус</button>
                        ${canEdit ? `<button type="button" class="btn-table-action" onclick="openEditPersonModal(${p.id})">✏️</button>` : ''}
                    </td>
                `;

                tbody.appendChild(tr);
            });
        }

        function focusPersonFromList(id) {
            switchMode('editing');
            flyToPerson(id);
        }

        // ========================================================
        // ДАУЫС ЖӘНЕ ЕСТЕЛІКТЕРДІ БАСҚАРУ (TASK-004)
        // ========================================================
        let currentMediaTab = 'audio';

        async function loadPersonMedia(personId) {
            const container = document.getElementById('panelMediaContainer');
            if (!container) return;
            container.innerHTML = '<div style="color:#94a3b8; font-size:12px; font-style:italic;">Жүктелуде...</div>';

            try {
                const res = await fetch(`/api/person/media?person_id=${personId}`);
                const json = await res.json();
                if (!json.success || !json.data) {
                    container.innerHTML = '<div style="color:#ef4444; font-size:12px;">Жүктеу қатесі</div>';
                    return;
                }

                const data = json.data;
                if (data.total_count === 0) {
                    container.innerHTML = '<div style="color:#94a3b8; font-size:12px; font-style:italic;">Әзірге үн жазбасы мен естеліктер қосылмаған. «+ Жазба қосу» батырмасын басыңыз.</div>';
                    return;
                }

                let html = '';

                // Аудиозаписи
                if (data.audio && data.audio.length > 0) {
                    html += '<div style="font-size:11px; color:#fef08a; font-weight:700; margin-top:4px;">🎙️ АУДИОЖАЗБАЛАР (АҚСАҚАЛДАР ҮНІ):</div>';
                    data.audio.forEach(a => {
                        html += `
                            <div style="background:rgba(255,255,255,0.04); border:1px solid #334155; border-radius:8px; padding:8px 10px;">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <div style="font-weight:700; color:#f8fafc; font-size:12.5px;">${a.title}</div>
                                    <span style="font-size:10.5px; color:#94a3b8;">${a.duration_formatted}</span>
                                </div>
                                <div style="font-size:11px; color:#94a3b8; margin:2px 0 6px 0;">
                                    ${a.recorded_by ? `Айтушы: ${a.recorded_by}` : ''} ${a.event_year ? `(${a.event_year} ж.)` : ''}
                                </div>
                                <audio controls preload="none" style="width:100%; height:32px; border-radius:4px;" src="${a.stream_url}"></audio>
                            </div>
                        `;
                    });
                }

                // Текстовые истории
                if (data.stories && data.stories.length > 0) {
                    html += '<div style="font-size:11px; color:#bae6fd; font-weight:700; margin-top:6px;">📝 ЕСТЕЛІКТЕР МЕН ТАРИХ:</div>';
                    data.stories.forEach(s => {
                        html += `
                            <div style="background:rgba(255,255,255,0.04); border:1px solid #334155; border-radius:8px; padding:10px;">
                                <div style="font-weight:700; color:#bae6fd; font-size:12.5px; margin-bottom:4px;">${s.title}</div>
                                <div style="font-size:12px; color:#cbd5e1; line-height:1.5; white-space:pre-wrap; max-height:120px; overflow-y:auto;">${s.content}</div>
                                <div style="font-size:11px; color:#94a3b8; margin-top:4px;">
                                    ${s.recorded_by ? `Жазған/айтқан: ${s.recorded_by}` : ''} ${s.event_year ? `(${s.event_year} ж.)` : ''}
                                </div>
                            </div>
                        `;
                    });
                }

                // Фотографии
                if (data.photos && data.photos.length > 0) {
                    html += '<div style="font-size:11px; color:#fbcfe8; font-weight:700; margin-top:6px;">📷 АРХИВТІК ФОТОСУРЕТТЕР:</div>';
                    data.photos.forEach(f => {
                        html += `
                            <div style="background:rgba(255,255,255,0.04); border:1px solid #334155; border-radius:8px; padding:8px;">
                                <div style="font-weight:700; color:#fbcfe8; font-size:12px; margin-bottom:4px;">${f.title}</div>
                                <a href="${f.stream_url}" target="_blank" title="Үлкейту">
                                    <img src="${f.stream_url}" style="width:100%; max-height:150px; object-fit:cover; border-radius:6px;" alt="${f.title}">
                                </a>
                                <div style="font-size:10.5px; color:#94a3b8; margin-top:3px;">${f.recorded_by || ''} ${f.event_year ? `(${f.event_year} ж.)` : ''}</div>
                            </div>
                        `;
                    });
                }

                container.innerHTML = html;
            } catch (e) {
                container.innerHTML = `<div style="color:#ef4444; font-size:12px;">Қате: ${e.message}</div>`;
            }
        }

        function openAddMediaModal(personId) {
            const p = treeData ? treeData.persons.find(x => x.id === (personId || selectedPersonId)) : null;
            if (!p) return;

            document.getElementById('mediaPersonId').value = p.id;
            document.getElementById('addMediaModalSub').innerText = `${p.first_name} ${p.last_name || ''} үшін жаңа жазба`;
            document.getElementById('mediaTitle').value = '';
            document.getElementById('mediaFileInput').value = '';
            document.getElementById('mediaStoryContent').value = '';
            document.getElementById('mediaRecordedBy').value = '';
            document.getElementById('mediaEventYear').value = '';

            setMediaTab('audio');
            document.getElementById('addMediaModal').classList.add('active');
        }

        function closeAddMediaModal() {
            document.getElementById('addMediaModal').classList.remove('active');
        }

        function setMediaTab(tab) {
            currentMediaTab = tab;
            ['tabMediaAudio', 'tabMediaStory', 'tabMediaPhoto'].forEach(id => {
                document.getElementById(id).classList.remove('active');
            });

            const fileGroup = document.getElementById('mediaFileGroup');
            const storyGroup = document.getElementById('mediaStoryGroup');
            const fileLabel = document.getElementById('mediaFileLabel');
            const fileInput = document.getElementById('mediaFileInput');

            if (tab === 'audio') {
                document.getElementById('tabMediaAudio').classList.add('active');
                fileGroup.style.display = 'block';
                storyGroup.style.display = 'none';
                fileLabel.innerText = 'Аудио файлды таңдаңыз (MP3, M4A, WAV) *';
                fileInput.accept = 'audio/*';
            } else if (tab === 'story') {
                document.getElementById('tabMediaStory').classList.add('active');
                fileGroup.style.display = 'none';
                storyGroup.style.display = 'block';
            } else if (tab === 'photo') {
                document.getElementById('tabMediaPhoto').classList.add('active');
                fileGroup.style.display = 'block';
                storyGroup.style.display = 'none';
                fileLabel.innerText = 'Архивтік фотосуретті таңдаңыз (JPG, PNG, WEBP) *';
                fileInput.accept = 'image/*';
            }
        }

        async function handleSaveMedia(e) {
            e.preventDefault();
            const personId = parseInt(document.getElementById('mediaPersonId').value, 10);
            const title = document.getElementById('mediaTitle').value.trim();
            const recordedBy = document.getElementById('mediaRecordedBy').value.trim();
            const eventYear = document.getElementById('mediaEventYear').value.trim();
            const submitBtn = document.getElementById('btnSubmitMedia');

            if (!personId || !title) return;

            submitBtn.disabled = true;
            submitBtn.innerText = 'Сақталуда...';

            try {
                if (currentMediaTab === 'story') {
                    const content = document.getElementById('mediaStoryContent').value.trim();
                    if (!content) {
                        alert('Естелік мәтінін жазыңыз!');
                        submitBtn.disabled = false;
                        submitBtn.innerText = 'Сақтау';
                        return;
                    }

                    const res = await fetch('/api/person/story/save', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            person_id: personId,
                            title: title,
                            content: content,
                            recorded_by: recordedBy,
                            event_year: eventYear
                        })
                    });
                    const result = await res.json();
                    if (!result.success) throw new Error(result.message || 'Қате');
                } else {
                    const fileInput = document.getElementById('mediaFileInput');
                    if (!fileInput.files || fileInput.files.length === 0) {
                        alert('Файлды таңдаңыз!');
                        submitBtn.disabled = false;
                        submitBtn.innerText = 'Сақтау';
                        return;
                    }

                    const formData = new FormData();
                    formData.append('person_id', personId);
                    formData.append('media_type', currentMediaTab);
                    formData.append('title', title);
                    formData.append('recorded_by', recordedBy);
                    formData.append('event_year', eventYear);
                    formData.append('media_file', fileInput.files[0]);

                    const res = await fetch('/api/person/media/upload', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await res.json();
                    if (!result.success) throw new Error(result.message || 'Жүктеу қатесі');
                }

                closeAddMediaModal();
                loadPersonMedia(personId);
            } catch (err) {
                alert('Қате: ' + err.message);
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerText = 'Сақтау';
            }
        }

        // ========================================================
        // ЖЕТІ АТА ТЕКСЕРУ МОДАЛЬДЫ ТЕРЕЗЕСІ (TASK-004)
        // ========================================================
        function openZhetiAtaTreeModal(preselectedId = null) {
            if (!treeData || !treeData.persons) return;

            const selA = document.getElementById('zhetiAtaPersonA');
            const selB = document.getElementById('zhetiAtaPersonB');
            selA.innerHTML = '';
            selB.innerHTML = '';

            const sorted = [...treeData.persons].sort((a,b) => (a.first_name || '').localeCompare(b.first_name || ''));
            sorted.forEach(p => {
                const optA = document.createElement('option');
                optA.value = p.id;
                optA.text = `${p.first_name} ${p.last_name || ''} (${p.gender === 'M' ? 'Ер' : 'Әйел'}, ${p.birth_year || '—'})`;
                selA.appendChild(optA);

                const optB = document.createElement('option');
                optB.value = p.id;
                optB.text = `${p.first_name} ${p.last_name || ''} (${p.gender === 'M' ? 'Ер' : 'Әйел'}, ${p.birth_year || '—'})`;
                selB.appendChild(optB);
            });

            if (preselectedId) {
                selA.value = preselectedId.toString();
            } else if (selectedPersonId) {
                selA.value = selectedPersonId.toString();
            } else {
                selA.value = "6"; // Жандос
            }

            // Второго человека выбираем отличающегося
            const diffPerson = sorted.find(x => x.id !== parseInt(selA.value, 10));
            if (diffPerson) {
                selB.value = diffPerson.id.toString();
            }

            document.getElementById('zhetiAtaResultBox').style.display = 'none';
            document.getElementById('zhetiAtaModal').classList.add('active');
        }

        function closeZhetiAtaTreeModal() {
            document.getElementById('zhetiAtaModal').classList.remove('active');
        }

        async function runTreeZhetiAtaCheck() {
            const a = document.getElementById('zhetiAtaPersonA').value;
            const b = document.getElementById('zhetiAtaPersonB').value;
            const resBox = document.getElementById('zhetiAtaResultBox');
            resBox.style.display = 'block';
            resBox.innerHTML = '<div style="text-align:center; padding:16px; color:#94a3b8;">Есептелуде...</div>';

            try {
                const res = await fetch(`/api/zheti-ata?a=${a}&b=${b}`);
                const data = await res.json();
                if (!data.success) {
                    resBox.innerHTML = `<div style="color:#ef4444; padding:10px;">${data.error || 'Қате орын алды'}</div>`;
                    return;
                }

                const pat = data.paternal;
                const isWithin = pat.is_within_7_generations;
                const statusColor = isWithin ? '#ef4444' : (pat.verdict_code === 'exceeds_7' ? '#10b981' : '#64748b');
                const bgColor = isWithin ? 'rgba(239,68,68,0.1)' : (pat.verdict_code === 'exceeds_7' ? 'rgba(16,185,129,0.1)' : 'rgba(255,255,255,0.03)');

                let html = `
                    <div style="background:${bgColor}; border:2px solid ${statusColor}; border-radius:10px; padding:16px; margin-bottom:14px;">
                        <div style="font-size:16px; font-weight:700; color:${statusColor}; margin-bottom:6px;">
                            ${pat.title_kk}
                        </div>
                        <div style="font-size:13.5px; line-height:1.6; color:#f8fafc;">
                            ${pat.desc_kk}
                        </div>
                    </div>
                `;

                if (pat.has_common_ancestor && pat.path_a && pat.path_b) {
                    html += `
                        <div style="background:rgba(255,255,255,0.03); border:1px solid #334155; border-radius:8px; padding:14px; margin-bottom:12px;">
                            <div style="font-weight:700; font-size:13px; color:#fef08a; margin-bottom:10px;">
                                🌿 Ортақ атаға дейінгі туыстық желісі (Әкелер желісі):
                            </div>
                            <div style="font-size:13px; color:#cbd5e1; line-height:1.6;">
                                <strong>${data.person_a.first_name} жағынан (${pat.gen_distance_a}-буын):</strong><br>
                                ${pat.path_a.map(x => `${x.title_kk}: <strong>${x.person.first_name} ${x.person.last_name || ''}</strong>`).join(' ➔ ')}
                            </div>
                            <div style="font-size:13px; color:#cbd5e1; line-height:1.6; margin-top:8px;">
                                <strong>${data.person_b.first_name} жағынан (${pat.gen_distance_b}-буын):</strong><br>
                                ${pat.path_b.map(x => `${x.title_kk}: <strong>${x.person.first_name} ${x.person.last_name || ''}</strong>`).join(' ➔ ')}
                            </div>
                        </div>
                    `;
                }

                if (data.all_lines && data.all_lines.has_common_ancestor && data.all_lines.is_maternal) {
                    html += `
                        <div style="background:rgba(255,255,255,0.02); border:1px dashed #64748b; border-radius:8px; padding:10px 14px; font-size:12px; color:#94a3b8;">
                            ℹ️ <strong>Справочно:</strong> ${data.all_lines.summary_kk}
                        </div>
                    `;
                }

                resBox.innerHTML = html;
            } catch (err) {
                resBox.innerHTML = `<div style="color:#ef4444; padding:10px;">Қате: ${err.message}</div>`;
            }
        }

        // БАСТАУ
        window.addEventListener('DOMContentLoaded', initTree);
    </script>
</body>
</html>
