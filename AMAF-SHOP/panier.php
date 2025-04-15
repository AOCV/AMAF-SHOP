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

// Initialiser le tableau des produits
$produits = [];
$total = 0;

// Traiter la mise à jour des quantités et des tailles
if (isset($_POST['update_cart'])) {
    foreach ($_SESSION['panier'] as $id => $item) {
        if (isset($_POST['quantite'][$id])) {
            $_SESSION['panier'][$id]['quantite'] = max(1, min((int)$_POST['quantite'][$id], 99));
        }
        if (isset($_POST['taille'][$id])) {
            $_SESSION['panier'][$id]['taille'] = $_POST['taille'][$id];
        }
    }
    header('Location: panier.php');
    exit();
}

// Récupérer les informations des produits dans le panier
if (!empty($_SESSION['panier'])) {
    foreach ($_SESSION['panier'] as $id_produit => $item) {
        $query = "SELECT p.*, c.nom as categorie_nom 
                 FROM produit p 
                 LEFT JOIN categorie c ON p.categorie_id = c.id 
                 WHERE p.id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id_produit);
        $stmt->execute();
        $result = $stmt->get_result();
        $produit = $result->fetch_assoc();
        
        if ($produit) {
            // Calculer le prix avec la promotion
            $prix_final = $produit['prix'];
            if (!empty($produit['promotion'])) {
                $prix_final = $prix_final - $produit['promotion'];
            }
            
            $sous_total = $prix_final * $item['quantite'];
            $total += $sous_total;
            
            $produits[] = [
                'id' => $produit['id'],
                'nom' => $produit['nom'],
                'prix' => $produit['prix'],
                'prix_final' => $prix_final,
                'promotion' => $produit['promotion'],
                'quantite' => $item,
                'image_url' => $produit['image_url'],
                'image_url2' => $produit['image_url2'],
                'image_url3' => $produit['image_url3'],
                'sous_total' => $sous_total,
                'stock' => $produit['stock']
            ];
        }
    }
}

// Traitement de la suppression d'un produit spécifique
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product']) && isset($_POST['produit_id'])) {
    $produit_id = (int)$_POST['produit_id'];
    
    // Supprimer le produit du panier
    if (isset($_SESSION['panier'][$produit_id])) {
        unset($_SESSION['panier'][$produit_id]);
        $_SESSION['success'] = "Le produit a été retiré de votre panier avec succès";
    }
    
    header('Location: panier.php');
    exit();
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .cart-header {
            background: #007bff;
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.5s ease-out;
        }

        .cart-header::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: shine 2s infinite;
        }

        @keyframes shine {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .cart-item {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            animation: slideInUp 0.5s ease-out;
        }

        .cart-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .price-original {
            text-decoration: line-through;
            color: #6c757d;
            font-size: 0.9em;
        }

        .price-promotion {
            color: #dc3545;
            font-weight: bold;
            font-size: 1.1em;
        }

        .quantity-input {
            border: 2px solid #007bff;
            border-radius: 25px;
            padding: 0.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .quantity-input:focus {
            box-shadow: 0 0 0 3px rgba(0,123,255,0.25);
            border-color: #0056b3;
        }

        .btn-remove {
            border-radius: 50%;
            width: 40px;
            height: 40px;
            padding: 0;
            line-height: 40px;
            text-align: center;
            transition: all 0.3s ease;
            background: linear-gradient(45deg, #ff416c, #ff4b2b);
            color: white;
            border: none;
            box-shadow: 0 2px 10px rgba(255, 65, 108, 0.3);
        }

        .btn-remove:hover {
            transform: rotate(90deg) scale(1.1);
            box-shadow: 0 5px 15px rgba(255, 65, 108, 0.5);
        }

        .cart-total {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            margin-top: 2rem;
            animation: fadeInUp 0.5s ease-out;
        }

        .cart-actions {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
            justify-content: space-between;
        }

        .btn-action {
            padding: 0.8rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .empty-cart {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            animation: fadeIn 0.5s ease-out;
        }

        .empty-cart i {
            font-size: 4rem;
            color: #007bff;
            margin-bottom: 1.5rem;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-30px); }
            60% { transform: translateY(-15px); }
        }

        @keyframes slideInUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes fadeInUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-confirm {
            color: #636363;
        }

        .modal-confirm .modal-content {
            padding: 20px;
            border-radius: 15px;
            border: none;
        }

        .modal-confirm .modal-header {
            border-bottom: none;
            position: relative;
            background: #ff416c;
            border-radius: 10px 10px 0 0;
            padding: 20px;
        }

        .modal-confirm h4 {
            color: white;
            text-align: center;
            font-size: 24px;
            margin: 0;
        }

        .modal-confirm .close {
            position: absolute;
            top: 15px;
            right: 15px;
            color: white;
            text-shadow: none;
            opacity: 1;
        }

        .modal-confirm .modal-body {
            padding: 20px;
            text-align: center;
        }

        .modal-confirm .modal-body i {
            font-size: 4rem;
            color: #ff416c;
            margin-bottom: 20px;
            animation: shake 0.8s ease-in-out;
        }

        .modal-confirm .modal-footer {
            border: none;
            text-align: center;
            padding: 20px;
        }

        .modal-confirm .btn {
            min-width: 100px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .modal-confirm .btn-secondary {
            background: #6c757d;
            border: none;
        }

        .modal-confirm .btn-danger {
            background: linear-gradient(45deg, #ff416c, #ff4b2b);
            border: none;
        }

        .modal-confirm .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        @keyframes shake {
            0%, 100% { transform: rotate(0); }
            20%, 60% { transform: rotate(8deg); }
            40%, 80% { transform: rotate(-8deg); }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="cart-header">
        <div class="container">
            <h2 class="mb-0 animate__animated animate__fadeInDown">
                <i class="fas fa-shopping-cart me-2"></i>Mon Panier
            </h2>
        </div>
    </div>

    <div class="container">
        <?php if (empty($produits)): ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-basket"></i>
                <h3>Votre panier est vide</h3>
                <p class="text-muted mb-4">Découvrez nos produits et commencez vos achats !</p>
                <a href="index.php" class="btn btn-primary btn-action animate__animated animate__pulse animate__infinite">
                    <i class="fas fa-store me-2"></i>Explorer la boutique
                </a>
            </div>
        <?php else: ?>
            <form method="POST" action="panier.php">
                <input type="hidden" name="update_cart" value="1">
                
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Nom</th>
                            <th>Prix</th>
                            <th>Quantité</th>
                            <th>Taille</th>
                            <th>Total</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produits as $produit): ?>
                            <tr>
                                <td>
                                    <img src="<?= htmlspecialchars($produit['image_url']) ?>" alt="<?= htmlspecialchars($produit['nom']) ?>" class="img-thumbnail" style="max-width: 100px;">
                                </td>
                                <td><?= htmlspecialchars($produit['nom']) ?></td>
                                <td><?= number_format($produit['prix'], 2) ?> €</td>
                                <td>
                                    <input type="number" 
                                           name="quantite[<?= $produit['id'] ?>]" value="<?= '' . $produit['quantite']['quantite'] ?>" max="<?= $produit['stock'] ?>" 
                                           min="1" 
                                           class="form-control quantity-input" 
                                           onchange="this.form.submit()">
                                </td>
                                <td>
                                    <select name="taille[<?= $produit['id'] ?>]" 
                                            class="form-select" 
                                            onchange="this.form.submit()">
                                        <option value="XS" <?= $produit['quantite']['taille'] === 'XS' ? 'selected' : '' ?>>XS</option>
                                        <option value="S" <?= $produit['quantite']['taille'] === 'S' ? 'selected' : '' ?>>S</option>
                                        <option value="M" <?= $produit['quantite']['taille'] === 'M' ? 'selected' : '' ?>>M</option>
                                        <option value="L" <?= $produit['quantite']['taille'] === 'L' ? 'selected' : '' ?>>L</option>
                                        <option value="XL" <?= $produit['quantite']['taille'] === 'XL' ? 'selected' : '' ?>>XL</option>
                                        <option value="XXL" <?= $produit['quantite']['taille'] === 'XXL' ? 'selected' : '' ?>>XXL</option>
                                    </select>
                                </td>
                                <td><?= number_format($produit['sous_total'], 2) ?> €</td>
                                <td>
                                    <button type="button" 
                                            class="btn btn-remove"
                                            onclick="confirmerSuppression(<?= $produit['id'] ?>)">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="cart-total">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h4 class="mb-0">Total de la commande</h4>
                        </div>
                        <div class="col-md-6 text-end">
                            <h3 class="mb-0"><?= number_format($total, 2) ?> €</h3>
                        </div>
                    </div>
                </div>

                <div class="cart-actions">
                    <a href="index.php" class="btn btn-outline-primary btn-action">
                        <i class="fas fa-arrow-left me-2"></i>Continuer mes achats
                    </a>
                    <a href="commander.php" class="btn btn-primary btn-action">
                        <i class="fas fa-check me-2"></i>Passer la commande
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>



    <!-- Remplacer la modal existante par celle-ci -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-confirm">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Confirmation</h4>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <i class="fas fa-trash-alt"></i>
                <h5>Êtes-vous sûr ?</h5>
                <p class="text-muted">Voulez-vous vraiment retirer cet article de votre panier ?</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Annuler
                </button>
                <form method="POST" action="panier.php" class="d-inline" id="deleteForm">
                    <input type="hidden" name="delete_product" value="1">
                    <input type="hidden" name="produit_id" id="produit_id_to_delete">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-2"></i>Supprimer
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    

    <script>
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    let currentProductId = null;
    let currentCartItem = null;

    function confirmerSuppression(produitId) {
        // Récupérer l'élément bouton qui a déclenché l'événement
        const btn = event.currentTarget;
        
        // Trouver l'élément parent cart-item
        currentCartItem = btn.closest('.cart-item');
        currentProductId = produitId;
        
        // Animation du bouton
        btn.classList.add('animate__animated', 'animate__rubberBand');
        
        // Définir l'ID du produit à supprimer
        document.getElementById('produit_id_to_delete').value = produitId;
        
        // Afficher la modale
        deleteModal.show();
        
        // Retirer l'animation après qu'elle soit terminée
        setTimeout(() => {
            btn.classList.remove('animate__animated', 'animate__rubberBand');
        }, 1000);
    }

    // Animation lors de la suppression
    document.getElementById('deleteForm').addEventListener('submit', function(e) {
        // Empêcher la soumission standard du formulaire
        e.preventDefault();
        
        // Ajouter une animation de sortie à l'élément du panier
        if (currentCartItem) {
            currentCartItem.classList.add('animate__animated', 'animate__fadeOutRight');
            
            // Attendre que l'animation soit terminée avant de soumettre le formulaire
            setTimeout(() => {
                this.submit();
            }, 500);
        } else {
            // Si pour une raison quelconque on ne trouve pas l'élément, soumettre directement
            this.submit();
        }
    });

    // Mettre à jour dynamiquement le total quand la quantité change
    document.querySelectorAll('.quantity-input').forEach(input => {
        input.addEventListener('change', function() {
            // Soumettre automatiquement le formulaire quand la quantité change
            this.form.submit();
        });
    });
</script>
</body>
</html>