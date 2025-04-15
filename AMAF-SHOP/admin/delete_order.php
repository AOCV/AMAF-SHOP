<?php
session_start();
require '../config.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté et est un admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id']) && is_numeric($_POST['order_id'])) {
    $order_id = intval($_POST['order_id']);

    try {
        // Vérifier si la commande existe
        $stmt = $conn->prepare("SELECT id FROM commande WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Commande introuvable']);
            exit();
        }

        // Supprimer la commande
        $stmt = $conn->prepare("DELETE FROM commande WHERE id = ?");
        $stmt->bind_param("i", $order_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Commande supprimée avec succès']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression']);
        }
    } catch (Exception $e) {
        error_log("Erreur suppression commande: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erreur interne']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Requête invalide']);
}

$conn->close();
?>
