<?php
session_start();
if (!isset($_SESSION['utente_id'])) {
    header('Location: /palestra/view/login.php');
    exit();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/palestra/config/database.php';

$uid = (int)$_SESSION['utente_id'];
$db  = connetti();
$nome = htmlspecialchars($_SESSION['nome']);

// ── Statistiche generali ──────────────────────────────────────────────────────
$stGen = $db->prepare("
    SELECT
        COUNT(*) AS tot_allenamenti,
        COALESCE(SUM(durata_sec), 0) AS tot_sec,
        COALESCE(MAX(durata_sec), 0) AS max_sec
    FROM allenamenti
    WHERE id_utente = ?
");
$stGen->bind_param("i", $uid);
$stGen->execute();
$gen = $stGen->get_result()->fetch_assoc();

$tot_allenamenti = (int)$gen['tot_allenamenti'];
$ore_totali      = round($gen['tot_sec'] / 3600, 1);
$durata_media    = $tot_allenamenti > 0 ? round($gen['tot_sec'] / $tot_allenamenti / 60) : 0;

// ── Streak attuale ────────────────────────────────────────────────────────────
$stStreak = $db->prepare("SELECT DISTINCT DATE(data) AS giorno FROM allenamenti WHERE id_utente=? ORDER BY giorno DESC");
$stStreak->bind_param("i", $uid);
$stStreak->execute();
$giorni_raw = $stStreak->get_result()->fetch_all(MYSQLI_ASSOC);
$streak = 0;
if (!empty($giorni_raw)) {
    $atteso = new DateTime('today');
    foreach ($giorni_raw as $r) {
        $g = new DateTime($r['giorno']);
        $diff = (int)$atteso->diff($g)->days;
        if ($diff === 0 || $diff === 1) { $streak++; $atteso = $g; }
        else break;
    }
}

// ── Allenamenti ultimi 12 mesi (per grafico mensile) ─────────────────────────
$stMes = $db->prepare("
    SELECT DATE_FORMAT(data,'%Y-%m') AS mese, COUNT(*) AS cnt
    FROM allenamenti
    WHERE id_utente = ? AND data >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY mese ORDER BY mese ASC
");
$stMes->bind_param("i", $uid);
$stMes->execute();
$mensili_raw = $stMes->get_result()->fetch_all(MYSQLI_ASSOC);
// ── Recupera pesi registrati per il grafico ─────────────────────────────────
$stPesi = $db->prepare("
    SELECT data, peso 
    FROM pesi 
    WHERE id_utente = ? 
    ORDER BY data ASC
");
$stPesi->bind_param("i", $uid);
$stPesi->execute();
$pesi_raw = $stPesi->get_result()->fetch_all(MYSQLI_ASSOC);

$date_pesi = [];
$valori_pesi = [];
foreach ($pesi_raw as $p) {
    $date_pesi[] = date('d/m/Y', strtotime($p['data']));
    $valori_pesi[] = (float)$p['peso'];
}
// Riempi tutti e 12 i mesi (anche quelli con 0)
$mensili = [];
for ($i = 11; $i >= 0; $i--) {
    $k = date('Y-m', strtotime("-$i months"));
    $mensili[$k] = 0;
}
foreach ($mensili_raw as $r) { $mensili[$r['mese']] = (int)$r['cnt']; }

// ── Volume per gruppo muscolare ───────────────────────────────────────────────
$stGruppo = $db->prepare("
    SELECT e.gruppo_muscolare AS gruppo, COUNT(*) AS cnt
    FROM sessione_esercizi se
    JOIN esercizi e ON se.id_esercizio = e.id_esercizio
    JOIN allenamenti a ON se.id_allenamento = a.id_allenamento
    WHERE a.id_utente = ?
    GROUP BY gruppo ORDER BY cnt DESC LIMIT 6
");
$stGruppo->bind_param("i", $uid);
$stGruppo->execute();
$gruppi = $stGruppo->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Record personali (peso massimo per esercizio) ─────────────────────────────
$stPR = $db->prepare("
    SELECT e.nome AS esercizio, MAX(se.peso_usato) AS pr, COUNT(*) AS eseguite
    FROM sessione_esercizi se
    JOIN esercizi e ON se.id_esercizio = e.id_esercizio
    JOIN allenamenti a ON se.id_allenamento = a.id_allenamento
    WHERE a.id_utente = ? AND se.peso_usato > 0
    GROUP BY e.id_esercizio ORDER BY pr DESC LIMIT 8
");
$stPR->bind_param("i", $uid);
$stPR->execute();
$prs = $stPR->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Ultimi 6 allenamenti ──────────────────────────────────────────────────────
$stRec = $db->prepare("
    SELECT a.id_allenamento, a.data, a.ora_inizio, a.durata_sec, s.nome AS scheda,
           COUNT(se.id) AS esercizi_fatti
    FROM allenamenti a
    LEFT JOIN schede s ON a.id_scheda = s.id_scheda
    LEFT JOIN sessione_esercizi se ON a.id_allenamento = se.id_allenamento
    WHERE a.id_utente = ?
    GROUP BY a.id_allenamento
    ORDER BY a.data DESC, a.ora_inizio DESC
    LIMIT 6
");
$stRec->bind_param("i", $uid);
$stRec->execute();
$recenti = $stRec->get_result()->fetch_all(MYSQLI_ASSOC);

// Preparazione dati JS per grafici
$mesi_labels = array_keys($mensili);
$mesi_valori = array_values($mensili);
$mesi_short  = array_map(fn($m) => date('M', strtotime($m . '-01')), $mesi_labels);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GymApp — Progressi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,300&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --accent:  #E8FF00;
            --accent2: #FF4D00;
            --dark:    #0a0a0a;
            --dark2:   #111111;
            --dark3:   #1a1a1a;
            --border:  rgba(255,255,255,0.07);
            --text:    #f0f0f0;
            --muted:   #666;
            --muted2:  #999;
            --radius:  16px;
        }

        body { font-family: 'DM Sans', sans-serif; background: var(--dark); color: var(--text); min-height: 100vh; }

        /* ── SIDEBAR ── */
        .sidebar {
            position: fixed; top: 0; left: 0;
            width: 72px; height: 100vh;
            background: var(--dark2); border-right: 1px solid var(--border);
            display: flex; flex-direction: column; align-items: center;
            padding: 24px 0; z-index: 100;
            transition: width 0.3s cubic-bezier(.16,1,.3,1);
        }
        .sidebar:hover { width: 220px; }
        .sidebar-logo {
            font-family: 'Bebas Neue', sans-serif; font-size: 1.5rem;
            letter-spacing: 3px; color: var(--accent);
            white-space: nowrap; overflow: hidden; width: 100%;
            text-align: center; padding: 0 16px; margin-bottom: 32px;
        }
        .nav-item {
            width: 100%; display: flex; align-items: center; gap: 14px;
            padding: 14px 22px; color: var(--muted2); text-decoration: none;
            font-size: 0.88rem; font-weight: 500; transition: all 0.2s;
            white-space: nowrap; overflow: hidden; border-left: 2px solid transparent;
        }
        .nav-item:hover { color: var(--text); background: rgba(255,255,255,0.04); }
        .nav-item.active { color: var(--accent); border-left-color: var(--accent); background: rgba(232,255,0,0.05); }
        .nav-icon  { font-size: 1.2rem; min-width: 24px; text-align: center; }
        .nav-label { opacity: 0; transition: opacity 0.2s; }
        .sidebar:hover .nav-label { opacity: 1; }
        .nav-spacer { flex: 1; }

        /* ── MAIN ── */
        .main { margin-left: 72px; min-height: 100vh; padding: 32px; }

        /* ── TOPBAR ── */
        .topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 36px; }
        .topbar-left h1 { font-family: 'Bebas Neue', sans-serif; font-size: 2rem; letter-spacing: 2px; line-height: 1; }
        .topbar-left p  { font-size: 0.85rem; color: var(--muted2); margin-top: 4px; }
        .topbar-right   { display: flex; align-items: center; gap: 16px; }
        .avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: var(--accent); display: flex; align-items: center; justify-content: center;
            font-family: 'Bebas Neue', sans-serif; font-size: 1.1rem; color: #000;
        }
        .btn-logout {
            padding: 8px 18px; background: transparent; border: 1px solid var(--border);
            border-radius: 8px; color: var(--muted2); font-family: 'DM Sans', sans-serif;
            font-size: 0.82rem; cursor: pointer; text-decoration: none; transition: all 0.2s;
        }
        .btn-logout:hover { border-color: var(--accent2); color: var(--accent2); }

        /* ── STATS ROW ── */
        .stats-grid {
            display: grid; grid-template-columns: repeat(4,1fr);
            gap: 16px; margin-bottom: 28px;
        }
        .stat-card {
            background: var(--dark2); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 24px;
            animation: fadeUp 0.4s both;
            transition: border-color 0.2s, transform 0.2s;
        }
        .stat-card:hover { border-color: rgba(232,255,0,0.2); transform: translateY(-2px); }
        .stat-card:nth-child(1) { animation-delay:0.05s; }
        .stat-card:nth-child(2) { animation-delay:0.1s; }
        .stat-card:nth-child(3) { animation-delay:0.15s; }
        .stat-card:nth-child(4) { animation-delay:0.2s; }
        .stat-label { font-size: 0.72rem; color: var(--muted); text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600; margin-bottom: 10px; }
        .stat-icon  { font-size: 1.4rem; margin-bottom: 8px; }
        .stat-value { font-family: 'Bebas Neue', sans-serif; font-size: 2.6rem; letter-spacing: 1px; line-height: 1; }
        .stat-value span { font-size: 1rem; color: var(--muted2); margin-left: 4px; }

        /* ── LAYOUT GRIDS ── */
        .two-col  { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .one-col  { margin-bottom: 20px; }

        /* ── CARD ── */
        .card {
            background: var(--dark2); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 28px;
            animation: fadeUp 0.4s both;
        }
        .card-title {
            font-family: 'Bebas Neue', sans-serif; font-size: 1.1rem; letter-spacing: 2px;
            color: var(--text); margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px;
        }
        .card-title-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--accent); }

        /* ── CANVAS ── */
        .chart-wrap { position: relative; width: 100%; }
        canvas { display: block; width: 100% !important; }

        /* ── GRUPPI MUSCOLARI (barre orizzontali) ── */
        .muscle-list { display: flex; flex-direction: column; gap: 12px; }
        .muscle-row  { display: flex; align-items: center; gap: 12px; }
        .muscle-name { font-size: 0.82rem; color: var(--text); min-width: 110px; }
        .muscle-bar-wrap {
            flex: 1; height: 8px; border-radius: 10px;
            background: var(--dark3); overflow: hidden;
        }
        .muscle-bar { height: 100%; border-radius: 10px; background: var(--accent); }
        .muscle-cnt { font-size: 0.78rem; color: var(--muted2); min-width: 30px; text-align: right; }

        /* ── PR TABLE ── */
        .pr-list { display: flex; flex-direction: column; gap: 10px; }
        .pr-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 16px; background: var(--dark3);
            border: 1px solid var(--border); border-radius: 10px;
            transition: border-color 0.2s;
        }
        .pr-item:hover { border-color: rgba(232,255,0,0.15); }
        .pr-name { font-size: 0.88rem; font-weight: 500; color: var(--text); }
        .pr-eseguite { font-size: 0.75rem; color: var(--muted2); margin-top: 2px; }
        .pr-weight {
            font-family: 'Bebas Neue', sans-serif; font-size: 1.5rem; letter-spacing: 1px;
            color: var(--accent);
        }
        .pr-weight span { font-size: 0.8rem; color: var(--muted2); }

        /* ── STORICO RECENTE ── */
        .history-list { display: flex; flex-direction: column; gap: 10px; }
        .history-item {
            display: flex; align-items: center; gap: 14px;
            padding: 14px 16px; background: var(--dark3);
            border: 1px solid var(--border); border-radius: 10px;
        }
        .history-date { text-align: center; min-width: 44px; }
        .history-date-day   { font-family: 'Bebas Neue', sans-serif; font-size: 1.4rem; line-height: 1; }
        .history-date-month { font-size: 0.65rem; color: var(--muted2); text-transform: uppercase; letter-spacing: 1px; }
        .history-divider    { width: 1px; height: 36px; background: var(--border); }
        .history-info  { flex: 1; }
        .history-name  { font-size: 0.88rem; font-weight: 500; }
        .history-meta  { font-size: 0.75rem; color: var(--muted2); margin-top: 2px; }
        .history-badge {
            font-size: 0.7rem; padding: 4px 10px; border-radius: 20px;
            background: rgba(78,235,176,0.1); color: #4eebb0;
            border: 1px solid rgba(78,235,176,0.2);
        }

        /* ── EMPTY STATE ── */
        .empty-state { text-align: center; padding: 40px; color: var(--muted); }
        .empty-state .empty-icon { font-size: 2.5rem; margin-bottom: 10px; }
        .empty-state p { font-size: 0.85rem; }

        /* ── ANIMAZIONI ── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 900px) {
            .stats-grid { grid-template-columns: repeat(2,1fr); }
            .two-col    { grid-template-columns: 1fr; }
        }
        @media (max-width: 600px) {
            .main { padding: 20px 16px; }
            .stats-grid { grid-template-columns: repeat(2,1fr); gap: 10px; }
        }
    </style>
</head>
<body>

<!-- ── SIDEBAR ── -->
<nav class="sidebar">
    <div class="sidebar-logo">G<span style="opacity:0;transition:opacity 0.2s" class="logo-rest">ymApp</span></div>

    <a href="/palestra/view/dashboard.php" class="nav-item">
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
    <a href="/palestra/view/progressi.php" class="nav-item active">
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

<!-- ── MAIN ── -->
<main class="main">

    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-left">
            <h1>Progressi</h1>
            <p>Le tue statistiche e i tuoi record</p>
        </div>
        <div class="topbar-right">
            <div class="avatar"><?= strtoupper(substr($nome, 0, 1)) ?></div>
            <a href="/palestra/controller/LogoutController.php" class="btn-logout">Esci</a>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">🏋️</div>
            <div class="stat-label">Allenamenti Totali</div>
            <div class="stat-value"><?= $tot_allenamenti ?><span>sessioni</span></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏱️</div>
            <div class="stat-label">Ore Totali</div>
            <div class="stat-value"><?= $ore_totali ?><span>h</span></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <div class="stat-label">Durata Media</div>
            <div class="stat-value"><?= $durata_media ?><span>min</span></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🔥</div>
            <div class="stat-label">Streak Attuale</div>
            <div class="stat-value"><?= $streak ?><span>giorni</span></div>
        </div>
    </div>

    <?php if ($tot_allenamenti === 0): ?>
    <!-- Empty state globale -->
    <div class="card" style="animation-delay:0.25s">
        <div class="empty-state">
            <div class="empty-icon">📊</div>
            <p>Nessun dato ancora.<br>Completa il tuo primo allenamento per vedere le statistiche!</p>
        </div>
    </div>

    <?php else: ?>

    <!-- Grafico allenamenti mensili -->
    <div class="one-col">
        <div class="card" style="animation-delay:0.25s">
            <div class="card-title">
                <div class="card-title-dot"></div>
                ALLENAMENTI ULTIMI 12 MESI
            </div>
            <div class="chart-wrap" style="height:200px">
                <canvas id="chartMensile"></canvas>
            </div>
        </div>
    </div>

    <!-- Grafico andamento peso -->
<div class="one-col">
    <div class="card" style="animation-delay:0.27s">
        <div class="card-title">
            <div class="card-title-dot"></div>
            ANDAMENTO PESO (kg)
        </div>
        <div class="chart-wrap" style="height:200px">
            <canvas id="chartPeso"></canvas>
        </div>
    </div>
</div>

    <div class="two-col">

        <!-- Gruppi muscolari -->
        <div class="card" style="animation-delay:0.3s">
            <div class="card-title">
                <div class="card-title-dot"></div>
                MUSCOLI PIÙ ALLENATI
            </div>
            <?php if (empty($gruppi)): ?>
                <div class="empty-state" style="padding:20px">
                    <p>Nessun dato disponibile.</p>
                </div>
            <?php else:
                $max_cnt = max(array_column($gruppi, 'cnt'));
            ?>
            <div class="muscle-list">
                <?php foreach ($gruppi as $g):
                    $pct = $max_cnt > 0 ? round($g['cnt'] / $max_cnt * 100) : 0;
                ?>
                <div class="muscle-row">
                    <div class="muscle-name"><?= htmlspecialchars($g['gruppo']) ?></div>
                    <div class="muscle-bar-wrap">
                        <div class="muscle-bar" style="width:<?= $pct ?>%"></div>
                    </div>
                    <div class="muscle-cnt"><?= $g['cnt'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Record personali -->
        <div class="card" style="animation-delay:0.35s">
            <div class="card-title">
                <div class="card-title-dot"></div>
                RECORD PERSONALI
            </div>
            <?php if (empty($prs)): ?>
                <div class="empty-state" style="padding:20px">
                    <p>Nessun record ancora.<br>Inserisci il peso durante l'allenamento!</p>
                </div>
            <?php else: ?>
            <div class="pr-list">
                <?php foreach ($prs as $pr): ?>
                <div class="pr-item">
                    <div>
                        <div class="pr-name">🏆 <?= htmlspecialchars($pr['esercizio']) ?></div>
                        <div class="pr-eseguite"><?= $pr['eseguite'] ?> serie registrate</div>
                    </div>
                    <div class="pr-weight"><?= number_format($pr['pr'], 1) ?><span> kg</span></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Storico allenamenti recenti -->
<div class="one-col">
    <div class="card" style="animation-delay:0.4s">
        <div class="card-title">
            <div class="card-title-dot"></div>
            ULTIMI ALLENAMENTI
        </div>
        <?php if (empty($recenti)): ?>
            <div class="empty-state" style="padding:20px">
                <p>Nessun allenamento registrato.</p>
            </div>
        <?php else: ?>
        <div class="history-list">
            <?php foreach ($recenti as $a):
                $durata_min = $a['durata_sec'] > 0 ? round($a['durata_sec'] / 60) . ' min' : '—';
                $giorno  = date('d', strtotime($a['data']));
                $mese    = strtoupper(date('M', strtotime($a['data'])));
            ?>
            <a href="/palestra/view/dettaglio_allenamento.php?id=<?= $a['id_allenamento'] ?>" style="text-decoration: none; color: inherit;">
                <div class="history-item" style="cursor: pointer;">
                    <div class="history-date">
                        <div class="history-date-day"><?= $giorno ?></div>
                        <div class="history-date-month"><?= $mese ?></div>
                    </div>
                    <div class="history-divider"></div>
                    <div class="history-info">
                        <div class="history-name"><?= htmlspecialchars($a['scheda'] ?? 'Allenamento') ?></div>
                        <div class="history-meta"><?= (int)$a['esercizi_fatti'] ?> esercizi · <?= $durata_min ?></div>
                    </div>
                    <div class="history-badge">✓ Dettaglio →</div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

    <?php endif; ?>

</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
// ── Sidebar logo ──
const sidebar  = document.querySelector('.sidebar');
const logoRest = document.querySelector('.logo-rest');
sidebar.addEventListener('mouseenter', () => logoRest.style.opacity = '1');
sidebar.addEventListener('mouseleave', () => logoRest.style.opacity = '0');

// ── Grafico mensile ──
const canvasMensile = document.getElementById('chartMensile');
if (canvasMensile) {
    const labels = <?= json_encode(array_values($mesi_short)) ?>;
    const data   = <?= json_encode($mesi_valori) ?>;

    new Chart(canvasMensile, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Allenamenti',
                data,
                backgroundColor: 'rgba(232,255,0,0.25)',
                borderColor: '#E8FF00',
                borderWidth: 2,
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1a1a1a',
                    borderColor: 'rgba(255,255,255,0.1)',
                    borderWidth: 1,
                    titleColor: '#f0f0f0',
                    bodyColor: '#E8FF00',
                    callbacks: {
                        label: ctx => ` ${ctx.parsed.y} allenament${ctx.parsed.y === 1 ? 'o' : 'i'}`
                    }
                }
            },
            scales: {
                x: {
                    ticks: { color: '#666', font: { family: 'DM Sans', size: 11 } },
                    grid:  { color: 'rgba(255,255,255,0.04)' }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#666',
                        font: { family: 'DM Sans', size: 11 },
                        stepSize: 1,
                        precision: 0
                    },
                    grid: { color: 'rgba(255,255,255,0.04)' }
                }
            }
        }
    });
}

// ── Grafico andamento peso ──
const canvasPeso = document.getElementById('chartPeso');
if (canvasPeso) {
    const labelsPeso = <?= json_encode($date_pesi) ?>;
    const dataPeso = <?= json_encode($valori_pesi) ?>;
    
    if (labelsPeso.length > 0) {
        new Chart(canvasPeso, {
            type: 'line',
            data: {
                labels: labelsPeso,
                datasets: [{
                    label: 'Peso (kg)',
                    data: dataPeso,
                    borderColor: '#E8FF00',
                    backgroundColor: 'rgba(232,255,0,0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#E8FF00',
                    pointBorderColor: '#000',
                    pointRadius: 5,
                    fill: true,
                    tension: 0.2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: { callbacks: { label: ctx => `${ctx.parsed.y} kg` } },
                    legend: { labels: { color: '#999' } }
                },
                scales: {
                    y: { 
                        title: { display: true, text: 'kg', color: '#999' },
                        ticks: { color: '#999' },
                        grid: { color: 'rgba(255,255,255,0.04)' }
                    },
                    x: {
                        ticks: { color: '#999', maxRotation: 45, autoSkip: true },
                        grid: { color: 'rgba(255,255,255,0.04)' }
                    }
                }
            }
        });
    } else {
        canvasPeso.parentElement.innerHTML = '<div style="text-align:center; color:#666; padding:40px;">📊 Nessun peso registrato.<br>Vai su <strong>Profilo</strong> per aggiungere il tuo peso.</div>';
    }
}
</script>

</body>
</html>