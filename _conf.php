<?php
/**
 * Modifier la configuration selon les besoins de votre installation locale
 * Renommer le fichier en Conf.php (sans le préfixe '_')
 *
 * Chaque paramètre est accessible dans l’application avec l’écriture Conf::$param
 *
 * ? = facultatif
 * ! = obligatoire
 */
class Conf {
  /** ! Nom public de l’entrepôt OAI */
  static $repositoryName = "Catalogue Weboai";
  /** ! Adresse email de l’administrateur du serveur OAI-PMH, à modifier */
  static $adminEmail = "frederic.glorieux@fictif.org";
  /** ! authentification admin, à modifier */
  static $user = array(
    'weboai' => array(
      'pass' => 'nimdA',
    )
  );
  /** ! Fichier de base SQLITE, doit être autorisé en écriture par Apache si chargement en ligne */
  static $sqlite = 'data/weboai.sqlite';
  /** ? URI absolue du serveur OAI, ou valeur automatique obtenue selon l’installation */
  static $baseURL;
  /**
   * ? Nom de domaine identifiant l’entrepôt OAI, sert notamment à forger les identifiants OAI,
   * Si vide, valeur automatique : localhost, mydomain.com…
   */
  static $domain;
  /** ? Lien relatif de la librairie weboai, permet de déporter l’installation et la librairie */
  static $weboaihref;
}


?>