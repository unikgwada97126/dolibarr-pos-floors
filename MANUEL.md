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

## 1. Vos salles/étages existants apparaissent automatiquement

Dès que vous ouvrez **Point de vente > Salles & Tables**, tous les étages qui
existent déjà dans TakePos (ceux qui ont au moins une table, même créée avant
l'installation de ce module) s'affichent tout seuls dans le tableau, avec un
nom par défaut ("Étage 1", "Étage 2"...). **Rien à recréer.**

Pour leur donner un vrai nom, voir section suivante.

## 1bis. Créer un TOUT NOUVEL étage (cas rare)

Utile uniquement pour un étage qui n'existe pas encore du tout (aucune table
créée nulle part, ni en natif ni ici) — par exemple avant d'ouvrir une
nouvelle terrasse.

1. Aller dans **Point de vente > Salles & Tables**.
2. En bas du tableau "Salles/étages", saisir un nom dans le champ "Créer un
   tout nouvel étage".
3. Cliquer sur **Créer la salle**.

Le numéro d'étage technique (1, 2, 3...) est attribué automatiquement.

## 2. Renommer une salle

Dans le tableau du haut, modifier directement le champ texte à côté du nom de
la salle puis cliquer sur **Enregistrer**.

## 3. Activer / désactiver une salle

Cliquer sur l'icône interrupteur à côté de la salle. Une salle désactivée
reste visible dans l'historique mais peut être masquée du choix courant
(utile pour une terrasse fermée l'hiver, par exemple).

## 4. Gestion des tables — tout se passe sous chaque salle

**La liste des tables de chaque salle s'affiche directement dessous, pour
toutes les salles en même temps** — il n'y a rien à cliquer pour la faire
apparaître, vous n'avez qu'à descendre dans la page. Sous chaque salle, vous
avez : la liste de ses tables (numéro, statut libre/occupée), et 3 boutons
d'action (ajouter une table, en créer plusieurs, renuméroter).

### Ajouter des tables

Sous la liste des tables de la salle voulue :
- **Ajouter une table** : crée une table, elle reçoit automatiquement le
  prochain numéro libre de cet étage (1, 2, 3...).
- **Créer plusieurs tables** : indiquer un nombre (max 50) et cliquer sur le
  bouton — utile pour initialiser une salle de 12 tables en un clic.

### Renommer une table

Dans la liste des tables de la salle, modifier le numéro dans le champ à côté
de la table puis **Enregistrer**.

- Si le numéro choisi est déjà utilisé **sur cet étage**, un message d'erreur
  s'affiche et rien n'est modifié.
- Le même numéro **peut** être réutilisé sur un **autre** étage (ex: "Table 1"
  en salle principale ET "Table 1" en terrasse) — c'est volontaire, c'est ce
  qui manquait dans TakePos natif.

### Déplacer une table vers une autre salle

Chaque table a un menu déroulant "Déplacer vers" listant toutes les autres
salles, avec un bouton **Déplacer**. La table change d'étage instantanément.

- Si le numéro de la table existe déjà sur la salle de destination, le
  déplacement est refusé avec un message clair — renommez la table d'abord
  (numéro libre), puis déplacez-la.
- Une table avec une commande ouverte peut être déplacée sans problème (la
  commande reste liée à la bonne table, peu importe l'étage).

### Supprimer une table ou une salle

- **Table** : bouton poubelle dans la liste des tables de la salle. Refusé si
  une commande/facture est ouverte sur cette table (icône rouge "Occupée") —
  fermez d'abord la commande en caisse.
- **Salle** : bouton poubelle dans l'en-tête de la salle, visible seulement si
  elle ne contient plus aucune table (déplacez ou supprimez ses tables
  d'abord).

## 5. Réparer une numérotation déjà en désordre

Si une salle a des numéros incohérents (avant l'installation du module, par
exemple), utilisez le bouton **Renuméroter cet étage** sous sa liste de
tables : toutes ses tables sont renommées 1, 2, 3... dans l'ordre de création.
Une confirmation est demandée avant d'agir (irréversible en un clic, mais sans
danger : ça ne touche que les numéros affichés, pas les commandes en cours).

## Problèmes fréquents

- **"Le numéro X est déjà utilisé sur cet étage"** → normal, choisissez un
  autre numéro ou consultez la liste des tables de cet étage pour voir
  lesquels sont pris.
- **Déplacement refusé** → le numéro existe déjà sur la salle de destination ;
  renommez la table avant de la déplacer.
- **Impossible de supprimer une table** → une commande est ouverte dessus ;
  terminez-la en caisse avant de supprimer.
- **Impossible de supprimer une salle** → elle contient encore des tables ;
  déplacez-les vers une autre salle ou supprimez-les d'abord.

## Installation (pour l'administrateur système)

Voir `README.md` à la racine de ce dépôt (copie dans `htdocs/custom/`,
activation depuis Configuration > Modules).
