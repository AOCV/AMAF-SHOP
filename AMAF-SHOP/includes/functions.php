<?php
function getNombreArticlesPanier() {
    if (!isset($_SESSION['panier'])) {
        return 0;
    }
    
    $total = 0;
    foreach ($_SESSION['panier'] as $item) {
        if (is_array($item) && isset($item['quantite'])) {
            $total += $item['quantite'];
        }
    }
    return $total;
}

?>
