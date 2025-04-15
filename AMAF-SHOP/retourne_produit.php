<?php
session_start();
require 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Vérifier si une demande de retour est soumise
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['retourner_produit'])) {
    $commande_id = $_POST['commande_id'];
    $produit_id = $_POST['produit_id'];
    $raison = trim($_POST['raison']);

    if (empty($raison)) {
        $error = "Veuillez indiquer la raison du retour.";
    } else {
        $query = "INSERT INTO retours (utilisateur_id, commande_id, produit_id, raison, statut) VALUES (?, ?, ?, ?, 'En attente')";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiis", $user_id, $commande_id, $produit_id, $raison);

        if ($stmt->execute()) {
            $success = "Votre demande de retour a été soumise.";
        } else {
            $error = "Erreur lors de la soumission du retour.";
        }
    }
}

// Récupérer les commandes livrées
$query = "SELECT c.id AS commande_id, p.id AS produit_id, p.nom
          FROM commande c
          JOIN commande_produit cp ON c.id = cp.commande_id
          JOIN produit p ON cp.produit_id = p.id
          WHERE c.utilisateur_id = ? AND c.statut = 'Livrée'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$commandes = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Retourner un produit</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-4">
        <h2>Retourner un produit</h2>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"> <?= htmlspecialchars($success) ?> </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"> <?= htmlspecialchars($error) ?> </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="commande" class="form-label">Sélectionnez une commande :</label>
                <select class="form-control" name="commande_id" required>
                    <?php while ($commande = $commandes->fetch_assoc()): ?>
                        <option value="<?= $commande['commande_id'] ?>">
                            Commande #<?= $commande['commande_id'] ?> - <?= htmlspecialchars($commande['nom']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="raison" class="form-label">Raison du retour :</label>
                <textarea class="form-control" name="raison" required></textarea>
            </div>
            <button type="submit" name="retourner_produit" class="btn btn-danger">Retourner le produit</button>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
