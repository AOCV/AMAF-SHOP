<?php
session_start();
require '../config.php';

// Vérifier si l'utilisateur est connecté et est un livreur
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'livreur') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $commande_id = $_POST['commande_id'] ?? null;
    $new_status = $_POST['status'] ?? null;
    $livreur_id = $_SESSION['user_id'];

    if (!$commande_id || !$new_status) {
        echo json_encode(['success' => false, 'message' => 'Données manquantes']);
        exit();
    }

    // Vérifier que la commande appartient bien à ce livreur
    $query = "UPDATE commande 
              SET statut = ? 
              WHERE id = ? AND livreur_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $new_status, $commande_id, $livreur_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
} 