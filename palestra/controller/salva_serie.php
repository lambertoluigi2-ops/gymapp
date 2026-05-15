<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/palestra/config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['utente_id'])) {
    echo json_encode(['ok'=>false, 'error'=>'Non autenticato']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id_allenamento = (int)($data['id_allenamento'] ?? 0);
$esercizio_id = (int)($data['esercizio_id'] ?? 0);
$ripetizioni = (int)($data['ripetizioni'] ?? 0);
$peso = (float)($data['peso'] ?? 0);

if (!$id_allenamento || !$esercizio_id) {
    echo json_encode(['ok'=>false, 'error'=>'Dati mancanti']);
    exit();
}

$db = connetti();

// Conta quante serie già eseguite per questo esercizio in questo allenamento
$count = $db->prepare("
    SELECT COUNT(*) AS totale 
    FROM sessione_esercizi 
    WHERE id_allenamento = ? AND id_esercizio = ?
");
$count->bind_param("ii", $id_allenamento, $esercizio_id);
$count->execute();
$res = $count->get_result()->fetch_assoc();
$serie_num = $res['totale'] + 1;

$stmt = $db->prepare("
    INSERT INTO sessione_esercizi (id_allenamento, id_esercizio, serie_eseguite, ripetizioni_fatte, peso_usato, completato) 
    VALUES (?, ?, ?, ?, ?, 1)
");
$stmt->bind_param("iiiid", $id_allenamento, $esercizio_id, $serie_num, $ripetizioni, $peso);

if ($stmt->execute()) {
    echo json_encode(['ok'=>true]);
} else {
    echo json_encode(['ok'=>false, 'error'=>$stmt->error]);
}
?>