<?php
session_start();
require '../config.php';

// Vérifier si l'utilisateur est connecté et est un admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $telephone = trim($_POST['telephone']);
    $password = $_POST['password'];
    $type = $_POST['type'];
    
    // Validation des données
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "Le nom d'utilisateur est requis";
    }
    if (empty($email)) {
        $errors[] = "L'email est requis";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'email n'est pas valide";
    }
    if (empty($password)) {
        $errors[] = "Le mot de passe est requis";
    }
    if (!in_array($type, ['client', 'livreur', 'admin'])) {
        $errors[] = "Le type d'utilisateur n'est pas valide";
    }

    // Vérifier si l'email ou le nom d'utilisateur existe déjà
    $stmt = $conn->prepare("SELECT id FROM utilisateur WHERE email = ? OR utilisateur = ?");
    $stmt->bind_param("ss", $email, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $errors[] = "Cet email ou nom d'utilisateur existe déjà";
    }

    if (empty($errors)) {
        // Hasher le mot de passe
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insérer le nouvel utilisateur
        $query = "INSERT INTO utilisateur (utilisateur, nom, email, telephone, mot_de_passe, type, date_inscription) 
                 VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssss", $username, $nom, $email, $telephone, $hashed_password, $type);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Utilisateur ajouté avec succès";
            header('Location: utilisateurs.php');
            exit();
        } else {
            $errors[] = "Erreur lors de l'ajout de l'utilisateur";
        }
    }

    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        header('Location: utilisateurs.php');
        exit();
    }
}

// Si la méthode n'est pas POST, rediriger vers la page des utilisateurs
header('Location: utilisateurs.php');
exit(); 