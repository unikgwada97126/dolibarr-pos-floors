-- Statut actif/inactif par table (information de gestion pour l'écran de ce
-- module uniquement : ne masque pas la table dans l'écran caisse natif de
-- TakePos, qui n'expose aucun hook pour ce genre de filtre).

CREATE TABLE llx_posfloormanager_table_meta(
	tableid integer PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	active tinyint DEFAULT 1
) ENGINE=innodb;
