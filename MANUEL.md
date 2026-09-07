# Manuel d'utilisation — Salles & Tables POS

Ce module ajoute un écran **"Salles & Tables"** dans le menu gauche du module
**Point de vente (TakePos)** de Dolibarr. Il ne remplace pas l'écran natif de
plan de salle : il le complète pour que la numérotation des tables reste
propre.

## Qui fait quoi entre les 2 écrans ?

| Écran | Pour quoi faire |
|---|---|
| **TakePos natif** (icône "Editer" dans l'écran de caisse, plan de salle) | Positionner visuellement les tables sur le plan (glisser-déposer). |
| **Ce module** (menu gauche "Salles & Tables") | Créer/nommer une salle ou un étage, ajouter/renommer/supprimer des tables, réparer une numérotation existante. |

Peu importe lequel des deux écrans vous utilisez pour ajouter ou renommer une
table : la numérotation propre par étage s'applique dans les deux cas (elle
est garantie au niveau de la base de données, pas seulement dans l'écran du
module).

## 1. Créer une salle / un étage

1. Aller dans **Point de vente > Salles & Tables**.
2. En bas du tableau "Salles/étages", saisir un nom (ex: *Terrasse*, *Salle
   principale*, *Étage 1*) dans le champ "Nouvelle salle/étage".
3. Cliquer sur **Créer la salle**.

Le numéro d'étage technique (1, 2, 3...) est attribué automatiquement — vous
n'avez qu'à gérer le **nom**.

## 2. Renommer une salle

Dans le tableau du haut, modifier directement le champ texte à côté du nom de
la salle puis cliquer sur **Enregistrer**.

## 3. Activer / désactiver une salle

Cliquer sur l'icône interrupteur à côté de la salle. Une salle désactivée
reste visible dans l'historique mais peut être masquée du choix courant
(utile pour une terrasse fermée l'hiver, par exemple).

## 4. Ajouter des tables à une salle

1. Cliquer sur le **numéro** dans la colonne "Nb tables" de la salle voulue
   (ou sur son nom) pour afficher le détail de ses tables.
2. Deux façons d'ajouter :
   - **Ajouter une table** : crée une table, elle reçoit automatiquement le
     prochain numéro libre de cet étage (1, 2, 3...).
   - **Créer plusieurs tables** : indiquer un nombre (max 50) et cliquer sur
     le bouton — utile pour initialiser une salle de 12 tables en un clic.

## 5. Renommer une table

Dans le détail d'une salle, modifier le numéro dans le champ à côté de la
table puis **Enregistrer**.

- Si le numéro choisi est déjà utilisé **sur cet étage**, un message d'erreur
  s'affiche et rien n'est modifié.
- Le même numéro **peut** être réutilisé sur un **autre** étage (ex: "Table 1"
  en salle principale ET "Table 1" en terrasse) — c'est volontaire, c'est ce
  qui manquait dans TakePos natif.

## 6. Supprimer une table ou une salle

- **Table** : bouton poubelle dans le détail de la salle. Refusé si une
  commande/facture est ouverte sur cette table (icône rouge "Occupée") —
  fermez d'abord la commande en caisse.
- **Salle** : bouton poubelle dans le tableau du haut, visible seulement si
  la salle ne contient plus aucune table.

## 7. Réparer une numérotation déjà en désordre

Si une salle a des numéros incohérents (avant l'installation du module, par
exemple), ouvrez son détail et cliquez sur **Renuméroter cet étage** : toutes
ses tables sont renommées 1, 2, 3... dans l'ordre de création. Une
confirmation est demandée avant d'agir (irréversible en un clic, mais sans
danger : ça ne touche que les numéros affichés, pas les commandes en cours).

## Problèmes fréquents

- **"Le numéro X est déjà utilisé sur cet étage"** → normal, choisissez un
  autre numéro ou consultez la liste des tables de cet étage pour voir
  lesquels sont pris.
- **Impossible de supprimer une table** → une commande est ouverte dessus ;
  terminez-la en caisse avant de supprimer.
- **Impossible de supprimer une salle** → elle contient encore des tables ;
  supprimez-les ou déplacez-les d'abord (il n'y a pas de "déplacer" — créez la
  table sur la bonne salle et supprimez l'ancienne).

## Installation (pour l'administrateur système)

Voir `README.md` à la racine de ce dépôt (copie dans `htdocs/custom/`,
activation depuis Configuration > Modules).
