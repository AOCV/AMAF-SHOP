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

// Récupérer les catégories
$categories = [];
$query_categories = "SELECT id, nom FROM categorie";
$result_categories = $conn->query($query_categories);
if ($result_categories) {
    while ($row = $result_categories->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Vérifier si un ID de produit a été fourni
if (!isset($_GET['id'])) {
    header('Location: supprime_produit.php');
    exit();
}

$id_produit = intval($_GET['id']);

// Traitement du formulaire de modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $prix = floatval($_POST['prix'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $categorie_id = intval($_POST['categorie_id'] ?? 0);
    $taille = trim($_POST['taille'] ?? '');
    $couleur = trim($_POST['couleur'] ?? '');
    $marque = trim($_POST['marque'] ?? '');
    $promotion = !empty($_POST['promotion']) ? floatval($_POST['promotion']) : null;

    if (empty($nom) || empty($description) || $prix <= 0) {
        $error = "Veuillez remplir tous les champs obligatoires correctement.";
    } else {
        // Traitement des images
        $image_urls = [];
        $upload_dir = 'uploads/';
        
        // Créer le répertoire d'upload s'il n'existe pas
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Traiter chaque image
        for ($i = 1; $i <= 4; $i++) {
            $image_field = 'image' . ($i === 1 ? '' : $i);
            if (isset($_FILES[$image_field]) && $_FILES[$image_field]['error'] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES[$image_field]['tmp_name'];
                $name = basename($_FILES[$image_field]['name']);
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                
                // Vérifier le type de fichier
                if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $new_name = uniqid('product_') . '_' . $i . '.' . $extension;
                    $upload_path = $upload_dir . $new_name;
                    
                    if (move_uploaded_file($tmp_name, $upload_path)) {
                        $image_urls['image_url' . ($i === 1 ? '' : $i)] = $upload_path;
                    }
                }
            }
        }

        // Préparer la requête de mise à jour
        $update_fields = [
            "nom = ?",
            "description = ?",
            "prix = ?",
            "stock = ?",
            "categorie_id = ?",
            "taille = ?",
            "couleur = ?",
            "marque = ?",
            "promotion = ?"
        ];

        $params = [$nom, $description, $prix, $stock, $categorie_id, $taille, $couleur, $marque, $promotion];
        $types = "ssdiiissd";

        // Ajouter les champs d'image qui ont été mis à jour
        foreach ($image_urls as $field => $value) {
            $update_fields[] = "$field = ?";
            $params[] = $value;
            $types .= "s";
        }

        $query = "UPDATE produit SET " . implode(", ", $update_fields) . " WHERE id = ?";
        $params[] = $id_produit;
        $types .= "i";

        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $message = "Le produit a été modifié avec succès.";
            // Rediriger après 2 secondes
            header("refresh:2;url=supprime_produit.php");
        } else {
            $error = "Une erreur est survenue lors de la modification du produit.";
        }
    }
}

// Récupérer les informations du produit
$query = "SELECT * FROM produit WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_produit);
$stmt->execute();
$produit = $stmt->get_result()->fetch_assoc();

if (!$produit) {
    header('Location: supprime_produit.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un Produit - AMAF-SHOP</title>
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
            padding-bottom: 50px;
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
        
        .product-form-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            animation: fadeInUp 0.5s ease;
            border: none;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 10px;
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
        
        .btn-action {
            border-radius: 50px;
            padding: 12px 25px;
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
        
        .alert {
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 20px;
            animation: fadeInDown 0.5s ease;
        }
        
        .image-preview-container {
            position: relative;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            background-color: #f8f9fa;
            border: 2px dashed #e0e0e0;
            transition: var(--transition);
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .image-preview-container {
            margin-bottom: 1rem;
        }
        
        .img-preview {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .custom-file-upload {
            position: relative;
            overflow: hidden;
        }

        .custom-file-upload input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .custom-file-upload label {
            margin: 0;
            cursor: pointer;
            padding: 8px 16px;
            display: inline-block;
            width: 100%;
            text-align: center;
        }
        
        .image-preview:hover {
            transform: scale(1.02);
        }
        
        .custom-file-label {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 10px;
            text-align: center;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            opacity: 0;
        }
        
        .image-preview-container:hover .custom-file-label {
            opacity: 1;
        }
        
        .custom-file-input {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 10;
        }
        
        .form-section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-light);
            color: var(--dark);
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .color-preview {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-block;
            margin-left: 10px;
            border: 2px solid #e0e0e0;
            vertical-align: middle;
        }
        
        .price-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            border-left: 5px solid var(--primary);
        }
        
        .price-label {
            font-size: 14px;
            color: var(--gray);
        }
        
        .price-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
        }
        
        .discount-value {
            font-size: 16px;
            color: var(--success);
        }
        
        .promotion-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--danger);
            color: white;
            padding: 5px 15px;
            border-radius: 30px;
            font-weight: 500;
            box-shadow: var(--shadow);
            z-index: 10;
        }
        
        .metadata-item {
            display: inline-block;
            margin-right: 15px;
            margin-bottom: 10px;
            background: #f5f7fa;
            padding: 8px 15px;
            border-radius: 30px;
            font-size: 14px;
            color: var(--dark);
        }
        
        .metadata-item i {
            color: var(--primary);
            margin-right: 5px;
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
        
        .btn-float {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 1000;
            transition: var(--transition);
        }
        
        .btn-float:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.3);
        }
        
        .input-group-text {
            background: #f5f7fa;
            border-radius: 10px 0 0 10px;
            border: 1px solid #e0e0e0;
            padding: 0 15px;
        }
        
        /* Animations */
        .animated-container {
            animation-duration: 0.5s;
        }
        
        .input-counter {
            position: absolute;
            right: 10px;
            bottom: -20px;
            font-size: 12px;
            color: var(--gray);
        }
        
        .tooltip .tooltip-inner {
            background-color: var(--dark);
            padding: 8px 15px;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container mt-5 slide-in">
        <!-- Header with product info -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1"><i class="fas fa-edit me-2"></i>Modifier un Produit</h2>
                    <p class="mb-0"><?= htmlspecialchars($produit['nom']) ?> - <?= htmlspecialchars($produit['categorie_nom'] ?? 'Sans catégorie') ?></p>
                </div>
                <div>
                    <?php if ($produit['promotion']): ?>
                        <span class="badge bg-danger fs-6 mb-2">
                            <i class="fas fa-tag me-1"></i>Promotion: 
                            <?= number_format(($produit['promotion'] / $produit['prix']) * 100, 0) ?>%
                        </span>
                    <?php endif; ?>
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
        
        <!-- Product metadata summary -->
        <div class="d-flex flex-wrap mb-4">
            <div class="metadata-item animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
                <i class="fas fa-cubes"></i> Stock: <?= $produit['stock'] ?>
            </div>
            <div class="metadata-item animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
                <i class="fas fa-money-bill-wave"></i> Prix: <?= number_format($produit['prix'], 0) ?> CFA
            </div>
            <?php if ($produit['marque']): ?>
                <div class="metadata-item animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
                    <i class="fas fa-trademark"></i> Marque: <?= htmlspecialchars($produit['marque']) ?>
                </div>
            <?php endif; ?>
            <?php if ($produit['taille']): ?>
                <div class="metadata-item animate__animated animate__fadeInUp" style="animation-delay: 0.4s;">
                    <i class="fas fa-ruler"></i> Taille: <?= htmlspecialchars($produit['taille']) ?>
                </div>
            <?php endif; ?>
            <?php if ($produit['couleur']): ?>
                <div class="metadata-item animate__animated animate__fadeInUp" style="animation-delay: 0.5s;">
                    <i class="fas fa-palette"></i> Couleur: <?= htmlspecialchars($produit['couleur']) ?>
                </div>
            <?php endif; ?>
        </div>

        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            <div class="row">
                <!-- Left column - Form fields -->
                <div class="col-lg-8">
                    <div class="product-form-card">
                        <div class="form-section">
                            <h3 class="form-section-title"><i class="fas fa-info-circle me-2"></i>Informations générales</h3>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="nom" class="form-label required-field">Nom du produit</label>
                                    <input type="text" class="form-control" id="nom" name="nom" 
                                           value="<?= htmlspecialchars($produit['nom']) ?>" required>
                                    <div class="invalid-feedback">Veuillez entrer un nom pour le produit.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="categorie_id" class="form-label">Catégorie</label>
                                    <select class="form-select" id="categorie_id" name="categorie_id">
                                        <option value="">Sélectionner une catégorie</option>
                                        <?php foreach ($categories as $categorie): ?>
                                            <option value="<?= $categorie['id'] ?>" 
                                                    <?= $produit['categorie_id'] == $categorie['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($categorie['nom']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="description" class="form-label required-field">Description</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="4" required><?= htmlspecialchars($produit['description']) ?></textarea>
                                <div class="input-counter">
                                    <span id="descriptionCount">0</span>/500 caractères
                                </div>
                                <div class="invalid-feedback">Veuillez entrer une description pour le produit.</div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3 class="form-section-title"><i class="fas fa-tags me-2"></i>Prix et stock</h3>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="prix" class="form-label required-field">Prix (CFA)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-money-bill"></i></span>
                                        <input type="number" class="form-control" id="prix" name="prix" 
                                               step="0.01" value="<?= $produit['prix'] ?>" required>
                                        <span class="input-group-text">CFA</span>
                                    </div>
                                    <div class="invalid-feedback">Veuillez entrer un prix valide.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="promotion" class="form-label">Promotion (CFA)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-percentage"></i></span>
                                        <input type="number" class="form-control" id="promotion" name="promotion" 
                                               step="0.01" value="<?= $produit['promotion'] ?>">
                                        <span class="input-group-text">CFA</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="stock" class="form-label">Stock disponible</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-cubes"></i></span>
                                    <input type="number" class="form-control" id="stock" name="stock" 
                                           value="<?= $produit['stock'] ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3 class="form-section-title"><i class="fas fa-cog me-2"></i>Attributs additionnels</h3>
                            <div class="row">
                                <div class="col-md-4 mb-4">
                                    <label for="marque" class="form-label">Marque</label>
                                    <input type="text" class="form-control" id="marque" name="marque" 
                                           value="<?= htmlspecialchars($produit['marque']) ?>">
                                </div>
                                <div class="col-md-4 mb-4">
                                    <label for="taille" class="form-label">Taille</label>
                                    <input type="text" class="form-control" id="taille" name="taille" 
                                           value="<?= htmlspecialchars($produit['taille']) ?>">
                                </div>
                                <div class="col-md-4 mb-4">
                                    <label for="couleur" class="form-label">Couleur</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="couleur" name="couleur" 
                                               value="<?= htmlspecialchars($produit['couleur']) ?>">
                                        <span class="input-group-text p-0">
                                            <input type="color" class="form-control form-control-color h-100 border-0" 
                                                   id="colorPicker" title="Choisir une couleur">
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right column - Image preview and price summary -->
                <div class="col-lg-4">
                    <div class="product-form-card">
                        <h3 class="form-section-title"><i class="fas fa-images me-2"></i>Images du produit</h3>
                        
                        <!-- Image 1 (Principale) -->
                        <div class="mb-4">
                            <label class="form-label">Image principale</label>
                            <div class="image-preview-container">
                                <img src="<?= htmlspecialchars($produit['image_url'] ?? 'assets/images/default-product.jpg') ?>" 
                                     alt="Image principale" 
                                     class="img-preview mb-2" 
                                     id="preview1">
                                <div class="custom-file-upload">
                                    <input type="file" name="image" class="form-control" id="image1" 
                                           accept="image/*" onchange="previewImage(this, 'preview1')">
                                    <label for="image1" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-upload me-2"></i>Choisir une image
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Image 2 -->
                        <div class="mb-4">
                            <label class="form-label">Image secondaire 1</label>
                            <div class="image-preview-container">
                                <img src="<?= htmlspecialchars($produit['image_url2'] ?? 'assets/images/default-product.jpg') ?>" 
                                     alt="Image secondaire 1" 
                                     class="img-preview mb-2" 
                                     id="preview2">
                                <div class="custom-file-upload">
                                    <input type="file" name="image2" class="form-control" id="image2" 
                                           accept="image/*" onchange="previewImage(this, 'preview2')">
                                    <label for="image2" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-upload me-2"></i>Choisir une image
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Image 3 -->
                        <div class="mb-4">
                            <label class="form-label">Image secondaire 2</label>
                            <div class="image-preview-container">
                                <img src="<?= htmlspecialchars($produit['image_url3'] ?? 'assets/images/default-product.jpg') ?>" 
                                     alt="Image secondaire 2" 
                                     class="img-preview mb-2" 
                                     id="preview3">
                                <div class="custom-file-upload">
                                    <input type="file" name="image3" class="form-control" id="image3" 
                                           accept="image/*" onchange="previewImage(this, 'preview3')">
                                    <label for="image3" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-upload me-2"></i>Choisir une image
                                    </label>
                                </div>
                            </div>
                        </div>


                    <!-- Prix et promotion -->
                    <div class="product-form-card mt-4">
                        <h3 class="form-section-title mb-3">
                            <i class="fas fa-tag me-2"></i>Résumé de prix
                        </h3>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="price-label">Prix de base</span>
                            <span class="price-value" id="displayPrice"><?= number_format($produit['prix'], 0) ?> CFA</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3" id="promotionDisplay" <?= !$produit['promotion'] ? 'style="display:none;"' : '' ?>>
                            <span class="price-label">Réduction</span>
                            <span class="discount-value" id="displayPromotion">
                                -<?= $produit['promotion'] ? number_format($produit['promotion'], 0) : '0' ?> CFA
                            </span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="price-label fw-bold">Prix final</span>
                            <span class="price-value text-success" id="displayFinalPrice">
                                <?= number_format($produit['prix'] - ($produit['promotion'] ?? 0), 0) ?> CFA
                            </span>
                        </div>
                    </div>
                        
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-action animate__animated animate__pulse">
                            <i class="fas fa-save me-2"></i>Enregistrer les modifications
                        </button>
                        <a href="supprime_produit.php" class="btn btn-secondary btn-action">
                            <i class="fas fa-times me-2"></i>Annuler
                        </a>
                    </div>
                </div>
            </div>
        </form>
        
        <!-- Floating back button -->
        <a href="supprime_produit.php" class="btn btn-secondary btn-float" data-bs-toggle="tooltip" title="Retour à la liste">
            <i class="fas fa-arrow-left"></i>
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    placement: 'top'
                });
            });
            
            // Form validation
            const form = document.querySelector('.needs-validation');
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                    
                    // Scroll to the first invalid element
                    const firstInvalid = form.querySelector(':invalid');
                    if (firstInvalid) {
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
                form.classList.add('was-validated');
            });
            
            // Image preview
            const imageInput = document.getElementById('image');
            const imagePreview = document.getElementById('imagePreview');
            const imagePreviewContainer = document.querySelector('.image-preview-container');
            
            imageInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        if (!imagePreview) {
                            // Create image element if it doesn't exist
                            const img = document.createElement('img');
                            img.id = 'imagePreview';
                            img.className = 'image-preview';
                            img.src = e.target.result;
                            img.alt = 'Aperçu de l\'image';
                            
                            // Remove existing content and add the image
                            imagePreviewContainer.innerHTML = '';
                            imagePreviewContainer.appendChild(img);
                            
                            // Add the custom file label back
                            const label = document.createElement('label');
                            label.htmlFor = 'image';
                            label.className = 'custom-file-label';
                            label.innerHTML = '<i class="fas fa-upload me-2"></i>Changer l\'image';
                            imagePreviewContainer.appendChild(label);
                            
                            // Add the input back
                            imagePreviewContainer.appendChild(imageInput);
                        } else {
                            imagePreview.src = e.target.result;
                        }
                        
                        // Add animation
                        imagePreviewContainer.classList.add('animate__animated', 'animate__fadeIn');
                        setTimeout(() => {
                            imagePreviewContainer.classList.remove('animate__animated', 'animate__fadeIn');
                        }, 1000);
                    };
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
            
            // Description character counter
            const descriptionTextarea = document.getElementById('description');
            const descriptionCount = document.getElementById('descriptionCount');
            
            if (descriptionTextarea && descriptionCount) {
                const updateDescriptionCount = () => {
                    const currentLength = descriptionTextarea.value.length;
                    descriptionCount.textContent = currentLength;
                    
                    if (currentLength > 500) {
                        descriptionCount.classList.add('text-danger');
                    } else {
                        descriptionCount.classList.remove('text-danger');
                    }
                };
                
                updateDescriptionCount(); // Initial count
                descriptionTextarea.addEventListener('input', updateDescriptionCount);
            }
            
            // Price and promotion calculation
            const priceInput = document.getElementById('prix');
            const promotionInput = document.getElementById('promotion');
            const displayPrice = document.getElementById('displayPrice');
            const displayPromotion = document.getElementById('displayPromotion');
            const displayFinalPrice = document.getElementById('displayFinalPrice');
            const promotionDisplay = document.getElementById('promotionDisplay');
            
            const updatePriceDisplay = () => {
                const price = parseFloat(priceInput.value) || 0;
                const promotion = parseFloat(promotionInput.value) || 0;
                
                displayPrice.textContent = price.toLocaleString() + ' CFA';
                
                if (promotion > 0 && promotion < price) {
                    promotionDisplay.style.display = 'flex';
                    displayPromotion.textContent = '-' + promotion.toLocaleString() + ' CFA';
                    displayFinalPrice.textContent = (price - promotion).toLocaleString() + ' CFA';
                } else {
                    promotionDisplay.style.display = 'none';
                    displayFinalPrice.textContent = price.toLocaleString() + ' CFA';
                }
                
                // Animation for price change
                displayFinalPrice.classList.add('animate__animated', 'animate__pulse');
                setTimeout(() => {
                    displayFinalPrice.classList.remove('animate__animated', 'animate__pulse');
                }, 1000);
            };
            
            priceInput.addEventListener('input', updatePriceDisplay);
            promotionInput.addEventListener('input', updatePriceDisplay);
            
            // Color picker
            const colorPicker = document.getElementById('colorPicker');
            const colorInput = document.getElementById('couleur');
            
            if (colorPicker && colorInput) {
                // Set initial color if possible
                const initialColor = colorInput.value.trim();
                if (initialColor && /^#[0-9A-F]{6}$/i.test(initialColor)) {
                    colorPicker.value = initialColor;
                }
                
                colorPicker.addEventListener('input', function() {
                    colorInput.value = this.value;
                });
                
                colorInput.addEventListener('input', function() {
                    // Try to set color picker if the input looks like a color code
                    const inputColor = this.value.trim();
                    if (/^#[0-9A-F]{6}$/i.test(inputColor)) {
                        colorPicker.value = inputColor;
                    }
                });
            }
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
            
            // Animations on scroll
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
            
            document.querySelectorAll('.form-section').forEach(el => {
                observer.observe(el);
            });
        });
        
        // Fonction pour prévisualiser les images
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Rendre la fonction previewImage globale
        window.previewImage = previewImage;
    </script>
</body>
</html>