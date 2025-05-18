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

    // Traitement des images
    $image_url = null;
    $image_url2 = null;
    $image_url3 = null;

    // Fonction pour gérer l'upload des images
    function uploadImage($input_name) {
        if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = strtolower(pathinfo($_FILES[$input_name]['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = uniqid() . '.' . $file_extension;
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES[$input_name]['tmp_name'], $destination)) {
                    return $destination;
                }
            }
        }
        return null;
    }

    // Récupérer les URLs des images
    $image_url = uploadImage('image_url');
    $image_url2 = uploadImage('image_url2');
    $image_url3 = uploadImage('image_url3');

    if (empty($nom) || empty($description) || $prix <= 0 || $stock < 0) {
        $error = "Veuillez remplir tous les champs obligatoires correctement.";
    } else {
        $sql = "INSERT INTO produit (nom, description, prix, stock, categorie_id, taille, couleur, marque, promotion, image_url, image_url2, image_url3) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdiisssdsss", $nom, $description, $prix, $stock, $categorie_id, $taille, $couleur, $marque, $promotion, $image_url, $image_url2, $image_url3);

        if ($stmt->execute()) {
            $message = "Produit ajouté avec succès !";
            // Réinitialiser les valeurs du formulaire après succès
            $nom = $description = $taille = $couleur = $marque = '';
            $prix = $stock = $categorie_id = 0;
            $promotion = null;
        } else {
            $error = "Erreur lors de l'ajout du produit : " . $conn->error;
        }
        $stmt->close();
    }
}

// Récupérer les catégories pour le formulaire
$categories = [];
$query = "SELECT id, nom FROM categorie";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}
?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Produit - AMAF-SHOP</title>
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
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
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
            border-radius: 10px;
            padding: 15px;
            box-shadow: var(--shadow);
            margin-bottom: 15px;
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
            border-radius: 6px;
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            transition: var(--transition);
            box-shadow: none;
            font-size: 0.9rem;
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
        
        .image-preview-container:hover {
            border-color: var(--primary);
        }
        
        .image-preview {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: var(--transition);
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
        
        /* Guide d'information */
        .info-card {
            background: linear-gradient(135deg, #3498db, #1abc9c);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
        }
        
        .info-card h4 {
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .info-card ul {
            padding-left: 20px;
        }
        
        .info-card li {
            margin-bottom: 8px;
        }
        
        .dropzone {
            border: 2px dashed #ccc;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background-color: #f9f9f9;
        }
        
        .dropzone:hover, .dropzone.dragover {
            border-color: var(--primary);
            background-color: rgba(58, 123, 213, 0.05);
        }
        
        .dropzone i {
            font-size: 48px;
            color: #ccc;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .dropzone:hover i {
            color: var(--primary);
        }
        
        .dropzone p {
            color: #777;
            margin-bottom: 0;
        }
        
        /* Styles pour l'upload d'images */
        .image-upload-container {
            position: relative;
            width: 100%;
            margin-bottom: 15px;
        }

        .image-preview {
            width: 100%;
            height: 200px;
            border: 2px dashed var(--primary);
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            cursor: pointer;
            transition: var(--transition);
            overflow: hidden;
        }

        .image-preview:hover {
            border-color: var(--primary-light);
            background-color: #e9ecef;
        }

        .image-preview i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .image-preview p {
            margin: 0;
            color: var(--gray);
            font-size: 0.9rem;
        }

        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none;
        }

        .image-preview.has-image img {
            display: block;
        }

        .image-preview.has-image i,
        .image-preview.has-image p {
            display: none;
        }

        input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container mt-5 slide-in">
        <!-- Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1"><i class="fas fa-plus-circle me-2"></i>Ajouter un Produit</h2>
                    <p class="mb-0">Créez un nouveau produit pour votre boutique</p>
                </div>
                <div>
                    <span class="badge bg-light text-dark">
                        <i class="fas fa-box me-1"></i> Nouveau produit
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

        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            <div class="row">
                <!-- Left column - Form fields -->
                <div class="col-lg-8">
                    <div class="product-form-card">
                        <div class="form-section">
                            <h5 class="form-section-title"><i class="fas fa-info-circle me-2"></i>Informations générales</h5>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="nom" class="form-label required-field">Nom du produit</label>
                                    <input type="text" class="form-control" id="nom" name="nom" value="<?= htmlspecialchars($nom ?? '') ?>" required>
                                    <div class="invalid-feedback">Veuillez entrer un nom pour le produit.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="categorie_id" class="form-label">Catégorie</label>
                                    <select class="form-select" id="categorie_id" name="categorie_id">
                                        <option value="">Sélectionner une catégorie</option>
                                        <?php foreach ($categories as $categorie): ?>
                                            <option value="<?= $categorie['id'] ?>" <?= isset($categorie_id) && $categorie_id == $categorie['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($categorie['nom']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="description" class="form-label required-field">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4" required><?= htmlspecialchars($description ?? '') ?></textarea>
                                <div class="input-counter">
                                    <span id="descriptionCount">0</span>/500 caractères
                                </div>
                                <div class="invalid-feedback">Veuillez entrer une description pour le produit.</div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h5 class="form-section-title"><i class="fas fa-tags me-2"></i>Prix et stock</h5>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="prix" class="form-label required-field">Prix (CFA)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-money-bill"></i></span>
                                        <input type="number" class="form-control" id="prix" name="prix" step="0.01" value="<?= $prix ?? '' ?>" required>
                                        <span class="input-group-text">CFA</span>
                                    </div>
                                    <div class="invalid-feedback">Veuillez entrer un prix valide.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="promotion" class="form-label">Promotion (CFA)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-percentage"></i></span>
                                        <input type="number" class="form-control" id="promotion" name="promotion" step="0.01" value="<?= $promotion ?? '' ?>">
                                        <span class="input-group-text">CFA</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="stock" class="form-label required-field">Stock disponible</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-cubes"></i></span>
                                    <input type="number" class="form-control" id="stock" name="stock" value="<?= $stock ?? '' ?>" required>
                                </div>
                                <div class="invalid-feedback">Veuillez entrer une quantité de stock valide.</div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h5 class="form-section-title"><i class="fas fa-cog me-2"></i>Attributs additionnels</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="marque" class="form-label">Marque</label>
                                    <input type="text" class="form-control" id="marque" name="marque" value="<?= htmlspecialchars($marque ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="taille" class="form-label">Taille</label>
                                    <input type="text" class="form-control" id="taille" name="taille" value="<?= htmlspecialchars($taille ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="couleur" class="form-label">Couleur</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="couleur" name="couleur" value="<?= htmlspecialchars($couleur ?? '') ?>">
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
                        <div class="form-section">
                            <h5 class="form-section-title"><i class="fas fa-images me-2"></i>Images du produit</h5>
                            
                            <!-- Image principale -->
                            <div class="mb-4">
                                <label class="form-label required-field">Image principale</label>
                                <div class="image-upload-container">
                                    <div class="image-preview mb-3" id="imagePreview" style="background-image: url(''); height: 150px;">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <p>Cliquez ou déposez une image</p>
                                    </div>
                                    <input type="file" name="image_url" class="form-control" id="image_url" accept="image/*" required>
                                </div>
                            </div>

                            <!-- Image secondaire 1 -->
                            <div class="mb-4">
                                <label class="form-label">Image secondaire 1</label>
                                <div class="image-upload-container">
                                    <div class="image-preview" id="imagePreview2">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <p>Cliquez ou déposez une image</p>
                                    </div>
                                    <input type="file" name="image_url1" class="form-control" id="image_url1" accept="image/*">
                                </div>
                            </div>

                            <!-- Image secondaire 2 -->
                            <div class="mb-4">
                                <label class="form-label">Image secondaire 2</label>
                                <div class="image-upload-container">
                                    <div class="image-preview" id="imagePreview3">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <p>Cliquez ou déposez une image</p>
                                    </div>
                                    <input type="file" name="image_url2" class="form-control" id="image_url2" accept="image/*">
                                </div>
                            </div>
                        </div>
                        
                        <div class="price-card mt-4">
                            <h3 class="form-section-title mb-3">
                                <i class="fas fa-tag me-2"></i>Résumé de prix
                            </h3>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="price-label">Prix de base</span>
                                <span class="price-value" id="displayPrice">0 CFA</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3" id="promotionDisplay" style="display:none;">
                                <span class="price-label">Réduction</span>
                                <span class="discount-value" id="displayPromotion">-0 CFA</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="price-label fw-bold">Prix final</span>
                                <span class="price-value text-success" id="displayFinalPrice">0 CFA</span>
                            </div>
                        </div>
                        
                        <!-- Info card -->
                        <div class="info-card mt-4">
                            <h4><i class="fas fa-lightbulb me-2"></i>Conseils</h4>
                            <ul>
                                <li>Utilisez un nom de produit clair et descriptif</li>
                                <li>Ajoutez une image de haute qualité</li>
                                <li>Détaillez les caractéristiques du produit</li>
                                <li>Définissez un prix compétitif sur le marché</li>
                            </ul>
                        </div>
                        
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-action animate__animated animate__pulse">
                                <i class="fas fa-plus-circle me-2"></i>Ajouter le produit
                            </button>
                            <a href="supprime_produit.php" class="btn btn-secondary btn-action">
                                <i class="fas fa-times me-2"></i>Annuler
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        
        <!-- Floating back button -->
        <a href="admin/dashboard.php" class="btn btn-secondary btn-float" data-bs-toggle="tooltip" title="Retour au tableau de bord">
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
            
            // Gestion des prévisualisations d'images
            function handleImagePreview(input, previewId) {
                const preview = document.getElementById(previewId);
                
                input.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            const img = preview.querySelector('img') || document.createElement('img');
                            img.src = e.target.result;
                            if (!preview.querySelector('img')) {
                                preview.appendChild(img);
                            }
                            preview.classList.add('has-image');
                        }
                        
                        reader.readAsDataURL(file);
                    } else {
                        preview.classList.remove('has-image');
                        const img = preview.querySelector('img');
                        if (img) {
                            img.remove();
                        }
                    }
                });
            }

            // Initialiser les prévisualisations d'images
            handleImagePreview(document.getElementById('image_url'), 'imagePreview');
            handleImagePreview(document.getElementById('image_url1'), 'imagePreview2');
            handleImagePreview(document.getElementById('image_url2'), 'imagePreview3');
            
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
            
            // Update price display on load
            updatePriceDisplay();
            
            // Color picker
            const colorPicker = document.getElementById('colorPicker');
            const colorInput = document.getElementById('couleur');
            
            if (colorPicker && colorInput) {
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
            
            // Optional: Reset form button
            const addResetButton = () => {
                const submitButton = document.querySelector('button[type="submit"]');
                const resetBtn = document.createElement('button');
                resetBtn.type = 'button';
                resetBtn.className = 'btn btn-outline-secondary mt-2';
                resetBtn.innerHTML = '<i class="fas fa-redo me-2"></i>Réinitialiser le formulaire';
                
                resetBtn.addEventListener('click', () => {
                    form.reset();
                    imagePreview.classList.add('d-none');
                    dropzone.classList.remove('d-none');
                    updatePriceDisplay();
                    form.classList.remove('was-validated');
                    
                    // Animation de confirmation
                    resetBtn.innerHTML = '<i class="fas fa-check me-2"></i>Formulaire réinitialisé';
                    resetBtn.classList.add('btn-success');
                    resetBtn.classList.remove('btn-outline-secondary');
                    
                    setTimeout(() => {
                        resetBtn.innerHTML = '<i class="fas fa-redo me-2"></i>Réinitialiser le formulaire';
                        resetBtn.classList.add('btn-outline-secondary');
                        resetBtn.classList.remove('btn-success');
                    }, 2000);
                });
                
                submitButton.parentNode.insertBefore(resetBtn, submitButton.nextSibling);
            };
            
            // Uncomment to add reset button
            // addResetButton();
        });
    </script>
</body>
</html>