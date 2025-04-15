<?php
session_start();
require '../config.php';

// Vérifier si l'utilisateur est connecté et est un admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "ID de commande invalide";
    exit();
}

$commande_id = intval($_GET['id']);

// Récupérer les détails de la commande
$query = "SELECT c.*, u.nom as client_nom, u.email as client_email, u.telephone, u.adresse,
          l.nom as livreur_nom, l.utilisateur as livreur_username, l.telephone as livreur_tel
          FROM commande c 
          LEFT JOIN utilisateur u ON c.utilisateur_id = u.id
          LEFT JOIN utilisateur l ON c.livreur_id = l.id
          WHERE c.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $commande_id);
$stmt->execute();
$result = $stmt->get_result();
$commande = $result->fetch_assoc();

if (!$commande) {
    echo "Commande non trouvée";
    exit();
}

// Récupérer les produits de la commande
$query_produits = "SELECT cp.*, p.nom as produit_nom, p.image_url, p.description
                  FROM commande_produit cp 
                  LEFT JOIN produit p ON cp.produit_id = p.id 
                  WHERE cp.commande_id = ?";

$stmt_produits = $conn->prepare($query_produits);
$stmt_produits->bind_param("i", $commande_id);
$stmt_produits->execute();
$result_produits = $stmt_produits->get_result();

// Générer un numéro de facture (par exemple, INV-ANNÉE-MOIS-ID)
$invoice_number = 'FAC-' . date('Ym', strtotime($commande['date_commande'])) . '-' . $commande['id'];

// Calculer la date d'échéance (par exemple, 15 jours après la date de commande)
$due_date = date('d/m/Y', strtotime($commande['date_commande'] . ' + 15 days'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture #<?= $invoice_number ?> - AMAF-SHOP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @media print {
            @page {
                size: A4;
                margin: 10mm;
            }
            body {
                background-color: #fff !important;
                color: #000 !important;
            }
            .no-print {
                display: none !important;
            }
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        
        body {
            background-color: #f8f9fa;
            color: #333;
            padding: 20px;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .invoice-container {
            background-color: #fff;
            max-width: 210mm;
            margin: 0 auto;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }
        
        .company-info {
            text-align: left;
        }
        
        .company-info h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .invoice-info {
            text-align: right;
        }
        
        .invoice-info h2 {
            font-size: 28px;
            color: #4a6fdc;
            margin-bottom: 10px;
        }
        
        .invoice-details {
            margin-bottom: 10px;
            color: #555;
        }
        
        .invoice-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #4a6fdc;
        }
        
        .invoice-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .client-info, .payment-info {
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        
        .client-info h3, .payment-info h3 {
            font-size: 16px;
            margin-bottom: 15px;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }
        
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .invoice-table th {
            background-color: #4a6fdc;
            color: white;
            text-align: left;
            padding: 12px;
            font-weight: bold;
        }
        
        .invoice-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        
        .invoice-table tr:last-child td {
            border-bottom: none;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .totals {
            width: 100%;
            max-width: 350px;
            margin-left: auto;
            margin-top: 20px;
        }
        
        .totals table {
            width: 100%;
        }
        
        .totals td {
            padding: 8px 12px;
        }
        
        .totals tr.grand-total {
            background-color: #f0f4f8;
            font-weight: bold;
            font-size: 16px;
        }
        
        .totals tr.grand-total td {
            padding: 15px 12px;
            color: #4a6fdc;
        }
        
        .notes {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .notes h3 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #333;
        }
        
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #777;
        }
        
        .print-btn {
            background-color: #4a6fdc;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            border-radius: 5px;
            display: block;
            margin: 20px auto;
            transition: background-color 0.3s;
        }
        
        .print-btn:hover {
            background-color: #3a5bb8;
        }
        
        .product-image {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 3px;
        }
        
        .product-title {
            font-weight: bold;
        }
        
        .product-description {
            font-size: 12px;
            color: #777;
            margin-top: 3px;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="invoice-header">
            <div class="company-info">
                <h1>AMAF-SHOP</h1>
                <p>Commerce en ligne de produits de qualité</p>
                <p>Abidjan, Côte d'Ivoire</p>
                <p>contact@amafshop.com</p>
            </div>
            <div class="invoice-info">
                <h2>FACTURE</h2>
                <div class="invoice-details">
                    <p><strong>Numéro:</strong> <?= $invoice_number ?></p>
                    <p><strong>Date:</strong> <?= date('d/m/Y', strtotime($commande['date_commande'])) ?></p>
                    <p><strong>Échéance:</strong> <?= $due_date ?></p>
                </div>
            </div>
        </div>
        
        <div class="invoice-meta">
            <div class="client-info">
                <h3><i class="fas fa-user"></i> Client</h3>
                <p><strong><?= htmlspecialchars($commande['client_nom']) ?></strong></p>
                <p><?= htmlspecialchars($commande['client_email']) ?></p>
                <?php if ($commande['telephone']): ?>
                    <p><?= htmlspecialchars($commande['telephone']) ?></p>
                <?php endif; ?>
                <?php if ($commande['adresse']): ?>
                    <p><?= htmlspecialchars($commande['adresse']) ?></p>
                <?php endif; ?>
            </div>
            
            <div class="payment-info">
                <h3><i class="fas fa-money-check-alt"></i> Modalités de paiement</h3>
                <p><strong>Méthode de paiement:</strong> À la livraison</p>
                <p><strong>Statut de la commande:</strong> <?= ucfirst($commande['statut']) ?></p>
                <p><strong>Référence de commande:</strong> #<?= $commande['id'] ?></p>
            </div>
        </div>
        
        <table class="invoice-table">
            <thead>
                <tr>
                    <th width="50">Image</th>
                    <th>Produit</th>
                    <th width="80">Quantité</th>
                    <th width="120">Prix unitaire</th>
                    <th width="120">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                    $total = 0;
                    while ($produit = $result_produits->fetch_assoc()):
                        $sous_total = $produit['quantite'] * $produit['prix_unitaire'];
                        $total += $sous_total;
                ?>
                    <tr>
                        <td>
                            <?php if ($produit['image_url']): ?>
                                <img src="../<?= htmlspecialchars($produit['image_url']) ?>" 
                                     alt="<?= htmlspecialchars($produit['produit_nom']) ?>" 
                                     class="product-image">
                            <?php else: ?>
                                <div class="product-image-placeholder">
                                    <i class="fas fa-image"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="product-title"><?= htmlspecialchars($produit['produit_nom']) ?></div>
                            <?php if ($produit['description']): ?>
                                <div class="product-description"><?= htmlspecialchars(substr($produit['description'], 0, 100)) ?><?= strlen($produit['description']) > 100 ? '...' : '' ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= $produit['quantite'] ?></td>
                        <td class="text-right"><?= number_format($produit['prix_unitaire'], 0) ?> CFA</td>
                        <td class="text-right"><?= number_format($sous_total, 0) ?> CFA</td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <div class="totals">
            <table>
                <tr>
                    <td>Sous-total :</td>
                    <td class="text-right"><?= number_format($total, 0) ?> CFA</td>
                </tr>
                <tr>
                    <td>Frais de livraison :</td>
                    <td class="text-right">0 CFA</td>
                </tr>
                <tr>
                    <td>Taxes :</td>
                    <td class="text-right">0 CFA</td>
                </tr>
                <tr class="grand-total">
                    <td>Total :</td>
                    <td class="text-right"><?= number_format($total, 0) ?> CFA</td>
                </tr>
            </table>
        </div>
        
        <div class="notes">
            <h3>Notes</h3>
            <p>Merci pour votre achat ! Pour toute question concernant cette facture, veuillez contacter notre service client à l'adresse support@amafshop.com.</p>
            <?php if ($commande['commentaire_livraison']): ?>
                <p><strong>Commentaire de livraison:</strong> <?= htmlspecialchars($commande['commentaire_livraison']) ?></p>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <p>AMAF-SHOP - Tous droits réservés</p>
            <p>Facture générée le <?= date('d/m/Y à H:i:s') ?></p>
        </div>
    </div>
    
    <button class="print-btn no-print" onclick="window.print()"><i class="fas fa-print"></i> Imprimer la facture</button>
    
    <script>
        // Exécuter automatiquement l'impression au chargement
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html> 