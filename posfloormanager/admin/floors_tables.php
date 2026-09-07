<?php
/**
 *  \file       posfloormanager/admin/floors_tables.php
 *  \brief      Écran de gestion des salles/étages et de la numérotation
 *              des tables du POS (TakePos). Complète (sans le modifier)
 *              l'écran natif takepos/floors.php.
 */

// Remontée relative à l'emplacement réel du fichier (__DIR__), sans supposer
// une profondeur fixe : fonctionne que le module soit dans htdocs/custom/
// (profondeur 3), directement sous htdocs/ (profondeur 2), ou toute autre
// disposition (ex: alt roots multi-niveaux).
$res = 0;
$tmpdir = __DIR__;
for ($i = 0; $i < 6 && !$res; $i++) {
	$tmpdir = dirname($tmpdir);
	if (file_exists($tmpdir.'/main.inc.php')) {
		$res = @include $tmpdir.'/main.inc.php';
	}
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array("posfloormanager@posfloormanager", "cashdesk"));

if (!$user->hasRight('posfloormanager', 'manage')) {
	accessforbidden();
}

$form = new Form($db);

pfmEnsureTableMetaSchema($db);

$action = GETPOST('action', 'aZ09');
$currentfloor = GETPOSTINT('floor');
$rowid = GETPOSTINT('rowid');
$label = GETPOST('label', 'alphanohtml');
$nbtocreate = GETPOSTINT('nbtocreate');

$error = 0;


/*
 * Fonctions locales
 */

/**
 * Compte le nombre de tables existantes pour un étage donné.
 *
 * @param DoliDB $db     Handler DB
 * @param int    $floor  Numéro d'étage
 * @return int
 */
function pfmCountTables($db, $floor)
{
	$sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."takepos_floor_tables";
	$sql .= " WHERE floor = ".((int) $floor)." AND entity IN (".getEntity('takepos').")";
	$resql = $db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		return (int) $obj->nb;
	}
	return 0;
}

/**
 * Crée la table de statut actif/inactif par table si elle n'existe pas
 * encore (auto-réparation : évite d'imposer une désactivation/réactivation
 * du module aux utilisateurs qui l'ont déjà installé avant l'ajout de cette
 * fonctionnalité).
 *
 * @param DoliDB $db Handler DB
 * @return void
 */
function pfmEnsureTableMetaSchema($db)
{
	$db->query("CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."posfloormanager_table_meta(
		tableid integer PRIMARY KEY,
		entity integer DEFAULT 1 NOT NULL,
		active tinyint DEFAULT 1
	) ENGINE=innodb");
}

/**
 * Etat actif/inactif d'une table (par défaut active si jamais personnalisée).
 * Note : ce statut est une information de gestion pour CET écran uniquement
 * (ex: table cassée, hors service temporairement) ; il ne masque pas la
 * table dans l'écran caisse natif de TakePos (aucun hook disponible là-bas,
 * cf. mémoire du projet).
 *
 * @param DoliDB $db       Handler DB
 * @param int    $tableid  rowid dans llx_takepos_floor_tables
 * @return int
 */
function pfmTableActive($db, $tableid)
{
	$sql = "SELECT active FROM ".MAIN_DB_PREFIX."posfloormanager_table_meta WHERE tableid = ".((int) $tableid);
	$resql = $db->query($sql);
	if ($resql && ($obj = $db->fetch_object($resql))) {
		return (int) $obj->active;
	}
	return 1;
}

/**
 * Vérifie si une table (par rowid natif takepos) a une facture/commande
 * POS ouverte dessus (même logique que takepos/floors.php).
 *
 * @param DoliDB $db  Handler DB
 * @param int    $tableid  rowid dans llx_takepos_floor_tables
 * @return bool
 */
function pfmTableIsOccupied($db, $tableid)
{
	// Le terminal n'est pas connu ici (hors session caisse) : on cherche sur
	// tous les terminaux possibles via une recherche large du ref provisoire.
	$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture";
	$sql .= " WHERE entity IN (".getEntity('facture').")";
	$sql .= " AND ref LIKE '(PROV-POS%-".((int) $tableid).")'";
	$resql = $db->query($sql);
	if ($resql && $db->num_rows($resql) > 0) {
		return true;
	}
	return false;
}

/**
 * Construit la liste des étages à afficher en fusionnant 2 sources :
 * - les étages qui existent DEJA nativement dans TakePos (dès qu'une table y
 *   a été créée depuis l'écran natif ou celui-ci) ;
 * - les étages personnalisés (nom/ordre/actif) enregistrés par ce module.
 * Un étage natif sans personnalisation apparaît avec un nom par défaut
 * ("Étage N") : aucune recréation manuelle n'est nécessaire pour le voir.
 *
 * @param DoliDB    $db     Handler DB
 * @param Conf      $conf   Config Dolibarr
 * @param Translate $langs  Langue
 * @return array<int,array{label:string,active:int,position:int,hasmeta:bool}>
 */
function pfmListFloors($db, $conf, $langs)
{
	$floors = array();

	$sql = "SELECT DISTINCT floor FROM ".MAIN_DB_PREFIX."takepos_floor_tables";
	$sql .= " WHERE entity IN (".getEntity('takepos').")";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$floornum = (int) $obj->floor;
			$floors[$floornum] = array('label' => null, 'active' => 1, 'position' => $floornum, 'hasmeta' => false);
		}
	}

	$sql = "SELECT floor, label, position, active FROM ".MAIN_DB_PREFIX."posfloormanager_salle";
	$sql .= " WHERE entity = ".((int) $conf->entity);
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$floornum = (int) $obj->floor;
			$floors[$floornum] = array('label' => $obj->label, 'active' => (int) $obj->active, 'position' => (int) $obj->position, 'hasmeta' => true);
		}
	}

	foreach ($floors as $floornum => &$f) {
		if ($f['label'] === null || $f['label'] === '') {
			$f['label'] = $langs->trans("Floor")." ".$floornum;
		}
	}
	unset($f);

	uasort($floors, function ($a, $b) {
		return $a['position'] <=> $b['position'];
	});

	return $floors;
}

/**
 * Prochain numéro d'étage libre, en tenant compte des étages déjà présents
 * nativement dans TakePos ET de ceux personnalisés par ce module.
 *
 * @param DoliDB $db    Handler DB
 * @param Conf   $conf  Config Dolibarr
 * @return int
 */
function pfmNextFreeFloor($db, $conf)
{
	$max = 0;
	$sql = "SELECT MAX(floor) as m FROM ".MAIN_DB_PREFIX."takepos_floor_tables WHERE entity IN (".getEntity('takepos').")";
	$resql = $db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		if ($obj && $obj->m !== null) {
			$max = max($max, (int) $obj->m);
		}
	}
	$sql = "SELECT MAX(floor) as m FROM ".MAIN_DB_PREFIX."posfloormanager_salle WHERE entity = ".((int) $conf->entity);
	$resql = $db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		if ($obj && $obj->m !== null) {
			$max = max($max, (int) $obj->m);
		}
	}
	return $max + 1;
}


/*
 * Actions
 */

if ($action == 'addfloor' && $user->hasRight('posfloormanager', 'manage')) {
	// Uniquement pour un étage tout NEUF (sans aucune table native existante) :
	// les étages déjà utilisés dans TakePos apparaissent automatiquement dans
	// la liste (pfmListFloors), inutile de les "créer" ici.
	$newfloorname = GETPOST('newfloorname', 'alphanohtml');
	if (empty($newfloorname)) {
		setEventMessages($langs->trans("PosFloorManagerErrorNameRequired"), null, 'errors');
		$error++;
	}
	if (!$error) {
		$nextfloor = pfmNextFreeFloor($db, $conf);
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."posfloormanager_salle(entity, floor, label, position, active, date_creation)";
		$sql .= " VALUES (".((int) $conf->entity).", ".((int) $nextfloor).", '".$db->escape($newfloorname)."', ".((int) $nextfloor).", 1, '".$db->idate(dol_now())."')";
		if (!$db->query($sql)) {
			setEventMessages($db->lasterror(), null, 'errors');
		} else {
			setEventMessages($langs->trans("PosFloorManagerFloorCreated"), null, 'mesgs');
			$currentfloor = $nextfloor;
		}
	}
}

if ($action == 'renamefloor' && $user->hasRight('posfloormanager', 'manage')) {
	$floornum = GETPOSTINT('floornum');
	if (empty($label)) {
		setEventMessages($langs->trans("PosFloorManagerErrorNameRequired"), null, 'errors');
	} elseif ($floornum <= 0) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		// Upsert : l'étage peut déjà exister nativement dans TakePos sans
		// jamais avoir eu de ligne de personnalisation (nom/ordre/actif) ici.
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."posfloormanager_salle(entity, floor, label, position, active, date_creation)";
		$sql .= " VALUES (".((int) $conf->entity).", ".((int) $floornum).", '".$db->escape($label)."', ".((int) $floornum).", 1, '".$db->idate(dol_now())."')";
		$sql .= " ON DUPLICATE KEY UPDATE label = '".$db->escape($label)."'";
		if (!$db->query($sql)) {
			setEventMessages($db->lasterror(), null, 'errors');
		} else {
			setEventMessages($langs->trans("PosFloorManagerFloorRenamed"), null, 'mesgs');
		}
	}
}

if ($action == 'toggleactivefloor' && $user->hasRight('posfloormanager', 'manage')) {
	$floornum = GETPOSTINT('floornum');
	if ($floornum > 0) {
		$defaultlabel = $langs->trans("Floor")." ".$floornum;
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."posfloormanager_salle(entity, floor, label, position, active, date_creation)";
		$sql .= " VALUES (".((int) $conf->entity).", ".((int) $floornum).", '".$db->escape($defaultlabel)."', ".((int) $floornum).", 0, '".$db->idate(dol_now())."')";
		$sql .= " ON DUPLICATE KEY UPDATE active = 1 - active";
		$db->query($sql);
	}
}

if ($action == 'deletefloor' && $user->hasRight('posfloormanager', 'manage')) {
	$floornum = GETPOSTINT('floornum');
	if ($floornum > 0) {
		$nbtables = pfmCountTables($db, $floornum);
		if ($nbtables > 0) {
			setEventMessages($langs->trans("PosFloorManagerErrorFloorNotEmpty", $nbtables), null, 'errors');
		} else {
			// Ne supprime que la personnalisation (nom/ordre/actif) : si l'étage
			// n'a plus aucune table, il disparaît de la liste de lui-même.
			$db->query("DELETE FROM ".MAIN_DB_PREFIX."posfloormanager_salle WHERE entity = ".((int) $conf->entity)." AND floor = ".((int) $floornum));
			setEventMessages($langs->trans("PosFloorManagerFloorDeleted"), null, 'mesgs');
		}
	}
}

if ($action == 'addtable' && $user->hasRight('posfloormanager', 'manage') && $currentfloor > 0) {
	// Même schéma d'insertion que takepos/floors.php (action=add) : le
	// trigger pfm_before_update_floor_table posé par ce module se charge
	// d'assigner un numéro propre, séquentiel, par étage (au lieu du rowid brut).
	$sql = "INSERT INTO ".MAIN_DB_PREFIX."takepos_floor_tables(entity, label, leftpos, toppos, floor)";
	$sql .= " VALUES (".((int) $conf->entity).", '', '45', '45', ".((int) $currentfloor).")";
	if ($db->query($sql)) {
		$newid = $db->last_insert_id(MAIN_DB_PREFIX."takepos_floor_tables");
		$db->query("UPDATE ".MAIN_DB_PREFIX."takepos_floor_tables SET label = rowid WHERE rowid = ".((int) $newid));
		setEventMessages($langs->trans("PosFloorManagerTableCreated"), null, 'mesgs');
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
}

if ($action == 'bulkaddtables' && $user->hasRight('posfloormanager', 'manage') && $currentfloor > 0 && $nbtocreate > 0) {
	$nbtocreate = min($nbtocreate, 50); // garde-fou
	$created = 0;
	for ($i = 0; $i < $nbtocreate; $i++) {
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."takepos_floor_tables(entity, label, leftpos, toppos, floor)";
		$sql .= " VALUES (".((int) $conf->entity).", '', '".((10 + ($i % 8) * 11))."', '".(10 + intdiv($i, 8) * 20)."', ".((int) $currentfloor).")";
		if ($db->query($sql)) {
			$newid = $db->last_insert_id(MAIN_DB_PREFIX."takepos_floor_tables");
			$db->query("UPDATE ".MAIN_DB_PREFIX."takepos_floor_tables SET label = rowid WHERE rowid = ".((int) $newid));
			$created++;
		}
	}
	setEventMessages($langs->trans("PosFloorManagerTablesCreated", $created), null, 'mesgs');
}

if ($action == 'renametable' && $user->hasRight('posfloormanager', 'manage')) {
	$newname = preg_replace("/[^a-zA-Z0-9]/", "", $label);
	if (empty($newname)) {
		setEventMessages($langs->trans("PosFloorManagerErrorNameRequired"), null, 'errors');
	} else {
		// Contrôle explicite AVANT la requête pour un message clair : le
		// trigger DB bloquera aussi en dernier recours si ce contrôle est
		// contourné (autre écran, accès direct DB, etc.)
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."takepos_floor_tables";
		$sql .= " WHERE floor = ".((int) $currentfloor)." AND entity IN (".getEntity('takepos').")";
		$sql .= " AND label = '".$db->escape($newname)."' AND rowid <> ".((int) $rowid);
		$resql = $db->query($sql);
		if ($resql && $db->num_rows($resql) > 0) {
			setEventMessages($langs->trans("PosFloorManagerErrorDuplicateLabel", $newname), null, 'errors');
		} else {
			$sql = "UPDATE ".MAIN_DB_PREFIX."takepos_floor_tables SET label = '".$db->escape($newname)."'";
			$sql .= " WHERE rowid = ".((int) $rowid);
			if (!$db->query($sql)) {
				setEventMessages($langs->trans("PosFloorManagerErrorDuplicateLabel", $newname), null, 'errors');
			} else {
				setEventMessages($langs->trans("PosFloorManagerTableRenamed"), null, 'mesgs');
			}
		}
	}
}

if ($action == 'movetable' && $user->hasRight('posfloormanager', 'manage')) {
	$targetfloor = GETPOSTINT('targetfloor');
	if ($targetfloor <= 0) {
		setEventMessages($langs->trans("PosFloorManagerErrorNameRequired"), null, 'errors');
	} elseif ($targetfloor == $currentfloor) {
		setEventMessages($langs->trans("PosFloorManagerErrorSameFloor"), null, 'errors');
	} else {
		// Contrôle explicite AVANT la requête, comme pour le renommage : le
		// trigger DB bloquera aussi en dernier recours si ce contrôle est
		// contourné, mais le message est plus clair ici.
		$sql = "SELECT tt.label FROM ".MAIN_DB_PREFIX."takepos_floor_tables tt WHERE tt.rowid = ".((int) $rowid);
		$resqllabel = $db->query($sql);
		$objlabel = $resqllabel ? $db->fetch_object($resqllabel) : null;
		$currentlabel = $objlabel ? $objlabel->label : '';

		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."takepos_floor_tables";
		$sql .= " WHERE floor = ".((int) $targetfloor)." AND entity IN (".getEntity('takepos').")";
		$sql .= " AND label = '".$db->escape($currentlabel)."'";
		$resql = $db->query($sql);
		if ($resql && $db->num_rows($resql) > 0) {
			setEventMessages($langs->trans("PosFloorManagerErrorDuplicateLabelOnTarget", $currentlabel, $targetfloor), null, 'errors');
		} else {
			$sql = "UPDATE ".MAIN_DB_PREFIX."takepos_floor_tables SET floor = ".((int) $targetfloor);
			$sql .= " WHERE rowid = ".((int) $rowid);
			if (!$db->query($sql)) {
				setEventMessages($db->lasterror(), null, 'errors');
			} else {
				setEventMessages($langs->trans("PosFloorManagerTableMoved", $targetfloor), null, 'mesgs');
			}
		}
	}
}

if ($action == 'toggletableactive' && $user->hasRight('posfloormanager', 'manage')) {
	$sql = "INSERT INTO ".MAIN_DB_PREFIX."posfloormanager_table_meta(tableid, entity, active)";
	$sql .= " VALUES (".((int) $rowid).", ".((int) $conf->entity).", 0)";
	$sql .= " ON DUPLICATE KEY UPDATE active = 1 - active";
	$db->query($sql);
}

if ($action == 'deletetable' && $user->hasRight('posfloormanager', 'manage')) {
	if (pfmTableIsOccupied($db, $rowid)) {
		setEventMessages($langs->trans("PosFloorManagerErrorTableOccupied"), null, 'errors');
	} else {
		$db->query("DELETE FROM ".MAIN_DB_PREFIX."takepos_floor_tables WHERE rowid = ".((int) $rowid));
		$db->query("DELETE FROM ".MAIN_DB_PREFIX."posfloormanager_table_meta WHERE tableid = ".((int) $rowid));
		setEventMessages($langs->trans("PosFloorManagerTableDeleted"), null, 'mesgs');
	}
}

if ($action == 'renumberfloor' && $user->hasRight('posfloormanager', 'manage') && $currentfloor > 0) {
	// Outil de réparation : renumérote toutes les tables d'un étage en
	// 1..N (ordre = rowid). Passe intermédiaire par des labels temporaires
	// non numériques pour ne jamais violer l'unicité en cours de route.
	$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."takepos_floor_tables";
	$sql .= " WHERE floor = ".((int) $currentfloor)." AND entity IN (".getEntity('takepos').") ORDER BY rowid ASC";
	$resql = $db->query($sql);
	$ids = array();
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$ids[] = (int) $obj->rowid;
		}
	}
	foreach ($ids as $tmpid) {
		$db->query("UPDATE ".MAIN_DB_PREFIX."takepos_floor_tables SET label = CONCAT('T', ".((int) $tmpid).") WHERE rowid = ".((int) $tmpid));
	}
	$num = 1;
	foreach ($ids as $tmpid) {
		$db->query("UPDATE ".MAIN_DB_PREFIX."takepos_floor_tables SET label = '".((int) $num)."' WHERE rowid = ".((int) $tmpid));
		$num++;
	}
	setEventMessages($langs->trans("PosFloorManagerFloorRenumbered", count($ids)), null, 'mesgs');
}


/*
 * Vue
 */

llxHeader('', $langs->trans("PosFloorManagerTitle"));

print load_fiche_titre($langs->trans("PosFloorManagerTitle"), '', 'houses');

print '<span class="opacitymedium">'.$langs->trans("PosFloorManagerIntro").'</span><br><br>';

?>
<style>
.pfm-room { border: 1px solid #d8dade; border-radius: 6px; margin-bottom: 22px; background: #fff; overflow: hidden; }
.pfm-room-head { display: flex; align-items: center; flex-wrap: wrap; gap: 14px; background: #f5f6f8; padding: 10px 16px; border-bottom: 1px solid #d8dade; }
.pfm-room-head .pfm-room-label { font-weight: bold; font-size: 1.05em; white-space: nowrap; }
.pfm-room-head form { display: inline-flex; align-items: center; gap: 6px; margin: 0; }
.pfm-room-badge { background: #e2e4e9; border-radius: 10px; padding: 2px 10px; font-size: 0.85em; white-space: nowrap; }
.pfm-room-head .pfm-room-spacer { flex: 1 1 auto; }
.pfm-room-body { padding: 10px 16px 16px 16px; }
.pfm-table-row { display: flex; align-items: center; flex-wrap: wrap; gap: 14px; padding: 7px 0; border-bottom: 1px solid #f0f1f3; }
.pfm-table-row:last-of-type { border-bottom: none; }
.pfm-table-row.pfm-inactive { opacity: 0.45; }
.pfm-table-row form { display: inline-flex; align-items: center; gap: 6px; margin: 0; }
.pfm-table-num-badge { min-width: 34px; }
.pfm-actions-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 14px; padding-top: 12px; border-top: 1px dashed #e2e4e9; }
.pfm-actions-bar form { display: inline-flex; align-items: center; gap: 6px; margin: 0; }
.pfm-empty { color: #888; font-style: italic; padding: 10px 0; }
</style>
<?php

// --- Liste des salles/étages : fusion auto TakePos natif + personnalisation ---
$floors = pfmListFloors($db, $conf, $langs);

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" class="marginbottomonly">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="addfloor">';
print $langs->trans("PosFloorManagerNewFloorName").' : ';
print '<input type="text" name="newfloorname" size="24" placeholder="'.$langs->trans("PosFloorManagerFloorNameExample").'">';
print ' <button type="submit" class="butAction">'.$langs->trans("PosFloorManagerAddFloor").'</button>';
print '</form>';

print '<br>';

// --- Chaque salle = 1 carte, avec la gestion COMPLETE de ses tables dedans :
// rien de caché derriere un clic ou un filtre d'URL separe.
foreach ($floors as $floornum => $f) {
	$nbtables = pfmCountTables($db, $floornum);

	print '<div class="pfm-room">';

	// --- En-tête de la salle ---
	print '<div class="pfm-room-head">';
	print '<span class="pfm-room-label">'.$langs->trans("Floor").' '.((int) $floornum).'</span>';
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="renamefloor">';
	print '<input type="hidden" name="floornum" value="'.((int) $floornum).'">';
	print '<input type="text" name="label" class="flat" value="'.dol_escape_htmltag($f['label']).'" size="22">';
	print '<button type="submit" class="button smallpaddingimp">'.$langs->trans("Save").'</button>';
	print '</form>';
	print '<span class="pfm-room-badge">'.$nbtables.' '.strtolower($langs->trans("PosFloorManagerNbTables")).'</span>';
	print '<span class="pfm-room-spacer"></span>';
	print '<a href="'.$_SERVER["PHP_SELF"].'?action=toggleactivefloor&token='.newToken().'&floornum='.((int) $floornum).'" title="'.$langs->trans("Status").'">';
	print $f['active'] ? img_picto($langs->trans("Active"), 'switch_on') : img_picto($langs->trans("Disabled"), 'switch_off');
	print '</a>';
	if ($nbtables == 0) {
		print ' <a href="'.$_SERVER["PHP_SELF"].'?action=deletefloor&token='.newToken().'&floornum='.((int) $floornum).'" onclick="return confirm(\''.dol_escape_js($langs->trans("ConfirmDelete")).'\');" title="'.$langs->trans("Delete").'">'.img_picto($langs->trans("Delete"), 'delete').'</a>';
	}
	print '</div>';

	// --- Corps : tables de CETTE salle + actions ---
	print '<div class="pfm-room-body">';

	$sql = "SELECT rowid, label, leftpos, toppos FROM ".MAIN_DB_PREFIX."takepos_floor_tables";
	$sql .= " WHERE floor = ".((int) $floornum)." AND entity IN (".getEntity('takepos').")";
	$sql .= " ORDER BY CAST(label AS UNSIGNED) ASC, label ASC";
	$resql = $db->query($sql);

	if ($resql && $db->num_rows($resql) > 0) {
		while ($obj = $db->fetch_object($resql)) {
			$occupied = pfmTableIsOccupied($db, $obj->rowid);
			$tableactive = pfmTableActive($db, $obj->rowid);

			print '<div class="pfm-table-row'.(!$tableactive ? ' pfm-inactive' : '').'">';

			print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" class="pfm-table-num-badge">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="renametable">';
			print '<input type="hidden" name="rowid" value="'.((int) $obj->rowid).'">';
			print '<input type="hidden" name="floor" value="'.((int) $floornum).'">';
			print '<input type="text" name="label" class="flat" value="'.dol_escape_htmltag($obj->label).'" size="4" maxlength="10">';
			print '<button type="submit" class="button smallpaddingimp">'.$langs->trans("Save").'</button>';
			print '</form>';

			print '<span>'.($occupied ? img_picto($langs->trans("PosFloorManagerOccupied"), 'statut4') : img_picto($langs->trans("PosFloorManagerFree"), 'statut6')).'</span>';

			print '<a href="'.$_SERVER["PHP_SELF"].'?action=toggletableactive&token='.newToken().'&rowid='.((int) $obj->rowid).'" title="'.$langs->trans("Status").'">';
			print $tableactive ? img_picto($langs->trans("Active"), 'switch_on') : img_picto($langs->trans("PosFloorManagerOutOfService"), 'switch_off');
			print '</a>';

			if (count($floors) > 1) {
				print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="movetable">';
				print '<input type="hidden" name="rowid" value="'.((int) $obj->rowid).'">';
				print '<input type="hidden" name="floor" value="'.((int) $floornum).'">';
				print '<select name="targetfloor" class="flat">';
				foreach ($floors as $tfloornum => $tf) {
					if ($tfloornum == $floornum) {
						continue;
					}
					print '<option value="'.((int) $tfloornum).'">'.dol_escape_htmltag($tf['label']).'</option>';
				}
				print '</select>';
				print '<button type="submit" class="button smallpaddingimp">'.$langs->trans("PosFloorManagerMoveTable").'</button>';
				print '</form>';
			}

			print '<span class="pfm-room-spacer"></span>';

			if (!$occupied) {
				print '<a href="'.$_SERVER["PHP_SELF"].'?action=deletetable&token='.newToken().'&rowid='.((int) $obj->rowid).'" onclick="return confirm(\''.dol_escape_js($langs->trans("ConfirmDelete")).'\');" title="'.$langs->trans("Delete").'">'.img_picto($langs->trans("Delete"), 'delete').'</a>';
			}

			print '</div>';
		}
	} else {
		print '<div class="pfm-empty">'.$langs->trans("PosFloorManagerNoTableYet").'</div>';
	}

	print '<div class="pfm-actions-bar">';

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="addtable">';
	print '<input type="hidden" name="floor" value="'.((int) $floornum).'">';
	print '<button type="submit" class="butAction">'.$langs->trans("PosFloorManagerAddTable").'</button>';
	print '</form>';

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="bulkaddtables">';
	print '<input type="hidden" name="floor" value="'.((int) $floornum).'">';
	print '<input type="number" name="nbtocreate" class="flat" value="6" min="1" max="50" style="width:60px">';
	print '<button type="submit" class="butAction">'.$langs->trans("PosFloorManagerBulkAddTables").'</button>';
	print '</form>';

	if ($nbtables > 0) {
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="renumberfloor">';
		print '<input type="hidden" name="floor" value="'.((int) $floornum).'">';
		print '<button type="submit" class="butActionDelete" onclick="return confirm(\''.dol_escape_js($langs->trans("PosFloorManagerConfirmRenumber")).'\');">'.$langs->trans("PosFloorManagerRenumberFloor").'</button>';
		print '</form>';
	}

	print '</div>'; // pfm-actions-bar
	print '</div>'; // pfm-room-body
	print '</div>'; // pfm-room
}

if (empty($floors)) {
	print '<div class="opacitymedium">'.$langs->trans("PosFloorManagerNoFloorYet").'</div>';
}

llxFooter();
