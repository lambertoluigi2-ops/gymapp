<?php
/**
 * AuthController.php — gestione POST registrazione e recupero password
 */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/palestra/config/database.php';
require_once __DIR__ . '/GeneraSchedaDefault.php';

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

                // 6. Genera scheda di allenamento personalizzata
                generaSchedaDefault($db, $nuovo_id, $obiettivo, $giorni_settimana);

                // 7. Login automatico dopo registrazione
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
?>