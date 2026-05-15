<?php
session_start();
if (!isset($_SESSION['utente_id'])) {
    header('Location: /palestra/view/login.php');
    exit();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/palestra/config/database.php';

$uid  = (int)$_SESSION['utente_id'];
$db   = connetti();

$successo     = '';
$errore       = '';
$msg_password = '';
$msg_peso     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? '';

    if ($azione === 'aggiorna_profilo') {
        $nome             = htmlspecialchars(trim($_POST['nome'] ?? ''));
        $cognome          = htmlspecialchars(trim($_POST['cognome'] ?? ''));
        $peso             = filter_var($_POST['peso'] ?? '', FILTER_VALIDATE_FLOAT);
        $altezza          = filter_var($_POST['altezza'] ?? '', FILTER_VALIDATE_INT);
        $obiettivo        = trim($_POST['obiettivo'] ?? '');
        $giorni_settimana = filter_var($_POST['giorni_settimana'] ?? '', FILTER_VALIDATE_INT);
        $obiettivi_validi = ['perdita_peso', 'massa_muscolare', 'tonificazione'];

        if (empty($nome) || empty($cognome)) {
            $errore = 'Nome e cognome sono obbligatori.';
        } elseif ($peso === false || $peso < 30 || $peso > 300) {
            $errore = 'Inserisci un peso valido (30-300 kg).';
        } elseif ($altezza === false || $altezza < 100 || $altezza > 250) {
            $errore = "Inserisci un'altezza valida (100-250 cm).";
        } elseif (!in_array($obiettivo, $obiettivi_validi)) {
            $errore = 'Seleziona un obiettivo valido.';
        } elseif ($giorni_settimana === false || $giorni_settimana < 1 || $giorni_settimana > 7) {
            $errore = 'Seleziona i giorni di allenamento (1-7).';
        } else {
            $stmt = $db->prepare("UPDATE utenti SET nome=?, cognome=?, peso_partenza=?, altezza=?, obiettivo=?, giorni_settimana=? WHERE id_utente=?");
            $stmt->bind_param("ssdisii", $nome, $cognome, $peso, $altezza, $obiettivo, $giorni_settimana, $uid);
            if ($stmt->execute()) {
                $_SESSION['nome']             = $nome;
                $_SESSION['obiettivo']        = $obiettivo;
                $_SESSION['giorni_settimana'] = $giorni_settimana;
                $successo = 'Profilo aggiornato con successo!';
            } else {
                $errore = 'Errore durante il salvataggio. Riprova.';
            }
        }
    }

    elseif ($azione === 'cambia_password') {
        $password_attuale = trim($_POST['password_attuale'] ?? '');
        $nuova_password   = trim($_POST['nuova_password'] ?? '');
        $conferma         = trim($_POST['conferma_password'] ?? '');

        if (empty($password_attuale) || empty($nuova_password) || empty($conferma)) {
            $msg_password = "Tutti i campi sono obbligatori.";
        } elseif (strlen($nuova_password) < 8) {
            $msg_password = "La nuova password deve essere di almeno 8 caratteri.";
        } elseif ($nuova_password !== $conferma) {
            $msg_password = "Le nuove password non coincidono.";
        } else {
            $stmt = $db->prepare("SELECT password FROM utenti WHERE id_utente = ?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $utente_db = $stmt->get_result()->fetch_assoc();

            if (password_verify($password_attuale, $utente_db['password'])) {
                $nuovo_hash = password_hash($nuova_password, PASSWORD_BCRYPT);
                $update = $db->prepare("UPDATE utenti SET password = ? WHERE id_utente = ?");
                $update->bind_param("si", $nuovo_hash, $uid);
                if ($update->execute()) {
                    $msg_password = "Password cambiata con successo!";
                } else {
                    $msg_password = "Errore durante l'aggiornamento. Riprova.";
                }
            } else {
                $msg_password = "Password attuale errata.";
            }
        }
    }

    elseif ($azione === 'registra_peso') {
        $peso = filter_var($_POST['peso'] ?? '', FILTER_VALIDATE_FLOAT);
        $data = $_POST['data'] ?? date('Y-m-d');

        if ($peso === false || $peso < 30 || $peso > 300) {
            $msg_peso = "Inserisci un peso valido (30-300 kg).";
        } else {
            $stmt = $db->prepare("INSERT INTO pesi (id_utente, peso, data) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE peso = VALUES(peso)");
            $stmt->bind_param("ids", $uid, $peso, $data);
            if ($stmt->execute()) {
                $msg_peso = "Peso registrato con successo!";
            } else {
                $msg_peso = "Errore durante il salvataggio.";
            }
        }
    }
}

$stmt = $db->prepare("SELECT nome, cognome, email, peso_partenza, altezza, obiettivo, giorni_settimana, created_at FROM utenti WHERE id_utente=?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$utente = $stmt->get_result()->fetch_assoc();

$stats = $db->prepare("SELECT COUNT(*) AS tot_allenamenti, COALESCE(SUM(durata_sec),0) AS tot_sec FROM allenamenti WHERE id_utente=?");
$stats->bind_param("i", $uid);
$stats->execute();
$stat = $stats->get_result()->fetch_assoc();

$tot_allenamenti = (int)$stat['tot_allenamenti'];
$ore_totali      = round($stat['tot_sec'] / 3600, 1);

$bmi = 0;
$bmi_label = '—';
$bmi_color = 'var(--muted2)';
if ($utente['altezza'] > 0 && $utente['peso_partenza'] > 0) {
    $h = $utente['altezza'] / 100;
    $bmi = round($utente['peso_partenza'] / ($h * $h), 1);
    if ($bmi < 18.5)      { $bmi_label = 'Sottopeso';   $bmi_color = '#60a5fa'; }
    elseif ($bmi < 25)    { $bmi_label = 'Normopeso';   $bmi_color = '#4eebb0'; }
    elseif ($bmi < 30)    { $bmi_label = 'Sovrappeso';  $bmi_color = '#facc15'; }
    else                   { $bmi_label = 'Obesità';     $bmi_color = '#f87171'; }
}

$obiettivo_label = [
    'perdita_peso'    => 'Perdita Peso',
    'massa_muscolare' => 'Massa Muscolare',
    'tonificazione'   => 'Tonificazione',
][$utente['obiettivo']] ?? 'Non impostato';

$obiettivo_icon = [
    'perdita_peso'    => '🔥',
    'massa_muscolare' => '💪',
    'tonificazione'   => '⚡',
][$utente['obiettivo']] ?? '🎯';

$membro_dal = date('d/m/Y', strtotime($utente['created_at']));
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GymApp — Profilo</title>
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
            font-size: 1.5rem; letter-spacing: 3px;
            color: var(--accent);
            white-space: nowrap; overflow: hidden;
            width: 100%; text-align: center;
            padding: 0 16px; margin-bottom: 32px;
        }

        .nav-item {
            width: 100%; display: flex; align-items: center;
            gap: 14px; padding: 14px 22px;
            color: var(--muted2); text-decoration: none;
            font-size: 0.88rem; font-weight: 500;
            transition: all 0.2s; white-space: nowrap;
            overflow: hidden; border-left: 2px solid transparent;
        }
        .nav-item:hover { color: var(--text); background: rgba(255,255,255,0.04); }
        .nav-item.active { color: var(--accent); border-left-color: var(--accent); background: rgba(232,255,0,0.05); }
        .nav-icon  { font-size: 1.2rem; min-width: 24px; text-align: center; }
        .nav-label { opacity: 0; transition: opacity 0.2s; }
        .sidebar:hover .nav-label { opacity: 1; }
        .nav-spacer { flex: 1; }

        /* ── MAIN ── */
        .main { margin-left: 72px; min-height: 100vh; padding: 32px 36px; }

        /* ── TOPBAR ── */
        .topbar {
            display: flex; align-items: center;
            justify-content: space-between;
            margin-bottom: 32px;
        }
        .topbar-left h1 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 2rem; letter-spacing: 2px;
            color: var(--text); line-height: 1;
        }
        .topbar-left p { font-size: 0.85rem; color: var(--muted2); margin-top: 4px; }
        .topbar-right { display: flex; align-items: center; gap: 16px; }
        .avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: var(--accent);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Bebas Neue', sans-serif; font-size: 1.1rem; color: #000;
        }
        .btn-logout {
            padding: 8px 18px; background: transparent;
            border: 1px solid var(--border); border-radius: 8px;
            color: var(--muted2); font-family: 'DM Sans', sans-serif;
            font-size: 0.82rem; cursor: pointer; text-decoration: none;
            transition: all 0.2s;
        }
        .btn-logout:hover { border-color: var(--accent2); color: var(--accent2); }

        /* ── CARD ── */
        .card {
            background: var(--dark2);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 28px;
            animation: fadeUp 0.4s both;
        }
        .card-title {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1rem; letter-spacing: 2px;
            color: var(--text); margin-bottom: 22px;
            display: flex; align-items: center; gap: 10px;
        }
        .card-title-dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: var(--accent); flex-shrink: 0;
        }

        /* ── HERO BANNER (sostituisce l'avatar card verticale) ── */
        .hero-banner {
            background: var(--dark2);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 28px 32px;
            display: flex; align-items: center;
            gap: 28px; margin-bottom: 24px;
            animation: fadeUp 0.3s both;
        }
        .hero-avatar {
            width: 80px; height: 80px; border-radius: 50%;
            background: var(--accent); flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Bebas Neue', sans-serif; font-size: 2.4rem; color: #000;
            border: 3px solid rgba(232,255,0,0.25);
        }
        .hero-info { flex: 1; }
        .hero-name {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 2rem; letter-spacing: 2px; line-height: 1;
        }
        .hero-email { font-size: 0.83rem; color: var(--muted2); margin-top: 4px; }
        .hero-since { font-size: 0.75rem; color: var(--muted); margin-top: 3px; }
        .obiettivo-badge {
            display: inline-flex; align-items: center; gap: 7px;
            margin-top: 12px; padding: 6px 16px;
            border-radius: 50px;
            background: rgba(232,255,0,0.08);
            border: 1px solid rgba(232,255,0,0.2);
            color: var(--accent); font-size: 0.82rem; font-weight: 600;
        }
        .hero-stats {
            display: flex; gap: 0;
            border: 1px solid var(--border); border-radius: 12px;
            overflow: hidden; flex-shrink: 0;
        }
        .hero-stat {
            padding: 16px 28px; text-align: center;
            border-right: 1px solid var(--border);
        }
        .hero-stat:last-child { border-right: none; }
        .hero-stat-value {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 2rem; letter-spacing: 1px; line-height: 1; color: var(--text);
        }
        .hero-stat-value span { font-size: 0.85rem; color: var(--muted2); }
        .hero-stat-label { font-size: 0.68rem; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; }

        /* ── GRIGLIA AZIONI (3 colonne) ── */
        .actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
            align-items: start;
        }

        /* ── GRIGLIA BOTTOM (form dati + BMI) ── */
        .bottom-grid {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 20px;
            align-items: start;
        }

        /* ── FORM ── */
        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group  { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full { grid-column: 1 / -1; }

        label {
            font-size: 0.7rem; text-transform: uppercase;
            letter-spacing: 1.5px; color: var(--muted); font-weight: 600;
        }
        input, select {
            background: var(--dark3);
            border: 1px solid var(--border);
            border-radius: 10px; color: var(--text);
            font-family: 'DM Sans', sans-serif; font-size: 0.9rem;
            padding: 11px 14px; outline: none;
            transition: border-color 0.2s; width: 100%;
        }
        input:focus, select:focus { border-color: rgba(232,255,0,0.4); }
        input[readonly] { color: var(--muted2); cursor: not-allowed; }

        /* ── PASSWORD FIELD ── */
        .pw-field { position: relative; width: 100%; }
        .pw-field input { padding-right: 40px; }
        .pw-toggle {
            position: absolute; right: 10px; top: 50%;
            transform: translateY(-50%); background: none; border: none;
            color: var(--muted2); cursor: pointer; font-size: 1rem; padding: 0;
        }
        .pw-toggle:hover { color: var(--accent); }

        /* ── BOTTONI ── */
        .btn-primary {
            width: 100%; margin-top: 20px; padding: 13px;
            background: var(--accent); color: #000;
            border: none; border-radius: 10px;
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.05rem; letter-spacing: 2px;
            cursor: pointer; transition: transform 0.15s, box-shadow 0.2s;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(232,255,0,0.28); }

        .btn-danger {
            width: 100%; margin-top: 20px; padding: 13px;
            background: var(--accent2); color: #fff;
            border: none; border-radius: 10px;
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.05rem; letter-spacing: 2px;
            cursor: pointer; transition: transform 0.15s, box-shadow 0.2s;
        }
        .btn-danger:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(255,77,0,0.28); }

        /* ── ALERT ── */
        .alert {
            padding: 11px 15px; border-radius: 10px;
            font-size: 0.845rem; margin-bottom: 18px;
        }
        .alert-success {
            background: rgba(78,235,176,0.1); border: 1px solid rgba(78,235,176,0.25);
            color: #4eebb0;
        }
        .alert-error {
            background: rgba(255,77,0,0.1); border: 1px solid rgba(255,77,0,0.25);
            color: var(--accent2);
        }

        /* ── BMI ── */
        .bmi-block { min-width: 260px; }
        .bmi-number {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 4rem; letter-spacing: 1px; line-height: 1;
        }
        .bmi-label-text { font-size: 0.9rem; font-weight: 600; margin-top: 2px; }
        .bmi-bar-wrap {
            height: 8px; border-radius: 10px;
            background: var(--dark3); overflow: hidden;
            margin: 18px 0 8px;
        }
        .bmi-bar { height: 100%; border-radius: 10px; transition: width 0.6s cubic-bezier(.16,1,.3,1); }
        .bmi-legend {
            display: flex; justify-content: space-between;
            font-size: 0.7rem; color: var(--muted);
        }
        .bmi-detail { font-size: 0.75rem; color: var(--muted); margin-top: 14px; line-height: 1.8; }

        /* ── SEZIONE DIVIDER ── */
        .section-label {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 0.85rem; letter-spacing: 3px;
            color: var(--muted); margin-bottom: 14px;
            text-transform: uppercase;
        }

        /* ── ANIMAZIONI ── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 1100px) {
            .actions-grid { grid-template-columns: 1fr 1fr; }
            .bottom-grid  { grid-template-columns: 1fr; }
        }
        @media (max-width: 760px) {
            .main { padding: 20px 16px; }
            .actions-grid { grid-template-columns: 1fr; }
            .hero-banner  { flex-direction: column; align-items: flex-start; gap: 20px; }
            .hero-stats   { width: 100%; }
            .form-grid-2  { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ── SIDEBAR ── -->
<nav class="sidebar">
    <div class="sidebar-logo">G<span style="opacity:0;transition:opacity 0.2s" class="logo-rest">ymApp</span></div>

    <a href="/palestra/view/dashboard.php" class="nav-item">
        <span class="nav-icon">🏠</span><span class="nav-label">Home</span>
    </a>
    <a href="/palestra/view/scheda.php" class="nav-item">
        <span class="nav-icon">📋</span><span class="nav-label">La mia scheda</span>
    </a>
    <a href="/palestra/view/allenamento.php" class="nav-item">
        <span class="nav-icon">▶️</span><span class="nav-label">Avvia allenamento</span>
    </a>
    <a href="/palestra/view/progressi.php" class="nav-item">
        <span class="nav-icon">📊</span><span class="nav-label">Progressi</span>
    </a>
    <a href="/palestra/view/calendario.php" class="nav-item">
        <span class="nav-icon">📅</span><span class="nav-label">Calendario</span>
    </a>
    <a href="/palestra/view/profilo.php" class="nav-item active">
        <span class="nav-icon">👤</span><span class="nav-label">Profilo</span>
    </a>
    <div class="nav-spacer"></div>
    <a href="/palestra/controller/LogoutController.php" class="nav-item">
        <span class="nav-icon">🚪</span><span class="nav-label">Esci</span>
    </a>
</nav>

<!-- ── MAIN ── -->
<main class="main">

    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-left">
            <h1>Profilo</h1>
            <p>Gestisci i tuoi dati personali</p>
        </div>
        <div class="topbar-right">
            <div class="avatar"><?= strtoupper(substr($utente['nome'], 0, 1)) ?></div>
            <a href="/palestra/controller/LogoutController.php" class="btn-logout">Esci</a>
        </div>
    </div>

    <!-- ── HERO BANNER ── -->
    <div class="hero-banner">
        <div class="hero-avatar"><?= strtoupper(substr($utente['nome'], 0, 1)) ?></div>
        <div class="hero-info">
            <div class="hero-name"><?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?></div>
            <div class="hero-email"><?= htmlspecialchars($utente['email']) ?></div>
            <div class="hero-since">Membro dal <?= $membro_dal ?></div>
            <div class="obiettivo-badge"><?= $obiettivo_icon ?> <?= $obiettivo_label ?></div>
        </div>
        <div class="hero-stats">
            <div class="hero-stat">
                <div class="hero-stat-value"><?= $tot_allenamenti ?></div>
                <div class="hero-stat-label">Sessioni</div>
            </div>
            <div class="hero-stat">
                <div class="hero-stat-value"><?= $ore_totali ?><span>h</span></div>
                <div class="hero-stat-label">Ore totali</div>
            </div>
            <div class="hero-stat">
                <div class="hero-stat-value"><?= $utente['giorni_settimana'] ?><span>gg</span></div>
                <div class="hero-stat-label">A settimana</div>
            </div>
        </div>
    </div>

    <!-- ── GRIGLIA AZIONI (3 card affiancate) ── -->
    <div class="actions-grid">

        <!-- CARD 1: Modifica dati -->
        <div class="card" style="animation-delay:0.05s">
            <div class="card-title">
                <div class="card-title-dot"></div>
                MODIFICA DATI
            </div>

            <?php if ($successo): ?>
                <div class="alert alert-success">✓ <?= htmlspecialchars($successo) ?></div>
            <?php endif; ?>
            <?php if ($errore): ?>
                <div class="alert alert-error">✕ <?= htmlspecialchars($errore) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="azione" value="aggiorna_profilo">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="nome">Nome</label>
                        <input type="text" id="nome" name="nome"
                               value="<?= htmlspecialchars($utente['nome']) ?>"
                               required maxlength="50">
                    </div>
                    <div class="form-group">
                        <label for="cognome">Cognome</label>
                        <input type="text" id="cognome" name="cognome"
                               value="<?= htmlspecialchars($utente['cognome']) ?>"
                               required maxlength="50">
                    </div>
                    <div class="form-group full">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email"
                               value="<?= htmlspecialchars($utente['email']) ?>" readonly>
                        <span style="font-size:0.7rem;color:var(--muted);margin-top:3px;">L'email non può essere modificata</span>
                    </div>
                    <div class="form-group">
                        <label for="peso">Peso (kg)</label>
                        <input type="number" id="peso" name="peso" step="0.1" min="30" max="300"
                               value="<?= htmlspecialchars($utente['peso_partenza']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="altezza">Altezza (cm)</label>
                        <input type="number" id="altezza" name="altezza" min="100" max="250"
                               value="<?= htmlspecialchars($utente['altezza']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="obiettivo">Obiettivo</label>
                        <select id="obiettivo" name="obiettivo" required>
                            <option value="perdita_peso"    <?= $utente['obiettivo']==='perdita_peso'    ? 'selected' : '' ?>>🔥 Perdita Peso</option>
                            <option value="massa_muscolare" <?= $utente['obiettivo']==='massa_muscolare' ? 'selected' : '' ?>>💪 Massa Muscolare</option>
                            <option value="tonificazione"   <?= $utente['obiettivo']==='tonificazione'   ? 'selected' : '' ?>>⚡ Tonificazione</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="giorni_settimana">Giorni/settimana</label>
                        <select id="giorni_settimana" name="giorni_settimana" required>
                            <?php for ($g = 1; $g <= 7; $g++): ?>
                            <option value="<?= $g ?>" <?= $utente['giorni_settimana'] == $g ? 'selected' : '' ?>>
                                <?= $g ?> <?= $g === 1 ? 'giorno' : 'giorni' ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-primary">SALVA MODIFICHE</button>
            </form>
        </div>

        <!-- CARD 2: Cambia password -->
        <div class="card" style="animation-delay:0.1s">
            <div class="card-title">
                <div class="card-title-dot" style="background:var(--accent2)"></div>
                CAMBIA PASSWORD
            </div>

            <?php if (!empty($msg_password)): ?>
                <div class="alert alert-<?= strpos($msg_password, 'successo') !== false ? 'success' : 'error' ?>">
                    <?= htmlspecialchars($msg_password) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="azione" value="cambia_password">
                <div class="form-group" style="margin-bottom:14px">
                    <label>Password attuale</label>
                    <div class="pw-field">
                        <input type="password" name="password_attuale" id="pw_attuale" required>
                        <button type="button" class="pw-toggle" onclick="togglePassword('pw_attuale', this)">👁</button>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:14px">
                    <label>Nuova password</label>
                    <div class="pw-field">
                        <input type="password" name="nuova_password" id="pw_nuova" required minlength="8">
                        <button type="button" class="pw-toggle" onclick="togglePassword('pw_nuova', this)">👁</button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Conferma nuova password</label>
                    <div class="pw-field">
                        <input type="password" name="conferma_password" id="pw_conferma" required>
                        <button type="button" class="pw-toggle" onclick="togglePassword('pw_conferma', this)">👁</button>
                    </div>
                </div>

                <!-- Indicatore forza password -->
                <div style="margin-top:14px;">
                    <div style="display:flex;justify-content:space-between;font-size:0.7rem;color:var(--muted);margin-bottom:5px;">
                        <span>Sicurezza password</span>
                        <span id="strength-label">—</span>
                    </div>
                    <div style="height:5px;border-radius:10px;background:var(--dark3);overflow:hidden;">
                        <div id="strength-bar" style="height:100%;width:0;border-radius:10px;background:var(--muted);transition:width 0.3s,background 0.3s;"></div>
                    </div>
                </div>

                <button type="submit" class="btn-danger">AGGIORNA PASSWORD</button>
            </form>
        </div>

        <!-- CARD 3: Registra peso + BMI -->
        <div style="display:flex;flex-direction:column;gap:20px;animation: fadeUp 0.4s 0.15s both;">

            <!-- Registra peso -->
            <div class="card" style="animation:none">
                <div class="card-title">
                    <div class="card-title-dot" style="background:#60a5fa"></div>
                    REGISTRA PESO
                </div>

                <?php if (!empty($msg_peso)): ?>
                    <div class="alert alert-<?= strpos($msg_peso, 'successo') !== false ? 'success' : 'error' ?>">
                        <?= htmlspecialchars($msg_peso) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="azione" value="registra_peso">
                    <div class="form-group" style="margin-bottom:14px">
                        <label>Peso (kg)</label>
                        <input type="number" name="peso" step="0.1" min="30" max="300" required>
                    </div>
                    <div class="form-group">
                        <label>Data</label>
                        <input type="date" name="data" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <button type="submit" class="btn-primary" style="background:#60a5fa;color:#000;">💾 SALVA PESO</button>
                </form>
            </div>

            <!-- BMI -->
            <?php if ($bmi > 0): ?>
            <div class="card" style="animation:none">
                <div class="card-title">
                    <div class="card-title-dot" style="background:<?= $bmi_color ?>"></div>
                    BMI
                </div>
                <div style="display:flex;align-items:baseline;gap:14px;">
                    <div class="bmi-number" style="color:<?= $bmi_color ?>"><?= $bmi ?></div>
                    <div>
                        <div class="bmi-label-text" style="color:<?= $bmi_color ?>"><?= $bmi_label ?></div>
                        <div style="font-size:0.72rem;color:var(--muted);margin-top:2px;">kg/m²</div>
                    </div>
                </div>
                <div class="bmi-bar-wrap">
                    <?php $pct = min(100, max(0, round(($bmi - 15) / (40 - 15) * 100))); ?>
                    <div class="bmi-bar" style="width:<?= $pct ?>%;background:<?= $bmi_color ?>"></div>
                </div>
                <div class="bmi-legend"><span>Sottopeso</span><span>Normopeso</span><span>Obesità</span></div>
                <div class="bmi-detail">
                    Peso: <strong><?= $utente['peso_partenza'] ?> kg</strong><br>
                    Altezza: <strong><?= $utente['altezza'] ?> cm</strong>
                </div>
            </div>
            <?php endif; ?>

        </div>

    </div><!-- /actions-grid -->

</main>

<script>
const sidebar  = document.querySelector('.sidebar');
const logoRest = document.querySelector('.logo-rest');
sidebar.addEventListener('mouseenter', () => logoRest.style.opacity = '1');
sidebar.addEventListener('mouseleave', () => logoRest.style.opacity = '0');

function togglePassword(fieldId, btn) {
    const field = document.getElementById(fieldId);
    field.type = field.type === 'password' ? 'text' : 'password';
    btn.textContent = field.type === 'password' ? '👁' : '🙈';
}

// Indicatore forza password
const pwNuova = document.getElementById('pw_nuova');
const bar     = document.getElementById('strength-bar');
const lbl     = document.getElementById('strength-label');
if (pwNuova) {
    pwNuova.addEventListener('input', () => {
        const v = pwNuova.value;
        let score = 0;
        if (v.length >= 8)          score++;
        if (/[A-Z]/.test(v))        score++;
        if (/[0-9]/.test(v))        score++;
        if (/[^A-Za-z0-9]/.test(v)) score++;
        const map = [
            { w: '0%',   c: 'var(--muted)',  t: '—' },
            { w: '25%',  c: '#f87171',       t: 'Debole' },
            { w: '50%',  c: '#facc15',       t: 'Discreta' },
            { w: '75%',  c: '#60a5fa',       t: 'Buona' },
            { w: '100%', c: '#4eebb0',       t: 'Ottima' },
        ];
        const m = map[score];
        bar.style.width      = m.w;
        bar.style.background = m.c;
        lbl.textContent      = m.t;
        lbl.style.color      = m.c;
    });
}
</script>
</body>
</html>