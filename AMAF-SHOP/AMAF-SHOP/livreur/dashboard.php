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
          COUNT(cp.produit_id) as nb_produits
          FROM commande c 
          LEFT JOIN utilisateur u ON c.utilisateur_id = u.id
          LEFT JOIN commande_produit cp ON c.id = cp.commande_id
          WHERE c.livreur_id = ?
          GROUP BY c.id
          ORDER BY c.date_commande DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $livreur_id);
$stmt->execute();
$commandes = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Livreur - AMAF-SHOP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-store me-2"></i>AMAF-SHOP
            </a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link">
                            <i class="fas fa-user me-2"></i><?= htmlspecialchars($_SESSION['user_name']) ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Mes livraisons</h2>
        
        <div class="row mt-4">
            <?php if ($commandes->num_rows > 0): ?>
                <?php while ($commande = $commandes->fetch_assoc()): 
                    $infos_livraison = json_decode($commande['informations_livraison'], true);
                ?>
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Commande #<?= $commande['id'] ?></h5>
                                <span class="badge bg-<?= getStatusColor($commande['statut']) ?>">
                                    <?= htmlspecialchars($commande['statut']) ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <h6>Client</h6>
                                    <p class="mb-1">
                                        <strong>Nom :</strong> 
                                        <?= htmlspecialchars($infos_livraison['nom']) ?> 
                                        <?= htmlspecialchars($infos_livraison['prenom']) ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong>Téléphone :</strong> 
                                        <?= htmlspecialchars($infos_livraison['telephone']) ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong>Adresse :</strong> 
                                        <?= htmlspecialchars($infos_livraison['adresse']) ?>
                                    </p>
                                </div>

                                <div class="mb-3">
                                    <h6>Détails de la commande</h6>
                                    <p class="mb-1">
                                        <strong>Nombre d'articles :</strong> 
                                        <?= $commande['nb_produits'] ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong>Montant total :</strong> 
                                        <?= number_format($commande['total'], 0) ?> CFA
                                    </p>
                                    <p class="mb-1">
                                        <strong>Date :</strong> 
                                        <?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?>
                                    </p>
                                </div>

                                <?php if (!empty($infos_livraison['instructions'])): ?>
                                    <div class="mb-3">
                                        <h6>Instructions de livraison</h6>
                                        <p class="mb-1">
                                            <?= htmlspecialchars($infos_livraison['instructions']) ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <?php if ($commande['statut'] === 'en attente'): ?>
                                    <button class="btn btn-success btn-sm" 
                                            onclick="updateStatus(<?= $commande['id'] ?>, 'en_livraison')">
                                        <i class="fas fa-truck me-2"></i>Démarrer la livraison
                                    </button>
                                <?php elseif ($commande['statut'] === 'en_livraison'): ?>
                                    <button class="btn btn-primary btn-sm" 
                                            onclick="updateStatus(<?= $commande['id'] ?>, 'livré')">
                                        <i class="fas fa-check me-2"></i>Marquer comme livrée
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Vous n'avez pas de livraisons en cours.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function updateStatus(commandeId, newStatus) {
        if (confirm('Êtes-vous sûr de vouloir modifier le statut de cette commande ?')) {
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
                    location.reload();
                } else {
                    alert('Erreur lors de la mise à jour du statut');
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
            case 'en_livraison':
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