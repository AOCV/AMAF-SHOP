<?php
session_start();
require 'config.php';

// // Vérifier si l'utilisateur est connecté et est un admin
// if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
//     header('Location: login.php');
//     exit();
// }

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
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer un Produit</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>Gestion des Produits</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="mb-3">
            <a href="./admin/dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
            <a href="ajout_produit.php" class="btn btn-success">
                <i class="fas fa-plus"></i> Ajouter un produit
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Nom</th>
                        <th>Catégorie</th>
                        <th>Prix</th>
                        <th>Stock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($produit = $result_produits->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php if ($produit['image_url']): ?>
                                    <img src="<?= htmlspecialchars($produit['image_url']) ?>" 
                                         alt="<?= htmlspecialchars($produit['nom']) ?>" 
                                         class="product-image">
                                <?php else: ?>
                                    <i class="fas fa-image text-muted"></i>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($produit['nom']) ?></td>
                            <td><?= htmlspecialchars($produit['categorie_nom'] ?? 'Sans catégorie') ?></td>
                            <td><?= number_format($produit['prix'], 2) ?> €</td>
                            <td>
                                <?php if ($produit['stock'] <= 0): ?>
                                    <span class="badge bg-danger">Rupture</span>
                                <?php elseif ($produit['stock'] < 10): ?>
                                    <span class="badge bg-warning"><?= $produit['stock'] ?></span>
                                <?php else: ?>
                                    <span class="badge bg-success"><?= $produit['stock'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="modif_produit.php?id=<?= $produit['id'] ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" 
                                            class="btn btn-sm btn-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteModal<?= $produit['id'] ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Modal de confirmation de suppression -->
                        <div class="modal fade" id="deleteModal<?= $produit['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Confirmer la suppression</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        Êtes-vous sûr de vouloir supprimer le produit "<?= htmlspecialchars($produit['nom']) ?>" ?
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                        <a href="?id=<?= $produit['id'] ?>" class="btn btn-danger">Supprimer</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 