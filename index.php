<?php
include(dirname(__FILE__).'/lib/Conf.php'); // importer la configuration
include(dirname(__FILE__).'/lib/Pub.php');
$pub=new Pub(Conf::$sqlite);



?><!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8" />
    <?php include(dirname(__FILE__).'/cahier/head.php'); ?>
    <title>Weboai</title>
    <link rel="stylesheet" type="text/css" href="<?php echo $pub->homehref ?>lib/weboai.css" />
    <link rel="stylesheet" type="text/css" href="<?php echo $pub->homehref ?>cahier/cahier.css" />
  </head>
  <body>
    <div id="body-pattern">
      <?php include(dirname(__FILE__).'/cahier/header.php'); ?>
      <div id="content" class="the-page content">
        <div id="content-containers" class="rightside">
          <div id="narrow-container" class="make-shadow global-radius">
            <div id="narrow-content" class="global-radius">
              <h3 class="sidebar-widget-title">Actualités</h3>
            </div>
          </div>
          <div id="withsidebar-container" class="make-shadow global-radius">
            <div id="withsidebar-content" class="global-radius" style="min-height: 0px;">
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
            </div>
          </div>
        </div>
      </div>
      <?php include(dirname(__FILE__).'/cahier/footer.php'); ?>
    </div>
    <script type="text/javascript" src="<?php echo $pub->homehref ?>lib/Sortable.js">//</script>
  </body>
</html>