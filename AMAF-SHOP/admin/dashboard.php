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
    'chiffre_affaires' => 0,
    'commandes_recentes' => []
];

// Récupérer les statistiques
$queries = [
    "SELECT COUNT(*) as count FROM produit",
    "SELECT COUNT(*) as count FROM commande",
    "SELECT COUNT(*) as count FROM utilisateur",
    "SELECT SUM(total) as total FROM commande WHERE statut = 'livré' OR statut = 'terminé'",
    // Modification de cette requête pour retirer le champ u.prenom qui n'existe pas
    "SELECT c.*, u.nom as client_nom 
     FROM commande c 
     LEFT JOIN utilisateur u ON c.utilisateur_id = u.id 
     ORDER BY c.date_commande DESC 
     LIMIT 5"
];

foreach ($queries as $index => $query) {
    $result = $conn->query($query);
    if ($result) {
        if ($index === 4) { // Commandes récentes
            while ($row = $result->fetch_assoc()) {
                $stats['commandes_recentes'][] = $row;
            }
        } else {
            $row = $result->fetch_assoc();
            switch ($index) {
                case 0: $stats['total_produits'] = $row['count']; break;
                case 1: $stats['total_commandes'] = $row['count']; break;
                case 2: $stats['total_utilisateurs'] = $row['count']; break;
                case 3: $stats['chiffre_affaires'] = $row['total'] ?? 0; break;
            }
        }
    }
}

// Récupérer les données pour le graphique (exemple: ventes des 7 derniers jours)
$ventes_data = [];
$query_ventes = "SELECT DATE(date_commande) as jour, SUM(total) as montant 
                FROM commande 
                WHERE date_commande > DATE_SUB(NOW(), INTERVAL 7 DAY) 
                GROUP BY DATE(date_commande) 
                ORDER BY jour";
$result_ventes = $conn->query($query_ventes);
if ($result_ventes) {
    while ($row = $result_ventes->fetch_assoc()) {
        $ventes_data[$row['jour']] = $row['montant'];
    }
}

// Statuts des commandes pour le graphique en secteurs
$statuts_commandes = [];
$query_statuts = "SELECT statut, COUNT(*) as count FROM commande GROUP BY statut";
$result_statuts = $conn->query($query_statuts);
if ($result_statuts) {
    while ($row = $result_statuts->fetch_assoc()) {
        $statuts_commandes[$row['statut']] = $row['count'];
    }
}

function getStatusColor($status) {
    switch(strtolower($status)) {
        case 'en attente': return 'warning';
        case 'en_attente': return 'warning';
        case 'confirmé': return 'info';
        case 'confirme': return 'info';
        case 'en préparation': return 'primary';
        case 'en_preparation': return 'primary';
        case 'en livraison': return 'info';
        case 'en_livraison': return 'info';
        case 'livré': return 'success';
        case 'livre': return 'success';
        case 'terminé': return 'success';
        case 'termine': return 'success';
        case 'annulé': return 'danger';
        case 'annule': return 'danger';
        default: return 'secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord administrateur - AMAF-SHOP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <style>
        :root {
            --primary: #3a7bd5;
            --primary-light: #5a95e5;
            --secondary: #00d2ff;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --info: #3498db;
            --dark: #2c3e50;
            --light: #f8f9fa;
            --gray: #6c757d;
            --gradient: linear-gradient(135deg, var(--primary), var(--secondary));
            --shadow: 0 5px 15px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }
        
        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f5f7fa;
            overflow-x: hidden;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 260px;
            background: var(--dark);
            z-index: 1000;
            transition: var(--transition);
            box-shadow: var(--shadow);
            color: #fff;
        }
        
        .sidebar-brand {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
        }
        
        .sidebar-brand i {
            font-size: 1.5rem;
            margin-right: 10px;
            color: var(--secondary);
        }
        
        .sidebar-brand h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(45deg, var(--secondary), #fff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .sidebar-menu h3 {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.6);
            padding: 0 20px;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        
        .sidebar-menu ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            position: relative;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .sidebar-menu a:hover, 
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }
        
        .sidebar-menu a.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: var(--gradient);
        }
        
        .sidebar-menu i {
            font-size: 1.2rem;
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .sidebar-menu .badge {
            margin-left: auto;
        }
        
        .sidebar-footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-footer a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: var(--transition);
        }
        
        .sidebar-footer a:hover {
            color: #fff;
        }
        
        .sidebar-footer i {
            margin-right: 10px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 20px;
            transition: var(--transition);
        }
        
        .page-header {
            background: var(--gradient);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow);
            animation: fadeInDown 0.5s ease;
        }
        
        .page-header::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            bottom: -50%;
            left: -50%;
            background: linear-gradient(to right, rgba(255,255,255,0) 0%, rgba(255,255,255,0.2) 50%, rgba(255,255,255,0) 100%);
            transform: rotate(45deg) translate(0, -100%);
            animation: shine 3s infinite;
        }
        
        @keyframes shine {
            0% { transform: rotate(45deg) translate(0, -100%); }
            100% { transform: rotate(45deg) translate(0, 100%); }
        }
        
        .page-header h1 {
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .page-header p {
            opacity: 0.8;
            margin-bottom: 0;
        }
        
        .stat-card {
            border-radius: 15px;
            padding: 20px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            color: white;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
            min-height: 140px;
            transform: translateY(0);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .stat-card.bg-primary {
            background: linear-gradient(45deg, var(--primary), var(--primary-light));
        }
        
        .stat-card.bg-success {
            background: linear-gradient(45deg, #2ecc71, #4cd786);
        }
        
        .stat-card.bg-info {
            background: linear-gradient(45deg, #3498db, #54a9e2);
        }
        
        .stat-card.bg-warning {
            background: linear-gradient(45deg, #f39c12, #f5ab35);
        }
        
        .stat-card .icon {
            position: absolute;
            bottom: -15px;
            right: 10px;
            font-size: 5rem;
            opacity: 0.2;
            transition: var(--transition);
        }
        
        .stat-card:hover .icon {
            transform: scale(1.1) rotate(10deg);
            opacity: 0.3;
        }
        
        .stat-card .stat-title {
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            font-size: 1rem;
        }
        
        .stat-card .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .stat-card .stat-link {
            color: rgba(255,255,255,0.8);
            font-size: 0.9rem;
            text-decoration: none;
            transition: var(--transition);
            display: inline-block;
        }
        
        .stat-card .stat-link:hover {
            color: white;
            transform: translateX(5px);
        }
        
        .card {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            border: none;
            transition: var(--transition);
        }
        
        .card:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transform: translateY(-5px);
        }
        
        .card-header {
            background: var(--dark);
            color: white;
            padding: 15px 20px;
            font-weight: 600;
            border-bottom: none;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .actions-card .list-group-item {
            padding: 15px;
            border: none;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            transition: var(--transition);
        }
        
        .actions-card .list-group-item:last-child {
            border-bottom: none;
        }
        
        .actions-card .list-group-item:hover {
            background: #f8f9fa;
        }
        
        .actions-card .action-btn {
            background: var(--gradient);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 12px 25px;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 5px 15px rgba(58, 123, 213, 0.2);
            transition: var(--transition);
            display: block;
            width: 100%;
            text-align: left;
        }
        
        .actions-card .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(58, 123, 213, 0.3);
        }
        
        .actions-card .action-btn i {
            margin-right: 10px;
            width: 20px;
            height: 20px;
            line-height: 20px;
            text-align: center;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            font-size: 0.8rem;
        }
        
        .orders-card .order-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            transition: var(--transition);
        }
        
        .orders-card .order-item:hover {
            background: #f8f9fa;
        }
        
        .orders-card .order-item:last-child {
            border-bottom: none;
        }
        
        .orders-card .order-id {
            font-weight: 600;
            color: var(--primary);
            margin-right: 10px;
        }
        
        .orders-card .badge {
            padding: 5px 10px;
            border-radius: 50px;
            font-weight: 500;
            font-size: 0.75rem;
        }
        
        .orders-card .order-customer {
            margin-left: 15px;
            flex-grow: 1;
        }
        
        .orders-card .order-price {
            font-weight: 600;
            color: var(--dark);
        }
        
        .orders-card .order-date {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        /* Responsive - Compact sidebar on smaller screens */
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar-brand h2,
            .sidebar-menu h3,
            .sidebar-menu a span,
            .sidebar-footer span {
                display: none;
            }
            
            .sidebar-menu i {
                margin-right: 0;
                font-size: 1.5rem;
            }
            
            .sidebar-menu a {
                justify-content: center;
                padding: 15px;
            }
            
            .sidebar-footer a {
                justify-content: center;
            }
            
            .sidebar-footer i {
                margin-right: 0;
            }
            
            .main-content {
                margin-left: 80px;
            }
        }
        
        @media (max-width: 576px) {
            .stat-card .stat-value {
                font-size: 2rem;
            }
            
            .page-header {
                padding: 20px;
                border-radius: 10px;
            }
            
            .page-header h1 {
                font-size: 1.5rem;
            }
        }
        
        /* Animations */
        .fadeIn {
            animation: fadeIn 0.5s ease;
        }
        
        .slideInUp {
            animation: slideInUp 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-store"></i>
            <h2>AMAF-SHOP</h2>
        </div>
        
        <div class="sidebar-menu">
            <h3>Principal</h3>
            <ul>
                <li>
                    <a href="dashboard.php" class="active">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Tableau de bord</span>
                    </a>
                </li>
                <li>
                    <a href="../supprime_produit.php">
                        <i class="fas fa-box"></i>
                        <span>Produits</span>
                        <span class="badge bg-primary rounded-pill"><?= $stats['total_produits'] ?></span>
                    </a>
                </li>
                <li>
                    <a href="commandes.php">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Commandes</span>
                        <span class="badge bg-success rounded-pill"><?= $stats['total_commandes'] ?></span>
                    </a>
                </li>
                <li>
                    <a href="categories.php">
                        <i class="fas fa-tags"></i>
                        <span>Catégories</span>
                    </a>
                </li>
            </ul>
            
            <h3>Gestion</h3>
            <ul>
                <li>
                    <a href="utilisateurs.php">
                        <i class="fas fa-users"></i>
                        <span>Utilisateurs</span>
                        <span class="badge bg-info rounded-pill"><?= $stats['total_utilisateurs'] ?></span>
                    </a>
                </li>
                <li>
                    <a href="promotions.php">
                        <i class="fas fa-percent"></i>
                        <span>Promotions</span>
                    </a>
                </li>
                <li>
                    <a href="rapports.php">
                        <i class="fas fa-chart-line"></i>
                        <span>Rapports</span>
                    </a>
                </li>
                <li>
                    <a href="parametres.php">
                        <i class="fas fa-cog"></i>
                        <span>Paramètres</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="sidebar-footer">
            <a href="../logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Déconnexion</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header animate__animated animate__fadeInDown">
            <h1><i class="fas fa-tachometer-alt me-2"></i>Tableau de bord administrateur</h1>
            <p>Bienvenue dans le panneau d'administration de AMAF-SHOP. Consultez vos statistiques et gérez votre boutique en ligne.</p>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-md-6 col-lg-3">
                <div class="stat-card bg-primary animate__animated animate__fadeInUp">
                    <div class="stat-title">Produits</div>
                    <div class="stat-value"><?= $stats['total_produits'] ?></div>
                    <a href="../supprime_produit.php" class="stat-link">Gérer les produits <i class="fas fa-arrow-right ms-1"></i></a>
                    <div class="icon"><i class="fas fa-box"></i></div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card bg-success animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
                    <div class="stat-title">Commandes</div>
                    <div class="stat-value"><?= $stats['total_commandes'] ?></div>
                    <a href="commandes.php" class="stat-link">Voir les commandes <i class="fas fa-arrow-right ms-1"></i></a>
                    <div class="icon"><i class="fas fa-shopping-cart"></i></div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card bg-info animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
                    <div class="stat-title">Utilisateurs</div>
                    <div class="stat-value"><?= $stats['total_utilisateurs'] ?></div>
                    <a href="utilisateurs.php" class="stat-link">Gérer les utilisateurs <i class="fas fa-arrow-right ms-1"></i></a>
                    <div class="icon"><i class="fas fa-users"></i></div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card bg-warning animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
                    <div class="stat-title">Chiffre d'affaires</div>
                    <div class="stat-value"><?= number_format($stats['chiffre_affaires'], 0) ?> CFA</div>
                    <a href="rapports.php" class="stat-link">Voir les rapports <i class="fas fa-arrow-right ms-1"></i></a>
                    <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
                </div>
            </div>
        </div>
        
        <!-- Charts and Activity -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card animate__animated animate__fadeInUp" style="animation-delay: 0.4s;">
                    <div class="card-header">
                        <i class="fas fa-chart-line me-2"></i>Ventes des 7 derniers jours
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card animate__animated animate__fadeInUp" style="animation-delay: 0.5s;">
                    <div class="card-header">
                        <i class="fas fa-chart-pie me-2"></i>Statuts des commandes
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="orderStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-6">
                <div class="card actions-card animate__animated animate__fadeInUp" style="animation-delay: 0.6s;">
                    <div class="card-header">
                        <i class="fas fa-bolt me-2"></i>Actions rapides
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item">
                                <a href="../ajout_produit.php" class="action-btn">
                                    <i class="fas fa-plus"></i>Ajouter un produit
                                </a>
                            </div>
                            <div class="list-group-item">
                                <a href="categories.php" class="action-btn" style="background: linear-gradient(45deg, #3498db, #2980b9);">
                                    <i class="fas fa-tags"></i>Gérer les catégories
                                </a>
                            </div>
                            <div class="list-group-item">
                                <a href="promotions.php" class="action-btn" style="background: linear-gradient(45deg, #e74c3c, #c0392b);">
                                    <i class="fas fa-percent"></i>Créer une promotion
                                </a>
                            </div>
                            <div class="list-group-item">
                                <a href="commandes.php" class="action-btn" style="background: linear-gradient(45deg, #2ecc71, #27ae60);">
                                    <i class="fas fa-shipping-fast"></i>Gérer les livraisons
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card orders-card animate__animated animate__fadeInUp" style="animation-delay: 0.7s;">
                    <div class="card-header">
                        <i class="fas fa-shopping-bag me-2"></i>Commandes récentes
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($stats['commandes_recentes'])): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Aucune commande récente</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($stats['commandes_recentes'] as $commande): ?>
                                <div class="order-item">
                                    <div class="order-id">#<?= $commande['id'] ?></div>
                                    <span class="badge bg-<?= getStatusColor($commande['statut']) ?>">
                                        <?= ucfirst(str_replace('_', ' ', $commande['statut'])) ?>
                                    </span>
                                    <div class="order-customer ms-3">
                                        <?php if (isset($commande['client_nom']) && !empty($commande['client_nom'])): ?>
                                            <?= htmlspecialchars($commande['client_nom']) ?>
                                        <?php else: ?>
                                            Client ID: <?= $commande['utilisateur_id'] ?>
                                        <?php endif; ?>
                                        <div class="order-date"><?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?></div>
                                    </div>
                                    <div class="order-price"><?= number_format($commande['total'], 0) ?> CFA</div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center py-2">
                                <a href="commandes.php" class="btn btn-sm btn-outline-primary">Voir toutes les commandes</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Données pour les graphiques
        const salesData = {
            labels: <?= json_encode(array_keys($ventes_data)) ?>,
            datasets: [{
                label: 'Ventes (CFA)',
                data: <?= json_encode(array_values($ventes_data)) ?>,
                fill: true,
                backgroundColor: 'rgba(58, 123, 213, 0.2)',
                borderColor: '#3a7bd5',
                tension: 0.4,
                pointBackgroundColor: '#3a7bd5',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        };
        
        const orderStatusData = {
            labels: <?= json_encode(array_map(function($status) { 
                return ucfirst(str_replace('_', ' ', $status)); 
            }, array_keys($statuts_commandes))) ?>,
            datasets: [{
                data: <?= json_encode(array_values($statuts_commandes)) ?>,
                backgroundColor: [
                    '#f39c12', // En attente
                    '#3498db', // Confirmé
                    '#2980b9', // En préparation
                    '#1abc9c', // En livraison
                    '#2ecc71', // Livré
                    '#e74c3c', // Annulé
                ],
                borderWidth: 0,
                hoverOffset: 10
            }]
        };
        
        // Configuration des graphiques
        const salesChartConfig = {
            type: 'line',
            data: salesData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0,0,0,0.7)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#3a7bd5',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)',
                            borderDash: [5, 5]
                        },
                        ticks: {
                            font: {
                                size: 12
                            },
                            color: '#666'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 12
                            },
                            color: '#666'
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                },
                animation: {
                    duration: 1500,
                    easing: 'easeOutQuart'
                }
            }
        };
        
        const orderStatusChartConfig = {
            type: 'doughnut',
            data: orderStatusData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                size: 12
                            },
                            padding: 15,
                            boxWidth: 12,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.7)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 10,
                        displayColors: true
                    }
                },
                animation: {
                    animateScale: true,
                    animateRotate: true,
                    duration: 1000,
                    easing: 'easeOutCirc'
                },
                cutout: '70%'
            }
        };
        
        // Initialisation des graphiques
        window.addEventListener('DOMContentLoaded', () => {
            const salesChart = new Chart(
                document.getElementById('salesChart'),
                salesChartConfig
            );
            
            const orderStatusChart = new Chart(
                document.getElementById('orderStatusChart'),
                orderStatusChartConfig
            );
            
            // Animation des cartes de statistiques
            document.querySelectorAll('.stat-card').forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
            
            // Animation au survol des boutons d'action
            document.querySelectorAll('.action-btn').forEach(btn => {
                btn.addEventListener('mouseenter', () => {
                    btn.classList.add('animate__animated', 'animate__pulse');
                });
                
                btn.addEventListener('mouseleave', () => {
                    btn.classList.remove('animate__animated', 'animate__pulse');
                });
            });
        });
        
        // Helper function pour capitaliser la première lettre
        function ucfirst(string) {
            return string.charAt(0).toUpperCase() + string.slice(1);
        }
    </script>
</body>
</html> 