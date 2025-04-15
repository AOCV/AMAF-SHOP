<?php
session_start();
require '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $commande_id = intval($_POST['commande_id'] ?? 0);
    $statut = $_POST['statut'] ?? '';
    $livreur_id = $_SESSION['user_id'] ?? 0;
    
    if ($commande_id && in_array($statut, ['livré', 'non_livré'])) {
        $query = "UPDATE commande SET 
                  statut = ?, 
                  commentaire_livraison = ?,
                  date_livraison = CURRENT_TIMESTAMP
                  WHERE id = ? AND livreur_id = ?";
                  
        $stmt = $conn->prepare($query);
        $commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';
        $stmt->bind_param("ssii", $statut, $commentaire, $commande_id, $livreur_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erreur lors de la mise à jour']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Données invalides']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
} 