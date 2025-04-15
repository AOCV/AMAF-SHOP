<?php
session_start();
require '../config.php';

// Vérifier si l'utilisateur est connecté et est un livreur
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'livreur') {
    header('Location: ../login.php');
    exit();
}

if (!isset($_GET['commande_id'])) {
    header('Location: dashboard.php');
    exit();
}

$commande_id = intval($_GET['commande_id']);
$livreur_id = $_SESSION['user_id'];

// Récupérer les détails de la commande
$query = "SELECT c.*, 
          u.nom as client_nom, u.telephone as client_tel
          FROM commande c
          LEFT JOIN utilisateur u ON c.utilisateur_id = u.id
          WHERE c.id = ? AND (c.livreur_id = ? OR c.livreur_id IS NULL)";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $commande_id, $livreur_id);
$stmt->execute();
$commande = $stmt->get_result()->fetch_assoc();

if (!$commande) {
    header('Location: dashboard.php');
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

// Fonction pour obtenir la couleur du statut
function getStatusColor($status) {
    // Normaliser le statut pour gérer les variations d'écriture
    $status = strtolower(trim($status));
    
    switch ($status) {
        case 'en attente':
        case 'en_attente':
            return 'warning';
        case 'confirmé':
        case 'confirme':
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

// Fonction pour normaliser l'affichage des statuts
function formatStatus($status) {
    $status = strtolower(trim($status));
    
    switch ($status) {
        case 'en attente':
        case 'en_attente':
            return 'En attente';
        case 'confirmé':
        case 'confirme':
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
    <title>Suivi de commande #<?= $commande_id ?> - AMAF-SHOP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary-color: #007bff;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
        }
        
        body {
            background-color: #f8f9fa;
        }

        .tracking-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-top: 2rem;
            animation: fadeIn 0.5s ease-out;
        }

        .tracking-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: 2rem 0;
        }

        .tracking-steps::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 4px;
            background: #e9ecef;
            transform: translateY(-50%);
            z-index: 1;
        }

        .tracking-step {
            position: relative;
            z-index: 2;
            text-align: center;
            width: 120px;
            transition: all 0.3s ease;
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
        }

        .step-icon i {
            font-size: 24px;
            color: #adb5bd;
            transition: all 0.3s ease;
        }

        .step-label {
            font-size: 14px;
            color: #6c757d;
            margin-top: 8px;
            font-weight: 500;
        }

        .tracking-step.active .step-icon {
            border-color: var(--primary-color);
            background: var(--primary-color);
            transform: scale(1.1);
            box-shadow: 0 0 15px rgba(0,123,255,0.5);
            animation: pulse 2s infinite;
        }

        .tracking-step.active .step-icon i {
            color: white;
        }

        .tracking-step.active .step-label {
            color: var(--primary-color);
            font-weight: bold;
        }

        .tracking-step.completed .step-icon {
            border-color: var(--success-color);
            background: var(--success-color);
        }
        
        .tracking-step.completed .step-icon::after {
            content: '';
            position: absolute;
            right: -100%;
            top: 50%;
            height: 4px;
            width: 100%;
            background: var(--success-color);
            transform: translateY(-50%);
            z-index: -1;
        }

        .tracking-step.completed .step-icon i {
            color: white;
        }
        
        .tracking-step.completed .step-label {
            color: var(--success-color);
        }

        .delivery-info {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
            margin-top: 2rem;
            animation: slideInUp 0.5s ease-out;
        }

        .info-row {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .info-row:hover {
            transform: translateX(10px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .info-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }

        .info-icon i {
            color: white;
            font-size: 18px;
        }
        
        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            animation: fadeIn 0.5s ease;
        }
        
        .product-list {
            margin-top: 2rem;
        }
        
        .product-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: white;
            border-radius: 10px;
            margin-bottom: 1rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .product-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 1rem;
        }
        
        .product-details {
            flex-grow: 1;
        }
        
        .product-quantity {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 5px 10px;
            font-weight: 600;
            color: #495057;
        }
        
        .action-buttons {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
        }
        
        .action-btn {
            padding: 0.8rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            flex-grow: 1;
            text-align: center;
        }
        
        .update-btn {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            border: none;
        }
        
        .update-btn:hover {
            background: linear-gradient(45deg, #20c997, #28a745);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        .back-btn {
            background: #f8f9fa;
            color: #495057;
            border: none;
        }
        
        .back-btn:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }
        
        .customer-container {
            background: linear-gradient(45deg, #2980b9, #3498db);
            border-radius: 15px;
            padding: 1.5rem;
            color: white;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .customer-container::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            animation: shine 2s infinite;
        }
        
        @keyframes shine {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="tracking-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="animate__animated animate__fadeIn">
                    <i class="fas fa-truck me-2"></i>Suivi de la commande #<?= $commande_id ?>
                </h2>
                <span class="status-badge bg-<?= getStatusColor($commande['statut']) ?>">
                    <?= formatStatus($commande['statut']) ?>
                </span>
            </div>
            
            <div class="customer-container">
                <div class="row">
                    <div class="col-md-6">
                        <h5><i class="fas fa-user me-2"></i>Client</h5>
                        <p class="mb-1"><strong>Nom:</strong> <?= htmlspecialchars($infos_livraison['nom'] ?? '') ?> <?= htmlspecialchars($infos_livraison['prenom'] ?? '') ?></p>
                        <p class="mb-1"><strong>Téléphone:</strong> <?= htmlspecialchars($infos_livraison['telephone'] ?? 'N/A') ?></p>
                    </div>
                    <div class="col-md-6">
                        <h5><i class="fas fa-map-marker-alt me-2"></i>Adresse de livraison</h5>
                        <p><?= htmlspecialchars($infos_livraison['adresse'] ?? 'Adresse non disponible') ?></p>
                        <?php if (!empty($infos_livraison['instructions'])): ?>
                            <p class="mb-0"><strong>Instructions:</strong> <?= htmlspecialchars($infos_livraison['instructions']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="tracking-steps">
                <?php
                // Définir l'ordre des statuts avec toutes les variantes
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
                $normalizedStatus = strtolower(trim($commande['statut']));
                $currentStatusValue = $statusOrder[$normalizedStatus] ?? 0;
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

            <!-- Produits commandés -->
            <div class="product-list">
                <h5><i class="fas fa-box-open me-2"></i>Produits commandés</h5>
                
                <?php if (count($produits) > 0): ?>
                    <?php foreach ($produits as $produit): ?>
                        <div class="product-item animate__animated animate__fadeIn">
                            <?php if (!empty($produit['image_url'])): ?>
                                <img src="../<?= htmlspecialchars($produit['image_url']) ?>" 
                                     alt="<?= htmlspecialchars($produit['nom']) ?>"
                                     class="product-image">
                            <?php else: ?>
                                <div class="product-image-placeholder">
                                    <i class="fas fa-box"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="product-details">
                                <h6><?= htmlspecialchars($produit['nom']) ?></h6>
                                <p class="mb-0">Prix: <?= number_format($produit['prix_unitaire'], 0) ?> CFA</p>
                            </div>
                            
                            <span class="product-quantity">
                                <i class="fas fa-times"></i> <?= $produit['quantite'] ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Aucun détail de produit disponible.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Boutons d'action -->
            <div class="action-buttons">
                <a href="dashboard.php" class="action-btn back-btn">
                    <i class="fas fa-arrow-left me-2"></i>Retour
                </a>
                
                <?php 
                    $statut = strtolower(trim($commande['statut']));
                    if ($statut === 'en attente' || $statut === 'en_attente'): 
                ?>
                    <button class="action-btn update-btn" onclick="updateStatus(<?= $commande['id'] ?>, 'en_livraison')">
                        <i class="fas fa-truck me-2"></i>Démarrer la livraison
                    </button>
                <?php elseif ($statut === 'en livraison' || $statut === 'en_livraison'): ?>
                    <button class="action-btn update-btn" onclick="updateStatus(<?= $commande['id'] ?>, 'livre')">
                        <i class="fas fa-check me-2"></i>Marquer comme livrée
                    </button>
                <?php endif; ?>
                
                <?php if (!empty($infos_livraison['adresse'])): ?>
                    <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($infos_livraison['adresse']) ?>" 
                       target="_blank" class="action-btn" style="background: #4285F4; color: white;">
                        <i class="fas fa-map-marker-alt me-2"></i>Voir sur la carte
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    function updateStatus(commandeId, newStatus) {
        Swal.fire({
            title: 'Confirmation',
            text: `Voulez-vous ${newStatus === 'en_livraison' ? 'démarrer' : 'terminer'} cette livraison ?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#dc3545',
            confirmButtonText: 'Oui, confirmer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('update_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `commande_id=${commandeId}&status=${newStatus}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: 'Succès!',
                            text: 'Statut mis à jour avec succès',
                            icon: 'success',
                            timer: 1500
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Erreur!',
                            text: data.message || 'Une erreur est survenue',
                            icon: 'error'
                        });
                    }
                })
                .catch(error => {
                    console.error("Erreur:", error);
                    Swal.fire({
                        title: 'Erreur!',
                        text: 'Une erreur technique est survenue',
                        icon: 'error'
                    });
                });
            }
        });
    }
    
    // Mettre à jour les statuts du tracking
    document.addEventListener('DOMContentLoaded', function() {
        // Les statuts sont déjà mis à jour par le PHP, cette fonction n'est pas nécessaire ici
        console.log('Page de suivi livreur chargée - Statut actuel: <?= $commande['statut'] ?>');
    });
    </script>
</body>
</html>