<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['commande_id'])) {
    header('Location: login.php');
    exit();
}

$commande_id = intval($_GET['commande_id']);
$user_id = $_SESSION['user_id'];

// Récupérer les détails de la commande
$query = "SELECT c.*, l.nom as livreur_nom, l.telephone as livreur_tel
          FROM commande c
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
    <title>Suivi de commande - AMAF-SHOP</title>
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
        
        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            animation: bounceIn 0.5s ease;
            display: inline-block;
            margin-top: 1rem;
        }
        
        @keyframes bounceIn {
            0% {
                transform: scale(0.3);
                opacity: 0;
            }
            50% {
                transform: scale(1.05);
            }
            70% {
                transform: scale(0.9);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <div class="tracking-container">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-4 animate__animated animate__fadeIn">
                    <i class="fas fa-truck me-2"></i>Suivi de la commande #<?= $commande_id ?>
                </h2>
                <span class="status-badge bg-<?= getStatusColor($commande['statut']) ?>">
                    <?= formatStatus($commande['statut']) ?>
                </span>
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
                
                <div class="tracking-step <?= $currentStatusValue >= 1 ? 'active' : '' ?> <?= $currentStatusValue > 1 ? 'completed' : '' ?>" id="step-en_attente">
                    <div class="step-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="step-label">En attente</div>
                </div>
                
                <div class="tracking-step <?= $currentStatusValue >= 2 ? 'active' : '' ?> <?= $currentStatusValue > 2 ? 'completed' : '' ?>" id="step-confirme">
                    <div class="step-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="step-label">Confirmé</div>
                </div>
                
                <div class="tracking-step <?= $currentStatusValue >= 3 ? 'active' : '' ?> <?= $currentStatusValue > 3 ? 'completed' : '' ?>" id="step-en_livraison">
                    <div class="step-icon">
                        <i class="fas fa-shipping-fast"></i>
                    </div>
                    <div class="step-label">En livraison</div>
                </div>
                
                <div class="tracking-step <?= $currentStatusValue >= 4 ? 'active' : '' ?>" id="step-livre">
                    <div class="step-icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="step-label">Livré</div>
                </div>
            </div>

            <div class="delivery-info">
                <h5 class="mb-4"><i class="fas fa-info-circle me-2"></i>Informations de livraison</h5>
                
                <div class="info-row">
                    <div class="info-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <strong>Livreur</strong><br>
                        <?= htmlspecialchars($commande['livreur_nom'] ?? 'Non assigné') ?>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div>
                        <strong>Téléphone</strong><br>
                        <?= htmlspecialchars($commande['livreur_tel'] ?? 'N/A') ?>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div>
                        <strong>Mode de paiement</strong><br>
                        <?= $commande['payment_method'] === 'livraison' ? 'Paiement à la livraison' : 'Paiement en ligne' ?>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <strong>Dernière mise à jour</strong><br>
                        <?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <a href="mes_commandes.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Retour aux commandes
                </a>
            </div>
        </div>
    </div>

    <script>
        function updateTrackingStatus(status) {
            // Normaliser le statut pour gérer les variations d'écriture
            status = status.toLowerCase().trim();
            
            const statusOrder = {
                'en attente': 1,
                'en_attente': 1,
                'confirmé': 2,
                'confirme': 2,
                'en livraison': 3,
                'en_livraison': 3,
                'livré': 4,
                'livre': 4
            };

            const currentStep = statusOrder[status] || 0;
            console.log("Statut détecté:", status, "Valeur:", currentStep);
            
            const steps = ['en_attente', 'confirme', 'en_livraison', 'livre'];
            
            steps.forEach((step, index) => {
                const element = document.getElementById(`step-${step}`);
                if (element) {
                    // Réinitialiser les classes
                    element.classList.remove('active', 'completed');
                    
                    // Ajouter la classe active si l'étape est atteinte
                    if (index + 1 <= currentStep) {
                        element.classList.add('active');
                        
                        // Ajouter la classe completed pour les étapes précédentes
                        if (index + 1 < currentStep) {
                            element.classList.add('completed');
                        }
                    }
                }
            });
        }

        // Initialiser le statut
        document.addEventListener('DOMContentLoaded', function() {
            updateTrackingStatus('<?= $commande['statut'] ?>');
        });
    </script>
</body>
</html> 