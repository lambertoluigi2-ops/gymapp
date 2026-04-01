<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/palestra/config/database.php';

$errore = "";
$successo = "";

// Se già loggato, vai alla dashboard
if (isset($_SESSION['utente_id'])) {
    header('Location: /palestra/view/dashboard.php');
    exit();
}

// Recupero email dal cookie se presente
$email_cookie = isset($_COOKIE['ricorda_email']) ? htmlspecialchars($_COOKIE['ricorda_email']) : '';


// ── POST REGISTRAZIONE ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['azione']) && $_POST['azione'] === 'registra') {

    $nome             = htmlspecialchars(trim($_POST['nome'] ?? ''));
    $cognome          = htmlspecialchars(trim($_POST['cognome'] ?? ''));
    $email            = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password         = trim($_POST['password'] ?? '');
    $peso             = filter_var($_POST['peso'] ?? '', FILTER_VALIDATE_FLOAT);
    $altezza          = filter_var($_POST['altezza'] ?? '', FILTER_VALIDATE_INT);
    $obiettivo        = trim($_POST['obiettivo'] ?? '');
    $giorni_settimana = filter_var($_POST['giorni_settimana'] ?? '', FILTER_VALIDATE_INT);

    if (empty($nome) || empty($cognome) || empty($email) || empty($password)) {
        $errore = "Tutti i campi obbligatori devono essere compilati.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errore = "Formato email non valido.";
    } elseif (strlen($password) < 8) {
        $errore = "La password deve essere di almeno 8 caratteri.";
    } else {
        $db = connetti();

        // Controlla se email già esiste
        $check = $db->prepare("SELECT id_utente FROM utenti WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $errore = "Questa email è già registrata.";
        } else {
            // Cifra la password
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $db->prepare("
                INSERT INTO utenti (nome, cognome, email, password, peso_partenza, altezza, obiettivo, giorni_settimana, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("ssssdiis",
                $nome, $cognome, $email, $password_hash,
                $peso, $altezza, $obiettivo, $giorni_settimana
            );

            if ($stmt->execute()) {
                $_SESSION['utente_id']        = $db->insert_id;
                $_SESSION['nome']             = $nome;
                $_SESSION['email']            = $email;
                $_SESSION['obiettivo']        = $obiettivo;
                $_SESSION['giorni_settimana'] = $giorni_settimana;

                header('Location: /palestra/view/dashboard.php');
                exit();
            } else {
                $errore = "Errore durante la registrazione. Riprova.";
            }
        }
    }
}


// ── POST LOGIN ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['azione']) && $_POST['azione'] === 'login') {

    $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST['password'] ?? '');
    $ricorda  = isset($_POST['ricorda']);

    if (empty($email) || empty($password)) {
        $errore = "Inserisci email e password.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errore = "Email non valida.";
    } else {
        $db   = connetti();
        $stmt = $db->prepare("SELECT id_utente, nome, cognome, email, password, obiettivo, giorni_settimana FROM utenti WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $utente = $result->fetch_assoc();

        if ($utente && password_verify($password, $utente['password'])) {
            // Sessione
            $_SESSION['utente_id']        = $utente['id_utente'];
            $_SESSION['nome']             = $utente['nome'];
            $_SESSION['email']            = $utente['email'];
            $_SESSION['obiettivo']        = $utente['obiettivo'];
            $_SESSION['giorni_settimana'] = $utente['giorni_settimana'];

            // Cookie ricorda (30 giorni)
            if ($ricorda) {
                setcookie('ricorda_email', $email, time() + (30 * 24 * 60 * 60), '/', '', false, true);
            } else {
                setcookie('ricorda_email', '', time() - 3600, '/');
            }

            header('Location: /palestra/view/dashboard.php');
            exit();
        } else {
            $errore = "Email o password errati.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GymApp — Accedi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --accent:    #E8FF00;
            --accent2:   #FF4D00;
            --dark:      #0a0a0a;
            --card-bg:   rgba(10,10,10,0.82);
            --border:    rgba(255,255,255,0.08);
            --text:      #f0f0f0;
            --muted:     #888;
            --input-bg:  rgba(255,255,255,0.05);
            --radius:    14px;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--dark);
            overflow: hidden;
        }

        /* Sfondo palestra */
        .bg {
            position: fixed; inset: 0; z-index: 0;
            background-image: url('https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=1600&q=80');
            background-size: cover;
            background-position: center;
            filter: brightness(0.25) saturate(0.6);
        }
        .bg-overlay {
            position: fixed; inset: 0; z-index: 1;
            background: linear-gradient(135deg, rgba(0,0,0,0.7) 0%, rgba(10,10,10,0.5) 100%);
        }

        /* Noise texture */
        .bg::after {
            content: ''; position: absolute; inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
            opacity: 0.4;
        }

        .wrapper {
            position: relative; z-index: 2;
            width: 100%; max-width: 440px;
            padding: 24px;
            animation: fadeUp 0.6s cubic-bezier(.16,1,.3,1) both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(28px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Logo */
        .logo {
            text-align: center;
            margin-bottom: 32px;
        }
        .logo-text {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 3.2rem;
            letter-spacing: 4px;
            color: var(--accent);
            line-height: 1;
            text-shadow: 0 0 40px rgba(232,255,0,0.3);
        }
        .logo-sub {
            font-size: 0.78rem;
            color: var(--muted);
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-top: 4px;
        }

        /* Card */
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 36px 32px;
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            box-shadow: 0 24px 80px rgba(0,0,0,0.6);
        }

        /* Tabs */
        .tabs {
            display: flex;
            background: rgba(255,255,255,0.04);
            border-radius: 10px;
            padding: 4px;
            margin-bottom: 28px;
            gap: 4px;
        }
        .tab-btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 8px;
            background: transparent;
            color: var(--muted);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.88rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.25s;
            letter-spacing: 0.5px;
        }
        .tab-btn.active {
            background: var(--accent);
            color: #000;
            font-weight: 600;
        }

        /* Form panels */
        .panel { display: none; }
        .panel.active { display: block; }

        /* Labels & inputs */
        .field { margin-bottom: 18px; }
        label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 7px;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="date"],
        input[type="number"],
        select {
            width: 100%;
            padding: 13px 16px;
            background: var(--input-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s, background 0.2s;
            appearance: none;
        }
        input:focus, select:focus {
            border-color: var(--accent);
            background: rgba(232,255,0,0.04);
        }
        select option { background: #1a1a1a; }

        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

        /* Password wrapper */
        .pw-wrap { position: relative; }
        .pw-wrap input { padding-right: 44px; }
        .pw-toggle {
            position: absolute; right: 14px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: var(--muted); cursor: pointer;
            font-size: 1rem; padding: 0;
            transition: color 0.2s;
        }
        .pw-toggle:hover { color: var(--accent); }

        /* Checkbox */
        .check-row {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 20px;
        }
        .check-row input[type="checkbox"] {
            width: 18px; height: 18px;
            accent-color: var(--accent);
            cursor: pointer;
        }
        .check-row label {
            margin: 0; font-size: 0.85rem; color: var(--muted);
            text-transform: none; letter-spacing: 0; font-weight: 400;
            cursor: pointer;
        }

        /* Btn principale */
        .btn-main {
            width: 100%;
            padding: 14px;
            background: var(--accent);
            color: #000;
            border: none;
            border-radius: var(--radius);
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.15rem;
            letter-spacing: 2px;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.2s, background 0.2s;
            margin-top: 4px;
        }
        .btn-main:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(232,255,0,0.35);
        }
        .btn-main:active { transform: translateY(0); }

        /* Link recupero */
        .link-small {
            display: block;
            text-align: right;
            font-size: 0.8rem;
            color: var(--muted);
            text-decoration: none;
            margin-bottom: 20px;
            transition: color 0.2s;
        }
        .link-small:hover { color: var(--accent); }

        /* Alert */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            display: flex; align-items: center; gap: 8px;
        }
        .alert-err  { background: rgba(255,77,0,0.12); border: 1px solid rgba(255,77,0,0.3); color: #ff7752; }
        .alert-ok   { background: rgba(0,200,100,0.1);  border: 1px solid rgba(0,200,100,0.3); color: #4eebb0; }

        /* Separatore */
        .sep {
            display: flex; align-items: center; gap: 12px;
            margin: 22px 0;
            color: var(--muted); font-size: 0.78rem;
        }
        .sep::before, .sep::after {
            content: ''; flex: 1;
            height: 1px; background: var(--border);
        }

        /* Strength bar */
        .strength-bar { margin-top: 8px; display: flex; gap: 4px; }
        .strength-bar span {
            flex: 1; height: 3px; border-radius: 2px;
            background: rgba(255,255,255,0.08);
            transition: background 0.3s;
        }
        .str-1 span:nth-child(1) { background: var(--accent2); }
        .str-2 span:nth-child(-n+2) { background: #ffaa00; }
        .str-3 span:nth-child(-n+3) { background: #aadd00; }
        .str-4 span { background: #00dd88; }

        /* Modal recupero */
        .modal-overlay {
            display: none; position: fixed; inset: 0; z-index: 100;
            background: rgba(0,0,0,0.7); backdrop-filter: blur(6px);
            align-items: center; justify-content: center;
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: #111;
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 36px 32px;
            width: 100%; max-width: 400px;
            margin: 24px;
            animation: fadeUp 0.3s both;
        }
        .modal h3 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.8rem;
            letter-spacing: 2px;
            color: var(--accent);
            margin-bottom: 10px;
        }
        .modal p { color: var(--muted); font-size: 0.88rem; margin-bottom: 22px; line-height: 1.6; }
        .modal-close {
            position: absolute; top: 16px; right: 20px;
            background: none; border: none; color: var(--muted);
            font-size: 1.4rem; cursor: pointer;
        }
        .modal { position: relative; }
    </style>
</head>
<body>

<div class="bg"></div>
<div class="bg-overlay"></div>

<div class="wrapper">
    <div class="logo">
        <div class="logo-text">GymApp</div>
        <div class="logo-sub">Il tuo allenamento personale</div>
    </div>

    <div class="card">

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('login')">Accedi</button>
            <button class="tab-btn" onclick="switchTab('registrati')">Registrati</button>
        </div>

        <!-- ── LOGIN ── -->
        <div id="panel-login" class="panel active">

            <?php if ($errore && $_POST['azione'] ?? '' === 'login'): ?>
                <div class="alert alert-err">⚠ <?= $errore ?></div>
            <?php endif; ?>
            <?php if ($successo): ?>
                <div class="alert alert-ok">✓ <?= $successo ?></div>
            <?php endif; ?>

            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                <input type="hidden" name="azione" value="login">

                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email"
                           value="<?= $email_cookie ?>"
                           placeholder="mario@email.com"
                           required autocomplete="email">
                </div>

                <div class="field">
                    <label>Password</label>
                    <div class="pw-wrap">
                        <input type="password" name="password" id="pw-login"
                               placeholder="••••••••" required autocomplete="current-password">
                        <button type="button" class="pw-toggle" onclick="togglePw('pw-login', this)">👁</button>
                    </div>
                </div>

                <a href="#" class="link-small" onclick="openModal()">Password dimenticata?</a>

                <div class="check-row">
                    <input type="checkbox" name="ricorda" id="ricorda"
                           <?= $email_cookie ? 'checked' : '' ?>>
                    <label for="ricorda">Ricordami su questo dispositivo</label>
                </div>

                <button type="submit" class="btn-main">ENTRA</button>
            </form>
        </div>

        <!-- ── REGISTRATI ── -->
        <div id="panel-registrati" class="panel">

            <?php if ($errore && ($_POST['azione'] ?? '') === 'registra'): ?>
                <div class="alert alert-err">⚠ <?= $errore ?></div>
            <?php endif; ?>

            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                <input type="hidden" name="azione" value="registra">

                <div class="field-row">
                    <div class="field">
                        <label>Nome</label>
                        <input type="text" name="nome" placeholder="Mario" required>
                    </div>
                    <div class="field">
                        <label>Cognome</label>
                        <input type="text" name="cognome" placeholder="Rossi" required>
                    </div>
                </div>

                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="mario@email.com" required>
                </div>

                <div class="field">
                    <label>Password</label>
                    <div class="pw-wrap">
                        <input type="password" name="password" id="pw-reg"
                               placeholder="Minimo 8 caratteri"
                               required minlength="8"
                               oninput="checkStrength(this.value)">
                        <button type="button" class="pw-toggle" onclick="togglePw('pw-reg', this)">👁</button>
                    </div>
                    <div class="strength-bar" id="strength-bar">
                        <span></span><span></span><span></span><span></span>
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label>Peso attuale (kg)</label>
                        <input type="number" name="peso" placeholder="70" step="0.1" min="30" max="300" required>
                    </div>
                    <div class="field">
                        <label>Altezza (cm)</label>
                        <input type="number" name="altezza" placeholder="175" min="100" max="250" required>
                    </div>
                </div>

                <div class="field">
                    <label>Obiettivo</label>
                    <select name="obiettivo" required>
                        <option value="" disabled selected>Scegli il tuo obiettivo</option>
                        <option value="perdita_peso">Perdita peso</option>
                        <option value="massa_muscolare">Aumento massa muscolare</option>
                        <option value="tonificazione">Tonificazione</option>
                    </select>
                </div>

                <div class="field">
                    <label>Giorni a settimana in palestra</label>
                    <select name="giorni_settimana" required>
                        <option value="" disabled selected>Quanti giorni?</option>
                        <option value="2">2 giorni</option>
                        <option value="3">3 giorni</option>
                        <option value="4">4 giorni</option>
                        <option value="5">5 giorni</option>
                    </select>
                </div>

                <button type="submit" class="btn-main">CREA ACCOUNT</button>
            </form>
        </div>

    </div><!-- /card -->
</div>

<!-- Modal recupero password -->
<div class="modal-overlay" id="modal-recupero">
    <div class="modal">
        <button class="modal-close" onclick="closeModal()">✕</button>
        <h3>RECUPERA PASSWORD</h3>
        <p>Inserisci la tua email. Ti invieremo un link per reimpostare la password.</p>

        <?php if (($errore || $successo) && ($_POST['azione'] ?? '') === 'recupera'): ?>
            <div class="alert <?= $successo ? 'alert-ok' : 'alert-err' ?>">
                <?= $successo ?: $errore ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
            <input type="hidden" name="azione" value="recupera">
            <div class="field">
                <label>La tua email</label>
                <input type="email" name="email_recupero" placeholder="mario@email.com" required>
            </div>
            <button type="submit" class="btn-main">INVIA LINK</button>
        </form>
    </div>
</div>

<script>
// Tab switch
function switchTab(tab) {
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('panel-' + tab).classList.add('active');
    event.target.classList.add('active');
}

// Mostra/nascondi password
function togglePw(id, btn) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.textContent = inp.type === 'password' ? '👁' : '🙈';
}

// Strength bar
function checkStrength(pw) {
    let score = 0;
    if (pw.length >= 8)  score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    const bar = document.getElementById('strength-bar');
    bar.className = 'strength-bar' + (score ? ' str-' + score : '');
}

// Modal
function openModal()  { document.getElementById('modal-recupero').classList.add('open'); }
function closeModal() { document.getElementById('modal-recupero').classList.remove('open'); }
document.getElementById('modal-recupero').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Apri modal se il POST era recupera
<?php if (($_POST['azione'] ?? '') === 'recupera'): ?>
window.addEventListener('load', openModal);
<?php endif; ?>

// Apri tab registrati se il POST era registra
<?php if (($_POST['azione'] ?? '') === 'registra'): ?>
window.addEventListener('load', () => {
    document.querySelectorAll('.tab-btn')[1].click();
});
<?php endif; ?>
</script>
</body>
</html>
