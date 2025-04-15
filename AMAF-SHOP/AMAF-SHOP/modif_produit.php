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
        // Traitement de la nouvelle image si fournie
        $image_url = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = uniqid() . '.' . $file_extension;
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                    // Supprimer l'ancienne image si elle existe
                    $query_old_image = "SELECT image_url FROM produit WHERE id = ?";
                    $stmt_old_image = $conn->prepare($query_old_image);
                    $stmt_old_image->bind_param("i", $id_produit);
                    $stmt_old_image->execute();
                    $result_old_image = $stmt_old_image->get_result();
                    $old_image = $result_old_image->fetch_assoc();

                    if ($old_image && !empty($old_image['image_url']) && file_exists($old_image['image_url'])) {
                        unlink($old_image['image_url']);
                    }

                    $image_url = $destination;
                }
            }
        }

        // Mise à jour du produit
        $sql = "UPDATE produit SET 
                nom = ?, 
                description = ?, 
                prix = ?, 
                stock = ?, 
                categorie_id = ?, 
                taille = ?, 
                couleur = ?, 
                marque = ?, 
                promotion = ?";

        $params = [$nom, $description, $prix, $stock, $categorie_id, $taille, $couleur, $marque, $promotion];
        $types = "ssdiisssd";

        if ($image_url !== null) {
            $sql .= ", image_url = ?";
            $params[] = $image_url;
            $types .= "s";
        }

        $sql .= " WHERE id = ?";
        $params[] = $id_produit;
        $types .= "i";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $message = "Produit modifié avec succès !";
        } else {
            $error = "Erreur lors de la modification du produit : " . $conn->error;
        }
        $stmt->close();
    }
}

// Récupérer les informations du produit
$query = "SELECT * FROM produit WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_produit);
$stmt->execute();
$result = $stmt->get_result();
$produit = $result->fetch_assoc();

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
    <title>Modifier un Produit</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2>Modifier le Produit</h2>
        
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

        <form method="POST" enctype="multipart/form-data" class="mt-4">
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label for="nom" class="form-label">Nom du produit *</label>
                        <input type="text" class="form-control" id="nom" name="nom" 
                               value="<?= htmlspecialchars($produit['nom']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description *</label>
                        <textarea class="form-control" id="description" name="description" 
                                  rows="3" required><?= htmlspecialchars($produit['description']) ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="prix" class="form-label">Prix (€) *</label>
                            <input type="number" class="form-control" id="prix" name="prix" 
                                   step="0.01" value="<?= $produit['prix'] ?>" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="stock" class="form-label">Stock</label>
                            <input type="number" class="form-control" id="stock" name="stock" 
                                   value="<?= $produit['stock'] ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="categorie_id" class="form-label">Catégorie</label>
                            <select class="form-control" id="categorie_id" name="categorie_id">
                                <option value="">Sélectionner une catégorie</option>
                                <?php foreach ($categories as $categorie): ?>
                                    <option value="<?= $categorie['id'] ?>" 
                                            <?= $produit['categorie_id'] == $categorie['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($categorie['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="promotion" class="form-label">Promotion (€)</label>
                            <input type="number" class="form-control" id="promotion" name="promotion" 
                                   step="0.01" value="<?= $produit['promotion'] ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="taille" class="form-label">Taille</label>
                            <input type="text" class="form-control" id="taille" name="taille" 
                                   value="<?= htmlspecialchars($produit['taille']) ?>">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="couleur" class="form-label">Couleur</label>
                            <input type="text" class="form-control" id="couleur" name="couleur" 
                                   value="<?= htmlspecialchars($produit['couleur']) ?>">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="marque" class="form-label">Marque</label>
                            <input type="text" class="form-control" id="marque" name="marque" 
                                   value="<?= htmlspecialchars($produit['marque']) ?>">
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="image" class="form-label">Image du produit</label>
                        <?php if ($produit['image_url']): ?>
                            <img src="<?= htmlspecialchars($produit['image_url']) ?>" 
                                 alt="Image actuelle" class="img-fluid mb-2">
                            <p class="text-muted">Image actuelle</p>
                        <?php endif; ?>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                <a href="supprime_produit.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 