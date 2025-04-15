<?php
session_start();
require 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$success = '';
$error = '';

// Récupérer les informations de l'utilisateur
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM utilisateur WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Traitement du formulaire de mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_info'])) {
        // Mise à jour des informations
        $nom = trim($_POST['nom']);
        $email = trim($_POST['email']);
        $telephone = trim($_POST['telephone']);
        $adresse = trim($_POST['adresse']);

        if (empty($nom) || empty($email)) {
            $error = "Le nom et l'email sont obligatoires.";
        } else {
            $query = "UPDATE utilisateur SET nom = ?, email = ?, telephone = ?, adresse = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssi", $nom, $email, $telephone, $adresse, $user_id);
            
            if ($stmt->execute()) {
                $success = "Informations mises à jour avec succès.";
                $_SESSION['user_name'] = $nom;
                // Recharger les informations
                header("Refresh:0");
            } else {
                $error = "Erreur lors de la mise à jour.";
            }
        }
    } elseif (isset($_POST['update_password'])) {
        // Mise à jour du mot de passe
        $ancien_mdp = $_POST['ancien_mdp'];
        $nouveau_mdp = $_POST['nouveau_mdp'];
        $confirm_mdp = $_POST['confirm_mdp'];

        if ($nouveau_mdp !== $confirm_mdp) {
            $error = "Les nouveaux mots de passe ne correspondent pas.";
        } elseif ($ancien_mdp !== $user['mot_de_passe']) {
            $error = "Ancien mot de passe incorrect.";
        } else {
            $query = "UPDATE utilisateur SET mot_de_passe = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $nouveau_mdp, $user_id);
            
            if ($stmt->execute()) {
                $success = "Mot de passe mis à jour avec succès.";
            } else {
                $error = "Erreur lors de la mise à jour du mot de passe.";
            }
        }
    }
}

// Récupérer l'historique des commandes
$query = "SELECT c.*, COUNT(cp.produit_id) as nb_produits 
          FROM commande c 
          LEFT JOIN commande_produit cp ON c.id = cp.commande_id 
          WHERE c.utilisateur_id = ? 
          GROUP BY c.id 
          ORDER BY c.date_commande DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$commandes = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - AMAF-SHOP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-store me-2"></i>AMAF-SHOP
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home me-2"></i>Accueil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="mes_commandes.php">
                            <i class="fas fa-shopping-bag me-2"></i>Mes Commandes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2 class="mb-4">Mon Profil</h2>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Informations du profil -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Informations personnelles</h5>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                            <i class="fas fa-edit me-2"></i>Modifier
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="fw-bold">Nom :</label>
                            <p><?= htmlspecialchars($user['nom']) ?></p>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">Email :</label>
                            <p><?= htmlspecialchars($user['email']) ?></p>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">Téléphone :</label>
                            <p><?= htmlspecialchars($user['telephone'] ?: 'Non renseigné') ?></p>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">Adresse :</label>
                            <p><?= htmlspecialchars($user['adresse'] ?: 'Non renseignée') ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sécurité -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Sécurité</h5>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                            <i class="fas fa-key me-2"></i>Changer le mot de passe
                        </button>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">
                            <i class="fas fa-info-circle me-2"></i>
                            Pour votre sécurité, choisissez un mot de passe unique et gardez-le confidentiel.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Modification Profil -->
    <div class="modal fade" id="editProfileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier mes informations</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="update_info" value="1">
                        <div class="mb-3">
                            <label class="form-label">Nom *</label>
                            <input type="text" class="form-control" name="nom" value="<?= htmlspecialchars($user['nom']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" name="telephone" value="<?= htmlspecialchars($user['telephone']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Adresse</label>
                            <textarea class="form-control" name="adresse" rows="3"><?= htmlspecialchars($user['adresse']) ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Changement Mot de passe -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Changer mon mot de passe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="update_password" value="1">
                        <div class="mb-3">
                            <label class="form-label">Ancien mot de passe *</label>
                            <input type="password" class="form-control" name="ancien_mdp" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nouveau mot de passe *</label>
                            <input type="password" class="form-control" name="nouveau_mdp" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirmer le nouveau mot de passe *</label>
                            <input type="password" class="form-control" name="confirm_mdp" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Changer le mot de passe</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 