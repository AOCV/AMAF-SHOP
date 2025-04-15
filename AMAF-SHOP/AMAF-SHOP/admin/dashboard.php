<?php
session_start();
require '../config.php';

// Vérifier si l'utilisateur est connecté et est un admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Statistiques pour le tableau de bord
$stats = [
    'total_produits' => 0,
    'total_commandes' => 0,
    'total_utilisateurs' => 0,
    'chiffre_affaires' => 0
];

// Récupérer les statistiques
$queries = [
    "SELECT COUNT(*) as count FROM produit",
    "SELECT COUNT(*) as count FROM commande",
    "SELECT COUNT(*) as count FROM utilisateur",
    "SELECT SUM(total) as total FROM commande WHERE statut = 'terminé'"
];

foreach ($queries as $index => $query) {
    $result = $conn->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        switch ($index) {
            case 0: $stats['total_produits'] = $row['count']; break;
            case 1: $stats['total_commandes'] = $row['count']; break;
            case 2: $stats['total_utilisateurs'] = $row['count']; break;
            case 3: $stats['chiffre_affaires'] = $row['total'] ?? 0; break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Ma Boutique</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                        <span class="nav-link">
                            <!-- <i class="fas fa-user me-2"></i><?= htmlspecialchars($_SESSION['username']) ?> -->
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
        <h2>Tableau de bord administrateur</h2>
        
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Produits</h5>
                        <p class="card-text display-6"><?= $stats['total_produits'] ?></p>
                        <a href="../supprime_produit.php" class="text-white">Gérer les produits</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card text-white bg-success mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Commandes</h5>
                        <p class="card-text display-6"><?= $stats['total_commandes'] ?></p>
                        <a href="commandes.php" class="text-white">Voir les commandes</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card text-white bg-info mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Utilisateurs</h5>
                        <p class="card-text display-6"><?= $stats['total_utilisateurs'] ?></p>
                        <a href="utilisateurs.php" class="text-white">Gérer les utilisateurs</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card text-white bg-warning mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Chiffre d'affaires</h5>
                        <p class="card-text display-6"><?= number_format($stats['chiffre_affaires'], 2) ?> €</p>
                        <a href="rapports.php" class="text-white">Voir les rapports</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        Actions rapides
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="../ajout_produit.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Ajouter un produit
                            </a>
                            <a href="categories.php" class="btn btn-secondary">
                                <i class="fas fa-tags me-2"></i>Gérer les catégories
                            </a>
                            <a href="promotions.php" class="btn btn-success">
                                <i class="fas fa-percent me-2"></i>Gérer les promotions
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        Dernières commandes
                    </div>
                    <div class="card-body">
                        <!-- Liste des 5 dernières commandes -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 