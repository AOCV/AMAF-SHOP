<?php
function getNombreArticlesPanier() {
    if (!isset($_SESSION['panier'])) {
        return 0;
    }
    
    $total = 0;
    foreach ($_SESSION['panier'] as $quantite) {
        $total += $quantite;
    }
    return $total;
}
?>
