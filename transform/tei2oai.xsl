<?xml version="1.0" encoding="UTF-8"?>
<!--
© 2013, <a href="http://www.algone.net/">Algone</a>, licence  <a href="http://www.cecill.info/licences/Licence_CeCILL-C_V1-fr.html">CeCILL-C</a>/<a href="http://www.gnu.org/licenses/lgpl.html">LGPL</a>
<ul>
  <li>[VJ] <a href="#" onmouseover="this.href='mailto'+'\x3A'+'jolivet'+'\x40'+'algone.net'">Vincent Jolivet</a></li>
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
  <xsl:output encoding="UTF-8" indent="yes" method="xml"/>
  <xsl:param name="oai_id_prefix">CAHIER:</xsl:param>
  <!-- name of the TEI file  -->
  <xsl:param name="filename"/>
  
  <xsl:template match="/">
    <xsl:apply-templates select="tei:TEI/tei:teiHeader"/>
  </xsl:template>
    
  <xsl:template match="tei:teiHeader">
    <record>
      <header>
        <identifier>
          <xsl:value-of select="$oai_id_prefix"/>
          <xsl:value-of select="$filename"/>
        </identifier>
        <datestamp><xsl:value-of select="date:date-time()"/></datestamp><!-- date du jour ou date de publi (tei:fileDesc/tei:publicationStmt/tei:date/@when) ? -->
        <setSpec>SHS</setSpec><!-- voir la liste des valeurs ; on articule avec textClass ? -->
      </header>
      <metadata>
        <oai_dc:dc xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd">
          <!-- closed, do not allow ordering, or to test if an expected element is not there
          <xsl:apply-templates select="
            tei:fileDesc/tei:publicationStmt/tei:idno |
            tei:fileDesc/tei:titleStmt/tei:title[1] |
            tei:fileDesc/tei:titleStmt/tei:author |
            tei:fileDesc/tei:titleStmt/tei:editor |
            tei:fileDesc/tei:publicationStmt/tei:publisher |
            tei:fileDesc/tei:publicationStmt/tei:availability |
            tei:fileDesc/tei:seriesStmt/tei:idno |
            tei:fileDesc/tei:notesStmt/tei:note[@type='abstract'] |
            tei:fileDesc/tei:sourceDesc/tei:bibl |
            tei:profileDesc/tei:creation |
            tei:profileDesc/tei:textClass//tei:term[@type='subject']"/>
            -->
          <!-- 1! identifier -->
          <xsl:apply-templates select="tei:fileDesc/tei:publicationStmt/tei:idno[1]"/>
          <!-- 1! title -->
          <!-- TODO: Aggregate subtitles ? translated titles ? -->
          <xsl:apply-templates select="tei:fileDesc/tei:titleStmt/tei:title[1]"/>
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
          <!-- 1? significant date -->
          <xsl:choose>
            <xsl:when test="tei:profileDesc/tei:creation/tei:date">
              <xsl:apply-templates select="tei:profileDesc/tei:creation/tei:date[1]"/>
            </xsl:when>
            <!-- specific BVH, msDesc before a <bibl> ? -->
            <xsl:when test="tei:fileDesc/tei:sourceDesc/tei:msDesc[1]//tei:biblStruct">
              <!-- Date can be in <imprint>, or not -->
              <xsl:for-each select="tei:fileDesc/tei:sourceDesc/tei:msDesc[1]//tei:biblStruct//tei:date[1]">
                <xsl:apply-templates select="."/>
              </xsl:for-each>
            </xsl:when>
            <xsl:when test="tei:fileDesc/tei:sourceDesc/tei:bibl[1][tei:date]">
              <xsl:apply-templates select="tei:fileDesc/tei:sourceDesc/tei:bibl[1][tei:date]/tei:date[1]"/>
            </xsl:when>
          </xsl:choose>
          <!-- n? contributor -->
          <xsl:apply-templates select="tei:fileDesc/tei:titleStmt/tei:editor"/>
          <!-- n? description -->
          <xsl:apply-templates select="tei:fileDesc/tei:notesStmt/tei:note[@type='abstract']"/>
          <!-- n? publisher -->
          <xsl:apply-templates select="tei:fileDesc/tei:publicationStmt/tei:publisher"/>
          <!-- 1! rights -->
          <xsl:apply-templates select="tei:fileDesc/tei:publicationStmt/tei:availability"/>
          <!-- 1? source -->
          <xsl:apply-templates select="tei:fileDesc/tei:sourceDesc/tei:bibl[1]"/>
          <!-- n? subject -->
          <xsl:apply-templates select="tei:profileDesc/tei:textClass//tei:term"/>
        </oai_dc:dc>
      </metadata>
    </record>
  </xsl:template>
  
  <!-- 
    METADATA
  -->
  
  <!-- http://weboai.sourceforge.net/teiHeader.html#el_idno -->
  <!-- obligatoire, unique -->
  <xsl:template match="tei:publicationStmt/tei:idno">
    <dc:identifier><xsl:apply-templates/></dc:identifier>
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
  <xsl:template match="tei:author | tei:titleStmt/tei:editor | tei:publicationStmt/tei:publisher" name="pers">
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
      <xsl:when test="self::tei:editor">
        <dc:contributor>
          <xsl:value-of select="normalize-space($text)"/>
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
    <dc:rights>
      <xsl:choose>
        <xsl:when test="tei:licence/@target">
          <xsl:value-of select="tei:licence/@target"/>
        </xsl:when>
        <xsl:otherwise>
          <xsl:value-of select="normalize-space(.)"/>
        </xsl:otherwise>
      </xsl:choose>
    </dc:rights>
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
    <dc:source><xsl:value-of select="normalize-space(.)"/></dc:source>
  </xsl:template>
  
  <!-- http://weboai.sourceforge.net/teiHeader.html#el_creation -->
  <!-- unique, obligatoire -->
  <xsl:template match="tei:date">
    <dc:date>
      <xsl:call-template name="year"/>
    </dc:date>
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