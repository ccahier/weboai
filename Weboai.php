<?php
/**
 * Classe de chargement des notices pour exposition OAI
 */

//if($_SERVER['REQUEST_METHOD']=='POST') Weboai::doPost();

// cli usage
set_time_limit(-1);
if (php_sapi_name() == "cli") Weboai::docli();

class Weboai {
  static $debug; // debug mode
  private $srcuri; //path du fichier chargé
  private $srcfilename; // nom du fichier chargé
  private $doc; // DOM du XML en court de traitement
  private $xsl; // DOM d’xsl de transformation
  private $proc; // processeur xsl
  private static $log; // flux de log
  
  // chargement en base
  private static $sqlitefile; // chemin du fichier sql
  private static $pdo; // sqlite connection
  private static $stmt=array(); // store PDOStatement in array
  private static $pars=array(); // parameters (as record_rowid) for SQL
  public static $setlang = array( // liste des langue utilisée pour les sets
    'fr' => 'lang:fr',
    'fre' => 'lang:fr',
  ); // lang sets 
  public static $re=array();
  
  function __construct($srcuri) {
    $this->srcuri = $srcuri;
    $this->srcfilename = pathinfo($srcuri, PATHINFO_FILENAME);
    $this->xsl = new DOMDocument("1.0", "UTF-8");
    $this->proc = new XSLTProcessor();
    $this->load($srcuri);
    // json replace table
    self::$re['fr_sort_tr'] = self::json(dirname(__FILE__).'/lib/fr_sort.json');
  }
  
  /**
   * Signaler une erreur
   */
  public static function log($line) {
    if (!self::$log) self::$log = STDERR;
    fwrite (self::$log, $line . "\n");
  }
  /**
   * Chargement d’un set avec lien sur un sitemap.xml
   *  — charger la notice de set oai en base sqlite
   *  — charger le sitemap.xml
   *  — boucler sur les <url>
   *  — transformer un TEI en notice DC-OAI
   *  — charger la notice DC-OAI en base
   */
  public static function set2sqlite($sqlitefile, $sets) {
    self::connect($sqlitefile);
    
    self::$stmt['setins']=self::$pdo->prepare(
    'INSERT INTO oaiset (setspec, setname, identifier, description, sitemap, xml, image)
                 VALUES (?,       ?,       ?,          ?,           ?,       ?,   ?);'
    );
    if (!is_array($sets)) $sets = array($sets);
    foreach ($sets as $setpath) {
      if (strpos($setpath, '*') !== false) {
        foreach (glob($setpath) as $setfile) Weboai::loadset($setfile);
      }
      else Weboai::loadset($setpath);
    }
  }
  
  /**
   * Charger une seule déclaration de set, appeler par sets ci-dessus
   */
  private static function loadset($setfile) {
    self::log("Load set file $setfile");
    $xml = file_get_contents($setfile);
    $doc = new DOMDocument();
    $doc->loadXML($xml, LIBXML_NOWARNING);
    // valider ?
    $setspec = $doc->getElementsByTagNameNS('http://www.openarchives.org/OAI/2.0/', 'setSpec')->item(0)->textContent;
    $setname = $doc->getElementsByTagNameNS('http://www.openarchives.org/OAI/2.0/', 'setName')->item(0)->textContent;
    $identifier = $doc->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'identifier')->item(0)->textContent;
    $description = $doc->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'description')->item(0)->textContent;
    $sourcelist = $doc->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'source');
    if ($sourcelist->length) $source = $sourcelist->item(0)->textContent;
    else $source = null;
    $xml = preg_replace('@<\?[^\n]*\?>@', '', $xml);
    // en cas de remplacement de set (même setspec), des triggers s’occupent de supprimer les records qui en dépendent
    self::$stmt['setins']->execute(array($setspec, $setname, $identifier, $description, $source, $xml, null));
    // pas de sitemap à parser, sortir.
    if (!$source) return;
    $reader = new XMLReader();
    $reader->open($source);
    
    // préparer l’indexation de plusieurs notices
    self::sqlitepre();
    while($reader->read()) {
      if ($reader->nodeType == XMLReader::ELEMENT && $reader->name == 'loc') {
        $teiuri = $reader->expand()->textContent;
        echo "$teiuri\n";
        $weboai = new Weboai($teiuri);
        $weboai->tei2oai();
        $weboai->sqlite($setspec);
      }
    }
    $reader->close();
    // finir l’indexation de plusieurs notices
    self::sqlitepost();
  }
  
  /**
   * Schematron compilation (file.sch chargé en DOM ds $this->doc)
   * TODO : tester .sch en input et la validité du fichier
   * TODO : test des droits en doPost()
   */
  public function sch2xsl() {
    //if (!$doc) $doc=$this->srcuri;
    /* step1, 2, 3 : see : https://code.google.com/p/schematron/wiki/RunningSchematronWithGNOMExsltproc */
    //step1
    $this->xsl->load(dirname(__FILE__).'/'.'iso-schematron-xslt1/iso_dsdl_include.xsl');
    $this->proc->importStylesheet($this->xsl);
    $step1 = $this->proc->transformToDoc($this->doc);
    //step2
    $this->xsl->load(dirname(__FILE__).'/'.'iso-schematron-xslt1/iso_abstract_expand.xsl');
    $this->proc->importStylesheet($this->xsl);
    $step2 = $this->proc->transformToDoc($step1);
    //step3
    $this->xsl->load(dirname(__FILE__).'/'.'iso-schematron-xslt1/iso_svrl_for_xslt1.xsl');
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
    $this->xsl->load(dirname(__FILE__) . $xmlValidator);
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
  public function tei2oai() {
    //if ($this->xmlValidation()==false) exit("===================$this->srcfilename non valide======================\n");
    $this->xsl->load(dirname(__FILE__) . '/transform/tei2oai.xsl');
    $this->proc->importStylesheet($this->xsl);
    $this->proc->setParameter(null, 'filename', $this->srcfilename);
    $this->doc = $this->proc->transformToDoc($this->doc);
  }
  
  /**
   * HTML conversion of XML string for navigator display -- doPost() context.
   */
  public function xml2html($xml) {
    $xmlDOM = new DOMDocument();
    $xmlDOM->loadXML($xml);// oai as DOM
    // [FG] hey! we get it in our projects http://svn.code.sf.net/p/algone/code/teipub/xml2html.xsl 
    // What should be added to make it works well for you?
    $this->xsl->load(dirname(__FILE__) . '/transform/verbid.xsl'); 
    $this->proc->importStylesheet($this->xsl);
    return $this->proc->transformToXML($xmlDOM);
  }
  /**
   * Préparer l’indexation dans sqlite
   */
  private static function sqlitepre() {
    // reconnecter, pour préparer les requêtes
    self::connect();
    self::$stmt['ins_record']=self::$pdo->prepare("
      INSERT OR REPLACE INTO record (oai_datestamp, oai_identifier, xml, title, identifier, byline, date, date2, issued)
                             VALUES (?,             ?,              ?,   ?,     ?,          ?,      ?,    ?,     ?)
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
  }
  /**
   * Load an OAI record in the weboai SQLITE database
   * TODO : tester qu’on envoie bien OAI valide
   */
  public function sqlite($setspec) {
    $oai_identifier = $setspec . ':' . $this->srcfilename;
    $oai = $this->doc;
    // TODO, log error
    $oai_datestamp  = time();
    // title, just the first one
    $titlelist = $oai->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'title');
    if(!$titlelist->length) {
      self::log("$oai_identifier : ERROR NO dc:title" );
      return;
    }
    $title = $titlelist->item(0)->nodeValue;
    // prepare the byline
    $creatorList=$oai->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'creator');
    $byline=NULL;
    if ($length=$creatorList->length) {
      $byline='';
      $sep='';
      for ($i =0; $i < $length; $i++ ) {
        $byline.=$sep.$creatorList->item($i)->nodeValue;
        $sep=' ; ';
      }
    }
    // be nice or block ?
    $identifier=NULL;
    $idList=$oai->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'identifier');
    if ($idList->length) $identifier= $idList->item(0)->nodeValue;
    else {
      self::log("$oai_identifier : WARN NO dc:identifier" );
    }
    // be nice or block ?
    $date=NULL;
    $dateList=$oai->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'date');
    if ($dateList->length) $date= $dateList->item(0)->nodeValue;
    $date2=NULL;
    $dateList=$oai->getElementsByTagNameNS('http://purl.org/dc/terms/', 'dateCopyrighted');
    if ($dateList->length) $date2 = $dateList->item(0)->nodeValue;
    $issued=NULL;
    $dateList=$oai->getElementsByTagNameNS('http://purl.org/dc/terms/', 'issued');
    if ($dateList->length) $issued = $dateList->item(0)->nodeValue;
    $record=$oai->saveXML();
    // (oai_datestamp, oai_identifier, record, title, identifier, byline, date, date2, issued)
    self::$stmt['ins_record']->execute(array(
      $oai_datestamp,
      $oai_identifier,
      $record,
      $title,
      $identifier,
      $byline,
      $date,
      $date2,
      $issued
    ));
    // garder en mémoire l’identifiant du record OAI (pour insertion en table de relation)
    self::$pars['record_rowid']=self::$pdo->lastInsertId();
    // full text version
    $heading = (($byline)?$byline.'. ':'') . $title;
    $description = $heading;
    $descList = $oai->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'description');
    for ($i =0; $i < $descList->length; $i++ ) {
      $description .= "\n\n".$descList->item($i)->nodeValue;
    }
    self::$stmt['ins_ft']->execute(array(
      self::$pars['record_rowid'],
      $heading,
      $heading.(($description)?"\n".$description:''),
    ));
    
    // add the setSpecs to the OAI file
    // add language set
    $nodeList=$oai->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'language');
    for ($i =0; $i < $nodeList->length; $i++ ) {
      $value=$nodeList->item($i)->nodeValue;
      $value = strtolower($value);
      if (isset(self::$setlang[$value])) {
        self::$stmt['ins_member']->execute(array(self::$pars['record_rowid'], self::$setlang[$value]));
      }
      else if ($i>1) {
        self::$stmt['ins_member']->execute(array(self::$pars['record_rowid'], 'lang:xx'));
      }
    }
    self::$stmt['ins_member']->execute(array(self::$pars['record_rowid'], $setspec));

    // Shall we inform when replace ?
    /* [FG] No more used with INSERT OR REPLACE
    // id d’une notice déjà soumise pour mise à jour -- TODO régler la politique ID pour améliorer la clause WHERE
    self::$stmt['sel_record_rowid']=self::$pdo->prepare("
      SELECT id FROM record WHERE title= ?;
    ");
    if (substr(self::$stmt['insRecord']->errorCode(), 0, 2) == 23) {
      echo '<mark>' . $title . ' (notice déjà insérée, record.id=' . self::$pars['record_rowid'] . ')</mark>';      
      self::$stmt['sel_record_rowid']->execute(array($title));
      self::$pars['record_rowid']=self::$stmt['sel_record_rowid']->fetchColumn();
      // on sort pour l’instant -- TODO: proposer mise à jour de la notice
      exit;
    }
    */
    
    // insertions
    foreach($oai->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'creator') as $creator) {
      self::ins_author($creator->nodeValue, 1);// arg2, 1=creator
    }
    foreach($oai->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'contributor') as $contributor) {
      self::ins_author($contributor->nodeValue, 2);// arg2, 2=contributor
    }
  }
  
  /**
   * called on dc:creator and dc:contributor
   *
   * Montaigne, Françoise de (153.?-....)
   * Bernard de Clairvaux (saint ; 1090?-1153)
   */
  public static function ins_author($text, $role=NULL, $uri=NULL) {
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
    if(!$uri) $uri=NULL;
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
        $heading, $family, $given, $sort1.$sort2, $sort1, $sort2, $birth, $death, $uri
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
  function connect($sqlitefile=null) {
    if ($sqlitefile) self::$sqlitefile = $sqlitefile;
    self::$pdo = null;
    // create database
    if (!file_exists(self::$sqlitefile)) {
      if (!file_exists($dir = dirname(self::$sqlitefile))) {
        mkdir($dir, 0775, true);
        @chmod($dir, 0775);
      }
      self::$pdo=new PDO("sqlite:" . self::$sqlitefile);
      self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
      self::$pdo->exec(file_get_contents(dirname(__FILE__).'/weboai.sql'));
    } else {
      self::$pdo=new PDO("sqlite:" . self::$sqlitefile);
      self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
    }
    // send some pragmas before work
    self::$pdo->exec("PRAGMA recursive_triggers = TRUE;"); // triggers ON CONFLICT
  }

  /**
   * load a json resource as an array()
   */
  static function json($file) {
    $content=file_get_contents($file);
    $content=substr($content, strpos($content, '{'));
    $content= json_decode($content, true);
    switch (json_last_error()) {
      case JSON_ERROR_NONE:
      break;
      case JSON_ERROR_DEPTH:
        echo "$file — Maximum stack depth exceeded\n";
      break;
      case JSON_ERROR_STATE_MISMATCH:
        echo "$file — Underflow or the modes mismatch\n";
      break;
      case JSON_ERROR_CTRL_CHAR:
        echo "$file — Unexpected control character found\n";
      break;
      case JSON_ERROR_SYNTAX:
        echo "$file — Syntax error, malformed JSON\n";
      break;
      case JSON_ERROR_UTF8:
        echo "$file — Malformed UTF-8 characters, possibly incorrectly encoded\n";
      break;
      default:
        echo "$file — Unknown error\n";
      break;
    }
    return $content;
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
            echo "===============\n$weboai->srcfilename\n===============\n$oai";
            $weboai->xmlValidation();
          }
        }
        else {
          $weboai = new Weboai($_SERVER['argv'][0]);
          echo "===============\n$weboai->srcfilename\n===============\n$oai";
          $weboai->xmlValidation();
          echo "\n";
        }
        break;
      case "tei2oai":
        if (is_dir($_SERVER['argv'][0])) {
          foreach(glob($_SERVER['argv'][0] . '/*.xml') as $xml) {
            $weboai = new Weboai($xml);
            echo "===============\n$weboai->srcfilename\n===============\n$oai";
            $weboai->tei2oai();
            echo $this->src->saveXML();
          }
        }
        else {
          $weboai=new Weboai($_SERVER['argv'][0]);
          echo "===============\n$weboai->srcfilename\n===============\n$oai";
          echo $weboai->tei2oai();
          echo $this->src->saveXML();
        }
        break;
      // set2sqlite, load a set from a list of TEI files (all record should be in a set)
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
  private function load($src) {
    $this->message=array();
    //$oldError=set_error_handler(array($this,"err"), E_ALL);
    $this->doc = new DOMDocument("1.0", "UTF-8");
    $this->doc->recover=true;
    // if not set here, no indent possible for output
    $this->doc->preserveWhiteSpace = false;
    $this->doc->formatOutput=true;
    $this->doc->substituteEntities=true;

    // LIBXML_NOWARNING to not output warning on 
    $this->doc->load($src, LIBXML_NOENT | LIBXML_NSCLEAN | LIBXML_NOCDATA | LIBXML_COMPACT  | LIBXML_NOWARNING);
    restore_error_handler();
    if (count($this->message)) {
      $this->doc->appendChild($this->doc->createComment("Error recovered in loaded XML document \n". implode("\n", $this->message)."\n"));
    }
    $this->message=array();
  }
  
}


?>