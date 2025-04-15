<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'config.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Vous devez être connecté pour passer une commande.";
    header('Location: login.php');
    exit();
}

// Vérifier si le panier n'est pas vide
if (empty($_SESSION['panier'])) {
    $_SESSION['error'] = "Votre panier est vide";
    header('Location: panier.php');
    exit();
}

$error = '';
$total = 0;
$produits_commande = [];

// Récupérer les produits du panier
foreach ($_SESSION['panier'] as $id_produit => $quantite) {
    $query = "SELECT * FROM produit WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id_produit);
    $stmt->execute();
    $result = $stmt->get_result();
    $produit = $result->fetch_assoc();
    
    if ($produit) {
        // Calculer le prix avec la promotion
        $prix_final = $produit['prix'];
        if (!empty($produit['promotion'])) {
            $prix_final = $prix_final - ($produit['promotion']);
        }
        
        $sous_total = $prix_final * $quantite['quantite'];
        $total += $sous_total;
        
        $produit['quantite'] = $quantite['quantite'];
        $produit['taille'] = $quantite['taille'];
        $produit['prix_final'] = $prix_final;
        $produit['sous_total'] = $sous_total;
        $produits_commande[] = $produit;
    }
}

// Récupérer les livreurs disponibles
$query_livreurs = "SELECT id, nom, telephone, type 
                   FROM utilisateur 
                   WHERE type = 'livreur'";
$result_livreurs = $conn->query($query_livreurs);
$livreurs_disponibles = [];
while ($livreur = $result_livreurs->fetch_assoc()) {
    $livreurs_disponibles[] = $livreur;
}

// Traitement de la commande
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($produits_commande)) {
            throw new Exception("Votre panier est vide.");
        }

        $conn->begin_transaction();
        
        try {
            // Récupérer les données du formulaire
            $livreur_id = $_POST['livreur_id'];
            $adresse_livraison = $_POST['adresse_livraison'];
            $instructions = $_POST['instructions'];
            $telephone = $_POST['telephone'];
            
            // Créer la commande avec les informations de livraison
            $user_id = $_SESSION['user_id'];
            $sql = "INSERT INTO commande (utilisateur_id, livreur_id, total, date_commande, statut, informations_livraison) 
                    VALUES (?, ?, ?, NOW(), 'en attente', ?)";

            // Créer le tableau d'informations de livraison
            $infos_livraison = [
                'nom' => $_SESSION['user_name'],
                'prenom' => '',
                'adresse' => $adresse_livraison,
                'telephone' => $telephone,
                'instructions' => $instructions,
                'coordinates' => [
                    'lat' => $_POST['latitude'],
                    'lng' => $_POST['longitude']
                ]
            ];

            $infos_livraison_json = json_encode($infos_livraison);
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiis", $user_id, $livreur_id, $total, $infos_livraison_json);
            $stmt->execute();
            $commande_id = $conn->insert_id;

            // Ajouter les détails de la commande
            foreach ($produits_commande as $produit) {
                // Vérifier le stock une dernière fois
                $sql = "SELECT stock FROM produit WHERE id = ? FOR UPDATE";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $produit['id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $stock_data = $result->fetch_assoc();
                
                if (!$stock_data) {
                    throw new Exception("Le produit n'existe plus : " . $produit['nom']);
                }
                
                if ($stock_data['stock'] < $produit['quantite']) {
                    throw new Exception("Stock insuffisant pour le produit : " . $produit['nom']);
                }
                
                // Insérer le détail de la commande dans commande_produit
                $sql = "INSERT INTO commande_produit (commande_id, produit_id, quantite, taille, prix_unitaire) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iiisi", $commande_id, $produit['id'], $produit['quantite'], $produit['taille'], $produit['prix_final']);
                $stmt->execute();
                
                // Mettre à jour le stock
                $sql = "UPDATE produit SET stock = stock - ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $produit['quantite'], $produit['id']);
                $stmt->execute();
            }
            
            // Valider la transaction
            $conn->commit();
            
            // Vider le panier
            $_SESSION['panier'] = [];
            $_SESSION['success'] = "Commande validée avec succès !";
            header('Location: confirmation.php?commande=' . $commande_id);
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finaliser la commande - AMAF-SHOP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .price-original {
            text-decoration: line-through;
            color: #6c757d;
            font-size: 0.9em;
        }
        .price-promotion {
            color: #dc3545;
            font-weight: bold;
        }
        .livreur-card {
            border: 2px solid #eee;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .livreur-card:hover {
            border-color: #3498db;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .livreur-card.selected {
            border-color: #2ecc71;
            background-color: #f8fff9;
        }
        .livreur-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .livreur-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .rating {
            color: #f1c40f;
        }
        .order-summary {
            background-color: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .location-icon {
            color: #007bff;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        .payment-card {
            border: 2px solid #eee;
            border-radius: 15px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            background: white;
        }

        .payment-card:hover {
            border-color: #007bff;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .payment-card.selected {
            border-color: #28a745;
            background-color: #f8fff9;
        }

        .payment-radio {
            position: absolute;
            top: 20px;
            right: 20px;
            transform: scale(1.2);
        }

        .payment-icon {
            font-size: 2rem;
            color: #007bff;
            margin-bottom: 15px;
        }

        .payment-content {
            text-align: center;
        }

        /* Style pour le suivi de commande */
        .tracking-steps {
            display: flex;
            justify-content: space-between;
            margin: 30px 0;
            position: relative;
        }

        .tracking-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e9ecef;
            z-index: 1;
        }

        .tracking-step {
            position: relative;
            z-index: 2;
            background: white;
            padding: 0 15px;
            text-align: center;
        }

        .step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            color: #6c757d;
            transition: all 0.3s ease;
        }

        .step-active .step-icon {
            background: #28a745;
            color: white;
        }

        .step-completed .step-icon {
            background: #007bff;
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <h2 class="mb-4">
            <i class="fas fa-shopping-bag me-2"></i>Finaliser la commande
        </h2>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <!-- Sélection du livreur -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="mb-0">Choisir votre livreur</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($livreurs_disponibles)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Aucun livreur n'est disponible pour le moment.
                            </div>
                        <?php else: ?>
                            <?php foreach ($livreurs_disponibles as $livreur): ?>
                                <div class="livreur-card" onclick="selectLivreur(this, <?= $livreur['id'] ?>)">
                                    <div class="livreur-info">
                                        <div class="livreur-avatar">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-1"><?= htmlspecialchars($livreur['nom']) ?></h5>
                                            <small class="text-muted">
                                                Tél: <?= htmlspecialchars($livreur['telephone']) ?>
                                            </small>
                                        </div>
                                        <div class="ms-auto">
                                            <span class="badge bg-success">Disponible</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Informations de livraison -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="mb-0">Informations de livraison</h4>
                    </div>
                    <div class="card-body">
                        <form id="orderForm" method="POST">
                            <input type="hidden" name="livreur_id" id="livreur_id">
                            <input type="hidden" name="latitude" id="latitude">
                            <input type="hidden" name="longitude" id="longitude">
                            
                            <div class="mb-3">
                                <label class="form-label">Téléphone *</label>
                                <input type="tel" class="form-control" name="telephone" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Adresse de livraison *</label>
                                <div class="input-group">
                                    <input type="text" 
                                           class="form-control" 
                                           name="adresse_livraison" 
                                           id="adresse_livraison" 
                                           required>
                                    <button class="btn btn-primary" 
                                            type="button" 
                                            onclick="getLocation()">
                                        <i class="fas fa-location-crosshairs"></i>
                                    </button>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    Cliquez sur l'icône pour utiliser votre position actuelle
                                </small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Instructions spéciales</label>
                                <textarea class="form-control" name="instructions" rows="2"></textarea>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Mode de paiement -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="mb-0">Mode de paiement</h4>
                    </div>
                    <div class="card-body">
                        <div class="payment-options">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="payment-card" onclick="selectPaymentMethod(this, 'livraison')">
                                        <input type="radio" name="payment_method" value="livraison" class="payment-radio" required>
                                        <div class="payment-content">
                                            <i class="fas fa-truck-fast payment-icon"></i>
                                            <h5>Paiement à la livraison</h5>
                                            <p class="text-muted mb-0">Payez en espèces à la réception</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="payment-card" onclick="selectPaymentMethod(this, 'en_ligne')">
                                        <input type="radio" name="payment_method" value="en_ligne" class="payment-radio" required>
                                        <div class="payment-content">
                                            <i class="fas fa-credit-card payment-icon"></i>
                                            <h5>Paiement en ligne</h5>
                                            <p class="text-muted mb-0">Payez maintenant par carte bancaire</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Résumé de la commande -->
                <div class="order-summary">
                    <h4 class="mb-4">Résumé de la commande</h4>
                    
                    <!-- Liste des produits -->
                    <div class="mb-4">
                        <?php foreach ($produits_commande as $produit): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span><?= htmlspecialchars($produit['nom']) ?> x <?= $produit['quantite'] ?></span>
                                <span><?= number_format($produit['sous_total']) ?> CFA</span>
                            </div>
                        <?php endforeach; ?>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <strong>Total</strong>
                            <strong><?= number_format($total) ?> CFA</strong>
                        </div>
                    </div>

                    <button type="submit" form="orderForm" class="btn btn-primary btn-lg w-100" id="submitOrder" disabled>
                        <i class="fas fa-check me-2"></i>Confirmer la commande
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    function getLocation() {
        if (navigator.geolocation) {
            const locationButton = document.querySelector('.btn-primary i');
            locationButton.className = 'fas fa-spinner fa-spin'; // Afficher un spinner pendant le chargement

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    // Sauvegarder les coordonnées
                    document.getElementById('latitude').value = lat;
                    document.getElementById('longitude').value = lng;
                    
                    // Utiliser l'API de géocodage inverse pour obtenir l'adresse
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                        .then(response => response.json())
                        .then(data => {
                            document.getElementById('adresse_livraison').value = data.display_name;
                            locationButton.className = 'fas fa-location-crosshairs';
                        })
                        .catch(error => {
                            console.error('Erreur:', error);
                            locationButton.className = 'fas fa-location-crosshairs';
                            alert('Erreur lors de la récupération de l\'adresse');
                        });
                },
                function(error) {
                    locationButton.className = 'fas fa-location-crosshairs';
                    let message = "";
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            message = "Vous devez autoriser la géolocalisation.";
                            break;
                        case error.POSITION_UNAVAILABLE:
                            message = "Information de localisation indisponible.";
                            break;
                        case error.TIMEOUT:
                            message = "Délai d'attente de localisation dépassé.";
                            break;
                        default:
                            message = "Une erreur inconnue est survenue.";
                    }
                    alert(message);
                }
            );
        } else {
            alert("La géolocalisation n'est pas supportée par votre navigateur.");
        }
    }

    function selectLivreur(element, livreurId) {
        document.querySelectorAll('.livreur-card').forEach(card => {
            card.classList.remove('selected');
        });
        element.classList.add('selected');
        document.getElementById('livreur_id').value = livreurId;
        document.getElementById('submitOrder').disabled = false;
    }

    function selectPaymentMethod(element, method) {
        // Retirer la sélection précédente
        document.querySelectorAll('.payment-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Sélectionner la nouvelle méthode
        element.classList.add('selected');
        element.querySelector('input[type="radio"]').checked = true;
        
        // Mettre à jour le formulaire
        document.getElementById('payment_method').value = method;
    }

    // Fonction pour le suivi de commande (à ajouter dans tracking.php)
    function updateTrackingStatus(status) {
        const steps = ['en_attente', 'confirme', 'en_preparation', 'en_livraison', 'livre'];
        const currentStep = steps.indexOf(status);
        
        steps.forEach((step, index) => {
            const stepElement = document.getElementById(`step-${step}`);
            if (index < currentStep) {
                stepElement.classList.add('step-completed');
            } else if (index === currentStep) {
                stepElement.classList.add('step-active');
            }
        });
    }
    </script>
</body>
</html>