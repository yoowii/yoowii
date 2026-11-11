# Script d'audit VPS Yoowii — référence versionnée

## Portée et origine

Le fichier [`ops/vps/yoowii-audit-readonly`](yoowii-audit-readonly) est une **copie à l'identique du texte** affiché par l'administrateur avec `cat /usr/local/sbin/yoowii-audit-readonly` le 10 novembre 2026, avec uniquement le saut de ligne final normalisé dans Git. Il s'agit d'un **snapshot documentaire**, pas d'un nouveau script à installer. Aucun changement du comportement des contrôles n'est voulu.

Le script effectivement exécuté reste **`/usr/local/sbin/yoowii-audit-readonly` sur le VPS**. Les fichiers Git ne se synchronisent pas automatiquement avec lui. Aucune règle SSH, sudoers, Plesk ou Docker n'est modifiée par l'ajout de cette copie.

## Dispositif actif

- Workflow GitHub Actions existant : [`audit-vps.yml`](../../.github/workflows/audit-vps.yml), `workflow_dispatch`, environnement **`preprod-audit`**.
- Utilisateur SSH : `yoowii-audit`, clé distincte de celle du déploiement ; commande forcée configurée côté VPS.
- Cible de la commande forcée : `sudo -n /usr/local/sbin/yoowii-audit-readonly` **sans arguments**.
- Autorisation sudoers historique attendue : `yoowii-audit ALL=(root) NOPASSWD: /usr/local/sbin/yoowii-audit-readonly ""` (à vérifier sur le VPS).
- Sorties : `RESULT failures=N warnings=M`, exit `0` OK, `1` avertissement(s), `2` erreur(s). Le workflow traite `1` comme un warning ; ce n'est pas une preuve d'absence d'anomalie.

Le script couvre les droits et propriétaires des fichiers sensibles, le format du tag `YOOWII_IMAGE_TAG`, le worker et son image, la file Doctrine `yoowii_async`, `messenger:consume async`, l'isolation mail/fournisseur et le HTTP local. Il **ne remplace pas** les contrôles séparés MySQL/Redis, les compteurs Messenger en base ni la vérification des volumes et sauvegardes.

## Vérifier la correspondance exacte Git ↔ VPS

**Important :** le texte reçu via `cat` ne fournit pas d'empreinte cryptographique du fichier installé. Le commit Git seul ne prouve donc **pas encore** que les octets présents sur le VPS sont strictement identiques. La comparaison ci-dessous doit être exécutée par l'opérateur.

Sur une machine avec le dépôt à jour, depuis sa racine :

```bash
git fetch origin main
git show origin/main:ops/vps/yoowii-audit-readonly | sha256sum
```

Sur le VPS, en lecture seule :

```bash
sudo sha256sum /usr/local/sbin/yoowii-audit-readonly
sudo stat -c 'uid=%u gid=%g mode=%a path=%n' /usr/local/sbin/yoowii-audit-readonly
```

Les deux empreintes SHA-256 **doivent être identiques**. Si elles diffèrent, ne pas écraser le script installé : collecter les deux versions et analyser les différences (notamment fins de ligne et espace final), puis ouvrir une PR expliquant la divergence. Ne pas publier d'informations sensibles si un script a été modifié pour inclure des secrets.

Pour vérifier la syntaxe du fichier versionné sans lancer d'audit :

```bash
git show origin/main:ops/vps/yoowii-audit-readonly | bash -n
```

Pour vérifier celle du fichier en service :

```bash
sudo bash -n /usr/local/sbin/yoowii-audit-readonly
```

## Évolution proposée — contrôles supplémentaires (non installés sur VPS)

La branche `feat/audit-vps-services-queues` ajoute au script versionné, **sans changer la commande forcée SSH ni les règles existantes** :

- Un seul conteneur attendu pour chaque service du projet Compose `yoowii-preprod` : PHP, Nginx, MySQL et Redis ; contrôle `running` et état de healthcheck MySQL/Redis (`healthy` requis).
- Comparaison des références d'images PHP/Nginx à `YOOWII_IMAGE_TAG` ; images MySQL `mysql:8.4` et Redis `redis:7.4-alpine` selon le manifeste Compose applicatif examiné.
- Vérification des montages Docker de type `volume` : PHP `/srv/sylius/public/media` et `/srv/sylius/var/private/print` en écriture, Nginx `/srv/sylius/public/media` en lecture seule, MySQL `/var/lib/mysql` en écriture. **Cela ne valide pas l'intégrité du contenu des volumes.**
- Une seule requête MySQL **`SELECT` agrégée** sur `messenger_messages`, par `queue_name`, affichant `total`, `unreserved` et `reserved`. Aucune sortie SQL non validée n'est imprimée ; aucun message n'est consommé, acquitté ou supprimé. Les noms de file sont limités à des caractères sûrs, et les compteurs doivent être cohérents.
- **Échec de requête / table manquante : WARN**, jamais un succès de lecture. Un écart d'image, de montage, de santé ou l'absence d'un service essentiel : **FAIL**. Le worker volontairement arrêté et le comportement historique des avertissements HTTP sont conservés.

**Attention :** ces contrôles sont une proposition Git jusqu'à validation, installation root-owned sur VPS et comparaison des empreintes. Ne pas supposer qu'ils sont exécutés par l'audit actuel. Le SQL se contente de compter les lignes ; les 19 messages historiques observés sur la file `default` ne constituent pas une valeur figée ni un seuil d'échec.

### Recette et installation contrôlées (hors de cette PR)

1. Vérifier la syntaxe `bash -n ops/vps/yoowii-audit-readonly`, le diff et le Compose effectivement déployé ; confirmer images, montages et table SQL avant tout remplacement.
2. Exécuter l'audit **en lecture seule** en tant qu'administrateur, sur une copie temporaire, sans modifier le script forcé existant ; contrôler explicitement les sorties PASS/WARN/FAIL et l'absence de secrets.
3. Conserver une sauvegarde du script précédent, installer la version revue avec `root:root`, `0755`, puis vérifier `sha256sum` Git ↔ VPS et les restrictions SSH/sudoers.
4. Relancer [le workflow d'audit existant](../../.github/workflows/audit-vps.yml), comparer le rapport et enregistrer le SHA du commit, l'empreinte SHA-256 et les résultats.
5. En cas de régression, restaurer uniquement le script d'audit précédent via procédure administrateur ; ne pas changer Docker, les messages, MySQL ou les autres sites Plesk.


### Redirection locale HTTP constatée (11/11/2026)

Le VPS répond à `HEAD http://127.0.0.1:8086/fr_FR/` avec `HTTP 302` et `Location: /en_US/` (redirection **relative**, constat fourni par l'administrateur). La reconnaissance de cette réponse est étendue pour produire un **WARN explicite** identique à celui de la forme absolue `http://127.0.0.1:8086/en_US/`. Cela ne classe **pas** le 302 comme un PASS et ne corrige pas le changement de locale : une revue des réglages de routing/localisation Sylius reste distincte. Le script installé sur VPS n'est pas automatiquement remplacé par cette modification Git.

## Maintenance, changements et traçabilité

1. Proposer toute évolution du script dans une PR dédiée et documenter chaque contrôle, son caractère read-only, les sorties et codes de retour.
2. Vérifier la syntaxe Bash et préparer une stratégie de retour arrière. Ne pas écrire vers la base, consommer Messenger, modifier les conteneurs ou les volumes depuis un audit.
3. Faire installer uniquement après revue par un administrateur, en conservant propriété root, permissions restrictives et la commande forcée existante. **Ce commit de versionnement n'installe rien.**
4. Enregistrer dans le journal de release : commit Git d'audit, empreinte SHA-256 Git, empreinte SHA-256 VPS, date, opérateur, résultat GitHub Actions et tout écart constaté.
5. Consulter l'[architecture globale](https://github.com/yoowii/infra-deploy/blob/main/docs/ARCHITECTURE-DEPLOIEMENT-AUDIT.md) dans `infra-deploy` pour les responsabilités CI/CD, la promotion, les contrôles HTTP et le rollback.

**Note :** le fichier versionné a été retranscrit à partir de la sortie fournie par l'administrateur. Il ne constitue pas encore une attestation cryptographique ; seule l'égalité des empreintes calculées sur les fichiers réels permettra de confirmer la correspondance.
