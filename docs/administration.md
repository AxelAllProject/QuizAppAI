# Rôles et clés d'accès

## Les trois rôles

| Rôle | Comment on l'obtient | Ce qu'il permet |
|---|---|---|
| **Joueur** | un compte (e-mail + mot de passe), sans rien d'autre | parcourir la bibliothèque, jouer en solo, rejoindre les parties en direct, voir sa correction, les classements, son historique |
| **Professeur** | compte + **clé professeur** | tout ce qui précède + **créer, modifier et supprimer ses propres quiz**, **animer des parties en direct** |
| **Administrateur** | compte + **clé administrateur** | tout ce qui précède + **modérer tous les quiz**, **générer et révoquer les clés**, **changer le rôle des comptes**, tableau de bord |

Une clé n'est pas un compte : elle donne un rôle **au compte qui la saisit**. Plusieurs
professeurs peuvent utiliser la même clé — chacun garde son propre compte, qui signe
les quiz qu'il rédige. Une clé ne fait que **monter** en grade : un administrateur qui
saisit une clé professeur reste administrateur.

## Les clés en place

| Rôle | Clé | Étiquette |
|---|---|---|
| **Administrateur** | `2MZL-SEPP-F67F-9TGN` | Compte administrateur principal |
| **Professeur** | `6YW5-4X49-2FFX-NB5P` | Équipe pédagogique |

Il existe en plus une **clé de secours** définie dans l'environnement
(`ADMIN_CODE` dans `backend/.env.local`) : `XPYX-KDEU-2RLE-97PV`. Elle donne le rôle
admin et ne peut pas être révoquée depuis l'interface — c'est le filet de sécurité
si toutes les clés admin de la base venaient à être révoquées.

> Ces clés sont des identifiants de **développement local**. Ne les recopie pas dans
> un dépôt public : régénère-les avant toute mise en ligne.

## Utiliser une clé

**À l'inscription**

1. Ouvrir http://localhost:5173 → onglet **Créer un compte**
2. Renseigner e-mail, pseudo (ex. `mme.martin`) et mot de passe
3. Cliquer sur **« J'ai une clé d'accès (professeur ou admin) »** et coller la clé
4. Accepter la politique de confidentialité, puis **Créer mon compte**

**Avec un compte existant** : **Mon compte → Clé d'accès**, coller la clé, **Valider la clé**.

Le rôle s'affiche sous le pseudo en haut à droite. Le menu **Créer** et le bouton
**En direct** apparaissent pour les professeurs et les admins ; **Administration**,
pour les admins seulement.

## Générer et révoquer des clés depuis l'interface (admin)

**Administration → Clés d'accès** :

- choisir **Clé professeur** ou **Clé administrateur** ;
- soit **chercher un compte existant** par pseudo dans le champ dédié et cliquer
  **Attribuer** : le rôle est accordé tout de suite à cette personne, sans code à
  transmettre — la ligne affiche « Attribuée à … » ;
- soit laisser ce champ vide, ajouter une étiquette (« Mme Martin », « Direction »…)
  et cliquer **Générer une clé** : un code à transmettre à la personne, qui le saisit
  elle-même (à l'inscription ou dans **Mon compte**) ;
- **Copier** met un code généré dans le presse-papiers ;
- **Révoquer** désactive un code généré : il ne peut plus être saisi. La ligne reste
  visible, grisée, avec son nombre d'utilisations.

Révoquer une clé **ne retire pas** le rôle aux comptes qui l'ont déjà utilisée.
Pour cela : **Administration → Comptes**, changer le rôle dans la liste. L'effet est
**immédiat**, sans que la personne ait à se déconnecter (le rôle est relu en base à
chaque requête). Un administrateur ne peut pas retirer ses propres droits.

## Clés IA (fonctionnalité premium)

Distinctes des clés d'accès : avoir le rôle professeur ou administrateur ne suffit pas
pour générer un quiz par IA, il faut **aussi** une clé IA. Une clé IA donne un nombre fixe
de générations (5 par défaut) et peut expirer ; elle se lie au **premier compte** qui la
saisit et personne d'autre ne peut ensuite l'utiliser.

**Administration → Clés IA** : choisir le nombre de générations et, si besoin, une
expiration (en jours). Comme pour les clés d'accès, chercher un compte existant et cliquer
**Attribuer** la lie tout de suite à cette personne ; sinon, ajouter une étiquette et
cliquer **Générer une clé** produit un code que la personne saisit elle-même dans
**Mon compte → Clé IA**. **Révoquer** coupe l'accès immédiatement, y compris pour les
générations déjà consommées ; ça n'efface pas l'historique.

Supprimer le compte qui détenait une clé IA la libère (redevient saisissable par
quelqu'un d'autre), mais **ne restaure pas** les générations déjà consommées.

## Générer des clés en ligne de commande

```bash
cd backend

php bin/console app:access-key --list                        # toutes les clés et leur état
php bin/console app:access-key prof  --label="Mme Martin"    # nouvelle clé professeur
php bin/console app:access-key admin --label="Direction"     # nouvelle clé administrateur

php bin/console app:admin-key                                # régénère la clé de secours (.env.local)
php bin/console app:admin-key --show                         # affiche la clé de secours
```

Format `XXXX-XXXX-XXXX-XXXX`, tiré par `random_int` (source cryptographique) dans un
alphabet **sans caractères ambigus** — ni `O`/`0`, ni `I`/`1` — pour se dicter sans erreur.

Changer la clé de secours demande un **redémarrage du serveur**
(`symfony server:stop && symfony server:start -d`) ; les clés créées en base sont
actives immédiatement, sans redémarrage.

## Données personnelles

- **Purge quotidienne** à planifier (cron) : `php bin/console app:rgpd:purge`
  — jetons expirés, parties en direct de plus de 24 h, comptes inactifs depuis 3 ans.
  `--dry-run` affiche ce qui serait supprimé.
- Une personne qui demande l'effacement de son compte peut le faire elle-même
  (**Mon compte → Supprimer mon compte**). Pour une demande de rectification
  (e-mail, pseudo), écrire directement en base ou supprimer puis recréer le compte.
- Avant mise en ligne : compléter le responsable du traitement, le contact et
  l'hébergeur dans `frontend/src/pages/Privacy.jsx`.
