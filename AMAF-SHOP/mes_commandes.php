<?php
session_start();
require 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Récupérer les commandes de l'utilisateur
$user_id = $_SESSION['user_id'];
$query = "SELECT c.*, 
          COUNT(cp.produit_id) as nb_produits,
          l.nom as livreur_nom
          FROM commande c 
          LEFT JOIN commande_produit cp ON c.id = cp.commande_id
          LEFT JOIN utilisateur l ON c.livreur_id = l.id
          WHERE c.utilisateur_id = ?
          GROUP BY c.id
          ORDER BY c.date_commande DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$commandes = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Commandes - AMAF-SHOP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background: #007bff !important;
        }
        .order-card {
            max-width: 400px;
            margin: 0 auto 20px;
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(0);
            transition: all 0.3s ease;
            background: white;
            animation: fadeInUp 0.5s ease-out;
        }
        .order-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 123, 255, 0.2);
        }
        .order-header {
            background: #007bff;
            color: white;
            padding: 15px;
            position: relative;
            overflow: hidden;
            animation: slideInRight 0.5s ease-out;
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
        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            animation: bounceIn 0.5s ease;
        }
        .info-row {
            padding: 10px 15px;
            border-bottom: 1px dashed rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            animation: fadeIn 0.5s ease-out;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-row i {
            color: #007bff;
            width: 20px;
            text-align: center;
            animation: rotateIn 0.5s ease;
        }
        .btn-action {
            border-radius: 25px;
            padding: 8px 20px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            animation: pulse 2s infinite;
        }
        .btn-details {
            background: #007bff;
            color: white;
            border: none;
            animation: pulse 2s infinite;
        }
        .btn-details:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3);
        }
        .btn-cancel {
            background: linear-gradient(135deg, #c0392b, #e74c3c);
            color: white;
            border: none;
        }
        .btn-cancel:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
            color: white;
        }
        .page-header {
            background: #007bff;
            color: white;
            padding: 40px 0;
            margin-bottom: 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
            animation: fadeInDown 0.5s ease-out;
        }
        .page-header::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: shine 3s infinite;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            animation: fadeInUp 1s ease;
        }
        .empty-state i {
            font-size: 5rem;
            color: #1a2a6c;
            margin-bottom: 20px;
            animation: bounce 2s infinite;
        }
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-30px); }
            60% { transform: translateY(-15px); }
        }
        .modal-content {
            border-radius: 20px;
            border: none;
        }
        .modal-header {
            background: #007bff;
            color: white;
            border-radius: 20px 20px 0 0;
        }
        .modal-body i {
            color: #e74c3c;
            animation: pulse 1.5s infinite;
        }
        .btn-tracking {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            margin-left: 10px;
            animation: pulse 2s infinite;
        }
        .btn-tracking:hover {
            background: linear-gradient(135deg, #2980b9, #2573a7);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
            color: white;
        }
        .btn-group {
            display: flex;
            gap: 10px;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
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
        @keyframes rotateIn {
            from {
                transform: rotate(-180deg);
                opacity: 0;
            }
            to {
                transform: rotate(0);
                opacity: 1;
            }
        }
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
            }
            to {
                transform: translateX(0);
            }
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
        .info-row:hover i {
            transform: scale(1.2) rotate(360deg);
            transition: all 0.5s ease;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }
        .btn-action {
            width: 32px;
            height: 32px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .btn-action::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }
        .btn-action:hover::before {
            transform: translateX(0);
        }
        .btn-details {
            background: #007bff;
            color: white;
            animation-delay: 0.1s;
        }
        .btn-details:hover {
            background: #0056b3;
            transform: translateY(-3px) rotate(360deg);
            box-shadow: 0 3px 10px rgba(0, 123, 255, 0.3);
            color: white;
        }
        .btn-tracking {
            background: #17a2b8;
            color: white;
            animation-delay: 0.2s;
        }
        .btn-tracking:hover {
            background: #138496;
            transform: translateY(-3px) rotate(360deg);
            box-shadow: 0 3px 10px rgba(23, 162, 184, 0.3);
            color: white;
        }
        .btn-cancel {
            background: #dc3545;
            color: white;
            animation-delay: 0.3s;
        }
        .btn-cancel:hover {
            background: #c82333;
            transform: translateY(-3px) rotate(360deg);
            box-shadow: 0 3px 10px rgba(220, 53, 69, 0.3);
            color: white;
        }
        @keyframes slideInButton {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        .order-card:hover .btn-action {
            animation: bounceButton 0.5s ease;
        }
        @keyframes bounceButton {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        .tooltip {
            font-size: 0.8rem;
        }
        .tooltip .tooltip-inner {
            background-color: #333;
            border-radius: 15px;
            padding: 5px 10px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="page-header animate__animated animate__fadeIn">
        <div class="container">
            <h2 class="mb-2">Mes Commandes</h2>
            <?php if ($commandes->num_rows > 0): ?>
                <span class="badge bg-light text-dark">
                    <i class="fas fa-list me-2"></i><?= $commandes->num_rows ?> commande(s)
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <?php if ($commandes->num_rows > 0): ?>
            <div class="row">
                <?php 
                $delay = 0;
                while ($commande = $commandes->fetch_assoc()): 
                ?>
                    <div class="col-md-6" style="animation-delay: <?= $delay ?>ms">
                        <div class="order-card">
                            <div class="order-header">
                                <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Commande #<?= $commande['id'] ?></h5>
                                    <span class="status-badge bg-<?= getStatusColor($commande['statut']) ?>">
                                        <?= formatStatus($commande['statut']) ?>
                                </span>
                                </div>
                                    </div>
                            <div class="info-row">
                                <span><i class="far fa-calendar-alt"></i> Date</span>
                                <strong><?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?></strong>
                                    </div>
                            <div class="info-row">
                                <span><i class="fas fa-box"></i> Articles</span>
                                <strong><?= $commande['nb_produits'] ?></strong>
                                </div>
                            <div class="info-row">
                                <span><i class="fas fa-money-bill-wave"></i> Total</span>
                                <strong><?= number_format($commande['total'], 0) ?> CFA</strong>
                                </div>
                                <?php if ($commande['livreur_nom']): ?>
                                <div class="info-row">
                                    <span><i class="fas fa-truck"></i> Livreur</span>
                                    <strong><?= htmlspecialchars($commande['livreur_nom']) ?></strong>
                                    </div>
                                <?php endif; ?>
                            
                            <div class="p-3">
                                <div class="action-buttons">
                                <a href="commande_details.php?id=<?= $commande['id'] ?>" 
                                       class="btn btn-sm btn-action btn-details animate__animated animate__fadeIn"
                                       data-bs-toggle="tooltip" 
                                       title="Voir les détails">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="tracking.php?commande_id=<?= $commande['id'] ?>" 
                                       class="btn btn-sm btn-action btn-tracking animate__animated animate__fadeIn"
                                       data-bs-toggle="tooltip" 
                                       title="Suivre le colis">
                                        <i class="fas fa-truck"></i>
                                </a>
                                <?php if ($commande['statut'] === 'en attente'): ?>
                                        <button class="btn btn-sm btn-action btn-cancel animate__animated animate__fadeIn" 
                                                onclick="confirmerAnnulation(<?= $commande['id'] ?>)"
                                                data-bs-toggle="tooltip" 
                                                title="Annuler la commande">
                                            <i class="fas fa-times"></i>
                                    </button>
                                <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php 
                $delay += 100;
                endwhile; 
                ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-shopping-cart mb-4"></i>
                <h3>Aucune commande pour le moment</h3>
                <p class="text-muted">Découvrez notre catalogue et commencez vos achats !</p>
                <a href="index.php" class="btn btn-primary btn-lg mt-3 animate__animated animate__pulse animate__infinite">
                    <i class="fas fa-shopping-bag me-2"></i>Explorer la boutique
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal de confirmation -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer l'annulation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="fas fa-exclamation-triangle mb-4" style="font-size: 4rem;"></i>
                    <h4>Êtes-vous sûr ?</h4>
                    <p class="text-muted">Cette action est irréversible.</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-danger" id="confirmAnnulation">
                        <i class="fas fa-times me-2"></i>Confirmer l'annulation
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let commandeIdToCancel = null;
        const modal = new bootstrap.Modal(document.getElementById('confirmModal'));

        function confirmerAnnulation(commandeId) {
            commandeIdToCancel = commandeId;
            modal.show();
        }

        document.getElementById('confirmAnnulation').addEventListener('click', function() {
            if (commandeIdToCancel) {
                fetch('annuler_commande.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'commande_id=' + commandeIdToCancel
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erreur lors de l\'annulation : ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Erreur lors de l\'annulation de la commande');
                });
            }
            modal.hide();
        });

        // Initialiser les tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Animation d'entrée des boutons
        document.querySelectorAll('.btn-action').forEach((btn, index) => {
            btn.style.animationDelay = `${index * 0.1}s`;
            btn.classList.add('animate__animated', 'animate__slideInRight');
        });

        // Animation au survol de la carte
        document.querySelectorAll('.order-card').forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.querySelectorAll('.btn-action').forEach((btn, index) => {
                    setTimeout(() => {
                        btn.classList.add('animate__animated', 'animate__pulse');
                    }, index * 100);
                });
            });

            card.addEventListener('mouseleave', () => {
                card.querySelectorAll('.btn-action').forEach(btn => {
                    btn.classList.remove('animate__animated', 'animate__pulse');
                });
            });
        });
    </script>

    <?php
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
</body>
</html> 