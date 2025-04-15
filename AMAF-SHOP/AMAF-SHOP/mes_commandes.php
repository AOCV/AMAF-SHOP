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
    <style>
        .status-badge {
            width: 100px;
            text-align: center;
        }
        .order-card {
            transition: transform 0.2s;
        }
        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-store me-2"></i>AMAF-SHOP
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home me-2"></i>Accueil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profil.php">
                            <i class="fas fa-user me-2"></i>Mon Profil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2 class="mb-4">Mes Commandes</h2>

        <?php if ($commandes->num_rows > 0): ?>
            <div class="row">
                <?php while ($commande = $commandes->fetch_assoc()): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card order-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Commande #<?= $commande['id'] ?></h5>
                                <span class="badge bg-<?= getStatusColor($commande['statut']) ?>">
                                    <?= htmlspecialchars($commande['statut']) ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="row mb-2">
                                    <div class="col-6">
                                        <strong>Date :</strong>
                                    </div>
                                    <div class="col-6">
                                        <?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-6">
                                        <strong>Nombre d'articles :</strong>
                                    </div>
                                    <div class="col-6">
                                        <?= $commande['nb_produits'] ?>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-6">
                                        <strong>Total :</strong>
                                    </div>
                                    <div class="col-6">
                                        <?= number_format($commande['total'], 2) ?> €
                                    </div>
                                </div>
                                <?php if ($commande['livreur_nom']): ?>
                                    <div class="row mb-2">
                                        <div class="col-6">
                                            <strong>Livreur :</strong>
                                        </div>
                                        <div class="col-6">
                                            <?= htmlspecialchars($commande['livreur_nom']) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <a href="commande_details.php?id=<?= $commande['id'] ?>" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye me-2"></i>Voir les détails
                                </a>
                                <?php if ($commande['statut'] === 'en attente'): ?>
                                    <button class="btn btn-danger btn-sm float-end" 
                                            onclick="annulerCommande(<?= $commande['id'] ?>)">
                                        <i class="fas fa-times me-2"></i>Annuler
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Vous n'avez pas encore passé de commande.
                <a href="index.php" class="alert-link">Commencer vos achats</a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function annulerCommande(commandeId) {
            if (confirm('Êtes-vous sûr de vouloir annuler cette commande ?')) {
                fetch('annuler_commande.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'commande_id=' + commandeId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erreur lors de l\'annulation de la commande');
                    }
                });
            }
        }
    </script>

    <?php
    function getStatusColor($status) {
        switch ($status) {
            case 'en attente':
                return 'warning';
            case 'confirmé':
                return 'info';
            case 'en livraison':
                return 'primary';
            case 'livré':
                return 'success';
            case 'annulé':
                return 'danger';
            default:
                return 'secondary';
        }
    }
    ?>
</body>
</html> 