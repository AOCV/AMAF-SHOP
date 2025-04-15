<?php
session_start();
require '../config.php';

// // Vérifier si l'utilisateur est connecté et est un admin
// if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
//     header('Location: ../login.php');
//     exit();
// }

// Traitement du changement de statut
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['commande_id'], $_POST['statut'])) {
    $commande_id = intval($_POST['commande_id']);
    $statut = $_POST['statut'];
    $livreur_id = isset($_POST['livreur_id']) ? intval($_POST['livreur_id']) : null;
    
    $query = "UPDATE commande SET statut = ?";
    $params = [$statut];
    $types = "s";
    
    if ($statut === 'en livraison' && $livreur_id) {
        $query .= ", livreur_id = ?";
        $params[] = $livreur_id;
        $types .= "i";
    }
    
    $query .= " WHERE id = ?";
    $params[] = $commande_id;
    $types .= "i";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        $success = "Statut de la commande mis à jour.";
    } else {
        $error = "Erreur lors de la mise à jour.";
    }
}

// Récupération des livreurs pour l'assignation
$query_livreurs = "SELECT id, nom, utilisateur FROM utilisateur WHERE type = 'livreur'";
$livreurs = $conn->query($query_livreurs);

// Récupération des commandes avec les informations client
$query = "SELECT c.*, u.nom as client_nom, u.email as client_email, u.telephone, 
          l.nom as livreur_nom, l.utilisateur as livreur_username
          FROM commande c 
          LEFT JOIN utilisateur u ON c.utilisateur_id = u.id
          LEFT JOIN utilisateur l ON c.livreur_id = l.id
          ORDER BY c.date_commande DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Commandes - AMAF-SHOP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .status-badge {
            width: 100px;
            text-align: center;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-store me-2"></i>AMAF-SHOP
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Tableau de bord
                        </a>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Gestion des Commandes</h2>
            <button class="btn btn-outline-primary" onclick="exportCSV()">
                <i class="fas fa-download me-2"></i>Exporter CSV
            </button>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Client</th>
                                <th>Contact</th>
                                <th>Total</th>
                                <th>Date</th>
                                <th>Statut</th>
                                <th>Livreur</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($commande = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $commande['id'] ?></td>
                                    <td><?= htmlspecialchars($commande['client_nom']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($commande['client_email']) ?><br>
                                        <?= htmlspecialchars($commande['telephone'] ?? '-') ?>
                                    </td>
                                    <td><?= number_format($commande['total'], 2) ?> €</td>
                                    <td><?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?></td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="commande_id" value="<?= $commande['id'] ?>">
                                            <select name="statut" class="form-select form-select-sm status-badge"
                                                    onchange="this.form.submit()"
                                                    style="background-color: <?= getStatusColor($commande['statut']) ?>;">
                                                <option value="en attente" <?= $commande['statut'] === 'en attente' ? 'selected' : '' ?>>
                                                    En attente
                                                </option>
                                                <option value="confirmé" <?= $commande['statut'] === 'confirmé' ? 'selected' : '' ?>>
                                                    Confirmé
                                                </option>
                                                <option value="en livraison" <?= $commande['statut'] === 'en livraison' ? 'selected' : '' ?>>
                                                    En livraison
                                                </option>
                                                <option value="livré" <?= $commande['statut'] === 'livré' ? 'selected' : '' ?>>
                                                    Livré
                                                </option>
                                                <option value="annulé" <?= $commande['statut'] === 'annulé' ? 'selected' : '' ?>>
                                                    Annulé
                                                </option>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <?php if ($commande['statut'] === 'en livraison'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="commande_id" value="<?= $commande['id'] ?>">
                                                <input type="hidden" name="statut" value="en livraison">
                                                <select name="livreur_id" class="form-select form-select-sm"
                                                        onchange="this.form.submit()"
                                                        style="width: auto;">
                                                    <option value="">Choisir un livreur</option>
                                                    <?php 
                                                    $livreurs->data_seek(0);
                                                    while ($livreur = $livreurs->fetch_assoc()): 
                                                    ?>
                                                        <option value="<?= $livreur['id'] ?>" 
                                                                <?= $commande['livreur_id'] == $livreur['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($livreur['utilisateur']) ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </form>
                                        <?php else: ?>
                                            <?= htmlspecialchars($commande['livreur_username'] ?? '-') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-info" 
                                                onclick="viewDetails(<?= $commande['id'] ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteOrder(<?= $commande['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewDetails(orderId) {
            window.location.href = 'commande_details.php?id=' + orderId;
        }

        function deleteOrder(orderId) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette commande ?')) {
                fetch('delete_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'order_id=' + orderId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erreur lors de la suppression');
                    }
                });
            }
        }

        function exportCSV() {
            window.location.href = 'export_orders.php';
        }
    </script>

    <?php
    function getStatusColor($status) {
        switch ($status) {
            case 'en attente':
                return '#ffc107';
            case 'confirmé':
                return '#17a2b8';
            case 'en livraison':
                return '#007bff';
            case 'livré':
                return '#28a745';
            case 'annulé':
                return '#dc3545';
            default:
                return '#6c757d';
        }
    }
    ?>
</body>
</html> 