<?php
/**
 * Modifier la configuration selon les besoins de votre installation locale
 * Renommer le fichier en �Conf.php� sans le pr�fixe '_'
 *
 * Chaque param�tre est accessible dans l�application avec l��criture Conf::$param
 */
/**
 * ? = facultatif
 * ! = obligatoire
 */
class Conf {
  /** ! Nom public de l�entrep�t OAI */
  static $repositoryName = "Catalogue Weboai";
  /** ! Adresse email de l�administrateur du serveur OAI-PMH */
  static $adminEmail = "frederic.glorieux@fictif.org";
  /** ! authentification admin */
  static $user = array(
    'weboai' => array(
      'pass' => 'nimdA',
    )
  );
  /** Fichier de base SQLITE, doit �tre autoris� en �criture par Apache si chargement en ligne */
  static $sqlite = 'weboai.sqlite';
  /** ? URI absolue du serveur OAI, ou valeur automatique obtenue selon l�installation */
  static $baseURL;
  /** 
   * ? Nom de domaine identifiant l�entrep�t OAI, sert notamment � forger les identifiants OAI, 
   * ou valeur automatique obtenue selon l�installation (attention � localhost)
   */
  static $domain;
  /** Lien relatif de la librairie weboai, permet de d�porter l�installation et la librairie */
  static $weboaihref;
}


?>