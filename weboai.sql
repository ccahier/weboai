
-- Format of an SQLite base feed with OAI
CREATE TABLE record (
  -- OAI record of a resource, should be enough for an OAI engine, and to display a short result for the resource
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  deleted BOOLEAN NOT NULL DEFAULT FALSE, -- ! required by OAI protocol to inform harvester
  oai_datestamp   INTEGER NOT NULL,       -- ! OAI record's submission date http://www.sqlite.org/lang_datefunc.html, time string format 6 : YYYY-MM-DDTHH:MM:SS
  oai_identifier  TEXT UNIQUE NOT NULL,   -- ! local OAI identifier used by harvester to get, update, delete records
  record          TEXT NOT NULL,          -- ! the oai record
  identifier      TEXT,                   -- ! a link for the full-text, should be unique dc:identifier, but life sometimes…
  title           TEXT NOT NULL,          -- ! dc:title, just for display 
  byline          TEXT,                   -- ? optional, texts may not have authors, dc:author x n, just for display 
  date            INTEGER NOT NULL,       -- ! dc:date, creation date of the text, should be not null
  date2           INTEGER,                -- ? second important date in life of resoource (ex: edition date of a medieval text)
  issued          INTEGER                 -- ? publication date of electronic resource
);

CREATE VIRTUAL TABLE ft USING FTS3 (
  -- fulltext fields to find records for a public interface
  heading      TEXT,  -- aggregation of titles and authors for full-text search
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
  record      INTEGER NOT NULL REFERENCES record(id),
  role        INTEGER NOT NULL -- 1=dc:creator | 2=dc:contributor (NB: dc:contributor = tei:editor)
);
CREATE INDEX writesAuthor   ON writes(author);
CREATE INDEX writesRecord   ON writes(record); -- revoir la sémantique de la base ???? [FG] Pourquoi ? plutôt "text" ?
CREATE INDEX writesRole     ON writes(role);


CREATE TABLE oaiset (
  -- external list of sets, also used for publishers http://www.openarchives.org/OAI/openarchivesprotocol.html#Set
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  spec         TEXT UNIQUE NOT NULL, -- OAI, setSpec, a colon [:] separated list indicating the path from the root of the set hierarchy to the respective node.
  name         TEXT,   -- OAI, setName, a short human-readable string naming the set.
  uri          TEXT,   -- not in OAI protocol but useful for links
  image        BLOB,   -- not in OAI protocol, useful for human interface
  description  TEXT    -- OAI, setDescription, an optional and repeatable container that may hold community-specific XML-encoded data about the set 
);
CREATE INDEX oaisetSpec ON oaiset(spec);

CREATE TABLE member (
  oaiset      INTEGER NOT NULL REFERENCES oaiset(id),
  record      INTEGER NOT NULL REFERENCES record(id)
);
CREATE INDEX memberOaiset   ON member(oaiset);
CREATE INDEX memberRecord   ON member(record);

-- TRIGGERS
CREATE TRIGGER recordDel
  -- on resource's record deletion, delete search index, relations to author (dc:creator | dc:contributor), relation to sets
  BEFORE DELETE ON record
  FOR EACH ROW BEGIN
    DELETE FROM ft WHERE ft.rowid = OLD.id;
    DELETE FROM writes WHERE writes.record = OLD.id;
    DELETE FROM member WHERE member.record = OLD.id;
END;

CREATE TRIGGER writesDel
  -- delete orphan authors (dc:creator | dc:contributor) when not protected
  AFTER DELETE ON writes
  FOR EACH ROW WHEN NOT EXISTS (SELECT * FROM writes WHERE author=OLD.author)
  BEGIN
    DELETE FROM author WHERE id=OLD.author AND protect IS NULL;
END;