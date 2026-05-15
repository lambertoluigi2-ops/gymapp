<?php
session_start();
if (!isset($_SESSION['utente_id'])) {
    header('Location: /palestra/view/login.php');
    exit();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/palestra/config/database.php';

$uid = (int)$_SESSION['utente_id'];
$id_allenamento = (int)($_GET['id'] ?? 0);
$db = connetti();

// Recupera info allenamento
$stmt = $db->prepare("
    SELECT a.id_allenamento, a.data, a.ora_inizio, a.ora_fine, a.durata_sec,
           s.nome AS nome_scheda
    FROM allenamenti a
    LEFT JOIN schede s ON a.id_scheda = s.id_scheda
    WHERE a.id_allenamento = ? AND a.id_utente = ?
");
$stmt->bind_param("ii", $id_allenamento, $uid);
$stmt->execute();
$allenamento = $stmt->get_result()->fetch_assoc();

if (!$allenamento) {
    header('Location: /palestra/view/progressi.php');
    exit();
}

// Recupera esercizi svolti in questo allenamento
$stmt2 = $db->prepare("
    SELECT e.nome, e.gruppo_muscolare, se.serie_eseguite, se.ripetizioni_fatte, se.peso_usato
    FROM sessione_esercizi se
    JOIN esercizi e ON se.id_esercizio = e.id_esercizio
    WHERE se.id_allenamento = ?
    ORDER BY se.serie_eseguite, se.id
");
$stmt2->bind_param("i", $id_allenamento);
$stmt2->execute();
$esercizi = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

// Raggruppa per esercizio
$esercizi_raggruppati = [];
foreach ($esercizi as $ex) {
    $nome = $ex['nome'];
    if (!isset($esercizi_raggruppati[$nome])) {
        $esercizi_raggruppati[$nome] = [
            'gruppo' => $ex['gruppo_muscolare'],
            'serie' => []
        ];
    }
    $esercizi_raggruppati[$nome]['serie'][] = [
        'serie_num' => $ex['serie_eseguite'],
        'ripetizioni' => $ex['ripetizioni_fatte'],
        'peso' => $ex['peso_usato']
    ];
}

$durata_min = $allenamento['durata_sec'] ? round($allenamento['durata_sec'] / 60) : 0;
$giorni_settimana = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
$giorno_nome = $giorni_settimana[date('w', strtotime($allenamento['data']))];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dettaglio Allenamento — GymApp</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --accent: #E8FF00;
            --accent2: #FF4D00;
            --dark: #0a0a0a;
            --dark2: #111111;
            --dark3: #1a1a1a;
            --border: rgba(255,255,255,0.07);
            --text: #f0f0f0;
            --muted: #666;
            --radius: 16px;
        }
        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--dark);
            color: var(--text);
            min-height: 100vh;
        }
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
            text-align: center;
            margin-bottom: 32px;
        }
        .nav-item {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 22px;
            color: var(--muted);
            text-decoration: none;
            font-size: 0.88rem;
            border-left: 2px solid transparent;
        }
        .nav-item:hover { color: var(--text); background: rgba(255,255,255,0.04); }
        .nav-item.active {
            color: var(--accent);
            border-left-color: var(--accent);
            background: rgba(232,255,0,0.05);
        }
        .nav-icon { font-size: 1.2rem; min-width: 24px; }
        .nav-label { opacity: 0; transition: opacity 0.2s; }
        .sidebar:hover .nav-label { opacity: 1; }
        .nav-spacer { flex: 1; }
        .main { margin-left: 72px; min-height: 100vh; padding: 32px; }
        
        .topbar { margin-bottom: 36px; }
        .topbar h1 { font-family: 'Bebas Neue', sans-serif; font-size: 2rem; letter-spacing: 2px; }
        .topbar p { font-size: 0.85rem; color: #888; margin-top: 4px; }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--accent);
            text-decoration: none;
            margin-bottom: 20px;
            font-size: 0.85rem;
        }
        .workout-header {
            background: var(--dark2);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px 32px;
            margin-bottom: 24px;
        }
        .workout-date {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 2rem;
            letter-spacing: 2px;
            color: var(--accent);
        }
        .workout-meta {
            display: flex;
            gap: 24px;
            margin-top: 16px;
            color: var(--muted);
            font-size: 0.85rem;
        }
        .exercise-card {
            background: var(--dark2);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-bottom: 16px;
            overflow: hidden;
        }
        .exercise-header {
            padding: 20px 24px;
            background: var(--dark3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }
        .exercise-name {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.3rem;
            letter-spacing: 1px;
        }
        .exercise-muscle {
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: 20px;
            background: rgba(232,255,0,0.1);
            color: var(--accent);
        }
        .exercise-stats {
            font-size: 0.8rem;
            color: var(--muted);
        }
        .serie-table {
            padding: 0 24px 20px 24px;
            display: none;
        }
        .serie-table.open {
            display: block;
        }
        .serie-row {
            display: grid;
            grid-template-columns: 80px 1fr 1fr 1fr;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        .serie-header {
            color: var(--accent);
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
        }
        .toggle-icon {
            font-size: 1.2rem;
            transition: transform 0.2s;
        }
        .toggle-icon.rotated {
            transform: rotate(90deg);
        }
    </style>
</head>
<body>

<nav class="sidebar">
    <div class="sidebar-logo">GymApp</div>
    <a href="/palestra/view/dashboard.php" class="nav-item">
        <span class="nav-icon">🏠</span><span class="nav-label">Home</span>
    </a>
    <a href="/palestra/view/scheda.php" class="nav-item">
        <span class="nav-icon">📋</span><span class="nav-label">La mia scheda</span>
    </a>
    <a href="/palestra/view/allenamento.php" class="nav-item">
        <span class="nav-icon">▶️</span><span class="nav-label">Avvia allenamento</span>
    </a>
    <a href="/palestra/view/progressi.php" class="nav-item active">
        <span class="nav-icon">📊</span><span class="nav-label">Progressi</span>
    </a>

    <a href="/palestra/view/calendario.php" class="nav-item">
    <span class="nav-icon">📅</span>
    <span class="nav-label">Calendario</span>
    </a>

    <a href="/palestra/view/profilo.php" class="nav-item">
        <span class="nav-icon">👤</span><span class="nav-label">Profilo</span>
    </a>
    <div class="nav-spacer"></div>
    <a href="/palestra/controller/LogoutController.php" class="nav-item">
        <span class="nav-icon">🚪</span><span class="nav-label">Esci</span>
    </a>
</nav>

<main class="main">
    <a href="/palestra/view/progressi.php" class="back-btn">← Torna ai Progressi</a>

    <div class="workout-header">
        <div class="workout-date">
            <?= $giorno_nome ?> <?= date('d F Y', strtotime($allenamento['data'])) ?>
        </div>
        <div class="workout-meta">
            <span>🕐 Inizio: <?= substr($allenamento['ora_inizio'], 0, 5) ?></span>
            <?php if ($allenamento['ora_fine']): ?>
            <span>🏁 Fine: <?= substr($allenamento['ora_fine'], 0, 5) ?></span>
            <?php endif; ?>
            <span>⏱️ Durata: <?= $durata_min ?> minuti</span>
            <span>📋 Scheda: <?= htmlspecialchars($allenamento['nome_scheda'] ?? 'Senza scheda') ?></span>
        </div>
    </div>

    <?php if (empty($esercizi_raggruppati)): ?>
        <div style="text-align: center; padding: 60px; background: var(--dark2); border-radius: var(--radius);">
            <div style="font-size: 3rem; margin-bottom: 16px;">📭</div>
            <p>Nessun esercizio registrato per questo allenamento.</p>
        </div>
    <?php else: ?>
        <?php foreach ($esercizi_raggruppati as $nome => $ex): ?>
        <div class="exercise-card">
            <div class="exercise-header" onclick="toggleSerie(this)">
                <div>
                    <div class="exercise-name"><?= htmlspecialchars($nome) ?></div>
                    <div class="exercise-stats">
                        <?= count($ex['serie']) ?> serie completate
                    </div>
                </div>
                <div>
                    <span class="exercise-muscle"><?= ucfirst($ex['gruppo']) ?></span>
                    <span class="toggle-icon">▶</span>
                </div>
            </div>
            <div class="serie-table">
                <div class="serie-row serie-header">
                    <div>Serie</div>
                    <div>Ripetizioni</div>
                    <div>Peso (kg)</div>
                    <div>Volume</div>
                </div>
                <?php foreach ($ex['serie'] as $serie): 
                    $volume = $serie['ripetizioni'] * $serie['peso'];
                ?>
                <div class="serie-row">
                    <div><?= $serie['serie_num'] ?></div>
                    <div><?= $serie['ripetizioni'] ?></div>
                    <div><?= number_format($serie['peso'], 1) ?></div>
                    <div style="color: var(--accent)"><?= number_format($volume, 1) ?> kg</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<script>
function toggleSerie(header) {
    const table = header.parentElement.querySelector('.serie-table');
    const icon = header.querySelector('.toggle-icon');
    table.classList.toggle('open');
    icon.classList.toggle('rotated');
}
</script>
</body>
</html>