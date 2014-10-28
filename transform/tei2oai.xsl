<?xml version="1.0" encoding="UTF-8"?>
<!--
© 2013, 2014 <a href="http://www.algone.net/">Algone</a>, licence  <a href="http://www.cecill.info/licences/Licence_CeCILL-C_V1-fr.html">CeCILL-C</a>/<a href="http://www.gnu.org/licenses/lgpl.html">LGPL</a>
<ul>
  <li>[FG] <a href="#" onmouseover="this.href='mailto'+'\x3A'+'glorieux'+'\x40'+'algone.net'">Frédéric Glorieux</a></li>
</ul>
-->
<xsl:transform version="1.1"
  xmlns="http://www.openarchives.org/OAI/2.0/"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:date="http://exslt.org/dates-and-times"
  xmlns:tei="http://www.tei-c.org/ns/1.0"
  xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"
  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:dcterms="http://purl.org/dc/terms/"
  exclude-result-prefixes="tei date">
  <xsl:import href="common.xsl"/>
  <xsl:output encoding="UTF-8" indent="yes" method="xml"/>
  <xsl:param name="filename"/>
  <xsl:variable name="ABC">ABCDEFGHIJKLMNOPQRSTUVWXYZÀÂÄÉÈÊÏÎÔÖÛÜÇàâäéèêëïîöôüû</xsl:variable>
  <xsl:variable name="abc">abcdefghijklmnopqrstuvwxyzaaaeeeiioouucaaaeeeeiioouu</xsl:variable>
  
  <xsl:template match="/">
    <xsl:apply-templates select="tei:TEI/tei:teiHeader"/>
  </xsl:template>
    
  <xsl:template match="tei:teiHeader">
    <!-- It is not the right context to handle that, too much infos are server dependant, and not source dependant -->
    <!--
    <header>
      <identifier>
        <xsl:value-of select="$oai_id_prefix"/>
        <xsl:value-of select="$filename"/>
      </identifier>
      <datestamp><xsl:value-of select="date:date-time()"/></datestamp>
    </header>
    -->
    <oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/" xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd">
      <xsl:choose>
        <xsl:when test="/*/@xml:id">
          <xsl:attribute name="xml:id">
            <xsl:value-of select="/*/@xml:id"/>
          </xsl:attribute>
        </xsl:when>
        <!-- Ajouter ici un filename ? -->
      </xsl:choose>
      <!-- n? creator -->
      <xsl:choose>
        <xsl:when test="tei:fileDesc/tei:titleStmt/tei:author">
          <xsl:apply-templates select="tei:fileDesc/tei:titleStmt/tei:author"/>
        </xsl:when>
        <!-- specific BVH, msDesc before a <bibl> ? -->
        <xsl:when test="tei:fileDesc/tei:sourceDesc/tei:msDesc[1]//tei:biblStruct">
          <xsl:apply-templates select="tei:fileDesc/tei:sourceDesc/tei:msDesc[1]//tei:biblStruct/*/tei:author"/>
        </xsl:when>
        <xsl:when test="tei:fileDesc/tei:sourceDesc/tei:bibl[1][tei:author]">
          <xsl:apply-templates select="tei:fileDesc/tei:sourceDesc/tei:bibl[1][tei:author]/tei:author"/>
        </xsl:when>
      </xsl:choose>
      <!-- 1! title -->
      <!-- TODO: Aggregate subtitles ? translated titles ? -->
      <xsl:apply-templates select="tei:fileDesc/tei:titleStmt/tei:title[1]"/>
      <!-- 1? significant date -->
      <xsl:variable name="date">
        <xsl:choose>
          <xsl:when test="tei:profileDesc/tei:creation/tei:date">
            <xsl:apply-templates select="tei:profileDesc/tei:creation/tei:date[1]" mode="year"/>
          </xsl:when>
          <!-- specific BVH, msDesc before a <bibl> ? -->
          <xsl:when test="tei:fileDesc/tei:sourceDesc/tei:msDesc[1]//tei:date">
            <xsl:apply-templates select="(tei:fileDesc/tei:sourceDesc/tei:msDesc[1]//tei:date)[1]" mode="year"/>
          </xsl:when>
          <xsl:when test="tei:fileDesc/tei:sourceDesc/tei:bibl[1][tei:date]">
            <xsl:apply-templates select="(tei:fileDesc/tei:sourceDesc/tei:bibl[1][tei:date]//tei:date)[1]" mode="year"/>
          </xsl:when>
        </xsl:choose>
      </xsl:variable>
      <xsl:if test="$date != ''">
        <dc:date>
          <xsl:value-of select="$date"/>
        </dc:date>
      </xsl:if>
      <xsl:variable name="date2">
        <xsl:choose>
          <xsl:when test="tei:fileDesc/tei:sourceDesc//tei:date">
            <xsl:apply-templates select="(tei:fileDesc/tei:sourceDesc//tei:date)[1]" mode="year"/>
          </xsl:when>
        </xsl:choose>
      </xsl:variable>
      <xsl:if test="$date2 != $date and $date2 != ''">
        <dcterms:dateCopyrighted>
          <xsl:value-of select="$date2"/>
        </dcterms:dateCopyrighted>
      </xsl:if>
      <xsl:if test="tei:fileDesc/tei:editionStmt/tei:edition[position()=last()]//tei:date">
        <dcterms:issued>
          <xsl:apply-templates select="(tei:fileDesc/tei:editionStmt/tei:edition[position()=last()]//tei:date)[1]" mode="year"/>
        </dcterms:issued>
      </xsl:if>
      <!-- n? contributor -->
      <xsl:apply-templates select="tei:fileDesc/tei:titleStmt/tei:editor"/>
      <xsl:apply-templates select="tei:fileDesc/tei:titleStmt/tei:principal"/>
      <!-- n? publisher -->
      <xsl:apply-templates select="tei:fileDesc/tei:publicationStmt/tei:publisher"/>
      <!-- 1! identifier -->
      <xsl:choose>
        <xsl:when test="tei:fileDesc/tei:publicationStmt/tei:idno">
            <xsl:apply-templates select="tei:fileDesc/tei:publicationStmt/tei:idno"/>
        </xsl:when>
        <!-- specific  -->
        <xsl:when test="tei:fileDesc/tei:editionStmt/tei:edition/@xml:base">
          <dc:identifier>
            <xsl:apply-templates select="tei:fileDesc/tei:editionStmt/tei:edition/@xml:base"/>
          </dc:identifier>
        </xsl:when>
      </xsl:choose>
      <!-- n? description -->
      <xsl:apply-templates select="tei:fileDesc/tei:notesStmt/tei:note[@type='abstract']"/>
      <!-- n? language -->
      <xsl:choose>
        <xsl:when test="tei:profileDesc/tei:langUsage/tei:language">
          <xsl:apply-templates select="tei:profileDesc/tei:langUsage/tei:language"/>
        </xsl:when>
        <xsl:when test="/*/@xml:lang">
          <xsl:apply-templates select="/*/@xml:lang"/>
        </xsl:when>
      </xsl:choose>
      <!-- 1! rights -->
      <xsl:apply-templates select="tei:fileDesc/tei:publicationStmt/tei:availability"/>
      <!-- 1? source -->
      <xsl:apply-templates select="tei:fileDesc/tei:sourceDesc/tei:bibl[1]"/>
      <!-- n? subject -->
      <xsl:apply-templates select="tei:profileDesc/tei:textClass//tei:term"/>
      <dc:type xsi:type="dcterms:DCMIType">Text</dc:type>
    </oai_dc:dc>
  </xsl:template>
  
  <!-- 
    METADATA
  -->
  
  <!-- http://weboai.sourceforge.net/teiHeader.html#el_idno -->
  <!-- obligatoire, unique -->
  <xsl:template match="tei:publicationStmt/tei:idno">
    <xsl:choose>
      <xsl:when test=". != '' and . != '?'">
        <dc:identifier>
          <xsl:apply-templates select="@type"/>
          <xsl:apply-templates/>
        </dc:identifier>
        <xsl:choose>
          <xsl:when test="@type = 'tei'">
            <dc:format>text/xml</dc:format>
          </xsl:when>
          <xsl:when test="@type = 'epub'">
            <dc:format>application/epub+zip</dc:format>
          </xsl:when>
          <xsl:when test="@type = 'txt'">
            <dc:format>text/plain</dc:format>
          </xsl:when>
          <xsl:when test="@type = 'text'">
            <dc:format>text/plain</dc:format>
          </xsl:when>
          <xsl:when test="@type = 'html'">
            <dc:format>text/html</dc:format>
          </xsl:when>
          <xsl:otherwise>
            <dc:format>text/html</dc:format>
          </xsl:otherwise>
        </xsl:choose>
      </xsl:when>
    </xsl:choose>
  </xsl:template>
  <xsl:template match="tei:idno/@type">
    <xsl:attribute name="xsi:type">
      <xsl:value-of select="translate(., '-', '/')"/>
    </xsl:attribute>
  </xsl:template>
  
  <!-- http://weboai.sourceforge.net/teiHeader.html#el_title -->
  <!-- obligatoire, unique -->
  <xsl:template match="tei:titleStmt/tei:title">
    <dc:title>
      <xsl:copy-of select="@xml:lang"/>
      <xsl:variable name="text">
        <xsl:apply-templates/>
      </xsl:variable>
      <xsl:value-of select="normalize-space($text)"/>
    </dc:title>
  </xsl:template>
  
  <!-- http://weboai.sourceforge.net/teiHeader.html#el_author -->
  <!-- http://weboai.sourceforge.net/teiHeader.html#el_editor -->
  <!-- http://weboai.sourceforge.net/teiHeader.html#el_publisher -->
  <!-- optionnel, répétable -->
  <xsl:template match="tei:author | tei:principal | tei:titleStmt/tei:editor | tei:publicationStmt/tei:publisher" name="pers">
    <xsl:variable name="text">
      <xsl:choose>
        <xsl:when test="@key">
          <xsl:value-of select="@key"/>
        </xsl:when>
  <!-- Because life is life
<author role="auteur">
<persName key="pers1">
      <surname>Rabelais</surname><forename>François</forename>
</persName>
</author>
  -->
        <xsl:when test=".//tei:surname">
          <xsl:apply-templates select=".//tei:surname" mode="txt"/>
          <xsl:if test=".//tei:forename">
            <xsl:text>, </xsl:text>
          <xsl:apply-templates select=".//tei:forename" mode="txt"/>
          </xsl:if>
        </xsl:when>
        <xsl:otherwise>
          <xsl:apply-templates mode="txt"/>
        </xsl:otherwise>
      </xsl:choose>
    </xsl:variable>
    <xsl:choose>
      <xsl:when test="normalize-space($text)=''"/>
      <xsl:when test="self::tei:author">
        <dc:creator>
          <xsl:value-of select="normalize-space($text)"/>
        </dc:creator>
      </xsl:when>
      <xsl:when test="self::tei:editor or self::tei:principal">
        <dc:contributor>
          <xsl:value-of select="normalize-space($text)"/>
          <xsl:text> (</xsl:text>
          <xsl:call-template name="message">
            <xsl:with-param name="id" select="concat(local-name(), '-role')"/>
          </xsl:call-template>
          <xsl:text>)</xsl:text>
        </dc:contributor>
      </xsl:when>
      <xsl:when test="self::tei:publisher">
        <dc:publisher>
          <xsl:value-of select="normalize-space($text)"/>
        </dc:publisher>
      </xsl:when>
      <xsl:otherwise>
        <xsl:message><xsl:value-of select="$filename"/> : <xsl:value-of select="$text"/> (role?)</xsl:message>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
  <xsl:template match="tei:note" mode="txt"/>
  
  <!-- http://weboai.sourceforge.net/teiHeader.html#el_licence -->
  <!-- obligatoire, unique -->
  <xsl:template match="tei:availability">
      <xsl:choose>
        <xsl:when test="tei:licence/@target = '' or tei:licence/@target = '?'"/>
        <xsl:when test="tei:licence/@target">
          <dc:rights>
            <xsl:value-of select="tei:licence/@target"/>
          </dc:rights>
        </xsl:when>
        <xsl:when test=". ='' or . ='?'"/>
        <xsl:otherwise>
          <dc:rights>
            <xsl:value-of select="normalize-space(.)"/>
          </dc:rights>
        </xsl:otherwise>
      </xsl:choose>
  </xsl:template>
  
  <!-- http://weboai.sourceforge.net/teiHeader.html#el_idno_2 -->
  <!-- optionnel, ? -->
  <!-- revoir pertinence en fonction de l’implémentation oai des moissonneurs -->
  <xsl:template match="tei:seriesStmt/tei:idno">
    <dcterms:isPartOf><xsl:apply-templates/></dcterms:isPartOf>
  </xsl:template>
  
  <!-- http://weboai.sourceforge.net/teiHeader.html#el_note -->
  <!-- optionnel, répétable par langue -->
  <xsl:template match="tei:notesStmt/tei:note[@type='abstract']">
    <dc:description>
      <xsl:copy-of select="@xml:lang"/>
      <xsl:apply-templates/>
    </dc:description>
  </xsl:template>
  
  <!-- TODO l’URI de la vignette en note ne relève pas de l’OAI mais de la seule application Weboai -> on fait quoi ? 
  [FG] pas de vignette, le client ne peut pas savoir le format dont on a besoin, une image de couverture suffit
  Mais de toute façon, pour l’application, il n’y a de vignette que par corpus, fournies par l’extérieur
  -->
  
  <!-- http://weboai.sourceforge.net/teiHeader.html#el_bibl -->
  <!-- unique, obligatoire -->
  <xsl:template match="tei:sourceDesc/tei:bibl">
    <xsl:choose>
      <xsl:when test=".='' or .='?'"/>
      <xsl:otherwise>
        <xsl:variable name="txt">
          <xsl:apply-templates mode="txt"/>
        </xsl:variable>
        <dc:source><xsl:value-of select="normalize-space($txt)"/></dc:source>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
  
  <!-- http://weboai.sourceforge.net/teiHeader.html#el_creation -->
  <!-- unique, obligatoire -->
  <xsl:template match="tei:date">
    <dc:date>
      <xsl:call-template name="year"/>
    </dc:date>
  </xsl:template>

  <xsl:template match="tei:language">
    <dc:language>
      <xsl:value-of select="@ident"/>
    </dc:language>
  </xsl:template>
  
  <xsl:template name="lang">
    <xsl:param name="code" select="@xml:lang|@ident"/>
    <xsl:variable name="lang" select="translate($code, $ABC, $abc)"/>
    <xsl:choose>
      <xsl:when test="$lang = 'fr'">fre</xsl:when>
      <xsl:when test="$lang = 'en'">eng</xsl:when>
      <xsl:when test="$lang = 'la'">lat</xsl:when>
      <xsl:when test="$lang = 'gr'">grc</xsl:when>
      <xsl:when test="$lang = 'xx'">xxx</xsl:when>
    </xsl:choose>
  </xsl:template>

  <!-- Get a year from a date tag with different possible attributes -->
  <xsl:template match="*" mode="year" name="year">
    <xsl:choose>
      <xsl:when test="@when">
        <xsl:value-of select="substring(@when,1,4)"/>
      </xsl:when>
      <xsl:when test="@notAfter">
        <xsl:value-of select="substring(@notAfter,1,4)"/>
      </xsl:when>
      <xsl:when test="@notBefore">
        <xsl:value-of select="substring(@notBefore,1,4)"/>
      </xsl:when>
      <xsl:otherwise>
        <xsl:variable name="text" select="string(.)"/>
        <!-- try to find a year -->
        <xsl:variable name="XXXX" select="translate($text,'0123456789', '##########')"/>
        <xsl:choose>
          <xsl:when test="contains($XXXX, '####')">
            <xsl:variable name="pos" select="string-length(substring-before($XXXX,'####')) + 1"/>
            <xsl:value-of select="substring($text, $pos, 4)"/>
          </xsl:when>
        </xsl:choose>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
  
  <!-- http://weboai.sourceforge.net/teiHeader.html#el_term -->
  <!-- optionnel, répétable -->
  <xsl:template match="tei:textClass//tei:term">
    <dc:subject><xsl:apply-templates/></dc:subject>
  </xsl:template>
 
</xsl:transform>