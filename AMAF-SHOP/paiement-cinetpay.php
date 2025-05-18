<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'config.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Vous devez être connecté pour effectuer un paiement.";
    header('Location: login.php');
    exit();
}

// Vérifier si l'ID de commande est fourni
if (!isset($_GET['cmd']) || !isset($_GET['transaction_id'])) {
    $_SESSION['error'] = "Informations de paiement incomplètes.";
    header('Location: profile.php');
    exit();
}

$commande_id = intval($_GET['cmd']);
$transaction_id = $_GET['transaction_id'];

// Récupérer les informations de la commande
$query = "SELECT c.*, u.nom as nom_client, u.email 
          FROM commande c 
          JOIN utilisateur u ON c.utilisateur_id = u.id 
          WHERE c.id = ? AND c.transaction_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("is", $commande_id, $transaction_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Commande non trouvée ou transaction invalide.";
    header('Location: profile.php');
    exit();
}

$commande = $result->fetch_assoc();

// Vérifier que la commande appartient bien à l'utilisateur connecté
if ($commande['utilisateur_id'] != $_SESSION['user_id']) {
    $_SESSION['error'] = "Vous n'êtes pas autorisé à accéder à cette commande.";
    header('Location: profile.php');
    exit();
}

// Configuration CinetPay
$site_id = 105894887;
$api_key = '751137124682209027ec079.91233481';
$notify_url = 'http://localhost/AMAF-SHOP-main/AMAF-SHOP/confirmation.php'; // À adapter à votre environnement
$return_url = 'http://localhost/AMAF-SHOP-main/AMAF-SHOP/confirmation.php?commande=' . $commande_id;

// Formatage du montant (CinetPay attend le montant en centimes sans virgule)
$amount = $commande['total'];
$currency = 'XOF'; // Franc CFA
$description = "Paiement de la commande #" . $commande_id;

// Générer un identifiant de transaction unique
$transaction_id = 'CMD' . $commande_id . '_' . time();

// Données d'intégration CinetPay
$cinetpay_data = [
    'transaction_id' => $transaction_id,
    'amount' => $amount,
    'currency' => $currency,
    'channels' => 'ALL',
    'description' => $description,
    'customer_name' => $commande['nom_client'],
    'customer_email' => $commande['email'],
    'customer_phone_number' => json_decode($commande['informations_livraison'], true)['telephone'],
    'customer_address' => json_decode($commande['informations_livraison'], true)['adresse'],
    'customer_city' => '',
    'customer_country' => 'CI', // Côte d'Ivoire par défaut, à modifier selon vos besoins
    'customer_state' => '',
    'customer_zip_code' => '',
    'notify_url' => $return_url,
    'return_url' => $return_url,
    'metadata' => json_encode(['commande_id' => $commande_id])
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement - AMAF-SHOP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Script CinetPay Seamless -->
    <script src="https://cdn.cinetpay.com/seamless/main.js"></script>
    <style>
        .payment-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .payment-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .payment-icon {
            font-size: 2rem;
            color: #007bff;
            margin-bottom: 10px;
        }
        .payment-details {
            margin-bottom: 20px;
        }
        .payment-button {
            text-align: center;
        }
        /* Style pour le SDK centré */
        .sdk-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-top: 20px;
        }
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <div class="payment-container">
            <div class="payment-header">
                <i class="fas fa-credit-card payment-icon"></i>
                <h4>Paiement de votre commande</h4>
                <p class="text-muted">Effectuez votre paiement en toute sécurité via CinetPay</p>
            </div>

            <div class="payment-details">
                <div class="row mb-2">
                    <div class="col-6 text-muted">Numéro de commande:</div>
                    <div class="col-6 text-end fw-bold">#<?php echo $commande_id; ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-6 text-muted">Montant:</div>
                    <div class="col-6 text-end fw-bold"><?php echo number_format($amount); ?> <?php echo $currency; ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-6 text-muted">Date:</div>
                    <div class="col-6 text-end"><?php echo date('d/m/Y H:i', strtotime($commande['date_commande'])); ?></div>
                </div>
                <hr>
                <div class="alert alert-info small">
                    <i class="fas fa-info-circle me-2"></i>
                    Après votre paiement, vous serez automatiquement redirigé vers notre site.
                </div>
            </div>

            <div class="sdk-container">
                <button onclick="checkout()" class="btn btn-primary btn-lg mb-3">
                    <i class="fas fa-lock me-2"></i>Procéder au paiement sécurisé
                </button>
            </div>
        </div>
    </div>

    <script>
    function checkout() {
        // Initialiser le paiement CinetPay avec le SDK Seamless
        CinetPay.setConfig({
            apikey: '<?php echo $api_key; ?>',
            site_id: <?php echo $site_id; ?>,
            notify_url: '<?php echo $notify_url; ?>',
            return_url: '<?php echo $return_url; ?>',
            mode: 'PRODUCTION'
        });
        
        CinetPay.getCheckout({
            transaction_id: '<?php echo $transaction_id; ?>', // ID de transaction unique
            amount: <?php echo $amount; ?>,
            currency: '<?php echo $currency; ?>',
            channels: 'ALL',
            description: '<?php echo $description; ?>',
            // Informations client
            customer_name: '<?php echo $_SESSION["user_name"]; ?>',
            customer_surname: '',
            customer_email: '<?php echo $commande["email"]; ?>',
            customer_phone_number: '<?php echo json_decode($commande["informations_livraison"], true)["telephone"]; ?>',
            customer_address: '<?php echo addslashes(json_decode($commande["informations_livraison"], true)["adresse"]); ?>',
            customer_city: '',
            customer_country: 'CI', // Code ISO pour la Côte d'Ivoire
            customer_state: '',
            customer_zip_code: ''
        });
        
        CinetPay.waitResponse(function(data) {
            if (data.status == "REFUSED") {
                alert("Votre paiement a échoué");
                window.location.href = "confirmation.php?commande=<?php echo $commande_id; ?>&status=failed";
            } else if (data.status == "ACCEPTED") {
                alert("Votre paiement a été effectué avec succès");
                window.location.href = "confirmation.php?commande=<?php echo $commande_id; ?>&status=success";
            }
        });
        
        CinetPay.onError(function(data) {
            console.log(data);
            alert("Une erreur est survenue lors de l'initialisation du paiement.");
        });
    }
    
    // Démarrer automatiquement le processus de paiement après 1 seconde
    setTimeout(function() {
        checkout();
    }, 1000);
    </script>
</body>
</html>
