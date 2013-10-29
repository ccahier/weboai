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
  
  // chargement en base
  private static $pdo;// sqlite connection
  private static $stmt=array();// store PDOStatement in array
  private static $pars=array();// parameters (as resourceID) for SQL

  public static $re=array(
    // normalize spaces and puctuation in html
    // . ‘
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
    // after tag wash
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
    $this->xsl = new DOMDocument("1.0", "UTF-8");
    $this->proc = new XSLTProcessor();
    $this->load($srcFile);
  }
  
  /**
   ** Schematron compilation (file.sch chargé en DOM ds $this->doc)
   ** TODO : tester .sch en input et la validité du fichier
   ** TODO : test des droits en doPost()
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
    if (!file_exists('./out/')) {
      mkdir('out/', 0755, true);
      @chmod('out/', 0755); // @, write permission for www-data
    }
    $xslValidator->save('out/teiHeader_validator.xsl');// mise à jour XSL de validation
    echo "./out/teiHeader_validator.xsl updated\n";
    $validator = file_get_contents('out/teiHeader_validator.xsl');
    echo $validator;
    // echo $this->xml2html($validator); // HTML display of XSLT schematron file
  }
  
  /*
   ** Shematron (XSL) validation (source xml chargée en DOM in $this->doc)
   ** si non valide : renvoie une vue HTML du rapport d’erreur SVRL
   ** si valide : renvoie la notice OAI
   */
  public function xmlValidation() {
      $this->xsl->load(dirname(__FILE__) . '/out/teiHeader_validator.xsl');
      $this->proc->importStylesheet($this->xsl);
      $this->proc->setParameter('axsl', 'fileNameParameter', $this->srcFileName);
      $svrl_report = $this->proc->transformToDoc($this->doc);
      $failed_assert_nodes = $svrl_report->getElementsByTagNameNS('http://purl.oclc.org/dsdl/svrl','failed-assert');
      // validation OK, on génère la notice OAI
      if($failed_assert_nodes->length==0) {
        echo 'teiHeader conforme au schéma <a href="http://weboai.sourceforge.net/teiHeader.html#el_term">cahier-weboai</a>';
        // renvoyer la notice oai
        $oai = $this->tei2oai($this->doc);// oai in string
        echo $oai;
        //echo $this->xml2html($oai); // HTML display of OAI record
      }
      // échec validation, on renvoie les erreurs
      else {
        echo 'teiHeader NON conforme au schéma <a href="http://weboai.sourceforge.net/teiHeader.html#el_term">cahier-weboai</a>';
        //echo $this->proc->transformToXML($this->doc); // rapport SVRL
        // SVRL 2 HTML
        $svrl = $this->proc->transformToDoc($this->doc);
        $this->xsl->load(dirname(__FILE__).'/'.'transform/svrl2html.xsl');
        $this->proc->importStylesheet($this->xsl);
        echo $this->proc->transformToXML($svrl);
      }
  } 
  /*
   ** OAI conversion
   ** TODO : enrichir la méthode pour manipuler la notice OAI (lancement chgt en base, etc.)
   */
  public function tei2oai($teiDOM) {
    $this->xsl->load(dirname(__FILE__) . '/transform/tei2oai.xsl');
    $this->proc->importStylesheet($this->xsl);
    return $this->proc->transformToXML($teiDOM);
  }
  
  /*
   ** HTML conversion of XML string for navigator display -- doPost() context.
   **
   */
  public function xml2html($xml) {
    $xmlDOM = new DOMDocument();
    $xmlDOM->loadXML($xml);// oai as DOM
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
      INSERT INTO resource (oai_datestamp, oai_identifier, identifier, title, rights, source, date, description, record)
                    VALUES (?,             ?,               ?,          ?,    ?,      ?,      ?,    ?,           ?);
    ");
    self::$stmt['insAuthor']=self::$pdo->prepare("
      INSERT INTO author (heading, family, given, sort1, sort2, birth, death, uri)
                  VALUES (?,       ?,      ?,     ?,     ?,     ?,     ?,     ?);
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
    self::$stmt['selPublisherId']=self::$pdo->prepare("
      SELECT id FROM publisher WHERE label = ?;
    ");
    // TODO : revoir la logique d’inscription du publisher : cette info est normalisée dans le contexte de l’application Weboai
    self::$stmt['insPublisher']=self::$pdo->prepare("
      INSERT INTO publisher (label)
                     VALUES (?);
    ");
    self::$stmt['insPublishes']=self::$pdo->prepare("
      INSERT INTO publishes (publisher, resource)
                     VALUES (?,         ?);
    ");
    
    //start transaction
    self::$pdo->beginTransaction();
    // pour meilleure lisibilité
    $oai = $this->doc;
    
    $oai_datestamp  = $oai->getElementsByTagNameNS('http://www.openarchives.org/OAI/2.0/', 'datestamp')->item(0)->nodeValue;
    $oai_identifier = $oai->getElementsByTagNameNS('http://www.openarchives.org/OAI/2.0/', 'identifier')->item(0)->nodeValue;
    $identifier     = $oai->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'identifier')->item(0)->nodeValue;
    $title          = $oai->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'title')->item(0)->nodeValue;
    $rights         = $oai->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'rights')->item(0)->nodeValue;
    $source         = $oai->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'source')->item(0)->nodeValue;
    $date           = $oai->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'date')->item(0)->nodeValue;
    $description    = $oai->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'description')->item(0)->nodeValue;
    $record         = file_get_contents('./oai_samples/balzac.xml');
    
    self::$stmt['insResource']->execute(array(
      $oai_datestamp,
      $oai_identifier,
      $identifier,
      $title,
      $rights,
      $source,
      $date,
      $description,
      $record,
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
      
    self::$pdo->commit();
  }
  
  /**
   * Called from XSL, to create an author entry
   *
   * Montaigne, Françoise de (153.?-....)
   */
  public static function insAuthor($text, $role=NULL, $uri=NULL) {
    $text=strtr(trim($text),array('…'=>'...'));
    preg_match('@^([^,]+)(?:, *([^\(]+))?(?:\([^0-9]*([0-9\.]+\??)[^0-9\.]+([0-9\.]+\??)?)?@u', $text, $matches);
    $family=$given=$birth=$death=null;
    if(isset($matches[1])) $family=trim($matches[1]);
    if(isset($matches[2])) $given=trim($matches[2]);
    if(isset($matches[3])) $birth=trim($matches[3]);
    if(isset($matches[4])) $death=trim($matches[4]);
    // be nice for common values like "Jean-Jacques Rousseau" ?
    if (!$given && $pos=strpos($family, ' ')) {
      $given=trim(substr($family,0,$pos));
      $family=trim(substr($family,$pos));
    }
    // charger ressource json pour le tri
    self::$re['fr_sort_tr']=self::json(dirname(__FILE__).'/fr_sort.json');
    $sort1=strtr($family, self::$re['fr_sort_tr']);
    $sort2=strtr($given, self::$re['fr_sort_tr']);
    if($death=='…' || $death=='...') $death='....';
    $heading=$family.($given?(', '.$given):'').($birth?(' ('.$birth.'-'.$death.')'):'');
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
    $authorId=self::$stmt['selAuthorId']->fetchColumn();//renvoie id auteur
    // be nice if no dates
    if (!$authorId && !$birth) {
      self::$stmt['selAuthorId2']->execute(array( $sort1, $sort2));
      $authorId=self::$stmt['selAuthorId2']->fetchColumn();
      // homonyms ? ALERT ?
      if ($authorId and self::$stmt['selAuthorId2']->fetchColumn()) $authorId=null;
    }
    if (!$authorId) {
      self::$stmt['insAuthor']->execute(array(
        $heading, $family, $given, $sort1, $sort2, $birth, $death, $uri
      ));
      $authorId=self::$pdo->lastInsertId();
    }
    $resourceId=null;
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
   * IDEM TEIPUB
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
      echo 'vhdfiuvh dfiuvhdfiuvdfi';
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
    if (!count($_SERVER['argv'])) exit("usage : php -f Weboai.php (sch2xsl|validation|sqlite) src.xml\n");
    $method=null;//method to call
    $src=null;//XML src
    $dest=null;
    
    while ($arg=array_shift($_SERVER['argv'])) {
      if ($arg=="sch2xsl" || $arg=="validation" || $arg=="sqlite") $method=$arg;
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
            echo "\n\n";
          }
        }
        else {
          $weboai = new Weboai($src);
          $weboai->xmlValidation();
          echo "\n";
        }
        break;
      case "sqlite":
        if (is_dir($src)) {
          foreach(glob($src . '/*.xml') as $xml) {
            echo "try to load $xml in weboai.sqlite\n";
            $weboai = new Weboai($xml);
            $weboai->sqlite('weboai.sqlite');
          }
        }
        else {
          $weboai = new Weboai($src);
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