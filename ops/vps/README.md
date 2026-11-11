# Audit VPS Yoowii — installation, exécution et rollback

**Périmètre :** préproduction Yoowii sous Plesk. **Script versionné :** [ops/vps/yoowii-audit-readonly](yoowii-audit-readonly). **Script utilisé par GitHub Actions sur le VPS :** `/usr/local/sbin/yoowii-audit-readonly`.

> Mise à jour documentaire du 11 novembre 2026. La version V2 (services Docker, montages et compteurs Messenger) et la correction du HTTP 302 relatif ont été exécutées **en copie temporaire** sur le VPS avec `RESULT failures=0 warnings=1`. Cela **ne prouve pas** que la V2 a été installée à l'emplacement actif ; faire un contrôle d'empreinte avant de conclure. Cette documentation ne réalise aucun changement sur le VPS.

## 1. Architecture et contrôles

- Dépôt applicatif : [yoowii/yoowii](https://github.com/yoowii/yoowii).
- Workflow existant : [Audit Yoowii VPS (read-only)](../../.github/workflows/audit-vps.yml), déclenchement **manuel** (`workflow_dispatch`) sur la branche `main`, environnement GitHub **`preprod-audit`**.
- Accès dédié : compte `yoowii-audit` avec clé SSH distincte de la clé de déploiement ; la clé doit être limitée par `authorized_keys` à la **commande forcée** `sudo -n /usr/local/sbin/yoowii-audit-readonly`.
- Autorisation sudoers attendue : `yoowii-audit ALL=(root) NOPASSWD: /usr/local/sbin/yoowii-audit-readonly ""`. Le suffixe `""` interdit les arguments ; vérifier la règle réellement installée avant de la considérer comme acquise.
- Script root-owned, sans arguments ; ne pas donner d'accès direct au shell, à Docker, ni de sudo général à `yoowii-audit`. Ne pas réutiliser la clé du déployeur.

La V2 vérifie les permissions des fichiers sensibles, la forme du SHA release, l'état du worker et sa configuration effective (Doctrine, file `yoowii_async`, `messenger:consume async`, isolation mail et Realisaprint), PHP/Nginx/MySQL/Redis, les références d'images, les montages persistants attendus et les **compteurs** SQL de `messenger_messages` (lecture seule). Elle sonde également le HTTP local.

**Limites :** un état `running` ou `healthy` ne prouve pas un parcours marchand fonctionnel ; le contrôle des volumes porte sur leurs montages, pas sur leur contenu ; le comptage ne lit ni ne valide le contenu des messages. Le SQL échoué émet un avertissement et **ne signifie pas** « file vide ». Ne jamais consommer/purger les messages de `default` (19 messages relevés lors de la recette du 11/11/2026, valeur historique), ni modifier les volumes, ni effectuer une migration, ni toucher aux autres sites Plesk.

## 2. Préparer une nouvelle version — avant toute installation

Conditions : accès administrateur VPS autorisé, commit Git déjà revu/fusionné, absence de déploiement ou de rollback en cours, sauvegarde du script actuel et procédure de retour arrière disponible. Comparer aussi les chemins, images et montages attendus avec le Compose **réellement déployé**.

Depuis le VPS, dans une session administrateur, **remplacer** `COMMIT_AUDITE` ci-dessous par le SHA complet du commit Git **approuvé** (ne pas utiliser un tag non vérifié) :

```bash
# Préparation : aucune modification du script actif.
COMMIT_AUDITE='COMMIT_AUDITE'
test "${#COMMIT_AUDITE}" -eq 40 || { echo 'SHA Git invalide'; exit 1; }

umask 077
curl --fail --silent --show-error --location \
  "https://raw.githubusercontent.com/yoowii/yoowii/${COMMIT_AUDITE}/ops/vps/yoowii-audit-readonly" \
  -o /tmp/yoowii-audit-readonly-candidate

bash -n /tmp/yoowii-audit-readonly-candidate
sha256sum /tmp/yoowii-audit-readonly-candidate
sudo sha256sum /usr/local/sbin/yoowii-audit-readonly
diff -u /usr/local/sbin/yoowii-audit-readonly /tmp/yoowii-audit-readonly-candidate || test "$?" -eq 1
```

Le `diff` peut normalement retourner 1 quand des contrôles ont été ajoutés. **Lire et approuver les modifications** ; ne pas poursuivre si un contenu inattendu apparaît. Vérifier l'origine Git du candidat et son empreinte depuis une copie de confiance :

```bash
# Depuis un clone de yoowii/yoowii sur votre poste :
git fetch origin main
set -o pipefail
git show COMMIT_AUDITE:ops/vps/yoowii-audit-readonly | sha256sum
```

Les empreintes candidat/Git doivent être égales. Ne jamais comparer une empreinte produite par une commande Git en échec avec un `sha256sum` sur son stdout vide : utiliser `set -o pipefail` pour les pipelines de vérification.

## 3. Exécuter l'audit manuellement sur le VPS (lecture seule)

**Audit actif**, sans modification :

```bash
sudo /usr/local/sbin/yoowii-audit-readonly
echo "audit_exit_code=$?"
```

**Recette d'une nouvelle version avant remplacement**, après revue du fichier temporaire :

```bash
sudo bash /tmp/yoowii-audit-readonly-candidate
echo "candidate_exit_code=$?"
```

Ces commandes exécutent des inspections Docker et une requête agrégée `SELECT` sur Messenger : elles ne doivent ni démarrer/arrêter un service ni modifier la base ou les volumes. La sortie doit être inspectée avant promotion :

| Sortie | Code | Interprétation |
| --- | --- | --- |
| `RESULT failures=0 warnings=0` | 0 | Tous les contrôles exécutés sont passés |
| `RESULT failures=0 warnings=N` avec `N > 0` | 1 | Avertissement(s) à examiner ; **pas** un succès sans réserve |
| `RESULT failures=N warnings=M` avec `N > 0` | 2 | Contrôle(s) en échec ; ne pas promouvoir le candidat |

La recette VPS du **11/11/2026** a rendu `RESULT failures=0 warnings=1`, avec PHP/Nginx `running`, MySQL/Redis `healthy`, worker conforme, montages présents, `default total=19 unreserved=19 reserved=0`. Le `WARN` est la redirection locale `HTTP 302`, `Location: /en_US/` depuis `/fr_FR/`. Cette redirection reste à corriger séparément côté routing/localisation Sylius. Le script **reconnaît** les formes relative `/en_US/` et absolue `http://127.0.0.1:8086/en_US/` mais les signale toujours comme `WARN`.

## 4. Installation contrôlée du script d'audit

**Faire seulement après validation du candidat.** La commande forcée dans `authorized_keys` doit continuer à pointer vers **le même chemin**. Aucune modification de clés, comptes, sudoers ou workflows n'est nécessaire pour remplacer le fichier.

```bash
set -Eeuo pipefail

# Sauvegarde distincte et horodatée dans le répertoire protégé, avant remplacement.
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
BACKUP="/usr/local/sbin/yoowii-audit-readonly.backup-${STAMP}"
sudo cp -a /usr/local/sbin/yoowii-audit-readonly "$BACKUP"
sudo stat -c '%U:%G %a %n' "$BACKUP"

# Installation de la version contrôlée (aucun autre fichier, volume ou service modifié).
sudo install -o root -g root -m 0755 \
  /tmp/yoowii-audit-readonly-candidate \
  /usr/local/sbin/yoowii-audit-readonly

# Contrôles post-installation : syntaxe, permissions, empreinte, exécution.
sudo bash -n /usr/local/sbin/yoowii-audit-readonly
sudo stat -c '%U:%G %a %n' /usr/local/sbin/yoowii-audit-readonly
sudo sha256sum /usr/local/sbin/yoowii-audit-readonly
```

Comparer l'empreinte installée à l'empreinte **approuvée** du fichier Git. Si une étape échoue, ne pas supposer l'installation réussie : restaurer le fichier de sauvegarde identifié ci-dessus selon § 6. Conserver le chemin de sauvegarde dans le journal d'intervention.

## 5. Lancer l'audit depuis GitHub Actions

1. Ouvrir [**Actions → Audit Yoowii VPS (read-only)**](https://github.com/yoowii/yoowii/actions/workflows/audit-vps.yml) dans le dépôt **`yoowii/yoowii`**.
2. Cliquer **Run workflow**, choisir **`main`**, puis **Run workflow**. Il n'y a aucun SHA ni argument à saisir.
3. Ouvrir le run → job **Read-only preprod diagnostic** → consulter les lignes `PASS`, `WARN`, `FAIL` et `RESULT`. Conserver l'URL du run.
4. Le workflow utilise les secrets de l'**environnement `preprod-audit` du dépôt `yoowii/yoowii`** : `YOOWII_AUDIT_HOST`, `YOOWII_AUDIT_SSH_KEY`, `YOOWII_AUDIT_KNOWN_HOSTS`. Leur valeur n'est jamais à copier dans les logs ou dans `.env`.
5. Le job vérifie l'empreinte de l'hôte et utilise une connexion SSH sans PTY ni commande distante arbitraire. Le VPS choisit la commande forcée. Le code SSH `0` = succès ; `1` = avertissement (le workflow reste vert avec `::warning::`) ; `2` ou erreur SSH `255` = échec.

**Attention :** le workflow lit toujours **la version réellement installée** à `/usr/local/sbin/yoowii-audit-readonly`, et non le script Git ou un candidat en `/tmp`. Un workflow vert ne prouve pas à lui seul la parité SHA-256 Git/VPS.

**Sécurité opérationnelle :** ne pas lancer cet audit pendant une opération de promotion/rollback en cours. Les verrous GitHub situés dans des dépôts différents ne garantissent pas une exclusion mutuelle. Ne pas modifier le compte SSH existant pour résoudre un problème de workflow.

## 6. Rollback du **script d'audit** (pas celui de l'application)

Ce rollback ne concerne **que** `/usr/local/sbin/yoowii-audit-readonly`. Il n'est pas le [rollback applicatif d'infra-deploy](https://github.com/yoowii/infra-deploy/blob/main/docs/ROLLBACK-PREPROD.md) et ne change ni PHP, Nginx, MySQL, Redis, volumes, files Messenger ni release SHA.

Si un changement du script provoque un échec, après avoir identifié la sauvegarde validée à la § 4 :

```bash
# Remplacer BACKUP_VERIFIE par le chemin exact noté pendant l'installation.
BACKUP_VERIFIE='/usr/local/sbin/yoowii-audit-readonly.backup-YYYYMMDDTHHMMSSZ'

sudo test -f "$BACKUP_VERIFIE"
sudo bash -n "$BACKUP_VERIFIE"
sudo sha256sum "$BACKUP_VERIFIE"
sudo cp -a "$BACKUP_VERIFIE" /usr/local/sbin/yoowii-audit-readonly
sudo bash -n /usr/local/sbin/yoowii-audit-readonly
sudo stat -c '%U:%G %a %n' /usr/local/sbin/yoowii-audit-readonly
sudo sha256sum /usr/local/sbin/yoowii-audit-readonly
```

Vérifier ensuite `sudo /usr/local/sbin/yoowii-audit-readonly`, puis relancer le workflow GitHub manuel et enregistrer les résultats. **Ne pas** restaurer la base ou redémarrer des conteneurs pour corriger une régression du script d'audit.

## 7. Traçabilité et maintenance

Conserver dans le journal d'intervention : date UTC, opérateur, PR et SHA Git approuvé, empreinte du candidat, empreinte VPS avant/après, chemin de sauvegarde, rapport de recette, résultat d'audit local, URL du run GitHub, warnings connus, décision de validation/retour arrière.

Chaque évolution du script doit faire l'objet d'une PR et de la validation `bash -n` (workflow [Lint VPS audit](../../.github/workflows/lint-vps-audit.yml)). Ne jamais installer automatiquement un script via un simple merge Git. Ne pas y ajouter d'opérations de mutation Docker, de consommation/purge Messenger ni d'accès non contrôlé aux secrets.

Architecture générale, déploiement et rollback **applicatif** : [yoowii/infra-deploy — architecture et traçabilité](https://github.com/yoowii/infra-deploy/blob/main/docs/ARCHITECTURE-DEPLOIEMENT-AUDIT.md).
