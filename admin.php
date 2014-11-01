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
  <body>
  <?php
Weboai::setform();
  
  ?>
  </body>
</html>