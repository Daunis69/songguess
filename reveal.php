<?php
session_start();
header('Content-Type: application/json');

$answer = $_SESSION['current_track']['label'] ?? 'Unknown';

echo json_encode([
    'ok' => true,
    'answer' => $answer
]);
exit;
?>