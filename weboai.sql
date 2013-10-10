-- Format of an SQLite base feed with OAI
CREATE TABLE resource (
  -- OAI record of a resource
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  oai_datestamp   TEXT NOT NULL,          -- ! OAI record's submission date http://www.sqlite.org/lang_datefunc.html, time string format 6 : YYYY-MM-DDTHH:MM:SS
  oai_identifier  TEXT UNIQUE NOT NULL,   -- ! revoir la spec OAI
  identifier      TEXT UNIQUE NOT NULL,   -- ! dc:identifier, XML source URI
  title           TEXT NOT NULL,          -- ! dc:title
  rights          TEXT NOT NULL,          -- ! dc:rights, distribution licence URI of XML source
  source          TEXT,                   -- ! dc:source, bibliographic reference of the encoded text
  date            INTEGER NOT NULL,       -- ! dc:date, creation date of the text
  description     TEXT,                   -- ? dc:description, abstract
  record          TEXT                    -- ! the oai record
);
CREATE INDEX resourceTitle ON resource(title);

CREATE TABLE author (
  -- authorities dc:creator or dc:contributor
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  heading         TEXT NOT NULL, -- ! "Baudelaire, Charles (1821-1867)"
  family          TEXT NOT NULL, -- ! "Baudelaire"
  given           TEXT NOT NULL, -- ? "Charles"
  sort1           TEXT NOT NULL, -- ! "baudelaire" : sortable key with no spaces or diacritics, family name : "baudelaire"
  sort2           TEXT NOT NULL, -- ! "charles" sortable key with no spaces or diacritics, given name : "charles"
  birth           INTEGER,       -- ? "1821" birth year
  death           INTEGER,       -- ? "1867" death year
  uri             TEXT,          -- ? URI
  protect         INTEGER        -- protected from automatic deletion (ex: external referential)
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
  -- authorities dc:publisher
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  -- heading    TEXT NOT NULL CHECK (heading IN('bfm','cesr','item')), -- ! tei:publisher/@key Compléter la liste
  label      TEXT UNIQUE NOT NULL,   -- ! tei:publisher
  uri        TEXT,            -- ? URI
  protect    INTEGER          -- protected from automatic deletion (ex: external referential)
);
-- CREATE INDEX publisherHeading ON publisher(heading);
CREATE INDEX publisherLabel   ON publisher(label);

CREATE TABLE publishes (
  -- relation table
  publisher     INTEGER NOT NULL REFERENCES publisher(id),
  resource      INTEGER NOT NULL REFERENCES resource(id)
);
-- TODO : utile de générer les INDEX ????

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