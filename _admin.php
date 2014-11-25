<?php
include (dirname(__FILE__).'/Conf.php'); // importer la configuration
include (dirname(dirname(__FILE__)).'/weboai/lib/Weboai.php'); // importer la logique d’importation OAI
$message = array();
session_start();

// logout
if (isset($_POST['logout'])) {
  Weboai::logout();
}
// essai de login, par défaut, logout
else if (isset($_POST['user']) && isset($_POST['pass'])) {
  // Weboai::logout(); // NO, will send cookie destruction order
  $_SESSION = array();
  $allowed =  Weboai::allowed($_POST['user'], $_POST['pass']);
  if ($allowed === '') {
    $_SESSION['user'] = $_POST['user'];
    // $_SESSION['pass'] = $_POST['pass'];
  }
  else $message[] = $allowed;
}
else {
}
/*
// faut-il revérifier l’authentification ?
else if ( isset($_SESSION['user']) && isset($_SESSION['pass']) ) {
  $allowed =  Weboai::allowed($_SESSION['user'], $_SESSION['pass']);
  echo '<h1>Logged ? $allowed</h1>';
  // Weboai::logout();
}
*/



?><!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8" />
    <title>Admin Weboai</title>
    <link rel="stylesheet" type="text/css" href="<?php echo Conf::$weboaihref ?>lib/weboai.css" />
  </head>
  <body class="oai">
    <h1><a href="admin.php">Consortium CAHIER, gestion du catalogue</a></h1>
    <?php
if (isset($_SESSION['user'])) {
  echo '<form style="float: right" method="POST"><button name="logout">Déconnexion</button></form>';
} 
else {
  echo '<form method="POST" style="float: right">
  <input placeholder="Utilisateur" class="text" type="text" name="user"/>
  <br/><input placeholder="Mot de passe" type="password" class="text" name="pass"/>
  <div style="text-align: center; "><button name="login">Connexion</button></div>
</form>';
} 
    ?>
  <?php
echo implode("\n", $message);
// privé
if (isset($_SESSION['user'])) {
  Weboai::setform();
}
// public
else {
  $sitemaptei = false;
  if (isset($_REQUEST['sitemaptei'])) $sitemaptei = $_REQUEST['sitemaptei'];
  echo '
<p>Tester un Sitemap TEI</p>
<form name="test" class="oai" method="POST" action="#">
  <input name="sitemaptei" placeholder="Sitemap TEI (URI)" title="[sitemaptei] Tester une source de données TEI" onclick="select()" class="text" size="55" value="' . htmlspecialchars($sitemaptei) . '"/>
  <button name="test" title="Tester une source de données" value="1">Test</button>
</form>
';
  if (isset($_POST['test']) && $_POST['test']) {
    echo '<h1>Test d’un Sitemap TEI</h1>';
    if (!isset($_POST['sitemaptei'])) echo '<div class="error">Aucun Sitemap TEI à tester</div>';
    else Weboai::sitemaptei($_POST['sitemaptei']);
  }


}
  ?>
  </body>
</html>