<?php
session_start();
require '../config.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté et est un livreur
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'livreur') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $commande_id = $_POST['commande_id'] ?? null;
    $new_status = $_POST['status'] ?? null;
    $livreur_id = $_SESSION['user_id'];
    $current_time = date('Y-m-d H:i:s');

    if (!$commande_id || !$new_status) {
        echo json_encode(['success' => false, 'message' => 'Données manquantes']);
        exit();
    }

    // Mettre à jour le statut et le livreur
    $update_query = "UPDATE commande 
                    SET statut = ?, 
                        livreur_id = ?,
                        date_livraison = CASE WHEN ? = 'livre' THEN ? ELSE date_livraison END
                    WHERE id = ? AND (livreur_id = ? OR livreur_id IS NULL)";
    
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("sisiii", $new_status, $livreur_id, $new_status, $current_time, $commande_id, $livreur_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Commande non modifiable']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour: ' . $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
} 