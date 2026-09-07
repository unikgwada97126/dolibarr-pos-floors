-- Table de métadonnées des étages/salles du POS.
-- TakePos ne stocke un étage que comme un entier brut (colonne "floor" de
-- llx_takepos_floor_tables) sans nom ni ordre : cette table comble ce manque
-- sans toucher à la table native.

CREATE TABLE llx_posfloormanager_salle(
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	floor integer NOT NULL,
	label varchar(128) NOT NULL,
	position integer DEFAULT 0,
	active tinyint DEFAULT 1,
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
