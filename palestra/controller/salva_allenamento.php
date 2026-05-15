<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/palestra/config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['utente_id'])) {
    echo json_encode(['ok'=>false]);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id_allenamento = (int)($data['id_allenamento'] ?? 0);
$durata = (int)($data['durata_secondi'] ?? 0);

if (!$id_allenamento) {
    echo json_encode(['ok'=>false]);
    exit();
}

$db = connetti();
$uid = $_SESSION['utente_id'];
$ora_fine = date('H:i:s');

$stmt = $db->prepare("
    UPDATE allenamenti 
    SET ora_fine = ?, durata_sec = ? 
    WHERE id_allenamento = ? AND id_utente = ?
");
$stmt->bind_param("siii", $ora_fine, $durata, $id_allenamento, $uid);

echo json_encode(['ok' => $stmt->execute()]);
?>