# PosFloorManager — module Dolibarr 23.x

Module additionnel qui corrige la numérotation des tables/étages du module
natif **TakePos** (POS), sans modifier aucun fichier du core.

## Le bug corrigé (vérifié sur le code source officiel Dolibarr 23.0.1)

- `llx_takepos_floor_tables` a une contrainte `UNIQUE(entity, label)` **globale
  à tout le restaurant**, pas par étage. Renommer une table vers un numéro
  déjà pris sur un *autre* étage échoue silencieusement (`floors.php` ne
  vérifie jamais le résultat de la requête).
- À la création d'une table, TakePos fait `label = rowid` (compteur global
  auto-incrémenté) : la numérotation ne redémarre jamais à 1 par étage et se
  décale à chaque ajout/suppression.
- Aucun étage nommé n'existe (juste un entier navigué par flèches), aucun
  écran de gestion globale des salles.
- `takepos/floors.php` n'appelle **jamais** `$hookmanager` : un module à
  hooks classique est donc techniquement impossible sur cet écran.

## Comment ce module corrige ça

Il agit au niveau base de données (déclenché à l'activation du module,
fonction `init()` de `modPosFloorManager.class.php`) :

1. Migre l'index unique `uk_takepos_floor_tables` de `(entity, label)` vers
   `(entity, floor, label)` → un numéro de table peut désormais se répéter
   d'un étage à l'autre (comportement normal attendu dans un restaurant),
   mais reste unique **sur un même étage**.
2. Pose 2 triggers MySQL sur `llx_takepos_floor_tables` :
   - `pfm_before_insert_floor_table` : sécurise la colonne `floor`.
   - `pfm_before_update_floor_table` : intercepte le `UPDATE` que TakePos fait
     juste après la création d'une table (celui qui remplaçait le label par
     le rowid brut) et le remplace par le prochain numéro **libre et
     séquentiel sur cet étage**. Intercepte aussi tout renommage vers un
     numéro déjà pris sur le même étage et le bloque proprement
     (`SIGNAL SQLSTATE '45000'`) au lieu de l'échec silencieux natif.

Ces triggers s'appliquent **quel que soit l'écran utilisé** : l'écran natif
TakePos (`floors.php`, mode "Editer") continue de fonctionner normalement
pour le placement visuel des tables (glisser-déposer) et l'ouverture d'une
commande — seule la logique de numérotation change, en mieux.

3. Ajoute un écran d'administration (`Point de vente > Salles & Tables` dans
   le menu gauche) qui n'existe pas nativement dans TakePos :
   - Créer/renommer/désactiver/supprimer une **salle nommée** (ex: "Salle
     principale", "Terrasse", "Étage 1") — mappée sur l'entier `floor` de
     TakePos.
   - Ajouter une table (ou plusieurs d'un coup) sur un étage, avec numérotation
     automatique propre.
   - Renommer/supprimer une table (bloqué si une commande/facture est ouverte
     dessus).
   - **Outil de réparation** : renumérote en un clic toutes les tables d'un
     étage en 1, 2, 3... (utile pour nettoyer une numérotation existante déjà
     incohérente avant l'installation du module).

## Installation

1. Copier le dossier `posfloormanager/` dans `htdocs/custom/` du serveur
   Dolibarr (ex: `htdocs/custom/posfloormanager/`).
2. Se connecter en admin Dolibarr → **Configuration > Modules/Applications**.
3. Si besoin, activer le filtre "Modules non certifiés / tiers" en haut de
   la page des modules.
4. Chercher **"Gestion Salles & Tables POS"** et cliquer sur Activer.
   L'activation crée automatiquement la table technique, migre l'index
   unique et pose les 2 triggers (aucune manipulation SQL manuelle requise).
5. Le menu **Salles & Tables** apparaît dans le menu gauche du module TakePos.

## Points à vérifier avant activation (je n'ai pas d'accès à votre serveur)

- **Numéro de module (`numero = 100450`)** : plage réservée aux modules non
  publiés, mais peut entrer en collision avec un autre module custom déjà
  installé chez vous. Si Dolibarr refuse l'activation en signalant un
  conflit de numéro, changez la valeur dans
  `core/modules/modPosFloorManager.class.php` (ex: `100451`, `100999`...).
- **Base de données** : les triggers sont écrits en syntaxe **MySQL/MariaDB**
  (moteur standard de Dolibarr). Si votre instance tourne sur PostgreSQL,
  dites-le moi : la syntaxe des triggers doit être adaptée (PL/pgSQL).
- Le module dépend de TakePos (`modTakePos`) déjà activé.

## Ce qui n'a pas pu être testé de bout en bout

Je n'ai pas accès à votre serveur Dolibarr (le seul serveur connu dans mon
contexte est un Synology `.78` actuellement injoignable depuis mon
environnement). Le code a été :
- vérifié ligne à ligne contre le code source réel de TakePos 23.0.1
  (schéma SQL, contrainte unique, logique de `floors.php`) ;
- linté avec `php -l` (aucune erreur de syntaxe).

Il reste à activer le module sur votre instance réelle et à tester le
scénario : créer 2 étages, ajouter des tables sur chacun, vérifier qu'ils
peuvent partager les mêmes numéros, tenter un doublon sur le même étage
(doit être refusé), utiliser l'outil de renumérotation. Dites-moi comment ça
se passe, je corrige si un comportement diffère de ce qui est documenté ici.
