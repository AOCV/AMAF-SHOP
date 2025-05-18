<?php
session_start();
require '../config.php';

// Vérifier si l'utilisateur est connecté et est un admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Vérifier et mettre à jour la structure de la table parametres si nécessaire
$required_columns = [
    'nom_site' => "VARCHAR(255) DEFAULT 'AMAF-SHOP'",
    'email_contact' => "VARCHAR(255)",
    'telephone' => "VARCHAR(50)",
    'adresse' => "TEXT",
    'devise' => "VARCHAR(10) DEFAULT 'CFA'",
    'frais_livraison' => "DECIMAL(10,2) DEFAULT 1500.00",
    'seuil_livraison_gratuite' => "DECIMAL(10,2) DEFAULT 25000.00",
    'taxe' => "DECIMAL(5,2) DEFAULT 18.00",
    'logo_url' => "VARCHAR(255)",
    'favicon_url' => "VARCHAR(255)",
    'meta_description' => "TEXT",
    'conditions_vente' => "TEXT",
    'facebook_url' => "VARCHAR(255)",
    'instagram_url' => "VARCHAR(255)",
    'twitter_url' => "VARCHAR(255)",
    'whatsapp_url' => "VARCHAR(255)",
    'youtube_url' => "VARCHAR(255)",
    'maintenance_mode' => "TINYINT(1) DEFAULT 0",
    'products_per_page' => "INT DEFAULT 8",
    'promotions_per_page' => "INT DEFAULT 6",
    'all_products_padding' => "INT DEFAULT 20",
    'all_products_card_height' => "INT DEFAULT 280",
    'promotions_padding' => "INT DEFAULT 40",
    'promotions_card_height' => "INT DEFAULT 320"
];

// Vérifier si la table existe, sinon la créer
$table_exists = $conn->query("SHOW TABLES LIKE 'parametres'")->num_rows > 0;
if (!$table_exists) {
    $create_table_query = "CREATE TABLE parametres (
        id INT AUTO_INCREMENT PRIMARY KEY
    )";
    $conn->query($create_table_query);
}

// Obtenir les colonnes existantes
$existing_columns = [];
$result = $conn->query("SHOW COLUMNS FROM parametres");
while ($row = $result->fetch_assoc()) {
    $existing_columns[] = $row['Field'];
}

// Ajouter les colonnes manquantes
foreach ($required_columns as $column => $definition) {
    if (!in_array($column, $existing_columns)) {
        $alter_query = "ALTER TABLE parametres ADD COLUMN $column $definition";
        $conn->query($alter_query);
    }
}

$message = '';
$error = '';

// Récupérer les paramètres actuels
$query = "SELECT * FROM parametres WHERE id = 1";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    $parametres = $result->fetch_assoc();
} else {
    // Créer les paramètres par défaut si inexistants
    $query_insert = "INSERT INTO parametres (nom_site, email_contact, telephone, adresse, devise, frais_livraison, seuil_livraison_gratuite, taxe, logo_url, favicon_url, meta_description, conditions_vente, facebook_url, instagram_url, twitter_url) 
                     VALUES ('AMAF-SHOP', 'contact@amaf-shop.com', '+1234567890', '123 Rue du Commerce, Ville, Pays', 'CFA', 1500, 25000, 18, '', '', 'Votre boutique en ligne pour des achats de qualité', '', '', '', '')";
    $conn->query($query_insert);
    
    // Récupérer les paramètres nouvellement créés
    $query = "SELECT * FROM parametres WHERE id = 1";
    $result = $conn->query($query);
    $parametres = $result->fetch_assoc();
}

// Traitement du formulaire de mise à jour des paramètres
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_parametres') {
        // Récupération des données du formulaire
        $nom_site = $conn->real_escape_string($_POST['nom_site'] ?? $parametres['nom_site']);
        $email_contact = $conn->real_escape_string($_POST['email_contact'] ?? $parametres['email_contact']);
        $telephone = $conn->real_escape_string($_POST['telephone'] ?? $parametres['telephone']);
        $adresse = $conn->real_escape_string($_POST['adresse'] ?? $parametres['adresse']);
        $devise = $conn->real_escape_string($_POST['devise'] ?? $parametres['devise']);
        $frais_livraison = floatval($_POST['frais_livraison'] ?? $parametres['frais_livraison']);
        $seuil_livraison_gratuite = floatval($_POST['seuil_livraison_gratuite'] ?? $parametres['seuil_livraison_gratuite']);
        $taxe = floatval($_POST['taxe'] ?? $parametres['taxe']);
        $meta_description = $conn->real_escape_string($_POST['meta_description'] ?? $parametres['meta_description']);
        $conditions_vente = $conn->real_escape_string($_POST['conditions_vente'] ?? $parametres['conditions_vente']);
        $facebook_url = $conn->real_escape_string($_POST['facebook_url'] ?? $parametres['facebook_url']);
        $instagram_url = $conn->real_escape_string($_POST['instagram_url'] ?? $parametres['instagram_url']);
        $twitter_url = $conn->real_escape_string($_POST['twitter_url'] ?? $parametres['twitter_url']);
        
        // Gestion des uploads de logo et favicon
        $logo_url = $parametres['logo_url'];
        $favicon_url = $parametres['favicon_url'];
        
        // Upload du logo si présent
        if (isset($_FILES['logo']) && $_FILES['logo']['size'] > 0) {
            $target_dir = "../uploads/";
            $file_extension = pathinfo($_FILES["logo"]["name"], PATHINFO_EXTENSION);
            $new_filename = 'logo_' . time() . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            // Vérifier que c'est bien une image
            $check = getimagesize($_FILES["logo"]["tmp_name"]);
            if ($check !== false) {
                if (move_uploaded_file($_FILES["logo"]["tmp_name"], $target_file)) {
                    $logo_url = 'uploads/' . $new_filename;
                } else {
                    $error = "Erreur lors de l'upload du logo.";
                }
            } else {
                $error = "Le fichier logo n'est pas une image valide.";
            }
        }
        
        // Upload du favicon si présent
        if (isset($_FILES['favicon']) && $_FILES['favicon']['size'] > 0) {
            $target_dir = "../uploads/";
            $file_extension = pathinfo($_FILES["favicon"]["name"], PATHINFO_EXTENSION);
            $new_filename = 'favicon_' . time() . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            // Vérifier que c'est bien une image
            $check = getimagesize($_FILES["favicon"]["tmp_name"]);
            if ($check !== false) {
                if (move_uploaded_file($_FILES["favicon"]["tmp_name"], $target_file)) {
                    $favicon_url = 'uploads/' . $new_filename;
                } else {
                    $error = "Erreur lors de l'upload du favicon.";
                }
            } else {
                $error = "Le fichier favicon n'est pas une image valide.";
            }
        }
        
        if (empty($error)) {
            // Mise à jour des paramètres
            $query = "UPDATE parametres SET 
                      nom_site = ?,
                      email_contact = ?,
                      telephone = ?,
                      adresse = ?,
                      devise = ?,
                      frais_livraison = ?,
                      seuil_livraison_gratuite = ?,
                      taxe = ?,
                      logo_url = ?,
                      favicon_url = ?,
                      meta_description = ?,
                      conditions_vente = ?,
                      facebook_url = ?,
                      instagram_url = ?,
                      twitter_url = ?
                      WHERE id = 1";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssssdddsssssss", 
                           $nom_site,
                           $email_contact,
                           $telephone,
                           $adresse,
                           $devise,
                           $frais_livraison,
                           $seuil_livraison_gratuite,
                           $taxe,
                           $logo_url,
                           $favicon_url,
                           $meta_description,
                           $conditions_vente,
                           $facebook_url,
                           $instagram_url,
                           $twitter_url);
            
            if ($stmt->execute()) {
                $message = "Les paramètres ont été mis à jour avec succès.";
                
                // Mettre à jour les paramètres dans la variable
                $parametres['nom_site'] = $nom_site;
                $parametres['email_contact'] = $email_contact;
                $parametres['telephone'] = $telephone;
                $parametres['adresse'] = $adresse;
                $parametres['devise'] = $devise;
                $parametres['frais_livraison'] = $frais_livraison;
                $parametres['seuil_livraison_gratuite'] = $seuil_livraison_gratuite;
                $parametres['taxe'] = $taxe;
                $parametres['logo_url'] = $logo_url;
                $parametres['favicon_url'] = $favicon_url;
                $parametres['meta_description'] = $meta_description;
                $parametres['conditions_vente'] = $conditions_vente;
                $parametres['facebook_url'] = $facebook_url;
                $parametres['instagram_url'] = $instagram_url;
                $parametres['twitter_url'] = $twitter_url;
            } else {
                $error = "Erreur lors de la mise à jour des paramètres : " . $conn->error;
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'clear_cache') {
        // Effacer le cache (simplifié)
        $cache_dir = "../cache/";
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            $message = "Le cache a été vidé avec succès.";
        } else {
            $error = "Le répertoire de cache n'existe pas.";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'maintenance_mode') {
        $maintenance_mode = isset($_POST['maintenance_enabled']) ? 1 : 0;
        
        $query = "UPDATE parametres SET maintenance_mode = ? WHERE id = 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $maintenance_mode);
        
        if ($stmt->execute()) {
            $parametres['maintenance_mode'] = $maintenance_mode;
            $message = $maintenance_mode ? "Le mode maintenance a été activé." : "Le mode maintenance a été désactivé.";
        } else {
            $error = "Erreur lors de la mise à jour du mode maintenance : " . $conn->error;
        }
    }
    
    // Rediriger pour éviter la soumission multiple du formulaire
    if (!empty($message) || !empty($error)) {
        header("Location: parametres.php?message=" . urlencode($message) . "&error=" . urlencode($error));
        exit();
    }
}

// Récupérer les messages d'URL
if (isset($_GET['message']) && !empty($_GET['message'])) {
    $message = $_GET['message'];
}

if (isset($_GET['error']) && !empty($_GET['error'])) {
    $error = $_GET['error'];
}

// Vérifier si la table existe, sinon la créer
$result = $conn->query("SHOW TABLES LIKE 'parametres'");
if ($result->num_rows == 0) {
    $create_table = "CREATE TABLE parametres (
        id INT(11) NOT NULL AUTO_INCREMENT,
        nom_site VARCHAR(100) NOT NULL,
        email_contact VARCHAR(100) NOT NULL,
        telephone VARCHAR(20) NOT NULL,
        adresse TEXT NOT NULL,
        devise VARCHAR(10) NOT NULL,
        frais_livraison DECIMAL(10,2) NOT NULL,
        seuil_livraison_gratuite DECIMAL(10,2) NOT NULL,
        taxe DECIMAL(5,2) NOT NULL,
        logo_url VARCHAR(255) NOT NULL,
        favicon_url VARCHAR(255) NOT NULL,
        meta_description TEXT NOT NULL,
        conditions_vente TEXT NOT NULL,
        facebook_url VARCHAR(255) NOT NULL,
        instagram_url VARCHAR(255) NOT NULL,
        twitter_url VARCHAR(255) NOT NULL,
        maintenance_mode TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($create_table) === FALSE) {
        $error = "Erreur lors de la création de la table paramètres : " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Administration AMAF-SHOP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary: #007bff;
            --primary-light: #cfe5ff;
            --secondary: #6c757d;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --dark: #343a40;
            --light: #f8f9fa;
            --gray: #6c757d;
            --gradient: linear-gradient(135deg, #007bff, #0056b3);
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
            color: var(--primary);
        }
        
        .sidebar-brand h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(45deg, var(--primary), #fff);
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
        
        .sidebar-footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 15px 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-footer a {
            display: flex;
            align-items: center;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .sidebar-footer a:hover {
            color: #fff;
        }
        
        .sidebar-footer i {
            font-size: 1.2rem;
            margin-right: 10px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            transition: var(--transition);
        }
        
        .page-header {
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .page-header p {
            font-size: 1rem;
            color: var(--gray);
            margin-bottom: 0;
        }
        
        .settings-section {
            margin-bottom: 30px;
        }
        
        .settings-section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--dark);
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-light);
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
        
        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
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
        
        .btn-action {
            border-radius: 50px;
            padding: 10px 25px;
            font-weight: 600;
            transition: var(--transition);
            text-transform: uppercase;
            letter-spacing: 1px;
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
        
        .alert {
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 20px;
            animation: fadeInDown 0.5s ease;
        }
        
        .nav-tabs {
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 20px;
        }
        
        .nav-tabs .nav-item {
            margin-bottom: -2px;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-bottom: 2px solid transparent;
            border-radius: 0;
            font-weight: 600;
            color: var(--gray);
            padding: 10px 20px;
            transition: var(--transition);
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--primary);
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
            background-color: transparent;
        }
        
        .tab-pane {
            padding: 20px 0;
        }
        
        .form-switch .form-check-input {
            width: 3em;
            height: 1.5em;
        }
        
        .current-logo-preview,
        .current-favicon-preview {
            max-width: 100px;
            max-height: 100px;
            border: 1px solid #eee;
            border-radius: 5px;
            padding: 5px;
            margin-top: 10px;
        }
        
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar-brand h2,
            .sidebar-menu h3,
            .sidebar-menu a span,
            .sidebar-footer a span {
                display: none;
            }
            
            .sidebar-menu a {
                padding: 15px 0;
                justify-content: center;
            }
            
            .sidebar-menu i {
                margin-right: 0;
                font-size: 1.5rem;
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
            .main-content {
                padding: 20px 15px;
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
            <h3>Menu Principal</h3>
            <ul>
                <li>
                    <a href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Tableau de bord</span>
                    </a>
                </li>
                <li>
                    <a href="produits.php">
                        <i class="fas fa-box"></i>
                        <span>Produits</span>
                    </a>
                </li>
                <li>
                    <a href="categories.php">
                        <i class="fas fa-tags"></i>
                        <span>Catégories</span>
                    </a>
                </li>
                <li>
                    <a href="commandes.php">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Commandes</span>
                    </a>
                </li>
                <li>
                    <a href="clients.php">
                        <i class="fas fa-users"></i>
                        <span>Clients</span>
                    </a>
                </li>
                <li>
                    <a href="promotions.php">
                        <i class="fas fa-percent"></i>
                        <span>Promotions</span>
                    </a>
                </li>
                <li>
                    <a href="parametres.php" class="active">
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
            <h1><i class="fas fa-cog me-2"></i>Paramètres du site</h1>
            <p>Configurez les paramètres généraux de votre boutique en ligne AMAF-SHOP.</p>
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
        
        <!-- Nav Tabs -->
        <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">
                    <i class="fas fa-store me-2"></i>Général
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="commerce-tab" data-bs-toggle="tab" data-bs-target="#commerce" type="button" role="tab" aria-controls="commerce" aria-selected="false">
                    <i class="fas fa-euro-sign me-2"></i>Commerce
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="appearance-tab" data-bs-toggle="tab" data-bs-target="#appearance" type="button" role="tab" aria-controls="appearance" aria-selected="false">
                    <i class="fas fa-palette me-2"></i>Apparence
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pagination-tab" data-bs-toggle="tab" data-bs-target="#pagination" type="button" role="tab" aria-controls="pagination" aria-selected="false">
                    <i class="fas fa-list-ol me-2"></i>Pagination
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab" aria-controls="system" aria-selected="false">
                    <i class="fas fa-wrench me-2"></i>Système
                </button>
            </li>
        </ul>
        
        <!-- Tab Content -->
        <div class="tab-content" id="settingsTabsContent">
            <!-- Paramètres Généraux -->
            <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                <div class="card animate__animated animate__fadeIn">
                    <div class="card-header">
                        <i class="fas fa-store me-2"></i>Informations de la boutique
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_parametres">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="nom_site" class="form-label">Nom de la boutique</label>
                                    <input type="text" class="form-control" id="nom_site" name="nom_site" value="<?= htmlspecialchars($parametres['nom_site'] ?? 'AMAF-SHOP') ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email_contact" class="form-label">Email de contact</label>
                                    <input type="email" class="form-control" id="email_contact" name="email_contact" value="<?= htmlspecialchars($parametres['email_contact'] ?? '') ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="telephone" class="form-label">Téléphone</label>
                                    <input type="text" class="form-control" id="telephone" name="telephone" value="<?= htmlspecialchars($parametres['telephone'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="adresse" class="form-label">Adresse</label>
                                    <textarea class="form-control" id="adresse" name="adresse" rows="3"><?= htmlspecialchars($parametres['adresse'] ?? '') ?></textarea>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="meta_description" class="form-label">Description du site (Meta description)</label>
                                    <textarea class="form-control" id="meta_description" name="meta_description" rows="3"><?= htmlspecialchars($parametres['meta_description'] ?? '') ?></textarea>
                                    <div class="form-text">Cette description sera utilisée pour le référencement de votre site.</div>
                                </div>
                            </div>
                            
                            <h5 class="settings-section-title mt-4">Réseaux sociaux</h5>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="facebook_url" class="form-label"><i class="fab fa-facebook me-2 text-primary"></i>Facebook</label>
                                    <input type="url" class="form-control" id="facebook_url" name="facebook_url" value="<?= htmlspecialchars($parametres['facebook_url'] ?? '') ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="instagram_url" class="form-label"><i class="fab fa-instagram me-2 text-danger"></i>Instagram</label>
                                    <input type="url" class="form-control" id="instagram_url" name="instagram_url" value="<?= htmlspecialchars($parametres['instagram_url'] ?? '') ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="twitter_url" class="form-label"><i class="fab fa-twitter me-2 text-info"></i>Twitter</label>
                                    <input type="url" class="form-control" id="twitter_url" name="twitter_url" value="<?= htmlspecialchars($parametres['twitter_url'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="whatsapp_url" class="form-label"><i class="fab fa-whatsapp me-2 text-success"></i>WhatsApp</label>
                                    <input type="url" class="form-control" id="whatsapp_url" name="whatsapp_url" value="<?= htmlspecialchars($parametres['whatsapp_url'] ?? '') ?>">
                                    <div class="form-text">Format: https://wa.me/votrenuméro (ex: https://wa.me/22507074895)</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="youtube_url" class="form-label"><i class="fab fa-youtube me-2 text-danger"></i>YouTube</label>
                                    <input type="url" class="form-control" id="youtube_url" name="youtube_url" value="<?= htmlspecialchars($parametres['youtube_url'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <button type="submit" class="btn btn-primary btn-action">
                                    <i class="fas fa-save me-2"></i>Enregistrer les modifications
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Paramètres Commerce -->
            <div class="tab-pane fade" id="commerce" role="tabpanel" aria-labelledby="commerce-tab">
                <div class="card animate__animated animate__fadeIn">
                    <div class="card-header">
                        <i class="fas fa-euro-sign me-2"></i>Paramètres de vente et livraison
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_parametres">
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="devise" class="form-label">Devise</label>
                                    <select class="form-select" id="devise" name="devise">
                                        <option value="CFA" <?= ($parametres['devise'] ?? 'CFA') === 'CFA' ? 'selected' : '' ?>>Franc CFA (CFA)</option>
                                        <option value="EUR" <?= ($parametres['devise'] ?? '') === 'EUR' ? 'selected' : '' ?>>Euro (€)</option>
                                        <option value="USD" <?= ($parametres['devise'] ?? '') === 'USD' ? 'selected' : '' ?>>Dollar US ($)</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="frais_livraison" class="form-label">Frais de livraison</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" class="form-control" id="frais_livraison" name="frais_livraison" value="<?= htmlspecialchars($parametres['frais_livraison'] ?? '1500') ?>">
                                        <span class="input-group-text"><?= htmlspecialchars($parametres['devise'] ?? 'CFA') ?></span>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="seuil_livraison_gratuite" class="form-label">Seuil de livraison gratuite</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" class="form-control" id="seuil_livraison_gratuite" name="seuil_livraison_gratuite" value="<?= htmlspecialchars($parametres['seuil_livraison_gratuite'] ?? '25000') ?>">
                                        <span class="input-group-text"><?= htmlspecialchars($parametres['devise'] ?? 'CFA') ?></span>
                                    </div>
                                    <div class="form-text">Montant d'achat à partir duquel la livraison est gratuite.</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="taxe" class="form-label">Taux de TVA (%)</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" class="form-control" id="taxe" name="taxe" value="<?= htmlspecialchars($parametres['taxe'] ?? '18') ?>">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                            </div>
                            
                            <h5 class="settings-section-title mt-4">Conditions de vente</h5>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="conditions_vente" class="form-label">Conditions générales de vente</label>
                                    <textarea class="form-control" id="conditions_vente" name="conditions_vente" rows="6"><?= htmlspecialchars($parametres['conditions_vente'] ?? '') ?></textarea>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <button type="submit" class="btn btn-primary btn-action">
                                    <i class="fas fa-save me-2"></i>Enregistrer les modifications
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Paramètres Apparence -->
            <div class="tab-pane fade" id="appearance" role="tabpanel" aria-labelledby="appearance-tab">
                <div class="card animate__animated animate__fadeIn">
                    <div class="card-header">
                        <i class="fas fa-palette me-2"></i>Identité visuelle
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_parametres">
                            
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="logo" class="form-label">Logo du site</label>
                                    <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                                    <div class="form-text">Format recommandé : PNG ou JPG, dimensions idéales 200x80px</div>
                                    
                                    <?php if (!empty($parametres['logo_url'])): ?>
                                        <div class="mt-2">
                                            <p>Logo actuel :</p>
                                            <img src="../<?= htmlspecialchars($parametres['logo_url']) ?>" alt="Logo actuel" class="current-logo-preview">
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <label for="favicon" class="form-label">Favicon</label>
                                    <input type="file" class="form-control" id="favicon" name="favicon" accept="image/*">
                                    <div class="form-text">Format recommandé : ICO ou PNG, dimensions idéales 32x32px</div>
                                    
                                    <?php if (!empty($parametres['favicon_url'])): ?>
                                        <div class="mt-2">
                                            <p>Favicon actuel :</p>
                                            <img src="../<?= htmlspecialchars($parametres['favicon_url']) ?>" alt="Favicon actuel" class="current-favicon-preview">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <button type="submit" class="btn btn-primary btn-action">
                                    <i class="fas fa-save me-2"></i>Enregistrer les modifications
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Paramètres de Pagination -->
            <div class="tab-pane fade" id="pagination" role="tabpanel" aria-labelledby="pagination-tab">
                <div class="card animate__animated animate__fadeIn">
                    <div class="card-header">
                        <i class="fas fa-list-ol me-2"></i>Paramètres de pagination
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_parametres">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="products_per_page" class="form-label">Nombre de produits par page (Catalogue)</label>
                                    <input type="number" min="4" max="24" class="form-control" id="products_per_page" name="products_per_page" value="<?= htmlspecialchars($parametres['products_per_page'] ?? '8') ?>">
                                    <div class="form-text">Nombre de produits affichés sur chaque page de la section "Tous nos produits"</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="promotions_per_page" class="form-label">Nombre de produits par page (Promotions)</label>
                                    <input type="number" min="3" max="12" class="form-control" id="promotions_per_page" name="promotions_per_page" value="<?= htmlspecialchars($parametres['promotions_per_page'] ?? '6') ?>">
                                    <div class="form-text">Nombre de produits affichés sur chaque page de la section "Promotions"</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <h5 class="settings-section-title mt-3">Style de la section "Tous nos produits"</h5>
                                    <div class="mb-3">
                                        <label for="all_products_padding" class="form-label">Padding (espacement)</label>
                                        <div class="input-group">
                                            <input type="number" min="10" max="60" class="form-control" id="all_products_padding" name="all_products_padding" value="<?= htmlspecialchars($parametres['all_products_padding'] ?? '20') ?>">
                                            <span class="input-group-text">px</span>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="all_products_card_height" class="form-label">Hauteur des cartes produit</label>
                                        <div class="input-group">
                                            <input type="number" min="200" max="500" class="form-control" id="all_products_card_height" name="all_products_card_height" value="<?= htmlspecialchars($parametres['all_products_card_height'] ?? '280') ?>">
                                            <span class="input-group-text">px</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <h5 class="settings-section-title mt-3">Style de la section "Promotions"</h5>
                                    <div class="mb-3">
                                        <label for="promotions_padding" class="form-label">Padding (espacement)</label>
                                        <div class="input-group">
                                            <input type="number" min="10" max="60" class="form-control" id="promotions_padding" name="promotions_padding" value="<?= htmlspecialchars($parametres['promotions_padding'] ?? '40') ?>">
                                            <span class="input-group-text">px</span>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="promotions_card_height" class="form-label">Hauteur des cartes produit</label>
                                        <div class="input-group">
                                            <input type="number" min="200" max="500" class="form-control" id="promotions_card_height" name="promotions_card_height" value="<?= htmlspecialchars($parametres['promotions_card_height'] ?? '320') ?>">
                                            <span class="input-group-text">px</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Remarque :</strong> Les sections "Tous nos produits" et "Promotions" ont des styles légèrement différenciés pour une meilleure expérience utilisateur. La section Promotions utilise des dimensions plus généreuses avec un padding de 40px, des cartes de produit de 320px de hauteur, et une grille de 3 colonnes sur desktop.
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <button type="submit" class="btn btn-primary btn-action">
                                    <i class="fas fa-save me-2"></i>Enregistrer les modifications
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Paramètres Système -->
            <div class="tab-pane fade" id="system" role="tabpanel" aria-labelledby="system-tab">
                <div class="row">
                    <!-- Mode Maintenance -->
                    <div class="col-md-6">
                        <div class="card animate__animated animate__fadeIn">
                            <div class="card-header bg-warning text-dark">
                                <i class="fas fa-tools me-2"></i>Mode Maintenance
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="maintenance_mode">
                                    
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="maintenance_enabled" name="maintenance_enabled" <?= isset($parametres['maintenance_mode']) && $parametres['maintenance_mode'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="maintenance_enabled">Activer le mode maintenance</label>
                                    </div>
                                    
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Attention:</strong> Lorsque le mode maintenance est activé, seuls les administrateurs peuvent accéder au site. Les visiteurs verront une page de maintenance.
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-warning">
                                            <i class="fas fa-save me-2"></i>Enregistrer le mode maintenance
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cache du site -->
                    <div class="col-md-6">
                        <div class="card animate__animated animate__fadeIn">
                            <div class="card-header bg-info text-white">
                                <i class="fas fa-bolt me-2"></i>Performance
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="clear_cache">
                                    
                                    <p>Si vous avez fait des modifications sur le site et qu'elles n'apparaissent pas immédiatement, vous pouvez vider le cache du site.</p>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-info text-white">
                                            <i class="fas fa-broom me-2"></i>Vider le cache du site
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Activer les tooltips Bootstrap
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
            
            // Vérifier l'URL pour activer l'onglet correspondant
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) {
                const tabEl = document.querySelector(`#${tab}-tab`);
                if (tabEl) {
                    const tabInstance = new bootstrap.Tab(tabEl);
                    tabInstance.show();
                }
            }
        });
    </script>
</body>
</html>
