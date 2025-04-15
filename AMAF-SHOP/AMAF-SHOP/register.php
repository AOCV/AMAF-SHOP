<?php
session_start();
require 'config.php';

$error = '';
$success = '';

// Si l'utilisateur est déjà connecté, le rediriger
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Traitement du formulaire d'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $adresse = trim($_POST['adresse'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $utilisateur = trim($_POST['utilisateur'] ?? '');
    
    // Validation des données
    if (empty($nom) || empty($email) || empty($mot_de_passe) || empty($utilisateur)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse email n'est pas valide.";
    } elseif ($mot_de_passe !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        // Vérifier si l'email existe déjà
        $query = "SELECT id FROM utilisateur WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Cette adresse email est déjà utilisée.";
        } else {
            // Insérer le nouvel utilisateur
            $query = "INSERT INTO utilisateur (nom, email, mot_de_passe, adresse, telephone, type, utilisateur) 
                     VALUES (?, ?, ?, ?, ?, 'client', ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssss", $nom, $email, $mot_de_passe, $adresse, $telephone, $utilisateur);
            
            if ($stmt->execute()) {
                $success = "Inscription réussie ! Vous pouvez maintenant vous connecter.";
                header("refresh:2;url=login.php");
            } else {
                $error = "Une erreur est survenue lors de l'inscription.";
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - AMAF-SHOP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .register-container {
            max-width: 500px;
            margin: 40px auto;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-store me-2"></i>AMAF-SHOP
            </a>
        </div>
    </nav>

    <div class="container">
        <div class="register-container">
            <h2 class="text-center mb-4">Inscription</h2>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="" class="needs-validation">
                        <div class="mb-3">
                            <label for="nom" class="form-label">Nom complet *</label>
                            <input type="text" class="form-control" id="nom" name="nom" 
                                   value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="utilisateur" class="form-label">Nom d'utilisateur *</label>
                            <input type="text" class="form-control" id="utilisateur" name="utilisateur" 
                                   value="<?= htmlspecialchars($_POST['utilisateur'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="adresse" class="form-label">Adresse</label>
                            <input type="text" class="form-control" id="adresse" name="adresse" 
                                   value="<?= htmlspecialchars($_POST['adresse'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label for="telephone" class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" id="telephone" name="telephone" 
                                   value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label for="mot_de_passe" class="form-label">Mot de passe *</label>
                            <input type="password" class="form-control" id="mot_de_passe" 
                                   name="mot_de_passe" required>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirmer le mot de passe *</label>
                            <input type="password" class="form-control" id="confirm_password" 
                                   name="confirm_password" required>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="terms" required>
                            <label class="form-check-label" for="terms">
                                J'accepte les <a href="#" class="text-decoration-none">conditions d'utilisation</a>
                            </label>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-user-plus me-2"></i>S'inscrire
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="text-center mt-3">
                Déjà inscrit ? 
                <a href="login.php" class="text-decoration-none">
                    Connectez-vous
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 