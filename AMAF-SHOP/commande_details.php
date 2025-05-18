<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: login.php');
    exit();
}

$commande_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Récupérer les détails de la commande
$query = "SELECT c.*, u.nom as client_nom, l.nom as livreur_nom, l.telephone as livreur_tel
          FROM commande c
          LEFT JOIN utilisateur u ON c.utilisateur_id = u.id
          LEFT JOIN utilisateur l ON c.livreur_id = l.id
          WHERE c.id = ? AND c.utilisateur_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $commande_id, $user_id);
$stmt->execute();
$commande = $stmt->get_result()->fetch_assoc();

if (!$commande) {
    header('Location: mes_commandes.php');
    exit();
}

// Récupérer les produits de la commande
$query_produits = "SELECT p.*, cp.quantite, cp.prix_unitaire
                  FROM commande_produit cp
                  JOIN produit p ON cp.produit_id = p.id
                  WHERE cp.commande_id = ?";
$stmt = $conn->prepare($query_produits);
$stmt->bind_param("i", $commande_id);
$stmt->execute();
$produits = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Décoder les informations de livraison
$infos_livraison = json_decode($commande['informations_livraison'], true);

// Ajouter la fonction manquante getStatusColor
function getStatusColor($status) {
    // Normaliser le statut pour gérer les variations d'écriture
    $status = strtolower(trim($status));
    
    switch ($status) {
        case 'en attente':
        case 'en_attente':
            return 'warning';
        case 'confirmé':
        case 'confirme':
        case '':
            return 'info';
        case 'en livraison':
        case 'en_livraison':
            return 'primary';
        case 'livré':
        case 'livre':
            return 'success';
        case 'annulé':
        case 'annule':
            return 'danger';
        default:
            return 'secondary';
    }
}

// Ajoutez cette fonction pour normaliser l'affichage des statuts
function formatStatus($status) {
    $status = strtolower(trim($status));
    
    switch ($status) {
        case 'en attente':
        case 'en_attente':
            return 'En attente';
        case 'confirmé':
        case 'confirme':
        case '':
            return 'Confirmé';
        case 'en livraison':
        case 'en_livraison':
            return 'En livraison';
        case 'livré':
        case 'livre':
            return 'Livré';
        case 'annulé':
        case 'annule':
            return 'Annulé';
        default:
            return ucfirst($status);
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de la commande - AMAF-SHOP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        .order-details {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            overflow: hidden;
            animation: fadeIn 0.5s ease-out;
        }
        .order-header {
            background: #007bff;
            color: white;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        .order-header::after {
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
        .status-timeline {
            position: relative;
            padding: 20px 0;
        }
        .status-step {
            position: relative;
            padding-left: 30px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            animation: slideInLeft 0.5s ease-out;
        }
        .status-step:before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 20px;
            height: 20px;
            background: #e9ecef;
            border-radius: 50%;
            transform: translateY(-50%);
            transition: all 0.3s ease;
        }
        .status-step.active:before {
            background: #007bff;
            box-shadow: 0 0 10px rgba(0,123,255,0.5);
            animation: pulse 2s infinite;
        }
        .status-step.active {
            color: #007bff;
            font-weight: bold;
        }
        .product-card {
            border: none;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            animation: fadeInUp 0.5s ease-out;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .info-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            animation: fadeInRight 0.5s ease-out;
        }
        .info-card h5 {
            color: #007bff;
            margin-bottom: 15px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .total-section {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            animation: fadeInUp 0.5s ease-out;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        @keyframes pulse {
            0% { transform: translateY(-50%) scale(1); }
            50% { transform: translateY(-50%) scale(1.2); }
            100% { transform: translateY(-50%) scale(1); }
        }
        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .product-image:hover {
            transform: scale(1.1);
        }
        .back-btn {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .back-btn:hover {
            transform: translateX(-5px);
        }
        .back-btn i {
            margin-right: 8px;
            transition: all 0.3s ease;
        }
        .back-btn:hover i {
            transform: translateX(-5px);
        }

        /* Nouveaux styles pour le statut de la commande */
        .status-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        .tracking-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: 40px 0;
        }

        .tracking-steps::before {
            content: '';
            position: absolute;
            top: 30px;
            left: 0;
            right: 0;
            height: 4px;
            background: #e9ecef;
            z-index: 1;
        }

        .tracking-step {
            position: relative;
            z-index: 2;
            text-align: center;
            width: 80px;
        }

        .step-icon {
            width: 60px;
            height: 60px;
            background: white;
            border: 4px solid #e9ecef;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            transition: all 0.3s ease;
            position: relative;
        }

        .step-icon i {
            font-size: 24px;
            color: #adb5bd;
            transition: all 0.3s ease;
        }

        .step-label {
            font-size: 12px;
            color: #6c757d;
            margin-top: 8px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .tracking-step.active .step-icon {
            border-color: #007bff;
            background: #007bff;
            transform: scale(1.1);
            box-shadow: 0 0 15px rgba(0,123,255,0.5);
        }

        .tracking-step.active .step-icon i {
            color: white;
        }

        .tracking-step.active .step-label {
            color: #007bff;
            font-weight: bold;
        }

        .tracking-step.completed .step-icon {
            border-color: #28a745;
            background: #28a745;
        }

        .tracking-step.completed .step-icon::after {
            content: '';
            position: absolute;
            right: -100%;
            top: 50%;
            height: 4px;
            width: 100%;
            background: #28a745;
            transform: translateY(-50%);
            z-index: -1;
        }

        .tracking-step.completed .step-icon i {
            color: white;
        }

        .tracking-step.completed .step-label {
            color: #28a745;
        }

        .tracking-step.active .step-icon {
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <a href="mes_commandes.php" class="btn btn-outline-primary back-btn mb-4 animate__animated animate__fadeIn">
            <i class="fas fa-arrow-left"></i>Retour aux commandes
        </a>

        <div class="order-details animate__animated animate__fadeIn">
            <div class="order-header">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h3 class="mb-0">Commande #<?= $commande_id ?></h3>
                        <p class="mb-0">
                            <i class="far fa-calendar-alt me-2"></i>
                            <?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?>
                        </p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <span class="badge bg-<?= getStatusColor($commande['statut']) ?> fs-5">
                            <?= formatStatus($commande['statut']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="container py-4">
                <div class="row">
                    <div class="col-md-4">
                        <div class="info-card">
                            <h5><i class="fas fa-shipping-fast me-2"></i>Informations de livraison</h5>
                            <p><strong><i class="fas fa-user me-2"></i>Nom :</strong> <?= htmlspecialchars($infos_livraison['nom']) ?></p>
                            <p><strong><i class="fas fa-map-marker-alt me-2"></i>Adresse :</strong> <?= htmlspecialchars($infos_livraison['adresse']) ?></p>
                            <p><strong><i class="fas fa-phone me-2"></i>Téléphone :</strong> <?= htmlspecialchars($infos_livraison['telephone']) ?></p>
                            <?php if (!empty($infos_livraison['instructions'])): ?>
                                <p><strong><i class="fas fa-info-circle me-2"></i>Instructions :</strong> <?= htmlspecialchars($infos_livraison['instructions']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <h5>Livreur</h5>
                        <div class="card">
                            <div class="card-body">
                                <p><strong>Nom :</strong> <?= htmlspecialchars($commande['livreur_nom']) ?></p>
                                <p><strong>Téléphone :</strong> <?= htmlspecialchars($commande['livreur_tel']) ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="status-container">
                            <h5 class="text-center mb-4">
                                <i class="fas fa-tasks me-2"></i>Statut de la commande
                            </h5>
                            <div class="tracking-steps">
                                <?php
                                // Définir l'ordre des statuts et normaliser les clés
                                $statusOrder = [
                                    'en attente' => 1,
                                    'en_attente' => 1,
                                    'confirmé' => 2,
                                    'confirme' => 2,
                                    'en livraison' => 3,
                                    'en_livraison' => 3,
                                    'livré' => 4,
                                    'livre' => 4
                                ];

                                // Obtenir la valeur numérique du statut actuel
                                $currentStatusValue = $statusOrder[strtolower(trim($commande['statut']))] ?? 0;
                                ?>
                                
                                <div class="tracking-step <?= $currentStatusValue >= 1 ? 'active' : '' ?> <?= $currentStatusValue > 1 ? 'completed' : '' ?>">
                                    <div class="step-icon">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="step-label">En attente</div>
                                </div>
                                
                                <div class="tracking-step <?= $currentStatusValue >= 2 ? 'active' : '' ?> <?= $currentStatusValue > 2 ? 'completed' : '' ?>">
                                    <div class="step-icon">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div class="step-label">Confirmé</div>
                                </div>
                                
                                <div class="tracking-step <?= $currentStatusValue >= 3 ? 'active' : '' ?> <?= $currentStatusValue > 3 ? 'completed' : '' ?>">
                                    <div class="step-icon">
                                        <i class="fas fa-shipping-fast"></i>
                                    </div>
                                    <div class="step-label">En livraison</div>
                                </div>
                                
                                <div class="tracking-step <?= $currentStatusValue >= 4 ? 'active' : '' ?>">
                                    <div class="step-icon">
                                        <i class="fas fa-home"></i>
                                    </div>
                                    <div class="step-label">Livré</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <h5 class="mt-4 mb-3"><i class="fas fa-box-open me-2"></i>Produits commandés</h5>
                <div class="row">
                    <?php foreach ($produits as $produit): ?>
                        <div class="col-md-6 mb-3">
                            <div class="product-card">
                                <div class="d-flex align-items-center">
                                    <?php if (!empty($produit['image_url'])): ?>
                                        <img src="<?= htmlspecialchars($produit['image_url']) ?>" 
                                             alt="<?= htmlspecialchars($produit['nom']) ?>"
                                             class="product-image me-3">
                                    <?php endif; ?>
                                    <div class="flex-grow-1">
    <h6 class="mb-1 fw-bold"><?= htmlspecialchars($produit['nom']) ?></h6>
    <p class="mb-1 icon-label"><i class="fas fa-cubes me-2"></i>Quantité : <?= $produit['quantite'] ?></p>
    <p class="mb-1 icon-label"><i class="fas fa-ruler-combined me-2"></i>Taille : <?= $produit['taille'] ?></p>
    <p class="mb-0 icon-label"><i class="fas fa-tag me-2"></i>Prix : <?= number_format($produit['prix_unitaire'], 0) ?> CFA</p>
</div>

                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>
 
                <div class="total-section">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Total de la commande</h5>
                        <h4 class="mb-0"><?= number_format($commande['total'], 0) ?> CFA</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 