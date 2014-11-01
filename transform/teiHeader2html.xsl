<?xml version="1.0" encoding="UTF-8"?>
<!--
  
<h1>TEI » HTML (tei_html.xsl)</h1>

© 2012, <a href="http://www.algone.net/">Algone</a>, licence  <a href="http://www.cecill.info/licences/Licence_CeCILL-C_V1-fr.html">CeCILL-C</a>/<a href="http://www.gnu.org/licenses/lgpl.html">LGPL</a>
<ul>
  <li>[VJ] <a href="#" onmouseover="this.href='mailto'+'\x3A'+'jolivet'+'\x40'+'algone.net'">Vincent Jolivet</a></li>
  <li>[FG] <a href="#" onmouseover="this.href='mailto'+'\x3A'+'glorieux'+'\x40'+'algone.net'">Frédéric Glorieux</a></li>
</ul>

<p>
Cette transformation XSLT 1.0 (compatible navigateurs, PHP, Python, Java…) 
transforme du TEI en HTML5.
Les auteurs ne s'engagent pas à supporter les 600 éléments TEI.
Cette feuille prend en charge <a href="http://www.tei-c.org/Guidelines/Customization/Lite/">TEI lite</a>
et les éléments TEI documentés dans les <a href="./../schema/">schémas</a> de cette installation.
</p>
<p>
Alternative : les transformations de Sebastian Rahtz <a href="http://www.tei-c.org/Tools/Stylesheets/">tei-c.org/Tools/Stylesheets/</a>
sont officiellement ditribuées par le consortium TEI, cependant ce développement est en XSLT 2.0 (java requis).
</p>
-->
<xsl:transform version="1.1"   xmlns:xsl="http://www.w3.org/1999/XSL/Transform" 

  xmlns="http://www.w3.org/1999/xhtml"
  xmlns:tei="http://www.tei-c.org/ns/1.0" 
  xmlns:html="http://www.w3.org/1999/xhtml"
  xmlns:epub="http://www.idpf.org/2007/ops"
  xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
  xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
  exclude-result-prefixes="tei html epub rdf rdfs"
>
  <xsl:import href="common.xsl"/>
  <xsl:output encoding="UTF-8" indent="yes" method="xml"/>
  <!-- 
<h3>teiHeader</h3>
  -->
  <xsl:template match="tei:TEI">
    <xsl:apply-templates select="tei:teiHeader/tei:fileDesc"/>
  </xsl:template>
  <xsl:template match="*" priority="-5">
    <xsl:call-template name="tag"/>
  </xsl:template>
  <xsl:template match="tei:title">
    <i>
      <xsl:call-template name="headatts"/>
      <xsl:apply-templates/>
    </i>
  </xsl:template>
  <xsl:template match="tei:bibl/tei:* | tei:publisher" priority="-1">
    <span>
      <xsl:call-template name="headatts"/>
      <xsl:apply-templates/>
    </span>
  </xsl:template>
  <xsl:template match="tei:titleStmt/tei:*">
    <div>
      <xsl:call-template name="headatts"/>
      <xsl:apply-templates/>
    </div>
  </xsl:template>
  <!-- Réordonner le bloc de description du fichier -->
  <xsl:template match="tei:fileDesc">
    <xsl:param name="el">div</xsl:param>
    <xsl:choose>
      <xsl:when test="normalize-space(.) = ''"/>
      <xsl:otherwise>
        <xsl:element name="{$el}">
          <xsl:call-template name="headatts"/>
          <!-- Envoyer la page de titre -->
          <xsl:apply-templates select="tei:titleStmt"/>
          <xsl:apply-templates select="tei:publicationStmt"/>
          <xsl:apply-templates select="tei:sourceDesc"/>
          <xsl:apply-templates select="tei:editionStmt"/>
          <xsl:apply-templates select="tei:notesStmt"/>
        </xsl:element>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
    <xsl:template match="tei:titleStmt/tei:title">
    <h1>
      
      <xsl:choose>
        <xsl:when test="@type">
          <xsl:attribute name="class">
            <xsl:value-of select="@type"/>
          </xsl:attribute>
        </xsl:when>
        <xsl:when test="../tei:title[@type='main']">
          <xsl:attribute name="class">notmain</xsl:attribute>
        </xsl:when>
      </xsl:choose>
      <xsl:apply-templates/>
    </h1>
  </xsl:template>
  <!-- Bloc de publication, réordonné -->
  <xsl:template match="tei:fileDesc / tei:publicationStmt">
    <xsl:choose>
      <xsl:when test="normalize-space(tei:publisher) =''"/>
      <xsl:otherwise>
        <xsl:element name="div">
          <xsl:call-template name="headatts"/>
          <xsl:if test="tei:idno">
            <div class="idno">
              <xsl:for-each select="tei:idno">
                <xsl:apply-templates select="."/>
                <xsl:choose>
                  <xsl:when test="position() = last()">.</xsl:when>
                  <xsl:otherwise>, </xsl:otherwise>
                </xsl:choose>
              </xsl:for-each>
            </div>
          </xsl:if>
          <div class="imprint">
            <xsl:for-each select="tei:publisher">
              <xsl:if test="position() != 1">, </xsl:if>
              <xsl:apply-templates select="."/>
            </xsl:for-each>
            <xsl:if test="tei:date">, </xsl:if>
            <xsl:apply-templates select="tei:date"/>
            <xsl:if test="tei:availability/tei:licence[@target]">, </xsl:if>
            <xsl:apply-templates select="tei:availability/tei:licence[@target][1]/@target"/>
            <xsl:text>.</xsl:text>
          </div>
          <!--
          <xsl:apply-templates select="../tei:sourceDesc"/>
          <xsl:apply-templates select="../tei:extent"/>
          <xsl:apply-templates select="tei:publisher"/>
          <xsl:apply-templates select="tei:address"/>
          <xsl:apply-templates select="tei:availability"/>
          -->
        </xsl:element>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
  <!-- Section avec titre prédéfini -->
  <xsl:template match="tei:encodingDesc"/>
  <xsl:template match="tei:availability | tei:editorialDecl | tei:projectDesc | tei:samplingDecl ">
    <xsl:param name="el">div</xsl:param>
    <xsl:element name="{$el}">
      <xsl:call-template name="headatts"/>
      <xsl:variable name="message">
        <xsl:call-template name="message"/>
      </xsl:variable>
      <xsl:if test="string($message) != '' and string($message) != local-name()">
        <label class="{local-name()}">
          <xsl:value-of select="$message"/>
        </label>
      </xsl:if>
      <xsl:apply-templates/>
    </xsl:element>
  </xsl:template>
  <!-- éléments teiHeader retenus -->
  <xsl:template match="tei:charDecl | tei:langUsage | tei:catRef"/>
  <!-- Ligne avec intitulé -->
  <xsl:template match="tei:sourceDesc">    
    <xsl:if test="normalize-space(.) != '' or tei:bibl/tei:ref">
      <div>
        <xsl:call-template name="headatts"/>
        <xsl:variable name="message">
          <xsl:call-template name="message"/>
        </xsl:variable>
        <xsl:choose>
          <xsl:when test="tei:bibl">
            <label>
              <xsl:value-of select="$message"/>
            </label>
            <xsl:text> : </xsl:text>
            <xsl:apply-templates select="tei:bibl[1]/node()"/>
          </xsl:when>
          <xsl:otherwise>
            <xsl:apply-templates/>
          </xsl:otherwise>
        </xsl:choose>
      </div>
    </xsl:if>
  </xsl:template>
  <xsl:template match="tei:licence">
    <div>
      <xsl:call-template name="headatts"/>
      <xsl:variable name="message">
        <xsl:call-template name="message"/>
      </xsl:variable>
      <xsl:if test="string($message) != ''">
        <a href="{@target}">
          <xsl:value-of select="$message"/>
        </a>
      </xsl:if>
      <xsl:apply-templates/>
    </div>
  </xsl:template>
  <xsl:template match="tei:licence/@target">
    <a href="{.}">
      <xsl:call-template name="message">
        <xsl:with-param name="id">license</xsl:with-param>
      </xsl:call-template>
      <xsl:choose>
        <xsl:when test="contains(., 'creativecommons.org')"> cc</xsl:when>
        <xsl:otherwise>
        </xsl:otherwise>
      </xsl:choose>
    </a>
  </xsl:template>
  <!-- Éléments avec intitulé -->
  <xsl:template match="tei:fileDesc/tei:titleStmt/tei:funder | tei:fileDesc/tei:titleStmt/tei:edition | tei:fileDesc/tei:titleStmt/tei:extent">
    <div>
      <xsl:call-template name="headatts"/>
      <xsl:variable name="message">
        <xsl:call-template name="message"/>
      </xsl:variable>
      <xsl:if test="string($message) != ''">
        <label><xsl:value-of select="$message"/><xsl:text> : </xsl:text></label>
      </xsl:if>
      <xsl:apply-templates/>
    </div>
  </xsl:template>
  <xsl:template match="tei:fileDesc/tei:titleStmt/tei:author">
    <div>
      <xsl:call-template name="headatts"/>
      <xsl:apply-templates/>
    </div>
  </xsl:template>
  <!-- Différents titres de responsabilité intellectuelle -->
  <xsl:template match="
  tei:fileDesc/tei:titleStmt/tei:editor[position() != 1]
| tei:fileDesc/tei:titleStmt/tei:principal[position() != 1]
| tei:fileDesc/tei:titleStmt/tei:sponsor[position() != 1]
    "/>
  <xsl:template match="
  tei:fileDesc/tei:titleStmt/tei:editor[1]
| tei:fileDesc/tei:titleStmt/tei:principal[1]
| tei:fileDesc/tei:titleStmt/tei:sponsor[1]
    ">
    <div>
      <xsl:call-template name="headatts"/>
      <xsl:variable name="message">
        <xsl:call-template name="message"/>
      </xsl:variable>
      <xsl:if test="string($message) != ''">
        <label><xsl:value-of select="$message"/></label>
      </xsl:if>
      <xsl:variable name="name" select="local-name()"/>
      <xsl:apply-templates/>
      <xsl:for-each select="following-sibling::*[local-name() = $name]">
        <xsl:choose>
          <xsl:when test="following-sibling::*[local-name() = $name]">, </xsl:when>
          <xsl:otherwise>
            <xsl:text> </xsl:text>
            <xsl:call-template name="message">
              <xsl:with-param name="id">and</xsl:with-param>
            </xsl:call-template>
            <xsl:text> </xsl:text>
          </xsl:otherwise>
        </xsl:choose>
        <xsl:apply-templates/>
      </xsl:for-each>
      <xsl:text>.</xsl:text>
    </div>
  </xsl:template>

  <!-- Les liens dans un <teiHeader> pour une page de titre sont généralement absolus -->
  <xsl:template match="tei:ref">
    <a>
      <xsl:attribute name="href">
        <xsl:value-of select="@target"/>
      </xsl:attribute>
      <xsl:apply-templates/>
    </a>
  </xsl:template>
    <xsl:template match="tei:hi">
    <xsl:variable name="rend" select="translate(@rend, $iso, $min)"></xsl:variable>
    <xsl:choose>
      <xsl:when test=". =''"/>
      <!-- si @rend est un nom d'élément HTML -->
      <xsl:when test="contains( ' b big em i s small strike strong sub sup tt u ', concat(' ', $rend, ' '))">
        <xsl:element name="{$rend}">
          <xsl:if test="@type">
            <xsl:attribute name="class">
              <xsl:value-of select="@type"/>
            </xsl:attribute>
          </xsl:if>
          <xsl:apply-templates/>
        </xsl:element>
      </xsl:when>
      <xsl:when test="starts-with($rend, 'it')">
        <i>
          <xsl:apply-templates/>
        </i>
      </xsl:when>
      <xsl:when test="contains($rend, 'bold') or contains($rend, 'gras')">
        <b>
          <xsl:apply-templates/>
        </b>
      </xsl:when>
      <xsl:when test="starts-with($rend, 'ind')">
        <sub>
          <xsl:apply-templates/>
        </sub>
      </xsl:when>
      <xsl:when test="starts-with($rend, 'exp')">
        <sup>
          <xsl:apply-templates/>
        </sup>
      </xsl:when>
      <!-- sinon appeler le span général -->
      <xsl:otherwise>
        <span class="{@rend}">
          <xsl:apply-templates/>
        </span>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>

  <!-- Réordonner la page de titre du fichier -->
  <xsl:template match="tei:fileDesc/tei:titleStmt">
    <xsl:choose>
      <xsl:when test="normalize-space(.) = ''"/>
      <xsl:otherwise>
        <div>
          <xsl:call-template name="headatts"/>
          <!-- Reorder -->
          <xsl:apply-templates select="tei:author"/>
          <xsl:apply-templates select="tei:title"/>
          <xsl:apply-templates select="tei:editor | tei:funder | tei:meeting | tei:principal | tei:sponsor"/>
          <xsl:apply-templates select="tei:respStmt"/>
          <xsl:apply-templates select="/tei:TEI/tei:teiHeader[1]/tei:profileDesc[1]/tei:creation[1]"/>
        </div>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
  <!--  -->
  <xsl:template match="tei:fileDesc/tei:editionStmt">
    <xsl:choose>
      <xsl:when test="not(tei:respStmt/tei:name[normalize-space(.) != ''])"/>
      <xsl:otherwise>
        <div>
          <xsl:call-template name="headatts"/>
          <xsl:call-template name="message"/>
          <xsl:for-each select="tei:respStmt">
            <xsl:choose>
              <xsl:when test="position() = 1"/>
              <xsl:when test="position() = last()"> et </xsl:when>
              <xsl:otherwise>, </xsl:otherwise>
            </xsl:choose>
              <span class="resp">
                <xsl:apply-templates select="tei:name" mode="txt"/>
                <xsl:if test="tei:resp">
                  <xsl:text> (</xsl:text>
                  <xsl:apply-templates select="tei:resp" mode="txt"/>
                  <xsl:text>)</xsl:text>
                </xsl:if>
              </span>
          </xsl:for-each>
          <xsl:text>.</xsl:text>
        </div>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
  <xsl:template match="tei:creation">
    <xsl:if test="tei:date">
      <div>
        <xsl:text>[</xsl:text>
        <xsl:apply-templates select="tei:date[1]"/>
        <xsl:text>]</xsl:text>
      </div>
    </xsl:if>
  </xsl:template>
  <!-- Champs blocs  -->
  <xsl:template match=" tei:addrLine | tei:keywords | tei:profileDesc | tei:textClass ">
    <xsl:if test="normalize-space(.) != ''">
      <div>
        <xsl:call-template name="headatts"/>
        <xsl:apply-templates/>
        <!-- Ramener une description du projet ? -->
        <!--
        <xsl:apply-templates select="../../tei:encodingDesc/tei:projectDesc"/>
        -->
      </div>
    </xsl:if>
  </xsl:template>
  <xsl:template match="tei:teiHeader" mode="title">
    <xsl:apply-templates select="tei:fileDesc/tei:titleStmt" mode="txt"/>
  </xsl:template>
  <xsl:template match="tei:fileDesc/tei:titleStmt" mode="txt">
    <xsl:choose>
      <xsl:when test="tei:author">
        <xsl:apply-templates select="tei:author" mode="txt"/>
        <xsl:text> ; </xsl:text>
      </xsl:when>
    </xsl:choose>
    <xsl:apply-templates select="tei:title" mode="txt"/>
  </xsl:template>
  <!-- Titres obtenus depuis tei.rdfs -->
  <xsl:template match="tei:publicationStmt | tei:titleStmt" mode="title">
    <xsl:call-template name="message"/>
  </xsl:template>
  <!-- CSS declaration, should be called from <head> -->
  <xsl:template match="tei:tagsDecl">
      <xsl:if test="tei:rendition[not(@scheme) or @scheme='css']">
        <style type="text/css" xml:space="preserve">
          <xsl:for-each select="tei:rendition[not(@scheme) or @scheme='css']">
            <xsl:apply-templates select="."/>
          </xsl:for-each>
        </style>
      </xsl:if>
  </xsl:template>
  <xsl:template match="tei:rendition"/>
  <xsl:template match="tei:rendition[not(@scheme) or @scheme='css']">
    <xsl:text>.</xsl:text>
    <xsl:value-of select="@xml:id"/>
    <xsl:if test="@scope">
      <xsl:text>:</xsl:text>
      <xsl:value-of select="@scope"/>
    </xsl:if>
    <xsl:text> {</xsl:text>
    <xsl:value-of select="."/>
    <xsl:text> }</xsl:text>
  </xsl:template>
  <!-- dates -->
  <xsl:template match="tei:date | tei:docDate | tei:origDate">
    <xsl:variable name="el">
      <xsl:choose>
        <xsl:when test="parent::tei:div">div</xsl:when>
        <xsl:otherwise>span</xsl:otherwise>
      </xsl:choose>
    </xsl:variable>
    <xsl:variable name="att">title</xsl:variable>
    <xsl:variable name="value">
      <xsl:if test="@cert">~</xsl:if>
      <xsl:if test="@scope">~</xsl:if>
      <xsl:variable name="notBefore">
        <xsl:value-of select="number(substring(@notBefore, 1, 4))"/>
      </xsl:variable>
      <xsl:variable name="notAfter">
        <xsl:value-of select="number(substring(@notAfter, 1, 4))"/>
      </xsl:variable>
      <xsl:variable name="when">
        <xsl:value-of select="number(substring(@when, 1, 4))"/>
      </xsl:variable>
      <xsl:choose>
        <xsl:when test="$when != 'NaN'">
          <xsl:value-of select="$when"/>
        </xsl:when>
        <xsl:when test="$notAfter = $notBefore and $notAfter != 'NaN'">
          <xsl:value-of select="$notAfter"/>
        </xsl:when>
        <xsl:when test="$notBefore != 'NaN' and $notAfter != 'NaN'">
          <xsl:value-of select="$notBefore"/>
          <xsl:text>/</xsl:text>
          <xsl:value-of select="$notAfter"/>
        </xsl:when>
        <xsl:when test="$notBefore != 'NaN'">
          <xsl:value-of select="$notBefore"/>
          <xsl:text>/…</xsl:text>
        </xsl:when>
        <xsl:when test="$notAfter != 'NaN'">
          <xsl:text>…–</xsl:text>
          <xsl:value-of select="$notAfter"/>
        </xsl:when>
      </xsl:choose>
    </xsl:variable>
    <xsl:choose>
      <xsl:when test=". = '' and $value = ''"/>
      <xsl:when test=". = '' and $value != ''">
        <xsl:element name="{$el}">
          <xsl:call-template name="headatts"/>
          <xsl:attribute name="{$att}">
            <xsl:value-of select="$value"/>
          </xsl:attribute>
          <xsl:value-of select="$value"/>
        </xsl:element>
       </xsl:when>
      <xsl:otherwise>
        <xsl:element name="{$el}">
          <xsl:call-template name="headatts"/>
          <xsl:if test="$value != ''">
            <xsl:attribute name="{$att}">
              <xsl:value-of select="$value"/>
            </xsl:attribute>
          </xsl:if>
          <xsl:apply-templates/>
        </xsl:element>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
  <!-- identifiant bibliographique -->
  <xsl:template match="tei:idno | tei:altIdentifier">
    <xsl:choose>
      <xsl:when test="starts-with(., 'http')">
        <a>
          <xsl:call-template name="headatts"/>
          <xsl:attribute name="href">
            <xsl:value-of select="."/>
          </xsl:attribute>
          <xsl:choose>
            <xsl:when test="@type">
              <xsl:value-of select="@type"/>
            </xsl:when>
            <xsl:otherwise>
              <xsl:apply-templates/>
            </xsl:otherwise>
          </xsl:choose>
        </a>
      </xsl:when>
      <xsl:otherwise>
        <span>
          <xsl:call-template name="headatts"/>
          <xsl:apply-templates/>
        </span>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
  <xsl:template name="headatts">
    <xsl:attribute name="class">
      <xsl:value-of select="local-name()"/>
    </xsl:attribute>
  </xsl:template>
</xsl:transform>