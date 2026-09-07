<?php
/* Module "Gestion Salles & Tables POS" pour Dolibarr 23.x
 * Corrige la numérotation des tables/étages de TakePos (module natif "pos")
 * sans modifier aucun fichier du core (compatible mises à jour Dolibarr).
 *
 * Racine du problème (vérifié sur htdocs/takepos/floors.php et
 * install/mysql/tables/llx_takepos_floor_tables-takepos.key.sql en 23.0.1) :
 *  - la contrainte UNIQUE(entity, label) est GLOBALE, pas par étage
 *  - le renommage d'une table (action=updatename) n'a AUCUN contrôle
 *    d'erreur : un renommage qui viole la contrainte échoue en silence
 *  - la création d'une table (action=add) fixe label = rowid (compteur
 *    global auto-incrémenté), donc la numérotation ne redémarre jamais
 *    à 1 par étage et se décale à chaque ajout/suppression
 *  - aucun hook n'est déclenché par floors.php : impossible d'intercepter
 *    ces opérations par le système de hooks standard de Dolibarr
 *
 * Ce module corrige ces 2 points au niveau base de données (triggers
 * MySQL posés à l'activation) : toute table créée ou renommée, que ce
 * soit depuis l'écran natif floors.php ou depuis l'écran de ce module,
 * respecte désormais une numérotation propre et unique PAR ÉTAGE.
 * Il ajoute en plus un véritable écran de gestion des étages (nommage,
 * ordre, activation) qui n'existe pas nativement dans TakePos.
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 *  Classe de description et d'activation du module PosFloorManager
 */
class modPosFloorManager extends DolibarrModules
{
	/**
	 * Constructeur
	 *
	 * @param DoliDB $db Handler base de données
	 */
	public function __construct($db)
	{
		global $conf;

		$this->db = $db;

		// Identifiant du module. Plage réservée aux modules non publiés/officiels
		// (100000-299999 recommandé par la doc Dolibarr). À changer si collision
		// avec un autre module custom déjà installé sur ce serveur (vérifier dans
		// Accueil > Informations système > Dolibarr > liste des numéros utilisés).
		$this->numero = 100450;

		// Clé utilisée pour les droits, menus, etc.
		$this->rights_class = 'posfloormanager';

		$this->family = "portal";
		$this->module_position = '46'; // juste après TakePos (45)

		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Gestion fiable des salles/étages et de la numérotation des tables pour TakePos";
		$this->descriptionlong = "Corrige la numérotation des tables et étages du module TakePos (Point de vente) : unicité des numéros de table PAR ÉTAGE au lieu d'une unicité globale, numérotation automatique séquentielle par étage, écran de gestion des salles/étages (nommage, ordre, activation) et outil de renumérotation. Fonctionne par triggers base de données : aucun fichier du module TakePos natif n'est modifié.";

		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'houses'; // picto standard Dolibarr (pas besoin d'image custom)

		$this->module_parts = array(
			'triggers' => 0,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'theme' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 0,
			'hooks' => array(), // Aucun hook exploitable côté TakePos (vérifié dans floors.php), cf. sql triggers dans init()
		);

		$this->dirs = array();

		$this->config_page_url = array("setup.php@posfloormanager");

		$this->hidden = false;
		// Dépend du module TakePos : inutile sans lui
		$this->depends = array('always' => array("modTakePos"));
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array("posfloormanager@posfloormanager");
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(19, 0);

		$this->const = array();

		if (!isModEnabled('posfloormanager')) {
			$conf->posfloormanager = new stdClass();
			$conf->posfloormanager->enabled = 0;
		}

		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array();

		// Permissions
		$this->rights = array();
		$r = 0;

		$r++;
		$this->rights[$r][0] = 100451;
		$this->rights[$r][1] = 'Gérer les salles, étages et la numérotation des tables du POS';
		$this->rights[$r][2] = 'a';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'manage';

		// Menu : entrée dans le menu gauche du module TakePos
		$this->menu = array();
		$r = 0;

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=takepos',
			'type' => 'left',
			'titre' => 'PosFloorManagerMenu',
			'mainmenu' => 'takepos',
			'leftmenu' => 'posfloormanager',
			'url' => '/posfloormanager/admin/floors_tables.php',
			'langs' => 'posfloormanager@posfloormanager',
			'position' => 1100,
			'enabled' => 'isModEnabled("posfloormanager")',
			'perms' => '$user->hasRight("posfloormanager", "manage")',
			'target' => '',
			'user' => 0,
		);
	}

	/**
	 * Nom du trigger MySQL posé sur INSERT
	 *
	 * @return string
	 */
	private function getInsertTriggerSql()
	{
		// Déclenché AVANT l'insertion faite par takepos/floors.php (action=add).
		// A ce stade label est toujours '' (floors.php insère label='' puis fait
		// un second UPDATE juste après pour le remplir avec le rowid : notre
		// trigger UPDATE ci-dessous interceptera CE second UPDATE pour imposer
		// une numérotation séquentielle propre par étage à la place du rowid brut).
		return "CREATE TRIGGER pfm_before_insert_floor_table
			BEFORE INSERT ON ".MAIN_DB_PREFIX."takepos_floor_tables
			FOR EACH ROW
			BEGIN
				IF NEW.floor IS NULL THEN
					SET NEW.floor = 1;
				END IF;
			END";
	}

	/**
	 * Nom du trigger MySQL posé sur UPDATE (coeur de la correction)
	 *
	 * @return string
	 */
	private function getUpdateTriggerSql()
	{
		return "CREATE TRIGGER pfm_before_update_floor_table
			BEFORE UPDATE ON ".MAIN_DB_PREFIX."takepos_floor_tables
			FOR EACH ROW
			BEGIN
				DECLARE nextnum INT;
				DECLARE conflictnum INT;

				-- Cas 1 : floors.php vient de créer la ligne (label='') et fait
				-- immédiatement un UPDATE label=rowid. On remplace ce rowid brut
				-- par le prochain numéro libre, séquentiel, PROPRE A CET ETAGE.
				IF (OLD.label = '' OR OLD.label IS NULL) THEN
					SELECT COALESCE(MAX(CAST(label AS UNSIGNED)), 0) + 1 INTO nextnum
						FROM ".MAIN_DB_PREFIX."takepos_floor_tables
						WHERE floor = NEW.floor
						  AND entity = NEW.entity
						  AND label REGEXP '^[0-9]+$'
						  AND rowid <> NEW.rowid;
					SET NEW.label = CAST(nextnum AS CHAR);

				-- Cas 2 : renommage explicite (action=updatename, ou notre propre
				-- écran). On bloque proprement tout doublon SUR LE MEME ETAGE
				-- avec un message clair, au lieu de l'échec silencieux natif.
				ELSE
					SELECT COUNT(*) INTO conflictnum
						FROM ".MAIN_DB_PREFIX."takepos_floor_tables
						WHERE floor = NEW.floor
						  AND entity = NEW.entity
						  AND label = NEW.label
						  AND rowid <> NEW.rowid;
					IF conflictnum > 0 THEN
						SIGNAL SQLSTATE '45000'
							SET MESSAGE_TEXT = 'Numero de table deja utilise sur cet etage';
					END IF;
				END IF;
			END";
	}

	/**
	 * Supprime proprement d'anciens triggers avant de les recréer (activation
	 * relancée, mise à jour du module).
	 *
	 * @return void
	 */
	private function dropTriggersIfExists()
	{
		$this->db->query("DROP TRIGGER IF EXISTS pfm_before_insert_floor_table");
		$this->db->query("DROP TRIGGER IF EXISTS pfm_before_update_floor_table");
	}

	/**
	 * Migre l'index unique de llx_takepos_floor_tables : unicité globale
	 * (entity,label) native -> unicité PAR ETAGE (entity,floor,label).
	 * Idempotent : sans effet si déjà migré.
	 *
	 * @return void
	 */
	private function migrateUniqueIndex()
	{
		$sql = "SELECT COUNT(*) as nb FROM information_schema.statistics
				WHERE table_schema = DATABASE()
				  AND table_name = '".MAIN_DB_PREFIX."takepos_floor_tables'
				  AND index_name = 'uk_takepos_floor_tables'
				  AND column_name = 'floor'";
		$resql = $this->db->query($sql);
		$alreadymigrated = false;
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			$alreadymigrated = ($obj && $obj->nb > 0);
		}
		if ($alreadymigrated) {
			return;
		}

		// Ne tente le DROP que si l'ancien index (natif TakePos) existe vraiment
		// (site déjà migré manuellement, ou TakePos jamais utilisé).
		$sql = "SELECT COUNT(*) as nb FROM information_schema.statistics
				WHERE table_schema = DATABASE()
				  AND table_name = '".MAIN_DB_PREFIX."takepos_floor_tables'
				  AND index_name = 'uk_takepos_floor_tables'";
		$resql = $this->db->query($sql);
		$oldindexexists = false;
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			$oldindexexists = ($obj && $obj->nb > 0);
		}
		if ($oldindexexists) {
			$this->db->query("ALTER TABLE ".MAIN_DB_PREFIX."takepos_floor_tables DROP INDEX uk_takepos_floor_tables");
		}
		$this->db->query("ALTER TABLE ".MAIN_DB_PREFIX."takepos_floor_tables ADD UNIQUE INDEX uk_takepos_floor_tables(entity, floor, label)");
	}

	/**
	 * Fonction appelée à l'activation du module.
	 *
	 * @param string $options Options
	 * @return int 1 si OK
	 */
	public function init($options = '')
	{
		$sql = array();
		$result = $this->_load_tables('/posfloormanager/sql/');
		if ($result < 0) {
			return -1; // Erreur SQL de base, voir $this->error
		}

		// Étapes propres à ce module : index unique + triggers. Faites en PHP
		// (pas via le loader générique sql/) car les triggers contiennent des
		// points-virgules internes que le loader découpe naïvement.
		// Protégé par try/catch : si le compte SQL n'a pas le privilège TRIGGER
		// (hébergement mutualisé/NAS restreint) ou toute autre erreur imprévue,
		// on affiche un message clair au lieu de laisser planter l'activation.
		try {
			$this->migrateUniqueIndex();
			$this->dropTriggersIfExists();
			$this->db->query($this->getInsertTriggerSql());
			$this->db->query($this->getUpdateTriggerSql());
		} catch (\Throwable $e) {
			$this->error = "PosFloorManager: echec de la migration index/triggers (".$e->getMessage()."). Verifiez que le compte MySQL a les privileges ALTER et TRIGGER sur la base.";
			dol_syslog(__METHOD__." ".$this->error, LOG_ERR);
			setEventMessages($this->error, null, 'errors');
			// On ne bloque pas l'activation du module pour autant : l'ecran
			// natif TakePos continue de fonctionner sans la correction tant
			// que ce point n'est pas resolu.
		}

		return $this->_init($sql, $options);
	}

	/**
	 * Fonction appelée à la désactivation du module.
	 *
	 * @param string $options Options
	 * @return int 1 si OK
	 */
	public function remove($options = '')
	{
		// On retire les triggers (sinon ils restent actifs même module désactivé).
		// On NE remet PAS l'ancien index unique global : la contrainte par étage
		// reste plus sûre et n'empêche rien de fonctionner nativement.
		$this->dropTriggersIfExists();

		$sql = array();
		return $this->_remove($sql, $options);
	}
}
