<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'config.php';
require_once 'includes/functions.php';

// Initialiser le panier s'il n'existe pas
if (!isset($_SESSION['panier'])) {
    $_SESSION['panier'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['id'])) {
    $id_produit = isset($_POST['id']) ? $_POST['id'] : $_GET['id'];
    $quantite = isset($_POST['quantite']) ? (int)$_POST['quantite'] : 1;
    
    // Vérifier que la quantité est positive
    if ($quantite <= 0) {
        $_SESSION['error'] = "La quantité doit être supérieure à 0";
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }
    
    // Vérifier si le produit existe et s'il y a assez de stock
    $query = "SELECT * FROM produit WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id_produit);
    $stmt->execute();
    $result = $stmt->get_result();
    $produit = $result->fetch_assoc();
    
    if ($produit) {
        // Calculer la quantité totale (panier + nouvelle quantité)
        $quantite_panier = isset($_SESSION['panier'][$id_produit]) ? $_SESSION['panier'][$id_produit] : 0;
        $nouvelle_quantite = $quantite_panier + $quantite;
        
        // Vérifier le stock
        if ($nouvelle_quantite <= $produit['stock']) {
            // Mettre à jour la quantité dans le panier
            $_SESSION['panier'][$id_produit] = $nouvelle_quantite;
            
            $_SESSION['success'] = "Le produit a été ajouté au panier";
        } else {
            $_SESSION['error'] = "Stock insuffisant. Il reste " . $produit['stock'] . " article(s) disponible(s).";
        }
    } else {
        $_SESSION['error'] = "Ce produit n'existe pas";
    }
    
    // Rediriger vers la page précédente
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}

// Si accès direct à la page sans POST ou GET, rediriger vers la page d'accueil
header('Location: index.php');
exit();
?>