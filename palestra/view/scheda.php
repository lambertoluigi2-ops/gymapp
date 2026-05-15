<?php
session_start();
if (!isset($_SESSION['utente_id'])) { header('Location: /palestra/view/login.php'); exit(); }
require_once $_SERVER['DOCUMENT_ROOT'] . '/palestra/config/database.php';

$uid       = (int)$_SESSION['utente_id'];
$nome      = htmlspecialchars($_SESSION['nome']);
$obiettivo = $_SESSION['obiettivo'] ?? 'tonificazione';
$db        = connetti();

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? '';

    // Aggiungi esercizio
    if ($azione === 'aggiungi') {
        $id_scheda    = (int)$_POST['id_scheda'];
        $id_esercizio = (int)$_POST['id_esercizio'];
        $giorno       = (int)$_POST['giorno'];
        $serie        = (int)($_POST['serie'] ?? 3);
        $rip          = htmlspecialchars(trim($_POST['ripetizioni'] ?? '10'));
        $peso         = !empty($_POST['peso_kg']) ? (float)$_POST['peso_kg'] : null;
        
        $chk = $db->prepare("SELECT id_scheda FROM schede WHERE id_scheda=? AND id_utente=?");
        $chk->bind_param("ii", $id_scheda, $uid); 
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $st = $db->prepare("INSERT INTO scheda_esercizi (id_scheda,id_esercizio,giorno,serie,ripetizioni,peso_kg,ordine) VALUES (?,?,?,?,?,?,99)");
            $st->bind_param("iiiisi", $id_scheda, $id_esercizio, $giorno, $serie, $rip, $peso);
            $st->execute();
        }
        header('Location: /palestra/view/scheda.php'); exit();
    }

    // Rimuovi esercizio
    if ($azione === 'rimuovi') {
        $id_se = (int)$_POST['id_se'];
        $chk   = $db->prepare("SELECT se.id FROM scheda_esercizi se JOIN schede s ON se.id_scheda=s.id_scheda WHERE se.id=? AND s.id_utente=?");
        $chk->bind_param("ii", $id_se, $uid); 
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $st = $db->prepare("DELETE FROM scheda_esercizi WHERE id=?");
            $st->bind_param("i", $id_se); 
            $st->execute();
        }
        header('Location: /palestra/view/scheda.php'); exit();
    }

    // Nuova scheda
    if ($azione === 'nuova_scheda') {
        $nome_scheda = htmlspecialchars(trim($_POST['nome_scheda'] ?? 'La mia scheda'));
        $st = $db->prepare("INSERT INTO schede (id_utente,nome,obiettivo,attiva) VALUES (?,?,?,0)");
        $st->bind_param("iss", $uid, $nome_scheda, $obiettivo); 
        $st->execute();
        header('Location: /palestra/view/scheda.php'); exit();
    }

    // Cambia scheda attiva
    if ($azione === 'cambia_scheda') {
        $id_scheda = (int)$_POST['id_scheda'];
        
        $chk = $db->prepare("SELECT id_scheda FROM schede WHERE id_scheda=? AND id_utente=?");
        $chk->bind_param("ii", $id_scheda, $uid);
        $chk->execute();
        
        if ($chk->get_result()->num_rows > 0) {
            $db->query("UPDATE schede SET attiva=0 WHERE id_utente=$uid");
            $st = $db->prepare("UPDATE schede SET attiva=1 WHERE id_scheda=?");
            $st->bind_param("i", $id_scheda);
            $st->execute();
        }
        header('Location: /palestra/view/scheda.php'); exit();
    }
    
    // Duplica scheda
    if ($azione === 'duplica') {
        $id_scheda = (int)$_POST['id_scheda'];
        
        $chk = $db->prepare("SELECT nome, obiettivo FROM schede WHERE id_scheda=? AND id_utente=?");
        $chk->bind_param("ii", $id_scheda, $uid);
        $chk->execute();
        $originale = $chk->get_result()->fetch_assoc();
        
        if ($originale) {
            $nome_copia = $originale['nome'] . ' (copia)';
            $st = $db->prepare("INSERT INTO schede (id_utente, nome, obiettivo, attiva) VALUES (?,?,?,0)");
            $st->bind_param("iss", $uid, $nome_copia, $originale['obiettivo']);
            $st->execute();
            $nuova_id = $db->insert_id;
            
            $copia = $db->prepare("
                INSERT INTO scheda_esercizi (id_scheda, id_esercizio, giorno, serie, ripetizioni, peso_kg, ordine)
                SELECT ?, id_esercizio, giorno, serie, ripetizioni, peso_kg, ordine
                FROM scheda_esercizi WHERE id_scheda = ?
            ");
            $copia->bind_param("ii", $nuova_id, $id_scheda);
            $copia->execute();
        }
        header('Location: /palestra/view/scheda.php'); exit();
    }
    
    // Elimina scheda
    if ($azione === 'elimina') {
        $id_scheda = (int)$_POST['id_scheda'];
        
        $count = $db->query("SELECT COUNT(*) as tot FROM schede WHERE id_utente=$uid")->fetch_assoc();
        
        if ($count['tot'] > 1) {
            $chk = $db->prepare("SELECT attiva FROM schede WHERE id_scheda=? AND id_utente=?");
            $chk->bind_param("ii", $id_scheda, $uid);
            $chk->execute();
            $scheda = $chk->get_result()->fetch_assoc();
            
            if ($scheda && $scheda['attiva'] == 1) {
                $altra = $db->query("SELECT id_scheda FROM schede WHERE id_utente=$uid AND id_scheda!=$id_scheda LIMIT 1")->fetch_assoc();
                if ($altra) {
                    $db->query("UPDATE schede SET attiva=0 WHERE id_utente=$uid");
                    $db->query("UPDATE schede SET attiva=1 WHERE id_scheda={$altra['id_scheda']}");
                }
            }
            
            $del = $db->prepare("DELETE FROM schede WHERE id_scheda=? AND id_utente=?");
            $del->bind_param("ii", $id_scheda, $uid);
            $del->execute();
        }
        header('Location: /palestra/view/scheda.php'); exit();
    }
}

// ── Recupera TUTTE le schede dell'utente ─────────────────────────────────────
$rs = $db->prepare("SELECT * FROM schede WHERE id_utente=? ORDER BY attiva DESC, created_at DESC");
$rs->bind_param("i", $uid); 
$rs->execute();
$tutte_schede = $rs->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Trova la scheda attiva ───────────────────────────────────────────────────
$scheda_attiva = null;
foreach ($tutte_schede as $s) {
    if ($s['attiva'] == 1) {
        $scheda_attiva = $s;
        break;
    }
}

if (!$scheda_attiva && count($tutte_schede) > 0) {
    $prima = $tutte_schede[0];
    $db->query("UPDATE schede SET attiva=1 WHERE id_scheda={$prima['id_scheda']}");
    $scheda_attiva = $prima;
}

$id_scheda = $scheda_attiva ? $scheda_attiva['id_scheda'] : 0;

// ── Esercizi della scheda attiva per giorno ──────────────────────────────────
$tutti = [];
if ($id_scheda > 0) {
    $qe = $db->prepare("SELECT se.id,se.giorno,se.serie,se.ripetizioni,se.peso_kg,
                               e.id_esercizio,e.nome,e.gruppo_muscolare,e.tipo
                        FROM scheda_esercizi se
                        JOIN esercizi e ON se.id_esercizio=e.id_esercizio
                        WHERE se.id_scheda=? ORDER BY se.giorno,se.ordine,se.id");
    $qe->bind_param("i", $id_scheda); 
    $qe->execute();
    $tutti = $qe->get_result()->fetch_all(MYSQLI_ASSOC);
}

$per_giorno = [];
foreach ($tutti as $r) { 
    $per_giorno[$r['giorno']][] = $r; 
}

// ── Esercizi disponibili per modale ──────────────────────────────────────────
$disponibili = $db->query("SELECT * FROM esercizi ORDER BY gruppo_muscolare,nome")->fetch_all(MYSQLI_ASSOC);
$per_gruppo  = [];
foreach ($disponibili as $d) { 
    $per_gruppo[$d['gruppo_muscolare']][] = $d; 
}

// ── Storico allenamenti su questa scheda ─────────────────────────────────────
$storico = [];
if ($id_scheda > 0) {
    $qs = $db->prepare("SELECT id_allenamento,data,ora_inizio,durata_sec
                        FROM allenamenti WHERE id_utente=? AND id_scheda=?
                        ORDER BY data DESC,ora_inizio DESC LIMIT 4");
    $qs->bind_param("ii", $uid, $id_scheda); 
    $qs->execute();
    $storico = $qs->get_result()->fetch_all(MYSQLI_ASSOC);
}

$giorni_nome  = ['','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato','Domenica'];
$giorni_short = ['','Lun','Mar','Mer','Gio','Ven','Sab','Dom'];
$oggi_num     = (int)date('N');
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>La mia scheda — GymApp</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,300&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root{
  --accent:#E8FF00;--accent2:#FF4D00;--dark:#0a0a0a;--dark2:#111111;
  --dark3:#1a1a1a;--border:rgba(255,255,255,0.07);--text:#f0f0f0;
  --muted:#666;--muted2:#999;--radius:16px;
}
body{font-family:'DM Sans',sans-serif;background:var(--dark);color:var(--text);min-height:100vh}
.sidebar{position:fixed;top:0;left:0;width:72px;height:100vh;background:var(--dark2);border-right:1px solid var(--border);display:flex;flex-direction:column;align-items:center;padding:24px 0;z-index:100;transition:width .3s cubic-bezier(.16,1,.3,1)}
.sidebar:hover{width:220px}
.sidebar-logo{font-family:'Bebas Neue',sans-serif;font-size:1.5rem;letter-spacing:3px;color:var(--accent);white-space:nowrap;overflow:hidden;width:100%;text-align:center;padding:0 16px;margin-bottom:32px}
.nav-item{width:100%;display:flex;align-items:center;gap:14px;padding:14px 22px;color:var(--muted2);text-decoration:none;font-size:.88rem;font-weight:500;transition:all .2s;white-space:nowrap;overflow:hidden;border-left:2px solid transparent}
.nav-item:hover{color:var(--text);background:rgba(255,255,255,.04)}
.nav-item.active{color:var(--accent);border-left-color:var(--accent);background:rgba(232,255,0,.05)}
.nav-icon{font-size:1.2rem;min-width:24px;text-align:center}
.nav-label{opacity:0;transition:opacity .2s}
.sidebar:hover .nav-label{opacity:1}
.nav-spacer{flex:1}
.main{margin-left:72px;min-height:100vh;padding:32px}
.topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px}
.topbar h1{font-family:'Bebas Neue',sans-serif;font-size:2rem;letter-spacing:2px;line-height:1}
.topbar p{font-size:.85rem;color:var(--muted2);margin-top:4px}
.scheda-header{background:var(--dark2);border:1px solid var(--border);border-radius:var(--radius);padding:22px 28px;display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:22px;animation:fadeUp .4s both}
.scheda-nome{font-family:'Bebas Neue',sans-serif;font-size:1.7rem;letter-spacing:2px}
.scheda-meta{font-size:.81rem;color:var(--muted2);margin-top:4px}
.scheda-actions{display:flex;gap:8px;align-items:center;flex-shrink:0}
.badge{padding:4px 14px;border-radius:20px;font-size:.72rem;font-weight:600;background:rgba(232,255,0,.08);color:var(--accent);border:1px solid rgba(232,255,0,.18);white-space:nowrap}
.btn-primary{padding:10px 22px;background:var(--accent);color:#000;border:none;border-radius:8px;font-family:'Bebas Neue',sans-serif;font-size:.9rem;letter-spacing:2px;cursor:pointer;text-decoration:none;display:inline-block;transition:transform .15s,box-shadow .2s}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(232,255,0,.25)}
.btn-ghost{padding:9px 16px;background:transparent;border:1px solid var(--border);border-radius:8px;color:var(--muted2);font-size:.82rem;cursor:pointer;transition:all .2s;white-space:nowrap;font-family:'DM Sans',sans-serif}
.btn-ghost:hover{border-color:rgba(255,255,255,.2);color:var(--text)}
.content-grid{display:grid;grid-template-columns:1fr 290px;gap:20px;align-items:start}
.giorni-tabs{display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap}
.tab-btn{padding:7px 15px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--muted2);font-family:'DM Sans',sans-serif;font-size:.82rem;cursor:pointer;transition:all .2s}
.tab-btn:hover{background:rgba(255,255,255,.04);color:var(--text)}
.tab-btn.active{background:rgba(232,255,0,.08);border-color:rgba(232,255,0,.3);color:var(--accent)}
.tab-btn.oggi-tab{border-color:rgba(232,255,0,.2)}
.tab-count{font-size:.68rem;color:var(--muted);margin-left:4px}
.tab-btn.active .tab-count{color:rgba(232,255,0,.55)}
.giorno-panel{display:none;animation:fadeUp .25s both}
.giorno-panel.show{display:block}
.riposo-box{text-align:center;padding:44px 24px;border:1px dashed rgba(255,255,255,.07);border-radius:var(--radius);color:var(--muted)}
.riposo-box .ico{font-size:2.4rem;margin-bottom:10px}
.riposo-box p{font-size:.84rem}
.ex-card{display:flex;align-items:center;gap:14px;padding:14px 18px;background:var(--dark2);border:1px solid var(--border);border-radius:12px;margin-bottom:8px;transition:border-color .2s}
.ex-card:hover{border-color:rgba(232,255,0,.14)}
.ex-num{font-family:'Bebas Neue',sans-serif;font-size:1.2rem;color:var(--muted);min-width:26px;text-align:center}
.ex-info{flex:1;min-width:0}
.ex-nome{font-size:.92rem;font-weight:600}
.ex-detail{font-size:.75rem;color:var(--muted2);margin-top:2px}
.ex-gruppo{font-size:.68rem;padding:3px 10px;border-radius:20px;background:rgba(232,255,0,.07);color:var(--accent);border:1px solid rgba(232,255,0,.12);white-space:nowrap;flex-shrink:0}
.btn-del{background:none;border:none;color:var(--muted);cursor:pointer;font-size:1rem;padding:4px 8px;border-radius:6px;transition:all .2s;flex-shrink:0}
.btn-del:hover{color:var(--accent2);background:rgba(255,77,0,.08)}
.btn-add-ex{width:100%;padding:11px;margin-top:6px;border:1px dashed rgba(232,255,0,.2);border-radius:10px;background:transparent;color:rgba(232,255,0,.7);cursor:pointer;font-size:.83rem;transition:all .2s;font-family:'DM Sans',sans-serif}
.btn-add-ex:hover{background:rgba(232,255,0,.05);border-color:rgba(232,255,0,.45)}
.side-card{background:var(--dark2);border:1px solid var(--border);border-radius:var(--radius);padding:22px;animation:fadeUp .45s both}
.side-title{font-family:'Bebas Neue',sans-serif;font-size:1rem;letter-spacing:2px;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.side-dot{width:6px;height:6px;border-radius:50%;background:var(--accent)}
.storico-item{display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid var(--border)}
.storico-item:last-child{border-bottom:none;padding-bottom:0}
.st-data{text-align:center;min-width:38px}
.st-giorno{font-family:'Bebas Neue',sans-serif;font-size:1.3rem;line-height:1;color:var(--text)}
.st-mese{font-size:.62rem;color:var(--muted2);text-transform:uppercase;letter-spacing:1px}
.st-sep{width:1px;height:32px;background:var(--border);flex-shrink:0}
.st-info{flex:1;min-width:0}
.st-nome{font-size:.84rem;font-weight:500}
.st-meta{font-size:.72rem;color:var(--muted2);margin-top:2px}
.st-badge{font-size:.66rem;padding:3px 9px;border-radius:20px;background:rgba(78,235,176,.1);color:#4eebb0;border:1px solid rgba(78,235,176,.2)}
.empty-side{text-align:center;padding:24px 12px;color:var(--muted)}
.empty-side .ei{font-size:2rem;margin-bottom:8px}
.empty-side p{font-size:.78rem}
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.78);display:none;z-index:200;align-items:center;justify-content:center}
.overlay.open{display:flex}
.modal{background:var(--dark2);border:1px solid var(--border);border-radius:var(--radius);padding:28px;width:min(500px,92vw);max-height:82vh;overflow-y:auto}
.modal h3{font-family:'Bebas Neue',sans-serif;font-size:1.3rem;letter-spacing:2px;margin-bottom:20px}
.f-row{margin-bottom:14px}
.f-row label{display:block;font-size:.73rem;color:var(--muted2);margin-bottom:5px;text-transform:uppercase;letter-spacing:1px}
.f-row select,.f-row input{width:100%;padding:9px 13px;background:var(--dark3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:.88rem}
.f-row select:focus,.f-row input:focus{outline:none;border-color:rgba(232,255,0,.3)}
.f-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}
.modal-btns{display:flex;gap:10px;margin-top:18px}
.btn-cancel{padding:11px 18px;background:transparent;border:1px solid var(--border);border-radius:8px;color:var(--muted2);cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s}
.btn-cancel:hover{border-color:var(--muted);color:var(--text)}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:900px){.content-grid{grid-template-columns:1fr}}
@media(max-width:600px){.main{padding:20px 16px}.scheda-header{flex-direction:column;align-items:flex-start}}

/* Nuovi stili per menu a tendina */
.scheda-selector {
    background: var(--dark3);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 8px 12px;
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    font-size: 0.85rem;
    cursor: pointer;
    min-width: 200px;
}
.scheda-selector:hover {
    border-color: var(--accent);
}
.scheda-actions-group {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}
.dropdown-actions {
    position: relative;
    display: inline-block;
}
.dropdown-actions .btn-ghost {
    padding: 8px 12px;
}
.dropdown-content {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    background: var(--dark2);
    border: 1px solid var(--border);
    border-radius: 10px;
    min-width: 160px;
    z-index: 10;
    margin-top: 4px;
}
.dropdown-content a, .dropdown-content button {
    display: block;
    width: 100%;
    text-align: left;
    padding: 10px 16px;
    color: var(--text);
    text-decoration: none;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 0.85rem;
    font-family: 'DM Sans', sans-serif;
}
.dropdown-content a:hover, .dropdown-content button:hover {
    background: rgba(232,255,0,0.08);
    color: var(--accent);
}
.dropdown-actions:hover .dropdown-content {
    display: block;
}
</style>
</head>
<body>

<nav class="sidebar">
    <div class="sidebar-logo">G<span style="opacity:0;transition:opacity .2s" class="logo-rest">ymApp</span></div>
    <a href="/palestra/view/dashboard.php"   class="nav-item"><span class="nav-icon">🏠</span><span class="nav-label">Home</span></a>
    <a href="/palestra/view/scheda.php"      class="nav-item active"><span class="nav-icon">📋</span><span class="nav-label">La mia scheda</span></a>
    <a href="/palestra/view/allenamento.php" class="nav-item"><span class="nav-icon">▶️</span><span class="nav-label">Avvia allenamento</span></a>
    <a href="/palestra/view/progressi.php"   class="nav-item"><span class="nav-icon">📊</span><span class="nav-label">Progressi</span></a>
    <a href="/palestra/view/calendario.php" class="nav-item">
    <span class="nav-icon">📅</span>
    <span class="nav-label">Calendario</span>
    </a>

    <a href="/palestra/view/profilo.php"     class="nav-item"><span class="nav-icon">👤</span><span class="nav-label">Profilo</span></a>
    <div class="nav-spacer"></div>
    <a href="/palestra/controller/LogoutController.php" class="nav-item"><span class="nav-icon">🚪</span><span class="nav-label">Esci</span></a>
</nav>

<main class="main">

    <div class="topbar">
        <div>
            <h1>La mia scheda</h1>
            <p>Gestisci il tuo programma settimanale</p>
        </div>
        <a href="#" class="btn-primary">▶ AVVIA</a>
    </div>

    <div class="scheda-header">
        <div style="flex:1">
            <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                <form method="POST" style="margin:0" id="cambiaSchedaForm">
                    <input type="hidden" name="azione" value="cambia_scheda">
                    <select name="id_scheda" class="scheda-selector" onchange="this.form.submit()">
                        <?php foreach ($tutte_schede as $s): ?>
                            <option value="<?= $s['id_scheda'] ?>" <?= $s['attiva'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['nome']) ?>
                                <?php if ($s['attiva']): ?> ⭐ <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                
                <div class="scheda-actions-group">
                    <div class="dropdown-actions">
                        <button type="button" class="btn-ghost">⚙️ Azioni ▼</button>
                        <div class="dropdown-content">
                            <form method="POST" style="margin:0">
                                <input type="hidden" name="azione" value="duplica">
                                <input type="hidden" name="id_scheda" value="<?= $id_scheda ?>">
                                <button type="submit">📋 Duplica scheda</button>
                            </form>
                            <?php if (count($tutte_schede) > 1): ?>
                            <form method="POST" style="margin:0" onsubmit="return confirm('Eliminare questa scheda? L\'operazione è irreversibile.');">
                                <input type="hidden" name="azione" value="elimina">
                                <input type="hidden" name="id_scheda" value="<?= $id_scheda ?>">
                                <button type="submit" style="color: var(--accent2)">🗑️ Elimina scheda</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button class="btn-ghost" onclick="document.getElementById('overlayNuova').classList.add('open')">+ Nuova scheda</button>
                </div>
            </div>
            <?php if ($scheda_attiva): ?>
            <div class="scheda-meta" style="margin-top: 12px;">
                Creata il <?= date('d/m/Y', strtotime($scheda_attiva['created_at'])) ?>
                &nbsp;·&nbsp; <?= count($tutti) ?> esercizi totali
                &nbsp;·&nbsp; <span style="color: var(--accent)">⭐ Scheda attiva</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$scheda_attiva): ?>
        <div class="side-card" style="text-align: center; padding: 60px;">
            <div style="font-size: 3rem; margin-bottom: 16px;">📋</div>
            <h3 style="margin-bottom: 12px;">Nessuna scheda trovata</h3>
            <p style="color: var(--muted2); margin-bottom: 24px;">Crea la tua prima scheda di allenamento per iniziare!</p>
            <button class="btn-primary" onclick="document.getElementById('overlayNuova').classList.add('open')">+ CREA LA PRIMA SCHEDA</button>
        </div>
    <?php else: ?>

    <div class="content-grid">

        <div>
            <div class="giorni-tabs">
                <?php for ($g = 1; $g <= 7; $g++): ?>
                <button class="tab-btn <?= $g===$oggi_num?'oggi-tab':'' ?>" id="tab-<?= $g ?>" onclick="switchGiorno(<?= $g ?>)">
                    <?= $giorni_short[$g] ?>
                    <?php if (!empty($per_giorno[$g])): ?>
                        <span class="tab-count"><?= count($per_giorno[$g]) ?></span>
                    <?php endif; ?>
                </button>
                <?php endfor; ?>
            </div>

            <?php for ($g = 1; $g <= 7; $g++): ?>
            <div class="giorno-panel" id="panel-<?= $g ?>">
                <?php if (empty($per_giorno[$g])): ?>
                <div class="riposo-box">
                    <div class="ico">😴</div>
                    <p><?= $giorni_nome[$g] ?> — giorno di riposo</p>
                </div>
                <?php else: ?>
                    <?php foreach ($per_giorno[$g] as $i => $ex): ?>
                    <div class="ex-card">
                        <div class="ex-num"><?= $i+1 ?></div>
                        <div class="ex-info">
                            <div class="ex-nome"><?= htmlspecialchars($ex['nome']) ?> <?= $tipo_icon[$ex['tipo']] ?? '' ?></div>
                            <div class="ex-detail">
                                <?= $ex['serie'] ?> serie &middot; <?= htmlspecialchars($ex['ripetizioni']) ?> rip
                                <?= $ex['peso_kg'] ? ' &middot; '.$ex['peso_kg'].' kg' : '' ?>
                            </div>
                        </div>
                        <div class="ex-gruppo"><?= htmlspecialchars($ex['gruppo_muscolare']) ?></div>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="azione" value="rimuovi">
                            <input type="hidden" name="id_se" value="<?= $ex['id'] ?>">
                            <button type="submit" class="btn-del" onclick="return confirm('Rimuovere <?= addslashes(htmlspecialchars($ex['nome'])) ?>?')">✕</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <button class="btn-add-ex" onclick="apriModal(<?= $g ?>)">+ Aggiungi esercizio — <?= $giorni_nome[$g] ?></button>
            </div>
            <?php endfor; ?>
        </div>

        <div class="side-card">
            <div class="side-title"><div class="side-dot"></div>ULTIMI ALLENAMENTI</div>
            <?php if (empty($storico)): ?>
            <div class="empty-side">
                <div class="ei">📭</div>
                <p>Nessuna sessione ancora.<br>Avvia il primo allenamento!</p>
            </div>
            <?php else: ?>
                <?php foreach ($storico as $s):
                    $min = $s['durata_sec'] ? round($s['durata_sec']/60) : 0;
                ?>
                <div class="storico-item">
                    <div class="st-data">
                        <div class="st-giorno"><?= date('d', strtotime($s['data'])) ?></div>
                        <div class="st-mese"><?= date('M', strtotime($s['data'])) ?></div>
                    </div>
                    <div class="st-sep"></div>
                    <div class="st-info">
                        <div class="st-nome">Allenamento</div>
                        <div class="st-meta"><?= $min ?>min &middot; <?= date('H:i', strtotime($s['ora_inizio'])) ?></div>
                    </div>
                    <div class="st-badge">✓</div>
                </div>
                <?php endforeach; ?>
                <a href="/palestra/view/progressi.php" style="display:block;margin-top:14px;text-align:center;font-size:.78rem;color:var(--accent);text-decoration:none">Vedi tutti i progressi →</a>
            <?php endif; ?>
        </div>

                </div>
                <?php endif; ?>
        </div>
    </div>
</main>

<script>
const sidebar = document.querySelector('.sidebar');
const logoRest = document.querySelector('.logo-rest');
sidebar.addEventListener('mouseenter', () => logoRest.style.opacity = '1');
sidebar.addEventListener('mouseleave', () => logoRest.style.opacity = '0');

function switchGiorno(g) {
    document.querySelectorAll('.giorno-panel').forEach(p => p.classList.remove('show'));
    document.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
    document.getElementById('panel-' + g).classList.add('show');
    document.getElementById('tab-' + g).classList.add('active');
}

function apriModal(g) {
    document.getElementById('modal-giorno').value = g;
    document.getElementById('overlayEsercizio').classList.add('open');
}

function chiudiModal(id) {
    document.getElementById(id).classList.remove('open');
}

document.querySelectorAll('.overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});

switchGiorno(<?= $oggi_num ?>);
</script>

<!-- Modal aggiungi esercizio -->
<div class="overlay" id="overlayEsercizio">
    <div class="modal">
        <h3>Aggiungi Esercizio</h3>
        <form method="POST">
            <input type="hidden" name="azione" value="aggiungi">
            <input type="hidden" name="id_scheda" value="<?= $id_scheda ?>">
            <input type="hidden" name="giorno" id="modal-giorno" value="1">
            <div class="f-row">
                <label>Esercizio</label>
                <select name="id_esercizio" required>
                    <option value="">— Seleziona —</option>
                    <?php foreach ($per_gruppo as $gruppo => $esList): ?>
                    <optgroup label="<?= htmlspecialchars($gruppo) ?>">
                        <?php foreach ($esList as $e): ?>
                        <option value="<?= $e['id_esercizio'] ?>"><?= htmlspecialchars($e['nome']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="f-grid">
                <div class="f-row">
                    <label>Serie</label>
                    <input type="number" name="serie" value="3" min="1" max="10">
                </div>
                <div class="f-row">
                    <label>Ripetizioni</label>
                    <input type="text" name="ripetizioni" value="10" placeholder="10 / 30s">
                </div>
                <div class="f-row">
                    <label>Peso kg</label>
                    <input type="number" name="peso_kg" step="0.5" placeholder="opz.">
                </div>
            </div>
            <div class="modal-btns">
                <button type="submit" class="btn-primary" style="flex:1">AGGIUNGI</button>
                <button type="button" class="btn-cancel" onclick="chiudiModal('overlayEsercizio')">Annulla</button>
            </div>
        </form>
    </div>
</div>


<!-- Modal nuova scheda -->
<div class="overlay" id="overlayNuova">
    <div class="modal">
        <h3>Nuova Scheda</h3>
        <p style="font-size:.82rem;color:var(--muted2);margin-bottom:18px">
            Inserisci il nome della nuova scheda (sarà vuota, poi potrai aggiungere esercizi).
        </p>
        <form method="POST">
            <input type="hidden" name="azione" value="nuova_scheda">
            <div class="f-row">
                <label>Nome scheda</label>
                <input type="text" name="nome_scheda" placeholder="Es. Scheda Push Pull Legs" required>
            </div>
            <div class="modal-btns">
                <button type="submit" class="btn-primary" style="flex:1">CREA</button>
                <button type="button" class="btn-cancel" onclick="chiudiModal('overlayNuova')">Annulla</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>