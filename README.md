# weboai


## Installer weboai

### Prérequis
* Un serveur Apache
* PHP5 + modules PDO, pdo_sqlite et xslt
* les fichiers XML TEI (ou au moins leur partie teiHeader) doivent être accessibles en ligne
* les textes devront être organisés en une ou plusieurs collections (= sets). Voir infra pour exprimer l'organisation en collections

### install
* déziper ou cloner ccahier/weboai sur le serveur
* vérifier les droits sur les fichiers : le fichier weboai.sqlite doit appartenir au groupe appache (_www)
* copier _Conf.php en Conf.php et _.htaccess en .htaccess
* adapter le chemin du rewriteRule dans .htaccess
* modifier les informations de conf dans Conf.php
