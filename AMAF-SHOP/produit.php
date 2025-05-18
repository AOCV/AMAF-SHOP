<?php
session_start();
require 'config.php';

// Récupération de l'ID du produit
$produit_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Vérification que l'ID est valide
if ($produit_id <= 0) {
    header('Location: index.php?error=Produit non trouvé');
    exit;
}
// var_dump($_SESSION['panier']);
// exit();

// Récupération des données du produit
$query = "SELECT p.*, c.nom as categorie_nom 
          FROM produit p 
          LEFT JOIN categorie c ON p.categorie_id = c.id 
          WHERE p.id = ?";
          
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $produit_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php?error=Produit non trouvé');
    exit;
}

$produit = $result->fetch_assoc();

// Récupération des produits similaires
$query_similaires = "SELECT p.*, c.nom as categorie_nom 
                    FROM produit p 
                    LEFT JOIN categorie c ON p.categorie_id = c.id 
                    WHERE p.categorie_id = ? AND p.id != ? 
                    ORDER BY RAND() 
                    LIMIT 4";
                    
$stmt_similaires = $conn->prepare($query_similaires);
$stmt_similaires->bind_param("ii", $produit['categorie_id'], $produit_id);
$stmt_similaires->execute();
$result_similaires = $stmt_similaires->get_result();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($produit['nom']) ?> - AMAF-SHOP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary-color: #3a7bd5;
            --secondary-color: #00d2ff;
            --accent-color: #ff6b6b;
            --dark-color: #2c3e50;
            --light-color: #f8f9fa;
            --text-color: #333;
            --success-color: #2ecc71;
            --card-shadow: 0 10px 20px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            color: var(--text-color);
            background-color: #f5f7fa;
            line-height: 1.6;
        }
        
        /* Barre de navigation */
        .navbar {
            background: linear-gradient(to right, var(--dark-color), var(--primary-color)) !important;
            padding: 12px 0;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .cart-icon {
            position: relative;
        }
        
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            font-size: 0.7rem;
            padding: 0.25rem 0.4rem;
            border-radius: 50%;
            background-color: var(--accent-color);
            animation: pulse 2s infinite;
        }
        
        /* Page produit */
        .product-detail-section {
            padding: 80px 0;
            background: #f9f9f9;
        }
        
        .breadcrumb-section {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            padding: 20px 0;
            color: white;
        }
        
        .breadcrumb-item + .breadcrumb-item::before {
            color: white;
        }
        
        .breadcrumb-item a {
            color: white;
            opacity: 0.8;
            transition: var(--transition);
        }
        
        .breadcrumb-item a:hover {
            opacity: 1;
        }
        
        .breadcrumb-item.active {
            color: white;
        }
        
        .product-detail-img-container {
            position: relative;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            height: 400px;
            margin-bottom: 20px;
        }
        
        .product-detail-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .product-detail-img-container:hover .product-detail-img {
            transform: scale(1.05);
        }
        
        .product-zoom-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .product-detail-img-container:hover .product-zoom-overlay {
            opacity: 1;
        }
        
        .zoom-icon {
            background: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--dark-color);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: scale(0.8);
            transition: transform 0.3s ease;
        }
        
        .product-detail-img-container:hover .zoom-icon {
            transform: scale(1);
        }
        
        .product-thumbnails {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }
        
        .thumbnail-item {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            overflow: hidden;
            cursor: pointer;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            transition: var(--transition);
        }
        
        .thumbnail-item:hover {
            transform: translateY(-5px);
        }
        
        .thumbnail-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-detail-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--dark-color);
            animation: fadeInUp 0.5s ease;
        }
        
        .product-detail-price {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            animation: fadeInUp 0.7s ease;
        }
        
        .product-detail-old-price {
            text-decoration: line-through;
            font-size: 1.2rem;
            color: #6c757d;
            margin-right: 15px;
        }
        
        .product-detail-discount {
            background: var(--accent-color);
            color: white;
            padding: 5px 10px;
            border-radius: 25px;
            font-size: 0.9rem;
            animation: pulse 2s infinite;
        }
        
        .product-detail-description {
            margin: 30px 0;
            line-height: 1.8;
            color: #555;
            animation: fadeInUp 0.9s ease;
        }
        
        .product-detail-meta {
            margin-bottom: 30px;
            animation: fadeInUp 1.1s ease;
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
        }
        
        .product-detail-meta div {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        
        .product-detail-meta i {
            width: 25px;
            color: var(--primary-color);
        }
        
        .product-detail-quantity {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            animation: fadeInUp 1.3s ease;
        }
        
        .quantity-input {
            width: 80px;
            text-align: center;
            margin: 0 10px;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 5px;
            font-weight: 600;
        }
        
        .quantity-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f1f1f1;
            border: none;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .quantity-btn:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .product-detail-actions {
            display: flex;
            gap: 15px;
            animation: fadeInUp 1.5s ease;
        }
        
        .product-detail-actions .btn {
            flex: 1;
            padding: 12px 0;
            font-weight: 600;
            border-radius: 30px;
        }
        
        .btn-add-to-cart {
            background: var(--success-color);
            color: white;
            border: none;
            transition: var(--transition);
        }
        
        .btn-add-to-cart:hover {
            background: #27ae60;
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.4);
            transform: translateY(-3px);
        }
        
        .btn-wishlist {
            background: white;
            color: var(--accent-color);
            border: 1px solid var(--accent-color);
            transition: var(--transition);
        }
        
        .btn-wishlist:hover {
            background: var(--accent-color);
            color: white;
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.4);
            transform: translateY(-3px);
        }
        
        .product-tabs {
            margin-top: 60px;
        }
        
        .nav-tabs {
            border-bottom: 2px solid #eee;
            margin-bottom: 30px;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #777;
            font-weight: 600;
            padding: 15px 25px;
            border-radius: 0;
            position: relative;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            background: transparent;
        }
        
        .nav-tabs .nav-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 3px;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            transition: var(--transition);
        }
        
        .nav-tabs .nav-link.active::after,
        .nav-tabs .nav-link:hover::after {
            width: 100%;
        }
        
        .tab-content {
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
        }
        
        .similar-products-section {
            padding: 60px 0;
        }
        
        .section-title {
            position: relative;
            font-weight: 700;
            margin-bottom: 2.5rem;
            color: var(--dark-color);
            text-align: center;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            border-radius: 4px;
        }
        
        /* Animations */
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
        
        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }
        
        /* Lightbox */
        .lightbox {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .lightbox.active {
            opacity: 1;
            visibility: visible;
        }
        
        .lightbox-content {
            max-width: 90%;
            max-height: 90%;
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }
        
        .lightbox.active .lightbox-content {
            transform: scale(1);
        }
        
        .lightbox img {
            max-width: 100%;
            max-height: 90vh;
            border-radius: 5px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
        }
        
        .lightbox-close {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--dark-color);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .lightbox-close:hover {
            background: var(--accent-color);
            color: white;
            transform: rotate(90deg);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .product-detail-img-container {
                height: 300px;
            }
            
            .product-detail-title {
                font-size: 1.5rem;
            }
            
            .product-detail-price {
                font-size: 1.5rem;
            }
            
            .product-detail-actions {
                flex-direction: column;
            }
            
            .thumbnail-item {
                width: 60px;
                height: 60px;
            }
        }
    </style>
</head>
<body>
    <!-- Barre de navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand animate__animated animate__fadeIn" href="index.php">
                <i class="fas fa-store me-2"></i>AMAF-SHOP
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Accueil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#nouveautes">Nouveautés</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#promotions">Promotions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#contact">Contact</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item me-2">
                        <a class="nav-link cart-icon" href="panier.php">
                            <i class="fas fa-shopping-cart"></i>
                            <?php if (!empty($_SESSION['panier'])): ?>
                                <span class="cart-badge badge bg-danger">
                                    <?= count($_SESSION['panier']) ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($_SESSION['user_name']) ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end animate__animated animate__fadeIn">
                                <li><a class="dropdown-item" href="profil.php"><i class="fas fa-user me-2"></i>Mon Profil</a></li>
                                <li><a class="dropdown-item" href="mes_commandes.php"><i class="fas fa-shopping-bag me-2"></i>Mes Commandes</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Déconnexion</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item me-2">
                            <a class="nav-link" href="login.php">
                                <i class="fas fa-sign-in-alt me-1"></i>Connexion
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-outline-light btn-sm animate__animated animate__pulse animate__infinite" href="register.php">
                                <i class="fas fa-user-plus me-1"></i>Inscription
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Fil d'Ariane -->
    <section class="breadcrumb-section">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
                    <?php if (!empty($produit['categorie_nom'])): ?>
                        <li class="breadcrumb-item"><a href="index.php?categorie=<?= $produit['categorie_id'] ?>"><?= htmlspecialchars($produit['categorie_nom']) ?></a></li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($produit['nom']) ?></li>
                </ol>
            </nav>
        </div>
    </section>
    
    <!-- Détail du produit -->
    <section class="product-detail-section">
        <div class="container">
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="product-detail-img-container animate__animated animate__fadeIn">
                        <img src="<?= !empty($produit['image_url']) ? htmlspecialchars($produit['image_url']) : '' ?>" 
                             class="product-detail-img" 
                             alt="<?= htmlspecialchars($produit['nom']) ?>" 
                             id="mainProductImage">
                        <div class="product-zoom-overlay">
                            <div class="zoom-icon" id="zoomBtn">
                                <i class="fas fa-search-plus"></i>
                            </div>
                        </div>
                    </div>
                    <div class="product-thumbnails">
        <?php 
        $imageFields = ['image_url', 'image_url2', 'image_url3', 'image_url4']; 
        foreach ($imageFields as $index => $field) {
            if (!empty($produit[$field])) { ?>
                <div class="thumbnail-item <?= $index === 0 ? 'active' : '' ?>" data-src="<?= htmlspecialchars($produit[$field]) ?>">
                    <img src="<?= htmlspecialchars($produit[$field]) ?>" alt="Thumbnail <?= $index + 1 ?>">
                </div>
        <?php } 
        } ?>
     </div>
                </div>
                
                <div class="col-md-6">
                    <h1 class="product-detail-title"><?= htmlspecialchars($produit['nom']) ?></h1>
                    
                    <div class="product-detail-price">
                        <?php if ($produit['promotion'] > 0): ?>
                            <span class="product-detail-old-price"><?= number_format($produit['prix']) ?> CFA</span>
                            <span><?= number_format($produit['prix']- ($produit['promotion'])) ?> CFA</span>
                            <span class="product-detail-discount ms-3">-<?= $produit['promotion'] ?></span>
                        <?php else: ?>
                            <span><?= number_format($produit['prix']) ?> CFA</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-detail-description">
                        <?php if (!empty($produit['description'])): ?>
                            <?= nl2br(htmlspecialchars($produit['description'])) ?>
                        <?php else: ?>
                            <p>Ce produit <?= strtolower(htmlspecialchars($produit['nom'])) ?> est conçu pour offrir un style unique tout en garantissant confort et durabilité. Fabriqué avec des matériaux de première qualité, il s'agit d'un article incontournable pour votre collection.</p>
                            <p>Que ce soit pour une occasion spéciale ou pour un usage quotidien, ce produit saura répondre à vos attentes avec son design élégant et sa finition soignée.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-detail-meta">
                        <div>
                            <i class="fas fa-tag"></i>
                            <span>Catégorie: <?= htmlspecialchars($produit['categorie_nom'] ?: 'Non catégorisé') ?></span>
                        </div>
                        <div>
                            <i class="fas fa-trademark"></i>
                            <span>Marque: <?= htmlspecialchars($produit['marque']) ?></span>
                        </div>
                        <div>
                            <i class="fas fa-box"></i>
                            <span>Disponibilité: <span class="text-success"><?= number_format($produit['stock']) ?> En stock</span></span>
                        </div>
                        <div>
                            <i class="fas fa-shipping-fast"></i>
                            <span>Livraison: 2-4 jours</span>
                        </div>
                    </div>
                    
                    <div class="product-detail-quantity">
                        <button class="quantity-btn quantity-minus">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" class="quantity-input" value="1" min="1" max="10">
                        <button class="quantity-btn quantity-plus">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    
                    <div class="product-detail-actions">
                        <a href="ajouter_panier.php?id=<?= $produit['id'] ?>" class="btn btn-add-to-cart">
                            <i class="fas fa-cart-plus me-2"></i>Ajouter au panier
                        </a>
                        <button class="btn btn-wishlist">
                            <i class="fas fa-heart me-2"></i>Ajouter aux favoris
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Onglets d'informations -->
            <div class="product-tabs">
                <ul class="nav nav-tabs" id="productTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="description-tab" data-bs-toggle="tab" data-bs-target="#description" type="button" role="tab" aria-controls="description" aria-selected="true">
                            <i class="fas fa-info-circle me-2"></i>Description
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="specifications-tab" data-bs-toggle="tab" data-bs-target="#specifications" type="button" role="tab" aria-controls="specifications" aria-selected="false">
                            <i class="fas fa-list-ul me-2"></i>Spécifications
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab" aria-controls="reviews" aria-selected="false">
                            <i class="fas fa-star me-2"></i>Avis (8)
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="productTabsContent">
                    <div class="tab-pane fade show active" id="description" role="tabpanel" aria-labelledby="description-tab">
                        <h4 class="mb-3">Description détaillée</h4>
                        <?php if (!empty($produit['description'])): ?>
                            <?= nl2br(htmlspecialchars($produit['description'])) ?>
                        <?php else: ?>
                            <p>Ce produit <?= strtolower(htmlspecialchars($produit['nom'])) ?> est conçu pour offrir un style unique tout en garantissant confort et durabilité. Fabriqué avec des matériaux de première qualité, il s'agit d'un article incontournable pour votre collection.</p>
                            <p>Les points forts du produit :</p>
                            <ul>
                                <li>Design élégant et contemporain</li>
                                <li>Matériaux de haute qualité pour une durabilité optimale</li>
                                <li>Finition soignée avec attention aux détails</li>
                                <li>Confort exceptionnel pour une utilisation quotidienne</li>
                                <li>Polyvalence pour s'adapter à différents styles et occasions</li>
                            </ul>
                            <p>Que ce soit pour une occasion spéciale ou pour un usage quotidien, ce produit saura répondre à vos attentes avec son design élégant et sa finition soignée.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="tab-pane fade" id="specifications" role="tabpanel" aria-labelledby="specifications-tab">
                        <h4 class="mb-3">Spécifications techniques</h4>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <tbody>
                                    <tr>
                                        <th scope="row" width="30%">Nom</th>
                                        <td><?= htmlspecialchars($produit['nom']) ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Référence</th>
                                        <td>REF-<?= $produit['id'] ?><?= strtoupper(substr(md5($produit['nom']), 0, 6)) ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Marque</th>
                                        <td><?= htmlspecialchars($produit['marque']) ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Catégorie</th>
                                        <td><?= htmlspecialchars($produit['categorie_nom']) ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Matériaux</th>
                                        <td>Premium</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Dimensions</th>
                                        <td>Variables selon la taille</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Entretien</th>
                                        <td>Lavage à 30°C recommandé</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="reviews" role="tabpanel" aria-labelledby="reviews-tab">
                        <h4 class="mb-4">Avis clients</h4>
                        <div class="d-flex align-items-center mb-4">
                            <div class="me-3">
                                <h2 class="mb-0">4.7</h2>
                                <div>
                                    <i class="fas fa-star text-warning"></i>
                                    <i class="fas fa-star text-warning"></i>
                                    <i class="fas fa-star text-warning"></i>
                                    <i class="fas fa-star text-warning"></i>
                                    <i class="fas fa-star-half-alt text-warning"></i>
                                </div>
                                <p class="text-muted">8 avis</p>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-1">
                                    <span class="me-2">5 <i class="fas fa-star text-warning"></i></span>
                                    <div class="progress flex-grow-1" style="height: 8px">
                                        <div class="progress-bar bg-success" style="width: 75%"></div>
                                    </div>
                                    <span class="ms-2">6</span>
                                </div>
                                <div class="d-flex align-items-center mb-1">
                                    <span class="me-2">4 <i class="fas fa-star text-warning"></i></span>
                                    <div class="progress flex-grow-1" style="height: 8px">
                                        <div class="progress-bar bg-success" style="width: 15%"></div>
                                    </div>
                                    <span class="ms-2">1</span>
                                </div>
                                <div class="d-flex align-items-center mb-1">
                                    <span class="me-2">3 <i class="fas fa-star text-warning"></i></span>
                                    <div class="progress flex-grow-1" style="height: 8px">
                                        <div class="progress-bar bg-warning" style="width: 12.5%"></div>
                                    </div>
                                    <span class="ms-2">1</span>
                                </div>
                                <div class="d-flex align-items-center mb-1">
                                    <span class="me-2">2 <i class="fas fa-star text-warning"></i></span>
                                    <div class="progress flex-grow-1" style="height: 8px">
                                        <div class="progress-bar bg-danger" style="width: 0%"></div>
                                    </div>
                                    <span class="ms-2">0</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <span class="me-2">1 <i class="fas fa-star text-warning"></i></span>
                                    <div class="progress flex-grow-1" style="height: 8px">
                                        <div class="progress-bar bg-danger" style="width: 0%"></div>
                                    </div>
                                    <span class="ms-2">0</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Avis individuels -->
                        <div class="review-list">
                            <div class="review-item p-3 mb-3 border-bottom">
                                <div class="d-flex justify-content-between mb-2">
                                    <div>
                                        <h5 class="mb-0">Sophie K.</h5>
                                        <div>
                                            <i class="fas fa-star text-warning"></i>
                                            <i class="fas fa-star text-warning"></i>
                                            <i class="fas fa-star text-warning"></i>
                                            <i class="fas fa-star text-warning"></i>
                                            <i class="fas fa-star text-warning"></i>
                                        </div>
                                    </div>
                                    <small class="text-muted">Il y a 2 jours</small>
                                </div>
                                <p>Produit de très bonne qualité, je suis vraiment satisfaite de mon achat ! La livraison a été rapide et le produit correspond parfaitement à la description. Je recommande !</p>
                            </div>
                            
                            <div class="review-item p-3 mb-3 border-bottom">
                                <div class="d-flex justify-content-between mb-2">
                                    <div>
                                        <h5 class="mb-0">Marc T.</h5>
                                        <div>
                                            <i class="fas fa-star text-warning"></i>
                                            <i class="fas fa-star text-warning"></i>
                                            <i class="fas fa-star text-warning"></i>
                                            <i class="fas fa-star text-warning"></i>
                                            <i class="far fa-star text-warning"></i>
                                        </div>
                                    </div>
                                    <small class="text-muted">Il y a 1 semaine</small>
                                </div>
                                <p>Très bon rapport qualité-prix. Le produit est élégant et bien conçu. Seul petit bémol sur la taille qui est légèrement plus petite que prévu, mais rien de rédhibitoire.</p>
                            </div>
                            
                            <div class="review-item p-3 mb-3 border-bottom">
                                <div class="d-flex justify-content-between mb-2">
                                    <div>
                                        <h5 class="mb-0">Amélie D.</h5>
                                        <div>
                                            <i class="fas fa-star text-warning"></i>
                                            <i class="fas fa-star text-warning"></i>
                                            <i class="fas fa-star text-warning"></i>
                                            <i class="fas fa-star text-warning"></i>
                                            <i class="fas fa-star text-warning"></i>
                                        </div>
                                    </div>
                                    <small class="text-muted">Il y a 2 semaines</small>
                                </div>
                                <p>Excellente qualité et finition impeccable ! Je suis ravie de mon achat et la livraison était super rapide. Je recommande vivement AMAF-SHOP pour le sérieux et la qualité de leurs produits.</p>
                            </div>
                            
                            <div class="review-item p-3 mb-3 border-bottom">
                                <div class="d-flex justify-content-between mb-2">
                                    <div>
                                        <h5 class="mb-0">Thomas L.</h5>
                                        <div>
                                            <i class="fas fa-star text-warning"></i>
                                            <i class="fas fa-star text-warning"></i>
                                            <i class="fas fa-star text-warning"></i>
                                            <i class="far fa-star text-warning"></i>
                                            <i class="far fa-star text-warning"></i>
                                        </div>
                                    </div>
                                    <small class="text-muted">Il y a 3 semaines</small>
                                </div>
                                <p>Le produit est correct mais j'attendais mieux côté finition. Le service client a été réactif quand j'ai eu une question, ce qui est appréciable.</p>
                            </div>
                            
                            <!-- Formulaire d'avis -->
                            <div class="review-form mt-4">
                                <h5>Laisser un avis</h5>
                                <form>
                                    <div class="mb-3">
                                        <label class="form-label">Votre note</label>
                                        <div class="rating-input">
                                            <i class="far fa-star rating-star" data-value="1"></i>
                                            <i class="far fa-star rating-star" data-value="2"></i>
                                            <i class="far fa-star rating-star" data-value="3"></i>
                                            <i class="far fa-star rating-star" data-value="4"></i>
                                            <i class="far fa-star rating-star" data-value="5"></i>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="reviewMessage" class="form-label">Votre avis</label>
                                        <textarea class="form-control" id="reviewMessage" rows="3" placeholder="Partagez votre expérience avec ce produit"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Soumettre mon avis</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Produits similaires -->
    <section class="similar-products-section py-5">
        <div class="container">
            <h2 class="section-title text-center mb-4">Produits similaires</h2>
            
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
                <?php 
                if ($result_similaires && $result_similaires->num_rows > 0):
                    while ($produit_similaire = $result_similaires->fetch_assoc()):
                ?>
                    <div class="col" data-aos="fade-up">
                        <div class="product-card h-100 shadow-sm rounded overflow-hidden">
                            <a href="produit.php?id=<?= $produit_similaire['id'] ?>" class="text-decoration-none">
                                <div class="product-image-container">
                                    <img src="<?= htmlspecialchars($produit_similaire['image_url']) ?>" 
                                         alt="<?= htmlspecialchars($produit_similaire['nom']) ?>" 
                                         class="product-image img-fluid">
                                </div>
                                <div class="product-info p-3">
                                    <h5 class="product-title text-dark mb-2"><?= htmlspecialchars($produit_similaire['nom']) ?></h5>
                                    <div class="product-price">
                                        <?php if ($produit_similaire['promotion'] > 0): ?>
                                            <span class="text-decoration-line-through text-muted me-2">
                                                <?= number_format($produit_similaire['prix'], ) ?>CFA
                                            </span>
                                            <span class="text-danger fw-bold">
                                                <?= number_format($produit_similaire['prix'] - ( $produit_similaire['promotion'] ), ) ?>CFA
                                            </span>
                                        <?php else: ?>
                                            <span class="text-primary fw-bold"><?= number_format($produit_similaire['prix'], ) ?>CFA</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                <?php 
                    endwhile;
                endif;
                ?>
            </div>
        </div>
    </section>
    
    <!-- Lightbox pour le zoom -->
    <div class="lightbox" id="imageLightbox">
        <div class="lightbox-content">
            <img src="" alt="Product" id="lightboxImage">
        </div>
        <div class="lightbox-close" id="lightboxClose">
            <i class="fas fa-times"></i>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Variables
            const mainImage = document.getElementById('mainProductImage');
            const thumbnails = document.querySelectorAll('.thumbnail-item');
            const zoomBtn = document.getElementById('zoomBtn');
            const lightbox = document.getElementById('imageLightbox');
            const lightboxImage = document.getElementById('lightboxImage');
            const lightboxClose = document.getElementById('lightboxClose');
            const quantityInput = document.querySelector('.quantity-input');
            const minusBtn = document.querySelector('.quantity-minus');
            const plusBtn = document.querySelector('.quantity-plus');
            const ratingStars = document.querySelectorAll('.rating-star');
            
            // Gestion des thumbnails
            thumbnails.forEach(thumb => {
                thumb.addEventListener('click', function() {
                    const imgSrc = this.getAttribute('data-src');
                    
                    // Animation de transition
                    mainImage.style.opacity = '0';
                    setTimeout(() => {
                        mainImage.src = imgSrc;
                        mainImage.style.opacity = '1';
                    }, 200);
                    
                    // Mise à jour de la classe active
                    thumbnails.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                });
            });
            
            // Zoom sur l'image (Lightbox)
            zoomBtn.addEventListener('click', function() {
                lightboxImage.src = mainImage.src;
                lightbox.classList.add('active');
                document.body.style.overflow = 'hidden'; // Empêcher le défilement
                
                // Animation d'entrée
                lightboxImage.classList.add('animate__animated', 'animate__zoomIn');
                setTimeout(() => {
                    lightboxImage.classList.remove('animate__animated', 'animate__zoomIn');
                }, 500);
            });
            
            // Fermer la lightbox
            lightboxClose.addEventListener('click', function() {
                lightboxImage.classList.add('animate__animated', 'animate__zoomOut');
                setTimeout(() => {
                    lightbox.classList.remove('active');
                    document.body.style.overflow = ''; // Réactiver le défilement
                    lightboxImage.classList.remove('animate__animated', 'animate__zoomOut');
                }, 300);
            });
            
            // Fermer la lightbox en cliquant en dehors de l'image
            lightbox.addEventListener('click', function(e) {
                if (e.target === lightbox) {
                    lightboxClose.click();
                }
            });
            
            // Gestion de la quantité
            minusBtn.addEventListener('click', function() {
                let value = parseInt(quantityInput.value, 10);
                value = isNaN(value) ? 1 : value;
                if (value > 1) {
                    value--;
                    quantityInput.value = value;
                }
            });
            
            plusBtn.addEventListener('click', function() {
                let value = parseInt(quantityInput.value, 10);
                value = isNaN(value) ? 1 : value;
                value++;
                quantityInput.value = value;
            });
            
            // Système de notation (étoiles)
            ratingStars.forEach(star => {
                star.addEventListener('mouseenter', function() {
                    const value = parseInt(this.getAttribute('data-value'));
                    highlightStars(value);
                });
                
                star.addEventListener('click', function() {
                    const value = parseInt(this.getAttribute('data-value'));
                    ratingStars.forEach(s => {
                        s.classList.remove('selected');
                        if (parseInt(s.getAttribute('data-value')) <= value) {
                            s.classList.add('selected');
                        }
                    });
                });
            });
            
            document.querySelector('.rating-input').addEventListener('mouseleave', function() {
                resetStarsHighlight();
                highlightSelectedStars();
            });
            
            // Fonctions auxiliaires pour les étoiles
            function highlightStars(count) {
                ratingStars.forEach(s => {
                    const starValue = parseInt(s.getAttribute('data-value'));
                    if (starValue <= count) {
                        s.classList.remove('far');
                        s.classList.add('fas');
                    } else {
                        s.classList.remove('fas');
                        s.classList.add('far');
                    }
                });
            }
            
            function resetStarsHighlight() {
                ratingStars.forEach(s => {
                    s.classList.remove('fas');
                    s.classList.add('far');
                });
            }
            
            function highlightSelectedStars() {
                const selectedStars = document.querySelectorAll('.rating-star.selected');
                selectedStars.forEach(s => {
                    s.classList.remove('far');
                    s.classList.add('fas');
                });
            }
            
            // Animation au scroll
            window.addEventListener('scroll', function() {
                const scrollPosition = window.scrollY;
                
                // Parallax effect sur l'image principale
                if (scrollPosition < 500) {
                    mainImage.style.transform = `scale(${1 + scrollPosition * 0.0005})`;
                }
            });
            
            // Animation des boutons d'action
            const actionButtons = document.querySelectorAll('.product-detail-actions .btn');
            actionButtons.forEach(btn => {
                btn.addEventListener('mouseenter', function() {
                    this.classList.add('animate__animated', 'animate__pulse');
                });
                
                btn.addEventListener('mouseleave', function() {
                    this.classList.remove('animate__animated', 'animate__pulse');
                });
            });
            
            // Améliorer l'expérience utilisateur sur mobile
            if (window.innerWidth < 768) {
                document.querySelector('.product-detail-img-container').addEventListener('click', function() {
                    zoomBtn.click();
                });
            }
        });
    </script>
</body>
</html>