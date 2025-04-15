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

    // Traitement de l'image
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
                $image_url = $destination;
            }
        }
    }

    if (empty($nom) || empty($description) || $prix <= 0 || $stock < 0) {
        $error = "Veuillez remplir tous les champs obligatoires correctement.";
    } else {
        $sql = "INSERT INTO produit (nom, description, prix, stock, categorie_id, taille, couleur, marque, promotion, image_url) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdiisssds", $nom, $description, $prix, $stock, $categorie_id, $taille, $couleur, $marque, $promotion, $image_url);

        if ($stmt->execute()) {
            $message = "Produit ajouté avec succès !";
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
    <title>Ajouter un Produit</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2>Ajouter un Produit</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="mt-4">
            <div class="mb-3">
                <label for="nom" class="form-label">Nom du produit *</label>
                <input type="text" class="form-control" id="nom" name="nom" required>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description *</label>
                <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="prix" class="form-label">Prix (€) *</label>
                    <input type="number" class="form-control" id="prix" name="prix" step="0.01" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="stock" class="form-label">Stock *</label>
                    <input type="number" class="form-control" id="stock" name="stock" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="categorie_id" class="form-label">Catégorie</label>
                    <select class="form-control" id="categorie_id" name="categorie_id">
                        <option value="">Sélectionner une catégorie</option>
                        <?php foreach ($categories as $categorie): ?>
                            <option value="<?= $categorie['id'] ?>"><?= htmlspecialchars($categorie['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="promotion" class="form-label">Promotion (€)</label>
                    <input type="number" class="form-control" id="promotion" name="promotion" step="0.01">
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="taille" class="form-label">Taille</label>
                    <input type="text" class="form-control" id="taille" name="taille">
                </div>

                <div class="col-md-4 mb-3">
                    <label for="couleur" class="form-label">Couleur</label>
                    <input type="text" class="form-control" id="couleur" name="couleur">
                </div>

                <div class="col-md-4 mb-3">
                    <label for="marque" class="form-label">Marque</label>
                    <input type="text" class="form-control" id="marque" name="marque">
                </div>
            </div>

            <div class="mb-3">
                <label for="image" class="form-label">Image du produit</label>
                <input type="file" class="form-control" id="image" name="image" accept="image/*">
            </div>

            <button type="submit" class="btn btn-primary">Ajouter le produit</button>
            <a href="index.php" class="btn btn-secondary">Retour</a>
        </form>
    </div>
</body>
</html> 