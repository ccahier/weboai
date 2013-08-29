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
  
  <xsl:template match="/">
    <xsl:apply-templates select="tei:TEI/tei:teiHeader"/>
  </xsl:template>
    
  <xsl:template match="tei:teiHeader">
    <record>
      <header>
        <identifier>TODO : voir règle de construction ; oai:</identifier>
        <datestamp><xsl:value-of select="date:date-time()"/></datestamp><!-- date du jour ou date de publi (tei:fileDesc/tei:publicationStmt/tei:date/@when) ? -->
        <setSpec>SHS</setSpec><!-- voir la liste des valeurs ; on articule avec textClass ? -->
      </header>
      <metadata>
        <oai_dc:dc xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd">
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
  <xsl:template match="tei:titleStmt/tei:title[1]">
    <dc:title><xsl:apply-templates/></dc:title>
  </xsl:template>
  
  <!-- http://weboai.sourceforge.net/teiHeader.html#el_author -->
  <!-- obligatoire, répétable -->
  <xsl:template match="tei:titleStmt/tei:author">
    <dc:creator><xsl:apply-templates select="@key"/></dc:creator>
  </xsl:template>
  
  <!-- http://weboai.sourceforge.net/teiHeader.html#el_editor -->
  <!-- optionnel, répétable -->
  <xsl:template match="tei:titleStmt/tei:editor">
    <dc:contributor><xsl:apply-templates/></dc:contributor>
  </xsl:template>
  
  <!-- http://weboai.sourceforge.net/teiHeader.html#el_publisher -->
  <!-- obligatoire, répétable ; TODO : normaliser les valeurs in schematron... -->
  <xsl:template match="tei:publicationStmt/tei:publisher">
    <dc:publisher><xsl:apply-templates/></dc:publisher>
  </xsl:template>
  
  <!-- http://weboai.sourceforge.net/teiHeader.html#el_licence -->
  <!-- obligatoire, unique -->
  <xsl:template match="tei:availability">
    <dc:rights><xsl:apply-templates select="tei:licence/@target"/></dc:rights>
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
    <dc:description><xsl:apply-templates/></dc:description>
  </xsl:template>
  
  <!-- TODO l’URI de la vignette en note ne relève pas de l’OAI mais de la seule application Weboai -> on fait quoi ? -->
  
  <!-- http://weboai.sourceforge.net/teiHeader.html#el_bibl -->
  <!-- unique, obligatoire -->
  <xsl:template match="tei:sourceDesc/tei:bibl">
    <dc:source><xsl:apply-templates/></dc:source>
  </xsl:template>
  
  <!-- http://weboai.sourceforge.net/teiHeader.html#el_creation -->
  <!-- unique, obligatoire -->
  <xsl:template match="tei:profileDesc/tei:creation">
    <dc:date><xsl:apply-templates select="tei:date/@when"/></dc:date>
  </xsl:template>
  
  <!-- http://weboai.sourceforge.net/teiHeader.html#el_term -->
  <!-- optionnel, répétable -->
  <!-- TODO revoir le sélecteur Xpath, plus ou moins permissif -->
  <xsl:template match="tei:textClass//tei:term[@type='subject']">
    <dc:subject><xsl:apply-templates/></dc:subject>
  </xsl:template>
 
</xsl:transform>