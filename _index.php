<?php
include(dirname(__FILE__).'/Conf.php'); // importer la configuration
include(dirname(__FILE__).'/lib/Pub.php');
$pub=new Pub(Conf::$sqlite);



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
if ($pub->path == '') {
  $pub->sets();
}
else if (strpos($pub->path, 'set/') === 0) {
  $setspec = trim(substr($pub->path, 4), '/');
  $pub->set($setspec);
}
else if (strpos($pub->path, 'record/') === 0) {
  $oai_identifier = substr($pub->path, strpos($pub->path, '/') + 1);
  $pub->record($oai_identifier);
}
else {
  echo '<p>La page ' . $pub->path . ' n’a pas été trouvée</p>';
}
// $pub->chrono();
// $pub->biblio(); 
              ?>
    </main>
    <script type="text/javascript" src="<?php echo $pub->homehref . Conf::$weboaihref ?>lib/Sortable.js">//</script>
  </body>
</html>