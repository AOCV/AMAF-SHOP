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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary-color: #3a7bd5;
            --secondary-color: #00d2ff;
            --text-color: #333;
            --light-bg: #f8f9fa;
            --card-shadow: 0 10px 20px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding-bottom: 3rem;
            color: var(--text-color);
        }
        
        .navbar {
            background: linear-gradient(45deg, #3a7bd5, #00d2ff) !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        .page-title {
            position: relative;
            display: inline-block;
            font-weight: 700;
            margin-bottom: 2rem;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .page-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 50px;
            height: 4px;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }
        
        .profile-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            margin-bottom: 2rem;
            animation: fadeInUp 0.5s ease-out;
        }
        
        .profile-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.12);
        }
        
        .card-header {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            font-weight: 600;
            padding: 1.25rem;
            position: relative;
            overflow: hidden;
        }
        
        .card-header::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: shine 2s infinite;
        }
        
        @keyframes shine {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .btn-edit {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            transition: var(--transition);
        }
        
        .btn-edit:hover {
            background: rgba(255,255,255,0.3);
            color: white;
            transform: scale(1.05);
        }
        
        .info-label {
            color: #6c757d;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            display: block;
        }
        
        .info-text {
            background-color: var(--light-bg);
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            position: relative;
            padding-left: 40px;
            transition: var(--transition);
        }
        
        .info-text:hover {
            transform: translateX(5px);
            background-color: #e9ecef;
        }
        
        .info-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
            font-size: 1.2rem;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border: none;
            box-shadow: 0 4px 15px rgba(58, 123, 213, 0.4);
            transition: var(--transition);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(58, 123, 213, 0.6);
            background: linear-gradient(45deg, var(--secondary-color), var(--primary-color));
        }
        
        .modal-content {
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-bottom: none;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 0.75rem;
            border: 1px solid #ced4da;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(58, 123, 213, 0.25);
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
        }
        
        .security-tip {
            background: rgba(58, 123, 213, 0.1);
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            border-radius: 0 8px 8px 0;
            margin: 1rem 0;
        }
        
        .security-tip i {
            color: var(--primary-color);
            margin-right: 0.5rem;
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
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            animation: fadeIn 0.5s ease-out;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            box-shadow: 0 5px 15px rgba(58, 123, 213, 0.5);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .stats-container {
            display: flex;
            margin-top: 1rem;
            gap: 1rem;
        }
        
        .stat-item {
            flex: 1;
            background-color: white;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
        }
        
        .stat-item:hover {
            transform: translateY(-5px);
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 animate__animated animate__fadeIn">
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

    <div class="container">
        <h2 class="page-title animate__animated animate__fadeInDown">Mon Profil</h2>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeIn">
                <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show animate__animated animate__fadeIn">
                <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-4 animate__animated animate__fadeInLeft" style="animation-delay: 0.2s">
                <div class="profile-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-user-circle me-2"></i>Informations personnelles</h5>
                        <button type="button" class="btn btn-edit btn-sm" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                            <i class="fas fa-edit me-2"></i>Modifier
                        </button>
                    </div>
                    <div class="card-body text-center">
                        <div class="profile-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        
                        <div class="info-text">
                            <i class="fas fa-user info-icon"></i>
                            <span class="info-label">Nom</span>
                            <strong><?= htmlspecialchars($user['nom']) ?></strong>
                        </div>
                        
                        <div class="info-text">
                            <i class="fas fa-envelope info-icon"></i>
                            <span class="info-label">Email</span>
                            <strong><?= htmlspecialchars($user['email']) ?></strong>
                        </div>
                        
                        <div class="info-text">
                            <i class="fas fa-phone info-icon"></i>
                            <span class="info-label">Téléphone</span>
                            <strong><?= htmlspecialchars($user['telephone'] ?: 'Non renseigné') ?></strong>
                        </div>
                        
                        <div class="info-text">
                            <i class="fas fa-map-marker-alt info-icon"></i>
                            <span class="info-label">Adresse</span>
                            <strong><?= htmlspecialchars($user['adresse'] ?: 'Non renseignée') ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="row">
                    <div class="col-12 animate__animated animate__fadeInRight" style="animation-delay: 0.3s">
                        <div class="profile-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Sécurité</h5>
                                <button type="button" class="btn btn-edit btn-sm" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                                    <i class="fas fa-key me-2"></i>Changer le mot de passe
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="security-tip">
                                    <i class="fas fa-info-circle"></i>
                                    Pour votre sécurité, choisissez un mot de passe unique et gardez-le confidentiel.
                                </div>
                                
                                <div class="security-tip">
                                    <i class="fas fa-lock"></i>
                                    Un bon mot de passe contient au moins 8 caractères, des lettres majuscules, minuscules, des chiffres et des caractères spéciaux.
                                </div>
                                
                                <div class="security-tip">
                                    <i class="fas fa-history"></i>
                                    Nous vous recommandons de changer votre mot de passe régulièrement.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12 mt-4 animate__animated animate__fadeInUp" style="animation-delay: 0.4s">
                        <div class="profile-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Statistiques du compte</h5>
                            </div>
                            <div class="card-body">
                                <div class="stats-container">
                                    <div class="stat-item">
                                        <i class="fas fa-shopping-bag text-primary"></i>
                                        <div class="stat-value"><?= $commandes->num_rows ?></div>
                                        <div class="stat-label">Commandes passées</div>
                                    </div>
                                    
                                    <div class="stat-item">
                                        <i class="fas fa-calendar-alt text-primary"></i>
                                        <div class="stat-value"><?= date('d/m/Y', strtotime($user['date_inscription'])) ?></div>
                                        <div class="stat-label">Date d'inscription</div>
                                    </div>
                                    
                                    <div class="stat-item">
                                        <i class="fas fa-user-tag text-primary"></i>
                                        <div class="stat-value"><?= ucfirst(htmlspecialchars($user['type'])) ?></div>
                                        <div class="stat-label">Type de compte</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Modification Profil -->
    <div class="modal fade" id="editProfileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content animate__animated animate__fadeInUp">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i>Modifier mes informations</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="update_info" value="1">
                        <div class="mb-3">
                            <label class="form-label">Nom *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" name="nom" value="<?= htmlspecialchars($user['nom']) ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Téléphone</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="tel" class="form-control" name="telephone" value="<?= htmlspecialchars($user['telephone']) ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Adresse</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                <textarea class="form-control" name="adresse" rows="3"><?= htmlspecialchars($user['adresse']) ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Changement Mot de passe -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content animate__animated animate__fadeInUp">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-key me-2"></i>Changer mon mot de passe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="update_password" value="1">
                        <div class="mb-3">
                            <label class="form-label">Ancien mot de passe *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" name="ancien_mdp" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nouveau mot de passe *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" class="form-control" name="nouveau_mdp" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirmer le nouveau mot de passe *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-check-double"></i></span>
                                <input type="password" class="form-control" name="confirm_mdp" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Changer le mot de passe
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animation des éléments lors du défilement
        document.addEventListener('DOMContentLoaded', function() {
            // Animation des cartes au survol
            const cards = document.querySelectorAll('.profile-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.classList.add('animate__pulse');
                });
                
                card.addEventListener('mouseleave', function() {
                    this.classList.remove('animate__pulse');
                });
            });
            
            // Animation des champs d'information
            const infoTexts = document.querySelectorAll('.info-text');
            infoTexts.forEach(info => {
                info.addEventListener('mouseenter', function() {
                    const icon = this.querySelector('.info-icon');
                    icon.style.transform = 'translateY(-50%) scale(1.2)';
                    icon.style.color = '#00d2ff';
                });
                
                info.addEventListener('mouseleave', function() {
                    const icon = this.querySelector('.info-icon');
                    icon.style.transform = 'translateY(-50%)';
                    icon.style.color = '#3a7bd5';
                });
            });
        });
    </script>
</body>
</html> 