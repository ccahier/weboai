# WebOAI

WebOAI permet d’extraire des notices Dublin Core depuis des collections de fichers XML/TEI pour les exposer sous forme de site et d’entrepôt OAI.

## Utilisation

Les fichiers XML TEI des textes dont on souhaite exposer les métadonnées en OAI  (ou a minima la partie teiHeader de ces fichiers) doivent être accessibles en ligne (on devra fournir leurs urls).

* [Schéma teiHeader](//ccahier.github.io/weboai/schema/teiHeader.html)
* [Schéma Dublin Core](//ccahier.github.io/weboai/schema/weboai.html)

Exemple d’installation
 * http://weboai.cahier.huma-num.fr/ (interface publique)
 * http://weboai.cahier.huma-num.fr/admin.php (administration des collections)
 * http://weboai.cahier.huma-num.fr/pmh (entrepôt OAI, XML transformé à la volés dans le navigateur, regarder la source pour voir le XML)

## Installation

### Prérequis

* Un serveur Apache
* PHP5.3 ou supérieur + modules PDO, pdo_sqlite et xslt
* Pour des belles adresses ('''clean uri'''), autoriser les .htaccess et les '''rewrite rules'''
```
<Directory ???????????? >
	Options Indexes FollowSymLinks
	AllowOverride All
	Require all granted
</Directory>
```

### Procédure

* Installer l’application sur le serveur (empacement par défaut, linux : /var/www/html, OSX : /Library/WebServer/Documents/)
  * Si accès en ligne de commmande, git clone https://github.com/ccahier/weboai.git, permet de mettre à jour la librairie avec la commande git pull.
  * Sinon, télécharger le zip sur GitHub, https://github.com/ccahier/weboai
* Droits, l’application a besoin de pouvoir écrire dans le dossier contenant la base sqlite, par défaut (dans conf.php) : /data/weboai.sqlite. Le dossier doit appartenir au groupe apache (linux : www-data,  sur linux, OSX :  _www).
* Copier _conf.php en conf.php, modifier le mot de passe d’administration.
* Si '''rewrite rules''', copier _.htaccess en .htaccess, modifier l’instruction RewriteBase
* Copier _index.php en index.php (permet de personnaliser l’accueil)
