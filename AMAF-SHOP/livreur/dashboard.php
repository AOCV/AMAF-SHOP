<?php
session_start();
require '../config.php';

// Vérifier si l'utilisateur est connecté et est un livreur
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'livreur') {
    header('Location: ../login.php');
    exit();
}

// Récupérer les commandes assignées au livreur
$livreur_id = $_SESSION['user_id'];
$query = "SELECT c.*, 
          u.nom as client_nom, u.telephone as client_tel,
          COUNT(cp.produit_id) as nb_produits,
          c.informations_livraison,
          c.statut,
          c.date_commande,
          c.date_livraison
          FROM commande c 
          LEFT JOIN utilisateur u ON c.utilisateur_id = u.id
          LEFT JOIN commande_produit cp ON c.id = cp.commande_id
          WHERE c.livreur_id = ? OR (c.statut = 'en attente' AND c.livreur_id IS NULL)
          GROUP BY c.id
          ORDER BY c.date_commande DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $livreur_id);
$stmt->execute();
$commandes = $stmt->get_result();

// Compter les commandes par statut
$stats = [
    'en_attente' => 0,
    'en_livraison' => 0,
    'livre' => 0,
    'total' => 0
];

$commandes_array = [];
while ($commande = $commandes->fetch_assoc()) {
    $commandes_array[] = $commande;
    $stats['total']++;
    $statut = strtolower(trim($commande['statut']));
    if ($statut === 'en attente' || $statut === 'en_attente') $stats['en_attente']++;
    if ($statut === 'en livraison' || $statut === 'en_livraison') $stats['en_livraison']++;
    if ($statut === 'livre' || $statut === 'livré') $stats['livre']++;
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
    <title>Dashboard Livreur - AMAF-SHOP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            animation: fadeIn 0.5s ease-out;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .stats-container {
            background: linear-gradient(45deg, #007bff, #00b894);
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
            border-radius: 15px;
            color: white;
            box-shadow: 0 10px 20px rgba(0,123,255,0.2);
        }

        .stat-card {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(255,255,255,0.2);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin: 0.7rem 0;
        }

        .status-badge {
            padding: 0.3rem 0.7rem;
            border-radius: 20px;
            font-weight: 600;
            animation: pulse 2s infinite;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 1px;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .action-button {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            color: white;
            padding: 0.6rem 1rem;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .action-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        .action-button:active {
            transform: translateY(1px);
        }

        .delivery-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 0.7rem;
            margin-bottom: 0.7rem;
            transition: all 0.3s ease;
        }
        
        .delivery-info:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .delivery-info h6 {
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .map-link {
            display: inline-block;
            padding: 0.3rem 0.7rem;
            background: #f8f9fa;
            border-radius: 20px;
            color: #007bff;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.8rem;
        }

        .map-link:hover {
            background: #007bff;
            color: white;
        }
        
        .card-header {
            border-bottom: none;
            background: white;
            position: relative;
            overflow: hidden;
            padding: 0.8rem;
        }
        
        .card-header:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(to right, transparent, #007bff, transparent);
        }
        
        .livreur-tracking {
            margin-top: 10px;
            padding: 8px;
            background: #f0f8ff;
            border-radius: 10px;
            border-left: 4px solid #007bff;
        }
        
        .livreur-tracking h6 {
            font-size: 0.9rem;
        }
        
        .livreur-tracking p {
            font-size: 0.75rem;
        }
        
        /* Filtres */
        .filter-container {
            background: white;
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
        }
        
        .filter-btn {
            padding: 8px 15px;
            border-radius: 20px;
            margin-right: 10px;
            background: #f8f9fa;
            border: none;
            color: #495057;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .filter-btn:hover {
            background: #e9ecef;
        }
        
        .filter-btn.active {
            background: #007bff;
            color: white;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .track-btn {
            background: linear-gradient(45deg, #17a2b8, #1abc9c);
            color: white;
            border: none;
            transition: all 0.3s ease;
            padding: 0.6rem 1rem;
            font-size: 0.9rem;
        }
        
        .track-btn:hover {
            background: linear-gradient(45deg, #1abc9c, #17a2b8);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(23, 162, 184, 0.3);
        }
    </style>
    <!-- Modifiez le style pour l'en-tête des cartes -->
<style>
    /* ... autres styles existants ... */
    
    .card-header {
        border-bottom: none;
        background: linear-gradient(45deg, #2c3e50, #3498db);
        color: white;
        position: relative;
        overflow: hidden;
        padding: 0.8rem;
        border-top-left-radius: 12px;
        border-top-right-radius: 12px;
    }
    
    .card-header h5 {
        font-weight: 600;
        margin-bottom: 0;
        font-size: 1rem;
    }
    
    .card-header:after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(to right, transparent, rgba(255,255,255,0.5), transparent);
    }
    
    /* Pour s'assurer que le badge de statut reste bien visible sur le fond coloré */
    .status-badge {
        padding: 0.3rem 0.7rem;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.7rem;
        letter-spacing: 1px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        background-color: rgba(255,255,255,0.9);
        color: #333;
    }
    
    .status-badge.bg-warning {
        background-color: #ffc107 !important;
        color: #212529;
    }
    
    .status-badge.bg-primary {
        background-color: #007bff !important;
        color: white;
    }
    
    .status-badge.bg-success {
        background-color: #28a745 !important;
        color: white;
    }
    
    .status-badge.bg-danger {
        background-color: #dc3545 !important;
        color: white;
    }
    
    .status-badge.bg-info {
        background-color: #17a2b8 !important;
        color: white;
    }
    
    /* ... autres styles existants ... */
</style>

</head>
<body>
    <!-- Navbar améliorée -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-gradient" style="background: linear-gradient(45deg, #1a237e, #0d47a1);">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-truck me-2"></i>Dashboard Livreur
            </a>
            <div class="d-flex align-items-center">
                <span class="text-white me-3">
                    <i class="fas fa-user-circle me-2"></i><?= htmlspecialchars($_SESSION['user_name']) ?>
                </span>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                </a>
            </div>
        </div>
    </nav>

    <!-- Statistiques -->
    <div class="container mt-4">
        <div class="stats-container animate__animated animate__fadeIn">
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-clock fa-2x"></i>
                        <div class="stat-number"><?= $stats['en_attente'] ?></div>
                        <div>En attente</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-truck fa-2x"></i>
                        <div class="stat-number"><?= $stats['en_livraison'] ?></div>
                        <div>En livraison</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-check-circle fa-2x"></i>
                        <div class="stat-number"><?= $stats['livre'] ?></div>
                        <div>Livrées</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-boxes fa-2x"></i>
                        <div class="stat-number"><?= $stats['total'] ?></div>
                        <div>Total</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filtres -->
        <div class="filter-container">
            <div class="d-flex align-items-center">
                <span class="me-3"><i class="fas fa-filter me-2"></i>Filtrer:</span>
                <button class="filter-btn active" data-filter="all">Toutes</button>
                <button class="filter-btn" data-filter="en_attente">En attente</button>
                <button class="filter-btn" data-filter="en_livraison">En livraison</button>
                <button class="filter-btn" data-filter="livre">Livrées</button>
            </div>
        </div>

        <!-- Liste des livraisons -->
        <div class="row">
            <?php if (count($commandes_array) > 0): ?>
                <?php foreach ($commandes_array as $index => $commande): 
                    $infos_livraison = json_decode($commande['informations_livraison'], true);
                    $normalizedStatus = strtolower(trim($commande['statut']));
                    
                    // Transformation des statuts pour correspondre aux filtres
                    $filterStatus = $normalizedStatus;
                    if ($normalizedStatus === 'en attente') $filterStatus = 'en_attente';
                    if ($normalizedStatus === 'en livraison') $filterStatus = 'en_livraison';
                    if ($normalizedStatus === 'livré') $filterStatus = 'livre';
                ?>
                    <div class="col-md-6 col-lg-4 mb-3 animate__animated animate__fadeInUp commande-card" 
                         data-status="<?= $filterStatus ?>" 
                         style="animation-delay: <?= $index * 0.1 ?>s">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center p-3">
                                <h5 class="mb-0">Commande #<?= $commande['id'] ?></h5>
                                <span class="status-badge bg-<?= getStatusColor($commande['statut']) ?>">
                                    <?= formatStatus($commande['statut']) ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="delivery-info">
                                    <h6><i class="fas fa-user me-2"></i>Client</h6>
                                    <p class="mb-2"><?= htmlspecialchars($infos_livraison['nom'] ?? '') ?> <?= htmlspecialchars($infos_livraison['prenom'] ?? '') ?></p>
                                    <a href="tel:<?= htmlspecialchars($infos_livraison['telephone'] ?? '') ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-phone me-2"></i><?= htmlspecialchars($infos_livraison['telephone'] ?? 'N/A') ?>
                                    </a>
                                </div>

                                <div class="delivery-info">
                                    <h6><i class="fas fa-map-marker-alt me-2"></i>Adresse</h6>
                                    <p class="mb-2"><?= htmlspecialchars($infos_livraison['adresse'] ?? 'Adresse non disponible') ?></p>
                                    <?php if (!empty($infos_livraison['adresse'])): ?>
                                        <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($infos_livraison['adresse']) ?>" 
                                           class="map-link" target="_blank">
                                            <i class="fas fa-directions me-2"></i>Voir sur la carte
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <div class="delivery-info">
                                    <h6><i class="fas fa-info-circle me-2"></i>Détails</h6>
                                    <p class="mb-1">Articles: <?= $commande['nb_produits'] ?></p>
                                    <p class="mb-1">Total: <?= number_format($commande['total'], 0) ?> CFA</p>
                                    <p class="mb-0">Date: <?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?></p>
                                </div>

                                <?php 
                                    $statut = strtolower(trim($commande['statut']));
                                    if ($statut === 'en attente' || $statut === 'en_attente'): 
                                ?>
                                    <button class="btn action-button w-100" onclick="updateStatus(<?= $commande['id'] ?>, 'en_livraison')">
                                        <i class="fas fa-truck me-2"></i>Démarrer la livraison
                                    </button>
                                <?php elseif ($statut === 'en livraison' || $statut === 'en_livraison'): ?>
                                    <button class="btn action-button w-100" onclick="updateStatus(<?= $commande['id'] ?>, 'livre')">
                                        <i class="fas fa-check me-2"></i>Marquer comme livrée
                                    </button>
                                <?php endif; ?>

                                <div class="livreur-tracking mt-3">
                                    <h6><i class="fas fa-search-location me-2"></i>Suivi</h6>
                                    <p class="small mb-2">Suivez cette commande ou partagez le lien avec le client</p>
                                    <button class="btn track-btn w-100" 
                                            onclick="window.open('livreur_tracking.php?commande_id=<?= $commande['id'] ?>', '_blank')">
                                        <i class="fas fa-map me-2"></i>Voir le suivi de la commande
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center mt-5">
                    <div class="empty-state">
                        <i class="fas fa-truck-loading mb-4" style="font-size: 5rem; color: #ccc;"></i>
                        <h3>Aucune livraison disponible</h3>
                        <p class="text-muted">Vous n'avez pas encore de commandes à livrer.</p>
                    </div>
                </div>
            <?php endif; ?>
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
    
    // Filtrage des commandes
    document.addEventListener('DOMContentLoaded', function() {
        const filterBtns = document.querySelectorAll('.filter-btn');
        
        filterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                // Retirer la classe active de tous les boutons
                filterBtns.forEach(b => b.classList.remove('active'));
                // Ajouter la classe active au bouton cliqué
                this.classList.add('active');
                
                const filter = this.getAttribute('data-filter');
                const cards = document.querySelectorAll('.commande-card');
                
                cards.forEach(card => {
                    console.log(`Card status: ${card.getAttribute('data-status')}, Filter: ${filter}`);
                    
                    if (filter === 'all' || card.getAttribute('data-status') === filter) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
    });
    </script>
    <script>
    // ... scripts existants ...
    
    // Ajouter un effet de brillance aux en-têtes des cartes
    document.addEventListener('DOMContentLoaded', function() {
        const headers = document.querySelectorAll('.card-header');
        
        headers.forEach(header => {
            header.addEventListener('mouseenter', function() {
                this.style.background = 'linear-gradient(45deg, #3498db, #2c3e50)';
            });
            
            header.addEventListener('mouseleave', function() {
                this.style.background = 'linear-gradient(45deg, #2c3e50, #3498db)';
            });
        });
    });
</script>
</body>
</html>