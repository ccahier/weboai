<?php
new Pmh('cahier.sqlite');
class Pmh {
  public $pdo;
  public $verb;
  public $servlet;
  public $set;
  function __construct($sqlitefile) {
    if (!file_exists($sqlitefile)) {
      $this->prolog();
      echo '  <error code="badArgument">Server configuration error, bad datalink</error>'."\n";
      $this->epilog();
      exit();
    }
    $this->servlet = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    if (isset($_REQUEST['verb'])) $this->verb = $_REQUEST['verb'];
    $verbs = array('ListSets'=>'', 'ListRecords'=>'');
    if (!isset($verbs[$this->verb])) {
      $this->verb = null;
      $this->prolog();
      echo '  <error code="badVerb">' . $this->verb . ' is not a known verb, chose among: ' . implode(array_keys($verbs), ', ') . '</error>'."\n";
      $this->epilog();
      exit();
    }
    $this->pdo=new PDO("sqlite:".$sqlitefile);
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
    call_user_func(array($this, $this->verb));
  }
  public function ListSets() {
    $this->prolog();
    echo '  <ListSets>' . "\n";
    foreach ($this->pdo->query('SELECT * FROM oaiset') as $row) {
      echo $row['xml'];
    }
    echo '  </LisSets>' . "\n";
    $this->epilog();
    exit();
  }
  public function ListRecords() {
  
  }
  /**
   * Start of response
   */
  public function prolog() {
    header ("Content-Type:text/plain");
    // header ("Content-Type:text/xml");
    $date = date(DATE_ATOM);
    $xml = array();
    $xml[] = '<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type="text/xsl" href="transform/oai2html.xsl"?>
<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/" 
         xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/
         http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd">
  <responseDate>' . $date . '</responseDate>
  ';
    $xml[] = "<request";
    if ($this->verb) $xml[] = ' verb="' . $this->verb . '"';
    $xml[] = '>' . $this->servlet . "</request>\n";
    
    echo implode($xml, '');
  }
  /**
   * End of response
   */
  public function epilog() {
    echo '</OAI-PMH>';
  }
}
?>