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

// Traitement de la mise à jour des quantités
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    foreach ($_POST['quantite'] as $id_produit => $nouvelle_quantite) {
        $nouvelle_quantite = (int)$nouvelle_quantite;
        
        // Vérifier que la quantité est positive
        if ($nouvelle_quantite <= 0) {
            // Supprimer le produit du panier
            unset($_SESSION['panier'][$id_produit]);
            continue;
        }
        
        // Vérifier le stock disponible
        $query = "SELECT stock FROM produit WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id_produit);
        $stmt->execute();
        $result = $stmt->get_result();
        $produit = $result->fetch_assoc();
        
        if ($produit && $nouvelle_quantite <= $produit['stock']) {
            $_SESSION['panier'][$id_produit] = $nouvelle_quantite;
        } else {
            $_SESSION['error'] = "Stock insuffisant pour un ou plusieurs produits";
        }
    }
    
    $_SESSION['success'] = "Panier mis à jour avec succès";
    header('Location: panier.php');
    exit();
}

// Récupérer les informations des produits dans le panier
$produits = [];
$total = 0;

if (!empty($_SESSION['panier'])) {
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
            
            $produits[] = [
                'id' => $produit['id'],
                'nom' => $produit['nom'],
                'prix' => $produit['prix'],
                'prix_final' => $prix_final,
                'promotion' => $produit['promotion'],
                'quantite' => $quantite,
                'sous_total' => $sous_total,
                'stock' => $produit['stock']
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Panier - AMAF-SHOP</title>
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
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <h2 class="mb-4">
            <i class="fas fa-shopping-cart me-2"></i>Mon Panier
        </h2>

        <?php if (empty($produits)): ?>
            <div class="alert alert-info">
                Votre panier est vide. <a href="index.php">Continuer vos achats</a>
            </div>
        <?php else: ?>
            <form method="POST" action="panier.php">
                <input type="hidden" name="update_cart" value="1">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Prix unitaire</th>
                                <th>Quantité</th>
                                <th>Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($produits as $produit): ?>
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
                                    <td>
                                        <input type="number" name="quantite[<?= $produit['id'] ?>]" 
                                               value="<?= $produit['quantite'] ?>" 
                                               max="<?= $produit['stock'] ?>" 
                                               min="1" 
                                               class="form-control quantity-input" 
                                               onchange="this.form.submit()"
                                               style="width: 80px;">
                                    </td>
                                    <td><?= number_format($produit['sous_total'], 2) ?> €</td>
                                    <td>
                                        <button type="submit" name="quantite[<?= $produit['id'] ?>]" 
                                                value="0" 
                                                class="btn btn-danger btn-sm"
                                                onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Total :</strong></td>
                                <td colspan="2"><strong><?= number_format($total, 2) ?> €</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Continuer mes achats
                    </a>
                    <a href="commander.php" class="btn btn-primary">
                        <i class="fas fa-check me-2"></i>Commander
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>