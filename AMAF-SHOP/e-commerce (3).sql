-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : ven. 28 mars 2025 à 01:20
-- Version du serveur : 8.2.0
-- Version de PHP : 8.2.13

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `e-commerce`
--

-- --------------------------------------------------------

--
-- Structure de la table `avis`
--

DROP TABLE IF EXISTS `avis`;
CREATE TABLE IF NOT EXISTS `avis` (
  `id` int NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int NOT NULL,
  `produit_id` int NOT NULL,
  `note` int DEFAULT NULL,
  `commentaire` text,
  `date_avis` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `utilisateur_id` (`utilisateur_id`),
  KEY `produit_id` (`produit_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `categorie`
--

DROP TABLE IF EXISTS `categorie`;
CREATE TABLE IF NOT EXISTS `categorie` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(70) NOT NULL,
  `description` text,
  `image_url` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `categorie`
--

INSERT INTO `categorie` (`id`, `nom`, `description`, `image_url`) VALUES
(1, 'Habits', NULL, NULL),
(2, 'Chaussures', NULL, NULL),
(3, 'Pantalons', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `commande`
--

DROP TABLE IF EXISTS `commande`;
CREATE TABLE IF NOT EXISTS `commande` (
  `id` int NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int NOT NULL,
  `date_commande` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `total` decimal(10,2) NOT NULL,
  `informations_livraison` text,
  `statut` varchar(50) NOT NULL DEFAULT 'en attente',
  `livreur_id` int DEFAULT NULL,
  `commentaire_livraison` text,
  `date_livraison` timestamp NULL DEFAULT NULL,
  `payment_method` varchar(25) DEFAULT NULL,
  `payment_status` varchar(25) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `utilisateur_id` (`utilisateur_id`),
  KEY `livreur_id` (`livreur_id`)
) ENGINE=MyISAM AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `commande`
--

INSERT INTO `commande` (`id`, `utilisateur_id`, `date_commande`, `total`, `informations_livraison`, `statut`, `livreur_id`, `commentaire_livraison`, `date_livraison`, `payment_method`, `payment_status`) VALUES
(11, 11, '2025-02-19 15:36:52', 22000.00, '{\"nom\":\"BAH\",\"prenom\":\"ABDOUL\",\"adresse\":\"ADJAME\",\"telephone\":\"0777686880\",\"instructions\":\"\"}', 'livré', 4, NULL, NULL, NULL, NULL),
(5, 9, '2025-02-19 11:43:18', 42000.00, '{\"nom\":\"RUDY\",\"prenom\":\"OGBEDECHI\",\"adresse\":\"abobo\",\"telephone\":\"0777686880\",\"instructions\":\"\"}', 'confirmé', 4, NULL, NULL, NULL, NULL),
(6, 4, '2025-02-19 11:49:08', 22000.00, '{\"nom\":\"RUDY\",\"prenom\":\"OGBEDECHI\",\"adresse\":\"abobo\",\"telephone\":\"0777686880\",\"instructions\":\"\"}', 'livré', 4, NULL, NULL, NULL, NULL),
(7, 9, '2025-02-19 11:54:01', 22000.00, '{\"nom\":\"RUDY\",\"prenom\":\"OGBEDECHI\",\"adresse\":\"abobo\",\"telephone\":\"0777686880\",\"instructions\":\"\"}', 'annulé', 4, NULL, NULL, NULL, NULL),
(8, 9, '2025-02-19 11:56:37', 14000.00, '{\"nom\":\"RUDY\",\"prenom\":\"OGBEDECHI\",\"adresse\":\"abobo\",\"telephone\":\"0777686880\",\"instructions\":\"\"}', 'confirmé', 4, NULL, NULL, NULL, NULL),
(9, 9, '2025-02-19 12:14:18', 50000.00, '{\"nom\":\"RUDY\",\"prenom\":\"OGBEDECHI\",\"adresse\":\"abobo\",\"telephone\":\"0777686880\",\"instructions\":\"\"}', 'annulé', 4, NULL, NULL, NULL, NULL),
(10, 8, '2025-02-19 14:53:16', 22000.00, '{\"nom\":\"RUDY\",\"prenom\":\"OGBEDECHI\",\"adresse\":\"abobo\",\"telephone\":\"0777686880\",\"instructions\":\"\"}', 'confirmé', 10, NULL, NULL, NULL, NULL),
(12, 16, '2025-03-27 12:08:50', 32000.00, '{\"nom\":\"mondesir\",\"adresse\":\"abidjan n\'dotre\",\"telephone\":\"1212457865\",\"instructions\":\"je veux les bien emballer \"}', 'annulé', 4, NULL, NULL, NULL, NULL),
(13, 16, '2025-03-27 12:10:42', 32000.00, '{\"nom\":\"mondesir\",\"adresse\":\"abidjan n\'dotre\",\"telephone\":\"1212457865\",\"instructions\":\"je veux les bien emballer \"}', 'annulé', 4, NULL, NULL, NULL, NULL),
(14, 16, '2025-03-27 12:10:45', 32000.00, '{\"nom\":\"mondesir\",\"adresse\":\"abidjan n\'dotre\",\"telephone\":\"1212457865\",\"instructions\":\"je veux les bien emballer \"}', 'en_livraison', 4, NULL, NULL, NULL, NULL),
(15, 16, '2025-03-27 12:13:45', 32000.00, '{\"nom\":\"mondesir\",\"adresse\":\"abidjan n\'dotre\",\"telephone\":\"1212457865\",\"instructions\":\"je veux les bien emballer \"}', 'en attente', 4, NULL, NULL, NULL, NULL),
(16, 16, '2025-03-27 12:14:06', 32000.00, '{\"nom\":\"mondesir\",\"adresse\":\"abidjan n\'dotre\",\"telephone\":\"1212457865\",\"instructions\":\"je veux les bien emballer \"}', 'livre', 4, NULL, NULL, NULL, NULL),
(17, 16, '2025-03-27 12:33:06', -9800000.00, '{\"nom\":\"mondesir\",\"prenom\":\"\",\"adresse\":\"bingerville\",\"telephone\":\"0707489545\",\"instructions\":\"jjfjfjfjf\"}', 'en attente', 10, NULL, NULL, NULL, NULL),
(18, 16, '2025-03-27 12:49:37', -9800000.00, '{\"nom\":\"mondesir\",\"prenom\":\"\",\"adresse\":\"Abobo, Abidjan, C\\u00f4te d\\u2019Ivoire\",\"telephone\":\"0707489545\",\"instructions\":\"ok\",\"coordinates\":{\"lat\":\"5.441247\",\"lng\":\"-4.030109\"}}', 'livré', 4, NULL, NULL, NULL, NULL),
(19, 16, '2025-03-27 12:58:54', 12000.00, '{\"nom\":\"mondesir\",\"prenom\":\"\",\"adresse\":\"Abobo, Abidjan, C\\u00f4te d\\u2019Ivoire\",\"telephone\":\"0707489545\",\"instructions\":\"ok\",\"coordinates\":{\"lat\":\"5.441247\",\"lng\":\"-4.030109\"}}', 'en attente', 10, NULL, NULL, NULL, NULL),
(20, 17, '2025-03-27 14:16:06', 12000.00, '{\"nom\":\"harold\",\"prenom\":\"\",\"adresse\":\"Abobo, Abidjan, C\\u00f4te d\\u2019Ivoire\",\"telephone\":\"0707489545\",\"instructions\":\".....\",\"coordinates\":{\"lat\":\"5.441247\",\"lng\":\"-4.030109\"}}', 'livre', 4, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `commande_produit`
--

DROP TABLE IF EXISTS `commande_produit`;
CREATE TABLE IF NOT EXISTS `commande_produit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `commande_id` int NOT NULL,
  `produit_id` int NOT NULL,
  `quantite` int NOT NULL,
  `prix_unitaire` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `commande_id` (`commande_id`),
  KEY `produit_id` (`produit_id`)
) ENGINE=MyISAM AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `commande_produit`
--

INSERT INTO `commande_produit` (`id`, `commande_id`, `produit_id`, `quantite`, `prix_unitaire`) VALUES
(1, 1, 17, 1, 20000.00),
(2, 2, 10, 1, 12000.00),
(3, 3, 17, 1, 20000.00),
(4, 4, 10, 1, 12000.00),
(5, 5, 17, 2, 20000.00),
(6, 6, 17, 1, 20000.00),
(7, 7, 17, 1, 20000.00),
(8, 8, 10, 1, 12000.00),
(9, 9, 10, 4, 12000.00),
(10, 10, 17, 1, 20000.00),
(11, 11, 17, 1, 20000.00),
(12, 16, 10, 1, 12000.00),
(13, 16, 17, 1, 20000.00),
(14, 17, 19, 1, -9800000.00),
(15, 18, 19, 1, -9800000.00),
(16, 19, 10, 1, 12000.00),
(17, 20, 10, 1, 12000.00);

-- --------------------------------------------------------

--
-- Structure de la table `livraison`
--

DROP TABLE IF EXISTS `livraison`;
CREATE TABLE IF NOT EXISTS `livraison` (
  `id` int NOT NULL AUTO_INCREMENT,
  `commande_id` int NOT NULL,
  `livreur_id` int NOT NULL,
  `date_estimee` date DEFAULT NULL,
  `date_livraison` date DEFAULT NULL,
  `statut` enum('en attente','en cours','livrée','échec') DEFAULT 'en attente',
  PRIMARY KEY (`id`),
  KEY `commande_id` (`commande_id`),
  KEY `livreur_id` (`livreur_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `paiement`
--

DROP TABLE IF EXISTS `paiement`;
CREATE TABLE IF NOT EXISTS `paiement` (
  `id` int NOT NULL AUTO_INCREMENT,
  `commande_id` int NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `methode` enum('carte','paypal','virement','espèces') NOT NULL,
  `statut` enum('en attente','réussi','échec') DEFAULT 'en attente',
  `date_paiement` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `commande_id` (`commande_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `paiement`
--

INSERT INTO `paiement` (`id`, `commande_id`, `montant`, `methode`, `statut`, `date_paiement`) VALUES
(1, 1, 20000.00, 'carte', 'en attente', '2025-02-18 16:25:36');

-- --------------------------------------------------------

--
-- Structure de la table `produit`
--

DROP TABLE IF EXISTS `produit`;
CREATE TABLE IF NOT EXISTS `produit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(70) NOT NULL,
  `description` text,
  `prix` decimal(10,2) NOT NULL,
  `stock` int NOT NULL,
  `categorie_id` int NOT NULL,
  `taille` varchar(10) DEFAULT NULL,
  `couleur` varchar(50) DEFAULT NULL,
  `marque` varchar(90) DEFAULT NULL,
  `promotion` decimal(10,2) DEFAULT NULL,
  `image_url` varchar(95) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `categorie_id` (`categorie_id`)
) ENGINE=MyISAM AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `produit`
--

INSERT INTO `produit` (`id`, `nom`, `description`, `prix`, `stock`, `categorie_id`, `taille`, `couleur`, `marque`, `promotion`, `image_url`) VALUES
(10, 'CAGO', 'très bon', 12000.00, 10, 3, '', '', '', NULL, 'uploads/67a32487a5db0.jpg'),
(17, 'CHEMISE', 'QLMVZDBZFGUYZ', 20000.00, 11, 1, '', '', '', NULL, 'uploads/67a33a13e693c.jpg'),
(19, 'ADIDAS', 'VGSCFYGZJDBZ', 200000.00, 21, 0, 'XL', 'Noir', 'ADIDAS', 5000.00, 'uploads/67e318fb8da45.jpg'),
(13, 'NEW BALANCE', 'Très bon', 25000.00, 20, 2, '', '', '', 1250.00, 'uploads/67a3267f74e02.jpg'),
(14, 'NEW BALANCE', 'Très bon', 25000.00, 20, 2, '', '', '', 1250.00, 'uploads/67a327541fd78.jpg'),
(16, 'Hello', 'azertyssdsg', 10000.00, 10, 1, '', '', '', 2000.00, 'uploads/67a32a28a805d.jpg');

-- --------------------------------------------------------

--
-- Structure de la table `promotion`
--

DROP TABLE IF EXISTS `promotion`;
CREATE TABLE IF NOT EXISTS `promotion` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `categorie_id` int NOT NULL,
  `type_reduction` enum('montant','pourcentage') NOT NULL DEFAULT 'montant',
  `valeur_reduction` decimal(10,2) NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '0',
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `categorie_id` (`categorie_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `promotion`
--

INSERT INTO `promotion` (`id`, `nom`, `categorie_id`, `type_reduction`, `valeur_reduction`, `date_debut`, `date_fin`, `actif`, `date_creation`) VALUES
(1, 'ramadan', 2, 'pourcentage', 5.00, '2025-03-27', '2025-04-29', 1, '2025-03-27 16:54:05');

-- --------------------------------------------------------

--
-- Structure de la table `retour`
--

DROP TABLE IF EXISTS `retour`;
CREATE TABLE IF NOT EXISTS `retour` (
  `id` int NOT NULL AUTO_INCREMENT,
  `commande_id` int NOT NULL,
  `produit_id` int NOT NULL,
  `raison` text,
  `statut` enum('demandé','accepté','refusé','remboursé') DEFAULT 'demandé',
  `date_retour` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `commande_id` (`commande_id`),
  KEY `produit_id` (`produit_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `utilisateur`
--

DROP TABLE IF EXISTS `utilisateur`;
CREATE TABLE IF NOT EXISTS `utilisateur` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `email` varchar(50) NOT NULL,
  `mot_de_passe` varchar(55) NOT NULL,
  `adresse` varchar(55) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `type` enum('client','livreur','admin') NOT NULL,
  `date_inscription` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `utilisateur` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `utilisateur`
--

INSERT INTO `utilisateur` (`id`, `nom`, `email`, `mot_de_passe`, `adresse`, `telephone`, `type`, `date_inscription`, `utilisateur`) VALUES
(1, 'RUDY', 'tho@gmail.com', '$2y$10$kek1Dgv.cEwHbKIZnOihNOBE7WfZliOEQEjkRaKwBSRqiUCA', 'abobo', '0777686880', 'client', '2025-02-05 00:14:15', 'OGBEDECHI'),
(2, 'RUDY JUJU', 'th@gmail.com', '$2y$10$PTnrnXrWVhsQRs9VneFUk.CSaR79d/RTKu45j5ysGpRg4Cg9', 'abobo', '0777686880', 'client', '2025-02-05 00:20:27', 'OGBEDECHI'),
(3, 'AMAFI', 'amafi@gmail.com', '123456', 'dabou', '1234567890', 'admin', '2025-02-05 00:33:58', 'LAMAFI'),
(4, 'Sarba', 'sarba@gmail.com', '1234567', 'ADJAME', '0765478933', 'livreur', '2025-02-10 18:31:15', 'sarba'),
(5, 'KOUAKOU EMMANUEL', 'nabi23@gmail.com', '$2y$10$xWCnABCse3WsrUGObblCHuWhWUIjDXkSYOLvbxNtfNLEriEp', 'abobo', '0777686880', 'client', '2025-02-14 09:29:44', 'mama'),
(6, 'kouassi marcelle', 'marcelle@gmail.com', '$2y$10$AumnaxzppBN9XuyvimPDNOrpefb/Z3K0/6sPxycKHhGhY/kz', 'abobo', '0777686880', 'client', '2025-02-18 09:27:46', 'kouassi'),
(7, 'KOUAKOU EMMANUEL', 'kouakou@gmail.com', '$2y$10$7I86YD8JMD4zhmjRmK.cD.UTlS5MOi3dVXKkddXHl7C6rnBr', 'abobo', '0777686880', 'client', '2025-02-18 09:47:55', 'kouakou'),
(8, 'Test Admin', 'admin@test.com', 'admin123', NULL, NULL, 'admin', '2025-02-18 10:09:13', 'admin'),
(9, 'SANGARE', 'sangare@gmail.com', '1234', 'abobo', '0777686880', 'client', '2025-02-18 10:23:30', 'ABOU'),
(10, 'DONI GERAD', 'gera@gmail.com', '$2y$10$gJIAar.d9C2p5R0tuIq8NeXLn/k6Q/sHCuCNrczVn9OJVMR.', NULL, '34567890', 'livreur', '2025-02-19 14:51:45', 'GERAD'),
(11, 'bah', 'bah@gmail.com', '1234', 'abobo', '0777686880', 'client', '2025-02-19 15:35:58', 'Bah abdoul'),
(12, 'Bah Abdoulaye', 'bah@67gmail.com', 'Amafi', 'COCODY', '0141949074', 'client', '2025-02-19 15:55:48', 'Abdoull67'),
(13, 'RUDY', 'tho1@gmail.com', '1234', 'abobo', '0777686880', 'client', '2025-03-25 12:53:23', 'OGBEDECHI RUDY'),
(14, 'SARBA ABIDINE', 'sarba2@gmail.com', '12345', 'abobo', '0777686880', 'client', '2025-03-25 20:53:06', 'Sarba225'),
(15, 'DOUA RUTH', 'doua@gmail.com', '12345', 'abobo', '0777686880', 'client', '2025-03-27 10:53:35', 'DOUA225'),
(16, 'mondesir', 'mondesir@gmail.com', '123', 'abidjan n\'dotre', '1212457865', 'client', '2025-03-27 11:51:50', 'desir'),
(17, 'harold', 'harold@gmail.com', '123', 'abidjan', '0445785954', 'client', '2025-03-27 14:15:08', 'harold');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
