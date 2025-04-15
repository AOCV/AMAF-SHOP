<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'config.php';
require_once 'includes/functions.php';

// Initialiser le panier
if (!isset($_SESSION['panier'])) {
    $_SESSION['panier'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['id'])) {
    $id_produit = isset($_POST['id']) ? (int)$_POST['id'] : (int)$_GET['id'];
    $quantite = isset($_POST['quantite']) ? (int)$_POST['quantite'] : 1;
    $taille = isset($_POST['taille']) ? htmlspecialchars($_POST['taille']) : 'M'; // taille par défaut

    // Quantité minimum
    if ($quantite <= 0) {
        $_SESSION['error'] = "La quantité doit être supérieure à 0";
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }

    // Vérifier que le produit existe
    $query = "SELECT * FROM produit WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id_produit);
    $stmt->execute();
    $result = $stmt->get_result();
    $produit = $result->fetch_assoc();

    if ($produit) {
        $stock = $produit['stock'];

        // Si le produit est déjà dans le panier
        if (isset($_SESSION['panier'][$id_produit])) {
            $ancienne_qte = $_SESSION['panier'][$id_produit]['quantite'];
            $nouvelle_qte = $ancienne_qte + $quantite;

            if ($nouvelle_qte <= $stock) {
                $_SESSION['panier'][$id_produit]['quantite'] = $nouvelle_qte;
                $_SESSION['panier'][$id_produit]['taille'] = $taille; // met à jour la taille aussi
                $_SESSION['success'] = "Le produit a été ajouté au panier.";
            } else {
                $_SESSION['error'] = "Stock insuffisant. Il reste $stock article(s).";
            }
        } else {
            // Nouveau produit dans le panier
            if ($quantite <= $stock) {
                $_SESSION['panier'][$id_produit] = [
                    'quantite' => $quantite,
                    'taille' => $taille
                ];
                $_SESSION['success'] = "Produit ajouté au panier.";
            } else {
                $_SESSION['error'] = "Stock insuffisant. Il reste $stock article(s).";
            }
        }
    } else {
        $_SESSION['error'] = "Produit introuvable.";
    }

    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}

header('Location: index.php');
exit();
?>
