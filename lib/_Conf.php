<?php
/**
 * Modifier la configuration selon les besoins de votre installation locale
 * Renommer le fichier en �Conf.php� sans le pr�fixe '_'
 *
 * Chaque param�tre est accessible dans l�application avec l��criture Conf::$param
 */
// rendre absolu un chemin relatif � ce fichier
Conf::$sqlite = dirname(dirname(__FILE__)) . '/' . Conf::$sqlite;
/**
 * ? = facultatif
 * ! = obligatoire
 */
class Conf {
  /** ! Nom public de l�entrep�t OAI */
  static $repositoryName = "Catalogue OAI du consortium CAHIER";
  /** ! Nom de domaine identifiant l�entrep�t OAI, sert notamment � forger les identifiants OAI */
  static $domain = "cahier.sf.net";
  /** ! Adresse email de l�administrateur du serveur OAI-PMH */
  static $adminEmail = "frederic.glorieux@fictif.org";
  /** ! authentification admin */
  static $user = array(
    'weboai' => array(
      'pass' => 'nimdA',
    )
  );
  /** Fichier de base SQLITE, doit �tre autoris� en �criture par Apache si chargement en ligne */
  static $sqlite =  'data/weboai.sqlite';
  /** ? URI absolue du serveur OAI, ou vaaleur automatique obtenue selon l�installation */
  static $baseURL;
}


?>