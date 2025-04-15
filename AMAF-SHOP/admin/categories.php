<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require '../config.php';

// Vérifier si l'utilisateur est connecté et est un admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Traitement des actions
$message = '';
$message_type = '';

// Ajout d'une catégorie
if (isset($_POST['add_category'])) {
    $nom = trim($_POST['nom']);
    $description = trim($_POST['description']);
    
    if (empty($nom)) {
        $message = "Le nom de la catégorie est obligatoire";
        $message_type = "danger";
    } else {
        // Traitement de l'image
        $image_url = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_filename = 'categories_' . uniqid() . '.' . $ext;
                $upload_dir = '../images/categories/';

                // Ajouter cette vérification
if (!is_writable('../images/') && !file_exists($upload_dir)) {
    $message = "Erreur: Le dossier images n'a pas les permissions d'écriture";
    $message_type = "danger";
    // Ajouter un log ou afficher l'erreur
    error_log("AMAF-SHOP: Erreur de permission sur le dossier images");
}
                
                // Créer le répertoire s'il n'existe pas
                if (!file_exists($upload_dir)) {
                    try {
                        mkdir($upload_dir, 0777, true);
                    } catch (Exception $e) {
                        $message = "Erreur lors de la création du dossier d'upload: " . $e->getMessage();
                        $message_type = "danger";
                    }
                }
                
                $destination = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                    $image_url = 'images/categories/' . $new_filename;
                }
            }
        }
        
        // Insérer dans la base de données
        $query = "INSERT INTO categorie (nom, description, image_url) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $nom, $description, $image_url);
        
        if ($stmt->execute()) {
            $message = "Catégorie ajoutée avec succès";
            $message_type = "success";
        } else {
            $message = "Erreur lors de l'ajout de la catégorie: " . $conn->error;
            $message_type = "danger";
        }
    }
}

// Modification d'une catégorie
if (isset($_POST['edit_category'])) {
    $id = intval($_POST['id']);
    $nom = trim($_POST['nom']);
    $description = trim($_POST['description']);
    
    if (empty($nom)) {
        $message = "Le nom de la catégorie est obligatoire";
        $message_type = "danger";
    } else {
        // Récupérer l'ancienne image_url
        $query = "SELECT image_url FROM categorie WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $category = $result->fetch_assoc();
        $old_image_url = $category['image_url'];
        
        // Traitement de l'image si une nouvelle est fournie
        $image_url = $old_image_url; // Par défaut, garde l'ancienne image
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_filename = 'categories_' . uniqid() . '.' . $ext;
                $upload_dir = '../images/categories/';
                
                // Créer le répertoire s'il n'existe pas
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $destination = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                    $image_url = 'images/categories/' . $new_filename;
                    
                    // Supprimer l'ancienne image si elle existe
                    if (!empty($old_image_url) && file_exists('../' . $old_image_url)) {
                        unlink('../' . $old_image_url);
                    }
                }
            }
        }
        
        // Mettre à jour dans la base de données
        $query = "UPDATE categorie SET nom = ?, description = ?, image_url = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssi", $nom, $description, $image_url, $id);
        
        if ($stmt->execute()) {
            $message = "Catégorie mise à jour avec succès";
            $message_type = "success";
        } else {
            $message = "Erreur lors de la mise à jour de la catégorie: " . $conn->error;
            $message_type = "danger";
        }
    }
}

// Suppression d'une catégorie
if (isset($_POST['delete_category'])) {
    $id = intval($_POST['id']);
    
    // Vérifier si la catégorie a des produits associés
    $query = "SELECT COUNT(*) as count FROM produit WHERE categorie_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        $message = "Impossible de supprimer cette catégorie car elle contient des produits";
        $message_type = "warning";
    } else {
        // Récupérer l'image pour la supprimer
        $query = "SELECT image_url FROM categorie WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $category = $result->fetch_assoc();
        
        // Supprimer de la base de données
        $query = "DELETE FROM categorie WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            // Supprimer l'image si elle existe
            if (!empty($category['image_url']) && file_exists('../' . $category['image_url'])) {
                unlink('../' . $category['image_url']);
            }
            
            $message = "Catégorie supprimée avec succès";
            $message_type = "success";
        } else {
            $message = "Erreur lors de la suppression de la catégorie: " . $conn->error;
            $message_type = "danger";
        }
    }
}

// Récupérer toutes les catégories
$query = "SELECT c.*, (SELECT COUNT(*) FROM produit p WHERE p.categorie_id = c.id) as produit_count 
          FROM categorie c ORDER BY c.nom";
$result = $conn->query($query);

// Statuts des commandes (pour les badges de la sidebar)
$query_commandes = "SELECT COUNT(*) as count FROM commande";
$result_commandes = $conn->query($query_commandes);
$total_commandes = ($result_commandes) ? $result_commandes->fetch_assoc()['count'] : 0;

// Total des produits (pour les badges de la sidebar)
$query_produits = "SELECT COUNT(*) as count FROM produit";
$result_produits = $conn->query($query_produits);
$total_produits = ($result_produits) ? $result_produits->fetch_assoc()['count'] : 0;

// Total des utilisateurs (pour les badges de la sidebar)
$query_users = "SELECT COUNT(*) as count FROM utilisateur";
$result_users = $conn->query($query_users);
$total_users = ($result_users) ? $result_users->fetch_assoc()['count'] : 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des catégories - AMAF-SHOP</title>
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
        
        .page-header h1 {
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .page-header p {
            opacity: 0.8;
            margin-bottom: 0;
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
        }
        
        .card-header {
            background: var(--dark);
            color: white;
            padding: 15px 20px;
            font-weight: 600;
            border-bottom: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .card-header .btn {
            margin-left: auto;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .category-card {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .category-image {
            height: 160px;
            background-color: #f0f0f0;
            background-size: cover;
            background-position: center;
            border-radius: 10px 10px 0 0;
            position: relative;
        }
        
        .category-image-placeholder {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--gray);
            font-size: 3rem;
        }
        
        .category-content {
            padding: 15px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            background-color: white;
        }
        
        .category-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        .category-description {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 15px;
            flex-grow: 1;
        }
        
        .category-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
        }
        
        .category-count {
            display: inline-flex;
            align-items: center;
            font-size: 0.9rem;
            color: var(--primary);
            background-color: rgba(58, 123, 213, 0.1);
            padding: 5px 10px;
            border-radius: 50px;
        }
        
        .category-count i {
            margin-right: 5px;
            font-size: 0.8rem;
        }
        
        .category-actions {
            display: flex;
            gap: 5px;
        }
        
        .btn-icon {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            padding: 0;
            transition: var(--transition);
        }
        
        .btn-icon:hover {
            transform: scale(1.1);
        }
        
        /* Form */
        .form-floating > .form-control {
            height: calc(3.5rem + 2px);
            padding: 1rem 0.75rem;
        }
        
        .form-floating > label {
            padding: 1rem 0.75rem;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(58, 123, 213, 0.25);
        }
        
        .preview-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            color: var(--gray);
            font-size: 2rem;
            overflow: hidden;
        }
        
        .preview-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Modals */
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: var(--shadow);
        }
        
        .modal-header {
            background: var(--gradient);
            color: white;
            border-bottom: none;
            border-radius: 15px 15px 0 0;
        }
        
        .modal-footer {
            border-top: none;
        }
        
        /* Animations */
        .fadeIn {
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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
        
        @media (max-width: 768px) {
            .page-header {
                padding: 20px;
            }
            
            .category-grid {
                grid-template-columns: 1fr;
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
                        <span class="badge bg-primary rounded-pill"><?= $total_produits ?></span>
                    </a>
                </li>
                <li>
                    <a href="commandes.php">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Commandes</span>
                        <span class="badge bg-success rounded-pill"><?= $total_commandes ?></span>
                    </a>
                </li>
                <li>
                    <a href="categories.php" class="active">
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
                        <span class="badge bg-info rounded-pill"><?= $total_users ?></span>
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
            <h1><i class="fas fa-tags me-2"></i>Gestion des catégories</h1>
            <p>Créez et gérez les catégories de produits pour votre boutique en ligne.</p>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $message_type ?> animate__animated animate__fadeIn">
                <?= $message ?>
            </div>
        <?php endif; ?>
        
        <div class="card animate__animated animate__fadeInUp">
            <div class="card-header">
                <i class="fas fa-list me-2"></i>Catégories
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="fas fa-plus me-2"></i>Ajouter une catégorie
                </button>
            </div>
            <div class="card-body">
                <?php if ($result->num_rows === 0): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-tags fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">Aucune catégorie trouvée</h4>
                        <p class="text-muted">Commencez par ajouter des catégories pour organiser vos produits.</p>
                        <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i class="fas fa-plus me-2"></i>Ajouter une catégorie
                        </button>
                    </div>
                <?php else: ?>
                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                        <?php while ($category = $result->fetch_assoc()): ?>
                            <div class="col">
                                <div class="category-card animate__animated animate__fadeIn">
                                    <?php if (!empty($category['image_url'])): ?>
                                        <div class="category-image" style="background-image: url('../<?= !empty($category['image_url']) ? htmlspecialchars($category['image_url']) : '' ?>')"></div>
                                    <?php else: ?>
                                        <div class="category-image">
                                            <div class="category-image-placeholder">
                                                <i class="fas fa-tag"></i>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="category-content">
                                        <div class="category-title"><?= htmlspecialchars($category['nom']) ?></div>
                                        <div class="category-description">
                                            <?php 
                                                $description = isset($category['description']) ? $category['description'] : '';
                                                echo !empty($description) 
                                                    ? htmlspecialchars($description)
                                                    : '<span class="text-muted">Aucune description</span>';
                                            ?>
                                        </div>
                                        
                                        <div class="category-footer">
                                            <div class="category-count">
                                                <i class="fas fa-box"></i>
                                                <?= $category['produit_count'] ?> produit<?= $category['produit_count'] > 1 ? 's' : '' ?>
                                            </div>
                                            
                                            <div class="category-actions">
                                                <button class="btn btn-icon btn-outline-primary" 
                                                        onclick="editCategory(<?= $category['id'] ?>, '<?= addslashes($category['nom']) ?>', '<?= addslashes($category['description']) ?>', '<?= $category['image_url'] ?>')"
                                                        title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <button class="btn btn-icon btn-outline-danger" 
        onclick="deleteCategory(<?= $category['id'] ?>, '<?= addslashes($category['nom']) ?>')"
        title="<?= $category['produit_count'] > 0 ? 'Impossible de supprimer: contient des produits' : 'Supprimer' ?>"
        <?= $category['produit_count'] > 0 ? 'disabled' : '' ?>>
    <i class="fas fa-trash"></i>
</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content animate__animated animate__fadeInDown">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Ajouter une catégorie</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="preview-image" id="addPreviewImage">
                            <i class="fas fa-image"></i>
                        </div>
                        
                        <div class="mb-3">
                            <label for="image" class="form-label">Image de la catégorie</label>
                            <input type="file" class="form-control" id="addImage" name="image" accept="image/*" onchange="previewAddImage(this)">
                            <div class="form-text">Format recommandé: 800x600 px. Taille max: 2 Mo.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="nom" class="form-label">Nom de la catégorie *</label>
                            <input type="text" class="form-control" id="addNom" name="nom" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="addDescription" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Annuler
                        </button>
                        <button type="submit" name="add_category" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content animate__animated animate__fadeInDown">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Modifier la catégorie</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" id="editId" name="id">
                        
                        <div class="preview-image" id="editPreviewImage">
                            <i class="fas fa-image"></i>
                        </div>
                        
                        <div class="mb-3">
                            <label for="image" class="form-label">Image de la catégorie</label>
                            <input type="file" class="form-control" id="editImage" name="image" accept="image/*" onchange="previewEditImage(this)">
                            <div class="form-text">Laissez vide pour conserver l'image actuelle.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="nom" class="form-label">Nom de la catégorie *</label>
                            <input type="text" class="form-control" id="editNom" name="nom" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="editDescription" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Annuler
                        </button>
                        <button type="submit" name="edit_category" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Mettre à jour
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Category Modal -->
    <div class="modal fade" id="deleteCategoryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content animate__animated animate__fadeInDown">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirmer la suppression</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer la catégorie <strong id="deleteCategoryName"></strong> ?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Cette action est irréversible. La catégorie sera définitivement supprimée.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Annuler
                    </button>
                    <form method="POST">
                        <input type="hidden" id="deleteId" name="id">
                        <button type="submit" name="delete_category" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Supprimer
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview images before upload
        function previewAddImage(input) {
            const preview = document.getElementById('addPreviewImage');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Aperçu">`;
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function previewEditImage(input) {
            const preview = document.getElementById('editPreviewImage');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Aperçu">`;
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Edit category modal
        function editCategory(id, nom, description, image_url) {
            document.getElementById('editId').value = id;
            document.getElementById('editNom').value = nom.replace(/&quot;/g, '"').replace(/&#039;/g, "'");
            document.getElementById('editDescription').value = description.replace(/&quot;/g, '"').replace(/&#039;/g, "'");
            
            const preview = document.getElementById('editPreviewImage');
            if (image_url) {
                preview.innerHTML = `<img src="../${image_url}" alt="${nom}">`;
            } else {
                preview.innerHTML = `<i class="fas fa-image"></i>`;
            }
            
            const editModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
            editModal.show();
        }
        
        // Delete category modal
        function deleteCategory(id, nom) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteCategoryName').textContent = nom;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteCategoryModal'));
            deleteModal.show();
        }
        
        // Animation on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Add animation delay to category cards
            document.querySelectorAll('.category-card').forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
            
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Auto-hide alerts after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    alert.classList.add('animate__fadeOut');
                    setTimeout(() => {
                        bsAlert.close();
                    }, 500);
                });
            }, 5000);
        });
    </script>
</body>
</html>