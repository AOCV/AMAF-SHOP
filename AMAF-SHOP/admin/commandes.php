<?php
session_start();
require '../config.php';

// Vérifier si l'utilisateur est connecté et est un admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$message = '';
$error = '';

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
        $message = "Statut de la commande #$commande_id mis à jour avec succès.";
    } else {
        $error = "Erreur lors de la mise à jour du statut : " . $conn->error;
    }
}

// Récupération des statistiques de commandes
$stats = [
    'total' => 0,
    'en_attente' => 0,
    'confirmees' => 0,
    'en_livraison' => 0,
    'livrees' => 0,
    'annulees' => 0
];

$query_stats = "SELECT statut, COUNT(*) as count FROM commande GROUP BY statut";
$result_stats = $conn->query($query_stats);
if ($result_stats) {
    while ($row = $result_stats->fetch_assoc()) {
        $stats['total'] += $row['count'];
        switch ($row['statut']) {
            case 'en attente': $stats['en_attente'] = $row['count']; break;
            case 'confirmé': $stats['confirmees'] = $row['count']; break;
            case 'en livraison': $stats['en_livraison'] = $row['count']; break;
            case 'livré': $stats['livrees'] = $row['count']; break;
            case 'annulé': $stats['annulees'] = $row['count']; break;
        }
    }
}

// Récupération des livreurs pour l'assignation
$query_livreurs = "SELECT id, nom, utilisateur FROM utilisateur WHERE type = 'livreur'";
$livreurs = $conn->query($query_livreurs);

// Filtre pour les commandes
$where_clauses = [];
$params = [];
$types = "";

// Filtrage par statut
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $where_clauses[] = "c.statut = ?";
    $params[] = $_GET['status'];
    $types .= "s";
}

// Filtrage par date
if (isset($_GET['date_start']) && !empty($_GET['date_start'])) {
    $where_clauses[] = "c.date_commande >= ?";
    $params[] = $_GET['date_start'] . " 00:00:00";
    $types .= "s";
}

if (isset($_GET['date_end']) && !empty($_GET['date_end'])) {
    $where_clauses[] = "c.date_commande <= ?";
    $params[] = $_GET['date_end'] . " 23:59:59";
    $types .= "s";
}

// Filtrage par client
if (isset($_GET['client']) && !empty($_GET['client'])) {
    $where_clauses[] = "(u.nom LIKE ? OR u.email LIKE ?)";
    $search_term = "%" . $_GET['client'] . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

// Construction de la requête avec les filtres
$query = "SELECT c.*, u.nom as client_nom, u.email as client_email, u.telephone, 
          l.nom as livreur_nom, l.utilisateur as livreur_username
          FROM commande c 
          LEFT JOIN utilisateur u ON c.utilisateur_id = u.id
          LEFT JOIN utilisateur l ON c.livreur_id = l.id";

if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}

$query .= " ORDER BY c.date_commande DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
$result = $conn->query($query);
}

// Fonction pour déterminer la couleur de badge selon le statut
function getStatusColor($status) {
    switch (strtolower($status)) {
        case 'en attente': return 'warning';
        case 'confirmé': return 'info';
        case 'en livraison': return 'primary';
        case 'livré': return 'success';
        case 'annulé': return 'danger';
        default: return 'secondary';
    }
}

// Fonction pour formater le statut en français
function formatStatus($status) {
    switch (strtolower($status)) {
        case 'en attente': return 'En attente';
        case 'confirmé': return 'Confirmé';
        case 'en livraison': return 'En livraison';
        case 'livré': return 'Livré';
        case 'annulé': return 'Annulé';
        default: return ucfirst($status);
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Commandes - AMAF-SHOP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
        
        .table {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .table th {
            background-color: var(--dark);
            color: white;
            border: none;
            padding: 15px;
            white-space: nowrap;
        }
        
        .table td {
            padding: 15px;
            vertical-align: middle;
        }
        
        .table tr {
            transition: var(--transition);
        }
        
        .table tr:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        .status-badge {
            padding: 8px 12px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.8rem;
            min-width: 120px;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        
        .status-badge i {
            margin-right: 5px;
        }
        
        .status-badge.bg-warning {
            background-color: rgba(243, 156, 18, 0.2) !important;
            color: #f39c12;
        }
        
        .status-badge.bg-info {
            background-color: rgba(52, 152, 219, 0.2) !important;
            color: #3498db;
        }
        
        .status-badge.bg-primary {
            background-color: rgba(58, 123, 213, 0.2) !important;
            color: #3a7bd5;
        }
        
        .status-badge.bg-success {
            background-color: rgba(46, 204, 113, 0.2) !important;
            color: #2ecc71;
        }
        
        .status-badge.bg-danger {
            background-color: rgba(231, 76, 60, 0.2) !important;
            color: #e74c3c;
        }
        
        .status-select {
            display: inline-block;
            position: relative;
            width: 100%;
            max-width: 200px;
        }
        
        .status-select select {
            appearance: none;
            -webkit-appearance: none;
            width: 100%;
            padding: 10px 15px;
            padding-right: 30px;
            border-radius: 50px;
            border: 1px solid #eee;
            background-color: white;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }
        
        .status-select select:focus {
            box-shadow: 0 0 0 0.25rem rgba(58, 123, 213, 0.25);
            border-color: #5a95e5;
            outline: none;
        }
        
        .status-select::after {
            content: '\f078';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: #3a7bd5;
        }
        
        .btn-action {
            border-radius: 50px;
            padding: 10px 20px;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: var(--shadow);
        }
        
        .btn-primary {
            background: var(--gradient);
            border: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(58, 123, 213, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #495057);
            border: none;
        }
        
        .btn-sm {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            padding: 0;
            margin: 0 5px;
            transition: var(--transition);
        }
        
        .btn-sm:hover {
            transform: translateY(-3px);
        }
        
        .alert {
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 20px;
            animation: fadeInDown 0.5s ease;
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
            min-height: 120px;
            transform: translateY(0);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
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
        
        .stat-card.bg-danger {
            background: linear-gradient(45deg, #e74c3c, #eb6b5e);
        }
        
        .stat-card.bg-purple {
            background: linear-gradient(45deg, #9b59b6, #b07cc6);
        }
        
        .stat-card .icon {
            position: absolute;
            bottom: -15px;
            right: 10px;
            font-size: 4rem;
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
            margin-bottom: 5px;
            font-size: 1rem;
            opacity: 0.8;
        }
        
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0;
        }
        
        .filter-form {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .filter-form:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .filter-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .filter-form .form-control,
        .filter-form .form-select {
            border-radius: 10px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            transition: var(--transition);
        }
        
        .filter-form .form-control:focus,
        .filter-form .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(58, 123, 213, 0.25);
        }
        
        .client-info {
            display: flex;
            flex-direction: column;
        }
        
        .client-name {
            font-weight: 600;
            color: var(--dark);
        }
        
        .client-email {
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .client-phone {
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .price-value {
            font-weight: 700;
            color: var(--primary);
        }
        
        /* Responsive styles */
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
        
        @media (max-width: 768px) {
            .status-select {
                max-width: 150px;
            }
            
            .table-responsive {
                border-radius: 15px;
                overflow: hidden;
            }
        }
        
        /* Animations */
        .fadeInDown {
            animation: fadeInDown 0.5s ease;
        }
        
        .fadeInUp {
            animation: fadeInUp 0.5s ease;
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
        
        /* Custom checkboxes */
        .form-check-input {
            width: 20px;
            height: 20px;
            margin-top: 0;
            vertical-align: middle;
            background-color: #fff;
            border: 1px solid #ddd;
            appearance: none;
            -webkit-appearance: none;
            transition: all 0.3s ease;
        }
        
        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .form-check-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(58, 123, 213, 0.25);
        }
        
        /* Additional custom styles */
        .livreur-select {
            min-width: 150px;
            border-radius: 50px;
            padding: 8px 12px;
            border: 1px solid #ddd;
            background-color: white;
            font-size: 0.9rem;
            transition: var(--transition);
        }
        
        .livreur-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(58, 123, 213, 0.25);
            outline: none;
        }
        
        .order-actions {
            display: flex;
            gap: 5px;
        }
        
        .modal-content {
            border-radius: 15px;
            border: none;
            overflow: hidden;
        }
        
        .modal-header {
            background: var(--gradient);
            color: white;
            border-bottom: none;
        }
        
        .modal-footer {
            border-top: none;
        }
        
        .product-item {
            display: flex;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 10px;
            transition: var(--transition);
            background-color: rgba(0,0,0,0.02);
        }
        
        .product-item:hover {
            background-color: rgba(0,0,0,0.05);
        }
        
        .product-name {
            font-weight: 600;
            flex-grow: 1;
        }
        
        .product-quantity {
            font-weight: 600;
            color: var(--primary);
            margin-right: 10px;
        }
        
        .product-price {
            font-weight: 600;
            color: var(--dark);
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
                    <a href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Tableau de bord</span>
                    </a>
                </li>
                <li>
                    <a href="../supprime_produit.php">
                        <i class="fas fa-box"></i>
                        <span>Produits</span>
                    </a>
                </li>
                <li>
                    <a href="commandes.php" class="active">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Commandes</span>
                        <span class="badge bg-primary rounded-pill"><?= $stats['total'] ?></span>
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
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-shopping-cart me-2"></i>Gestion des Commandes</h1>
                    <p class="mb-0">Consultez et gérez toutes les commandes de votre boutique</p>
                </div>
                <div>
                    <button class="btn btn-light btn-action animate__animated animate__pulse animate__infinite animate__slower" onclick="exportCSV()">
                <i class="fas fa-download me-2"></i>Exporter CSV
            </button>
                </div>
            </div>
        </div>

        <!-- Notifications -->
        <?php if (isset($message) && !empty($message)): ?>
            <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeInDown" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error) && !empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show animate__animated animate__fadeInDown" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row animate__animated animate__fadeInUp">
            <div class="col-md-4 col-lg-2">
                <div class="stat-card bg-primary">
                    <div class="stat-title">Total commandes</div>
                    <div class="stat-value"><?= $stats['total'] ?></div>
                    <div class="icon"><i class="fas fa-shopping-cart"></i></div>
                </div>
            </div>
            <div class="col-md-4 col-lg-2">
                <div class="stat-card bg-warning">
                    <div class="stat-title">En attente</div>
                    <div class="stat-value"><?= $stats['en_attente'] ?></div>
                    <div class="icon"><i class="fas fa-clock"></i></div>
                </div>
            </div>
            <div class="col-md-4 col-lg-2">
                <div class="stat-card bg-info">
                    <div class="stat-title">Confirmées</div>
                    <div class="stat-value"><?= $stats['confirmees'] ?></div>
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>
            <div class="col-md-4 col-lg-2">
                <div class="stat-card bg-purple">
                    <div class="stat-title">En livraison</div>
                    <div class="stat-value"><?= $stats['en_livraison'] ?></div>
                    <div class="icon"><i class="fas fa-truck"></i></div>
                </div>
            </div>
            <div class="col-md-4 col-lg-2">
                <div class="stat-card bg-success">
                    <div class="stat-title">Livrées</div>
                    <div class="stat-value"><?= $stats['livrees'] ?></div>
                    <div class="icon"><i class="fas fa-check-double"></i></div>
                </div>
            </div>
            <div class="col-md-4 col-lg-2">
                <div class="stat-card bg-danger">
                    <div class="stat-title">Annulées</div>
                    <div class="stat-value"><?= $stats['annulees'] ?></div>
                    <div class="icon"><i class="fas fa-times-circle"></i></div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filter-form animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
            <div class="filter-title"><i class="fas fa-filter me-2"></i>Filtrer les commandes</div>
            <form method="GET" class="row">
                <div class="col-md-3 mb-3">
                    <label for="status" class="form-label">Statut</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Tous les statuts</option>
                        <option value="en attente" <?= isset($_GET['status']) && $_GET['status'] === 'en attente' ? 'selected' : '' ?>>En attente</option>
                        <option value="confirmé" <?= isset($_GET['status']) && $_GET['status'] === 'confirmé' ? 'selected' : '' ?>>Confirmé</option>
                        <option value="en livraison" <?= isset($_GET['status']) && $_GET['status'] === 'en livraison' ? 'selected' : '' ?>>En livraison</option>
                        <option value="livré" <?= isset($_GET['status']) && $_GET['status'] === 'livré' ? 'selected' : '' ?>>Livré</option>
                        <option value="annulé" <?= isset($_GET['status']) && $_GET['status'] === 'annulé' ? 'selected' : '' ?>>Annulé</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="date_start" class="form-label">Date début</label>
                    <input type="date" class="form-control datepicker" id="date_start" name="date_start" value="<?= $_GET['date_start'] ?? '' ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="date_end" class="form-label">Date fin</label>
                    <input type="date" class="form-control datepicker" id="date_end" name="date_end" value="<?= $_GET['date_end'] ?? '' ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="client" class="form-label">Client</label>
                    <input type="text" class="form-control" id="client" name="client" placeholder="Nom ou email" value="<?= $_GET['client'] ?? '' ?>">
                </div>
                <div class="col-12 text-end">
                    <button type="button" class="btn btn-secondary btn-action me-2" onclick="resetFilters()">
                        <i class="fas fa-undo me-2"></i>Réinitialiser
                    </button>
                    <button type="submit" class="btn btn-primary btn-action">
                        <i class="fas fa-search me-2"></i>Rechercher
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Orders Table -->
        <div class="card animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
            <div class="card-header">
                <i class="fas fa-list me-2"></i>Liste des commandes
                <span class="badge bg-primary rounded-pill ms-2"><?= $result->num_rows ?? 0 ?></span>
            </div>
            <div class="card-body">
                <?php if ($result->num_rows === 0): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">Aucune commande trouvée</h4>
                        <p class="text-muted">Modifiez vos critères de recherche ou attendez de nouvelles commandes.</p>
                        <button type="button" class="btn btn-outline-primary mt-3" onclick="resetFilters()">
                            <i class="fas fa-undo me-2"></i>Réinitialiser les filtres
                        </button>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Client</th>
                                <th>Total</th>
                                <th>Date</th>
                                <th>Statut</th>
                                <th>Livreur</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($commande = $result->fetch_assoc()): ?>
                                    <tr class="order-row animate__animated animate__fadeIn" 
                                        data-id="<?= $commande['id'] ?>" 
                                        style="animation-delay: <?= $delay = ($delay ?? 0) + 0.05 ?>s">
                                        <td>
                                            <span class="fw-bold">#<?= $commande['id'] ?></span>
                                        </td>
                                        <td>
                                            <div class="client-info">
                                                <span class="client-name"><?= htmlspecialchars($commande['utilisateur_id']) ?></span>
                                                <!-- <span class="client-email"><?= htmlspecialchars($commande['client_email']) ?></span> -->
                                                <?php if ($commande['telephone']): ?>
                                                    <span class="client-phone">
                                                        <i class="fas fa-phone-alt me-1 text-muted"></i><?= htmlspecialchars($commande['telephone']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="price-value"><?= number_format($commande['total'], 0) ?> CFA</span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="fw-bold"><?= date('d/m/Y', strtotime($commande['date_commande'])) ?></span>
                                                <small class="text-muted"><?= date('H:i', strtotime($commande['date_commande'])) ?></small>
                                            </div>
                                    </td>
                                    <td>
                                            <div class="status-select">
                                                <form method="POST" class="status-form">
                                            <input type="hidden" name="commande_id" value="<?= $commande['id'] ?>">
                                                    <select name="statut" class="status-select-input" 
                                                            data-order-id="<?= $commande['id'] ?>" 
                                                            style="border-color: var(--<?= getStatusColor($commande['statut']) ?>);">
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
                                            </div>
                                    </td>
                                    <td>
                                            <?php if ($commande['statut'] === 'en livraison' || $commande['statut'] === 'livré'): ?>
                                                <form method="POST" class="livreur-form">
                                                <input type="hidden" name="commande_id" value="<?= $commande['id'] ?>">
                                                    <input type="hidden" name="statut" value="<?= $commande['statut'] ?>">
                                                    <select name="livreur_id" class="livreur-select" 
                                                            data-order-id="<?= $commande['id'] ?>"
                                                            <?= $commande['statut'] === 'livré' ? 'disabled' : '' ?>>
                                                        <option value="">Sélectionner</option>
                                                    <?php 
                                                        if ($livreurs) {
                                                    $livreurs->data_seek(0);
                                                    while ($livreur = $livreurs->fetch_assoc()): 
                                                    ?>
                                                        <option value="<?= $livreur['id'] ?>" 
                                                                <?= $commande['livreur_id'] == $livreur['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($livreur['utilisateur']) ?>
                                                        </option>
                                                        <?php 
                                                            endwhile;
                                                        }
                                                        ?>
                                                </select>
                                            </form>
                                        <?php else: ?>
                                                <span class="text-muted">
                                                    <?= $commande['livreur_username'] ? htmlspecialchars($commande['livreur_username']) : '-' ?>
                                                </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                            <div class="order-actions">
                                                <button class="btn btn-primary btn-sm" 
                                                        onclick="viewDetails(<?= $commande['id'] ?>)" 
                                                        title="Détails">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                                <button class="btn btn-info btn-sm" 
                                                        onclick="printInvoice(<?= $commande['id'] ?>)" 
                                                        title="Facture">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm" 
                                                        onclick="deleteOrder(<?= $commande['id'] ?>)" 
                                                        title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                            </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Order Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content animate__animated animate__fadeInDown">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-info-circle me-2"></i>Détails de la commande <span id="orderIdDisplay"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="orderDetailsContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-3">Chargement des détails...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Fermer
                    </button>
                    <button type="button" class="btn btn-primary" id="printButton">
                        <i class="fas fa-print me-2"></i>Imprimer
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content animate__animated animate__fadeInDown">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirmer la suppression
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer la commande <strong>#<span id="deleteOrderId"></span></strong> ?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Attention :</strong> Cette action est irréversible. Toutes les données liées à cette commande seront supprimées.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Annuler
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="fas fa-trash me-2"></i>Supprimer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize date pickers
            flatpickr(".datepicker", {
                dateFormat: "Y-m-d",
                allowInput: true
            });
            
            // Handle status change
            document.querySelectorAll('.status-select-input').forEach(select => {
                select.addEventListener('change', function() {
                    const orderRow = this.closest('.order-row');
                    orderRow.classList.add('animate__animated', 'animate__pulse');
                    
                    // If status is "en livraison", check if a livreur is selected
                    if (this.value === 'en livraison') {
                        const livreurSelect = orderRow.querySelector('.livreur-select');
                        if (livreurSelect && (!livreurSelect.value || livreurSelect.value === '')) {
                            // Focus on livreur select
                            livreurSelect.focus();
                            livreurSelect.style.borderColor = 'var(--danger)';
                            livreurSelect.classList.add('animate__animated', 'animate__headShake');
                            
                            setTimeout(() => {
                                livreurSelect.classList.remove('animate__animated', 'animate__headShake');
                                livreurSelect.style.borderColor = '';
                            }, 1000);
                            
                            return false;
                        }
                    }
                    
                    // Submit the form
                    this.closest('form').submit();
                });
            });
            
            // Handle livreur change
            document.querySelectorAll('.livreur-select').forEach(select => {
                select.addEventListener('change', function() {
                    const orderRow = this.closest('.order-row');
                    orderRow.classList.add('animate__animated', 'animate__pulse');
                    
                    // Submit the form
                    this.closest('form').submit();
                });
            });
            
            // Animation for the rows
            document.querySelectorAll('.order-row').forEach((row, index) => {
                setTimeout(() => {
                    row.classList.add('visible');
                }, 100 * index);
            });
            
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        
        // View order details
        function viewDetails(orderId) {
            const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
            document.getElementById('orderIdDisplay').textContent = '#' + orderId;
            
            // Show the modal and load details
            modal.show();
            
            // Load order details via AJAX
            fetch('get_order_details.php?id=' + orderId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('orderDetailsContent').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('orderDetailsContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Erreur lors du chargement des détails: ${error.message}
                        </div>
                    `;
                });
                
            // Print functionality
            document.getElementById('printButton').onclick = function() {
                window.open('print_order.php?id=' + orderId, '_blank');
            };
        }
        
        // Delete order
        function deleteOrder(orderId) {
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
            document.getElementById('deleteOrderId').textContent = orderId;
            
            // Show confirmation modal
            deleteModal.show();
            
            // Set up confirm button
            document.getElementById('confirmDeleteBtn').onclick = function() {
                // Make deletion AJAX request
                fetch('delete_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'order_id=' + orderId
                })
                .then(response => response.json())
                .then(data => {
                    deleteModal.hide();
                    
                    if (data.success) {
                        // Animate row removal
                        const row = document.querySelector(`.order-row[data-id="${orderId}"]`);
                        row.classList.add('animate__animated', 'animate__fadeOutRight');
                        
                        setTimeout(() => {
                            row.remove();
                            
                            // Show success message
                            const alertDiv = document.createElement('div');
                            alertDiv.className = 'alert alert-success alert-dismissible fade show animate__animated animate__fadeInDown';
                            alertDiv.innerHTML = `
                                <i class="fas fa-check-circle me-2"></i>
                                La commande #${orderId} a été supprimée avec succès.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            `;
                            
                            // Prepend to the content area
                            document.querySelector('.main-content').insertBefore(
                                alertDiv, 
                                document.querySelector('.main-content').firstChild
                            );
                            
                            // Update stats
                            updateOrderStats();
                            
                            // Remove alert after 5 seconds
                            setTimeout(() => {
                                const bsAlert = new bootstrap.Alert(alertDiv);
                                bsAlert.close();
                            }, 5000);
                        }, 500);
                    } else {
                        // Show error message
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-danger alert-dismissible fade show animate__animated animate__fadeInDown';
                        alertDiv.innerHTML = `
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Erreur lors de la suppression: ${data.message || 'Une erreur est survenue.'}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;
                        
                        // Prepend to the content area
                        document.querySelector('.main-content').insertBefore(
                            alertDiv, 
                            document.querySelector('.main-content').firstChild
                        );
                        
                        // Remove alert after 5 seconds
                        setTimeout(() => {
                            const bsAlert = new bootstrap.Alert(alertDiv);
                            bsAlert.close();
                        }, 5000);
                    }
                });
            };
        }
        
        // Print invoice
        function printInvoice(orderId) {
            window.open('print_invoice.php?id=' + orderId, '_blank');
        }
        
        // Export to CSV
        function exportCSV() {
            // Get current filter parameters
            const urlParams = new URLSearchParams(window.location.search);
            let exportUrl = 'export_orders.php';
            
            // Add filters to export URL
            if (urlParams.toString()) {
                exportUrl += '?' + urlParams.toString();
            }
            
            window.location.href = exportUrl;
        }
        
        // Reset filters
        function resetFilters() {
            window.location.href = 'commandes.php';
        }
        
        // Update order statistics
        function updateOrderStats() {
            fetch('get_order_stats.php')
                .then(response => response.json())
                .then(data => {
                    document.querySelector('.stat-card:nth-child(1) .stat-value').textContent = data.total;
                    document.querySelector('.stat-card:nth-child(2) .stat-value').textContent = data.en_attente;
                    document.querySelector('.stat-card:nth-child(3) .stat-value').textContent = data.confirmees;
                    document.querySelector('.stat-card:nth-child(4) .stat-value').textContent = data.en_livraison;
                    document.querySelector('.stat-card:nth-child(5) .stat-value').textContent = data.livrees;
                    document.querySelector('.stat-card:nth-child(6) .stat-value').textContent = data.annulees;
                });
        }
    </script>
</body>
</html> 