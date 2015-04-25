<?php
include (dirname(__FILE__).'/Conf.php'); // importer la configuration
include (dirname(__FILE__).'/lib/Pmh.php'); // importer le le serveur OAI
new Pmh(Conf::$sqlite);

?>