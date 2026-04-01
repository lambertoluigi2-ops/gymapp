<?php
session_start();

// Protezione pagina - se non loggato torna al login
if (!isset($_SESSION['utente_id'])) {
    header('Location: /palestra/view/login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - GymApp</title>
</head>
<body>
    <h1>Benvenuto, <?= htmlspecialchars($_SESSION['nome']) ?>!</h1>
    <p>Email: <?= htmlspecialchars($_SESSION['email']) ?></p>
    <p>Obiettivo: <?= htmlspecialchars($_SESSION['obiettivo']) ?></p>
    <p>Giorni settimana: <?= htmlspecialchars($_SESSION['giorni_settimana']) ?></p>
    <br>
    <a href="/palestra/controller/LogoutController.php">Esci</a>
</body>
</html>