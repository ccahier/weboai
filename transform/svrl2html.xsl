<!--
  voir base : https://code.google.com/p/schematron/source/browse/trunk/converters/code/FromSVRL/SVRLReportRender.xsl
--> 
<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:xs="http://www.w3.org/2001/XMLSchema"
  xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
  xmlns:sch="http://www.ascc.net/xml/schematron"
  xmlns:iso="http://purl.oclc.org/dsdl/schematron"
  xmlns="http://www.w3.org/1999/xhtml">
  <xsl:output encoding="UTF-8" method="html" />
  
  <xsl:template match="/">
    <html>
      <xsl:comment>no seeding</xsl:comment>
      <head>
        <title>SVRL Validation Report</title>
        <link rel="stylesheet" href="svrl-report.css" type="text/css" />
      </head>
      <body>
        <h1>SVRL Validation Report</h1>
        <xsl:apply-templates select="//svrl:file"/><!-- AJOUT VJ -->
        <xsl:apply-templates select="//svrl:failed-assert"/><!-- AJOUT VJ -->
        <!--
          <xsl:for-each select="fileset/file">
          <div class="file">
          <xsl:apply-templates select="." />
          </div>
          </xsl:for-each>
        --> 
      </body>
    </html>
  </xsl:template>
  
  <xsl:template match="svrl:file"><!-- modif VJ -->
    <div class="filename">
      <h2>Instance File Name: <xsl:value-of select="." />.xml</h2>
    </div>
    <!--
    <div class="status">
      <xsl:choose>
        <xsl:when test="(count(svrl:schematron-output/svrl:successful-report) + count(svrl:schematron-output/svrl:failed-assert)) != 0">
          <h3 class="no">Results: The file is Not Validated!</h3>
        </xsl:when>
        <xsl:otherwise>
          <h3 class="yes">Results: The file is Validated!</h3>
        </xsl:otherwise>
      </xsl:choose>
    </div>
    <div class="schematron">
      <div class="schematron-title">
        <p>Schematron Title: <xsl:value-of select="svrl:schematron-output/@title" /></p>
      </div>
      <div class="schematron-version">
        <p>Schematron Version: <xsl:value-of select="svrl:schematron-output/@schemaVersion" /></p>
      </div>
        <xsl:for-each select="svrl:ns-prefix-in-attribute-values">
        <div class="schematron-ns">
        <p>Schematron Namespace Prefix: <xsl:value-of select="@prefix" /></p>
        <p>Schematron Namespace URI: <xsl:value-of select="@uri" /></p>
        </div>
        </xsl:for-each>
      <div class="result">
        <xsl:apply-templates />
      </div>
    </div>
    -->
  </xsl:template>
  
  <xsl:template match="svrl:successful-report">
    <div class="result-report">
      <div class="result-report-test">
        <span class="label"><b>Test: </b></span>
        <xsl:value-of select="@test" />
      </div>
      <div class="result-report-location">
        <span class="label"><b>Location: </b></span>
        <xsl:value-of select="@location" />
      </div>
      <div class="result-report-text">
        <span class="label"><b>Description: </b></span>
        <xsl:value-of select="svrl:text" />
      </div>
    </div>
  </xsl:template>
  
  <xsl:template match="svrl:failed-assert">
    <div class="result-assert">
      <div class="result-assert-test">
        <span class="label"><b>Test: </b></span>
        <xsl:value-of select="@test" />
      </div>
      <div class="result-assert-location">
        <span class="label"><b>Location: </b></span>
        <xsl:value-of select="@location" />
      </div>
      <div class="result-assert-text">
        <span class="label"><b>Description: </b></span>
        <xsl:value-of select="svrl:text" />
      </div>
    </div>
  </xsl:template>
  
  <xsl:template match="text()">
  </xsl:template>
  
</xsl:stylesheet>
