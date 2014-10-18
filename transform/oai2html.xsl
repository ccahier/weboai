<?xml version="1.0" encoding="UTF-8"?>
<xsl:transform version="1.1"
  xmlns="http://www.w3.org/1999/xhtml"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:oai="http://www.openarchives.org/OAI/2.0/"
  xmlns:date="http://exslt.org/dates-and-times"
  xmlns:tei="http://www.tei-c.org/ns/1.0"
  xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"
  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:dcterms="http://purl.org/dc/terms/"
  exclude-result-prefixes="date dc dcterms oai oai_dc tei"
  >
  <xsl:param name="css">local/cahier.css</xsl:param>
  <xsl:template match="/">
    <html>
      <head>
        <meta http-equiv="Content-type" content="text/html; charset=UTF-8" />
        <link rel="stylesheet" type="text/css" href="{$css}"/>
      </head>
      <body class="oai">
        <main>
          <xsl:apply-templates/>
        </main>
      </body>
    </html>
  </xsl:template>
  <xsl:template match="oai:error">
    <p class="error">
      <xsl:text>[</xsl:text>
      <xsl:value-of select="@code"/>
      <xsl:text>] </xsl:text>
      <xsl:apply-templates/>
    </p>
  </xsl:template>
  <xsl:template match="oai:error[@code='badVerb']">
    <p>Pour explorer cet entrepôt, commencez par <a href="?verb=ListSets">choisir une collection</a></p>
  </xsl:template>
  <xsl:template match="oai:set">
    <a class="set" href="?verb=ListRecords&amp;set={oai:setSpec}">
      <div class="set">
        <xsl:variable name="desc" select="oai:setDescription/oai_dc:dc/dc:description"/>
        <xsl:if test="$desc">
          <xsl:attribute name="title">
            <xsl:value-of select="normalize-space($desc)"/>
          </xsl:attribute>
        </xsl:if>
        <xsl:apply-templates select="oai:setName"/>
        <xsl:apply-templates select="oai:setDescription/oai_dc:dc/dc:publisher"/>
        <xsl:apply-templates select="oai:setDescription/oai_dc:dc/dc:title"/>
      </div>
    </a>
  </xsl:template>
  <xsl:template match="oai:request">
    <a class="{local-name()}" href="{normalize-space(.)}">
      <xsl:apply-templates/>
    </a>
  </xsl:template>
  <xsl:template match="oai:setName">
    <label>
      <xsl:text>[</xsl:text>
      <xsl:value-of select="../oai:setSpec"/>
      <xsl:text>] </xsl:text>
      <xsl:apply-templates/>
    </label>
  </xsl:template>
  <xsl:template match="oai:debug">
    <pre>
      <xsl:copy-of select="node()"/>
      <xsl:text>
</xsl:text>
    </pre>
  </xsl:template>
  <xsl:template match="dc:*">
    <div class="{local-name()}">
      <xsl:apply-templates/>
    </div>
  </xsl:template>
  <xsl:template match="dc:publisher">
    <a class="{local-name()}" href="{normalize-space(../dc:publisher)}">
      <xsl:apply-templates/>
    </a>
  </xsl:template>
  <xsl:template match="dc:identifier">
    <a class="{local-name()}" href="{normalize-space(.)}">
      <xsl:apply-templates/>
    </a>
  </xsl:template>
</xsl:transform>