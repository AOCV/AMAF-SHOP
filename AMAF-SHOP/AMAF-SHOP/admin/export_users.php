<?php
session_start();
require '../config.php';

// Vérifier si l'utilisateur est connecté et est un admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Configuration de l'en-tête pour le téléchargement du fichier CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=utilisateurs_' . date('Y-m-d') . '.csv');

// Création du fichier CSV
$output = fopen('php://output', 'w');

// Ajout du BOM UTF-8 pour Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// En-têtes des colonnes
fputcsv($output, [
    'ID',
    'Nom d\'utilisateur',
    'Nom',
    'Email',
    'Téléphone',
    'Type',
    'Date d\'inscription'
]);

// Récupération des utilisateurs
$query = "SELECT id, utilisateur, nom, email, telephone, type, date_inscription 
          FROM utilisateur 
          ORDER BY date_inscription DESC";
$result = $conn->query($query);

// Écriture des données
while ($row = $result->fetch_assoc()) {
    // Formatage de la date
    $row['date_inscription'] = date('d/m/Y', strtotime($row['date_inscription']));
    
    // Écriture de la ligne dans le CSV
    fputcsv($output, [
        $row['id'],
        $row['utilisateur'],
        $row['nom'],
        $row['email'],
        $row['telephone'] ?? '',
        $row['type'],
        $row['date_inscription']
    ]);
}

// Fermeture du fichier
fclose($output);
exit(); 