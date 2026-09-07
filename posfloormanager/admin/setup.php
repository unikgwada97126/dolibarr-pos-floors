<?php
/**
 *  \file       posfloormanager/admin/setup.php
 *  \brief      Page de configuration du module PosFloorManager
 */

require '../../main.inc.php';
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

llxHeader('', $langs->trans("PosFloorManagerSetup"));

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("PosFloorManagerSetup"), $linkback, 'title_setup');

print '<span class="opacitymedium">'.$langs->trans("PosFloorManagerSetupDesc").'</span><br><br>';

print '<div class="info">';
print $langs->trans("PosFloorManagerHowItWorks");
print '</div><br>';

print '<div class="center">';
print '<a class="butAction" href="'.DOL_URL_ROOT.'/posfloormanager/admin/floors_tables.php">'.$langs->trans("PosFloorManagerOpenScreen").'</a>';
print '</div>';

llxFooter();
