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
$query_produits = "SELECT cp.*, p.nom as produit_nom, p.image_url 
                  FROM commande_produit cp 
                  LEFT JOIN produit p ON cp.produit_id = p.id 
                  WHERE cp.commande_id = ?";

$stmt_produits = $conn->prepare($query_produits);
$stmt_produits->bind_param("i", $commande_id);
$stmt_produits->execute();
$result_produits = $stmt_produits->get_result();

// Fonction pour formater le statut
function getStatusBadge($status) {
    switch(strtolower($status)) {
        case 'en attente': return '<span class="status pending">En attente</span>';
        case 'confirmé': return '<span class="status confirmed">Confirmé</span>';
        case 'en livraison': return '<span class="status shipping">En livraison</span>';
        case 'livré': return '<span class="status delivered">Livré</span>';
        case 'annulé': return '<span class="status cancelled">Annulé</span>';
        default: return '<span class="status">' . ucfirst($status) . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de la commande #<?= $commande_id ?> - Impression</title>
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
        
        .print-container {
            background-color: #fff;
            max-width: 210mm;
            margin: 0 auto;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .company-info {
            text-align: left;
        }
        
        .company-info h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .order-info {
            text-align: right;
        }
        
        .order-info h2 {
            font-size: 20px;
            color: #4a6fdc;
            margin-bottom: 5px;
        }
        
        .print-section {
            margin-bottom: 30px;
        }
        
        .print-section h3 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #333;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-block {
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        
        .info-block p {
            margin-bottom: 8px;
        }
        
        .info-block strong {
            font-weight: bold;
            display: inline-block;
            min-width: 120px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        table th {
            background-color: #f0f4f8;
            text-align: left;
            padding: 10px;
            font-weight: bold;
            border-bottom: 2px solid #ddd;
        }
        
        table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        table tr:last-child td {
            border-bottom: none;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status.pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status.confirmed {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .status.shipping {
            background-color: #cfe2ff;
            color: #084298;
        }
        
        .status.delivered {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status.cancelled {
            background-color: #f8d7da;
            color: #721c24;
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
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 3px;
        }
        
        .product-image-placeholder {
            width: 50px;
            height: 50px;
            background-color: #f0f0f0;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 3px;
            color: #aaa;
        }
    </style>
</head>
<body>
    <div class="print-container">
        <div class="print-header">
            <div class="company-info">
                <h1>AMAF-SHOP</h1>
                <p>Commerce en ligne de produits de qualité</p>
                <p>Abidjan, Côte d'Ivoire</p>
            </div>
            <div class="order-info">
                <h2>Détails de commande #<?= $commande['id'] ?></h2>
                <p>Date: <?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?></p>
                <p>Statut: <?= getStatusBadge($commande['statut']) ?></p>
            </div>
        </div>
        
        <div class="print-section">
            <div class="info-grid">
                <div class="info-block">
                    <h3><i class="fas fa-user"></i> Informations client</h3>
                    <p><strong>Nom:</strong> <?= htmlspecialchars($commande['client_nom']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($commande['client_email']) ?></p>
                    <?php if ($commande['telephone']): ?>
                        <p><strong>Téléphone:</strong> <?= htmlspecialchars($commande['telephone']) ?></p>
                    <?php endif; ?>
                    <?php if ($commande['adresse']): ?>
                        <p><strong>Adresse:</strong> <?= htmlspecialchars($commande['adresse']) ?></p>
                    <?php endif; ?>
                </div>
                
                <?php if ($commande['livreur_id']): ?>
                <div class="info-block">
                    <h3><i class="fas fa-truck"></i> Informations livreur</h3>
                    <p><strong>Nom:</strong> <?= htmlspecialchars($commande['livreur_username']) ?></p>
                    <?php if ($commande['livreur_tel']): ?>
                        <p><strong>Téléphone:</strong> <?= htmlspecialchars($commande['livreur_tel']) ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($commande['informations_livraison']): ?>
                <div class="info-block">
                    <h3><i class="fas fa-map-marker-alt"></i> Informations de livraison</h3>
                    <?php 
                        $info_livraison = $commande['informations_livraison'];
                        if (is_string($info_livraison)) {
                            $info_livraison = json_decode($info_livraison, true);
                        }
                        
                        if (is_array($info_livraison)):
                    ?>
                        <?php foreach ($info_livraison as $key => $value): ?>
                            <p>
                                <strong><?= ucfirst(str_replace('_', ' ', $key)) ?>:</strong> 
                                <?php if (is_array($value)): ?>
                                    <?php if (isset($value['lat']) && isset($value['lng'])): ?>
                                        <?= $value['lat'] ?>, <?= $value['lng'] ?>
                                    <?php else: ?>
                                        <?= json_encode($value) ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?= htmlspecialchars($value) ?>
                                <?php endif; ?>
                            </p>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p><?= htmlspecialchars((string)$commande['informations_livraison']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="print-section">
            <h3><i class="fas fa-shopping-basket"></i> Produits commandés</h3>
            <?php if ($result_produits->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th width="60">Image</th>
                            <th>Produit</th>
                            <th class="text-center">Quantité</th>
                            <th class="text-right">Prix unitaire</th>
                            <th class="text-right">Total</th>
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
                                <td><?= htmlspecialchars($produit['produit_nom']) ?></td>
                                <td class="text-center"><?= $produit['quantite'] ?></td>
                                <td class="text-right"><?= number_format($produit['prix_unitaire'], 0) ?> CFA</td>
                                <td class="text-right"><?= number_format($sous_total, 0) ?> CFA</td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-right"><strong>Total:</strong></td>
                            <td class="text-right"><strong><?= number_format($total, 0) ?> CFA</strong></td>
                        </tr>
                    </tfoot>
                </table>
            <?php else: ?>
                <p>Aucun produit trouvé pour cette commande.</p>
            <?php endif; ?>
        </div>
        
        <?php if ($commande['commentaire_livraison']): ?>
            <div class="print-section">
                <h3><i class="fas fa-comment"></i> Commentaire de livraison</h3>
                <div class="info-block">
                    <p><?= nl2br(htmlspecialchars($commande['commentaire_livraison'])) ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>Ce document a été généré le <?= date('d/m/Y à H:i:s') ?> sur AMAF-SHOP.</p>
            <p>Merci de votre confiance.</p>
        </div>
    </div>
    
    <button class="print-btn no-print" onclick="window.print()"><i class="fas fa-print"></i> Imprimer</button>
    
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