<?php
session_start();
if (!isset($_SESSION['utente_id'])) {
    header('Location: /palestra/view/login.php');
    exit();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/palestra/config/database.php';

$uid = (int)$_SESSION['utente_id'];
$db = connetti();

// Recupera tutti gli allenamenti con data
$stmt = $db->prepare("
    SELECT a.id_allenamento, a.data, COUNT(se.id) AS esercizi 
    FROM allenamenti a
    LEFT JOIN sessione_esercizi se ON a.id_allenamento = se.id_allenamento
    WHERE a.id_utente = ?
    GROUP BY a.id_allenamento
    ORDER BY a.data ASC
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$allenamenti = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Crea un array associativo data => dettagli
$eventi = [];
foreach ($allenamenti as $a) {
    $eventi[$a['data']] = [
        'id' => $a['id_allenamento'],
        'esercizi' => $a['esercizi']
    ];
}

$mese = $_GET['mese'] ?? date('n');
$anno = $_GET['anno'] ?? date('Y');
$primo_giorno = mktime(0, 0, 0, $mese, 1, $anno);
$giorni_mese = date('t', $primo_giorno);
$inizio_settimana = date('N', $primo_giorno); // 1=Lunedì

$giorni_settimana = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario Allenamenti - GymApp</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --accent: #E8FF00;
            --dark: #0a0a0a;
            --dark2: #111111;
            --border: rgba(255,255,255,0.07);
            --text: #f0f0f0;
            --muted: #666;
            --radius: 16px;
        }
        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--dark);
            color: var(--text);
        }
        /* Sidebar (stessa identica agli altri file) */
        .sidebar {
            position: fixed; top: 0; left: 0;
            width: 72px; height: 100vh;
            background: var(--dark2);
            border-right: 1px solid var(--border);
            display: flex; flex-direction: column;
            align-items: center;
            padding: 24px 0;
            z-index: 100;
            transition: width 0.3s;
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
            color: #999;
            text-decoration: none;
            font-size: 0.88rem;
            border-left: 2px solid transparent;
        }
        .nav-item:hover { color: var(--text); background: rgba(255,255,255,0.04); }
        .nav-icon { font-size: 1.2rem; min-width: 24px; }
        .nav-label { opacity: 0; transition: opacity 0.2s; }
        .sidebar:hover .nav-label { opacity: 1; }
        .nav-spacer { flex: 1; }
        .main { margin-left: 72px; padding: 32px; }
        
        .calendario {
            background: var(--dark2);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            max-width: 800px;
            margin: 0 auto;
        }
        .cal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .cal-header h2 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.8rem;
            letter-spacing: 2px;
        }
        .cal-header a {
            color: var(--accent);
            text-decoration: none;
            padding: 6px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
        }
        .weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            text-align: center;
            font-weight: 600;
            color: var(--accent);
            margin-bottom: 12px;
        }
        .days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }
        .day {
            background: var(--dark3);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px 8px;
            text-align: center;
            min-height: 80px;
            position: relative;
            transition: all 0.2s;
        }
        .day.empty {
            background: transparent;
            border-color: transparent;
        }
        .day-number {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .workout-badge {
            background: rgba(232,255,0,0.15);
            border: 1px solid rgba(232,255,0,0.3);
            border-radius: 20px;
            padding: 4px 8px;
            font-size: 0.7rem;
            color: var(--accent);
            text-decoration: none;
            display: inline-block;
            margin-top: 4px;
        }
        .workout-badge:hover {
            background: var(--accent);
            color: #000;
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
    <a href="/palestra/view/scheda.php" class="nav-item">
        <span class="nav-icon">📋</span>
        <span class="nav-label">La mia scheda</span>
    </a>
    <a href="/palestra/view/allenamento.php" class="nav-item">
        <span class="nav-icon">▶️</span>
        <span class="nav-label">Avvia allenamento</span>
    </a>
    <a href="/palestra/view/progressi.php" class="nav-item">
        <span class="nav-icon">📊</span>
        <span class="nav-label">Progressi</span>
    </a>

     <a href="/palestra/view/calendario.php" class="nav-item">
    <span class="nav-icon">📅</span>
    <span class="nav-label">Calendario</span>
    </a>

    <a href="/palestra/view/profilo.php" class="nav-item">
        <span class="nav-icon">👤</span>
        <span class="nav-label">Profilo</span>
    </a>

    <div class="nav-spacer"></div>

    <a href="/palestra/controller/LogoutController.php" class="nav-item">
        <span class="nav-icon">🚪</span>
        <span class="nav-label">Esci</span>
    </a>
    
</nav>

<main class="main">
    <div class="calendario">
        <div class="cal-header">
            <a href="?mese=<?= $mese-1 < 1 ? 12 : $mese-1 ?>&anno=<?= $mese-1 < 1 ? $anno-1 : $anno ?>">←</a>
            <h2><?= date('F Y', $primo_giorno) ?></h2>
            <a href="?mese=<?= $mese+1 > 12 ? 1 : $mese+1 ?>&anno=<?= $mese+1 > 12 ? $anno+1 : $anno ?>">→</a>
        </div>
        <div class="weekdays">
            <?php foreach ($giorni_settimana as $g): ?>
                <div><?= $g ?></div>
            <?php endforeach; ?>
        </div>
        <div class="days">
            <?php
            $giorno_corrente = 1;
            // celle vuote prima del primo giorno
            for ($i = 1; $i < $inizio_settimana; $i++) {
                echo '<div class="day empty"></div>';
            }
            for ($d = 1; $d <= $giorni_mese; $d++) {
                $data_iso = date('Y-m-d', mktime(0,0,0,$mese,$d,$anno));
                $has_workout = isset($eventi[$data_iso]);
                ?>
                <div class="day">
                    <div class="day-number"><?= $d ?></div>
                    <?php if ($has_workout): ?>
                        <a href="/palestra/view/dettaglio_allenamento.php?id=<?= $eventi[$data_iso]['id'] ?>" class="workout-badge">
                            🏋️ <?= $eventi[$data_iso]['esercizi'] ?> ex
                        </a>
                    <?php endif; ?>
                </div>
                <?php
            }
            ?>
        </div>
    </div>
</main>
</body>
</html>