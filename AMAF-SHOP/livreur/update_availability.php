<?php
session_start();
require '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'livreur') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

if (!isset($_POST['disponible'])) {
    echo json_encode(['success' => false, 'message' => 'Paramètre manquant']);
    exit();
}

$disponible = (int)$_POST['disponible'];
$livreur_id = $_SESSION['user_id'];

$query = "UPDATE livreur SET disponible = ? WHERE utilisateur_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $disponible, $livreur_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erreur de mise à jour']);
} 