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
        .btn-empty-action.btn-demo {
            background: rgba(255,255,255,0.08);
            color: #fef08a;
            border: 1px solid rgba(212, 175, 55, 0.5);
        }
        .btn-empty-action.btn-demo:hover {
            background: rgba(212, 175, 55, 0.25);
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

        <!-- РЕЖИМДЕР АУЫСТЫРҒЫШЫ («ӨҢДЕУ» ЖӘНЕ «ШЕЖІРЕ-ОРМАН») -->
        <div class="mode-switcher">
            <button class="mode-btn active" id="btnModeEditing" onclick="switchMode('editing')">
                <span>🛠️</span>
                <span data-i18n="mode_editing">Өңдеу</span>
            </button>
            <button class="mode-btn" id="btnModeForest" onclick="switchMode('forest')">
                <span>🌲</span>
                <span data-i18n="mode_forest">Шежіре-Орман</span>
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
            <button class="btn-tool" id="btnToggleDemo" onclick="toggleDemoMode()" title="Демонстрациялық режимді қосу/өшіру">
                <span id="demoIcon">🌟</span>
                <span id="demoLabel" data-i18n="btn_demo_mode">Демо</span>
            </button>

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

            <div style="display:flex; gap:10px; margin-top:10px;">
                <button class="btn-modal-submit" style="flex:1;" onclick="openAddModal('child', selectedPersonId)">
                    + <span data-i18n="btn_add_child">Бала қосу</span>
                </button>
            </div>
        </div>
    </aside>

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
                        <div class="form-group">
                            <label data-i18n="form_is_deceased">Күйі</label>
                            <select class="form-select" id="formIsDeceased" onchange="toggleDeathInputs()">
                                <option value="0" data-i18n="status_living">Тірі</option>
                                <option value="1" data-i18n="status_deceased">Дүниеден өткен</option>
                            </select>
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

                    <div class="form-group">
                        <label data-i18n="form_birth_place">Туған жері</label>
                        <input type="text" class="form-input" id="formBirthPlace" placeholder="Мысалы: Алматы қ.">
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

    <!-- ========================================================
         JAVASCRIPT ЛОГИКАСЫ МЕН ДИНАМИКАЛЫҚ ҚҰРЫЛЫМ
         ======================================================== -->
    <script>
        // СЕРВЕРДЕН АЛЫНҒАН АҚПАРАТТАР
        const userPersonId = <?= (int)$userPersonId ?>;
        let currentMode = 'editing'; // 'editing' немесе 'forest'
        let currentLang = localStorage.getItem('shezhire_lang') || 'kk';

        let treeData = null;
        let selectedPersonId = userPersonId;
        let activeKinships = {}; // person_id -> { term_kk, term_ru, code, badge_class }

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
                mode_forest: "Шежіре-Орман",
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
                selected_badge: "Таңдалған адам",
                btn_demo_mode: "Демо",
                btn_demo_active: "🌟 Демо",
                empty_tree_title: "Деректер жоқ — GEDCOM импорттаңыз",
                empty_tree_desc: "Деректер қорында әлі тұлғалар жоқ. MyHeritage жүйесінен GEDCOM файлын импорттаңыз немесе танысу үшін демонстрациялық режимді көріңіз.",
                btn_import_gedcom: "📥 GEDCOM импорттау",
                btn_view_demo: "🌟 Демо-режимді көру",
                btn_return_real: "🌳 Негізгі базаға оралу",
            },
            ru: {
                back_to_portal: "Главная страница",
                brand_title_main: "Шежире",
                brand_title_sub: "Алтыбаевых",
                mode_editing: "Редактирование",
                mode_forest: "Шежіре-Орман",
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
                selected_badge: "Выбранный человек",
                btn_demo_mode: "Демо",
                btn_demo_active: "🌟 Демо",
                empty_tree_title: "Данных нет — импортируйте GEDCOM",
                empty_tree_desc: "В базе данных пока нет персон. Импортируйте файл GEDCOM из MyHeritage или включите демонстрационный режим для ознакомления.",
                btn_import_gedcom: "📥 Импортировать GEDCOM",
                btn_view_demo: "🌟 Включить демо-режим",
                btn_return_real: "🌳 Вернуться к реальной базе",
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
        let isDemoMode = new URLSearchParams(window.location.search).get('demo') === '1';

        function updateDemoButtonState() {
            const btn = document.getElementById('btnToggleDemo');
            const lbl = document.getElementById('demoLabel');
            const icon = document.getElementById('demoIcon');
            if (!btn) return;

            const isDemoActive = isDemoMode || (treeData && treeData.is_demo);
            if (isDemoActive) {
                btn.classList.add('active');
                if (lbl) lbl.innerText = i18n[currentLang].btn_demo_active;
                if (icon) icon.innerText = '🌟';
                btn.title = currentLang === 'ru' ? 'Включен демо-режим. Нажмите для возврата к базе.' : 'Демо-режим қосулы. Базаға оралу үшін басыңыз.';
            } else {
                btn.classList.remove('active');
                if (lbl) lbl.innerText = i18n[currentLang].btn_demo_mode;
                if (icon) icon.innerText = '🌟';
                btn.title = currentLang === 'ru' ? 'Включить демо-режим' : 'Демонстрациялық режимді қосу';
            }
        }

        function toggleDemoMode(forceState) {
            if (forceState !== undefined) {
                isDemoMode = !!forceState;
            } else {
                isDemoMode = !isDemoMode;
            }
            const url = new URL(window.location);
            if (isDemoMode) {
                url.searchParams.set('demo', '1');
            } else {
                url.searchParams.delete('demo');
            }
            window.history.replaceState({}, '', url);
            initTree(isDemoMode);
        }

        async function initTree(forceDemo) {
            if (forceDemo !== undefined) {
                isDemoMode = !!forceDemo;
            }
            setLanguage(currentLang);
            initEvents();

            try {
                const url = isDemoMode ? '/api/tree?demo=1' : '/api/tree';
                const res = await fetch(url);
                const data = await res.json();
                treeData = data;
                activeKinships = data.initial_kinships || {};

                updateDemoButtonState();
                renderEditingView();
                renderForestView();

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
                updateDemoButtonState();
                renderEditingView();
                renderForestView();
            }
        }

        // РЕЖИМДЕРДІ АУЫСТЫРУ («ӨҢДЕУ» <-> «ШЕЖІРЕ-ОРМАН»)
        function switchMode(mode) {
            currentMode = mode;
            const btnEditing = document.getElementById('btnModeEditing');
            const btnForest = document.getElementById('btnModeForest');
            const viewEditing = document.getElementById('editingView');
            const viewForest = document.getElementById('forestView');

            if (mode === 'editing') {
                btnEditing.classList.add('active');
                btnForest.classList.remove('active');
                viewEditing.style.display = 'block';
                viewForest.style.display = 'none';
                renderConnectionsEditing();
                if (treeData && treeData.persons && treeData.persons.length > 0) {
                    flyToPerson(selectedPersonId || userPersonId || treeData.persons[0].id);
                }
            } else {
                btnForest.classList.add('active');
                btnEditing.classList.remove('active');
                viewEditing.style.display = 'none';
                viewForest.style.display = 'block';
                // Шежіре-Орман ашылғанда бірден жоғарғы кронаға (тірілерге) барады!
                if (treeData && treeData.persons && treeData.persons.length > 0) {
                    flyToCrown();
                }
            }
        }

        // ========================================================
        // 1. «ӨҢДЕУ» РЕЖИМІН ДИНАМИКАЛЫҚ ҚҰРУ
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
                        <button type="button" onclick="toggleDemoMode(true)" class="btn-empty-action btn-demo">${i18n[currentLang].btn_view_demo}</button>
                    </div>
                `;
                container.appendChild(emptyCard);
                return;
            }

            // Иерархиялық орналасу картасы (буындар бойынша)
            const layoutMap = {
                1: { x: 2000, y: 1550 }, // Керей баба (корень)
                2: { x: 2000, y: 1250 }, // Самай би
                3: { x: 2000, y: 950 },  // Алтыбай баба
                4: { x: 1800, y: 680 },  // Сейітқали
                5: { x: 2120, y: 680 },  // Бибінұр (жұбайы, Жағалбайлы)
                6: { x: 1950, y: 420 },  // Қасым (әкесі)
                7: { x: 1820, y: 160 },  // Жандос (Сіз осындасыз)
                8: { x: 2140, y: 160 },  // Әлия (жұбайы)
                9: { x: 2520, y: 160 },  // Айнұр (қарындасы)
                10: { x: 1720, y: -100 }, // Арнұр (ұлы)
                11: { x: 2040, y: -100 }, // Медина (қызы)
                12: { x: 2840, y: 160 }, // Дәулет (күйеу бала, Қоңырат)
            };

            treeData.persons.forEach(p => {
                const pos = layoutMap[p.id] || { x: 1500 + (p.id % 6) * 280, y: 500 + Math.floor(p.id / 6) * 220 };
                const isMale = p.gender === 'M';
                const isDeceased = !!p.is_deceased;
                const isSelf = p.id === userPersonId;
                const isSelected = p.id === selectedPersonId;

                const card = document.createElement('div');
                card.className = `person-card ${isMale ? 'male' : 'female'} ${isDeceased ? 'deceased' : ''} ${isSelf ? 'is-self' : ''} ${isSelected ? 'selected' : ''}`;
                card.id = `card-${p.id}`;
                card.style.left = `${pos.x}px`;
                card.style.top = `${pos.y}px`;

                const initials = (p.first_name ? p.first_name[0] : '') + (p.last_name ? p.last_name[0] : '');

                // Туыстық атауын алу
                const kin = activeKinships[p.id] || { term_kk: 'Туысқан', term_ru: 'Родственник' };
                const kinLabel = currentLang === 'ru' ? (kin.term_ru || kin.term_kk) : kin.term_kk;

                // Батырмалар жазбалары
                const tFather = currentLang === 'ru' ? '+ Отец' : '+ Әке';
                const tMother = currentLang === 'ru' ? '+ Мать' : '+ Ана';
                const tSpouse = currentLang === 'ru' ? '+ Супруг(а)' : '+ Жұбай';
                const tSibling = currentLang === 'ru' ? '+ Брат/Сестра' : '+ Бауыр';
                const tSon = currentLang === 'ru' ? '+ Сын' : '+ Ұл';
                const tDaughter = currentLang === 'ru' ? '+ Дочь' : '+ Қыз';

                card.innerHTML = `
                    <div class="card-kinship-badge ${isSelf ? 'self-rel' : (isSelected ? 'active-rel' : '')}" id="kin-badge-${p.id}">
                        <span>${isSelf && !isSelected ? i18n[currentLang].you_are_here_badge : (isSelected ? i18n[currentLang].selected_badge : kinLabel)}</span>
                        ${isDeceased ? '<span>✨</span>' : '<span>🌿</span>'}
                    </div>

                    <div class="card-header-inner">
                        <div class="card-avatar">${initials}</div>
                        <div class="card-info">
                            <div class="card-name" title="${p.last_name || ''} ${p.first_name}">${p.last_name || ''} ${p.first_name}</div>
                            ${p.maiden_name ? `<div class="card-maiden">${currentLang === 'ru' ? 'девичья:' : 'қыз кезінде:'} ${p.maiden_name}</div>` : ''}
                            <div class="card-years">${p.birth_year || '—'} ${p.death_year ? '— ' + p.death_year : (p.is_deceased ? '†' : `(${currentLang === 'ru' ? 'жив' : 'тірі'})`)}</div>
                            <div class="card-place">${p.birth_place || ''}</div>
                        </div>
                    </div>
                    <div>
                        <span class="card-clan-tag">${p.clan_ru || 'Керей'}</span>
                    </div>

                    <!-- Карточканың айналасындағы жылдам қосу радиалды батырмалары -->
                    <div class="radial-action-wrap">
                        <button class="radial-btn radial-top-father" data-tooltip="${tFather}" onclick="event.stopPropagation(); openAddModal('father', ${p.id})">+</button>
                        <button class="radial-btn radial-top-mother" data-tooltip="${tMother}" onclick="event.stopPropagation(); openAddModal('mother', ${p.id})">+</button>
                        <button class="radial-btn radial-left-spouse" data-tooltip="${tSpouse}" onclick="event.stopPropagation(); openAddModal('spouse', ${p.id})">+</button>
                        <button class="radial-btn radial-right-sibling" data-tooltip="${tSibling}" onclick="event.stopPropagation(); openAddModal('sibling', ${p.id})">+</button>
                        <button class="radial-btn radial-bottom-son" data-tooltip="${tSon}" onclick="event.stopPropagation(); openAddModal('son', ${p.id})">+</button>
                        <button class="radial-btn radial-bottom-daughter" data-tooltip="${tDaughter}" onclick="event.stopPropagation(); openAddModal('daughter', ${p.id})">+</button>
                    </div>
                `;

                card.onclick = (e) => {
                    e.stopPropagation();
                    selectPerson(p.id);
                };

                container.appendChild(card);
            });

            renderConnectionsEditing();
        }

        // БАЙЛАНЫС ЖЕЛІЛЕРІН СЫЗУ (СВЯЗИ ПОКОЛЕНИЙ И БРАКОВ)
        function renderConnectionsEditing() {
            const svg = document.getElementById('svgConnections');
            svg.innerHTML = '';
            if (currentMode !== 'editing' || !treeData) return;

            // Әке мен балалар желілері
            treeData.persons.forEach(p => {
                if (p.father_id) {
                    const fatherCard = document.getElementById(`card-${p.father_id}`);
                    const childCard = document.getElementById(`card-${p.id}`);
                    if (fatherCard && childCard) {
                        const x1 = parseInt(fatherCard.style.left) + 125;
                        const y1 = parseInt(fatherCard.style.top) + 115;
                        const x2 = parseInt(childCard.style.left) + 125;
                        const y2 = parseInt(childCard.style.top);

                        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                        const midY = (y1 + y2) / 2;
                        path.setAttribute('d', `M ${x1} ${y1} C ${x1} ${midY}, ${x2} ${midY}, ${x2} ${y2}`);
                        path.setAttribute('stroke', '#64748b');
                        path.setAttribute('stroke-width', '2.5');
                        path.setAttribute('fill', 'none');
                        svg.appendChild(path);
                    }
                }
            });

            // Неке желілері (супруги)
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
                    line.setAttribute('stroke-width', '2.5');
                    line.setAttribute('stroke-dasharray', '5 4');
                    svg.appendChild(line);
                }
            });
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
                        <button type="button" onclick="toggleDemoMode(true)" class="btn-empty-action btn-demo">${i18n[currentLang].btn_view_demo}</button>
                    </div>
                `;
                container.appendChild(emptyCard);
                return;
            }

            // 1. ОРТАЛЫҚ БАСТЫ АҒАШ: КЕРЕЙ — АЛТЫБАЕВТАР ӘУЛЕТІ
            const mainStand = document.createElement('div');
            mainStand.className = 'clan-tree-stand stand-main-kerey';
            mainStand.id = 'standKerey';

            // КРОНА (ЖОҒАРҒЫ ҚАТАРДА ТІРІЛЕР — БАЛАЛАР МЕН НЕВЕСТКАЛАР)
            const crown = document.createElement('div');
            crown.className = 'organic-crown-container';
            crown.id = 'treeCrown';

            crown.innerHTML = `
                <div class="crown-living-banner">
                    <span>☀️</span>
                    <span>${i18n[currentLang].crown_title}</span>
                </div>

                <!-- 1. ЕҢ ЖАС ҰРПАҚ: АРНҰР (ҰЛ — ЛИСТИК) ЖӘНЕ МЕДИНА (ҚЫЗ — ГҮЛ) -->
                <div class="branch-children-row">
                    <div class="leaf-son-node" onclick="selectPerson(10)" title="Жас жапырақ (ұлы)">
                        <span>🍃</span>
                        <div><strong>Арнұр Алтыбаев</strong> (2014)</div>
                    </div>
                    <div class="flower-daughter-node" onclick="selectPerson(11)" title="Нәзік гүл (қызы)">
                        <span>🌸</span>
                        <div><strong>Медина Алтыбаева</strong> (2019)</div>
                    </div>
                </div>

                <!-- 2. ЖАНДОС ПЕН ӘЛИЯ ШАҢЫРАҒЫ (ВЕТКА: МУЖ + ЖЕНА РЯДОМ У ОСНОВАНИЯ) -->
                <div class="family-branch">
                    <div class="branch-couple-node is-self-node" onclick="selectPerson(7)">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span>🎯</span>
                            <div>
                                <strong>Жандос Алтыбаев</strong> (1985)
                                <div style="font-size:11px; color:#fef08a;">${i18n[currentLang].you_are_here_badge}</div>
                            </div>
                        </div>
                        <div style="color:#d4af37; font-weight:700;">&</div>
                        <div class="in-married-flower-node" onclick="event.stopPropagation(); selectPerson(8);" title="Жағалбайлы руынан келген берекелі келін">
                            <span>🌸</span>
                            <div>
                                <strong>Әлия</strong> (1987)
                                <div style="font-size:10px; color:#9d174d;">${i18n[currentLang].married_in_tag}</div>
                            </div>
                        </div>
                    </div>

                    <!-- ҰЗАТЫЛЫП КЕТКЕН ҚЫЗ АЙНҰР (СЛЕД УШЕДШЕЙ В РОД КОНЫРАТ) -->
                    <div style="margin-top:12px;">
                        <div class="out-married-trace-node" onclick="flyToKonyrat()" title="Қоңыратқа келін болып кетті (клик — оның ағашына ұшып бару)">
                            <span>🕊️</span>
                            <div>
                                <strong>Айнұр Алтыбаева</strong>
                                <div style="font-size:10px; color:#fbcfe8;">${i18n[currentLang].married_away_arrow}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. ӘКЕСІ ҚАСЫМ ШАҢЫРАҒЫ (ШЕЖІРЕ РЕДАКТОРЫ) -->
                <div class="family-branch">
                    <div class="branch-couple-node" onclick="selectPerson(6)">
                        <span>📜</span>
                        <div>
                            <strong>Қасым Сейітқалиұлы</strong> (1955)
                            <div style="font-size:11px; color:#86efac;">${currentLang === 'ru' ? 'Главный редактор (Отец)' : 'Бас редактор (Әкесі)'}</div>
                        </div>
                    </div>
                </div>
            `;
            mainStand.appendChild(crown);

            // БИІК ҚАСИЕТТІ ДІҢГЕК (ОТ АЛТЫБАЯ ДО САМАЯ — БІРТҰТАС ПРЯМАЯ ЛИНИЯ)
            const trunk = document.createElement('div');
            trunk.className = 'organic-trunk';

            trunk.innerHTML = `
                <div class="trunk-ancestor-node" onclick="selectPerson(4)">
                    ✨ Сейітқали Алтыбаев (1898 — 1972)
                </div>
                <div class="trunk-ancestor-node" onclick="selectPerson(3)" style="border-color:#f59e0b; background:#29180c; color:#fef08a;">
                    👑 Алтыбай Баба (ок. 1795) — Ру басы
                </div>
                <div class="trunk-ancestor-node" onclick="selectPerson(2)">
                    📜 Самай Би (ок. 1620)
                </div>
            `;
            mainStand.appendChild(trunk);

            // ҚАСИЕТТІ ТҮП ТАМЫР (КЕРЕЙ БАБА В САМОМ НИЗУ В ЗЕМЛЕ)
            const root = document.createElement('div');
            root.className = 'tree-sacred-root';
            root.id = 'treeRoot';

            root.innerHTML = `
                <div class="root-ancestor-badge" onclick="selectPerson(1)">
                    <span style="font-size:22px;">🌱</span>
                    <div>
                        <div>КЕРЕЙ БАБА (ок. 1380)</div>
                        <div style="font-size:11px; color:#d4af37; font-weight:600;">
                            ${currentLang === 'ru' ? 'Священный корень рода в земле (Тамга: Ашамай, Уран: Ошыбай!)' : 'Шежіренің ең түпкі қасиетті тамыры (Таңба: Ашамай, Ұран: Ошыбай!)'}
                        </div>
                    </div>
                </div>
            `;
            mainStand.appendChild(root);
            container.appendChild(mainStand);


            // 2. КӨРШІ АҒАШ: ЖАҒАЛБАЙЛЫ (СОЛ ЖАҚТАҒЫ ТОҒАЙ)
            const zhagalbaylyStand = document.createElement('div');
            zhagalbaylyStand.className = 'clan-tree-stand stand-clan-left';
            zhagalbaylyStand.id = 'standZhagalbayly';

            zhagalbaylyStand.innerHTML = `
                <div class="crown-living-banner" style="background:rgba(180,83,9,0.3); border-color:#d4af37; color:#fef08a;">
                    <span>🌳</span>
                    <span>${i18n[currentLang].clan_zhagalbayly_tree}</span>
                </div>
                <div style="font-size:12px; color:#cbd5e1; max-width:320px; text-align:center; margin:10px 0;">
                    ${currentLang === 'ru' ? 'Род наших матерей (Бибинур, Алия), пришедших в семью Алтыбаевых' : 'Алтыбаевтар әулетіне келін болып түскен аяулы аналарымыздың руы'}
                </div>
                <div class="branch-couple-node" onclick="selectPerson(5)">
                    <span>🌸</span>
                    <div>
                        <strong>Бибінұр Сейітқызы</strong> (1904 — 1984)
                        <div style="font-size:10px; color:#fef08a;">${currentLang === 'ru' ? 'Золотая память матери' : 'Аналардың алтын естелігі'}</div>
                    </div>
                </div>
            `;
            container.appendChild(zhagalbaylyStand);


            // 3. КӨРШІ АҒАШ: ҚОҢЫРАТ (ОҢ ЖАҚТАҒЫ ТОҒАЙ)
            const konyratStand = document.createElement('div');
            konyratStand.className = 'clan-tree-stand stand-clan-right';
            konyratStand.id = 'standKonyrat';

            konyratStand.innerHTML = `
                <div class="crown-living-banner" style="background:rgba(30,58,138,0.3); border-color:#38bdf8; color:#bae6fd;">
                    <span>🌳</span>
                    <span>${i18n[currentLang].clan_konyrat_tree}</span>
                </div>
                <div style="font-size:12px; color:#cbd5e1; max-width:320px; text-align:center; margin:10px 0;">
                    ${currentLang === 'ru' ? 'Священный род Конырат, куда ушла невестой дочь Айнур' : 'Қасымның қызы Айнұр ұзатылып барған құрметті құдалар ағашы'}
                </div>
                <div style="display:flex; gap:12px; align-items:center;">
                    <div class="leaf-son-node" onclick="selectPerson(12)">
                        <span>🌿</span>
                        <div><strong>Дәулет Төлегенов</strong> (1987)</div>
                    </div>
                    <div class="flower-daughter-node" onclick="selectPerson(9)">
                        <span>🌸</span>
                        <div>
                            <strong>Айнұр</strong>
                            <div style="font-size:10px; color:#fbcfe8;">← ${currentLang === 'ru' ? 'Невестка из рода Керей' : 'Керейден келген келін'}</div>
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(konyratStand);
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
            document.getElementById('panelPersonName').innerText = `${p.last_name || ''} ${p.first_name} ${p.middle_name || ''}`;
            document.getElementById('panelPersonClan').innerText = p.clan_ru || 'Керей';
            document.getElementById('panelGender').innerText = p.gender === 'M' ? (currentLang === 'ru' ? 'Мужской' : 'Ер азамат') : (currentLang === 'ru' ? 'Женский' : 'Әйел адам');
            document.getElementById('panelYears').innerText = `${p.birth_year || '—'} ${p.death_year ? '— ' + p.death_year : ''}`;
            document.getElementById('panelPlace').innerText = p.birth_place || 'ШҚО';
            document.getElementById('panelStatus').innerText = p.is_deceased ? (currentLang === 'ru' ? 'Умер(ла)' : 'Дүниеден өткен') : (currentLang === 'ru' ? 'Жив(а)' : 'Тірі');
            document.getElementById('panelBiography').innerText = p.biography || (currentLang === 'ru' ? 'Биография не заполнена.' : 'Өмірбаяны жазылмаған.');

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

            openSidePanel();
        }

        // БАРЛЫҚ КАРТОЧКАЛАРДЫҢ ТУЫСТЫҚ АТАУЛАРЫН ТАҢДАЛҒАН ТҰЛҒАҒА ҚАТЫСТЫ ҚАЙТА ЕСЕПТЕУ
        async function updateAllKinshipBadges(baseId) {
            if (!treeData || !treeData.persons || treeData.persons.length === 0) return;

            // Серверден барлық туыстықты бірден сұрау (кэштей отырып)
            try {
                const res = await fetch(`/api/kinship-all?base_id=${baseId}` + (isDemoMode ? '&demo=1' : ''));
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
                    const res = await fetch(`/api/tree/search?q=${encodeURIComponent(q)}` + (isDemoMode ? '&demo=1' : ''));
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
            const isDeceased = document.getElementById('formIsDeceased').value === '1';
            document.getElementById('deathYearGroup').style.display = isDeceased ? 'block' : 'none';
        }

        function handleSavePerson(e) {
            e.preventDefault();
            const relType = document.getElementById('formRelationType').value;
            const baseId = parseInt(document.getElementById('formBasePersonId').value) || selectedPersonId;

            const newPerson = {
                id: treeData.persons.length + 1,
                first_name: document.getElementById('formFirstName').value,
                last_name: document.getElementById('formLastName').value,
                middle_name: document.getElementById('formMiddleName').value,
                maiden_name: document.getElementById('formMaidenName').value,
                gender: document.getElementById('formGender').value,
                birth_year: document.getElementById('formBirthYear').value,
                is_deceased: parseInt(document.getElementById('formIsDeceased').value),
                death_year: document.getElementById('formDeathYear').value,
                birth_place: document.getElementById('formBirthPlace').value,
                biography: document.getElementById('formBiography').value,
                clan_ru: 'Керей',
            };

            // Байланыстарды реттеу
            if (relType === 'father') {
                const base = treeData.persons.find(x => x.id === baseId);
                if (base) base.father_id = newPerson.id;
            } else if (relType === 'mother') {
                const base = treeData.persons.find(x => x.id === baseId);
                if (base) base.mother_id = newPerson.id;
            } else if (relType === 'son' || relType === 'daughter' || relType === 'child') {
                const base = treeData.persons.find(x => x.id === baseId);
                if (base && base.gender === 'M') {
                    newPerson.father_id = base.id;
                } else if (base) {
                    newPerson.mother_id = base.id;
                }
            } else if (relType === 'spouse') {
                const base = treeData.persons.find(x => x.id === baseId);
                if (base && base.gender === 'M') {
                    treeData.marriages.push({ id: treeData.marriages.length + 1, husband_id: base.id, wife_id: newPerson.id });
                } else if (base) {
                    treeData.marriages.push({ id: treeData.marriages.length + 1, husband_id: newPerson.id, wife_id: base.id });
                }
            }

            treeData.persons.push(newPerson);
            closeAddModal();

            renderEditingView();
            renderForestView();
            flyToPerson(newPerson.id);
        }

        // БАСТАУ
        window.addEventListener('DOMContentLoaded', initTree);
    </script>
</body>
</html>
