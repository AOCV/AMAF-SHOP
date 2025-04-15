<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'config.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Vous devez être connecté pour passer une commande.";
    header('Location: login.php');
    exit();
}

// Vérifier si le panier n'est pas vide
if (empty($_SESSION['panier'])) {
    $_SESSION['error'] = "Votre panier est vide";
    header('Location: panier.php');
    exit();
}

$error = '';
$total = 0;
$produits_commande = [];

// Récupérer les produits du panier
foreach ($_SESSION['panier'] as $id_produit => $quantite) {
    $query = "SELECT * FROM produit WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id_produit);
    $stmt->execute();
    $result = $stmt->get_result();
    $produit = $result->fetch_assoc();
    
    if ($produit) {
        // Calculer le prix avec la promotion
        $prix_final = $produit['prix'];
        if (!empty($produit['promotion'])) {
            $prix_final = $prix_final * (1 - $produit['promotion'] / 100);
        }
        
        $sous_total = $prix_final * $quantite;
        $total += $sous_total;
        
        $produit['quantite'] = $quantite;
        $produit['prix_final'] = $prix_final;
        $produit['sous_total'] = $sous_total;
        $produits_commande[] = $produit;
    }
}

// Traitement de la commande
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($produits_commande)) {
            throw new Exception("Votre panier est vide.");
        }

        $conn->begin_transaction();
        
        try {
            // Créer la commande
            $user_id = $_SESSION['user_id'];
            
            $sql = "INSERT INTO commande (user_id, total, date_commande, statut) VALUES (?, ?, NOW(), 'en_attente')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("id", $user_id, $total);
            $stmt->execute();
            $commande_id = $conn->insert_id;
            
            // Ajouter les détails de la commande
            foreach ($produits_commande as $produit) {
                // Vérifier le stock une dernière fois
                $sql = "SELECT stock FROM produit WHERE id = ? FOR UPDATE";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $produit['id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $stock_data = $result->fetch_assoc();
                
                if (!$stock_data) {
                    throw new Exception("Le produit n'existe plus : " . $produit['nom']);
                }
                
                if ($stock_data['stock'] < $produit['quantite']) {
                    throw new Exception("Stock insuffisant pour le produit : " . $produit['nom']);
                }
                
                // Insérer le détail de la commande
                $sql = "INSERT INTO commande_detail (commande_id, produit_id, quantite, prix_unitaire) 
                        VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iiid", $commande_id, $produit['id'], $produit['quantite'], $produit['prix_final']);
                $stmt->execute();
                
                // Mettre à jour le stock
                $sql = "UPDATE produit SET stock = stock - ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $produit['quantite'], $produit['id']);
                $stmt->execute();
            }
            
            // Valider la transaction
            $conn->commit();
            
            // Vider le panier
            $_SESSION['panier'] = [];
            $_SESSION['success'] = "Votre commande a été enregistrée avec succès !";
            header('Location: mes_commandes.php');
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finaliser la commande - AMAF-SHOP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .price-original {
            text-decoration: line-through;
            color: #6c757d;
            font-size: 0.9em;
        }
        .price-promotion {
            color: #dc3545;
            font-weight: bold;
        }
        .order-summary {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <h2 class="mb-4">
            <i class="fas fa-shopping-bag me-2"></i>Finaliser la commande
        </h2>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title mb-0">Récapitulatif de votre commande</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Produit</th>
                                        <th>Prix unitaire</th>
                                        <th>Quantité</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($produits_commande as $produit): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($produit['nom']) ?></td>
                                            <td>
                                                <?php if (!empty($produit['promotion'])): ?>
                                                    <span class="price-original"><?= number_format($produit['prix'], 2) ?> €</span><br>
                                                    <span class="price-promotion"><?= number_format($produit['prix_final'], 2) ?> €</span>
                                                <?php else: ?>
                                                    <?= number_format($produit['prix_final'], 2) ?> €
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $produit['quantite'] ?></td>
                                            <td><?= number_format($produit['sous_total'], 2) ?> €</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Total :</strong></td>
                                        <td><strong><?= number_format($total, 2) ?> €</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="order-summary">
                    <h4>Informations de livraison</h4>
                    <p>
                        <strong>Nom :</strong> <?= htmlspecialchars($_SESSION['user_name'] ?? '') ?><br>
                        <strong>Email :</strong> <?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>
                    </p>

                    <form method="POST" class="mt-4">
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-check me-2"></i>Confirmer la commande
                        </button>
                        <a href="panier.php" class="btn btn-outline-secondary btn-lg w-100 mt-2">
                            <i class="fas fa-arrow-left me-2"></i>Retour au panier
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>