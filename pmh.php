<?php
$conf = include (dirname(__FILE__).'/conf.php'); // importer la configuration
include (dirname(__FILE__).'/lib/Pmh.php'); // importer le le serveur OAI
new Pmh( $conf['sqlite'] );

?>
