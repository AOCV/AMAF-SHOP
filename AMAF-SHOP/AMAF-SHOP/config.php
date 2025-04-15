<?php
$host = "localhost"; // Serveur MySQL (localhost en local)
$dbname = "e-commerce"; // Nom de la base de données
$username = "root"; // Nom d'utilisateur MySQL
$password = ""; // Mot de passe (laisser vide en local sous XAMPP)

// Création de la connexion
$conn = new mysqli($host, $username, $password, $dbname);

// Vérifier la connexion
if ($conn->connect_error) {
    die("Échec de la connexion : " . $conn->connect_error);
}

// Définir l'encodage des caractères
$conn->set_charset("utf8");
?>
