<?php
function connetti() {
    $conn = new mysqli("localhost", "root", "", "gymapp");
    if ($conn->connect_error) {
        die("Connessione fallita: " . $conn->connect_error);
    }
    return $conn;
}