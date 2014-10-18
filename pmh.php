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
    $verbs = array(
      'ListSets' => '', 
      'ListRecords' => '',
      'ListMetadataFormats' => '',
    );
    if (!isset($verbs[$this->verb])) {
      $this->verb = null;
      $this->prolog();
      echo '  <error code="badVerb">' . $this->verb . ' is not a known verb, chose among: ' . implode(array_keys($verbs), ', ') . '</error>'."\n";
      $this->epilog();
      exit();
    }
    $this->pdo=new PDO("sqlite:".$sqlitefile);
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
    $this->prolog();
    call_user_func(array($this, $this->verb));
    $this->epilog();
    exit();
  }

  public function ListMetadataFormats() {
    echo '  <ListMetadataFormats>
    <metadataFormat>
      <metadataPrefix>oai_dc</metadataPrefix>
      <schema>http://www.openarchives.org/OAI/2.0/oai_dc.xsd</schema>
      <metadataNamespace>http://www.openarchives.org/OAI/2.0/oai_dc/</metadataNamespace>
    </metadataFormat>
  </ListMetadataFormats>
';
  }
  public function ListSets() {
    echo '  <ListSets>' . "\n";
    foreach ($this->pdo->query('SELECT * FROM oaiset') as $row) {
      echo $row['xml'];
    }
    echo '  </ListSets>' . "\n";
  }
  public function ListRecords() {
    if (isset($_REQUEST['from']) || isset($_REQUEST['until'])) {
      echo '  <error code="badArgument">Because we can’t ensure dates from our partners, accept the whole list instead, “from” and “until” parameters are not considered.</error>'."\n";
    }
    if (isset($_REQUEST['resumptionToken'])) {
      echo '  <error code="badResumptionToken">This OAI repository do not support resumptionToken, accept the whole list instead.</error>'."\n";
    }
    if (isset($_REQUEST['metadataPrefix']) && $_REQUEST['metadataPrefix'] != 'oai_dc') {
      echo '  <error code="cannotDisseminateFormat">This OAI repository support oai_dc only as a metadata format.</error>'."\n";
    }
    if (!isset($_REQUEST['set'])) {
      echo '  <error code="badArgument">A set is required for this OAI repository. Because we are an agregator of collections, full list of records has no sense.</error>'."\n";
      return;
    }
    echo "  <ListRecords>\n";
    $stmt = $this->pdo->prepare("SELECT record.* FROM record, member, oaiset WHERE member.record = record.rowid AND member.oaiset = oaiset.rowid AND oaiset.setspec=?");
    $stmt->execute(array($_REQUEST['set']));
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $xml = array();
      $xml[] = "    <record>";
      $xml[] = "      <header>";
      $xml[] = "        <identifier>" . $row['oai_identifier'] . "</identifier>";
      $xml[] = "        <datestamp>" . $row['oai_datestamp'] . "</datestamp>";
      $xml[] = "      </header>";
      $xml[] = "      <metadata>";
      $xml[] = $row['xml'];
      $xml[] = "      </metadata>";
      $xml[] = "    </record>\n";
      echo implode($xml, "\n");
    }
    echo "  </ListRecords>\n";
  }
  /**
   * Start of response
   */
  public function prolog() {
    // header ("Content-Type:text/plain");
    header ("Content-Type:text/xml");
    if (!ini_get("zlib.output_compression")) ob_start('ob_gzhandler');
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
    if ($this->verb == 'ListRecords') $xml[] = ' metadataPrefix="oai_dc"';
    $xml[] = '>' . htmlspecialchars($this->servlet) . "</request>\n";
    
    echo implode($xml, '');
  }
  /**
   * End of response
   */
  public function epilog() {
    echo '</OAI-PMH>';
    if (!ini_get("zlib.output_compression")) ob_end_flush();
  }
}
?>