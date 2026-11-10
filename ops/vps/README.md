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

## Maintenance, changements et traçabilité

1. Proposer toute évolution du script dans une PR dédiée et documenter chaque contrôle, son caractère read-only, les sorties et codes de retour.
2. Vérifier la syntaxe Bash et préparer une stratégie de retour arrière. Ne pas écrire vers la base, consommer Messenger, modifier les conteneurs ou les volumes depuis un audit.
3. Faire installer uniquement après revue par un administrateur, en conservant propriété root, permissions restrictives et la commande forcée existante. **Ce commit de versionnement n'installe rien.**
4. Enregistrer dans le journal de release : commit Git d'audit, empreinte SHA-256 Git, empreinte SHA-256 VPS, date, opérateur, résultat GitHub Actions et tout écart constaté.
5. Consulter l'[architecture globale](https://github.com/yoowii/infra-deploy/blob/main/docs/ARCHITECTURE-DEPLOIEMENT-AUDIT.md) dans `infra-deploy` pour les responsabilités CI/CD, la promotion, les contrôles HTTP et le rollback.

**Note :** le fichier versionné a été retranscrit à partir de la sortie fournie par l'administrateur. Il ne constitue pas encore une attestation cryptographique ; seule l'égalité des empreintes calculées sur les fichiers réels permettra de confirmer la correspondance.
