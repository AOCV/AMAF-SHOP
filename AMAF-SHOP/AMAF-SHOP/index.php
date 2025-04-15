<?php
session_start();
require 'config.php';

// Récupérer les catégories
$categories = [];
$query_categories = "SELECT id, nom FROM categorie";
$result_categories = $conn->query($query_categories);
if ($result_categories) {
    while ($row = $result_categories->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Filtrage par catégorie
$categorie_filter = isset($_GET['categorie']) ? intval($_GET['categorie']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Construction de la requête SQL avec filtres
$query = "SELECT p.*, c.nom as categorie_nom 
          FROM produit p 
          LEFT JOIN categorie c ON p.categorie_id = c.id 
          WHERE 1=1";

if ($categorie_filter > 0) {
    $query .= " AND p.categorie_id = " . $categorie_filter;
}

if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $query .= " AND (p.nom LIKE '%$search%' OR p.description LIKE '%$search%' OR p.marque LIKE '%$search%')";
}

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ma Boutique en Ligne</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --accent-color: #e74c3c;
        }
        
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('assets/hero-bg.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 150px 0;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            animation: fadeInDown 1s ease;
        }
        
        .hero-section .lead {
            font-size: 1.5rem;
            margin-bottom: 2rem;
            animation: fadeInUp 1s ease;
        }
        
        .card {
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-radius: 15px;
            overflow: hidden;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .card-img-top {
            height: 250px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .card:hover .card-img-top {
            transform: scale(1.05);
        }
        
        .promotion-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: var(--accent-color);
            color: white;
            padding: 8px 15px;
            border-radius: 25px;
            font-weight: 600;
            z-index: 1;
            animation: pulse 2s infinite;
        }
        
        .filters-section {
            background-color: #f8f9fa;
            padding: 30px 0;
            margin-bottom: 40px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .btn-outline-primary {
            border-radius: 25px;
            padding: 8px 20px;
            margin: 5px;
            transition: all 0.3s ease;
        }
        
        .btn-outline-primary:hover {
            transform: translateY(-2px);
        }
        
        .navbar {
            padding: 15px 0;
            background: linear-gradient(to right, #2c3e50, #3498db) !important;
        }
        
        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .search-form {
            position: relative;
        }
        
        .search-form input {
            border-radius: 25px;
            padding-right: 40px;
        }
        
        .search-form button {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
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
        
        .product-price {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .category-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background-color: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

    <!-- Messages -->
    <?php if (isset($_GET['success'])): ?>
        <div class="container mt-3">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_GET['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="container mt-3">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_GET['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Barre de navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
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
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            Catégories
                        </a>
                        <ul class="dropdown-menu">
                            <?php foreach ($categories as $cat): ?>
                                <li><a class="dropdown-item" href="?categorie=<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nom']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                </ul>
                <form class="d-flex me-3" action="" method="GET">
                    <input class="form-control me-2" type="search" name="search" placeholder="Rechercher..." value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-outline-light" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="panier.php">
                            <i class="fas fa-shopping-cart"></i> Panier
                            <?php if (!empty($_SESSION['panier'])): ?>
                                <span class="badge bg-danger rounded-pill">
                                    <?= array_sum($_SESSION['panier']) ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-2"></i><?= htmlspecialchars($_SESSION['user_name']) ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="profil.php">Mon Profil</a></li>
                                <li><a class="dropdown-item" href="mes_commandes.php">Mes Commandes</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Déconnexion</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">
                                <i class="fas fa-sign-in-alt me-2"></i>Connexion
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">
                                <i class="fas fa-user-plus me-2"></i>Inscription
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Section Hero -->
    <div class="hero-section text-center">
        <div class="container">
            <h1>Découvrez l'Excellence chez AMAF-SHOP</h1>
            <p class="lead">Votre destination pour des produits de qualité supérieure</p>
            <div class="mt-4">
                <a href="#products" class="btn btn-primary btn-lg me-3 rounded-pill">
                    <i class="fas fa-shopping-bag me-2"></i>Découvrir nos produits
                </a>
                <a href="#categories" class="btn btn-outline-light btn-lg rounded-pill">
                    <i class="fas fa-th-list me-2"></i>Parcourir les catégories
                </a>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="filters-section" id="categories">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <h4 class="mb-3"><i class="fas fa-filter me-2"></i>Filtrer par catégorie</h4>
                </div>
                <div class="col-md-8">
                    <div class="d-flex flex-wrap">
                        <a href="index.php" class="btn btn-outline-primary <?= !$categorie_filter ? 'active' : '' ?>">
                            <i class="fas fa-border-all me-2"></i>Tous les produits
                        </a>
                        <?php foreach ($categories as $cat): ?>
                            <a href="?categorie=<?= $cat['id'] ?>" 
                               class="btn btn-outline-primary <?= $categorie_filter === $cat['id'] ? 'active' : '' ?>">
                                <i class="fas fa-tag me-2"></i><?= htmlspecialchars($cat['nom']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Liste des produits -->
    <div class="container mb-5" id="products">
        <div class="row">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($produit = $result->fetch_assoc()): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <?php if ($produit['promotion'] > 0): ?>
                                <div class="promotion-badge">
                                    -<?= $produit['promotion'] ?>%
                                </div>
                            <?php endif; ?>
                            <?php if ($produit['categorie_nom']): ?>
                                <div class="category-badge">
                                    <?= htmlspecialchars($produit['categorie_nom']) ?>
                                </div>
                            <?php endif; ?>
                            <img src="<?= htmlspecialchars($produit['image_url']) ?>" 
                                 class="card-img-top" 
                                 alt="<?= htmlspecialchars($produit['nom']) ?>">
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($produit['nom']) ?></h5>
                                <p class="card-text text-muted mb-2">
                                    <i class="fas fa-trademark me-2"></i><?= htmlspecialchars($produit['marque']) ?>
                                </p>
                                <p class="card-text"><?= nl2br(htmlspecialchars($produit['description'])) ?></p>
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div class="product-price">
                                        <?php if ($produit['promotion'] > 0): ?>
                                            <span class="text-decoration-line-through text-muted me-2">
                                                <?= number_format($produit['prix'], 2) ?>€
                                            </span>
                                            <span class="text-danger">
                                                <?= number_format($produit['prix'] * (1 - $produit['promotion']/100), 2) ?>€
                                            </span>
                                        <?php else: ?>
                                            <?= number_format($produit['prix'], 2) ?>€
                                        <?php endif; ?>
                                    </div>
                                    <a href="ajouter_panier.php?id=<?= $produit['id'] ?>" 
                                       class="btn btn-primary rounded-pill">
                                        <i class="fas fa-cart-plus me-2"></i>Ajouter
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <i class="fas fa-search fa-3x mb-3 text-muted"></i>
                    <h3>Aucun produit trouvé</h3>
                    <p class="text-muted">Essayez de modifier vos critères de recherche</p>
                    <a href="index.php" class="btn btn-primary mt-3">
                        <i class="fas fa-undo me-2"></i>Voir tous les produits
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5>À propos</h5>
                    <p>Notre boutique en ligne vous propose une sélection de produits de qualité.</p>
                </div>
                <div class="col-md-4">
                    <h5>Liens utiles</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-light">Conditions générales de vente</a></li>
                        <li><a href="#" class="text-light">Politique de confidentialité</a></li>
                        <li><a href="#" class="text-light">Nous contacter</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Suivez-nous</h5>
                    <div class="social-links">
                        <a href="#" class="text-light me-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-light me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-light me-2"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>