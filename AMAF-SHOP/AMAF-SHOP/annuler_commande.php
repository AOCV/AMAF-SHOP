<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur non authentifié']);
    exit();
}

if (!isset($_POST['commande_id']) || !is_numeric($_POST['commande_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de commande invalide']);
    exit();
}

$commande_id = intval($_POST['commande_id']);
$user_id = $_SESSION['user_id'];

try {
    // Vérifier si la commande appartient bien à l'utilisateur et peut être annulée
    $query = "SELECT id, statut FROM commande WHERE id = ? AND utilisateur_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $commande_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Commande introuvable ou non autorisée']);
        exit();
    }

    $commande = $result->fetch_assoc();

    if ($commande['statut'] !== 'en attente') {
        echo json_encode(['success' => false, 'message' => 'Impossible d\'annuler cette commande']);
        exit();
    }

    // Mettre à jour le statut de la commande
    $update_query = "UPDATE commande SET statut = 'annulé' WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("i", $commande_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Commande annulée avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'annulation']);
    }

} catch (Exception $e) {
    error_log("Erreur lors de l'annulation : " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur interne']);
}

$conn->close();
?>
 