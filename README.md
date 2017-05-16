# weboai


## Installer weboai

### Prérequis
* Un serveur Apache
* PHP5 ou supérieur + modules PDO, pdo_sqlite et xslt
* les fichiers XML TEI des textes dont on souhaite exposer les métadonnées en OAI  (ou a minima la partie teiHeader de ces fichiers) doivent être accessibles en ligne (on devra fournir leurs urls)
* les textes devront être organisés en une ou plusieurs collections (= sets). Voir infra la manière d'exprimer l'organisation des textes en collections

### Install
* déziper ou cloner ccahier/weboai sur le serveur apache (empacement linux par défaut des fichiers de sites : /var/www/html)
* vérifier les droits sur les fichiers : le fichier weboai.sqlite doit appartenir au groupe apache (www-data sur linux, _www sur OSX)
* copier _Conf.php en Conf.php et _.htaccess en .htaccess
* adapter le chemin du rewriteRule dans .htaccess
* passer AllowOverride à All dans la conf d'apache pour le répertoire ????????????
```
<Directory ???????????? >
	Options Indexes FollowSymLinks
	AllowOverride All
	Require all granted
</Directory>
```
* modifier les informations de conf dans Conf.php
