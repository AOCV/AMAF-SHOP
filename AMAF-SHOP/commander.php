<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'config.php';
require_once 'includes/functions.php';

// Configuration CinetPay - u00e0 remplacer par vos clu00e9s ru00e9elles
define('CINETPAY_SITE_ID', '105894887');  // Remplacer par votre Site ID CinetPay
define('CINETPAY_API_KEY', '751137124682209027ec079.91233481');  // Remplacer par votre API Key CinetPay
define('CINETPAY_RETURN_URL', 'https://client.cinetpay.com/v1');  // URL de retour apru00e8s paiement

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
            $mode_paiement = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'livraison';
            
            // Vérifier si la colonne mode_paiement existe, sinon l'ajouter
            try {
                $check_column = "SHOW COLUMNS FROM commande LIKE 'mode_paiement'";
                $column_result = $conn->query($check_column);
                if ($column_result->num_rows == 0) {
                    $alter_table = "ALTER TABLE commande ADD COLUMN mode_paiement VARCHAR(50) DEFAULT 'livraison' AFTER informations_livraison";
                    $conn->query($alter_table);
                }
            } catch (Exception $e) {
                // Ignorer l'erreur et continuer - nous utiliserons la version simplifiée de la requête
            }
            
            // Créer la commande avec les informations de livraison
            $user_id = $_SESSION['user_id'];
            
            // Vérifier si la colonne mode_paiement existe avant de l'utiliser
            $check_column = "SHOW COLUMNS FROM commande LIKE 'mode_paiement'";
            $column_result = $conn->query($check_column);
            
            if ($column_result->num_rows > 0) {
                // La colonne existe, utilisez-la dans la requête
                $sql = "INSERT INTO commande (utilisateur_id, livreur_id, total, date_commande, statut, informations_livraison, mode_paiement) 
                        VALUES (?, ?, ?, NOW(), 'en attente', ?, ?)";
            } else {
                // La colonne n'existe pas, utilisez la requête sans cette colonne
                $sql = "INSERT INTO commande (utilisateur_id, livreur_id, total, date_commande, statut, informations_livraison) 
                        VALUES (?, ?, ?, NOW(), 'en attente', ?)";
            }

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
            
            if ($column_result->num_rows > 0) {
                // Utiliser la version avec mode_paiement
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iiiss", $user_id, $livreur_id, $total, $infos_livraison_json, $mode_paiement);
            } else {
                // Utiliser la version sans mode_paiement
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iiis", $user_id, $livreur_id, $total, $infos_livraison_json);
            }
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
            
            // Vérifier si la colonne transaction_id existe, sinon l'ajouter
            try {
                $check_column = "SHOW COLUMNS FROM commande LIKE 'transaction_id'";
                $column_result = $conn->query($check_column);
                if ($column_result->num_rows == 0) {
                    $alter_table = "ALTER TABLE commande ADD COLUMN transaction_id VARCHAR(100) NULL AFTER mode_paiement";
                    $conn->query($alter_table);
                }
            } catch (Exception $e) {
                // Ignorer l'erreur et continuer
            }
            
            // Vérifier le mode de paiement sélectionné
            if ($mode_paiement === 'en_ligne') {
                // Redirection vers CinetPay
                // Préparer les données pour CinetPay
                $transaction_id = 'CMD' . $commande_id . '_' . time();
                
                // Essayer de stocker l'ID de transaction
                try {
                    $sql = "UPDATE commande SET transaction_id = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("si", $transaction_id, $commande_id);
                    $stmt->execute();
                } catch (Exception $e) {
                    // Continuer même si ça échoue
                }
                
                // Rediriger vers la page de paiement CinetPay
                header('Location: paiement-cinetpay.php?cmd=' . $commande_id . '&transaction_id=' . $transaction_id);
                exit();
            } else {
                // Paiement à la livraison, redirection vers la page de confirmation
                header('Location: confirmation.php?commande=' . $commande_id);
                exit();
            }
            
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
            border: 1px solid #eee;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .livreur-card:hover {
            border-color: #3498db;
        }
        .livreur-card.selected {
            border-color: #2ecc71;
            background-color: #f8fff9;
        }
        .livreur-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .livreur-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }
        .rating {
            color: #f1c40f;
        }
        .order-summary {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .location-icon {
            color: #007bff;
        }

        .payment-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            background: white;
        }

        .payment-card:hover {
            border-color: #007bff;
        }

        .payment-card.selected {
            border-color: #28a745;
            background-color: #f8fff9;
        }

        .payment-radio {
            position: absolute;
            top: 10px;
            right: 10px;
        }

        .payment-icon {
            font-size: 1.5rem;
            color: #007bff;
            margin-bottom: 10px;
        }

        .payment-content {
            text-align: center;
        }

        /* Style pour le suivi de commande */
        .tracking-steps {
            display: flex;
            justify-content: space-between;
            margin: 15px 0;
            position: relative;
        }

        .tracking-steps::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 1px;
            background: #e9ecef;
            z-index: 1;
        }

        .tracking-step {
            position: relative;
            z-index: 2;
            background: white;
            padding: 0 5px;
            text-align: center;
            font-size: 0.9rem;
        }

        .step-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 5px;
            color: #6c757d;
            font-size: 0.8rem;
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
        <h4 class="mb-3">
            <i class="fas fa-shopping-bag me-2"></i>Finaliser la commande
        </h4>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <!-- Sélection du livreur -->
                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h5 class="mb-0">Choisir votre livreur</h5>
                    </div>
                    <div class="card-body py-2">
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
                                            <span class="fw-bold"><?= htmlspecialchars($livreur['nom']) ?></span>
                                            <small class="d-block text-muted">Tél: <?= htmlspecialchars($livreur['telephone']) ?></small>
                                        </div>
                                        <span class="badge bg-success ms-auto">Disponible</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Informations de livraison -->
                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h5 class="mb-0">Informations de livraison</h5>
                    </div>
                    <div class="card-body py-2">
                        <form id="orderForm" method="POST">
                            <input type="hidden" name="livreur_id" id="livreur_id">
                            <input type="hidden" name="latitude" id="latitude">
                            <input type="hidden" name="longitude" id="longitude">
                            <input type="hidden" name="payment_method" id="payment_method" value="livraison">
                            
                            <div class="mb-2">
                                <label class="form-label small mb-1">Téléphone *</label>
                                <input type="tel" class="form-control form-control-sm" name="telephone" required>
                            </div>

                            <div class="mb-2">
                                <label class="form-label small mb-1">Adresse de livraison *</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" name="adresse_livraison" id="adresse_livraison" required>
                                    <button class="btn btn-primary btn-sm" type="button" onclick="getLocation()">
                                        <i class="fas fa-location-crosshairs"></i>
                                    </button>
                                </div>
                                <small class="text-muted small">Cliquez sur l'icône pour localisation actuelle</small>
                            </div>

                            <div class="mb-2">
                                <label class="form-label small mb-1">Instructions spéciales</label>
                                <textarea class="form-control form-control-sm" name="instructions" rows="1"></textarea>
                            </div>
                        </form>
                    </div>
                </div>

            </div>

            <div class="col-md-4">
                <!-- Mode de paiement -->
                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h5 class="mb-0">Mode de paiement</h5>
                    </div>
                    <div class="card-body py-2">
                        <div class="payment-options">
                            <div class="row">
                                <div class="col-6 mb-2">
                                    <div class="payment-card" onclick="selectPaymentMethod(this, 'livraison')">
                                        <input type="radio" name="payment_method" value="livraison" class="payment-radio" required>
                                        <div class="payment-content">
                                            <i class="fas fa-truck-fast payment-icon"></i>
                                            <h6>Paiement à la livraison</h6>
                                            <small class="text-muted">Espèces</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 mb-2">
                                    <div class="payment-card" onclick="selectPaymentMethod(this, 'en_ligne')">
                                        <input type="radio" name="payment_method" value="en_ligne" class="payment-radio" required>
                                        <div class="payment-content">
                                            <i class="fas fa-credit-card payment-icon"></i>
                                            <h6>Paiement en ligne</h6>
                                            <small class="text-muted">Carte bancaire</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Résumé de la commande -->
                <div class="order-summary">
                    <h5 class="mb-3">Résumé de la commande</h5>
                    
                    <!-- Liste des produits -->
                    <div class="mb-3">
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
        
        // Debug - afficher la valeur sélectionnée dans la console
        console.log('Mode de paiement sélectionné: ' + method);
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