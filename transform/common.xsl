<?xml version="1.0" encoding="UTF-8"?>
<xsl:transform version="1.1"
  xmlns="http://www.w3.org/1999/xhtml"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  
  xmlns:date="http://exslt.org/dates-and-times"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:dcterms="http://purl.org/dc/terms/"
  xmlns:oai="http://www.openarchives.org/OAI/2.0/"
  xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"
  xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
  xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
  xmlns:tei="http://www.tei-c.org/ns/1.0"
  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
  exclude-result-prefixes="date dc dcterms oai oai_dc rdf rdfs tei"
  >
  <xsl:variable name="iso">ABCDEFGHIJKLMNOPQRSTUVWXYZÀÂÄÉÈÊÏÎÔÖÛÜÇàâäéèêëïîöôüû ,.</xsl:variable>
  <xsl:variable name="min">abcdefghijklmnopqrstuvwxyzaaaeeeiioouucaaaeeeeiioouu-</xsl:variable>
  <xsl:param name="debug" select="processing-instruction('debug')"/>
  <!-- Langue du document, sert aussi pour les messages générés -->
  <xsl:param name="lang">
    <xsl:choose>
      <xsl:when test="/*/@xml:lang">
        <xsl:value-of select="/*/@xml:lang"/>
      </xsl:when>
      <xsl:when test="//dc:language">
        <xsl:value-of select="//dc:language"/>
      </xsl:when>
      <xsl:otherwise>fr</xsl:otherwise>
    </xsl:choose>
  </xsl:param>
    <!-- <*>, modèle par défaut d'interception des éléments non pris en charge -->
  <xsl:template name="tag">
    <div>
    <b style="color:red">
      <xsl:text>&lt;</xsl:text>
      <xsl:value-of select="name()"/>
      <xsl:for-each select="@*">
        <xsl:text> </xsl:text>
        <xsl:value-of select="name()"/>
        <xsl:text>="</xsl:text>
        <xsl:value-of select="."/>
        <xsl:text>"</xsl:text>
      </xsl:for-each>
      <xsl:text>&gt;</xsl:text>
    </b>
      <xsl:apply-templates/>
      <b style="color:red">
        <xsl:text>&lt;/</xsl:text>
        <xsl:value-of select="name()"/>
        <xsl:text>&gt;</xsl:text>
      </b>
    </div>
  </xsl:template>

  <!--  charger le fichier de messages, document('') permet de résoudre les chemin relativement à ce fichier  -->
  <xsl:variable name="rdf:Property" select="document('weboai.rdfs', document(''))/*/rdf:Property"/>
  <!-- Message, intitulé court d'un élément TEI lorsque disponible -->
  <xsl:template name="message">
    <xsl:param name="id" select="local-name()"/>
    <xsl:choose>
      <xsl:when test="$rdf:Property[@xml:id = $id]/rdfs:label[starts-with( $lang, @xml:lang)]">
        <xsl:copy-of select="$rdf:Property[@xml:id = $id]/rdfs:label[starts-with( $lang, @xml:lang)][1]/node()"/>
      </xsl:when>
      <xsl:when test="$rdf:Property[@xml:id = $id]/rdfs:label">
        <xsl:copy-of select="$rdf:Property[@xml:id = $id]/rdfs:label[1]/node()"/>
      </xsl:when>
      <xsl:otherwise>
        <xsl:value-of select="$id"/>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
</xsl:transform>