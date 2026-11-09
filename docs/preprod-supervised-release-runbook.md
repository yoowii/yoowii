# Yoowii PR #14 — Fiche courte de recette supervisée et rollback

**Statut : préparation terminée, exécution NON autorisée — Draft / No-Go.** Hébergement VPS Plesk partagé ; autres sites hors périmètre. Ne pas exécuter cette fiche automatiquement. Responsable et fenêtre d'intervention à désigner.

## État de référence vérifié (09/11/2026)

- Release active : `sha-a4972eaba9e4a40f9e1feb4bd32f34dc4d7480c7` ; images PHP et Nginx présentes localement ; worker actif, même image PHP, `restart: unless-stopped`, commande `messenger:consume async` et DSN `doctrine://default?queue_name=yoowii_async`.
- MySQL 8.4 / Redis 7.4 healthy ; volumes nommés `yoowii-preprod_mysql_data`, `yoowii-preprod_media`, `yoowii-preprod_private_assets`. Nginx monte media en lecture seule.
- Baseline Messenger : file historique `default` = **19 messages disponibles, 0 réservés** ; aucune ligne dans `yoowii_async` et files fournisseur au moment du constat. Ces messages **ne doivent pas être consommés, purgés ou migrés**.
- Healthcheck réel (mêmes en-têtes que le déployeur) : **HTTP 200** sur `http://127.0.0.1:8086/fr_FR/`. Le Docker isolé retournait 302 : environnement différent, sans correction prévue.
- Snapshot Restic Nextcloud `7e0fb8e7` du 09/11/2026, sauvegarde locale `/opt/yoowii/backups/daily/2026-11-09_03-02-11` (MySQL, media, private, Compose, env et SHA256) ; restaurée avec succès sur machine locale jetable.
- Copie root-only des fichiers pré-intervention : `/opt/yoowii/rollback/2026-11-09-before-pr14` (mode 0700), manifeste SHA256 **4/4 OK** : déployeur, Compose, overlay worker, `preprod.env`. Ne jamais publier ce dossier ou ses secrets.
- PR #14 : tests rollback simulés et CI verts sur `47272c1`, documentation mise à jour ultérieurement. Les 79 produits de test désactivés avec médias manquants relèvent de l'issue #17, différée.

## Porte d'entrée / conditions Go pour une fenêtre *distinctement autorisée*

1. Choisir un opérateur présent, une fenêtre adaptée et un moyen de revenir à la version précédente ; sauvegarde **fraîche** et cohérente vérifiée juste avant l'opération. Confirmer la conservation hors du répertoire modifié du déployeur d'origine et de ses fichiers.
2. Relever juste avant l'intervention (lecture seule) : ID/image/état du worker, images PHP/Nginx, conteneurs MySQL/Redis, montages, tag en vigueur, compteurs Messenger par `queue_name` et HTTP strict 200. Ne pas lire le corps des messages ni imprimer les secrets.
3. Tester/simuler les cas de panne et restauration sur environnement jetable ; reconnaître qu'un SIGKILL/coupure et la reprise du handler métier réel ne sont pas prouvés.
4. Revoir **exactement** le script installé et celui de la PR, l'overlay approuvé, le tag de la nouvelle release et la CI de la tête de PR. Ne pas valider uniquement sur un ancien commit vert.
5. Requérir une **autorisation explicite séparée** pour installer le script root-owned et pour exécuter un déploiement réel. La présente fiche n'est pas une telle autorisation.

## Pendant une future recette supervisée (non exécutée)

- Exclure toute migration, `docker compose down`, suppression de volume, `prune`, purge/consommation manuelle Messenger, appel fournisseur/paiement, ou modification des autres sites Plesk.
- Observer les transitions du worker : stop gracieux (90 s), puis recréation PHP/Nginx, healthcheck strict 200, puis reprise worker **uniquement s'il était actif au départ**. Si worker absent ou arrêté initialement, ne pas l'activer.
- Sur erreur, l'opération doit sortir non-zéro ; vérifier la restauration de l'ancien Compose, de l'ancien tag, des images PHP/Nginx et de l'état du worker. Vérifier de nouveau les volumes et les compteurs de file. **Ne jamais annoncer un succès sur la seule présence de l'ancien tag.**
- Si le rollback échoue : arrêter toute nouvelle promotion, conserver les données, signaler incident et effectuer une reprise manuelle par l'opérateur à partir du dossier root-only et des images précédentes. Ne pas improviser une restauration MySQL sur le VPS.

## Conditions Stop / No-Go

- Différence de montages/volumes, worker sur image inattendue ou file fournisseur activée.
- Dernière sauvegarde fraîche non vérifiée ou point de retour indisponible.
- HTTP différent de 200 sur le chemin strict, avant ou après le swap.
- Erreur stop worker, perte d'ACK/doublon confirmé, rollback incomplet, CLI Docker incertain.
- CI tête de PR rouge ou non vérifiée, absence d'opérateur ou d'autorisation explicite.

**Verdict actuel : aucune installation ni déploiement autorisé.** L'état des données/volumes, les tests simulés et le point de retour sont prêts ; la recette opérationnelle et la preuve de reprise en panne brutale restent ouvertes.