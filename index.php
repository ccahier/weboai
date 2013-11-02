<?php
$path="";
if (isset($_SERVER['PATH_INFO'])) $path=ltrim($_SERVER['PATH_INFO'], '/');
$baseHref=str_repeat("../", substr_count($path, '/'));
if (!$baseHref) $baseHref="./";
include('Oaiweb.php');
$oaiweb=new Oaiweb('weboai.sqlite');

?><!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8" />
    <?php include(dirname(__FILE__).'/local/head.php'); ?>
    <link rel="stylesheet" type="text/css" href="<?php echo $baseHref ?>local/cahier.css" />
  </head>
  <body>
    <div id="body-pattern">
      <?php include(dirname(__FILE__).'/local/header.php'); ?>
      <div id="content" class="the-page">
        <div id="content-containers" class="rightside">
          <div id="narrow-container" class="make-shadow global-radius">
            <div id="narrow-content" class="global-radius">
              <h3 class="sidebar-widget-title">Actualités</h3>
            </div>
          </div>
          <div id="withsidebar-container" class="make-shadow global-radius">
            <div id="withsidebar-content" class="global-radius" style="min-height: 0px;">
              <h1>Consortium CAHIER, les collections</h1>
              <p>
Ceci est une démonstration de Weboai sur les corpus CAHIER.
Plus d’explications sur le <a href="http://sourceforge.net/p/weboai/wiki/Home/">wiki du projet Sourceforge</a>. 
              </p>
              <?php 
$oaiweb->chrono();
echo '<p> </p>';
$oaiweb->biblio(); 
              ?>
            </div>
          </div>
        </div>
      </div>
      <?php include(dirname(__FILE__).'/local/footer.php'); ?>
    </div>
    <script type="text/javascript" src="<?php echo $baseHref ?>lib/Sortable.js">//</script>
  </body>
</html>