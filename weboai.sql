-- TOHINK, implement sets, a record may be 

-- Format of an SQLite base feed with OAI
CREATE TABLE resource (
  -- OAI record of a resource, should be enough for an OAI engine, and to display a short result for the resource
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  deleted BOOLEAN NOT NULL DEFAULT FALSE, -- ! required by OAI protocol to inform harvester
  oai_datestamp   TEXT NOT NULL,          -- ! OAI record's submission date http://www.sqlite.org/lang_datefunc.html, time string format 6 : YYYY-MM-DDTHH:MM:SS
  oai_identifier  TEXT UNIQUE NOT NULL,   -- ! revoir la spec OAI
  record          TEXT NOT NULL,          -- ! the oai record
  title           TEXT NOT NULL,          -- ! dc:title, just for display 
  uri             TEXT,                   -- ! a link for the resource, should be unique dc:identifier, but life sometimes…
  date            INTEGER,                -- ! dc:date, creation date of the text, should be not null, but let people see it in their queries
  byline          TEXT,                   -- ? optional, texts may not have authors, dc:author x n, just for display 
  publisher       INTEGER                 -- ? a link to the table of publisher, specific to CAHIER 
);

CREATE VIRTUAL TABLE ft USING FTS3 (
  -- fulltext fields to find records for a public interface
  title        TEXT,  -- aggregation of titles and authors for full-text search
  description  TEXT   -- aggregation of abstracts for full-text search
);


CREATE TABLE author (
  -- person facets for dc:creator or dc:contributor
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
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
CREATE INDEX authorHeading ON author(heading);
CREATE INDEX authorFamily  ON author(family);
CREATE INDEX authorGiven   ON author(given);
CREATE INDEX authorSort1   ON author(sort1);
CREATE INDEX authorSort2   ON author(sort2);
CREATE INDEX authorProtect ON author(protect);

CREATE TABLE writes (
  -- relation table
  author      INTEGER NOT NULL REFERENCES author(id),
  resource    INTEGER REFERENCES resource(id),
  role        INTEGER NOT NULL -- 1=dc:creator | 2=dc:contributor (NB: dc:contributor = tei:editor)
);
CREATE INDEX writesAuthor   ON writes(author);
CREATE INDEX writesResource ON writes(resource); -- revoir la sémantique de la base ????
CREATE INDEX writesRole     ON writes(role);

CREATE TABLE publisher (
  -- external list of publishers
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  -- heading    TEXT NOT NULL CHECK (heading IN('bfm','cesr','item')), -- ! tei:publisher/@key Compléter la liste
  label      TEXT UNIQUE NOT NULL,   -- ! html for the publisher
  uri        TEXT            -- ? URI
);


-- TRIGGERS
CREATE TRIGGER resourceDel
  -- on resource's record deletion, delete search index, relations to author (dc:creator | dc:contributor) and relation to publisher (dc:publisher)
  BEFORE DELETE ON resource
  FOR EACH ROW BEGIN
    DELETE FROM writes WHERE writes.resource = OLD.id;
    DELETE FROM publishes WHERE publishes.resource = OLD.id;
    DELETE FROM search WHERE search.rowid = OLD.id;
END;

CREATE TRIGGER writesDel
  -- delete orphan authors (dc:creator | dc:contributor) when not protected
  AFTER DELETE ON writes
  FOR EACH ROW WHEN NOT EXISTS (SELECT * FROM writes WHERE author=OLD.author)
  BEGIN
    DELETE FROM author WHERE id=OLD.author AND protect IS NULL;
END;