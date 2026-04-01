<?php
/**
 * registra.php — gestione POST registrazione e recupero password
 * Questo file viene incluso da login.php oppure chiamato direttamente
 * Mettilo in: /palestra/controller/registra.php
 */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/palestra/config/database.php';

$errore  = "";
$successo = "";
$azione  = $_POST['azione'] ?? '';

// ── REGISTRAZIONE ─────────────────────────────────────────────────────────────
if ($azione === 'registra') {

    // 1. Recupero e sanificazione input
    $nome             = htmlspecialchars(trim($_POST['nome'] ?? ''));
    $cognome          = htmlspecialchars(trim($_POST['cognome'] ?? ''));
    $email            = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password         = trim($_POST['password'] ?? '');
    $peso             = filter_var($_POST['peso'] ?? '', FILTER_VALIDATE_FLOAT);
    $altezza          = filter_var($_POST['altezza'] ?? '', FILTER_VALIDATE_INT);
    $obiettivo        = trim($_POST['obiettivo'] ?? '');
    $giorni_settimana = filter_var($_POST['giorni_settimana'] ?? '', FILTER_VALIDATE_INT);

    $obiettivi_validi = ['perdita_peso', 'massa_muscolare', 'tonificazione'];

    // 2. Validazioni
    if (empty($nome) || empty($cognome) || empty($email) || empty($password)) {
        $errore = "Tutti i campi obbligatori devono essere compilati.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errore = "Formato email non valido.";
    } elseif (strlen($password) < 8) {
        $errore = "La password deve essere di almeno 8 caratteri.";
    } elseif ($peso === false || $peso < 30 || $peso > 300) {
        $errore = "Inserisci un peso valido (30-300 kg).";
    } elseif ($altezza === false || $altezza < 100 || $altezza > 250) {
        $errore = "Inserisci un'altezza valida (100-250 cm).";
    } elseif (!in_array($obiettivo, $obiettivi_validi)) {
        $errore = "Seleziona un obiettivo valido.";
    } elseif ($giorni_settimana === false || $giorni_settimana < 1 || $giorni_settimana > 7) {
        $errore = "Seleziona i giorni di allenamento.";
    } else {
        $db = connetti();

        // 3. Controlla se email già esiste
        $check = $db->prepare("SELECT id_utente FROM utenti WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $errore = "Questa email è già registrata.";
        } else {
            // 4. Cifra la password
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            // 5. Inserimento nel DB con prepared statement
            $stmt = $db->prepare("
                INSERT INTO utenti (nome, cognome, email, password, peso_partenza, altezza, obiettivo, giorni_settimana, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param(
                "ssssdisi",
                $nome, $cognome, $email, $password_hash,
                $peso, $altezza, $obiettivo, $giorni_settimana
            );

            if ($stmt->execute()) {
                $nuovo_id = $db->insert_id;

                // 6. Login automatico dopo registrazione
                $_SESSION['utente_id']        = $nuovo_id;
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

    // Torna al login con errore
    $_SESSION['reg_errore'] = $errore;
    header('Location: /palestra/view/login.php?tab=registrati');
    exit();
}

// ── RECUPERO PASSWORD ─────────────────────────────────────────────────────────
if ($azione === 'recupera') {

    $email = filter_var(trim($_POST['email_recupero'] ?? ''), FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['rec_errore'] = "Inserisci un'email valida.";
    } else {
        $db   = connetti();
        $stmt = $db->prepare("SELECT id_utente, nome FROM utenti WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $utente = $result->fetch_assoc();

        if ($utente) {
            // Genera token sicuro
            $token   = bin2hex(random_bytes(32));
            $scadenza = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Salva token nel DB
            $save = $db->prepare("
                INSERT INTO password_reset (id_utente, token, scadenza)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE token = VALUES(token), scadenza = VALUES(scadenza)
            ");
            $save->bind_param("iss", $utente['id_utente'], $token, $scadenza);
            $save->execute();

            // Link di reset
            $link = "http://localhost/palestra/view/reset_password.php?token=" . $token;

            // Invio email (usa mail() di PHP — in produzione usa SendGrid)
            $oggetto = "GymApp - Reimposta la tua password";
            $corpo   = "Ciao " . $utente['nome'] . ",\n\n";
            $corpo  .= "Clicca il link qui sotto per reimpostare la password.\n";
            $corpo  .= "Il link scade tra 1 ora.\n\n";
            $corpo  .= $link . "\n\n";
            $corpo  .= "Se non hai richiesto il reset, ignora questa email.\n\n";
            $corpo  .= "— Il team di GymApp";

            $headers = "From: noreply@gymapp.it\r\nContent-Type: text/plain; charset=UTF-8";
            mail($email, $oggetto, $corpo, $headers);
        }

        // Messaggio generico per sicurezza (non rivela se email esiste)
        $_SESSION['rec_successo'] = "Se l'email è registrata, riceverai il link entro pochi minuti.";
    }

    header('Location: /palestra/view/login.php?modal=recupero');
    exit();
}
?>
