
-- Format of an SQLite base feed with OAI
CREATE TABLE record (
  -- OAI record of a resource, should be enough for an OAI engine, and to display a short result for the resource
  oai_datestamp   TEXT NOT NULL,          -- ! OAI record's submission 
  oai_identifier  TEXT UNIQUE NOT NULL,   -- ! local OAI identifier used by harvester to get, update, delete records
  oai             BLOB NOT NULL,          -- ! the oai xml record
  html            BLOB,                   -- ! a displayable title page for web navigation
  teiheader       BLOB,                   -- ! store xml <teiHeader> maybe useful for later transformations
  identifier      TEXT,                   -- ! a link for the full-text, should be unique 
  title           TEXT NOT NULL,          -- ! dc:title, just for display 
  byline          TEXT,                   -- ? optional, texts may not have authors, dc:author x n, just for display 
  date            INTEGER NOT NULL,       -- ! dc:date, creation date of the text, should be not null
  date2           INTEGER,                -- ? second important date in life of resoource (ex: edition date of a medieval text)
  publisher       TEXT,                   -- ! required, publisher of electronic resource
  issued          INTEGER,                -- ? publication date of electronic resource
  deleted BOOLEAN NOT NULL DEFAULT FALSE  -- ! required by OAI protocol to inform harvester but not supported
);

CREATE VIRTUAL TABLE ft USING FTS3 (
  -- fulltext fields to find records for a public interface
  heading      TEXT,  -- aggregation of titles and authors for full-text search
  description  TEXT   -- aggregation of abstracts for full-text search
);


CREATE TABLE author (
  -- person facets for dc:creator or dc:contributor
  heading         TEXT NOT NULL, -- normalize key, ex: Baudelaire, Charles (1821-1867)
  family          TEXT NOT NULL, -- family name, ex: Baudelaire
  given           TEXT, -- given name, ex: Charles (could be null, medieval)
  sort            TEXT NOT NULL, -- full key with no diacritics for search with like
  sort1           TEXT NOT NULL, -- ! family name as a sortable key with no spaces or diacritics, ex: baudelaire
  sort2           TEXT, -- ! given name as a sortable key with no spaces or diacritics, ex: charles (may be null)
  birth           INTEGER, -- ? year of birth
  death           INTEGER, -- ? year of death
  uri             TEXT,  -- ? an URI identifier for the entity
  protect         INTEGER  -- protected from automatic deletion (ex: external referential)
);
CREATE INDEX author_heading ON author(heading);
CREATE INDEX author_family  ON author(family);
CREATE INDEX author_given   ON author(given);
CREATE INDEX author_sort1   ON author(sort1);
CREATE INDEX author_sort2   ON author(sort2);
CREATE INDEX author_protect ON author(protect);

CREATE TABLE writes (
  -- relation table
  author      INTEGER NOT NULL REFERENCES author(rowid),
  record      INTEGER NOT NULL REFERENCES record(rowid),
  role        INTEGER NOT NULL -- 1=dc:creator | 2=dc:contributor (NB: dc:contributor = tei:editor)
);
CREATE INDEX writes_author   ON writes(author);
CREATE INDEX writes_record   ON writes(record); -- revoir la sémantique de la base ???? [FG] Pourquoi ? plutôt "text" ?
CREATE INDEX writes_role     ON writes(role);


CREATE TABLE oaiset (
  -- external list of sets, also used for publishers http://www.openarchives.org/OAI/openarchivesprotocol.html#Set
  setspec      TEXT UNIQUE NOT NULL, -- OAI, setSpec, a colon [:] separated list indicating the path from the root of the set hierarchy to the respective node.
  setname      TEXT,    -- OAI, setName, a short human-readable string naming the set.
  publisher    TEXT,    -- the editor
  identifier   TEXT,    -- not in OAI protocol but useful for links
  title        TEXT,    -- A short description of the set
  description  TEXT,    -- OAI, setDescription, an optional and repeatable container that may hold community-specific XML-encoded data about the set
  sitemaptei   TEXT,    -- URI of a sitemap.xml, list of URIs pointing on XML-TEI source text
  oai          BLOB,    -- <set> XML description of set
  image        BLOB     -- not in OAI protocol, useful for human interface
);
CREATE INDEX oaiset_setspec ON oaiset(setspec);

CREATE TABLE member (
  oaiset      INTEGER NOT NULL REFERENCES oaiset(rowid),
  record      INTEGER NOT NULL REFERENCES record(rowid)
);
CREATE INDEX member_oaiset   ON member(oaiset);
CREATE INDEX member_record   ON member(record);



-- TRIGGERS

CREATE TRIGGER oaiset_del
  -- when a set is deleted, delete record from this source
  BEFORE DELETE ON oaiset
  FOR EACH ROW
  BEGIN
    DELETE FROM record WHERE rowid IN (SELECT record FROM member WHERE oaiset = OLD.rowid);
END;

CREATE TRIGGER oaiset_update
  -- when the source of a set is modified, delete old records
  BEFORE UPDATE OF sitemaptei ON oaiset
  FOR EACH ROW WHEN OLD.sitemaptei != NEW.sitemaptei
  BEGIN
    DELETE FROM record WHERE rowid IN (SELECT record FROM member WHERE oaiset = OLD.rowid);
END;

CREATE TRIGGER record_del
  -- on resource's record deletion, delete search index, relations to author (dc:creator | dc:contributor), relation to sets
  BEFORE DELETE ON record
  FOR EACH ROW 
  BEGIN
    DELETE FROM ft WHERE ft.rowid = OLD.rowid;
    DELETE FROM writes WHERE writes.record = OLD.rowid;
    DELETE FROM member WHERE member.record = OLD.rowid;
END;

CREATE TRIGGER writes_del
  -- delete orphan authors (dc:creator | dc:contributor) when not protected
  AFTER DELETE ON writes
  FOR EACH ROW WHEN NOT EXISTS (SELECT * FROM writes WHERE author=OLD.author)
  BEGIN
    DELETE FROM author WHERE rowid=OLD.author AND protect IS NULL;
END;