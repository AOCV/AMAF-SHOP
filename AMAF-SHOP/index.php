<?php
session_start();
require 'config.php';

// Récupérer les paramètres du site
$parametres = [];
$query_parametres = "SELECT * FROM parametres WHERE id = 1";
$result_parametres = $conn->query($query_parametres);
if ($result_parametres && $result_parametres->num_rows > 0) {
    $parametres = $result_parametres->fetch_assoc();
}

// Récupérer les catégories
$categories = [];
$query_categories = "SELECT id, nom, description, image_url FROM categorie";
$result_categories = $conn->query($query_categories);
if ($result_categories) {
    while ($row = $result_categories->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Préparation des variables pour les filtres
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$categorie_filter = isset($_GET['categorie']) ? intval($_GET['categorie']) : 0;

// Configuration de la pagination pour tous les produits
$products_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$products_per_page = 8; // Nombre de produits par page
$products_start = ($products_page - 1) * $products_per_page;

// Base de la requête SQL
$query = "SELECT p.*, c.nom as categorie_nom \n          FROM produit p \n          LEFT JOIN categorie c ON p.categorie_id = c.id \n          WHERE 1=1";

// Ajout des filtres si nécessaire
if ($categorie_filter > 0) {
    $query .= " AND p.categorie_id = " . $categorie_filter;
}

if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $query .= " AND (p.nom LIKE '%$search%' OR p.description LIKE '%$search%' OR p.marque LIKE '%$search%')";
}
// Images prédéfinies pour certaines catégories
$categorie_images = [
    'habits' => 'assets/images/categories/habits.jpg',
    'chaussures' => 'assets/images/categories/chaussures.jpg',
    'pantalons' => 'assets/images/categories/pantalons.jpg',
    'accessoires' => 'assets/images/categories/accessoires.jpg',
    'montres' => 'assets/images/categories/montres.jpg',
];

// Filtrage par catégorie
$categorie_filter = isset($_GET['categorie']) ? intval($_GET['categorie']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Construction de la requête SQL avec filtres
// Configuration de la pagination pour tous les produits
$products_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$products_per_page = 8; // Nombre de produits par page
$products_start = ($products_page - 1) * $products_per_page;

// Base de la requête SQL
$query = "SELECT p.*, c.nom as categorie_nom 
          FROM produit p 
          LEFT JOIN categorie c ON p.categorie_id = c.id 
          WHERE 1=1";

// Ajout des filtres si nécessaire
if ($categorie_filter > 0) {
    $query .= " AND p.categorie_id = " . $categorie_filter;
}

if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $query .= " AND (p.nom LIKE '%$search%' OR p.description LIKE '%$search%' OR p.marque LIKE '%$search%')";
}

// Compter le nombre total de produits qui correspondent aux critères
$count_query = "SELECT COUNT(*) as total " . substr($query, strpos($query, 'FROM'));
$result_count = $conn->query($count_query);
$total_products = $result_count->fetch_assoc()['total'];
$total_pages = ceil($total_products / $products_per_page);

// Ajouter la clause LIMIT pour la pagination
$query .= " ORDER BY p.id DESC LIMIT $products_start, $products_per_page";

// Exécuter la requête paginée
$result = $conn->query($query);

// Configuration de la pagination pour les promotions
$promo_page = isset($_GET['promo_page']) ? intval($_GET['promo_page']) : 1;
$promos_par_page = 6; // Nombre de promotions par page
$promo_debut = ($promo_page - 1) * $promos_par_page;

// Compter le nombre total de produits en promotion
$query_count_promos = "SELECT COUNT(*) as total FROM produit WHERE promotion > 0";
$result_count_promos = $conn->query($query_count_promos);
$total_promos = $result_count_promos->fetch_assoc()['total'];
$total_pages_promos = ceil($total_promos / $promos_par_page);

// Récupérer les produits en promotion avec pagination
$query_promotions = "SELECT p.*, c.nom as categorie_nom 
                    FROM produit p 
                    LEFT JOIN categorie c ON p.categorie_id = c.id 
                    WHERE p.promotion > 0 
                    ORDER BY p.promotion DESC 
                    LIMIT $promo_debut, $promos_par_page";
$result_promotions = $conn->query($query_promotions);

// Récupérer les nouveaux produits
$query_nouveautes = "SELECT p.*, c.nom as categorie_nom 
                    FROM produit p 
                    LEFT JOIN categorie c ON p.categorie_id = c.id 
                    ORDER BY p.id DESC 
                    LIMIT 4";
$result_nouveautes = $conn->query($query_nouveautes);

?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMAF-SHOP - Votre boutique en ligne</title>
    
    <!-- Préchargement des ressources critiques -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    
    <!-- CSS minimal pour le premier affichage -->
    <style>
        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, #3a7bd5, #00d2ff);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .loader-content {
            text-align: center;
            color: white;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    
    <!-- CSS principal chargé de façon asynchrone -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" media="print" onload="this.media='all'">
</head>
<body>
    <!-- Écran de chargement -->
    <div class="loader" id="loader">
        <div class="loader-content">
            <div class="spinner"></div>
            <h2>AMAF-SHOP</h2>
            <p>Chargement en cours...</p>
        </div>
    </div>

    <!-- Bouton retour en haut -->
    <a href="#" class="back-to-top" id="backToTop" style="display: none">
        <i class="fas fa-chevron-up"></i>
    </a>

    <!-- Messages de notification -->
    <?php if (isset($_GET['success'])): ?>
        <div class="container mt-3">
            <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeInDown" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_GET['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="container mt-3">
            <div class="alert alert-danger alert-dismissible fade show animate__animated animate__fadeInDown" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($_GET['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

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
                        <a class="nav-link active" href="index.php">Accueil</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            Collections
                        </a>
                        <ul class="dropdown-menu animate__animated animate__fadeIn">
                            <?php foreach ($categories as $cat): ?>
                                <li><a class="dropdown-item" href="?categorie=<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nom']) ?></a></li>
                            <?php endforeach; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#">Toutes les collections</a></li>
                        </ul>
                    </li>
                    <!-- <li class="nav-item">
                        <a class="nav-link" href="#nouveautes">Nouveautés</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#promotions">Promotions</a>
                    </li> -->
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                </ul>
                
                <form class="d-flex search-form me-3" action="" method="GET">
                    <input class="form-control" type="search" name="search" placeholder="Rechercher..." value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
                
                <ul class="navbar-nav">
                    <li class="nav-item me-2">
                        <a class="nav-link cart-icon" href="panier.php">
                            <i class="fas fa-shopping-cart"></i>
                            <?php if (!empty($_SESSION['panier'])): ?>
                                <span class="cart-badge badge bg-danger">
                                    <?php
                                    $total_items = 0;
                                    foreach ($_SESSION['panier'] as $item) {
                                        $total_items += $item['quantite'];
                                    }
                                    echo $total_items;
                                    ?>
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
                            <a class="nav-link " href="register.php">
                                <i class="fas fa-user-plus me-1"></i>Inscription
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Carousel Hero -->
    <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-indicators">
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active"></button>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1"></button>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2"></button>
        </div>
        <div class="carousel-inner">
            <div class="carousel-item active">
                <img src="https://images.pexels.com/photos/5632402/pexels-photo-5632402.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750" 
                     class="d-block w-100" alt="Collection Mode" loading="eager">
                <div class="carousel-caption">
                    <h2>Bienvenue chez AMAF-SHOP</h2>
                    <p>Découvrez notre sélection exclusive de produits de qualité</p>
                    <a href="#categories" class="btn btn-primary btn-lg rounded-pill">
                        <i class="fas fa-shopping-bag me-2"></i>Explorer
                    </a>
                </div>
            </div>
            <div class="carousel-item">
                <img src="https://images.pexels.com/photos/6567607/pexels-photo-6567607.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750" 
                     class="d-block w-100" alt="Nouvelles Collections" loading="lazy">
                <div class="carousel-caption">
                    <h2>Nouvelles Collections</h2>
                    <p>Les dernières tendances sont arrivées</p>
                    <a href="#nouveautes" class="btn btn-primary btn-lg rounded-pill">
                        <i class="fas fa-tshirt me-2"></i>Découvrir
                    </a>
                </div>
            </div>
            <div class="carousel-item">
                <img src="https://images.pexels.com/photos/1488463/pexels-photo-1488463.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750" 
                     class="d-block w-100" alt="Promotions Exceptionnelles" loading="lazy">
                <div class="carousel-caption">
                    <h2>Promotions Exceptionnelles</h2>
                    <p>Jusqu'à 50% de réduction sur des articles sélectionnés</p>
                    <a href="#promotions" class="btn btn-primary btn-lg rounded-pill">
                        <i class="fas fa-tags me-2"></i>Voir les offres
                    </a>
                </div>
            </div>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon"></span>
            <span class="visually-hidden">Précédent</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon"></span>
            <span class="visually-hidden">Suivant</span>
        </button>
    </div>

    <!-- Section des catégories -->
    <section class="category-section" id="categories">
        <div class="container">
            <h2 class="section-title">Nos Catégories</h2>
            <div class="row">
                <?php foreach ($categories as $index => $cat): 
                    $catName = strtolower(htmlspecialchars($cat['nom']));
                    // Utiliser l'image téléchargée par l'admin si disponible
                    if (!empty($cat['image_url'])) {
                        $catImagePath = $cat['image_url'];
                    } else {
                        // Fallback sur les images prédéfinies ou une image générique
                        $catImagePath = isset($categorie_images[$catName]) ? $categorie_images[$catName] : 'https://source.unsplash.com/300x200/?'.urlencode($catName);
                    }
                ?>
                    <div class="col-6 col-md-4 col-lg-3" data-aos="zoom-in" data-aos-delay="<?= $index * 50 ?>">
                        <a href="?categorie=<?= $cat['id'] ?>" class="text-decoration-none category-link">
                            <div class="category-card">
                                <img src="<?= $catImagePath ?>" alt="<?= htmlspecialchars($cat['nom']) ?>" class="category-img" loading="lazy">
                                <div class="category-overlay">
                                    <h5 class="category-name"><?= htmlspecialchars($cat['nom']) ?></h5>
                                    <?php if (!empty($cat['description'])): ?>
                                    <p class="category-description"><?= htmlspecialchars(substr($cat['description'], 0, 50)) . (strlen($cat['description']) > 50 ? '...' : '') ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Section des nouveautés -->
    <section class="product-section" id="nouveautes">
        <div class="container">
            <h2 class="section-title">Nouveautés</h2>
            <div class="row">
                <?php 
                if ($result_nouveautes && $result_nouveautes->num_rows > 0):
                    $index = 0;
                    while ($produit = $result_nouveautes->fetch_assoc()):
                        $index++;
                ?>
                    <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="<?= $index * 50 ?>">
                        <div class="product-card">
                            <?php if ($produit['categorie_nom']): ?>
                                <div class="product-category"><?= htmlspecialchars($produit['categorie_nom']) ?></div>
                            <?php endif; ?>
                            
                            <div class="product-img-container">
                                <img src="<?= !empty($produit['image_url']) ? htmlspecialchars($produit['image_url']) : 'https://images.pexels.com/photos/1598505/pexels-photo-1598505.jpeg?auto=compress&cs=tinysrgb&w=600' ?>" 
                                     class="product-img" 
                                     alt="<?= htmlspecialchars($produit['nom']) ?>"
                                     loading="lazy">
                                <div class="product-overlay">
                                    <button class="btn-quick-view" 
                                            data-id="<?= $produit['id'] ?>" 
                                            data-name="<?= htmlspecialchars($produit['nom']) ?>" 
                                            data-price="<?= number_format($produit['prix'], 0) ?> CFA"
                                            data-category="<?= htmlspecialchars($produit['categorie_nom']) ?>"
                                            data-brand="<?= htmlspecialchars($produit['marque']) ?>"
                                            data-image="<?= !empty($produit['image_url']) ? htmlspecialchars($produit['image_url']) : 'https://images.pexels.com/photos/1598505/pexels-photo-1598505.jpeg?auto=compress&cs=tinysrgb&w=600' ?>">
                                        <i class="fas fa-eye"></i> Aperçu rapide
                                    </button>
                                </div>
                            </div>
                            
                            <div class="product-body">
                                <h5 class="product-title"><?= htmlspecialchars($produit['nom']) ?></h5>
                                <!-- <p class="product-brand">
                                    <i class="fas fa-trademark me-1"></i><?= htmlspecialchars($produit['marque']) ?>
                                </p> -->
                                <div class="product-price">
                                    <?= number_format($produit['prix']) ?> CFA
                                </div>
                                <div class="product-actions">
                                    <a href="produit.php?id=<?= $produit['id'] ?>" class="btn btn-view">
                                        <i class="fas fa-eye me-1"></i>Détails
                                    </a>
                                    <a href="ajouter_panier.php?id=<?= $produit['id'] ?>" class="btn btn-add">
                                        <i class="fas fa-cart-plus me-1"></i>Ajouter
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php 
                    endwhile;
                endif; 
                ?>
            </div>
        </div>
    </section>

    <!-- Section des promotions -->
    <section class="promotion-section" id="promotions">
        <div class="container">
            <h2 class="section-title">Promotions</h2>
            <div class="row">
                <?php 
                if ($result_promotions && $result_promotions->num_rows > 0):
                    $index = 0;
                    while ($produit = $result_promotions->fetch_assoc()):
                        $index++;
                ?>
                    <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="<?= $index * 50 ?>">
                        <div class="product-card promotion-card">
                            <?php if ($produit['promotion'] > 0): ?>
                                <div class="product-badge">-<?= $produit['promotion'] ?></div>
                            <?php endif; ?>
                            
                            <?php if ($produit['categorie_nom']): ?>
                                <div class="product-category"><?= htmlspecialchars($produit['categorie_nom']) ?></div>
                            <?php endif; ?>
                            
                            <div class="product-img-container">
                                <img src="<?= !empty($produit['image_url']) ? htmlspecialchars($produit['image_url']) : 'https://images.pexels.com/photos/1598505/pexels-photo-1598505.jpeg?auto=compress&cs=tinysrgb&w=600' ?>" 
                                     class="product-img" 
                                     alt="<?= htmlspecialchars($produit['nom']) ?>"
                                     loading="lazy">
                                <div class="product-overlay">
                                    <button class="btn-quick-view" 
                                            data-id="<?= $produit['id'] ?>" 
                                            data-name="<?= htmlspecialchars($produit['nom']) ?>" 
                                            data-price="<?= number_format($produit['prix'], 0) ?> CFA"
                                            data-category="<?= htmlspecialchars($produit['categorie_nom'] ) ?>"
                                            data-brand="<?= htmlspecialchars($produit['marque']) ?>"
                                            data-image="<?= !empty($produit['image_url']) ? htmlspecialchars($produit['image_url']) : 'https://images.pexels.com/photos/1598505/pexels-photo-1598505.jpeg?auto=compress&cs=tinysrgb&w=600' ?>">
                                        <i class="fas fa-eye"></i> Aperçu rapide
                                    </button>
                                </div>
                            </div>
                            
                            <div class="product-body">
                                <h5 class="product-title"><?= htmlspecialchars($produit['nom']) ?></h5>
                                <!-- <p class="product-brand">
                                    <i class="fas fa-trademark me-1"></i><?= htmlspecialchars($produit['marque'] ) ?>
                                </p> -->
                                <div class="product-price">
                                    <span class="old-price"><?= number_format($produit['prix']) ?> CFA</span>
                                    <span class="text-danger"><?= number_format($produit['prix'] - ($produit['promotion'])) ?> CFA</span>
                                </div>
                                <div class="product-actions">
                                    <a href="produit.php?id=<?= $produit['id'] ?>" class="btn btn-view">
                                        <i class="fas fa-eye me-1"></i>Détails
                                    </a>
                                    <a href="ajouter_panier.php?id=<?= $produit['id'] ?>" class="btn btn-add">
                                        <i class="fas fa-cart-plus me-1"></i>Ajouter
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php 
                    endwhile;
                endif; 
                ?>
            </div>
            
            <?php if ($total_pages_promos > 1): ?>
            <!-- Pagination des promotions -->
            <div class="promotion-pagination">
                <nav aria-label="Pagination des promotions">
                    <ul class="pagination justify-content-center">
                        <?php if ($promo_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?promo_page=<?= $promo_page - 1 ?>#promotions" aria-label="Pru00e9cu00e9dent">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages_promos; $i++): ?>
                        <li class="page-item <?= ($i == $promo_page) ? 'active' : '' ?>">
                            <a class="page-link" href="index.php?promo_page=<?= $i ?>#promotions"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($promo_page < $total_pages_promos): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?promo_page=<?= $promo_page + 1 ?>#promotions" aria-label="Suivant">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Styles CSS complets -->
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
        
        /* Styles généraux */
        body {
            font-family: 'Poppins', sans-serif;
            color: var(--text-color);
            background-color: #f5f7fa;
            line-height: 1.6;
            opacity: 1;
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
        
        .navbar-brand {
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        .nav-link {
            font-weight: 500;
            position: relative;
            margin: 0 5px;
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: white;
            transition: var(--transition);
        }
        
        .nav-link:hover::after {
            width: 100%;
        }
        
        .search-form input {
            border-radius: 30px 0 0 30px;
            border: none;
            padding: 10px 20px;
        }
        
        .search-form button {
            border-radius: 0 30px 30px 0;
            padding: 10px 20px;
            background: var(--accent-color);
            border: none;
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
        
        /* Carousel et hero section */
        .carousel-item {
            height: 500px;
        }
        
        .carousel-item img {
            object-fit: cover;
            height: 100%;
            width: 100%;
        }
        
        .carousel-caption {
            bottom: 20%;
            padding: 30px;
            background: rgba(0,0,0,0.5);
            border-radius: 15px;
            max-width: 700px;
            margin: 0 auto;
            animation: fadeInUp 1s ease;
        }
        
        .carousel-caption h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            animation: fadeInDown 1s ease;
        }
        
        .carousel-caption p {
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
            animation: fadeInUp 1.2s ease;
        }
        
        /* Section des catégories */
        .category-section {
            padding: 60px 0;
            background: #f8f9fa;
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
        
        .category-card {
            position: relative;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            transition: var(--transition);
            height: 200px;
        }
        
        .category-link:hover .category-card {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .category-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }
        
        .category-link:hover .category-img {
            transform: scale(1.1);
        }
        
        .category-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            padding: 20px;
            transition: var(--transition);
        }
        
        .category-link:hover .category-overlay {
            background: linear-gradient(to top, rgba(58, 123, 213, 0.8), transparent);
        }
        
        .category-name {
            color: white;
            font-weight: 600;
            margin-bottom: 0;
        }
        
        /* Section des produits */
        .product-section {
            padding: 60px 0;
            background-color: var(--light-color);
        }
        
        .product-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            margin-bottom: 30px;
            position: relative;
            background-color: white;
        }
        
        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .product-img-container {
            position: relative;
            height: 250px;
            overflow: hidden;
        }
        
        .product-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }
        
        .product-card:hover .product-img {
            transform: scale(1.1);
        }
        
        .product-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: var(--transition);
        }
        
        .product-card:hover .product-overlay {
            opacity: 1;
        }
        
        .btn-quick-view {
            background: white;
            color: var(--dark-color);
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            transform: translateY(20px);
            transition: all 0.4s ease;
            opacity: 0;
        }
        
        .product-card:hover .btn-quick-view {
            transform: translateY(0);
            opacity: 1;
        }
        
        .product-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--accent-color);
            color: white;
            font-weight: 600;
            padding: 8px 15px;
            border-radius: 25px;
            z-index: 2;
            box-shadow: 0 4px 10px rgba(231, 76, 60, 0.3);
            animation: pulse 2s infinite;
        }
        
        .product-category {
            position: absolute;
            top: 15px;
            left: 15px;
            background: rgba(0,0,0,0.6);
            color: white;
            font-size: 0.8rem;
            padding: 5px 15px;
            border-radius: 20px;
            z-index: 2;
        }
        
        .product-body {
            padding: 20px;
        }
        
        .product-title {
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 1.2rem;
            color: var(--dark-color);
        }
        
        .product-brand {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .product-price {
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .old-price {
            text-decoration: line-through;
            color: #6c757d;
            font-size: 1rem;
            margin-right: 10px;
        }
        
        .product-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }
        
        .btn-view {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: 25px;
            padding: 8px 20px;
            transition: var(--transition);
            font-weight: 600;
            flex: 1;
            margin-right: 8px;
        }
        
        .btn-view:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(58, 123, 213, 0.4);
            color: white;
        }
        
        .btn-add {
            background: var(--success-color);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 8px 20px;
            transition: var(--transition);
            font-weight: 600;
            flex: 1;
        }
        
        .btn-add:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.4);
            color: white;
        }
        
        /* Section des promotions */
        .promotion-section {
            background: linear-gradient(45deg, #3a7bd5, #00d2ff);
            padding: 60px 0;
            color: white;
            margin-bottom: 30px;
        }
        
        .promotion-section .section-title {
            color: white;
            margin-bottom: 30px;
        }
        
        .promotion-section .section-title::after {
            background: white;
        }
        
        .promotion-section .product-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 30px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: auto;
        }
        
        .promotion-section .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }
        
        .promotion-section .product-img-container {
            height: 200px;
            overflow: hidden;
            position: relative;
        }
        
        .promotion-section .product-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .promotion-section .product-card:hover .product-img {
            transform: scale(1.1);
        }
        
        .promotion-section .product-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--accent-color);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
            z-index: 2;
            font-size: 0.8rem;
        }
        
        .promotion-section .product-body {
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .promotion-section .product-title {
            color: white;
            font-size: 1rem;
            margin-bottom: 10px;
            overflow: hidden;
        }
        
        .promotion-section .product-brand {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.85rem;
            margin-bottom: 10px;
        }
        
        .promotion-section .product-price {
            margin-bottom: 15px;
            color: white;
        }
        
        .promotion-section .product-price .old-price {
            text-decoration: line-through;
            color: rgba(255, 255, 255, 0.6);
            margin-right: 10px;
            font-size: 0.9rem;
        }
        
        .promotion-section .product-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }
        
        .promotion-section .btn-view, 
        .promotion-section .btn-add {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .promotion-section .btn-view {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            margin-right: 5px;
        }
        
        .promotion-section .btn-add {
            background-color: var(--accent-color);
            color: white;
            flex-grow: 1;
        }
        
        .promotion-card .btn-details {
            padding: 8px 12px;
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            flex: 0 0 auto;
            width: 40%;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .promotion-card .btn-details:hover {
            background-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        .promotion-card .btn-add-cart {
            padding: 8px 12px;
            background-color: var(--accent-color, #ff6b6b);
            color: white;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            flex: 1;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .promotion-card .btn-add-cart:hover {
            background-color: #ff5252;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        /* Styles pour la pagination des promotions */
        .promotion-pagination {
            margin-top: 25px;
            margin-bottom: 15px;
        }
        
        .promotion-pagination .pagination {
            gap: 5px;
        }
        
        .promotion-pagination .page-link {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .promotion-pagination .page-link:hover {
            background-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        .promotion-pagination .page-item.active .page-link {
            background-color: var(--accent-color, #ff6b6b);
            border-color: var(--accent-color, #ff6b6b);
            color: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        /* Styles pour la pagination des produits */
        .products-pagination {
            margin-top: 30px;
            margin-bottom: 20px;
        }
        
        .products-pagination .pagination {
            gap: 5px;
        }
        
        .products-pagination .page-link {
            background-color: #f0f2f5;
            color: var(--primary-color);
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .products-pagination .page-link:hover {
            background-color: #e9ecef;
            color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .products-pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            box-shadow: 0 2px 5px rgba(58, 123, 213, 0.3);
        }
        
        /* Modal de prévisualisation rapide */
        .quick-view-modal .modal-content {
            border-radius: 15px;
            overflow: hidden;
            border: none;
        }
        
        .quick-view-modal .modal-header {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
        }
        
        .quick-view-modal .modal-body {
            padding: 30px;
        }
        
        .quick-view-img {
            max-height: 400px;
            width: 100%;
            object-fit: cover;
            border-radius: 10px;
        }
        
        .quick-view-details {
            padding: 20px;
        }
        
        .quick-view-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .quick-view-price {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        .quick-view-description {
            margin-bottom: 20px;
            color: #6c757d;
        }
        
        .quick-view-meta {
            margin-bottom: 15px;
        }
        
        .quick-view-meta span {
                display: block;
                margin-bottom: 5px;
                font-size: 0.9rem;
            }
            
            .quick-view-meta i {
                width: 25px;
                color: var(--primary-color);
            }
            
            .quick-view-actions {
                display: flex;
                gap: 10px;
                margin-top: 20px;
            }
            
            .quick-view-actions .btn {
                flex: 1;
                padding: 10px 20px;
            }
        
        /* Footer */
        .footer {
            background: var(--dark-color);
            padding: 60px 0 30px;
            color: #f8f9fa;
        }
        
        .footer-title {
            font-weight: 600;
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
        }
        
        .footer-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 2px;
            background: var(--primary-color);
        }
        
        .footer-links {
            list-style: none;
            padding-left: 0;
        }
        
        .footer-links li {
            margin-bottom: 10px;
        }
        
        .footer-links a {
            color: #adb5bd;
            text-decoration: none;
            transition: var(--transition);
            position: relative;
            padding-left: 15px;
        }
        
        .footer-links a::before {
            content: '›';
            position: absolute;
            left: 0;
            color: var(--primary-color);
            transition: var(--transition);
        }
        
        .footer-links a:hover {
            color: white;
            padding-left: 20px;
        }
        
        .footer-links a:hover::before {
            color: var(--secondary-color);
        }
        
        .social-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            margin-right: 10px;
            transition: var(--transition);
        }
        
        .social-links a:hover {
            background: var(--primary-color);
            transform: translateY(-5px);
        }
        
        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
            margin-top: 40px;
            text-align: center;
            font-size: 0.9rem;
            color: #adb5bd;
        }
        
        /* BTN Back To Top */
        .back-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--primary-color);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            z-index: 999;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .back-to-top.active {
            opacity: 1;
            visibility: visible;
        }
        
        .back-to-top:hover {
            background: var(--secondary-color);
            transform: translateY(-5px);
            color: white;
        }
        
        /* Page produit */
        .product-detail-section {
            padding: 80px 0;
            background: #f9f9f9;
        }
        
        .product-detail-img {
            width: 100%;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s ease;
        }
        
        .product-detail-img:hover {
            transform: scale(1.02);
        }
        
        .product-detail-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--dark-color);
        }
        
        .product-detail-price {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 20px;
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
        }
        
        .product-detail-description {
            margin: 30px 0;
            line-height: 1.8;
        }
        
        .product-detail-meta {
            margin-bottom: 30px;
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
        }
        
        .quantity-input {
            width: 80px;
            text-align: center;
            margin: 0 10px;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 5px;
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
        }
        
        /* Animations */
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
        
        /* Responsive */
        @media (max-width: 768px) {
            .carousel-item {
                height: 300px;
            }
            
            .carousel-caption h2 {
                font-size: 1.8rem;
            }
            
            .carousel-caption p {
                font-size: 1rem;
            }
            
            .product-card {
                margin-bottom: 20px;
            }
            
            .product-detail-img {
                margin-bottom: 30px;
            }
            
            .product-detail-actions {
                flex-direction: column;
            }
        }
    </style>

    <!-- Reste des sections de l'interface -->
    <section class="product-section" id="products">
        <div class="container">
            <h2 class="section-title">
                <?php if ($categorie_filter > 0): 
                    foreach ($categories as $cat) {
                        if ($cat['id'] == $categorie_filter) {
                            echo htmlspecialchars($cat['nom']);
                            break;
                        }
                    }
                elseif (!empty($search)): ?>
                    Résultats pour "<?= htmlspecialchars($search) ?>"
                <?php else: ?>
                    Tous nos produits
    <?php endif; ?>
            </h2>            

    <!-- Filtres -->
            <div class="filters mb-4">
                <div class="d-flex flex-wrap justify-content-center">
                    <a href="index.php" class="btn btn-outline-primary m-1 <?= !$categorie_filter ? 'active' : '' ?>">
                        <i class="fas fa-border-all me-1"></i>Tous
                        </a>
                        <?php foreach ($categories as $cat): ?>
                            <a href="?categorie=<?= $cat['id'] ?>" 
                           class="btn btn-outline-primary m-1 <?= $categorie_filter === $cat['id'] ? 'active' : '' ?>">
                            <i class="fas fa-tag me-1"></i><?= htmlspecialchars($cat['nom']) ?>
                            </a>
                        <?php endforeach; ?>
        </div>
    </div>

        <div class="row">
                <?php if ($result && $result->num_rows > 0): 
                    $index = 0;
                    while ($produit = $result->fetch_assoc()):
                        $index++;
                ?>
                    <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="<?= ($index % 4)?>">
                        <div class="product-card">
                            <?php if ($produit['promotion'] > 0): ?>
                                <div class="product-badge">-<?= $produit['promotion'] ?></div>
                            <?php endif; ?>
                            
                            <?php if ($produit['categorie_nom']): ?>
                                <div class="product-category"><?= htmlspecialchars($produit['categorie_nom']) ?></div>
                            <?php endif; ?>
                            
                            <div class="product-img-container">
                                <img src="<?= !empty($produit['image_url']) ? htmlspecialchars($produit['image_url']) : 'https://images.pexels.com/photos/1598505/pexels-photo-1598505.jpeg?auto=compress&cs=tinysrgb&w=600' ?>" 
                                     class="product-img" 
                                     alt="<?= htmlspecialchars($produit['nom']) ?>"
                                     loading="lazy">
                                <div class="product-overlay">
                                    <button class="btn-quick-view" 
                                            data-id="<?= $produit['id'] ?>" 
                                            data-name="<?= htmlspecialchars($produit['nom']) ?>" 
                                            data-price="<?= number_format($produit['prix'], 0) ?> CFA"
                                            data-category="<?= htmlspecialchars($produit['categorie_nom'] ?: 'Non catégorisé') ?>"
                                            data-brand="<?= htmlspecialchars($produit['marque'] ?: 'Non spécifiée') ?>"
                                            data-image="<?= !empty($produit['image_url']) ? htmlspecialchars($produit['image_url']) : 'https://images.pexels.com/photos/1598505/pexels-photo-1598505.jpeg?auto=compress&cs=tinysrgb&w=600' ?>">
                                        <i class="fas fa-eye"></i> Aperçu rapide
                                    </button>
                                </div>
                            </div>
                            
                            <div class="product-body">
                                <h5 class="product-title"><?= htmlspecialchars($produit['nom']) ?></h5>
                                <!-- <p class="product-brand">
                                    <i class="fas fa-trademark me-1"></i><?= htmlspecialchars($produit['marque'] ?: 'Non spécifiée') ?>
                                </p> -->
                                    <div class="product-price">
                                        <?php if ($produit['promotion'] > 0): ?>
                                        <span class="old-price"><?= number_format($produit['prix']) ?> CFA</span>
                                        <span class="text-danger"><?= number_format($produit['prix'] -($produit['promotion']), 0) ?> CFA</span>
                                        <?php else: ?>
                                        <?= number_format($produit['prix'], 0) ?> CFA
                                        <?php endif; ?>
                                    </div>
                                <div class="product-actions">
                                    <a href="produit.php?id=<?= $produit['id'] ?>" class="btn btn-view">
                                        <i class="fas fa-eye me-1"></i>Détails
                                    </a>
                                    <a href="ajouter_panier.php?id=<?= $produit['id'] ?>" class="btn btn-add">
                                        <i class="fas fa-cart-plus me-1"></i>Ajouter
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php 
                    endwhile;
                else: 
                ?>
                <div class="col-12 text-center py-5">
                        <i class="fas fa-search fa-3x mb-3 text-muted animate__animated animate__bounce"></i>
                    <h3>Aucun produit trouvé</h3>
                    <p class="text-muted">Essayez de modifier vos critères de recherche</p>
                    <a href="index.php" class="btn btn-primary mt-3">
                        <i class="fas fa-undo me-2"></i>Voir tous les produits
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <!-- Pagination des produits -->
        <div class="products-pagination mt-4 mb-5">
            <nav aria-label="Pagination des produits">
                <ul class="pagination justify-content-center">
                    <?php if ($products_page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="index.php?page=<?= $products_page - 1 ?><?= $categorie_filter ? '&categorie='.$categorie_filter : '' ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>#products" aria-label="Précédent">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= ($i == $products_page) ? 'active' : '' ?>">
                        <a class="page-link" href="index.php?page=<?= $i ?><?= $categorie_filter ? '&categorie='.$categorie_filter : '' ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>#products"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($products_page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="index.php?page=<?= $products_page + 1 ?><?= $categorie_filter ? '&categorie='.$categorie_filter : '' ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>#products" aria-label="Suivant">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
    </section>

    <!-- Section des caractéristiques -->
    <section class="feature-section py-5 bg-light">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3" data-aos="fade-up" data-aos-delay="100">
                    <div class="p-4">
                        <i class="fas fa-truck fa-3x text-primary mb-3"></i>
                        <h5>Livraison Rapide</h5>
                        <p class="text-muted">Livraison dans tout le pays</p>
                    </div>
                </div>
                <div class="col-md-3" data-aos="fade-up" data-aos-delay="200">
                    <div class="p-4">
                        <i class="fas fa-shield-alt fa-3x text-primary mb-3"></i>
                        <h5>Paiement Sécurisé</h5>
                        <p class="text-muted">Transactions 100% sécurisées</p>
                    </div>
                </div>
                <div class="col-md-3" data-aos="fade-up" data-aos-delay="300">
                    <div class="p-4">
                        <i class="fas fa-undo fa-3x text-primary mb-3"></i>
                        <h5>Retours Faciles</h5>
                        <p class="text-muted">Retours sous 30 jours</p>
                    </div>
                </div>
                <div class="col-md-3" data-aos="fade-up" data-aos-delay="400">
                    <div class="p-4">
                        <i class="fas fa-headset fa-3x text-primary mb-3"></i>
                        <h5>Assistance 24/7</h5>
                        <p class="text-muted">Service client à votre écoute</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer" id="contact">
        <div class="container">
            <div class="row footer-main">
                <div class="col-lg-4 mb-4 mb-lg-0">
                    <h4 class="footer-title"><?= htmlspecialchars($parametres['nom_site'] ?? 'AMAF-SHOP') ?></h4>
                    <p><?= htmlspecialchars($parametres['meta_description'] ?? 'Votre destination pour des produits de qualité à des prix compétitifs.') ?></p>
                    <div class="social-links mt-4">
                        <?php if (!empty($parametres['facebook_url'])): ?>
                        <a href="<?= htmlspecialchars($parametres['facebook_url']) ?>" target="_blank"><i class="fab fa-facebook-f"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($parametres['twitter_url'])): ?>
                        <a href="<?= htmlspecialchars($parametres['twitter_url']) ?>" target="_blank"><i class="fab fa-twitter"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($parametres['instagram_url'])): ?>
                        <a href="<?= htmlspecialchars($parametres['instagram_url']) ?>" target="_blank"><i class="fab fa-instagram"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($parametres['whatsapp_url'])): ?>
                        <a href="<?= htmlspecialchars($parametres['whatsapp_url']) ?>" target="_blank"><i class="fab fa-whatsapp"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($parametres['youtube_url'])): ?>
                        <a href="<?= htmlspecialchars($parametres['youtube_url']) ?>" target="_blank"><i class="fab fa-youtube"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-4 mb-md-0">
                    <h5 class="footer-title">Catégories</h5>
                    <ul class="footer-links">
                        <?php foreach ($categories as $cat): ?>
                            <li><a href="?categorie=<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nom']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-6 mb-4 mb-md-0">
                    <h5 class="footer-title">Mon compte</h5>
                    <ul class="footer-links">
                        <li><a href="profil.php">Mon profil</a></li>
                        <li><a href="mes_commandes.php">Mes commandes</a></li>
                        <li><a href="panier.php">Mon panier</a></li>
                        <li><a href="#">Liste de souhaits</a></li>
                    </ul>
                    </div>
                <div class="col-lg-4">
                    <h5 class="footer-title">Contactez-nous</h5>
                    <ul class="footer-links">
                        <li><i class="fas fa-map-marker-alt me-2"></i> <?= htmlspecialchars($parametres['adresse'] ?? '123 Rue du Commerce, Abidjan') ?></li>
                        <li><i class="fas fa-phone me-2"></i> <?= htmlspecialchars($parametres['telephone'] ?? '+225 07 07 48 95 45') ?></li>
                        <li><i class="fas fa-envelope me-2"></i> <?= htmlspecialchars($parametres['email_contact'] ?? 'contact@amaf-shop.com') ?></li>
                        <li><i class="fas fa-clock me-2"></i> Lun-Sam: 9h à 18h</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($parametres['nom_site'] ?? 'AMAF-SHOP') ?>. Tous droits réservés. Conçu avec <i class="fas fa-heart text-danger"></i></p>
            </div>
        </div>
    </footer>

    <!-- Modal d'aperçu rapide -->
    <div class="modal fade quick-view-modal" id="quickViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Aperçu du produit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <img src="" alt="Product" class="quick-view-img" id="quickViewImg">
                        </div>
                        <div class="col-md-6">
                            <div class="quick-view-details">
                                <h3 class="quick-view-title" id="quickViewTitle"></h3>
                                <div class="quick-view-price" id="quickViewPrice"></div>
                                <p class="quick-view-description" id="quickViewDescription"></p>
                                <div class="quick-view-meta">
                                    <span><i class="fas fa-tag"></i> <span id="quickViewCategory"></span></span>
                                    <span><i class="fas fa-trademark"></i> <span id="quickViewBrand"></span></span>
                                </div>
                                <div class="quick-view-actions">
                                    <a href="#" class="btn btn-primary" id="quickViewDetailsBtn">
                                        <i class="fas fa-eye me-1"></i>Voir les détails
                                    </a>
                                    <a href="#" class="btn btn-success" id="quickViewAddBtn">
                                        <i class="fas fa-cart-plus me-1"></i>Ajouter au panier
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script>
        // Affichage du contenu une fois que tout est chargé
        window.addEventListener('load', function() {
            document.body.style.opacity = '1';
            setTimeout(function() {
                document.getElementById('loader').style.display = 'none';
            }, 500);
            
            // Initialiser AOS pour les animations au scroll
            AOS.init({
                duration: 800,
                easing: 'ease-in-out',
                once: true,
                offset: 50
            });
        });
        
        // Précherger les images pour une expérience plus fluide
        function preloadImages() {
            const productImages = document.querySelectorAll('.product-img');
            productImages.forEach(img => {
                const src = img.getAttribute('src');
                if (src) {
                    const newImg = new Image();
                    newImg.src = src;
                }
            });
        }
        
        // Bouton retour en haut
        const backToTopBtn = document.getElementById('backToTop');
        
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTopBtn.style.display = 'flex';
                setTimeout(() => {
                    backToTopBtn.classList.add('active');
                }, 50);
            } else {
                backToTopBtn.classList.remove('active');
                setTimeout(() => {
                    if (!backToTopBtn.classList.contains('active')) {
                        backToTopBtn.style.display = 'none';
                    }
                }, 300);
            }
        });
        
        backToTopBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        
        // Animation des produits au survol
        document.querySelectorAll('.product-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.classList.add('animate__animated', 'animate__pulse');
            });
            
            card.addEventListener('mouseleave', function() {
                this.classList.remove('animate__animated', 'animate__pulse');
            });
        });
        
        // Modal d'aperçu rapide
        const quickViewModal = new bootstrap.Modal(document.getElementById('quickViewModal'));
        
        document.querySelectorAll('.btn-quick-view').forEach(btn => {
            btn.addEventListener('click', function() {
                const productId = this.getAttribute('data-id');
                const productName = this.getAttribute('data-name');
                const productPrice = this.getAttribute('data-price');
                const productCategory = this.getAttribute('data-category');
                const productBrand = this.getAttribute('data-brand');
                const productImage = this.getAttribute('data-image');
                
                // Remplir les détails dans la modal
                document.getElementById('quickViewImg').src = productImage;
                document.getElementById('quickViewTitle').textContent = productName;
                document.getElementById('quickViewPrice').textContent = productPrice;
                document.getElementById('quickViewDescription').textContent = 'Ce produit est conçu pour offrir style, confort et durabilité. Il s\'agit d\'un article de grande qualité parfait pour toutes les occasions.';
                document.getElementById('quickViewCategory').textContent = productCategory;
                document.getElementById('quickViewBrand').textContent = productBrand;
                
                document.getElementById('quickViewDetailsBtn').href = 'produit.php?id=' + productId;
                document.getElementById('quickViewAddBtn').href = 'ajouter_panier.php?id=' + productId;
                
                // Afficher la modal avec une animation
                const quickViewModalElement = document.getElementById('quickViewModal');
                quickViewModalElement.classList.add('animate__animated', 'animate__fadeIn');
                quickViewModal.show();
                
                // Retirer l'animation après l'affichage pour permettre de la réutiliser
                setTimeout(() => {
                    quickViewModalElement.classList.remove('animate__animated', 'animate__fadeIn');
                }, 500);
            });
        });
        
        // Page produit améliorée
        document.addEventListener('DOMContentLoaded', function() {
            // Pour la page produit détaillée
            const productPage = document.querySelector('.product-detail-section');
            if (productPage) {
                // Zoom sur l'image au survol
                const productImg = document.querySelector('.product-detail-img');
                if (productImg) {
                    productImg.addEventListener('mousemove', function(e) {
                        const x = e.clientX - this.offsetLeft;
                        const y = e.clientY - this.offsetTop;
                        const xPercent = x / this.offsetWidth * 100;
                        const yPercent = y / this.offsetHeight * 100;
                        this.style.transformOrigin = `${xPercent}% ${yPercent}%`;
                    });
                }
                
                // Gestion de la quantité
                const minusBtn = document.querySelector('.quantity-minus');
                const plusBtn = document.querySelector('.quantity-plus');
                const quantityInput = document.querySelector('.quantity-input');
                
                if (minusBtn && plusBtn && quantityInput) {
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
                }
            }
        });

        // Précharger les images
        preloadImages();
    </script>
</body>
</html>