# Préproduction Yoowii — contrôles d'exploitation et durcissement (#5)

**État** : procédures proposées à valider sur VPS. Ce document n'atteste ni d'une restauration complète réussie, ni d'un audit réseau effectif, ni du moindre privilège Nextcloud.

## Architecture connue

- VPS sous Plesk avec proxy HTTPS / Basic Auth vers Docker Nginx publié uniquement sur `127.0.0.1:8086`.
- Services `php`, `nginx`, `mysql`, `redis` ; volumes persistants `mysql_data`, `media`, `private_assets`.
- Secret applicatif et accès fournisseur conservés dans l'`.env` du VPS, hors dépôt.
- Sauvegardes locales quotidiennes et sauvegardes chiffrées Restic vers Nextcloud déjà installées selon le suivi d'exploitation ; **leur succès actuel doit être vérifié indépendamment**.
- Le passage en production reste bloqué par la sécurité Préscript (#2) et les recettes de commande (#1).

## Vérifications en lecture seule

À exécuter depuis une session root autorisée. Ne pas coller les secrets ou DSN dans les issues, ni des URLs contenant une clé de partage.

```bash
cd /opt/yoowii/preprod

# Services, images, ports effectivement publiés et empreinte du Compose
docker compose --env-file .env -f compose.preprod.yml ps
docker ps --format 'table {{.Names}}\t{{.Ports}}'
sha256sum compose.preprod.yml
grep '^YOOWII_IMAGE_TAG=' .env

# Permissions — ne pas afficher le contenu des secrets
stat -c '%a %U:%G %n' .env compose.preprod.yml /usr/local/sbin/yoowii-deploy-preprod
sudo -l -U deploy-yoowii

# Présence/permissions et rétention locale
find /opt/yoowii/backups/daily -mindepth 1 -maxdepth 1 -type d -printf '%TY-%Tm-%Td %TH:%TM %p\n' | sort -r | head -10

# Utilisation Docker (ne pas purger les images de rollback)
docker system df
```

Vérifier séparément la configuration du proxy Plesk : authentification Basic active, HTTPS, certificat valide, HSTS, absence d'exposition directe du backend, configuration des en-têtes transmis au PHP et restrictions d'accès à l'administration.

### Restic / Nextcloud

1. Remplacer le compte administrateur Nextcloud par un **compte de sauvegarde dédié**, limité au dépôt approprié, et sauvegarder hors du VPS le secret Restic (coffre de secrets et procédure de récupération).
2. Valider les autorisations du compte : écriture de sauvegarde et lecture pour restauration, puis étudier un mécanisme de protection contre la suppression distante ; il n'est pas toujours possible d'avoir l'immutabilité avec un simple WebDAV Nextcloud.
3. Vérifier la cohérence de la politique de rétention (7 journalières / 4 hebdomadaires) sur **le dépôt réellement utilisé**, sans exposer le mot de passe :
   `restic snapshots` puis `restic check` dans un environnement où `RESTIC_REPOSITORY` et les secrets sont déjà initialisés.
4. Procéder à une **restauration dans un répertoire ou serveur isolé**, et tester la disponibilité de MySQL, des médias et des fichiers privés. Ne jamais restaurer par-dessus les volumes de la préproduction pour une simple recette.
5. Tester une alerte d'échec de sauvegarde sans transmettre un véritable incident au fournisseur ni exposer les données personnelles.

## Barrières de promotion vers la production

- [ ] Issue #2 : origine dédiée pour Préscript, CSP, iframe sandbox et callback à usage unique validés.
- [ ] Issue #1 : parcours devis/panier et commande simulée testés pour Realisaprint classique et Préscript ; aucune vraie commande de test n'est envoyée au fournisseur.
- [ ] Issue #3 : images et Compose associés au même commit, signature/provenance du Compose, rollback et déploiement automatique confirmés.
- [ ] Issue #4 : transport Messenger, supervision des workers, échecs, cron et prévention des emails client involontaires vérifiés.
- [ ] Contrôles sécurité : gestion des secrets, pare-feu, authentification administrateur, restauration hors VPS, moindre privilège Nextcloud et stratégie de rollback prod.
- [ ] Exercice de restauration documenté avec durée, responsable, version des fichiers, somme de contrôle et résultat.

**Aucune promotion en production ne doit être déclenchée par une fusion de documentation ou de CI.**
