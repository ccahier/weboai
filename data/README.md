# Données

Les données importées sont entièrement contenues dans une base sqlite.
Le chemin de cette base est configuré dans le fichier conf.php en racine
de cette application.
Afin que la base de données puisse être alimentée avec les formulaires PHP en ligne,
il faut que l’utilisateur Apache (ex : www-data sur debian, _www sur macosx) soit autorisé à écrire
dans le dossier qui contient la base Sqlite.
En effet, la base est créée par le script lib/Weboai.php, de plus, Sqlite a besoin
de créer des fichiers temporaires dans le dossier de la base,
notamment pour pouvoir journaliser les opérations en cas de crash.
https://sqlite.org/tempfiles.html
On conseillera donc de placer la base dans un sous-dossier de l’application,
pour limiter l’espace ou le serveur peut écrire,
par exemple ce dossier data/
(ce chemin est modifiable dans conf.php).
