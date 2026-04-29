<?php
session_start();

if (!isset($_SESSION['utente_id'])) {
    header('Location: /palestra/view/login.php');
    exit();
}

$nome             = htmlspecialchars($_SESSION['nome']);
$email            = htmlspecialchars($_SESSION['email']);
$obiettivo        = $_SESSION['obiettivo'] ?? '';
$giorni           = $_SESSION['giorni_settimana'] ?? 3;

// Etichette obiettivo
$obiettivo_label = [
    'perdita_peso'    => 'Perdita Peso',
    'massa_muscolare' => 'Massa Muscolare',
    'tonificazione'   => 'Tonificazione',
][$obiettivo] ?? 'Non impostato';

$obiettivo_icon = [
    'perdita_peso'    => '🔥',
    'massa_muscolare' => '💪',
    'tonificazione'   => '⚡',
][$obiettivo] ?? '🎯';

// Giorno della settimana corrente
$giorni_settimana = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
$oggi = $giorni_settimana[date('w')];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GymApp — Home</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,300&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --accent:   #E8FF00;
            --accent2:  #FF4D00;
            --dark:     #0a0a0a;
            --dark2:    #111111;
            --dark3:    #1a1a1a;
            --border:   rgba(255,255,255,0.07);
            --text:     #f0f0f0;
            --muted:    #666;
            --muted2:   #999;
            --radius:   16px;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--dark);
            color: var(--text);
            min-height: 100vh;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            position: fixed; top: 0; left: 0;
            width: 72px; height: 100vh;
            background: var(--dark2);
            border-right: 1px solid var(--border);
            display: flex; flex-direction: column;
            align-items: center;
            padding: 24px 0;
            z-index: 100;
            transition: width 0.3s cubic-bezier(.16,1,.3,1);
        }
        .sidebar:hover { width: 220px; }

        .sidebar-logo {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.5rem;
            letter-spacing: 3px;
            color: var(--accent);
            white-space: nowrap;
            overflow: hidden;
            width: 100%;
            text-align: center;
            padding: 0 16px;
            margin-bottom: 32px;
        }

        .nav-item {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 22px;
            color: var(--muted2);
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 500;
            transition: all 0.2s;
            white-space: nowrap;
            overflow: hidden;
            border-left: 2px solid transparent;
        }
        .nav-item:hover {
            color: var(--text);
            background: rgba(255,255,255,0.04);
        }
        .nav-item.active {
            color: var(--accent);
            border-left-color: var(--accent);
            background: rgba(232,255,0,0.05);
        }
        .nav-icon { font-size: 1.2rem; min-width: 24px; text-align: center; }
        .nav-label { opacity: 0; transition: opacity 0.2s; }
        .sidebar:hover .nav-label { opacity: 1; }

        .nav-spacer { flex: 1; }

        /* ── MAIN ── */
        .main {
            margin-left: 72px;
            min-height: 100vh;
            padding: 32px;
        }

        /* ── TOPBAR ── */
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 36px;
        }
        .topbar-left h1 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 2rem;
            letter-spacing: 2px;
            color: var(--text);
            line-height: 1;
        }
        .topbar-left p {
            font-size: 0.85rem;
            color: var(--muted2);
            margin-top: 4px;
        }
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .avatar {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: var(--accent);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.1rem;
            color: #000;
            cursor: pointer;
        }
        .btn-logout {
            padding: 8px 18px;
            background: transparent;
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--muted2);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.82rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-logout:hover {
            border-color: var(--accent2);
            color: var(--accent2);
        }

        /* ── HERO BANNER ── */
        .hero {
            position: relative;
            border-radius: 20px;
            overflow: hidden;
            padding: 40px 40px;
            margin-bottom: 28px;
            background: linear-gradient(135deg, #111 0%, #1a1a1a 100%);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            animation: fadeUp 0.5s both;
        }
        .hero::before {
            content: '';
            position: absolute; inset: 0;
            background: url('https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=1200&q=60') center/cover;
            opacity: 0.08;
        }
        .hero-text { position: relative; z-index: 1; }
        .hero-greeting {
            font-size: 0.8rem;
            color: var(--accent);
            letter-spacing: 3px;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .hero-name {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 3.5rem;
            letter-spacing: 3px;
            line-height: 1;
            margin-bottom: 12px;
        }
        .hero-sub {
            font-size: 0.9rem;
            color: var(--muted2);
        }
        .hero-badge {
            position: relative; z-index: 1;
            text-align: center;
        }
        .hero-badge-icon { font-size: 4rem; line-height: 1; }
        .hero-badge-label {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.1rem;
            letter-spacing: 2px;
            color: var(--accent);
            margin-top: 8px;
        }
        .hero-badge-sub {
            font-size: 0.75rem;
            color: var(--muted2);
            margin-top: 2px;
        }

        /* ── STATS GRID ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: var(--dark2);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            animation: fadeUp 0.5s both;
            transition: border-color 0.2s, transform 0.2s;
            cursor: default;
        }
        .stat-card:hover {
            border-color: rgba(232,255,0,0.2);
            transform: translateY(-2px);
        }
        .stat-card:nth-child(1) { animation-delay: 0.05s; }
        .stat-card:nth-child(2) { animation-delay: 0.1s; }
        .stat-card:nth-child(3) { animation-delay: 0.15s; }
        .stat-card:nth-child(4) { animation-delay: 0.2s; }

        .stat-label {
            font-size: 0.72rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .stat-value {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 2.6rem;
            letter-spacing: 1px;
            line-height: 1;
            color: var(--text);
        }
        .stat-value span { font-size: 1rem; color: var(--muted2); margin-left: 4px; }
        .stat-icon {
            font-size: 1.4rem;
            margin-bottom: 8px;
        }
        .stat-delta {
            font-size: 0.78rem;
            color: var(--muted2);
            margin-top: 6px;
        }
        .stat-delta.up { color: #4eebb0; }
        .stat-delta.down { color: var(--accent2); }

        /* ── BOTTOM GRID ── */
        .bottom-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .card {
            background: var(--dark2);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 28px;
            animation: fadeUp 0.5s both;
        }
        .card:nth-child(1) { animation-delay: 0.25s; }
        .card:nth-child(2) { animation-delay: 0.3s; }

        .card-title {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.1rem;
            letter-spacing: 2px;
            color: var(--text);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-title-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--accent);
        }

        /* Scheda del giorno */
        .workout-day {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .workout-day-name {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.6rem;
            letter-spacing: 2px;
            color: var(--accent);
        }
        .workout-day-label {
            font-size: 0.78rem;
            color: var(--muted2);
        }

        .exercise-list { display: flex; flex-direction: column; gap: 10px; }
        .exercise-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            background: var(--dark3);
            border-radius: 10px;
            border: 1px solid var(--border);
            transition: border-color 0.2s;
        }
        .exercise-item:hover { border-color: rgba(232,255,0,0.15); }
        .exercise-num {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.1rem;
            color: var(--muted);
            min-width: 24px;
        }
        .exercise-info { flex: 1; }
        .exercise-name {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text);
        }
        .exercise-detail {
            font-size: 0.76rem;
            color: var(--muted2);
            margin-top: 2px;
        }
        .exercise-muscle {
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 20px;
            background: rgba(232,255,0,0.08);
            color: var(--accent);
            border: 1px solid rgba(232,255,0,0.15);
        }

        .btn-start {
            width: 100%;
            margin-top: 20px;
            padding: 14px;
            background: var(--accent);
            color: #000;
            border: none;
            border-radius: 10px;
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.1rem;
            letter-spacing: 2px;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.2s;
            text-decoration: none;
            display: block;
            text-align: center;
        }
        .btn-start:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(232,255,0,0.3);
        }

        /* Storico allenamenti */
        .history-list { display: flex; flex-direction: column; gap: 10px; }
        .history-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            background: var(--dark3);
            border-radius: 10px;
            border: 1px solid var(--border);
        }
        .history-date {
            text-align: center;
            min-width: 44px;
        }
        .history-date-day {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.4rem;
            line-height: 1;
            color: var(--text);
        }
        .history-date-month {
            font-size: 0.65rem;
            color: var(--muted2);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .history-divider {
            width: 1px; height: 36px;
            background: var(--border);
        }
        .history-info { flex: 1; }
        .history-name {
            font-size: 0.88rem;
            font-weight: 500;
            color: var(--text);
        }
        .history-meta {
            font-size: 0.75rem;
            color: var(--muted2);
            margin-top: 2px;
        }
        .history-badge {
            font-size: 0.7rem;
            padding: 4px 10px;
            border-radius: 20px;
            background: rgba(78,235,176,0.1);
            color: #4eebb0;
            border: 1px solid rgba(78,235,176,0.2);
        }

        .empty-state {
            text-align: center;
            padding: 32px;
            color: var(--muted);
        }
        .empty-state .empty-icon { font-size: 2.5rem; margin-bottom: 10px; }
        .empty-state p { font-size: 0.85rem; }

        /* ── ANIMAZIONI ── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 900px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .bottom-grid { grid-template-columns: 1fr; }
            .hero { flex-direction: column; gap: 20px; text-align: center; }
            .hero-name { font-size: 2.5rem; }
        }
        @media (max-width: 600px) {
            .main { padding: 20px 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .hero { padding: 28px 24px; }
        }
    </style>
</head>
<body>

<!-- ── SIDEBAR ── -->
<nav class="sidebar">
    <div class="sidebar-logo">G<span style="opacity:0;transition:opacity 0.2s" class="logo-rest">ymApp</span></div>

    <a href="/palestra/view/dashboard.php" class="nav-item active">
        <span class="nav-icon">🏠</span>
        <span class="nav-label">Home</span>
    </a>
    <a href="#" class="nav-item">
        <span class="nav-icon">📋</span>
        <span class="nav-label">La mia scheda</span>
    </a>
    <a href="#" class="nav-item">
        <span class="nav-icon">▶️</span>
        <span class="nav-label">Avvia allenamento</span>
    </a>
    <a href="#" class="nav-item">
        <span class="nav-icon">📊</span>
        <span class="nav-label">Progressi</span>
    </a>
    <a href="#" class="nav-item">
        <span class="nav-icon">👤</span>
        <span class="nav-label">Profilo</span>
    </a>

    <div class="nav-spacer"></div>

    <a href="/palestra/controller/LogoutController.php" class="nav-item">
        <span class="nav-icon">🚪</span>
        <span class="nav-label">Esci</span>
    </a>
</nav>

<!-- ── MAIN ── -->
<main class="main">

    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-left">
            <h1>Dashboard</h1>
            <p><?= $oggi ?>, <?= date('d F Y') ?></p>
        </div>
        <div class="topbar-right">
            <div class="avatar"><?= strtoupper(substr($nome, 0, 1)) ?></div>
            <a href="/palestra/controller/LogoutController.php" class="btn-logout">Esci</a>
        </div>
    </div>

    <!-- Hero -->
    <div class="hero">
        <div class="hero-text">
            <div class="hero-greeting">Bentornato</div>
            <div class="hero-name"><?= strtoupper($nome) ?></div>
            <div class="hero-sub">
                Oggi è <?= $oggi ?> — è il momento di allenarti! 💪
            </div>
        </div>
        <div class="hero-badge">
            <div class="hero-badge-icon"><?= $obiettivo_icon ?></div>
            <div class="hero-badge-label"><?= $obiettivo_label ?></div>
            <div class="hero-badge-sub"><?= $giorni ?> giorni/settimana</div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">🏋️</div>
            <div class="stat-label">Allenamenti Totali</div>
            <div class="stat-value">0<span>sessioni</span></div>
            <div class="stat-delta">Inizia il tuo primo!</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🔥</div>
            <div class="stat-label">Streak Attuale</div>
            <div class="stat-value">0<span>giorni</span></div>
            <div class="stat-delta">Costruisci la tua serie</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <div class="stat-label">Questo Mese</div>
            <div class="stat-value">0<span>sessioni</span></div>
            <div class="stat-delta">Obiettivo: <?= $giorni * 4 ?> sessioni</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏱️</div>
            <div class="stat-label">Ore Totali</div>
            <div class="stat-value">0<span>h</span></div>
            <div class="stat-delta">Inizia ad allenarti</div>
        </div>
    </div>

    <!-- Bottom grid -->
    <div class="bottom-grid">

        <!-- Scheda del giorno -->
        <div class="card">
            <div class="card-title">
                <div class="card-title-dot"></div>
                SCHEDA DI OGGI
            </div>

            <div class="workout-day">
                <div>
                    <div class="workout-day-name"><?= strtoupper($oggi) ?></div>
                    <div class="workout-day-label">Giorno di allenamento</div>
                </div>
                <div style="font-size:2rem">💪</div>
            </div>

            <?php
            // Esercizi di esempio in base all'obiettivo
            $esercizi_esempio = [
                'perdita_peso' => [
                    ['Burpees', '4x15', 'Corpo completo'],
                    ['Squat con salto', '4x12', 'Gambe'],
                    ['Mountain Climbers', '3x30s', 'Core'],
                    ['Corsa sul posto', '5x1min', 'Cardio'],
                ],
                'massa_muscolare' => [
                    ['Panca Piana', '4x8', 'Petto'],
                    ['Squat', '4x6', 'Gambe'],
                    ['Stacchi', '3x6', 'Schiena'],
                    ['Military Press', '3x8', 'Spalle'],
                ],
                'tonificazione' => [
                    ['Affondi', '3x12', 'Gambe'],
                    ['Push-up', '3x15', 'Petto'],
                    ['Plank', '3x45s', 'Core'],
                    ['Rematore con manubri', '3x12', 'Schiena'],
                ],
            ];
            $esercizi = $esercizi_esempio[$obiettivo] ?? $esercizi_esempio['tonificazione'];
            ?>

            <div class="exercise-list">
                <?php foreach ($esercizi as $i => $ex): ?>
                <div class="exercise-item">
                    <div class="exercise-num"><?= $i + 1 ?></div>
                    <div class="exercise-info">
                        <div class="exercise-name"><?= $ex[0] ?></div>
                        <div class="exercise-detail"><?= $ex[1] ?></div>
                    </div>
                    <div class="exercise-muscle"><?= $ex[2] ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <a href="#" class="btn-start">▶ AVVIA ALLENAMENTO</a>
        </div>

        <!-- Storico allenamenti -->
        <div class="card">
            <div class="card-title">
                <div class="card-title-dot"></div>
                ULTIMI ALLENAMENTI
            </div>

            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <p>Nessun allenamento registrato ancora.<br>Avvia la tua prima sessione!</p>
            </div>

            <!-- Quando ci saranno allenamenti nel DB, verranno mostrati qui -->
            <!--
            <div class="history-list">
                <div class="history-item">
                    <div class="history-date">
                        <div class="history-date-day">28</div>
                        <div class="history-date-month">Apr</div>
                    </div>
                    <div class="history-divider"></div>
                    <div class="history-info">
                        <div class="history-name">Allenamento Petto</div>
                        <div class="history-meta">4 esercizi · 52 minuti</div>
                    </div>
                    <div class="history-badge">✓ Completo</div>
                </div>
            </div>
            -->
        </div>

    </div>

</main>

<script>
// Anima logo sidebar all'hover
const sidebar = document.querySelector('.sidebar');
const logoRest = document.querySelector('.logo-rest');
sidebar.addEventListener('mouseenter', () => logoRest.style.opacity = '1');
sidebar.addEventListener('mouseleave', () => logoRest.style.opacity = '0');
</script>

</body>
</html>
