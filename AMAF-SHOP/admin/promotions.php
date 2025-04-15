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

// Traitement du formulaire d'ajout/modification de promotion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Récupération des données du formulaire
        $categorie_id = intval($_POST['categorie_id'] ?? 0);
        $type_reduction = $_POST['type_reduction'] ?? 'montant';
        $valeur_reduction = floatval($_POST['valeur_reduction'] ?? 0);
        $date_debut = $_POST['date_debut'] ?? date('Y-m-d');
        $date_fin = $_POST['date_fin'] ?? date('Y-m-d', strtotime('+30 days'));
        $actif = isset($_POST['actif']) ? 1 : 0;
        $nom_promotion = trim($_POST['nom_promotion'] ?? '');
        
        // Validation des données
        if ($categorie_id <= 0 || $valeur_reduction <= 0 || empty($nom_promotion)) {
            $error = "Veuillez remplir tous les champs obligatoires correctement.";
        } else {
            // Ajout/modification d'une promotion
            if ($_POST['action'] === 'ajouter') {
                // Vérifier si une promotion active existe déjà pour cette catégorie
                $query_check = "SELECT id FROM promotion WHERE categorie_id = ? AND actif = 1 AND date_fin >= CURDATE()";
                $stmt_check = $conn->prepare($query_check);
                $stmt_check->bind_param("i", $categorie_id);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                
                if ($result_check->num_rows > 0 && $actif) {
                    $error = "Une promotion active existe déjà pour cette catégorie. Veuillez la désactiver avant d'en créer une nouvelle.";
                } else {
                    // Insertion d'une nouvelle promotion
                    $query = "INSERT INTO promotion (nom, categorie_id, type_reduction, valeur_reduction, date_debut, date_fin, actif) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("sisdssi", $nom_promotion, $categorie_id, $type_reduction, $valeur_reduction, $date_debut, $date_fin, $actif);
                    
                    if ($stmt->execute()) {
                        $promotion_id = $stmt->insert_id;
                        
                        // Si active, appliquer la promotion à tous les produits de la catégorie
                        if ($actif) {
                            appliquerPromotionCategorie($conn, $categorie_id, $type_reduction, $valeur_reduction);
                            $message = "La promotion a été créée et appliquée avec succès !";
                        } else {
                            $message = "La promotion a été créée avec succès mais n'est pas encore active.";
                        }
                    } else {
                        $error = "Erreur lors de la création de la promotion : " . $conn->error;
                    }
                }
            } elseif ($_POST['action'] === 'modifier' && isset($_POST['promotion_id'])) {
                $promotion_id = intval($_POST['promotion_id']);
                
                // Récupérer l'état actuel de la promotion
                $query_current = "SELECT actif, categorie_id FROM promotion WHERE id = ?";
                $stmt_current = $conn->prepare($query_current);
                $stmt_current->bind_param("i", $promotion_id);
                $stmt_current->execute();
                $result_current = $stmt_current->get_result();
                $promotion_current = $result_current->fetch_assoc();
                
                if ($promotion_current) {
                    // Mise à jour de la promotion
                    $query = "UPDATE promotion SET nom = ?, categorie_id = ?, type_reduction = ?, valeur_reduction = ?, 
                              date_debut = ?, date_fin = ?, actif = ? WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("sissssii", $nom_promotion, $categorie_id, $type_reduction, $valeur_reduction, 
                                   $date_debut, $date_fin, $actif, $promotion_id);
                    
                    if ($stmt->execute()) {
                        // Si le statut a changé de inactif à actif ou si la catégorie a changé
                        if (($actif && !$promotion_current['actif']) || $categorie_id != $promotion_current['categorie_id']) {
                            // Réinitialiser toutes les promotions pour l'ancienne catégorie si elle a changé
                            if ($categorie_id != $promotion_current['categorie_id'] && $promotion_current['actif']) {
                                reinitialiserPromotionsCategorie($conn, $promotion_current['categorie_id']);
                            }
                            
                            // Appliquer la promotion à la nouvelle catégorie
                            if ($actif) {
                                appliquerPromotionCategorie($conn, $categorie_id, $type_reduction, $valeur_reduction);
                                $message = "La promotion a été mise à jour et appliquée avec succès !";
                            } else {
                                $message = "La promotion a été mise à jour avec succès.";
                            }
                        } elseif (!$actif && $promotion_current['actif']) {
                            // Si la promotion est passée d'active à inactive
                            reinitialiserPromotionsCategorie($conn, $categorie_id);
                            $message = "La promotion a été désactivée et les produits ont été mis à jour.";
                        } else {
                            // Mise à jour uniquement des valeurs de réduction si la promotion est active
                            if ($actif) {
                                appliquerPromotionCategorie($conn, $categorie_id, $type_reduction, $valeur_reduction);
                            }
                            $message = "La promotion a été mise à jour avec succès.";
                        }
                    } else {
                        $error = "Erreur lors de la mise à jour de la promotion : " . $conn->error;
                    }
                } else {
                    $error = "Promotion non trouvée.";
                }
            }
        }
    } elseif (isset($_POST['supprimer']) && isset($_POST['promotion_id'])) {
        // Suppression d'une promotion
        $promotion_id = intval($_POST['promotion_id']);
        
        // Récupérer la catégorie associée à la promotion
        $query_cat = "SELECT categorie_id, actif FROM promotion WHERE id = ?";
        $stmt_cat = $conn->prepare($query_cat);
        $stmt_cat->bind_param("i", $promotion_id);
        $stmt_cat->execute();
        $result_cat = $stmt_cat->get_result();
        $promotion_info = $result_cat->fetch_assoc();
        
        if ($promotion_info) {
            // Supprimer la promotion
            $query = "DELETE FROM promotion WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $promotion_id);
            
            if ($stmt->execute()) {
                // Si la promotion était active, réinitialiser les produits de la catégorie
                if ($promotion_info['actif']) {
                    reinitialiserPromotionsCategorie($conn, $promotion_info['categorie_id']);
                }
                $message = "La promotion a été supprimée avec succès.";
            } else {
                $error = "Erreur lors de la suppression de la promotion : " . $conn->error;
            }
        } else {
            $error = "Promotion non trouvée.";
        }
    }
}

// Fonction pour appliquer une promotion à tous les produits d'une catégorie
function appliquerPromotionCategorie($conn, $categorie_id, $type_reduction, $valeur_reduction) {
    // Désactiver toutes les autres promotions pour cette catégorie
    $query_desactiver = "UPDATE promotion SET actif = 0 WHERE categorie_id = ? AND actif = 1 AND id != (SELECT id FROM (SELECT MAX(id) as id FROM promotion WHERE categorie_id = ? AND actif = 1) as temp)";
    $stmt_desactiver = $conn->prepare($query_desactiver);
    $stmt_desactiver->bind_param("ii", $categorie_id, $categorie_id);
    $stmt_desactiver->execute();
    
    // Récupérer tous les produits de la catégorie
    $query_produits = "SELECT id, prix FROM produit WHERE categorie_id = ?";
    $stmt_produits = $conn->prepare($query_produits);
    $stmt_produits->bind_param("i", $categorie_id);
    $stmt_produits->execute();
    $result_produits = $stmt_produits->get_result();
    
    // Pour chaque produit, calculer et appliquer la promotion
    while ($produit = $result_produits->fetch_assoc()) {
        $prix_original = $produit['prix'];
        $montant_reduction = 0;
        
        if ($type_reduction === 'pourcentage') {
            $montant_reduction = $prix_original * ($valeur_reduction / 100);
        } else {
            $montant_reduction = $valeur_reduction;
        }
        
        // S'assurer que la réduction ne dépasse pas le prix du produit
        $montant_reduction = min($montant_reduction, $prix_original - 1); // Garder au moins 1 unité de prix
        
        // Mettre à jour le produit avec la promotion
        $query_update = "UPDATE produit SET promotion = ? WHERE id = ?";
        $stmt_update = $conn->prepare($query_update);
        $stmt_update->bind_param("di", $montant_reduction, $produit['id']);
        $stmt_update->execute();
    }
}

// Fonction pour réinitialiser les promotions des produits d'une catégorie
function reinitialiserPromotionsCategorie($conn, $categorie_id) {
    $query = "UPDATE produit SET promotion = NULL WHERE categorie_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $categorie_id);
    $stmt->execute();
}

// Récupérer toutes les promotions
$promotions = [];
$query_promotions = "SELECT p.*, c.nom as categorie_nom 
                   FROM promotion p 
                   LEFT JOIN categorie c ON p.categorie_id = c.id 
                   ORDER BY p.actif DESC, p.date_fin DESC";
$result_promotions = $conn->query($query_promotions);
if ($result_promotions) {
    while ($row = $result_promotions->fetch_assoc()) {
        $promotions[] = $row;
    }
}

// Récupérer les catégories pour le formulaire
$categories = [];
$query_categories = "SELECT id, nom FROM categorie ORDER BY nom";
$result_categories = $conn->query($query_categories);
if ($result_categories) {
    while ($row = $result_categories->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Récupérer une promotion spécifique pour l'édition
$promotion_edit = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $promotion_id = intval($_GET['edit']);
    $query_edit = "SELECT * FROM promotion WHERE id = ?";
    $stmt_edit = $conn->prepare($query_edit);
    $stmt_edit->bind_param("i", $promotion_id);
    $stmt_edit->execute();
    $result_edit = $stmt_edit->get_result();
    $promotion_edit = $result_edit->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Promotions - AMAF-SHOP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
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
        
        .badge {
            padding: 7px 12px;
            border-radius: 50px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        
        .promotion-status {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        
        .promotion-status.active {
            background-color: var(--success);
        }
        
        .promotion-status.inactive {
            background-color: var(--gray);
        }
        
        .promotion-status.expired {
            background-color: var(--danger);
        }
        
        .promotion-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
        }
        
        .required-field::after {
            content: '*';
            color: var(--danger);
            margin-left: 4px;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            transition: var(--transition);
            box-shadow: none;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(58, 123, 213, 0.25);
        }
        
        .form-switch .form-check-input {
            width: 3em;
            height: 1.5em;
            margin-top: 0.25em;
        }
        
        .form-check-input:checked {
            background-color: var(--success);
            border-color: var(--success);
        }
        
        .form-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-light);
            color: var(--dark);
        }
        
        /* Responsive */
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
                    <a href="commandes.php">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Commandes</span>
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
                    <a href="promotions.php" class="active">
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
            <h1><i class="fas fa-percent me-2"></i>Gestion des Promotions</h1>
            <p>Créez et gérez des promotions par catégorie de produits pour votre boutique.</p>
        </div>
        
        <!-- Notifications -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeInDown" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show animate__animated animate__fadeInDown" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Formulaire de promotion -->
            <div class="col-lg-4">
                <div class="card animate__animated animate__fadeInUp">
                    <div class="card-header">
                        <i class="fas fa-<?= $promotion_edit ? 'edit' : 'plus' ?> me-2"></i>
                        <?= $promotion_edit ? 'Modifier la promotion' : 'Créer une nouvelle promotion' ?>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="action" value="<?= $promotion_edit ? 'modifier' : 'ajouter' ?>">
                            <?php if ($promotion_edit): ?>
                                <input type="hidden" name="promotion_id" value="<?= $promotion_edit['id'] ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="nom_promotion" class="form-label required-field">Nom de la promotion</label>
                                <input type="text" class="form-control" id="nom_promotion" name="nom_promotion" 
                                       value="<?= htmlspecialchars($promotion_edit['nom'] ?? '') ?>" required>
                                <div class="invalid-feedback">Veuillez entrer un nom pour la promotion.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="categorie_id" class="form-label required-field">Catégorie</label>
                                <select class="form-select" id="categorie_id" name="categorie_id" required>
                                    <option value="">Sélectionner une catégorie</option>
                                    <?php foreach ($categories as $categorie): ?>
                                        <option value="<?= $categorie['id'] ?>" <?= isset($promotion_edit['categorie_id']) && $promotion_edit['categorie_id'] == $categorie['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($categorie['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner une catégorie.</div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="type_reduction" class="form-label required-field">Type de réduction</label>
                                    <select class="form-select" id="type_reduction" name="type_reduction" required>
                                        <option value="montant" <?= isset($promotion_edit['type_reduction']) && $promotion_edit['type_reduction'] == 'montant' ? 'selected' : '' ?>>
                                            Montant fixe
                                        </option>
                                        <option value="pourcentage" <?= isset($promotion_edit['type_reduction']) && $promotion_edit['type_reduction'] == 'pourcentage' ? 'selected' : '' ?>>
                                            Pourcentage
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="valeur_reduction" class="form-label required-field">Valeur</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="valeur_reduction" name="valeur_reduction" 
                                               value="<?= $promotion_edit['valeur_reduction'] ?? '' ?>" step="0.01" min="0" required>
                                        <span class="input-group-text" id="reduction-suffix">CFA</span>
                                    </div>
                                    <div class="invalid-feedback">Veuillez entrer une valeur valide.</div>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="date_debut" class="form-label">Date de début</label>
                                    <input type="date" class="form-control" id="date_debut" name="date_debut" 
                                           value="<?= $promotion_edit['date_debut'] ?? date('Y-m-d') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="date_fin" class="form-label">Date de fin</label>
                                    <input type="date" class="form-control" id="date_fin" name="date_fin" 
                                           value="<?= $promotion_edit['date_fin'] ?? date('Y-m-d', strtotime('+30 days')) ?>">
                                </div>
                            </div>
                            
                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" id="actif" name="actif" 
                                       <?= isset($promotion_edit['actif']) && $promotion_edit['actif'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="actif">Activer la promotion</label>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <small>Les promotions actives sont automatiquement appliquées à tous les produits de la catégorie sélectionnée.</small>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-action">
                                    <i class="fas fa-<?= $promotion_edit ? 'save' : 'plus-circle' ?> me-2"></i>
                                    <?= $promotion_edit ? 'Enregistrer les modifications' : 'Créer la promotion' ?>
                                </button>
                                <?php if ($promotion_edit): ?>
                                    <a href="promotions.php" class="btn btn-secondary btn-action">
                                        <i class="fas fa-times me-2"></i>Annuler
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Liste des promotions -->
            <div class="col-lg-8">
                <div class="card animate__animated animate__fadeInUp">
                    <div class="card-header">
                        <i class="fas fa-list me-2"></i>Promotions existantes
                    </div>
                    <div class="card-body">
                        <?php if (empty($promotions)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-percent fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Aucune promotion n'a été créée.</p>
                                <p class="text-muted">Utilisez le formulaire pour créer votre première promotion.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Nom</th>
                                            <th>Catégorie</th>
                                            <th>Réduction</th>
                                            <th>Période</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($promotions as $promotion): ?>
                                            <?php 
                                                $statut = 'inactive';
                                                $statut_text = 'Inactive';
                                                $statut_badge = 'secondary';
                                                
                                                if ($promotion['actif']) {
                                                    $date_aujourdhui = date('Y-m-d');
                                                    if ($date_aujourdhui < $promotion['date_debut']) {
                                                        $statut = 'pending';
                                                        $statut_text = 'À venir';
                                                        $statut_badge = 'info';                                            
                                                    } elseif ($date_aujourdhui > $promotion['date_fin']) {
                                                        $statut = 'expired';
                                                        $statut_text = 'Expirée';
                                                        $statut_badge = 'danger';
                                                    } else {
                                                        $statut = 'active';
                                                        $statut_text = 'Active';
                                                        $statut_badge = 'success';
                                                    }
                                                }
                                            ?>
                                            <tr>
                                                <td><?= htmlspecialchars($promotion['nom']) ?></td>
                                                <td>
                                                    <?php if ($promotion['categorie_nom']): ?>
                                                        <span class="badge bg-info">
                                                            <?= htmlspecialchars($promotion['categorie_nom']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Catégorie supprimée</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($promotion['type_reduction'] === 'pourcentage'): ?>
                                                        <span class="promotion-badge bg-warning text-dark">
                                                            -<?= number_format($promotion['valeur_reduction'], 0) ?>%
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="promotion-badge bg-primary text-white">
                                                            -<?= number_format($promotion['valeur_reduction'], 0) ?> CFA
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small>
                                                        Du <?= date('d/m/Y', strtotime($promotion['date_debut'])) ?>
                                                        <br>
                                                        Au <?= date('d/m/Y', strtotime($promotion['date_fin'])) ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="promotion-status <?= $statut ?>"></span>
                                                    <span class="badge bg-<?= $statut_badge ?>"><?= $statut_text ?></span>
                                                </td>
                                                <td>
                                                    <div class="d-flex">
                                                        <a href="?edit=<?= $promotion['id'] ?>" class="btn btn-primary btn-sm" title="Modifier">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-danger btn-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#deleteModal<?= $promotion['id'] ?>" 
                                                                title="Supprimer">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- Modal de confirmation de suppression -->
                                                    <div class="modal fade" id="deleteModal<?= $promotion['id'] ?>" tabindex="-1">
                                                        <div class="modal-dialog modal-dialog-centered">
                                                            <div class="modal-content">
                                                                <div class="modal-header bg-danger text-white">
                                                                    <h5 class="modal-title">
                                                                        <i class="fas fa-exclamation-triangle me-2"></i>Confirmer la suppression
                                                                    </h5>
                                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <p>Êtes-vous sûr de vouloir supprimer la promotion <strong>"<?= htmlspecialchars($promotion['nom']) ?>"</strong> ?</p>
                                                                    <?php if ($promotion['actif']): ?>
                                                                        <div class="alert alert-warning">
                                                                            <i class="fas fa-exclamation-circle me-2"></i>
                                                                            <strong>Attention :</strong> Cette promotion est actuellement active. La supprimer annulera les réductions sur tous les produits de la catégorie <?= htmlspecialchars($promotion['categorie_nom']) ?>.
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                                        <i class="fas fa-times me-2"></i>Annuler
                                                                    </button>
                                                                    <form method="POST">
                                                                        <input type="hidden" name="promotion_id" value="<?= $promotion['id'] ?>">
                                                                        <button type="submit" name="supprimer" class="btn btn-danger">
                                                                            <i class="fas fa-trash me-2"></i>Supprimer
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Guide d'utilisation -->
                <div class="card animate__animated animate__fadeInUp">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-2"></i>Guide d'utilisation des promotions
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="promotionGuide">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingOne">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                                        Comment fonctionnent les promotions par catégorie ?
                                    </button>
                                </h2>
                                <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#promotionGuide">
                                    <div class="accordion-body">
                                        <p>Les promotions par catégorie permettent d'appliquer automatiquement une réduction à tous les produits d'une catégorie spécifique.</p>
                                        <ul>
                                            <li>Une seule promotion peut être active à la fois pour une catégorie donnée.</li>
                                            <li>Lorsque vous activez une promotion, les réductions sont immédiatement appliquées.</li>
                                            <li>Lorsque vous désactivez ou supprimez une promotion, les réductions sont automatiquement annulées.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingTwo">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                                        Types de réductions disponibles
                                    </button>
                                </h2>
                                <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#promotionGuide">
                                    <div class="accordion-body">
                                        <p>Deux types de réductions sont disponibles :</p>
                                        <ol>
                                            <li><strong>Montant fixe :</strong> Réduit le prix de chaque produit du montant spécifié (par ex. -5000 CFA)</li>
                                            <li><strong>Pourcentage :</strong> Réduit le prix de chaque produit d'un pourcentage spécifié (par ex. -20%)</li>
                                        </ol>
                                        <p class="text-muted">Note : Les réductions ne peuvent pas réduire le prix d'un produit à moins de 1 CFA.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingThree">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">
                                        Planification des promotions
                                    </button>
                                </h2>
                                <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#promotionGuide">
                                    <div class="accordion-body">
                                        <p>Vous pouvez planifier vos promotions à l'avance :</p>
                                        <ul>
                                            <li>Définissez les dates de début et de fin pour contrôler la durée de la promotion</li>
                                            <li>Une promotion active mais dont la date de début est dans le futur sera marquée comme "À venir"</li>
                                            <li>Une promotion active mais dont la date de fin est dépassée sera marquée comme "Expirée"</li>
                                        </ul>
                                        <p>Pensez à vérifier régulièrement les statuts de vos promotions pour maintenir votre stratégie commerciale.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mise à jour du suffixe du champ de réduction en fonction du type de réduction
            const typeReductionSelect = document.getElementById('type_reduction');
            const reductionSuffix = document.getElementById('reduction-suffix');
            
            function updateReductionSuffix() {
                if (typeReductionSelect.value === 'pourcentage') {
                    reductionSuffix.textContent = '%';
                } else {
                    reductionSuffix.textContent = 'CFA';
                }
            }
            
            // Initialiser le suffixe au chargement
            updateReductionSuffix();
            
            // Mettre à jour le suffixe lors du changement de type de réduction
            typeReductionSelect.addEventListener('change', updateReductionSuffix);
            
            // Validation du formulaire
            const form = document.querySelector('.needs-validation');
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                form.classList.add('was-validated');
            });
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert:not(.alert-warning)');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
            
            // Animation pour les éléments lors du défilement
            function revealOnScroll() {
                const elements = document.querySelectorAll('.card');
                
                elements.forEach(element => {
                    const elementTop = element.getBoundingClientRect().top;
                    const elementBottom = element.getBoundingClientRect().bottom;
                    const windowHeight = window.innerHeight;
                    
                    if (elementTop < windowHeight && elementBottom > 0 && !element.classList.contains('visible')) {
                        element.classList.add('animate__animated', 'animate__fadeInUp', 'visible');
                    }
                });
            }
            
            // Observer pour animer les éléments lorsqu'ils entrent dans la vue
            const observerOptions = {
                root: null,
                threshold: 0.1,
                rootMargin: "0px"
            };
            
            const observer = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !entry.target.classList.contains('visible')) {
                        entry.target.classList.add('animate__animated', 'animate__fadeInUp', 'visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);
            
            document.querySelectorAll('.card:not(.visible)').forEach(el => {
                observer.observe(el);
            });
            
            // Activer les tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Animations au survol
            document.querySelectorAll('.btn-action').forEach(btn => {
                btn.addEventListener('mouseenter', function() {
                    this.classList.add('animate__animated', 'animate__pulse');
                });
                
                btn.addEventListener('mouseleave', function() {
                    this.classList.remove('animate__animated', 'animate__pulse');
                });
            });
        });
    </script>
</body>
</html> 