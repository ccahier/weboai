<?xml version="1.0" encoding="UTF-8"?>
<!--
© 2013, <a href="http://www.algone.net/">Algone</a>, licence  <a href="http://www.cecill.info/licences/Licence_CeCILL-C_V1-fr.html">CeCILL-C</a>/<a href="http://www.gnu.org/licenses/lgpl.html">LGPL</a>
<ul>
  <li>[VJ] <a href="#" onmouseover="this.href='mailto'+'\x3A'+'jolivet'+'\x40'+'algone.net'">Vincent Jolivet</a></li>
  <li>[FG] <a href="#" onmouseover="this.href='mailto'+'\x3A'+'glorieux'+'\x40'+'algone.net'">Frédéric Glorieux</a></li>
</ul>
-->
<schema
  xmlns="http://purl.oclc.org/dsdl/schematron"
  xmlns:exsl="http://exslt.org/common"
  xmlns:set="http://exslt.org/sets"
  xmlns:date="http://exslt.org/dates-and-times"
  queryBinding="xslt">

  <ns uri="http://www.tei-c.org/ns/1.0" prefix="tei"/>
  <ns uri="http://exslt.org/common" prefix="exsl"/>
  <ns uri="http://exslt.org/sets" prefix="set"/>
  <ns uri="http://exslt.org/dates-and-times" prefix="date"/>
  <title>Validation du teiHeader en vue de la conversion OAI</title>
  
  <pattern>
    <title>Présence des champs obligatoires</title>
    <rule context="tei:titleStmt">
      <assert test="count(tei:title) >=1">Le titre (dc:title), obligatoire, est inscrit en valeur de l’élément title</assert>
      <assert test="count(tei:author|tei:principal) >= 1">Le texte doit être attribué (dc:creator) à un auteur [fileDesc/titleStmt/author] ou à un éditeur scientifique [fileDesc/titleStmt/principal].</assert>
    </rule>
    <rule context="tei:publicationStmt">
      <assert test="count(tei:idno) = 1">Une unique URI de référence doit être renseignée [fileDesc/publicationStmt/idno]. Elle sert d’identifiant (dc:identifier) à la notice.</assert>
      <assert test="count(tei:publisher) >=1">Au moins un éditeur de la ressource (dc:publisher) doit être renseigné [fileDesc/publicationStmt/publisher]</assert>
      <!-- à revoir car on ne déclare pas nécessairement le fichier source mais parfois la seule application de consultation -->
      <assert test="count(tei:availability/tei:licence) = 1">Une unique URI de référence de la licence de distribution (dc:rights) de la ressource doit être renseignée [fileDesc/publicationStmt/availability/licence/@target]</assert>
      <assert test="count(tei:date) = 1">Une unique date de publication de la ressource (dc:date) doit être renseignée.</assert>
    </rule>
    <rule context="tei:sourceDesc">
      <assert test="count(tei:bibl) = 1">Une unique référence bibliographique de la ressource (dc:source) doit être renseignée [fileDesc/sourceDesc/bibl]</assert>
    </rule>
    <rule context="tei:profileDesc">
      <assert test="count(tei:creation/tei:date) = 1">Une unique "date de création" du texte transcrit (dc:date) doit être renseignée [profileDesc/creation/date/@when]. Cette date sert au tri chronologique des œuvres.</assert>
      <assert test="count(tei:langUsage/tei:language) >=1 ">Au moins une langue du document (dc:language) doit être renseignée [profileDesc/langUsage/language/@ident].</assert>
    </rule>
  </pattern>
    
  <pattern>
    <title>Formatage des métadonnées</title>
    <!-- Revoir la règle de construction des identifiants -->
    <rule context="tei:idno">
      <assert test="starts-with(., 'http://')">Les identifiants idno doivent être de type xsd:anyURI (http://...)</assert>
    </rule>
    <!-- On reste aussi contraignant pour le formatage de l’éditeur scientifique ? // Revoir la règle pour le formatage NF Z 44-061 -->
    <rule context="tei:titleStmt/tei:author | tei:titleStmt/tei:editor">
      <assert test="@key">L’attribut @key est obligatoire pour permettre le tri alphabétique des auteurs.</assert>
      <assert test="contains(@key, ', ')">L’attribut @key [<value-of select="@key"/>] doit être renseigné conformément à la norme NF Z 44-061 [Nom, Prénom (naissance-mort)].</assert>
    </rule>
    <!-- Voir si on contraint l’inscription d’un @xml:id (@key) n’est pas autorisé en P5 ; quel identifiant en base ? -->
    <rule context="tei:publicationStmt/tei:publisher">
      <assert test="not(@xml:id) or (@xml:id='cesr') or (@xml:id='bfm') or (@xml:id='item')">L’identifiant de l’organisme responsable de la publication [<value-of select="@xml:id"/>] ne figure pas dans la liste d’autorité : cesr|bfm|item</assert>
    </rule>
    <rule context="tei:licence">
      <assert test="@target">L’attribut @target est obligatoire pour renseigner l’URI de référence de la licence de distribution.</assert>
      <assert test="starts-with(@target, 'http://')">L’URI de référence de la licence doit être de type xsd:anyURI (http://...)</assert>
    </rule>
    <rule context="tei:language">
      <assert test="@ident">L’attribut @ident est obligatoire pour renseigner la langue du document (dc:language) au format ISO 639-2.</assert>
    </rule>
  </pattern>
  
  <pattern>
    <title>Formatage des dates normalisées (@when)</title>
    <rule context="tei:date/@when">
      <let name="year" value="number(substring(.,1,4))"/>
      <let name="month" value="number(substring(.,6,2))"/>
      <let name="day" value="number(substring(.,9,2))"/>
      <assert test="string-length(.) = 4
        or (string-length(.) = 7 and substring(.,5,1) = '-')
        or (string-length(.) = 10 and substring(.,8,1) = '-')">
        La date [<value-of select="."/>] doit être normalisée : AAAA(-MM(-JJ)?)?
      </assert>
      <assert test="$year &lt;= date:year()">
        L’année [<value-of select="$year"/>] ne peut pas être postérieure à celle d’aujourd’hui (<value-of select="date:year()"/>).
      </assert>
      <assert test="($month >= 1 and $month &lt;= 12) or not($month)">
        Le mois [<value-of select="$month"/>] doit être compris entre 01 (janvier) et 12 (décembre).
      </assert>
      <assert test="($day >= 1 and $day &lt;= 31) or not($day)">
        Le jour [<value-of select="$day"/>] doit être compris entre 01 et 31.
      </assert>
    </rule>
  </pattern>
  
</schema>