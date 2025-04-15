<?php
session_start();
require 'config.php';

$error = '';
$success = '';

// Si l'utilisateur est déjà connecté, le rediriger selon son type
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['user_type']) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'livreur':
            header('Location: livreur/dashboard.php');
            break;
        case 'client':
            header('Location: index.php');
            break;
    }
    exit();
}

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $mot_de_passe = trim($_POST['mot_de_passe'] ?? '');
    
    if (empty($email) || empty($mot_de_passe)) {
        $error = "Veuillez remplir tous les champs.";
    } else {
        try {
            // Vérifier si l'utilisateur existe avec cet email
            $query = "SELECT id, nom, email, mot_de_passe, type, utilisateur FROM utilisateur WHERE email = ? LIMIT 1";
            $stmt = $conn->prepare($query);
            
            if (!$stmt) {
                throw new Exception("Erreur de préparation de la requête");
            }
            
            $stmt->bind_param("s", $email);
            
            if (!$stmt->execute()) {
                throw new Exception("Erreur d'exécution de la requête");
            }
            
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if (!$user) {
                $error = "Email ou mot de passe incorrect.";
            } 
            // Vérification directe du mot de passe (sans hachage pour le moment)
            elseif ($mot_de_passe === $user['mot_de_passe']) {
                // Connexion réussie - Création des variables de session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['nom'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_type'] = $user['type'];
                $_SESSION['username'] = $user['utilisateur'];

                // Redirection selon le type d'utilisateur
                switch ($user['type']) {
                    case 'admin':
                        header('Location: admin/dashboard.php');
                        break;
                    case 'livreur':
                        header('Location: livreur/dashboard.php');
                        break;
                    case 'client':
                    default:
                        header('Location: index.php');
                        break;
                }
                exit();
            } else {
                $error = "Email ou mot de passe incorrect.";
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $error = "Une erreur est survenue. Veuillez réessayer plus tard.";
            // Pour le débogage (à commenter en production) :
            // $error .= " " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - AMAF-SHOP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .login-container {
            max-width: 400px;
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
        <div class="login-container">
            <h2 class="text-center mb-4">Connexion</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="mot_de_passe" class="form-label">Mot de passe</label>
                            <input type="password" class="form-control" id="mot_de_passe" name="mot_de_passe" required>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Se souvenir de moi</label>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                            </button>
                        </div>
                    </form>

                    <div class="text-center mt-3">
                        <a href="forgot_password.php" class="text-decoration-none">
                            Mot de passe oublié ?
                        </a>
                    </div>
                </div>
            </div>

            <div class="text-center mt-3">
                Pas encore de compte ? 
                <a href="register.php" class="text-decoration-none">
                    Inscrivez-vous
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 