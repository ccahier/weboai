<?php
/**
 * Modifier la configuration selon les besoins de votre installation locale
 * Renommer le fichier en Conf.php (sans le préfixe '_')
 *
 * Chaque paramètre est accessible dans l’application avec l’écriture Conf::$param
 */
/**
 * ? = facultatif
 * ! = obligatoire
 */
return array(
  // ! Nom public de l’entrepôt OAI
  'repositoryName' => "Catalogue Weboai",
  // ! Adresse email de l’administrateur du serveur OAI-PMH
  'adminEmail' => "email@domain.com",
  // ! authentification admin
  'user' => array(
    'weboai' => array(
      'pass' => 'nimdA',
    )
  ),
  /* Chemin de fichier de la base SQLITE, le dossier qui la contient
   doit être autorisé en écriture par Apache si chargement en ligne */
  'sqlite' => 'data/weboai.sqlite',
  // ? URI absolue du serveur OAI, ou valeur automatique obtenue selon l’installation
  'baseURL' => null,
  /*
   * ? Nom de domaine identifiant l’entrepôt OAI, sert notamment à forger les identifiants OAI,
   * ou valeur automatique obtenue selon l’installation (attention à localhost)
   */
  'domain' => null,
  /** Lien relatif de la librairie weboai, permet de déporter l’installation et la librairie */
  'weboaihref' => null,
);


?>
