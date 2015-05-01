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
  xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
  xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
  exclude-result-prefixes="date dc dcterms oai oai_dc rdf rdfs tei"
  >
  <xsl:import href="common.xsl"/>
  <xsl:param name="css">
    <xsl:choose>
      <xsl:when test="processing-instruction('css')">
        <xsl:value-of select="processing-instruction('css')"/>
      </xsl:when>
      <xsl:otherwise>lib/weboai.css</xsl:otherwise>
    </xsl:choose>
  </xsl:param>
  <xsl:param name="js">
    <xsl:choose>
      <xsl:when test="processing-instruction('js')">
        <xsl:value-of select="processing-instruction('js')"/>
      </xsl:when>
      <xsl:otherwise>lib/Sortable.js</xsl:otherwise>
    </xsl:choose>
  </xsl:param>
  <xsl:param name="repositoryName">
    <xsl:choose>
      <xsl:when test="//oai:repositoryName">
        <xsl:value-of select="//oai:repositoryName"/>
      </xsl:when>
      <xsl:when test="processing-instruction('repositoryName')">
        <xsl:value-of select="processing-instruction('repositoryName')"/>
      </xsl:when>
      <xsl:otherwise>Weboai</xsl:otherwise>
    </xsl:choose>
  </xsl:param>
  <xsl:template match="/">
    <html>
      <head>
        <meta http-equiv="Content-type" content="text/html; charset=UTF-8" />
        <title>
          <xsl:value-of select="$repositoryName"/>
        </title>
        <link rel="stylesheet" type="text/css" href="{$css}"/>
      </head>
      <body class="oai">
        <main>
          <xsl:if test="not(//oai:repositoryName)">
            <h1>
              <a href="?verb=ListSets">
                <xsl:value-of select="$repositoryName"/>
              </a>
            </h1>
          </xsl:if>
          <xsl:apply-templates/>
        </main>
        <script type="text/javascript" src="{$js}">//</script>
        <script type="text/javascript" xml:space="preserve">Sortable.load();</script>
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
  <xsl:template match="oai:ListIdentifiers">
    <ul class="ListIdentifiers">
      <xsl:apply-templates/>
    </ul>
  </xsl:template>
  <xsl:template match="oai:ListIdentifiers/oai:header">
    <li class="header">
      <xsl:apply-templates/>
    </li>
  </xsl:template>
  <xsl:template match="oai:ListRecords">
    <table width="100%" class="sortable" cellpadding="0" cellspacing="0">
      <caption>
        <xsl:call-template name="message">
          <xsl:with-param name="id">set</xsl:with-param>
        </xsl:call-template>
        <xsl:text> </xsl:text>
        <xsl:value-of select="../oai:request/@set "/>
      </caption>
      <thead>
        <tr>
          <th>
            <xsl:call-template name="message">
              <xsl:with-param name="id">no</xsl:with-param>
            </xsl:call-template>
          </th>
          <th>
            <xsl:call-template name="message">
              <xsl:with-param name="id">title</xsl:with-param>
            </xsl:call-template>
          </th>
          <th>
            <xsl:call-template name="message">
              <xsl:with-param name="id">creator</xsl:with-param>
            </xsl:call-template>
          </th>
          <th>
            <xsl:call-template name="message">
              <xsl:with-param name="id">date</xsl:with-param>
            </xsl:call-template>
          </th>
          <th>
            <xsl:call-template name="message">
              <xsl:with-param name="id">see</xsl:with-param>
            </xsl:call-template>
          </th>
        </tr>
      </thead>
      <tbody>
        <xsl:apply-templates/>
      </tbody>
    </table>
  </xsl:template>
  <xsl:template match="oai:ListRecords/oai:record">
    <tr>
      <th>
        <xsl:number/>
      </th>
      <td>
        <a href="?verb=GetRecord&amp;identifier={oai:header/oai:identifier}">
          <xsl:value-of select="oai:metadata/oai_dc:dc/dc:title"/>
        </a>
      </td>
      <td>
        <xsl:value-of select="oai:metadata/oai_dc:dc/dc:creator"/>
      </td>
      <td>
        <xsl:value-of select="oai:metadata/oai_dc:dc/dc:date"/>
      </td>
      <td>
        <xsl:choose>
          <xsl:when test="oai:metadata/oai_dc:dc/dc:identifier">
            <a href="{oai:metadata/oai_dc:dc/dc:identifier}">
              <xsl:choose>
                <xsl:when test="oai:metadata/oai_dc:dc/dc:publisher">
                  <xsl:value-of select="oai:metadata/oai_dc:dc/dc:publisher"/>
                </xsl:when>
                <xsl:otherwise>
                  <xsl:value-of select="oai:metadata/oai_dc:dc/dc:identifier"/>
                </xsl:otherwise>
              </xsl:choose>
            </a>
          </xsl:when>
          <xsl:when test="oai:metadata/oai_dc:dc/dc:publisher">
            <xsl:value-of select="oai:metadata/oai_dc:dc/dc:publisher"/>
          </xsl:when>
        </xsl:choose>
      </td>
    </tr>
  </xsl:template>
  <xsl:template match="oai:request">
    <a class="{local-name()}" href="{normalize-space(.)}">
      <xsl:apply-templates/>
    </a>
  </xsl:template>
  <xsl:template match="oai:header/oai:setSpec">
    <div class="{local-name()}">
      <label>
        <xsl:call-template name="message"/>
      </label>
      <a href="?verb=ListRecords&amp;set={.}">
        <xsl:value-of select="."/>
      </a>
    </div>
  </xsl:template>
  <xsl:template match="oai:set">
    <a class="set" href="?verb=ListRecords&amp;set={oai:setSpec}">
      <table class="set">
        <xsl:variable name="desc" select="oai:setDescription/oai_dc:dc/dc:description"/>
        <xsl:if test="$desc != ''">
          <xsl:attribute name="title">
            <xsl:value-of select="normalize-space($desc)"/>
          </xsl:attribute>
        </xsl:if>
        <tr>
          <td class="publisher">
            <xsl:apply-templates select="oai:setDescription/oai_dc:dc/dc:publisher"/>
          </td>
        </tr>
        <tr>
          <td class="title">
            <xsl:choose>
              <xsl:when test="oai:setDescription/oai_dc:dc/dc:title != ''">
                <xsl:apply-templates select="oai:setDescription/oai_dc:dc/dc:title"/>
              </xsl:when>
              <xsl:otherwise>
                <xsl:apply-templates select="oai:setName"/>
              </xsl:otherwise>
            </xsl:choose>
          </td>
        </tr>
        <tr>
          <td class="count">
            <xsl:text>[</xsl:text>
              <xsl:apply-templates select="oai:setSpec"/>
            <xsl:text>]</xsl:text>
          </td>
        </tr>
      </table>
    </a>
  </xsl:template>
  <xsl:template match="oai:GetRecord/oai:record">
    <article class="record">
      <xsl:apply-templates/>
    </article>
  </xsl:template>
  <xsl:template match="oai:GetRecord/oai:record/oai:header">
    <header>
      <xsl:apply-templates/>
    </header>
  </xsl:template>
  <xsl:template match="oai:identifier">
    <div class="oai_identifier">
      <label>
        <xsl:call-template name="message">
          <xsl:with-param name="id">oai_identifier</xsl:with-param>
        </xsl:call-template>
      </label>
      <a href="?verb=GetRecord&amp;identifier={.}">
        <xsl:value-of select="."/>
      </a>
    </div>
  </xsl:template>
  <xsl:template match="oai:debug">
    <pre>
      <xsl:copy-of select="node()"/>
      <xsl:text>
</xsl:text>
    </pre>
  </xsl:template>
  <xsl:template match="dc:*">
    <xsl:variable name="message">
      <xsl:call-template name="message"/>
    </xsl:variable>
    <xsl:variable name="el">
    <xsl:choose>
      <xsl:when test="self::dc:source">p</xsl:when>
      <xsl:otherwise>div</xsl:otherwise>
    </xsl:choose>
    </xsl:variable>
    <xsl:element name="{$el}">
      <xsl:attribute name="class">
        <xsl:value-of select="local-name()"/>
      </xsl:attribute>
      <xsl:if test="normalize-space($message) != ''">
        <label>
          <xsl:copy-of select="$message"/>
          <xsl:text> : </xsl:text>
        </label>
      </xsl:if>
      <xsl:apply-templates/>
    </xsl:element>
  </xsl:template>
  <xsl:template match="dc:title">
    <div class="title">
      <xsl:choose>
        <xsl:when test="ancestor::oai:setDescription">
          <xsl:apply-templates/>
        </xsl:when>
        <xsl:when test="../dc:identifier">
          <a href="{../dc:identifier}">
            <xsl:apply-templates/>
          </a>
        </xsl:when>
        <xsl:otherwise>
          <xsl:apply-templates/>
        </xsl:otherwise>
      </xsl:choose>
    </div>
  </xsl:template>
  <xsl:template match="oai_dc:dc">
    <div class="dc">
      <xsl:apply-templates/>
    </div>
  </xsl:template>
  <xsl:template match="oai:datestamp | dc:format | dc:language | oai:request | oai:responseDate | dc:type"/>
  <xsl:template match="oai:repositoryName">
    <h1>
      <a>
        <xsl:attribute name="href">
          <xsl:choose>
            <xsl:when test="false() and ../oai:request">
              <xsl:value-of select="../oai:request"/>
            </xsl:when>
            <xsl:otherwise>
              <xsl:text>?verb=ListSets</xsl:text>
            </xsl:otherwise>
          </xsl:choose>
        </xsl:attribute>
        <xsl:apply-templates/>
      </a>
    </h1>
  </xsl:template>
  <xsl:template match="dc:identifier | dc:rights">
    <div class="{local-name()}">
      <xsl:if test="self::dc:rights">
        <label>
          <xsl:call-template name="message"/>
        </label>
      </xsl:if>
      <xsl:choose>
        <xsl:when test="starts-with(., 'http')">
          <a>
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
          <xsl:apply-templates/>
        </xsl:otherwise>
      </xsl:choose>
    </div>
  </xsl:template>
</xsl:transform>