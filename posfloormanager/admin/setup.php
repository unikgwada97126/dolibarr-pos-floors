<?php
/**
 *  \file       posfloormanager/admin/setup.php
 *  \brief      Page de configuration du module PosFloorManager
 */

// Le module est installé dans htdocs/custom/posfloormanager/ : depuis
// admin/, il faut remonter 3 niveaux (admin -> posfloormanager -> custom -> htdocs)
// pour atteindre main.inc.php. Gère aussi le cas d'un module posé directement
// sous htdocs/ (hors custom/), moins courant mais possible.
$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array("admin", "posfloormanager@posfloormanager"));

if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

llxHeader('', $langs->trans("PosFloorManagerSetup"));

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("PosFloorManagerSetup"), $linkback, 'title_setup');

print '<span class="opacitymedium">'.$langs->trans("PosFloorManagerSetupDesc").'</span><br><br>';

print '<div class="info">';
print $langs->trans("PosFloorManagerHowItWorks");
print '</div><br>';

print '<div class="center">';
print '<a class="butAction" href="'.dol_buildpath('/posfloormanager/admin/floors_tables.php', 1).'">'.$langs->trans("PosFloorManagerOpenScreen").'</a>';
print '</div>';

llxFooter();
