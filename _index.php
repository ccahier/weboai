<?php
include(dirname(__FILE__).'/Conf.php'); // importer la configuration
include(dirname(__FILE__).'/lib/Pub.php');
// si fichier .htaccess alors on suppose que les URI seront de type /set/setSpec /record/oai_identifier
if ( file_exists('.htaccess') ) $pub=new Pub( Conf::$sqlite, "PATHINFO" );
// sinon ?set=setSpec, ?record=oai_identifier
else $pub=new Pub( Conf::$sqlite );


?><!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8" />
    <title><?php echo Conf::$repositoryName ?></title>
    <link rel="stylesheet" type="text/css" href="<?php echo $pub->homehref . Conf::$weboaihref ?>lib/weboai.css" />
  </head>
  <body>
    <main>
      <h1><?php echo Conf::$repositoryName ?></h1>
      <?php
// un set demandé par paramètre (si pas de RewriteRule dans le .htaccess)
if ( isset($_REQUEST['set']) ) {
  $pub->set( $_REQUEST['set'] );
}
// un enregistrement demandé par paramètre (si pas de RewriteRule dans le .htaccess)
else if ( isset($_REQUEST['record']) ) {
  $pub->record( $_REQUEST['record'] );
}
// liste des sets
else if ($pub->path == '') {
  $pub->sets( );
}
// set par chemin d’URL
else if (strpos($pub->path, 'set/') === 0) {
  $setspec = trim(substr($pub->path, 4), '/');
  $pub->set( $setspec );
}
// Record par chemin D'URL
else if (strpos($pub->path, 'record/') === 0) {
  $oai_identifier = substr($pub->path, strpos($pub->path, '/') + 1);
  $pub->record( $oai_identifier );
}
else {
  echo '<p>La page ' . $pub->path . ' n’a pas été trouvée</p>';
}
              ?>
    </main>
    <script type="text/javascript" src="<?php echo $pub->homehref . Conf::$weboaihref ?>lib/Sortable.js">//</script>
  </body>
</html>
