<?php
session_start();
if (!isset($_SESSION['utente_id'])) {
    header('Location: /palestra/view/login.php');
    exit();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/palestra/config/database.php';

$uid = (int)$_SESSION['utente_id'];
$db = connetti();

// Trova la scheda attiva
$rs = $db->prepare("SELECT id_scheda, nome FROM schede WHERE id_utente = ? AND attiva = 1 LIMIT 1");
$rs->bind_param("i", $uid);
$rs->execute();
$scheda = $rs->get_result()->fetch_assoc();

$oggi_num = (int)date('N');
$esercizi_oggi = [];

if ($scheda) {
    $id_scheda = $scheda['id_scheda'];
    $qe = $db->prepare("
        SELECT se.id, se.id_esercizio, e.nome, se.serie, se.ripetizioni, se.peso_kg,
               e.gruppo_muscolare, e.tipo
        FROM scheda_esercizi se
        JOIN esercizi e ON se.id_esercizio = e.id_esercizio
        WHERE se.id_scheda = ? AND se.giorno = ?
        ORDER BY se.ordine, se.id
    ");
    $qe->bind_param("ii", $id_scheda, $oggi_num);
    $qe->execute();
    $esercizi_oggi = $qe->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Crea una nuova sessione di allenamento
$id_allenamento = null;
if (!empty($esercizi_oggi)) {
    $data_oggi = date('Y-m-d');
    $ora_inizio = date('H:i:s');
    
    $ins = $db->prepare("
        INSERT INTO allenamenti (id_utente, id_scheda, data, ora_inizio) 
        VALUES (?, ?, ?, ?)
    ");
    $ins->bind_param("iiss", $uid, $id_scheda, $data_oggi, $ora_inizio);
    $ins->execute();
    $id_allenamento = $db->insert_id;
}

$giorni_settimana = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
$oggi = $giorni_settimana[date('w')];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GymApp — Avvia Allenamento</title>
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
            overflow: hidden;
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
        
        .workout-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .exercise-card {
            background: var(--dark2);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 16px;
            transition: all 0.2s;
        }
        .exercise-card.completed {
            border-color: rgba(78,235,176,0.3);
            background: rgba(78,235,176,0.05);
        }
        .exercise-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .exercise-name {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.4rem;
            letter-spacing: 1px;
        }
        .exercise-target {
            font-size: 0.8rem;
            color: var(--accent);
            background: rgba(232,255,0,0.1);
            padding: 4px 12px;
            border-radius: 20px;
        }
        .exercise-details {
            display: flex;
            gap: 16px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            color: #aaa;
        }
        .serie-item {
            background: var(--dark3);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .serie-number {
            font-weight: 600;
            min-width: 40px;
        }
        .serie-inputs {
            display: flex;
            gap: 16px;
        }
        .serie-inputs input {
            background: var(--dark);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px 12px;
            color: var(--text);
            width: 80px;
            text-align: center;
        }
        .serie-check {
            background: rgba(78,235,176,0.1);
            border: 1px solid rgba(78,235,176,0.3);
            border-radius: 8px;
            padding: 8px 16px;
            color: #4eebb0;
            cursor: pointer;
        }
        .serie-check.completed {
            background: #4eebb0;
            color: #000;
        }
        .btn-complete-workout {
            width: 100%;
            padding: 16px;
            background: var(--accent);
            color: #000;
            border: none;
            border-radius: 12px;
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.2rem;
            letter-spacing: 2px;
            cursor: pointer;
            margin-top: 24px;
        }
        .empty-state {
            text-align: center;
            padding: 60px;
            background: var(--dark2);
            border-radius: var(--radius);
        }
        .timer {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--dark2);
            border: 1px solid var(--accent);
            border-radius: 50px;
            padding: 12px 24px;
            font-family: monospace;
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--accent);
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
    <a href="/palestra/view/allenamento.php" class="nav-item active">
        <span class="nav-icon">▶️</span><span class="nav-label">Avvia allenamento</span>
    </a>
    <a href="/palestra/view/progressi.php" class="nav-item">
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
    <div class="topbar">
        <h1>⚡ ALLENAMENTO</h1>
        <p><?= $oggi ?> · <?= date('d F Y') ?></p>
    </div>

    <div class="workout-container">
        <?php if (empty($esercizi_oggi)): ?>
            <div class="empty-state">
                <div style="font-size: 3rem; margin-bottom: 16px;">😴</div>
                <h3>Oggi è giorno di riposo!</h3>
                <p style="color: #888; margin-top: 8px;">Goditi il recupero o modifica la tua scheda.</p>
                <a href="/palestra/view/scheda.php" style="display: inline-block; margin-top: 20px; color: var(--accent);">📋 Modifica scheda</a>
            </div>
        <?php else: ?>
            <div id="esercizi-container">
                <?php foreach ($esercizi_oggi as $idx => $ex): 
                    $serie = (int)$ex['serie'];
                ?>
                <div class="exercise-card" data-esercizio-id="<?= $ex['id_esercizio'] ?>" data-allenamento-id="<?= $id_allenamento ?>">
                    <div class="exercise-header">
                        <div class="exercise-name"><?= htmlspecialchars($ex['nome']) ?></div>
                        <div class="exercise-target">🎯 <?= $ex['serie'] ?>x<?= htmlspecialchars($ex['ripetizioni']) ?> <?= $ex['peso_kg'] ? '('.$ex['peso_kg'].'kg)' : '' ?></div>
                    </div>
                    <div class="exercise-details">
                        <span>💪 <?= ucfirst($ex['gruppo_muscolare']) ?></span>
                        <span>📋 Serie da completare: <?= $serie ?></span>
                    </div>
                    <div class="serie-list">
                        <?php for ($s = 1; $s <= $serie; $s++): ?>
                        <div class="serie-item" data-serie="<?= $s ?>">
                            <div class="serie-number">Serie <?= $s ?></div>
                            <div class="serie-inputs">
                                <input type="number" placeholder="Rip" class="rip-input" value="<?= explode('x', $ex['ripetizioni'])[0] ?>">
                                <input type="number" placeholder="Peso" class="peso-input" step="0.5" value="<?= $ex['peso_kg'] ?? 0 ?>">
                            </div>
                            <div class="serie-check" onclick="completaSerie(this)">⭕ Completa</div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button class="btn-complete-workout" onclick="terminaAllenamento()">🏁 TERMINA ALLENAMENTO</button>
        <?php endif; ?>
    </div>
</main>

<div class="timer" id="timer">00:00</div>

<script>
let secondiTrascorsi = 0;
let timerInterval = null;

// Avvia timer all'inizio dell'allenamento
<?php if (!empty($esercizi_oggi)): ?>
timerInterval = setInterval(() => {
    secondiTrascorsi++;
    const min = Math.floor(secondiTrascorsi / 60);
    const sec = secondiTrascorsi % 60;
    document.getElementById('timer').innerText = `${min.toString().padStart(2,'0')}:${sec.toString().padStart(2,'0')}`;
}, 1000);
<?php endif; ?>

function completaSerie(btn) {
    const serieItem = btn.closest('.serie-item');
    const exerciseCard = btn.closest('.exercise-card');
    const esercizioId = exerciseCard.dataset.esercizioId;
    const allenamentoId = exerciseCard.dataset.allenamentoId;
    const ripetizioni = serieItem.querySelector('.rip-input').value;
    const peso = serieItem.querySelector('.peso-input').value;
    
    if (btn.classList.contains('completed')) {
        return;
    }
    
    btn.classList.add('completed');
    btn.innerHTML = '✅ Completata';
    btn.style.background = '#4eebb0';
    btn.style.color = '#000';
    
    // Salva la serie nel database via AJAX
    fetch('/palestra/controller/salva_serie.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id_allenamento: parseInt(allenamentoId),
            esercizio_id: parseInt(esercizioId),
            ripetizioni: parseInt(ripetizioni),
            peso: parseFloat(peso)
        })
    });
    
    // Controlla se tutte le serie sono completate
    const tutteSerie = exerciseCard.querySelectorAll('.serie-check');
    const completate = exerciseCard.querySelectorAll('.serie-check.completed');
    if (tutteSerie.length === completate.length) {
        exerciseCard.classList.add('completed');
    }
}

function terminaAllenamento() {
    if (!confirm("Hai completato l'allenamento? Verrà salvato il progresso.")) return;
    
    clearInterval(timerInterval);
    
    fetch('/palestra/controller/salva_allenamento.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id_allenamento: <?= $id_allenamento ?? 0 ?>,
            durata_secondi: secondiTrascorsi
        })
    }).then(() => {
        window.location.href = '/palestra/view/progressi.php';
    });
}
</script>
</body>
</html>