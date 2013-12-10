<?php
/**
 * Methods to search and display records produced with Weboai
 */  
class Oaiweb {
  /** Sqlite connexion, is not dynamic, parameters by constructor, public, maybe useful externally */
  public $pdo;
  /** some regex tools, probably static, no constructor params, maybe useful externally */
  public static $re=array();
  /** Has searching been done? */
  public $search;
  /** total number of documents, external read access useful */
  public $docsCount;
  /** number of doc results */
  public $docsFound;
  /** author filter, integers separated by commas */
  public $by=array();
  /** author filter, integers separated by commas */
  /** date query, end year */
  public $end;
  public $notby=array();
  /** term query param */
  public $q;
  /** date query, start year */
  public $start;
  /** set query, 0 or more */
  public $set;
  /** lang for generated messages */
  public $lang;
  /** Generated messages */
  private static $msg=array(
    "author"=>array("en"=>"Author","fr"=>"Auteur"),
    "authors"=>array("en"=>"Author(s)","fr"=>"Auteur(s)"),
    "birth"=>array("en"=>"Birth","fr"=>"Naissance"),
    "byline"=>array(
      "fr"=>'Auteur(s)',
      "en"=>'Byline',
    ),
    "date"=>array("en"=>"Date","fr"=>"Date"),
    "date1"=>array("en"=>"Date","fr"=>"Date"),
    "death"=>array("en"=>"Death","fr"=>"Mort"),
    "docs"=>array(
      "fr"=>'<b>%d</b> textes trouvés parmi <a href="?">%d</a>',
      "en"=>'<b>%d</b> texts out <a href="?">%d</a>'
    ),
    "docsAll"=>array(
      "fr"=>'<b>%d</b> textes',
      "en"=>'<b>%d</b> texts'
    ),
    "docs1"=>array(
      "fr"=>'<b>Un</b> texte trouvé parmi  <a href="?">%d</a>',
      "en"=>'<b>One</b> text out of <a href="?">%d</a>'
    ),
    "docs0"=>array(
      "fr"=>'<b>Aucun</b> texte trouvé parmi  <a href="?">%d</a>',
      "en"=>'<b>No</b> text out of  <a href="?">%d</a>'
    ),
    "n"=>array("en"=>"№","fr"=>"n°"),
    "text"=>array("en"=>"Text","fr"=>"Texte"),
    "texts"=>array("en"=>"Text","fr"=>"Textes"),
    "text1"=>array("en"=>"One text","fr"=>"Un texte"),
    "title"=>array("en"=>"Title","fr"=>"Titre"),
    "titles"=>array("en"=>"Title(s)","fr"=>"Titre(s)"),
  );

  /**
   * Constructor, class is build around a connexion to an sqlite file
   */
  function __construct($sqliteFile, $lang='fr') {
    $this->lang=$lang;
    $this->pdo=new PDO("sqlite:".$sqliteFile);
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
    $this->pdo->exec("PRAGMA temp_store = 2;");
    // load a json resource, no test for errors, bet it works
    $content=file_get_contents(dirname(__FILE__).'/lib/fr_sort.json');
    self::$re['fr_sort_tr']=json_decode($content, true);
    // set query params
    if (isset($_REQUEST['q']) && $_REQUEST['q']) $this->q=trim($_REQUEST['q']);
    if (isset($_REQUEST['start']) && 0+$_REQUEST['start'] != 0) $this->start=0+$_REQUEST['start'];
    if (isset($_REQUEST['end']) && $this->start && $_REQUEST['end'] > $this->start) $this->end=$_REQUEST['end'];
    if ($this->start && !$this->end) $this->end=$this->start;
    // by and notby may be repeated params or comma separated integer list
    if (isset($_REQUEST['by'])) $this->by=self::csi(implode(',',Web::pars('by')));
    if (isset($_REQUEST['notby'])) $this->notby=self::csi(implode(',',Web::pars('notby')));
    // valid sets requested and take the integer for query
    if (isset($_REQUEST['set'])) {
      $setSpecs=Web::pars('set');
      $this->set=array();
      $getId=$this->pdo->prepare("SELECT id FROM oaiset WHERE spec = ? ");
      foreach ($setSpecs AS $set) {
        $getId->execute(array($set));
        list($id)=$getId->fetch();
        if($id) $this->set[]=$id;
      }
    }
  }
  /** What to append to a query string, to keep search params */
  function qsa($exclude=array(), $include=array()) {
    if(!$exclude)$exclude=array();
    if (!count($include)) $include=array('by','end','notby','q','set','start');
    $include=array_diff($include, $exclude);
    // array_unique() will reorder array, unify by key
    $include=array_flip(array_flip($include));
    $qsa="";
    foreach($include as $key) {
      if (!isset($this->$key)) continue;
      else if (!$this->$key) continue;
      else if (is_array($this->$key)) foreach ($this->$key as $value) $qsa.='&'.$key.'='.$value; 
      else $qsa.='&'.$key.'='.$this->$key;
    }
    return $qsa;
  }
  /** validate comma separated integers and return array */
  static function csi($string) {
    $list=explode(',',$string);
    // $list=array_map(function($value) {return $value=0+$value;}, $list); // do not work in php 5.2
    $fint=create_function('$value', 'return $value=0+$value;');
    $list=array_map($fint, $list);
    // unify values
    $list=array_flip($list);
    // delete null values (?)
    unset($list[0]);
    $list=array_flip($list);
    return $list;
  }
  /** 
   * build a temp table of found documents, especially usefull to have index 
   * on some calculated values, more efficient before any results view 
   */
  public function search() {
    $timeStart = microtime(true);
    $this->pdo->beginTransaction();
    $this->docsCount = current($this->pdo->query("SELECT COUNT(*) FROM record")->fetch());
    $this->pdo->exec("CREATE TEMP TABLE found (id INTEGER PRIMARY KEY, date INTEGER);");
    $from="";
    $where=" 1 ";
    if($this->by || $this->notby) $from .= ", writes";
    if ($this->by) $where.=" AND (writes.author IN (".implode(',',$this->by).")  AND writes.record = record.id)";
    if ($this->notby) $where.=" AND (writes.author NOT IN (".implode(',',$this->notby).")  AND writes.record = record.id)";
    // set array supposed to have been verified against the table oaiset to have integer serial
    if ($this->set) {
      $from .= ", member";
      $where.=" AND (member.oaiset IN (".implode(',',$this->set).")  AND member.record = record.id)";
    }
    
    if ($this->start && $this->end) $where.=" AND (record.date >= $this->start AND record.date <= $this->end)";
    // occurrences not useful in this biliographic context
    if ($this->q) {
      $sql="INSERT INTO found (id, date) SELECT ft.docid, record.date FROM record, ft ".$from." WHERE (record.id=ft.docid AND ft.heading MATCH ".$this->pdo->quote($this->q).") AND ".$where;
    }
    else $sql="INSERT INTO found (id, date) SELECT record.id, record.date FROM record ".$from." WHERE ".$where;
    $this->pdo->exec($sql);
    $this->pdo->exec("CREATE INDEX foundDate ON found(date);");
    $this->docsFound = current($this->pdo->query("select count(*) from found")->fetch());
    
    echo "<!-- $sql \n",$this->docsFound," found out ".$this->docsCount." in ",number_format( microtime(true) - $timeStart, 3)," s. -->";
    $this->pdo->commit(); // temp tables only available now
    $this->search=TRUE;
  }
  /**
   * Display a simple line as a report about query
   */
  public function report() {
    if (!$this->search) $this->search();
    // no query
    if ($this->docsFound==$this->docsCount) echo $this->msg("docsAll", array($this->docsCount));
    else if ($this->docsFound > 1) echo $this->msg("docs", array($this->docsFound, $this->docsCount));
    else if ($this->docsFound == 1) echo $this->msg("docs1", array($this->docsCount));
    // nothing found
    else echo $this->msg("docs0", array($this->docsCount));
  }
  /** 
   * display search results as a chrono
   * height: max height of a bar in em
   * colPref: number of cols to approach, respecting span and steps
   */
  public function chrono($height=10,$colPref=10) {
    $html=array();
    if (!$this->search) $this->search();
    if (!$this->docsFound) return;
    // the step of times spans
    $timeStep=array(1,5,10,25,50,100,250,500,1000);
    $start=current($this->pdo->query("SELECT date FROM found ORDER BY date LIMIT 1")->fetch());
    $end=current($this->pdo->query("SELECT date FROM found ORDER BY date DESC LIMIT 1")->fetch());
    // if ($start==$end) return; // 1 year, not interesting ?
    $end++; // last year+1
    // $sql="SELECT created, count(*) AS count FROM article, found WHERE article.id=found.rowid GROUP BY created";
    // prefered number of colomns
    $colBest=100000;
    $step=10;
    $span=$end-$start;
    foreach($timeStep AS $test) {
       $cols=round($span / $test);
       if (abs($cols - $colPref) < ($colBest- $colPref)) {
         $colBest=$cols;
         $step=$test;
       }
    }
    $last=floor($start/$step)*$step;
    $query=$this->pdo->prepare("SELECT count(*) FROM found WHERE date >= ? AND date < ? ");
    $max=0;
    $chrono=array();
    for ($i=$last+$step;$i<$end+$step;$i=$i+$step) {
      $count=0;
      $query->execute(array($last,$i));
      list($count)= $query->fetch();
      $chrono[$last]=$count;
      if ($count > $max) $max=$count;
      $last=$i;
    }
    $chrono[$last]="";
    // max bar height in em
    $height=10;
    $html[]='<p>Répartition des textes trouvés selon les dates “premières” (généralement la date de création)</p>';
    $html[]='<table width="100%" class="chrono" border="0" cellspacing="0" cellpadding="0">
  <tr>';
    foreach($chrono as $start=>$value) {
      // keep other search params
      $href="?".$this->qsa(array('start','end'));
      // last cell, link to reset chrono search
      if ($value==="") {
        $html[]='    <td align="left" class="end"><a href="'.$href.'">X<div class="year">'.$start.'</div></a></td>';
        continue;
      }
      // add the period search params
      $href .= "&start=".$start."&end=".($start+$step-1);
      $em=number_format($height*$value/$max);
      // no link if value=0
      if (!$value) $href="";
      $html[]='    <td>';
      if ($href) $html[]='      <a href="'.$href.'">';
      // do not display value if no words searched
      $html[]='        <div class="value">'.$value.'</div>';
      $html[]='        <div style="height:'.$em.'em" class="bar"></div>
        <div class="year">'.$start.'</div>';
      if ($href) $html[]='      </a>';
      $html[]='    </td>';
    }
    $html[]='</tr>
</table>';
    echo implode("\n",$html);
  }
  /**
   * List sets
   */
  public function sets($prefix="") {
    if (!$this->search) $this->search();
    if (!$this->docsFound) return;
    // no search, list all sets
    if ($this->docsFound == $this->docsCount) {
      echo "\n".'<div class="sets">';
      $countQ=$this->pdo->prepare("SELECT count(*) AS count FROM member, found WHERE member.oaiset=? AND found.id=member.record");
      foreach ($this->pdo->query("SELECT * FROM oaiset", PDO::FETCH_ASSOC) as $set) {
        // indent subsets
        $indent=substr_count($set['spec'], ':');
        $countQ->execute(array($set['id']));
        list($count)=$countQ->fetch();
        if (!$count) $texts='';
        else if ($count==1) $texts=' ('.$this->msg('text1').')';
        else $texts=' ('.$count.' '.mb_strtolower ( $this->msg('texts') , "UTF-8" ).')';
        if(!$count) echo '<div class="level' . (0 + $indent) . '">'. $set['name'] .'</a></div>';
        else echo '<div class="level' . (0+$indent) . '">'.'<a href="?' . $this->qsa(array('set')) . '&set=' . $set['spec'] . '">' . $set['name'] . $texts .'</a></div>'; 
      }
      echo "\n".'</div>';
    }
    else {
      $list=$this->pdo->prepare("SELECT oaiset.*, count(*) AS count FROM oaiset, member, found WHERE found.id=member.record AND member.oaiset=oaiset.id GROUP BY oaiset.id ORDER BY oaiset.spec ");
      $list->execute(array());
      echo "\n".'<div class="sets">';
      while($set=$list->fetch(PDO::FETCH_ASSOC)) {
        // do no display sets with no result, except for home
        if (!$set['count'] && $this->docsFound != $this->docsCount) continue;
        // indent subsets
        $indent=substr_count($set['spec'], ':');
        // number of texts
        if (!$set['count']) $texts='';
        else if ($set['count']==1) $texts=' ('.$this->msg('text1').')';
        else $texts=' ('.$set['count'].' '.mb_strtolower ( $this->msg('texts') , "UTF-8" ).')';
        
        echo '<div class="level'. (0 + $indent) . '">'.'<a href="?' . $this->qsa(array('set')) . '&set=' . $set['spec'] . '">' . $set['name'] . $texts .'</a></div>'; 
      }
      echo "\n".'</div>';
    }
    
  }
  
  
  /**
   * display search results as a bibliography
   * do not display books by author (pb multiple authors)
   * $limit : max books
   */
  public function biblio($cols=array('n', 'byline', 'title', 'date', 'date2'), $limit=300) {
    if (!$this->search) $this->search();
    if (!$this->docsFound) return;

    $list=$this->pdo->prepare("SELECT record.identifier, record.byline, record.title, record.date, record.date2 FROM record, found WHERE found.id=record.id ORDER BY found.date LIMIT ".$limit);
    
    // buffer to output line after line
    $html=array();
    $html[]='<table class="sortable">';
    $html[]='  <tr>';
    foreach($cols as $col) {
      if ($col == 'n')  $html[]='    <th>'.$this->msg('n').'</th>';
      if ($col == 'byline') $html[]='    <th>'.$this->msg('byline').'</th>';
      if ($col == 'date')   $html[]='    <th title="Date de création du texte">Date <br/>première</th>';
      if ($col == 'date2')   $html[]='    <th title="Autre date importante, comme la date d’édition d’un texte ancien">Date <br/>seconde</th>';
      if ($col == 'title')  $html[]='    <th class="nosort">'.$this->msg('title').'</th>';
    }
    echo implode("\n",$html);
    $list->execute(array());
    $i=0;
    while($record=$list->fetch(PDO::FETCH_ASSOC)) {
      $i++;
      $html=array();
      $html[]='  <tr>';
      foreach($cols as $col) {
        if ($col == 'n')      $html[]='    <td class="n">'.$i.'</td>';
        if ($col == 'byline') $html[]='    <td class="byline">'.$record['byline'].'</td>';
        if ($col == 'date')   $html[]='    <td>'.$record['date'].'</td>';
        if ($col == 'date2')   $html[]='    <td>'.$record['date2'].'</td>';
        if ($col == 'title')  {
          if ($record['identifier'] && $record['identifier']!='?') $html[]='    <td><a href="'.$record['identifier'].'">'.$record['title'].'</a></td>';
          else $html[]='<td>'.$record['title'].'</td>';
        }
      }
      $html[]='  </tr>';
      echo implode("\n",$html);
    }
    echo '</table>';
  }
  /** Display a message */
  public function msg($key, $arg=array(), $lang=false) {
    $text=$key;
    if(!$lang) $lang=$this->lang;
    if (isset(self::$msg[$key]) && isset(self::$msg[$key][$lang])) $text=self::$msg[$key][$lang];
    if (!count($arg)) return $text;
    else if (count($arg) > 3 ) return sprintf($text, $arg[0], $arg[1], $arg[2], $arg[3]);
    else if (count($arg) > 2 ) return sprintf($text, $arg[0], $arg[1], $arg[2]);
    else if (count($arg) > 1 ) return sprintf($text, $arg[0], $arg[1]);
    else if (count($arg) > 0 ) return sprintf($text, $arg[0]);
  }

}

/**
Tools to deal with Http oddities
 */
class Web {
  /** Content-Type header */
  static $mime=array(
    "css"  => 'text/css; charset=UTF-8',
    "epub" => 'application/epub+zip',
    "html" => 'text/html; charset=UTF-8',
    "jpg"  => 'image/jpeg',
    "png"  => 'image/png',
    "xml"  => 'text/xml',
  );
/**
   * Give pathinfo with priority order of different values.
   * The possible variables are not equally robust
   *
   * http://localhost/~user/teipot/doc/install&sons?a=1&a=2#ancre
   *
   * — $_SERVER['REQUEST_URI'] OK /~user/teipot/doc/install&sons?a=1&a=2
   * — $_SERVER['SCRIPT_NAME'] OK /~user/teipot/index.php
   * — $_SERVER['PHP_SELF'] /~user/teipot/index.php/doc/install&sons (not always given by mod_rewrite)
   * — $_SERVER['PATH_INFO'] sometimes unavailable, ex: through mod_rewrite /doc/install&sons
   * — $_SERVER['SCRIPT_URI'] sometimes, ex : http://teipot.x10.mx/install&bon
   * — $_SERVER['PATH_ORIG_INFO'] found on the web
   *
   */
  static $pathinfo;
  public static function pathinfo() {
    if (self::$pathinfo) return self::$pathinfo;
    $pathinfo="";
    if (!isset($_SERVER['REQUEST_URI'])) return $pathinfo; // command line
    list($request)=explode('?', $_SERVER['REQUEST_URI']);
    if(strpos($request, '%') !== false) $request=urldecode($request);
    if (strpos($request, $_SERVER['SCRIPT_NAME']) === 0)
      $pathinfo=substr($request, strlen($_SERVER['SCRIPT_NAME']));
    else if (strpos($request, dirname($_SERVER['SCRIPT_NAME'])) === 0)
      $pathinfo=substr($request, strlen(dirname($_SERVER['SCRIPT_NAME'])));
    // if nothing found, try other variables
    if ($pathinfo); // something found, keep it
    else if (isset($_SERVER['PATH_ORIG_INFO'])) $pathinfo=$_SERVER['PATH_ORIG_INFO'];
    else if (isset($_SERVER['PATH_INFO'])) $pathinfo=$_SERVER['PATH_INFO'];
    else if (isset($_REQUEST['id'])) $pathinfo=$_REQUEST['id'];
    // should I trim last / ?
    self::$pathinfo=ltrim($pathinfo, '/');
    return self::$pathinfo;
  }
  /**
   * Handle repeated parameters values, especially in multiple select.
   * $_REQUEST propose a strange PHP centric interpretation of http protocol, with the bracket keys
   * &lt;select name="var[]">
   *
   * $query : optional, a "query string" ?cl%C3%A9=%C3%A9%C3%A9&param=valeur1&param=&param=valeur2
   * return : Array (
   *   "clé" => array("éé"),
   *   "param" => array("valeur1", "", "valeur2")
   * )
   */
  public static function pars( $name=FALSE, $query=FALSE, $expire=0) {
    if (!$query) $query=Web::query();
    // populate an array
    $pars=array();
    $a = explode('&', $query);
    foreach ($a as $p) {
      if (!$p) continue;
      if (!strpos($p,'=')) continue;
      list($k, $v) = preg_split('/=/', $p);
      $k=urldecode($k);
      $v=urldecode($v);
      // seems , traduire les accents
      if (preg_match('/[\xC0-\xFD]/', $k+$v)) {
        $k=utf8_encode ($k);
        $v=utf8_encode ($v);
      }
      $pars[$k][]=$v;
    }
    // no key requested, return all params, do not store cookies
    if (!$name) return $pars;
    // a param is requested, values found
    else if (isset($pars[$name])) $pars=$pars[$name];
    // no param for this name
    else $pars=array();


    // no cookie store requested
    if(!$expire);
    // if empty ?, delete cookie
    else if (count($pars)==1 && !$pars[0]) {
      setcookie($name);
    }
    // if a value, set cookie, do not $_COOKIE[$name]=$value
    else if (count($pars)) {
      // if a number
      if ($expire > 60) setcookie($name, serialize($pars), time()+ $expire);
      // session time
      else setcookie($name, serialize($pars));
    }
    // if cookie stored, load it
    else if(isset($_COOKIE[$name])) $pars=unserialize($_COOKIE[$name]);
    return $pars;
  }
  /**
   * build a clean query string from get or post, especially
   * to get multiple params from select
   *
   * query: ?A=1&A=2&A=&B=3
   * return: ?A=1&A=2&B=3
   * $keep=true : keep empty params -> ?A=1&A=2&A=&B=3
   * $exclude=array() : exclude some parameters 
   */
  public static function query($keep=false, $exclude=array(), $query=null) {
    // query given as param
    if ($query) $query=preg_replace( '/&amp;/', '&', $p1);
    // POST
    else if ($_SERVER['REQUEST_METHOD'] == "POST") {
      if (isset($HTTP_RAW_POST_DATA)) $query=$HTTP_RAW_POST_DATA;
      else $query = file_get_contents("php://input");
    }
    // GET
    else $query=$_SERVER['QUERY_STRING'];
    // exclude some params
    if (count($exclude)) $query=preg_replace( '/&('.implode('|',$exclude).')=[^&]*/', '', '&'.$query);
    // delete empty params
    if (!$keep) $query=preg_replace( array('/[^&=]+=&/', '/&$/'), array('', ''), $query.'&');
    return $query;
  }
  /**
   * Send the best headers for cache, according to the request and a timestamp
   */
  public static function notModified($file, $expires=null, $force=false) {
    if (!$file) return false;
    $filemtime=false;
    // seems already a filemtime
    if (is_int($file)) $filemtime=$file;
    // if array of file, get the newest
    else if (is_array($file)) foreach($file as $f) {
      // if not file exists, no error
      if (!file_exists($f)) continue;
      $i=filemtime($f);
      if ($i && $i > $filemtime) $filemtime=$i;
    }
    else $filemtime=filemtime($file);
    if(!$filemtime) return $filemtime;
    // Default expires
    if (filemtime($_SERVER['SCRIPT_FILENAME']) > $filemtime) {
      $filemtime=filemtime($_SERVER['SCRIPT_FILENAME']);
    }
    $if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? stripslashes($_SERVER['HTTP_IF_MODIFIED_SINCE']) :false;
    // $if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? stripslashes($_SERVER['HTTP_IF_NONE_MATCH']) : false; // etag
    $modification=gmdate('D, d M Y H:i:s', $filemtime).' GMT';
    // tests for 304
    if($force);
    else if (self::noCache());
    // ($if_none_match && $if_none_match == $etag) || 
    else if ( $if_modified_since == $modification) {
      header('HTTP/1.x 304 Not Modified');
      exit;
    }
    // header("X-Date: ". substr(gmdate('r'), 0, -5).'GMT');
    /*
    // According to google, https://developers.google.com/speed/docs/best-practices/caching
    // exclude etag if last-Modified, and last-Modified is better 
    $etag = '"'.md5($modification).'"';
    header("ETag: $etag");
    */
    // it seems there is something to send
    header("Cache control: public"); // for FireFox over https
    header("Last-Modified: $modification");
    // it's good to 
    if ($expires) header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$expires) . ' GMT');
  }

  /**
   * If client ask a forced relaod.
   */
  public static function noCache() {
    // pas de cache en POST
    if ($_SERVER['REQUEST_METHOD'] == 'POST') return 'POST';
    if (isset ($_SERVER['HTTP_PRAGMA']) && stripos($_SERVER['HTTP_PRAGMA'], "no-cache") !== false) return "Pragma: no-cache";
    if (isset ($_SERVER['HTTP_CACHE_CONTROL']) && stripos($_SERVER['HTTP_CACHE_CONTROL'], "no-cache") !== false) return "Cache-Control: no-cache";
    if (isset($_REQUEST['no-cache'])) return '?no-cache=';
    if (isset($_REQUEST['force'])) return '?force=';
    return false;
  }

}

?>