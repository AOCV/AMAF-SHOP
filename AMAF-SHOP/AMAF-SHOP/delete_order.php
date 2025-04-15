<?php
session_start();
require 'config.php';

// Vérifier si l'utilisateur est administrateur
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(["success" => false, "message" => "Accès non autorisé."]);
    exit();
}

// Vérifier si l'ID de la commande est fourni
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $commande_id = intval($_POST['order_id']);
    
    // Vérifier si la commande existe
    $stmt = $conn->prepare("SELECT id FROM commande WHERE id = ?");
    $stmt->execute([$commande_id]);
    
    if ($stmt->$conn() === 0) {
        echo json_encode(["success" => false, "message" => "Commande introuvable."]);
        exit();
    }
    
    // Supprimer la commande
    $stmt = $conn->prepare("DELETE FROM commande WHERE id = ?");
    if ($stmt->execute([$commande_id])) {
        echo json_encode(["success" => true, "message" => "Commande supprimée avec succès."]);
    } else {
        echo json_encode(["success" => false, "message" => "Erreur lors de la suppression."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Requête invalide."]);
}
