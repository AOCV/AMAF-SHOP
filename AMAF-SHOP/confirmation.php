<?php
session_start();
require 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Vérifier si l'ID de commande est fourni
if (!isset($_GET['commande'])) {
    header('Location: index.php');
    exit();
}

$commande_id = $_GET['commande'];

// Récupérer les détails de la commande
$query = "SELECT c.*, u.nom as client_nom, l.nom as livreur_nom, l.telephone as livreur_tel 
          FROM commande c 
          LEFT JOIN utilisateur u ON c.utilisateur_id = u.id 
          LEFT JOIN utilisateur l ON c.livreur_id = l.id 
          WHERE c.id = ? AND c.utilisateur_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $commande_id, $_SESSION['user_id']);
$stmt->execute();
$commande = $stmt->get_result()->fetch_assoc();

if (!$commande) {
    header('Location: index.php');
    exit();
}

// Récupérer les produits de la commande
$query_produits = "SELECT p.nom, cp.quantite, cp.prix_unitaire 
                  FROM commande_produit cp 
                  JOIN produit p ON cp.produit_id = p.id 
                  WHERE cp.commande_id = ?";
$stmt_produits = $conn->prepare($query_produits);
$stmt_produits->bind_param("i", $commande_id);
$stmt_produits->execute();
$produits = $stmt_produits->get_result()->fetch_all(MYSQLI_ASSOC);

// Décoder les informations de livraison
$infos_livraison = json_decode($commande['informations_livraison'], true);

// S'assurer que toutes les clés existent
$infos_livraison = array_merge([
    'nom' => '',
    'prenom' => '',
    'adresse' => '',
    'telephone' => '',
    'instructions' => ''
], $infos_livraison ?? []);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de commande - AMAF-SHOP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .confirmation-icon {
            font-size: 4rem;
            color: #198754;
            margin-bottom: 1rem;
        }
        .order-info {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .delivery-info {
            border-left: 4px solid #0d6efd;
            padding-left: 15px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-store me-2"></i>AMAF-SHOP
            </a>
        </div>
    </nav>

    <div class="container mt-5 mb-5">
        <div class="text-center mb-4">
            <i class="fas fa-check-circle confirmation-icon"></i>
            <h2>Commande confirmée !</h2>
            <p class="lead">Merci pour votre commande n°<?= $commande_id ?>.</p>
            <p>Un livreur vous contactera bientôt pour la livraison.</p>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="order-info mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Informations de livraison</h5>
                            <div class="delivery-info mt-3">
                                <p><strong>Nom :</strong> <?= htmlspecialchars($infos_livraison['nom']) ?></p>
                                <p><strong>Prénom :</strong> <?= htmlspecialchars($infos_livraison['prenom']) ?></p>
                                <p><strong>Adresse :</strong> <?= htmlspecialchars($infos_livraison['adresse']) ?></p>
                                <p><strong>Téléphone :</strong> <?= htmlspecialchars($infos_livraison['telephone']) ?></p>
                                <?php if (!empty($infos_livraison['instructions'])): ?>
                                    <p><strong>Instructions :</strong> <?= htmlspecialchars($infos_livraison['instructions']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h5>Informations du livreur</h5>
                            <div class="delivery-info mt-3">
                                <p><strong>Nom :</strong> <?= htmlspecialchars($commande['livreur_nom']) ?></p>
                                <p><strong>Téléphone :</strong> <?= htmlspecialchars($commande['livreur_tel']) ?></p>
                                <p><strong>Statut :</strong> 
                                    <span class="badge bg-warning">En attente de livraison</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Détails de la commande</h5>
                    </div>
                    <div class="card-body">
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
                                <?php foreach ($produits as $produit): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($produit['nom']) ?></td>
                                        <td><?= number_format($produit['prix_unitaire'], 0) ?> CFA</td>
                                        <td><?= $produit['quantite'] ?></td>
                                        <td><?= number_format($produit['prix_unitaire'] * $produit['quantite'], 0) ?> CFA</td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <td colspan="3" class="text-end">Frais de livraison :</td>
                                    <td>1500 CFA</td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Total :</strong></td>
                                    <td><strong><?= number_format($commande['total'] + 1500, 0) ?> CFA</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="text-center">
                    <a href="mes_commandes.php" class="btn btn-primary me-2">
                        <i class="fas fa-list me-2"></i>Voir mes commandes
                    </a>
                    <a href="index.php" class="btn btn-outline-primary">
                        <i class="fas fa-home me-2"></i>Retour à l'accueil
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 