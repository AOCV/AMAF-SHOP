<?php
session_start();
require 'config.php';

// Vérifier si l'utilisateur est connecté et est un admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

// Vérifier si un ID de produit a été fourni
if (isset($_GET['id'])) {
    $id_produit = intval($_GET['id']);
    
    // Récupérer les informations du produit avant la suppression
    $query = "SELECT nom, image_url FROM produit WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id_produit);
    $stmt->execute();
    $result = $stmt->get_result();
    $produit = $result->fetch_assoc();
    
    if ($produit) {
        // Supprimer l'image associée si elle existe
        if (!empty($produit['image_url']) && file_exists($produit['image_url'])) {
            unlink($produit['image_url']);
        }
        
        // Supprimer le produit de la base de données
        $query_delete = "DELETE FROM produit WHERE id = ?";
        $stmt_delete = $conn->prepare($query_delete);
        $stmt_delete->bind_param("i", $id_produit);
        
        if ($stmt_delete->execute()) {
            $message = "Le produit \"" . htmlspecialchars($produit['nom']) . "\" a été supprimé avec succès.";
        } else {
            $error = "Erreur lors de la suppression du produit : " . $conn->error;
        }
        
        $stmt_delete->close();
    } else {
        $error = "Produit non trouvé.";
    }
    
    $stmt->close();
}

// Récupérer la liste des produits
$query_produits = "SELECT p.*, c.nom as categorie_nom 
                  FROM produit p 
                  LEFT JOIN categorie c ON p.categorie_id = c.id 
                  ORDER BY p.nom";
$result_produits = $conn->query($query_produits);

// Récupérer les catégories pour le filtre
$query_categories = "SELECT * FROM categorie ORDER BY nom";
$result_categories = $conn->query($query_categories);
$categories = [];
if ($result_categories) {
    while ($row = $result_categories->fetch_assoc()) {
        $categories[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Produits - AMAF-SHOP</title>
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
            color: #333;
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
        
        .btn-success {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            border: none;
        }
        
        .card {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: none;
            margin-bottom: 30px;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        
        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
            transition: var(--transition);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .product-image:hover {
            transform: scale(1.1);
        }
        
        .badge {
            padding: 7px 12px;
            border-radius: 50px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        
        .modal-content {
            border-radius: 15px;
            overflow: hidden;
            border: none;
            box-shadow: 0 15px 50px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            background: var(--gradient);
            color: white;
            border-bottom: none;
            padding: 20px 30px;
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .modal-footer {
            border-top: none;
            padding: 20px 30px;
        }
        
        .alert {
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 20px;
            animation: fadeInDown 0.5s ease;
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
        
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        
        .search-input {
            border-radius: 50px;
            padding: 10px 20px;
            border: 1px solid #e0e0e0;
            transition: var(--transition);
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .search-input:focus {
            box-shadow: 0 5px 15px rgba(58, 123, 213, 0.2);
            border-color: var(--primary);
        }
        
        /* Animations */
        .product-row {
            animation: fadeInUp 0.5s ease;
            animation-fill-mode: both;
        }
        
        .view-toggle {
            display: flex;
            align-items: center;
            margin-left: 15px;
        }
        
        .view-toggle-btn {
            border: none;
            background: transparent;
            font-size: 1.2rem;
            color: var(--gray);
            padding: 5px 10px;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .view-toggle-btn.active {
            color: var(--primary);
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .product-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        
        .product-card-img {
            height: 200px;
            width: 100%;
            object-fit: cover;
        }
        
        .product-card-body {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .product-card-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        .product-card-category {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 15px;
        }
        
        .product-card-price {
            font-weight: 700;
            color: var(--primary);
            font-size: 1.2rem;
            margin-bottom: 15px;
        }
        
        .product-card-stock {
            margin-bottom: 15px;
        }
        
        .product-card-actions {
            margin-top: auto;
            display: flex;
            gap: 10px;
        }
        
        .product-card-actions .btn {
            flex: 1;
        }
        
        .hidden {
            display: none;
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease;
        }
        
        .slide-in {
            animation: slideIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { 
                opacity: 0;
                transform: translateY(20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Tooltip customization */
        .tooltip .tooltip-inner {
            background-color: var(--dark);
            padding: 8px 15px;
            border-radius: 10px;
        }
        
        /* Loading state */
        .spinner-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100px;
        }
    </style>
</head>
<body>
    <div class="container mt-5 slide-in">
        <!-- Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1"><i class="fas fa-box me-2"></i>Gestion des Produits</h2>
                    <p class="mb-0">Consultez, modifiez ou supprimez vos produits</p>
                </div>
                <div>
                    <span class="badge bg-light text-dark mb-2">
                        <i class="fas fa-box me-1"></i> <?= $result_produits->num_rows ?> produits
                    </span>
                </div>
            </div>
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
        
        <!-- Action buttons -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="./admin/dashboard.php" class="btn btn-secondary btn-action">
                    <i class="fas fa-arrow-left me-2"></i>Tableau de bord
                </a>
                <a href="ajout_produit.php" class="btn btn-success btn-action ms-2 animate__animated animate__pulse animate__infinite animate__slower">
                    <i class="fas fa-plus me-2"></i>Ajouter un produit
                </a>
            </div>
            <div class="d-flex align-items-center">
                <div class="view-toggle">
                    <button class="view-toggle-btn active" id="tableViewBtn" title="Vue tableau">
                        <i class="fas fa-list"></i>
                    </button>
                    <button class="view-toggle-btn" id="gridViewBtn" title="Vue grille">
                        <i class="fas fa-th"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Filter section -->
        <div class="filter-section animate__animated animate__fadeInUp">
            <div class="row align-items-center">
                <div class="col-md-6 mb-3 mb-md-0">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" id="searchInput" class="form-control search-input border-start-0" placeholder="Rechercher un produit...">
                    </div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <select id="categoryFilter" class="form-select">
                        <option value="">Toutes les catégories</option>
                        <?php foreach ($categories as $categorie): ?>
                            <option value="<?= htmlspecialchars($categorie['nom']) ?>">
                                <?= htmlspecialchars($categorie['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="stockFilter" class="form-select">
                        <option value="">Tous les stocks</option>
                        <option value="rupture">Rupture de stock</option>
                        <option value="bas">Stock bas (<10)</option>
                        <option value="disponible">Disponible</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Table View -->
        <div id="tableView">
            <div class="table-responsive">
                <table class="table table-hover animate__animated animate__fadeInUp">
                    <thead>
                        <tr>
                            <th><i class="fas fa-image me-2"></i>Image</th>
                            <th><i class="fas fa-tag me-2"></i>Nom</th>
                            <th><i class="fas fa-folder me-2"></i>Catégorie</th>
                            <th><i class="fas fa-money-bill-wave me-2"></i>Prix</th>
                            <th><i class="fas fa-warehouse me-2"></i>Stock</th>
                            <th class="text-center"><i class="fas fa-cogs me-2"></i>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="productsTableBody">
                        <?php if ($result_produits->num_rows === 0): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Aucun produit trouvé</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $i = 0; while ($produit = $result_produits->fetch_assoc()): $i++; ?>
                                <tr class="product-row" style="animation-delay: <?= $i * 0.05 ?>s;" 
                                    data-name="<?= htmlspecialchars(strtolower($produit['nom'])) ?>"
                                    data-category="<?= htmlspecialchars(strtolower($produit['categorie_nom'] ?? '')) ?>"
                                    data-stock="<?= $produit['stock'] ?>">
                                    <td>
                                        <?php if ($produit['image_url']): ?>
                                            <img src="<?= htmlspecialchars($produit['image_url']) ?>" 
                                                 alt="<?= htmlspecialchars($produit['nom']) ?>" 
                                                 class="product-image">
                                        <?php else: ?>
                                            <div class="rounded bg-light d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                                <i class="fas fa-image text-muted fa-2x"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-semibold">
                                        <?= htmlspecialchars($produit['nom']) ?>
                                        <?php if ($produit['promotion']): ?>
                                            <span class="badge bg-danger ms-2">
                                                -<?= number_format(($produit['promotion'] / $produit['prix']) * 100, 0) ?>%
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($produit['categorie_nom']): ?>
                                            <span class="badge bg-info">
                                                <?= htmlspecialchars($produit['categorie_nom']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Sans catégorie</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($produit['promotion']): ?>
                                            <span class="text-decoration-line-through text-muted me-2">
                                                <?= number_format($produit['prix'], 0) ?> CFA
                                            </span>
                                            <span class="fw-bold text-success">
                                                <?= number_format($produit['prix'] - $produit['promotion'], 0) ?> CFA
                                            </span>
                                        <?php else: ?>
                                            <span class="fw-bold"><?= number_format($produit['prix'], 0) ?> CFA</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($produit['stock'] <= 0): ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-times-circle me-1"></i>Rupture
                                            </span>
                                        <?php elseif ($produit['stock'] < 10): ?>
                                            <span class="badge bg-warning text-dark">
                                                <i class="fas fa-exclamation-circle me-1"></i><?= $produit['stock'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle me-1"></i><?= $produit['stock'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center">
                                            <a href="modif_produit.php?id=<?= $produit['id'] ?>" 
                                               class="btn btn-primary btn-sm" 
                                               data-bs-toggle="tooltip" 
                                               title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-danger btn-sm" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteModal<?= $produit['id'] ?>"
                                                    data-bs-toggle="tooltip" 
                                                    title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Modal de confirmation de suppression -->
                                <div class="modal fade" id="deleteModal<?= $produit['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content animate__animated animate__fadeInDown">
                                            <div class="modal-header">
                                                <h5 class="modal-title">
                                                    <i class="fas fa-trash-alt me-2"></i>Confirmer la suppression
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="text-center mb-4">
                                                    <?php if ($produit['image_url']): ?>
                                                        <img src="<?= htmlspecialchars($produit['image_url']) ?>" 
                                                             alt="<?= htmlspecialchars($produit['nom']) ?>" 
                                                             class="product-image mb-3" style="width: 120px; height: 120px;">
                                                    <?php else: ?>
                                                        <div class="rounded bg-light d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 120px; height: 120px;">
                                                            <i class="fas fa-image text-muted fa-3x"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <p class="fs-5">Êtes-vous sûr de vouloir supprimer le produit :</p>
                                                    <p class="fw-bold fs-4 text-danger">"<?= htmlspecialchars($produit['nom']) ?>"</p>
                                                    <p class="text-muted">Cette action est irréversible.</p>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                    <i class="fas fa-times me-2"></i>Annuler
                                                </button>
                                                <a href="?id=<?= $produit['id'] ?>" class="btn btn-danger">
                                                    <i class="fas fa-trash-alt me-2"></i>Supprimer
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Grid View -->
        <div id="gridView" class="hidden">
            <div class="product-grid animate__animated animate__fadeInUp">
                <?php 
                // Réinitialiser le pointeur de résultat
                $result_produits->data_seek(0);
                $i = 0;
                while ($produit = $result_produits->fetch_assoc()): 
                    $i++;
                ?>
                    <div class="product-card" style="animation-delay: <?= $i * 0.05 ?>s;"
                         data-name="<?= htmlspecialchars(strtolower($produit['nom'])) ?>"
                         data-category="<?= htmlspecialchars(strtolower($produit['categorie_nom'] ?? '')) ?>"
                         data-stock="<?= $produit['stock'] ?>">
                        <div class="position-relative">
                            <?php if ($produit['image_url']): ?>
                                <img src="<?= htmlspecialchars($produit['image_url']) ?>" 
                                     alt="<?= htmlspecialchars($produit['nom']) ?>" 
                                     class="product-card-img">
                            <?php else: ?>
                                <div class="bg-light d-flex align-items-center justify-content-center product-card-img">
                                    <i class="fas fa-image text-muted fa-3x"></i>
                                </div>
                            <?php endif; ?>
                            <?php if ($produit['promotion']): ?>
                                <span class="badge bg-danger position-absolute top-0 end-0 m-2">
                                    -<?= number_format(($produit['promotion'] / $produit['prix']) * 100, 0) ?>%
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="product-card-body">
                            <div class="product-card-title"><?= htmlspecialchars($produit['nom']) ?></div>
                            <div class="product-card-category">
                                <?php if ($produit['categorie_nom']): ?>
                                    <span class="badge bg-info">
                                        <?= htmlspecialchars($produit['categorie_nom']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Sans catégorie</span>
                                <?php endif; ?>
                            </div>
                            <div class="product-card-price">
                                <?php if ($produit['promotion']): ?>
                                    <span class="text-decoration-line-through text-muted me-2 fs-6">
                                        <?= number_format($produit['prix'], 0) ?> CFA
                                    </span>
                                    <span class="text-success">
                                        <?= number_format($produit['prix'] - $produit['promotion'], 0) ?> CFA
                                    </span>
                                <?php else: ?>
                                    <?= number_format($produit['prix'], 0) ?> CFA
                                <?php endif; ?>
                            </div>
                            <div class="product-card-stock">
                                <?php if ($produit['stock'] <= 0): ?>
                                    <span class="badge bg-danger">
                                        <i class="fas fa-times-circle me-1"></i>Rupture
                                    </span>
                                <?php elseif ($produit['stock'] < 10): ?>
                                    <span class="badge bg-warning text-dark">
                                        <i class="fas fa-exclamation-circle me-1"></i>Stock: <?= $produit['stock'] ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check-circle me-1"></i>Stock: <?= $produit['stock'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="product-card-actions">
                                <a href="modif_produit.php?id=<?= $produit['id'] ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-edit me-2"></i>Modifier
                                </a>
                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $produit['id'] ?>">
                                    <i class="fas fa-trash me-2"></i>Supprimer
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <!-- Empty state for grid view -->
            <div id="emptyGridState" class="text-center py-5 hidden">
                <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">Aucun produit trouvé</h4>
                <p class="text-muted">Essayez de modifier vos filtres ou ajoutez de nouveaux produits.</p>
                <a href="ajout_produit.php" class="btn btn-primary mt-3">
                    <i class="fas fa-plus me-2"></i>Ajouter un produit
                </a>
            </div>
        </div>
        
        <!-- Empty state for filtered results -->
        <div id="noResultsMessage" class="text-center py-5 hidden animate__animated animate__fadeIn">
            <i class="fas fa-search fa-4x text-muted mb-3"></i>
            <h4 class="text-muted">Aucun résultat trouvé</h4>
            <p class="text-muted">Essayez de modifier vos critères de recherche.</p>
            <button id="resetFiltersBtn" class="btn btn-outline-primary mt-3">
                <i class="fas fa-sync me-2"></i>Réinitialiser les filtres
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    placement: 'top'
                });
            });
            
            // View toggle functionality
            const tableViewBtn = document.getElementById('tableViewBtn');
            const gridViewBtn = document.getElementById('gridViewBtn');
            const tableView = document.getElementById('tableView');
            const gridView = document.getElementById('gridView');
            
            tableViewBtn.addEventListener('click', function() {
                tableView.classList.remove('hidden');
                gridView.classList.add('hidden');
                tableViewBtn.classList.add('active');
                gridViewBtn.classList.remove('active');
                applyFilters(); // Re-apply filters when switching views
            });
            
            gridViewBtn.addEventListener('click', function() {
                gridView.classList.remove('hidden');
                tableView.classList.add('hidden');
                gridViewBtn.classList.add('active');
                tableViewBtn.classList.remove('active');
                applyFilters(); // Re-apply filters when switching views
            });
            
            // Search and filter functionality
            const searchInput = document.getElementById('searchInput');
            const categoryFilter = document.getElementById('categoryFilter');
            const stockFilter = document.getElementById('stockFilter');
            const resetFiltersBtn = document.getElementById('resetFiltersBtn');
            const noResultsMessage = document.getElementById('noResultsMessage');
            const productsTableBody = document.getElementById('productsTableBody');
            const emptyGridState = document.getElementById('emptyGridState');
            
            // Function to apply all filters
            function applyFilters() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                const categoryValue = categoryFilter.value.toLowerCase();
                const stockValue = stockFilter.value;
                
                // Select all product elements in both views
                const tableRows = document.querySelectorAll('#productsTableBody tr.product-row');
                const gridItems = document.querySelectorAll('.product-grid .product-card');
                
                let visibleCount = 0;
                
                // Filter function for a product element
                const filterProduct = (product) => {
                    const name = product.dataset.name;
                    const category = product.dataset.category;
                    const stock = parseInt(product.dataset.stock);
                    
                    // Check if product matches all filters
                    const matchesSearch = name.includes(searchTerm);
                    const matchesCategory = !categoryValue || category === categoryValue;
                    
                    let matchesStock = true;
                    if (stockValue === 'rupture') {
                        matchesStock = stock <= 0;
                    } else if (stockValue === 'bas') {
                        matchesStock = stock > 0 && stock < 10;
                    } else if (stockValue === 'disponible') {
                        matchesStock = stock >= 10;
                    }
                    
                    const isVisible = matchesSearch && matchesCategory && matchesStock;
                    product.classList.toggle('hidden', !isVisible);
                    
                    if (isVisible) visibleCount++;
                    return isVisible;
                };
                
                // Apply filters to table view
                if (!tableView.classList.contains('hidden')) {
                    tableRows.forEach(filterProduct);
                }
                
                // Apply filters to grid view
                if (!gridView.classList.contains('hidden')) {
                    gridItems.forEach(filterProduct);
                }
                
                // Show/hide no results message
                noResultsMessage.classList.toggle('hidden', visibleCount > 0);
                
                // Show/hide empty state for grid view
                if (!gridView.classList.contains('hidden')) {
                    emptyGridState.classList.toggle('hidden', visibleCount > 0);
                }
            }
            
            // Add event listeners for filters
            searchInput.addEventListener('input', applyFilters);
            categoryFilter.addEventListener('change', applyFilters);
            stockFilter.addEventListener('change', applyFilters);
            
            // Reset filters button
            resetFiltersBtn.addEventListener('click', function() {
                searchInput.value = '';
                categoryFilter.value = '';
                stockFilter.value = '';
                applyFilters();
            });
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
            
            // Animation pour les produits lors du chargement
            function animateProductsOnLoad() {
                const tableRows = document.querySelectorAll('#productsTableBody tr.product-row');
                const gridItems = document.querySelectorAll('.product-grid .product-card');
                
                // Animer les lignes du tableau
                tableRows.forEach((row, index) => {
                    row.style.opacity = '0';
                    row.style.transform = 'translateY(20px)';
                    
                    setTimeout(() => {
                        row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                        row.style.opacity = '1';
                        row.style.transform = 'translateY(0)';
                    }, 50 * index);
                });
                
                // Animer les cartes en vue grille
                gridItems.forEach((item, index) => {
                    item.style.opacity = '0';
                    item.style.transform = 'translateY(20px)';
                    
                    setTimeout(() => {
                        item.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                        item.style.opacity = '1';
                        item.style.transform = 'translateY(0)';
                    }, 50 * index);
                });
            }
            
            // Animer les produits au chargement
            animateProductsOnLoad();
            
            // Animation pour les suppressions de produits
            const deleteButtons = document.querySelectorAll('[data-bs-target^="#deleteModal"]');
            const deleteLinks = document.querySelectorAll('.modal-footer a.btn-danger');
            
            deleteLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const url = this.getAttribute('href');
                    const modal = bootstrap.Modal.getInstance(this.closest('.modal'));
                    const productId = url.split('=')[1];
                    
                    // Trouver les éléments de produit correspondants
                    const tableRow = document.querySelector(`#productsTableBody tr[data-product-id="${productId}"]`);
                    const gridItem = document.querySelector(`.product-grid .product-card[data-product-id="${productId}"]`);
                    
                    // Fermer le modal
                    modal.hide();
                    
                    // Animer la suppression si l'élément existe
                    if (tableRow) {
                        tableRow.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                        tableRow.style.opacity = '0';
                        tableRow.style.transform = 'translateX(20px)';
                    }
                    
                    if (gridItem) {
                        gridItem.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                        gridItem.style.opacity = '0';
                        gridItem.style.transform = 'scale(0.8)';
                    }
                    
                    // Rediriger après l'animation
                    setTimeout(() => {
                        window.location.href = url;
                    }, 500);
                });
            });
            
            // Effet d'apparition au défilement
            function revealOnScroll() {
                const elements = document.querySelectorAll('.filter-section, .table, .product-grid');
                
                elements.forEach(element => {
                    const elementTop = element.getBoundingClientRect().top;
                    const elementBottom = element.getBoundingClientRect().bottom;
                    const windowHeight = window.innerHeight;
                    
                    if (elementTop < windowHeight && elementBottom > 0) {
                        element.classList.add('visible');
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
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate__animated', 'animate__fadeInUp');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);
            
            document.querySelectorAll('.card, .filter-section').forEach(el => {
                observer.observe(el);
            });
            
            // Prévisualisation d'image améliorée
            const productImages = document.querySelectorAll('.product-image, .product-card-img');
            productImages.forEach(img => {
                if (img.src) {
                    img.addEventListener('click', function() {
                        const previewModal = new bootstrap.Modal(document.getElementById('imagePreviewModal') || createImagePreviewModal());
                        document.getElementById('previewImage').src = this.src;
                        document.getElementById('previewTitle').textContent = this.alt || 'Aperçu de l\'image';
                        previewModal.show();
                    });
                }
            });
            
            // Création dynamique du modal de prévisualisation d'image
            function createImagePreviewModal() {
                const modalHtml = `
                <div class="modal fade" id="imagePreviewModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content animate__animated animate__zoomIn">
                            <div class="modal-header">
                                <h5 class="modal-title" id="previewTitle">Aperçu de l'image</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body text-center">
                                <img id="previewImage" src="" alt="Aperçu" class="img-fluid rounded">
                            </div>
                        </div>
                    </div>
                </div>
                `;
                
                document.body.insertAdjacentHTML('beforeend', modalHtml);
                return document.getElementById('imagePreviewModal');
            }
            
            // Ajouter des ID de produit aux éléments pour la suppression animée
            document.querySelectorAll('#productsTableBody tr.product-row').forEach(row => {
                const productId = row.querySelector('a[href^="modif_produit.php?id="]')?.href.split('=')[1];
                if (productId) {
                    row.setAttribute('data-product-id', productId);
                }
            });
            
            document.querySelectorAll('.product-grid .product-card').forEach(card => {
                const productId = card.querySelector('a[href^="modif_produit.php?id="]')?.href.split('=')[1];
                if (productId) {
                    card.setAttribute('data-product-id', productId);
                }
            });
            
            // Effet de message d'état en haut de la page
            function showStatusMessage(message, type = 'success') {
                const statusBarId = 'statusBar';
                let statusBar = document.getElementById(statusBarId);
                
                if (!statusBar) {
                    statusBar = document.createElement('div');
                    statusBar.id = statusBarId;
                    statusBar.style.position = 'fixed';
                    statusBar.style.top = '20px';
                    statusBar.style.left = '50%';
                    statusBar.style.transform = 'translateX(-50%)';
                    statusBar.style.zIndex = '9999';
                    statusBar.style.borderRadius = '50px';
                    statusBar.style.padding = '10px 25px';
                    statusBar.style.boxShadow = '0 5px 15px rgba(0,0,0,0.2)';
                    statusBar.style.display = 'flex';
                    statusBar.style.alignItems = 'center';
                    statusBar.style.transition = 'all 0.3s ease';
                    document.body.appendChild(statusBar);
                }
                
                statusBar.className = type === 'success' ? 'bg-success text-white' : 'bg-danger text-white';
                statusBar.innerHTML = `
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                    <span>${message}</span>
                `;
                
                statusBar.style.opacity = '0';
                statusBar.style.transform = 'translate(-50%, -20px)';
                
                setTimeout(() => {
                    statusBar.style.opacity = '1';
                    statusBar.style.transform = 'translate(-50%, 0)';
                }, 100);
                
                setTimeout(() => {
                    statusBar.style.opacity = '0';
                    statusBar.style.transform = 'translate(-50%, -20px)';
                }, 3000);
            }
            
            // Vérifier s'il y a un message de succès dans l'URL (après redirection)
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success_message')) {
                showStatusMessage(decodeURIComponent(urlParams.get('success_message')));
            }
            
            // Fonctionnalité de sorting pour le tableau
            document.querySelectorAll('th').forEach(header => {
                if (header.textContent.trim() !== 'Actions') {
                    header.style.cursor = 'pointer';
                    header.dataset.order = 'asc'; // Par défaut, ordre croissant
                    
                    header.addEventListener('click', function() {
                        const columnIndex = Array.from(this.parentNode.children).indexOf(this);
                        const rows = Array.from(document.querySelectorAll('#productsTableBody tr.product-row'));
                        const order = this.dataset.order === 'asc' ? 'desc' : 'asc';
                        
                        // Mettre à jour l'indicateur visuel
                        document.querySelectorAll('th').forEach(th => {
                            th.classList.remove('sorting-asc', 'sorting-desc');
                        });
                        
                        this.classList.add(order === 'asc' ? 'sorting-asc' : 'sorting-desc');
                        this.dataset.order = order;
                        
                        // Trier les lignes
                        rows.sort((a, b) => {
                            let valueA = a.children[columnIndex].textContent.trim();
                            let valueB = b.children[columnIndex].textContent.trim();
                            
                            // Traitement spécial pour les nombres (prix, stock)
                            if (!isNaN(parseFloat(valueA.replace(/[^0-9.,]/g, '')))) {
                                valueA = parseFloat(valueA.replace(/[^0-9.,]/g, ''));
                                valueB = parseFloat(valueB.replace(/[^0-9.,]/g, ''));
                            }
                            
                            if (order === 'asc') {
                                return valueA > valueB ? 1 : -1;
                            } else {
                                return valueA < valueB ? 1 : -1;
                            }
                        });
                        
                        // Réinsérer les lignes dans l'ordre déterminé
                        const tbody = document.getElementById('productsTableBody');
                        rows.forEach(row => tbody.appendChild(row));
                        
                        // Animation pour montrer le changement
                        rows.forEach((row, index) => {
                            row.style.opacity = '0.5';
                            setTimeout(() => {
                                row.style.transition = 'opacity 0.3s ease';
                                row.style.opacity = '1';
                            }, 50 * index);
                        });
                    });
                }
            });
        });
    </script>
</body>
</html>