<?php
/**
 * Classe de chargement des notices pour exposition OAI
 *
 * http://www.bnf.fr/documents/Guide_oaipmh.pdf
 */
ini_set( 'default_charset', 'UTF-8' );
set_time_limit(-1);
Weboai::$conf = include( dirname(dirname(__FILE__)).'/conf.php' );
Weboai::$re['fr_sort_tr'] = Weboai::dic( dirname(__FILE__).'/fr_sort.json' ); // clé de tri pour noms propres
$tz = ini_get('date.timezone');
if ( !$tz ) $tz = "Europe/Paris";
date_default_timezone_set( $tz );
if (php_sapi_name() == "cli") Weboai::docli();

class Weboai {
  static $conf;
  static $debug; // debug mode
  private $srcurl; //path du fichier chargé
  private $srcfilename; // nom du fichier chargé
  private $doc; // DOM du XML en court de traitement
  private $xsl; // DOM d’xsl de transformation
  private $proc; // processeur xsl
  private static $log; // flux de log
  private static $duplicate;

  public static $pdo; // sqlite connection
  private static $stmt=array(); // store PDOStatement in array
  private static $pars=array(); // parameters (as record_rowid) for SQL
  private static $vacuum; // nettoyer ?
  public static $setlang = array( // liste des langue utilisée pour les sets
    'fr' => 'lang:fre',
    'fre' => 'lang:fre',
    'fra' => 'lang:fre',
  ); // lang sets
  public static $re=array();

  function __construct( $srcurl = false )
  {
    $this->xsl = new DOMDocument("1.0", "UTF-8");
    $this->proc = new XSLTProcessor();
    if ( !isset( self::$conf['domain'] ) ) self::$conf['domain'] = $_SERVER['HTTP_HOST'];
    if( $srcurl ) {
      $this->srcurl = $srcurl;
      $this->srcfilename = pathinfo( $srcurl, PATHINFO_FILENAME );
      $this->load( $srcurl );
    }
  }
  /**
   * Sortir de l’authentification
   */
  public static function logout()
  {
    // if(session_id() == '' || !isset($_SESSION)) return;
    if (isset($_SESSION)) $_SESSION = array();
    if (ini_get("session.use_cookies")) {
      $params = session_get_cookie_params();
      if(session_name()) setcookie(session_name(), '', time() - 42000,
          $params["path"], $params["domain"],
          $params["secure"], $params["httponly"]
      );
    }
    if(session_id()) session_destroy();
  }
  /**
   * Renvoit un message, ou true si c’est OK
   */
  public static function allowed( $user, $pass )
  {
    $message = array();
    if (!isset( self::$conf['user'] )) return '<div class="message">Pas d’utilisateurs configurés.</div>';
    else if ( !isset( self::$conf['user'][$user]) ) return '<p class="error">Utilisateur ou mot de passe inconnu</p>';
    else if ( !isset( self::$conf['user'][$user]['pass']) ) return '<div class="message">Utilisateur inutilisable (pour ce rôle).</div>';
    else if ( self::$conf['user'][$user]['pass'] != $pass) return '<p class="error">Utilisateur ou mot de passe inconnu</p>';
    else return '';
  }
  /**
   * Chargement optimisé d’un tei header (ne pas tout télécharger)
   */
  function teiheader($url)
  {
    $this->srcurl = $url;
    $this->srcfilename = pathinfo($this->srcurl, PATHINFO_FILENAME);
    $this->doc = false;
    $reader = new XMLReader();
    if (!@$reader->open($this->srcurl, null, LIBXML_NOENT | LIBXML_NSCLEAN | LIBXML_NOCDATA | LIBXML_NOWARNING)) {
      $reader->close();
      return false;
    }
    libxml_clear_errors();
    libxml_use_internal_errors(true);
    while (@$reader->read()) {
      if ($reader->name != 'teiHeader') continue;
      $this->doc = new DOMDocument("1.0", "UTF-8");
      $this->doc->preserveWhiteSpace = false;
      $this->doc->substituteEntities = true;
      $this->doc->formatOutput = true;
      $this->doc->appendChild($reader->expand());
      break;
    }
    $reader->close();
    return $this->doc;
  }

  /**
   * Signaler une erreur
   */
  public static function log($line)
  {
    if (!self::$log) self::$log = STDERR;
    fwrite (self::$log, $line . "\n");
  }

  public static function formpublic()
  {

    $sitemaptei = '';
    if (isset($_REQUEST['sitemaptei'])) $sitemaptei = $_REQUEST['sitemaptei'];
    $html[] = '
<form name="sitemaptei" class="oai" method="POST">
  <input name="sitemaptei" placeholder="Sitemap TEI (URI)" title="[sitemaptei] Source de données TEI" onclick="select()" class="text" size="55" value="' . $sitemaptei . '"/>
  <button name="test" title="Tester une source de données" value="1">Test</button>
</form>
<form name="tei2oai" class="oai" method="POST" enctype="multipart/form-data">
  <input type="file" accept=".xml,.tei,.txt" name="tei" placeholder="Fichier TEI" title="Fichier TEI" onclick="select()" class="text" size="55"/>
  <button name="tei2oai" title="Tester un fichier TEI" value="1">Test</button>
</form>
      ';
    print(implode("\n", $html));
    if (isset($_POST['sitemaptei']) ) {
      echo '<h1>Test d’un Sitemap TEI</h1>';
      if (!$_POST['sitemaptei']) echo '<div class="error">Aucun Sitemap TEI à tester</div>';
      else self::sitemaptei($_POST['sitemaptei']);
      return true;
    }
    else if (isset($_POST['tei2oai']) ) {
      echo '<h1>Notice OAI pour un fichier TEI</h1>';
      $a = self::upload();
      if (!$a || !count($a) || !isset($a['file'])) echo '<div class="error">Pas de fichier reçu</div>';
      else {
        $weboai = new Weboai($a['file']);
         echo '<div>' . $a['name'] . '</div>' . "\n";
         echo '<textarea style="width: 100%" rows="25">' . "\n";
         $doc = $weboai->tei2oai();
         echo $doc->saveXML();
         echo '</textarea>' . "\n";
      }
      return true;
    }
  }
  /**
   * Création ou modification d’un set depuis un formulaire html
   */
  public static function formset()
  {
    self::connect();
    $html = array();
    $set = $setspec = $setname = $publisher = $identifier = $title = $description = $sitemaptei = $oai = null;
    if (isset($_REQUEST['setspec'])) $setspec = $_REQUEST['setspec'];
    if (isset($_REQUEST['setname'])) $setname = $_REQUEST['setname'];
    if (isset($_REQUEST['publisher'])) $publisher = $_REQUEST['publisher'];
    if (isset($_REQUEST['identifier'])) $identifier = $_REQUEST['identifier'];
    if (isset($_REQUEST['title'])) $title = $_REQUEST['title'];
    if (isset($_REQUEST['description'])) $description = $_REQUEST['description'];
    if (isset($_REQUEST['subject'])) $subject = $_REQUEST['subject'];
    if (isset($_REQUEST['sitemaptei'])) $sitemaptei = $_REQUEST['sitemaptei'];

    self::$stmt['selset']=self::$pdo->prepare('SELECT rowid, setspec, setname, publisher, identifier, title, description, sitemaptei, oai  FROM oaiset WHERE setspec = ?');
    // pas de set demandé, donner la liste
    if (!$setspec) {
      $html[] = '<ul class="setspec">';
      foreach (self::$pdo->query('SELECT * FROM oaiset ORDER BY setspec') as $row) {
        $html[] = '<li><a href="?setspec=' . htmlspecialchars($row['setspec']) . '">[' . htmlspecialchars($row['setspec'], ENT_NOQUOTES) . '] ' . htmlspecialchars($row['setname'], ENT_NOQUOTES) . '</a></li>';
      }
      $html[] = '</ul>';
      $html[] = '
<form name="create" class="oai">
  <input name="setspec" required="required" pattern="[a-z\:_\-]{3,20}" placeholder="&lt;setSpec&gt; code" title="&lt;setSpec&gt; Code de la collection, lettres minuscules sans accent, possibilité de séparateur ‘-’ ‘_’" class="text" size="10"/>
  <button name="new" value="1">Créer</button>
</form>
      ';
      print(implode("\n", $html));
      self::formpublic();
      return;
    }

    // à partir d’ici un set est demandé
    self::$stmt['selset']->execute(array($setspec));
    $set = self::$stmt['selset']->fetch( PDO::FETCH_NUM);

    if (preg_match('@[^a-z:_\-]@', $setspec)) { // setspec invalide
      $html[] = '<div class="error">“' . $setspec . '” contient des caractères invalides, un code de collection &lt;setSpec&gt; ne contient que des lettres minuscules non accentuées (et éventuellement le caractère deux points ‘:’)</div>';
      $setspec = false;
    }
    else if(isset($_POST['delete']) && $_POST['delete']) { // demande de suppression
      if (!$set) {
        $html[] = '<div class="error">Impossible de supprimer la collection “' . $setspec . '”, elle n’existe pas encore.</div>';
      }
      else {
        $stmt = self::$pdo->prepare("DELETE FROM oaiset WHERE setspec = ?");
        $stmt->execute(array($setspec));
        self::$vacuum = true;
        $html[] = '<div class="message">La collection “' . $setspec . '” a été supprimée, avec toutes les notices qui en dépendent. Il est encore possible de recréer la notice, le formulaire a été pré-rempli (mais il faudra recharger les données).</div>';
      }
    }
    else if (isset($_POST['modify']) && $_POST['modify']) { // demande de modification
      $err = false;
      if (!$setname && ($err = true)) $html[] = '<div class="error">&lt;setName&gt; quel est le nom court de collection ?</div>';
      if (!$publisher && ($err = true)) $html[] = '<div class="error">&lt;publisher&gt; qui est éditeur (responsable du contenu) de l’organisation ?</div>';
      if (!$identifier && ($err = true)) $html[] = '<div class="error">&lt;identifier&gt; où (URI, adresse) trouver la collection sur Internet ?</div>';
      if (!$title) $html[] = '<div class="error">&lt;dc:title&gt; quel est le titre de la collection ?</div>';
      $oai = array();
      $oai[] = '<set
xmlns="http://www.openarchives.org/OAI/2.0/"
xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"
xmlns:dc="http://purl.org/dc/elements/1.1/"
>';
      $oai[] = '  <setSpec>' . htmlspecialchars($setspec, ENT_NOQUOTES) . '</setSpec>';
      $oai[] = '  <setName>' . htmlspecialchars($setname, ENT_NOQUOTES) . '</setName>';
      $oai[] = '  <setDescription>';
      $oai[] = '    <oai_dc:dc>';
      $oai[] = '      <dc:publisher>' . htmlspecialchars($publisher, ENT_NOQUOTES) . '</dc:publisher>';
      $oai[] = '      <dc:identifier>' . htmlspecialchars($identifier, ENT_NOQUOTES) . '</dc:identifier>';
      if (trim($title)) $oai[] = '      <dc:title>' . htmlspecialchars($title, ENT_NOQUOTES) . '</dc:title>';
      if (trim($description)) $oai[] = '      <dc:description>' . htmlspecialchars($description, ENT_NOQUOTES) . '</dc:description>';
      /*
      if (trim($subject)) {
        for (
      }
      */
      if (trim($sitemaptei)) $oai[] = '      <dc:relation scheme="sitemaptei">' . htmlspecialchars($sitemaptei, ENT_NOQUOTES) . '</dc:relation>';
      $oai[] = '    </oai_dc:dc>';
      $oai[] = '  </setDescription>';
      $oai[] = '</set>';
      $oai = implode("\n", $oai);
      if ($err); // ne rien faire
      else if ($set){ // remplacement
        $stmt=self::$pdo->prepare(
        'UPDATE oaiset SET setspec = ?, setname = ?, publisher = ?, identifier = ?, title = ?, description = ?, sitemaptei = ?, oai = ? WHERE rowid = ?'
        );
        $stmt->execute(array($setspec, $setname, $publisher, $identifier, $title, $description, $sitemaptei, $oai, $set[0]));
        $html[] = '<div class="message">La fiche de la collection “' . $setspec . '” a été modifiée (pour actualiser les notices OAI, recharger la source de données)</div>';
      }
      else { // insertion
        $stmt=self::$pdo->prepare(
        'INSERT INTO oaiset (setspec, setname, publisher, identifier, title, description, sitemaptei, oai)
                     VALUES (?,       ?,       ?,         ?,          ?,     ?,           ?,          ?);'
        );
        $stmt->execute(array($setspec, $setname, $publisher, $identifier, $title, $description, $sitemaptei, $oai));
        $html[] = '<div class="message">La collection “' . $setspec . '” a été ajoutée.</div>';
        if (!$sitemaptei) $html[] = '<div class="error">[sitemaptei] Aucune source de données n’a été indiquée pour charger des notices.</div>';
        else $html[] = '<div class="message">Pour charger des notices dans cette collection, cliquer le bouton Charger</div>';
        $set =true; // permettre d’afficher le bouton de chargement
      }
    }
    else if($set) { // afficher un set chargé depuis la base
      list($rowid, $setspec, $setname, $publisher, $identifier, $title, $description, $sitemaptei) = $set;
    }
    $html[] = '
<form name="set" id="set" class="oai" method="post" action="?">
  <div style="clear: both">
    <input style="float: left" name="setspec" required="required" placeholder="&lt;setSpec&gt; code" title="&lt;setSpec&gt; Code de la collection" class="text" readonly="readonly" size="10"  value="' . htmlspecialchars($setspec) . '"/>
    <input style="float: right" name="setname" required="required" placeholder="&lt;setName&gt; Nom court" title="&lt;setName&gt; Nom court de la collection"  class="text" size="25" value="' . htmlspecialchars($setname) . '"/>
  </div>
  <input style="width: 100%" name="publisher" required="required" placeholder="&lt;dc:publisher&gt; Éditeur de la collection" title="&lt;dc:publisher&gt; Organisation responsable de la collection" class="text" size="60" value="' . htmlspecialchars($publisher) . '"/>
  <input style="width: 100%" name="identifier" required="required" placeholder="&lt;dc:identifier&gt; Site de la collection (URI)" title="&lt;dc:identifier&gt; Lien vers la page d’accueil de la collection" class="text" size="60" value="' . htmlspecialchars($identifier) . '"/>
  <br/><input style="width: 100%" name="title" placeholder="&lt;dc:title&gt; Titre" title="&lt;dc:title&gt; Titre pour la  collection (1 ligne)"  class="text" size="60" value="' . htmlspecialchars($title) . '"/>
  <br/><textarea style="width: 100%" name="description" placeholder="&lt;dc:description&gt; Description" title="&lt;dc:description&gt; Description de la collection (quelques lignes)" cols="60" rows="4">' . htmlspecialchars($description) . '</textarea>
  <br/><input style="width: 100%" name="sitemaptei" placeholder=" Sitemap TEI (URI)" title="[sitemaptei] Lien vers une liste de fichiers TEI en ligne pour la collection (liste au format sitemaps.org)" class="text" size="60" value="' . htmlspecialchars($sitemaptei) . '"/>
  <div style="clear:both; text-align: center; margin: 1em 0 0 0;">
    <button style="float: left;" name="delete" value="1" type="submit" title="Supprimer la collection, avec toutes les notices OAI qui en dépendent">Supprimer</button>';
  if ( $set ) $html[] = '
    <button name="load" value="1" title="Charger les notices OAI depuis la source de données" type="submit">Charger</button>
    <button style="float: right;" name="modify" value="1" title="Modifier la fiche de la collection (sans affecter les notices OAI)" type="submit">Modifier</button>';
  else $html[] = '
<button style="float: right;" name="modify" value="1" title="Créer la fiche de la collection" type="submit">Créer</button>';
  $html[] = '</div>
</form>
    ';
    print(implode("\n", $html));
    if( isset($_POST['load']) && $_POST['load'] ) { // chargement de notices
      // créer d
      if ( !$set ) {
        echo "<p>La fiche de la collection n'a pas encore été créée, les notices ne seront pas chargées.</p>";
      }
      else {
        self::sqlitepre(); // nécessaire
        self::sitemaptei($sitemaptei, $setspec);
        self::sqlitepost(); // pareil
      }
    }

  }
  /**
   * obtenir le nom d’un fichier téléchargé
   */
  public static function upload()
  {
    if (!count($_FILES)) return;
    $ret = array();
    reset($_FILES);
    $tmp=current($_FILES);
    if($tmp['tmp_name']) {
      $ret['file'] = $tmp['tmp_name'];
      if ($tmp['name']) $ret['name'] = substr($tmp['name'], 0, strrpos($tmp['name'], '.'));
      return $ret;
    }
    else if($tmp['name']){
      echo $tmp['name'],' seems bigger than allowed size for upload in your php.ini : upload_max_filesize=',ini_get('upload_max_filesize'),', post_max_size=',ini_get('post_max_size');
      return false;
    }
  }

  /**
   * Chargement d’un set avec lien sur un sitemap.xml
   *  — charger la notice de set oai en base sqlite
   *  — charger le sitemap.xml
   *  — boucler sur les <url>
   *  — transformer un TEI en notice DC-OAI
   *  — charger la notice DC-OAI en base
   */
  public static function set2sqlite( $sqlitefile, $sets )
  {
    self::connect( $sqlitefile );

    self::$stmt['setins']=self::$pdo->prepare(
    'INSERT INTO oaiset (setspec, setname, identifier, description, sitemap, oai, image)
                 VALUES (?,       ?,       ?,          ?,           ?,       ?,   ?);'
    );
    if (!is_array($sets)) $sets = array($sets);
    foreach ($sets as $setpath) {
      if (strpos($setpath, '*') !== false) {
        foreach (glob($setpath) as $setfile) Weboai::setload($setfile);
      }
      else Weboai::setload($setpath);
    }
  }

  /**
   * Charger une seule déclaration de set, appeler par sets ci-dessus
   */
  private static function setload( $setfile )
  {
    self::log("Load set file $setfile");
    $xml = file_get_contents($setfile);
    $doc = new DOMDocument();
    $doc->loadXML($xml, LIBXML_NOWARNING);
    // valider ?
    $setspec = $doc->getElementsByTagNameNS('http://www.openarchives.org/OAI/2.0/', 'setSpec')->item(0)->textContent;
    $setname = $doc->getElementsByTagNameNS('http://www.openarchives.org/OAI/2.0/', 'setName')->item(0)->textContent;
    $identifier = $doc->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'identifier')->item(0)->textContent;
    // requis ?
    $descns = $doc->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'description');
    if ($descns->length) $description = $descns->item(0)->textContent;
    else $description = null;
    $sourcelist = $doc->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'source');
    if ($sourcelist->length) $source = $sourcelist->item(0)->textContent;
    else $source = null;
    $xml = preg_replace('@<\?[^\n]*\?>@', '', $xml);
    // en cas de remplacement de set (même setspec), des triggers s’occupent de supprimer les records qui en dépendent
    // ICI UNE ERREUR DE LOCK SQLITE
    self::$stmt['setins']->execute(array($setspec, $setname, $identifier, $description, $source, $xml, null));
    // pas de sitemap à parser, sortir.
    if (!$source) return;
    $reader = new XMLReader();
    $reader->open($source);

  }
  /**
   * Traitement d’un sitemap TEI
   */
  public static function sitemaptei( $url, $setspec=null ) {
    $baseurl = dirname( $url )."/";
    $reader = new XMLReader();
    if (!@$reader->open($url)) {
      $reader->close();
      echo "<p class=\"error\">Impossible d’ouvrir $url</p>\n";
      return;
    }
    // supprimer les enregistrements du setSpec avant d’en ajouter d’autres, conserver la notice
    if ( $setspec ) {
      $selset = self::$pdo->prepare("SELECT rowid FROM oaiset WHERE setspec = ? ");
      $selset->execute(array($setspec));
      $setrowid = $selset->fetchColumn();
      $selmember = self::$pdo->prepare("SELECT record FROM member WHERE oaiset = ?");
      $selmember->execute(array($setrowid));
      $delrecord = self::$pdo->prepare("DELETE FROM record WHERE rowid = ?");
      while ($member = $selmember->fetch()) {
        $delrecord->execute(array($member['record']));
      }
      self::$vacuum = true;
    }
    self::$duplicate = array();
    echo '
<style type="text/css">
textarea.xml { width: 100%; border: none; }
</style>
<table class="sortable sets">
  <caption>[' . $setspec . '] ' . $url . '</caption>
  <thead>
    <th title="Pour voir la source OAI/XML, cliquer l’identifiant">Identifiant</th>
    <th title="&lt;dc:title&gt; /TEI/teiHeader/fileDesc/titleStmt/title">Titre</th>
    <th title="&lt;dc:creator&gt; /TEI/teiHeader/fileDesc/titleStmt/author">Auteur</th>
    <th title="&lt;dc:date&gt; /TEI/teiHeader/profileDesc/creation/date">Date</th>
    <th title="&lt;dc:publisher&gt; /TEI/teiHeader/fileDesc/publicationStmt/publisher">Éditeur</th>
  </thead>
    ';
    while($reader->read()) {
      if ($reader->nodeType == XMLReader::ELEMENT && $reader->name == 'loc') {
        $teiurl = $reader->expand()->textContent;
        // relative URI
        if ( strpos( $teiurl, "http" ) !== 0 ) $teiurl = $baseurl.$teiurl;
        $weboai = new Weboai( $teiurl );
        // loading error
        if ( !$weboai->doc->textContent ) {
          echo '<tr><td colspan="5"><b class="error">'.$teiurl.' IMPOSSIBLE À CHARGER</b></td></tr>';
        }
        else {
          $weboai->tei2tr( $setspec );
        }
      }
    }
    echo "\n</table>";
    $reader->close();
    echo "\n<p>Traitement terminé du set “" . $setspec . '” ' . $url . '</p>';
  }
  /**
   * Traitement d’un fichier TEI
   */
  public function tei2tr( $setspec=false )
  {
    if ( !$setspec ) $setspec='&lt;setSpec&gt;';
    $oai_identifier = 'oai:' . self::$conf['domain'] . ':' . $setspec . ':' . $this->srcfilename;
    $duplicate= '';
    if ( isset(self::$duplicate[$oai_identifier]) ) $duplicate = ' <div class="error">DOUBLON</div>';
    self::$duplicate[$oai_identifier] = 1;
    $th = '<a href="#" title="Voir la source OAI" onclick="div=document.getElementById(\'' . $oai_identifier . '\'); if (div.style.display == \'none\') {div.style.display = \'\';} else {div.style.display = \'none\'}; return false; ">' . $oai_identifier  . ' ▶</a>' . $duplicate;
    // mal chargé
    if (!$this->doc) {
      echo "
<tr>
  <th>$th</th>
  <td colspan=\"5\"><p class=\"error\">FICHIER NON TROUVÉ {$this->srcurl}</p></td>
</tr>
";
      return false;
    }

    $oaidoc = $this->tei2oai();


    $nl = $oaidoc->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'title');
    if($nl->length) $title = $head = $nl->item(0)->nodeValue;
    else $head = '<b class="error">TITRE NON TROUVÉ &lt;dc:title&gt; /TEI/teiHeader/fileDesc/titleStmt/title</b>';

    $identifier = null;
    $nl = $oaidoc->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'identifier');
    if ($nl->length) $identifier = $nl->item(0)->nodeValue;
    if (isset($identifier)) $head = '<a href="' . $identifier . '">' . $head . '</a>';
    else $head = $head . ' <div class="error">LIEN NON TROUVÉ &lt;dc:identifier&gt; /TEI/teiHeader/fileDesc/publicationStmt/idno</div>';

    $creator = '';
    $byline='';
    $nl = $oaidoc->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'creator');
    if($nl->length) {
      $creator = $nl->item(0)->nodeValue;
      $sep='';
      for ($i =0; $i < $nl->length; $i++ ) {
        $byline .= $sep . $nl->item($i)->nodeValue;
        $sep=' ; ';
      }
    }

    $nl = $oaidoc->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'date');
    if($nl->length) $date = $dateline = $nl->item(0)->nodeValue;
    else $dateline = '<b class="error">DATE NON TROUVÉE &lt;dc:date&gt; /TEI/teiHeader/profileDesc/creation/date</b>';

    $publisher = '';
    $nl = $oaidoc->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'publisher');
    if($nl->length)  {
      $org = '<a href="' . $this->srcurl . '">' . $nl->item(0)->nodeValue. '</a>';
      $sep = '';
      for ($i =0; $i < $nl->length; $i++ ) {
        $publisher .= $sep . $nl->item($i)->nodeValue;
        $sep=' ; ';
      }
    }
    else $org = '<b class="error">ÉDITEUR NON TROUVÉ &lt;dc:publisher&gt; /TEI/teiHeader/fileDesc/publicationStmt/publisher</b>';

    echo "
<tr>
  <td>$th</td>
  <td>$head</td>
  <td>$byline</td>
  <td>$dateline</td>
  <td>$org</td>
</tr>
";

    echo '<tr id="' . $oai_identifier . '" class="xml" style="display: none"><td colspan="5"><textarea class="xml" cols="80" rows="10">' . $oaidoc->saveXML() . '</textarea></td></tr>';


    // à partir d’ici, insertion sqlite ou pas ?
    if (!isset(self::$stmt['ins_record'])) return;

    /*
    notice issue du TEI, ne montre pas exactement le contenu de l’OAI (dont les des)
    $this->xsl->load(dirname(dirname(__FILE__)) . '/transform/teiHeader2html.xsl');
    $this->proc->importStylesheet($this->xsl);
    $html = $this->proc->transformToXML($this->doc);
    $html = preg_replace('@\s*<\?[^\n]*\?>\s*@', '', $html);
    */
    $this->xsl->load(dirname(dirname(__FILE__)) . '/transform/oai2html.xsl');
    $this->proc->importStylesheet($this->xsl);
    $html = $this->proc->transformToXML($oaidoc);

    $oai_datestamp  = date('Y-m-d\TH:i:s\Z');

    $date = NULL;
    $nl = $oaidoc->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'date');
    if ($nl->length) $date= $nl->item(0)->nodeValue;
    $date2 = NULL;
    $nl = $oaidoc->getElementsByTagNameNS('http://purl.org/dc/terms/', 'dateCopyrighted');
    if ($nl->length) $date2 = $nl->item(0)->nodeValue;
    $issued = NULL;
    $nl=$oaidoc->getElementsByTagNameNS('http://purl.org/dc/terms/', 'issued');
    if ($nl->length) $issued = $nl->item(0)->nodeValue;
    $oaidoc->formatOutput = true;
    $oai = $oaidoc->saveXML();

    $oai = preg_replace('@\s*<\?[^\n]*\?>\s*@', '', $oai);
    // Attention, le tei contient plus que le <teiHeader>
    // $teiheader = $this->doc->saveXML();

    // (oai_datestamp, oai_identifier, title, identifier, byline, date, date2, issued, oai, html, tei)
    self::$stmt['ins_record']->execute(array(
      $oai_datestamp,
      $oai_identifier,
      $title,
      $identifier,
      $byline,
      $date,
      $date2,
      $publisher,
      $issued,
      $oai,
      $html,
      // $teiheader,
    ));
    // garder en mémoire l’identifiant du record OAI (pour insertion en table de relation)
    self::$pars['record_rowid']=self::$pdo->lastInsertId();
    // full text version
    $heading = (($byline)?$byline.'. ':'') . $title;
    $description = $heading;
    $descList = $oaidoc->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'description');
    for ($i =0; $i < $descList->length; $i++ ) {
      $description .= "\n\n".$descList->item($i)->nodeValue;
    }
    self::$stmt['ins_ft']->execute(array(
      self::$pars['record_rowid'],
      $heading,
      $heading.(($description)?"\n".$description:''),
    ));

    self::$stmt['ins_member']->execute(array(self::$pars['record_rowid'], $setspec));
    /* ajouter un set de langue ?
    $nl = $oaidoc->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'language');
    for ($i =0; $i < $nl->length; $i++ ) {
      $value=$nl->item($i)->nodeValue;
      $value = strtolower($value);
      if (isset(self::$setlang[$value])) {
        self::$stmt['ins_member']->execute(array(self::$pars['record_rowid'], self::$setlang[$value]));
      }
      else if ($i>1) {
        self::$stmt['ins_member']->execute(array(self::$pars['record_rowid'], 'lang:xx'));
      }
    }
    */


    // insertions
    foreach($oaidoc->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'creator') as $creator) {
      self::ins_author($creator->nodeValue, 1);// arg2, 1=creator
    }
    foreach($oaidoc->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'contributor') as $contributor) {
      self::ins_author($contributor->nodeValue, 2);// arg2, 2=contributor
    }
  }

  /**
   * Schematron compilation (file.sch chargé en DOM ds $this->doc)
   * TODO : tester .sch en input et la validité du fichier
   * TODO : test des droits en doPost()
   */
  public function sch2xsl() {
    //if (!$doc) $doc=$this->srcurl;
    /* step1, 2, 3 : see : https://code.google.com/p/schematron/wiki/RunningSchematronWithGNOMExsltproc */
    //step1
    $this->xsl->load(dirname(dirname(__FILE__)) . '/iso-schematron-xslt1/iso_dsdl_include.xsl');
    $this->proc->importStylesheet($this->xsl);
    $step1 = $this->proc->transformToDoc($this->doc);
    //step2
    $this->xsl->load(dirname(dirname(__FILE__)) . '/iso-schematron-xslt1/iso_abstract_expand.xsl');
    $this->proc->importStylesheet($this->xsl);
    $step2 = $this->proc->transformToDoc($step1);
    //step3
    $this->xsl->load(dirname(dirname(__FILE__)) . '/iso-schematron-xslt1/iso_svrl_for_xslt1.xsl');
    $this->proc->importStylesheet($this->xsl);
    $xslValidator = $this->proc->transformToDoc($step2);
    // TODO : une petite méthode pour gérer les $dest + chown :www-data ?
    // [FG] cache folder should be configurable, with a fallback on the tmp folder, or recreation as a class field if nothing better
    if (!file_exists('./out/')) {
      mkdir('out/', 0755, true);
      @chmod('out/', 0755); // @, write permission for www-data
    }
    $xslValidator->save('out/teiHeader_validator.xsl');// mise à jour XSL de validation
    echo "./out/teiHeader_validator.xsl updated\n";
    $validator = file_get_contents('out/teiHeader_validator.xsl');
    echo $validator;
    //echo $this->xml2html($validator); //HTML display of XSLT schematron file
  }

  /**
   * Shematron (XSL) validation of $this->doc
   */
  public function xmlValidation($xmlValidator='/out/teiHeader_validator.xsl') {
    $validation=null;
    $this->xsl->load( dirname(__FILE__) . $xmlValidator );
    $this->proc->importStylesheet($this->xsl);
    $this->proc->setParameter('axsl', 'fileNameParameter', $this->srcfilename);
    $svrl_report = $this->proc->transformToDoc($this->doc);
    $failed_assert_nodes = $svrl_report->getElementsByTagNameNS('http://purl.oclc.org/dsdl/svrl','failed-assert');
    //($failed_assert_nodes->length==0) ? $validation=true : $validation=false;
    if($failed_assert_nodes->length==0) $validation=true;
    else {
      $validation=false;
      $report=$this->proc->transformToXML($this->doc);//SVRL format
      //$report=$this->xml2html($report);//HTML format
      echo $report;
    }
    return $validation;
  }

  /**
   * OAI conversion
   */
  public function tei2oai()
  {
    $this->xsl->load( dirname(dirname(__FILE__) ) . '/transform/tei2oai.xsl');
    $this->proc->importStylesheet($this->xsl);
    $this->proc->setParameter(null, 'filename', $this->srcfilename);
    $doc = $this->proc->transformToDoc($this->doc);
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = true;
    return $doc;
  }

  /**
   * HTML conversion of XML string for navigator display -- doPost() context.
   */
  public function xml2html($xml) {
    $xmlDOM = new DOMDocument();
    $xmlDOM->loadXML($xml);// oai as DOM
    // [FG] hey! we get it in our projects http://svn.code.sf.net/p/algone/code/teipub/xml2html.xsl
    // What should be added to make it works well for you?
    $this->xsl->load( dirname(dirname(__FILE__) ) . '/transform/verbid.xsl');
    $this->proc->importStylesheet($this->xsl);
    return $this->proc->transformToXML($xmlDOM);
  }
  /**
   * Préparer l’indexation dans sqlite
   */
  private static function sqlitepre() {
    // reconnecter, pour préparer les requêtes
    self::connect();
// send some pragmas before work
    self::$pdo->exec("
-- triggers ON CONFLICT
PRAGMA recursive_triggers = TRUE;
-- Optimize conf for import
PRAGMA locking_mode=EXCLUSIVE;
PRAGMA synchronous=OFF;
PRAGMA default_cache_size=10000;
PRAGMA page_size=8192;
PRAGMA journal_mode=MEMORY;
PRAGMA count_changes=OFF;
-- PRAGMA foreign_keys=ON; -- perfs ? pbs relationnels !
PRAGMA temp_store=MEMORY;
PRAGMA temp_store = 2; -- memory temp table
    ");
    self::$vacuum = false;
    self::$stmt['ins_record']=self::$pdo->prepare("
      INSERT OR REPLACE INTO record (oai_datestamp, oai_identifier, title, identifier, byline, date, date2, publisher, issued, oai, html)
                             VALUES (?,             ?,              ?,     ?,     ?,          ?,      ?,    ?,         ?,      ?,   ?)
      ;
    ");
    self::$stmt['ins_ft']=self::$pdo->prepare("
      INSERT OR REPLACE INTO ft (docid, heading, description)
                         VALUES (?,     ?,       ?)
      ;
    ");
    self::$stmt['ins_author']=self::$pdo->prepare("
      INSERT INTO author (heading, family, given, sort, sort1, sort2, birth, death, uri)
                  VALUES (?,       ?,      ?,     ?,    ?,     ?,     ?,     ?,     ?);
    ");
    self::$stmt['ins_writes']=self::$pdo->prepare("
      INSERT INTO writes (author, record, role)
                  VALUES (?,      ?,        ?);
    ");
    self::$stmt['sel_author_rowid']=self::$pdo->prepare("
      SELECT rowid FROM author WHERE sort1 = ? AND sort2 = ? AND birth = ? AND death = ?;
    ");
    self::$stmt['sel_author_rowid2']=self::$pdo->prepare("
      SELECT rowid FROM author WHERE sort1 = ? AND sort2 = ?;
    ");
    self::$stmt['ins_member']=self::$pdo->prepare("
      INSERT INTO member (record, oaiset) SELECT ?, oaiset.rowid FROM oaiset WHERE setspec=?
    ");

    //start transaction ?
    self::$pdo->beginTransaction();

  }
  /**
   * Finir l’indexation de notices dans sqlite
   */
  public static function sqlitepost() {
    self::$pdo->commit();
    // pas de VACUUM possible maintenant, transaction pas finie, ou lock si on veut reconnecter
    // imaginer un compteur ?
  }

  /**
   * called on dc:creator and dc:contributor
   *
   * Montaigne, Françoise de (153.?-....)
   * Bernard de Clairvaux (saint ; 1090?-1153)
   */
  public static function ins_author($text, $role=NULL, $url=NULL) {
    // bug ?
    if (!$text) return;
    $heading=$text;
    $text=strtr(trim($text),array('…'=>'...', ' '=>' '));  // unbreakable space before ';'
    if(strpos($text,'(')) {
      $dates=substr($text, strpos($text,'(')+1);
      $dates=trim(substr($dates, 0, strpos($dates+')',')')-1));
      if(strpos($text,';'))  $dates=trim(substr($dates, strpos($dates ,';')+1));
      $names=trim(substr($text, 0, strpos($text,'(')));
    }
    else {
      $names=$text;
      $dates="";
    }
    $family=$given=$birth=$death="";
    if (($pos=strpos($names, ',')) !== false) {
      $family=trim(substr($names, 0, $pos));
      $given=trim(substr($names, $pos+1));
    }
    else $family=$names;
    // if a wild value, try to separate $names an convert case ?
    /*
    if ($text && !$given && $pos=strpos($family, ' ')) {
      $given=trim(substr($family,0,$pos));
      $family=trim(substr($family,$pos));
    }
     // a wild value, try to convert case
    if ($text) {
      // if uppercase, convert case,
      if ($family==mb_convert_case($family, MB_CASE_UPPER, "UTF-8")) $family=mb_convert_case($family, MB_CASE_TITLE, "UTF-8");
      // keep things like "Henri de", or "T.H.L", "J.-C."
      if ($given==mb_convert_case($given, MB_CASE_UPPER, "UTF-8") && preg_match('/\p{Lu}\p{Lu}/', $given)) $given=mb_convert_case($given, MB_CASE_TITLE, "UTF-8");
    }
    */
    $sort1=strtr($family, self::$re['fr_sort_tr']);
    $sort2=strtr($given, self::$re['fr_sort_tr']);
    $birth=trim(substr($dates,0, strpos($dates, '-')));
    $death=trim(substr($dates, strpos($dates, '-')+1));
    if($death=='…' || $death=='...') $death='....';
    if (!$text) $heading=$key; // give key as is
    // no rebuild of an heading from fields, some info can be lost like (saint ; 1090?-1153)
    // $heading=$family.($given?(', '.$given):'').($birth?(' ('.$birth.'-'.$death.')'):'');
    if(!$url) $url=NULL;
    // dates should be int in sql field to be used
    if ($death=='....') $death=null;
    $birth=strtr($birth, '.', '0');
    $death=strtr($death, '.', '9');
    $birth=0+$birth;
    if (!$birth) $birth=null;
    $death=0+$death;
    if (!$death) $death=null;
    // ? negative dates ?
    self::$stmt['sel_author_rowid']->execute(array( $sort1, $sort2, $birth, $death ));
    $authorId=self::$stmt['sel_author_rowid']->fetchColumn();
    // be nice if no dates
    if (!$authorId && !$birth) {
      self::$stmt['sel_author_rowid2']->execute(array( $sort1, $sort2));
      $authorId=self::$stmt['sel_author_rowid2']->fetchColumn();
      // homonyms ? ALERT ?
      if ($authorId and self::$stmt['sel_author_rowid2']->fetchColumn()) $authorId=null;
    }
    if (!$authorId) {
      // (heading, family, given, sort, sort1, sort2, birth, death, uri)
      self::$stmt['ins_author']->execute(array(
        $heading, $family, $given, $sort1.$sort2, $sort1, $sort2, $birth, $death, $url
      ));
      // echo $text.' — '.$dates.' — '.$family.($given?(', '.$given):'').($birth?(' ('.$birth.'-'.$death.')'):'')."\n";
      $authorId=self::$pdo->lastInsertId();
    }
    $record_rowid=self::$pars['record_rowid'];
    self::$stmt['ins_writes']->execute(array($authorId, $record_rowid, $role));
  }
  /**
   * Connect to database
   */
  public static function connect() {
    if (self::$pdo) return; // no way found to prevent lock, do not reopen connection
    // create database
    if (!file_exists( self::$conf['sqlite'] )) {
      if (!file_exists($dir = dirname( self::$conf['sqlite'] ))) {
        mkdir( $dir, 0775, true );
        @chmod( $dir, 0775 );
      }
      self::$pdo=new PDO("sqlite:" . self::$conf['sqlite'] );
      @chmod( self::$conf['sqlite'], 0775 );
      self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
      self::$pdo->exec(file_get_contents(dirname(__FILE__).'/weboai.sql'));
    } else {
      self::$pdo=new PDO("sqlite:" . self::$conf['sqlite'] );
      self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
    }

  }

  /**
   * load a csv key=>val table
   */
  static function dic( $file ) {
    $dic = array();
    $handle = fopen( $file, "r" );
    fgetcsv( $handle, 0, ";" ); // passer la première ligne
    while ( ($row = fgetcsv($handle, 0, ";") ) !== FALSE) { // lire toutes les autres lignes
      if ( count( $row ) < 2 ) continue; // ligne vide, incomplète ou non reconnue
      $dic[$row[0]] = $row[1]; // remplir le tableau de graphies, avec pour clé la première cellule, et pour valeur la deuxième
    }
    fclose($handle);
    return $dic;
  }

  public static function doPost() {
    // a file seems uploaded
    $filename="";
    if(count($_FILES)) {
      reset($_FILES);
      $tmp=current($_FILES);
      if($tmp['tmp_name']) {
        $src=$tmp['tmp_name'];
        if ($tmp['name']) $filename=substr($tmp['name'], 0, strrpos($tmp['name'], '.'));
      }
      else if($tmp['name']){
        echo $tmp['name'],' seems bigger than allowed size for upload in your php.ini : upload_max_filesize=',ini_get('upload_max_filesize'),', post_max_size=',ini_get('post_max_size');
        return false;
      }
      else return;
    } else {
      echo "No file ?";
    }
    // store the file submitted as a memory of activity
    if (is_writable($cache=dirname(__FILE__).'/cache/') && $tmp['name']) @copy($src, $cache.$tmp['name']);
    // IF SCH/XML/ZIP
    if(pathinfo($tmp['name'], PATHINFO_EXTENSION) == 'sch') {
      $weboai=new Weboai($src);
      echo $weboai->sch2xsl();
      exit;
    }
    elseif(pathinfo($tmp['name'], PATHINFO_EXTENSION) == 'xml') {
      $weboai=new Weboai($src);
      // renvoyer le nom du fichier chargé (TODO trop fragile, à revoir...)
      $weboai->srcfilename = $filename;
      // aiguillage : sch compilation, validation, sqlite load
      if(isset($_POST['validation'])) echo $weboai->xmlValidation();
      if(isset($_POST['sqlite'])) $weboai->sqlite($src,'weboai.sqlite');
      exit;
    }
    elseif(pathinfo($tmp['name'], PATHINFO_EXTENSION) == 'zip') {
      $weboai=new Weboai($file);
      echo $weboai->zipParse();
      exit;
    }
  }

  /**
   * Command line interface for Weboai
   * php -f Weboai.php sch2xsl file.sch [dir/file.xsl]
   * php -f Weboai.php validation file.tei
   * php -f Weboai.php tei2oai file.tei
   * php -f Webaoi.php sqlite file.oai
   */
  public static function docli() {
    $timeStart = microtime(true);
    array_shift($_SERVER['argv']); // shift arg 1, the script filepath
    $ops = "(sch2xsl|sets|tei2oai|validation)";
    if (!count($_SERVER['argv'])) exit("usage : php -f Weboai.php $ops dest.sqlite? (src.xml)+|dir/\n");
    $method=null; // method to call
    $src=null;//XML src
    $srcfilename=null;
    $dest=null;
    while ($arg=array_shift($_SERVER['argv'])) {
      // method
      if (!preg_match('@^' . $ops . '$@', $arg)) continue;
      $method=$arg;
      break;
    }
    echo "$method $src $dest\n";
    switch ($method) {
      case "sch2xsl":
        $weboai = new Weboai($_SERVER['argv'][0]);
        $weboai->sch2xsl();
        echo "$src compiled\n";
        break;
      case "validation":
        if (is_dir($_SERVER['argv'][0])) {
          foreach(glob($_SERVER['argv'][0] . '/*.xml') as $xml) {
            $weboai = new Weboai($xml);
            echo "===============\n$weboai->srcfilename\n===============\n";
            $weboai->xmlValidation();
          }
        }
        else {
          $weboai = new Weboai($_SERVER['argv'][0]);
          echo "===============\n$weboai->srcfilename\n===============\n";
          $weboai->xmlValidation();
          echo "\n";
        }
        break;
      case "tei2oai":
        if (is_dir($_SERVER['argv'][0])) {
          foreach(glob($_SERVER['argv'][0] . '/*.xml') as $xml) {
            $weboai = new Weboai($xml);
            echo "===============\n$weboai->srcfilename\n===============\n";
            echo $weboai->tei2oai()->saveXML();
          }
        }
        else {
          $weboai=new Weboai($_SERVER['argv'][0]);
          echo "===============\n$weboai->srcfilename\n===============\n";
          echo $weboai->tei2oai()->saveXML();
        }
        break;
      // load sets with a list of TEI files (all record should be in a set)
      case "sets":
        $sqlitefile = array_shift($_SERVER['argv']);
        Weboai::set2sqlite($sqlitefile, $_SERVER['argv']);
        break;

    }
  }

  /**
   * Load xml src as dom in $this->doc, with an error recorder
   * called by constructor
   * [FG] error handler is unplugged. Keep or strip?
   */
  private function load( $src ) {
    $src = trim( $src );
    libxml_clear_errors();
    //$oldError=set_error_handler(array($this,"err"), E_ALL);
    $this->doc = new DOMDocument("1.0", "UTF-8");
    $this->doc->preserveWhiteSpace = false; // if not set here, no indent possible for output
    $this->doc->formatOutput=true;
    $this->doc->substituteEntities=true;
    libxml_use_internal_errors(true);
    // LIBXML_NOWARNING to not output warning on @xml:id
    $this->doc->load( $src, LIBXML_NOENT | LIBXML_NSCLEAN | LIBXML_NOCDATA | LIBXML_NOWARNING );
    // si grosse erreur, supprimer le document
    foreach (libxml_get_errors() as $err) {
      if (is_object($err) && isset($err->level)) $level = $err->level;
      else if (is_array($err) && isset($err['level'])) $level = $err['level'];
      else continue;
      if ($level == LIBXML_ERR_WARNING) continue;
      $this->doc = false; // doc mal chargé
      break;
    }
    // TODO? append error report as comment to the doc ?
  }

}


?>
