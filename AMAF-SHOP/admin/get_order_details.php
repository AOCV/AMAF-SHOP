<?php
session_start();
require '../config.php';

// Vérifier si l'utilisateur est connecté et est un admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    echo '<div class="alert alert-danger">Accès non autorisé</div>';
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger">ID de commande invalide</div>';
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
    echo '<div class="alert alert-danger">Commande non trouvée</div>';
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
    $color = '';
    $icon = '';
    
    switch(strtolower($status)) {
        case 'en attente': 
            $color = 'warning';
            $icon = 'clock';
            break;
        case 'confirmé': 
            $color = 'info';
            $icon = 'check-circle';
            break;
        case 'en livraison': 
            $color = 'primary';
            $icon = 'truck';
            break;
        case 'livré': 
            $color = 'success';
            $icon = 'check-double';
            break;
        case 'annulé': 
            $color = 'danger';
            $icon = 'times-circle';
            break;
        default:
            $color = 'secondary';
            $icon = 'question-circle';
    }
    
    return '<span class="badge bg-'.$color.'"><i class="fas fa-'.$icon.' me-1"></i>'.ucfirst($status).'</span>';
}

// Afficher les détails
?>

<div class="order-details">
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header bg-dark text-white">
                    <i class="fas fa-user me-2"></i>Informations client
                </div>
                <div class="card-body">
                    <p><strong>Nom:</strong> <?= htmlspecialchars($commande['client_nom']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($commande['client_email']) ?></p>
                    <?php if ($commande['telephone']): ?>
                        <p><strong>Téléphone:</strong> <?= htmlspecialchars($commande['telephone']) ?></p>
                    <?php endif; ?>
                    <?php if ($commande['adresse']): ?>
                        <p><strong>Adresse:</strong> <?= htmlspecialchars($commande['adresse']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header bg-dark text-white">
                    <i class="fas fa-info-circle me-2"></i>Détails de la commande
                </div>
                <div class="card-body">
                    <p><strong>Commande #:</strong> <?= $commande['id'] ?></p>
                    <p><strong>Date:</strong> <?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?></p>
                    <p><strong>Statut:</strong> <?= getStatusBadge($commande['statut']) ?></p>
                    <p><strong>Total:</strong> <span class="fw-bold text-primary"><?= number_format($commande['total'], 0) ?> CFA</span></p>
                    <?php if ($commande['livreur_id']): ?>
                        <p><strong>Livreur:</strong> <?= htmlspecialchars($commande['livreur_username']) ?></p>
                        <?php if ($commande['livreur_tel']): ?>
                            <p><strong>Tél. livreur:</strong> <?= htmlspecialchars($commande['livreur_tel']) ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($commande['informations_livraison']): ?>
        <div class="card mb-3">
            <div class="card-header bg-dark text-white">
                <i class="fas fa-truck me-2"></i>Informations de livraison
            </div>
            <div class="card-body">
                <?php 
                    // Vérifier si les informations sont déjà un tableau ou une chaîne JSON
                    $info_livraison = $commande['informations_livraison'];
                    if (is_string($info_livraison)) {
                        $info_livraison = json_decode($info_livraison, true);
                    }
                    
                    if (is_array($info_livraison)):
                ?>
                    <div class="row">
                        <?php foreach ($info_livraison as $key => $value): ?>
                            <div class="col-md-6">
                                <p>
                                    <strong><?= ucfirst(str_replace('_', ' ', $key)) ?>:</strong> 
                                    <?php if (is_array($value)): ?>
                                        <?php if (isset($value['lat']) && isset($value['lng'])): ?>
                                            <a href="https://maps.google.com/?q=<?= $value['lat'] ?>,<?= $value['lng'] ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-map-marker-alt me-1"></i>Voir sur la carte
                                            </a>
                                        <?php else: ?>
                                            <?= json_encode($value) ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?= htmlspecialchars($value) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p><?= htmlspecialchars((string)$commande['informations_livraison']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="card mb-3">
        <div class="card-header bg-dark text-white">
            <i class="fas fa-shopping-basket me-2"></i>Produits commandés
        </div>
        <div class="card-body">
            <?php if ($result_produits->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="70">Image</th>
                                <th>Produit</th>
                                <th class="text-center">Quantité</th>
                                <th class="text-end">Prix unitaire</th>
                                <th class="text-end">Total</th>
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
                                            <img src="../<?= htmlspecialchars($produit['image_url']) ?>" alt="<?= htmlspecialchars($produit['produit_nom']) ?>" class="img-thumbnail" width="60">
                                        <?php else: ?>
                                            <div class="bg-light text-center" style="width: 60px; height: 60px; line-height: 60px;">
                                                <i class="fas fa-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($produit['produit_nom']) ?></td>
                                    <td class="text-center fw-bold"><?= $produit['quantite'] ?></td>
                                    <td class="text-end"><?= number_format($produit['prix_unitaire'], 0) ?> CFA</td>
                                    <td class="text-end fw-bold"><?= number_format($sous_total, 0) ?> CFA</td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-end fw-bold">Total:</td>
                                <td class="text-end fw-bold fs-5 text-primary"><?= number_format($total, 0) ?> CFA</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-circle me-2"></i>Aucun produit trouvé pour cette commande.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($commande['commentaire_livraison']): ?>
        <div class="card mb-3">
            <div class="card-header bg-dark text-white">
                <i class="fas fa-comment me-2"></i>Commentaire de livraison
            </div>
            <div class="card-body">
                <p><?= nl2br(htmlspecialchars($commande['commentaire_livraison'])) ?></p>
            </div>
        </div>
    <?php endif; ?>
</div> 