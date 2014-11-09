<?php
$path="";
if (isset($_SERVER['PATH_INFO'])) $path=ltrim($_SERVER['PATH_INFO'], '/');
$basehref=str_repeat("../", substr_count($path, '/'));
if (!$basehref) $basehref="./";
include('php/Weboai.php');

?><!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8" />
    <link rel="stylesheet" type="text/css" href="<?php echo $basehref ?>local/cahier.css" />
  </head>
  <body class="oai">
    <h1><a href="<?php echo $basehref; ?>admin.php">Consortium CAHIER, administration du catalogue</a></h1>
    <main>
  <?php
Weboai::setform();
  ?>
    </main>
  </body>
</html>