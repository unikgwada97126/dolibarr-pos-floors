<?php
/**
 *  \file       posfloormanager/admin/setup.php
 *  \brief      Page de configuration du module PosFloorManager
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
