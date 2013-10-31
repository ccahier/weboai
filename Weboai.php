<?php
//if($_SERVER['REQUEST_METHOD']=='POST') Weboai::doPost();

// cli usage
set_time_limit(-1);
if (php_sapi_name() == "cli") Weboai::doCli();

class Weboai {
  // conversions
  private $srcFile;//path du fichier chargé
  private $srcFileName;// nom du fichier chargé
  private $xsl;
  private $proc;
  private $doc;// DOM doc 
  /** Debug mode */
  static $debug; 
  
  // chargement en base
  private static $pdo;// sqlite connection
  private static $stmt=array();// store PDOStatement in array
  private static $pars=array();// parameters (as resourceID) for SQL

  public static $re=array(
    // normalize spaces and puctuation in html
    // . ‘
    // [FG] utilisé ?
    'punct'=>array(
      '@\.\.\.@' => '…',
      '@ ([»?!])@u' => ' $1',
      '@([\.?!…»])\s\s+@u' => '$1 ',
      '@( [tp]\.) ([IVX])@' => '$1 $2', // t. II
      '@­@' => '',
      '@<(blockquote|dd|div|dt|h1|h2|h3|h4|h5|h6|li|p|pre)( [^>]+)?>\s*@' => "\$0\n",
      '@\n +<@' => "\n<",
      '@</(blockquote|dd|div|dt|h1|h2|h3|h4|h5|h6|li|p|pre)>@' => "\n\$0",
      '@\n +\n@' => "\n\n",
    ),
    // [FG] utilisé ?
    's'=>array(
      '@(St).@u' => '$1&#46;', // protect non period dots
      '@( +)([.)][) »]*)@u' => '$2$1',
      '@([\p{Ll}>»]\.) ( *)(« |‘|"|“)?([\p{Lu}])@u' => "\$1\$2\n\$3\$4", // a. A… a. « A… f135b. ‘Or pour
      '@([\?\.\!…] ») ( *)([\p{Lu}«])@' => "\$1\$2\n\$3",
      '@([?!…]) ( *)([\p{Lu}«])@' => "\$1\$2\n\$3",
      '@&#46;@' => '.', //restore periods
    )
  );
  
  function __construct($srcFile) {
    $this->srcFile = $srcFile;
    $this->srcFileName = basename($srcFile);
    $this->xsl = new DOMDocument("1.0", "UTF-8");
    $this->proc = new XSLTProcessor();
    $this->load($srcFile);
  }
  
  /**
   * Schematron compilation (file.sch chargé en DOM ds $this->doc)
   * TODO : tester .sch en input et la validité du fichier
   * TODO : test des droits en doPost()
   */
  public function sch2xsl() {
    //if (!$doc) $doc=$this->srcFile;
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
    $this->proc->setParameter('axsl', 'fileNameParameter', $this->srcFileName);
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
    //if ($this->xmlValidation()==false) exit("===================$this->srcFileName non valide======================\n");
    $this->xsl->load(dirname(__FILE__) . '/transform/tei2oai.xsl');
    $this->proc->importStylesheet($this->xsl);
    $this->proc->setParameter(null, 'filename', $this->srcFileName);
    $oai = $this->proc->transformToXML($this->doc);
    return $oai;
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
   * Load an OAI record in the weboai SQLITE database
   * TODO : tester qu’on envoie bien OAI valide
   */
  public function sqlite($sqlFile) {
    self::connect($sqlFile);
    //prepare statements
    //self::$stmt['delResource']=self::$pdo->prepare("DELETE FROM resource WHERE title = ?"); // idéalement faire porter clause WHERE sur identifier
    self::$stmt['insResource']=self::$pdo->prepare("
      INSERT INTO resource (oai_datestamp, oai_identifier, record, title, identifier, date, byline, publisher)
                    VALUES (?,             ?,              ?,      ?,     ?,          ?,    ?,      ?);
    ");
    self::$stmt['insAuthor']=self::$pdo->prepare("
      INSERT INTO author (heading, family, given, sort, sort1, sort2, birth, death, uri)
                  VALUES (?,       ?,      ?,     ?,    ?,     ?,     ?,     ?,     ?);
    ");
    self::$stmt['insWrites']=self::$pdo->prepare("
      INSERT INTO writes (author, resource, role)
                  VALUES (?,      ?,        ?);
    ");
    // id d’une notice déjà soumise pour mise à jour -- TODO régler la politique ID pour améliorer la clause WHERE
    self::$stmt['selResourceId']=self::$pdo->prepare("
      SELECT id FROM resource WHERE title= ?;
    ");
    // déplacer dans la méthode insAuthor()?
    self::$stmt['selAuthorId']=self::$pdo->prepare("
      SELECT id FROM author WHERE sort1 = ? AND sort2 = ? AND birth = ? AND death = ?; 
    ");
    // idem, repérer les homonymes
    self::$stmt['selAuthorId2']=self::$pdo->prepare("
      SELECT id FROM author WHERE sort1 = ? AND sort2 = ?; 
    ");

    
    //start transaction
    self::$pdo->beginTransaction();
    // pour meilleure lisibilité
    $oai = $this->doc;
    
    $oai_datestamp  = $oai->getElementsByTagNameNS('http://www.openarchives.org/OAI/2.0/', 'datestamp')->item(0)->nodeValue;
    $oai_identifier = $oai->getElementsByTagNameNS('http://www.openarchives.org/OAI/2.0/', 'identifier')->item(0)->nodeValue;
    // title, just the first one
    $title          = $oai->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'title')->item(0)->nodeValue;
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
    // be nice or block ?
    $date=NULL;
    $dateList=$oai->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'date');
    if ($dateList->length) $date= $dateList->item(0)->nodeValue;
    // TODO, verify if indent is OK
    $record=$oai->saveXML();
    // (oai_datestamp, oai_identifier, record, title, identifier, date, byline, publisher)
    self::$stmt['insResource']->execute(array(
      $oai_datestamp,
      $oai_identifier,
      $record,
      $title,
      $identifier,
      $date,
      $byline,
      0,
    ));
    
    // resource déjà insérée ? récupérer id de la resource pour mise à jour (TODO)
    if (substr(self::$stmt['insResource']->errorCode(), 0, 2) == 23) {
      self::$stmt['selResourceId']->execute(array($title));
      self::$pars['resourceId']=self::$stmt['selResourceId']->fetchColumn();
      echo '<mark>' . $title . ' (notice déjà insérée, resource.id=' . self::$pars['resourceId'] . ')</mark>';      
      // on sort pour l’instant -- TODO: proposer mise à jour de la notice
      exit;
    }   
    //garder en mémoire l’identifiant du record OAI (pour insertion en table de relation)
    if(!isset(self::$pars['resourceId'])) self::$pars['resourceId']=self::$pdo->lastInsertId();
    
    // insertions
    foreach($oai->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'creator') as $creator) {
      self::insAuthor($creator->nodeValue, 1);// arg2, 1=creator
    }
    foreach($oai->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'contributor') as $contributor) {
      self::insAuthor($contributor->nodeValue, 2);// arg2, 2=contributor
    }
    
    /* 
    <teiHeader> is not reliable enough to get an information about the publisher
    and there is no need for a n-n table, why the same book by same publisher ?
    The list should be provided externally (TODO)
    
    foreach($oai->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'publisher') as $publisher) {
      $label = $publisher->nodeValue;
      self::$stmt['insPublisher']->execute(array($label)); // $label UNIQUE in weboai.sql
      $publisherId=null;
      // si publisher existe déjà
      if (substr(self::$stmt['insPublisher']->errorCode(), 0, 2) == 23) {
        self::$stmt['selPublisherId']->execute(array($label));
        $publisherId=self::$stmt['selPublisherId']->fetchColumn();
      }
      // insertion d’un nouveau publisher
      else $publisherId=self::$pdo->lastInsertId();
      self::$stmt['insPublishes']->execute(array($publisherId, self::$pars['resourceId']));
    }
    */
    self::$pdo->commit();
  }
  
  /**
   * called on dc:creator and dc:contributor
   *
   * Montaigne, Françoise de (153.?-....)
   * Bernard de Clairvaux (saint ; 1090?-1153)
   */
  public static function insAuthor($text, $role=NULL, $uri=NULL) {
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
    // charger ressource json pour le tri
    self::$re['fr_sort_tr']=self::json(dirname(__FILE__).'/lib/fr_sort.json');
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
    self::$stmt['selAuthorId']->execute(array( $sort1, $sort2, $birth, $death ));
    $authorId=self::$stmt['selAuthorId']->fetchColumn();
    // be nice if no dates
    if (!$authorId && !$birth) {
      self::$stmt['selAuthorId2']->execute(array( $sort1, $sort2));
      $authorId=self::$stmt['selAuthorId2']->fetchColumn();
      // homonyms ? ALERT ?
      if ($authorId and self::$stmt['selAuthorId2']->fetchColumn()) $authorId=null;
    }
    if (!$authorId) {
      // (heading, family, given, sort, sort1, sort2, birth, death, uri)
      self::$stmt['insAuthor']->execute(array(
        $heading, $family, $given, $sort1.$sort2, $sort1, $sort2, $birth, $death, $uri
      ));
      // echo $text.' — '.$dates.' — '.$family.($given?(', '.$given):'').($birth?(' ('.$birth.'-'.$death.')'):'')."\n";
      $authorId=self::$pdo->lastInsertId();
    }
    $resourceId=self::$pars['resourceId'];
    self::$stmt['insWrites']->execute(array($authorId, $resourceId, $role));
  }  
  
  
  function connect($sqlFile) {
    // create database
    if (!file_exists($sqlFile)) {
      if (!file_exists($dir=dirname($sqlFile))) {
        mkdir($dir, 0775, true);
        @chmod($dir, 0775);
      }
      self::$pdo=new PDO("sqlite:".$sqlFile);
      self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
      @chmod($sqlFile, 0775);
      self::$pdo->exec(file_get_contents(dirname(__FILE__).'/weboai.sql'));
      return;
    }
    else {
      self::$pdo=new PDO("sqlite:".$sqlFile);
      self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
    }
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
    $fileName="";
    if(count($_FILES)) {
      reset($_FILES);
      $tmp=current($_FILES);
      if($tmp['tmp_name']) {
        $src=$tmp['tmp_name'];
        if ($tmp['name']) $fileName=substr($tmp['name'], 0, strrpos($tmp['name'], '.'));
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
      $weboai->srcFileName = $fileName;
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
  public static function doCli() {
    $timeStart = microtime(true);
    array_shift($_SERVER['argv']); // shift arg 1, the script filepath
    if (!count($_SERVER['argv'])) exit("usage : php -f Weboai.php (sch2xsl|validation|tei2oai|sqlite|tei2sqlite) (src.xml|dir/)\n");
    $method=null;//method to call
    $src=null;//XML src
    $srcFileName=null;
    $dest=null;
    
    while ($arg=array_shift($_SERVER['argv'])) {
      if ($arg=="sch2xsl" || $arg=="tei2oai" || $arg=="validation" || $arg=="sqlite" || $arg=="tei2sqlite") $method=$arg;
      else $src=$arg;
    }
    switch ($method) {
      case "sch2xsl":
        $weboai = new Weboai($src);
        $weboai->sch2xsl();
        echo "$src compiled\n";
        break;
      case "validation":
        if (is_dir($src)) {
          foreach(glob($src . '/*.xml') as $xml) {
            $weboai = new Weboai($xml);
            $weboai->xmlValidation();
            echo "==================================\n";
          }
        }
        else {
          $weboai = new Weboai($src);
          $weboai->xmlValidation();
          echo "\n";
        }
        break;
      case "tei2oai":
        if (is_dir($src)) {
          foreach(glob($src . '/*.xml') as $xml) {
            $weboai = new Weboai($xml);
            echo $weboai->tei2oai();
            echo "==================================\n";
          }
        }
        else {
        $weboai=new Weboai($src);
        echo $weboai->tei2oai();
        }
        break;
      //default: oai2sqlite
      case "sqlite":
        if (is_dir($src)) {
          foreach(glob($src . '/*.xml') as $xml) {
            $weboai = new Weboai($xml);
            echo "try to load $weboai->srcFileName in weboai.sqlite\n";
            $weboai->sqlite('weboai.sqlite');
          }
        }
        else {
          $weboai = new Weboai($src);
          $weboai->sqlite('weboai.sqlite');
        }
        break;
      //hook: tei2sqlite
      case "tei2sqlite":
        if (is_dir($src)) {
          foreach(glob($src . '/*.xml') as $tei) {
          $weboai = new Weboai($tei);
          $oai = $weboai->tei2oai();
          echo "===============\n$weboai->srcFileName\n===============\n$oai";
          $oaiDOM = new DOMDocument();
          $oaiDOM->loadXML($oai);
          $weboai->doc = $oaiDOM;
          $weboai->sqlite('weboai.sqlite');          }
        }
        else {
          $weboai = new Weboai($src);
          $oai = $weboai->tei2oai();
          echo "===============\n$weboai->srcFileName\n===============\n$oai";
          $oaiDOM = new DOMDocument();
          $oaiDOM->loadXML($oai);
          $weboai->doc = $oaiDOM;
          $weboai->sqlite('weboai.sqlite');
        }
        break;
    }
  }

  /**
   * Load xml src as dom in $this->doc, with an error recorder
   * called by constructor
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

    // realpath is supposed to be useful on win but break absolute uris
    $this->doc->load($src, LIBXML_NOENT | LIBXML_NSCLEAN | LIBXML_NOCDATA | LIBXML_COMPACT);
    restore_error_handler();
    if (count($this->message)) {
      $this->doc->appendChild($this->doc->createComment("Error recovered in loaded XML document \n". implode("\n", $this->message)."\n"));
    }
    $this->message=array();
  }
  
}


?>