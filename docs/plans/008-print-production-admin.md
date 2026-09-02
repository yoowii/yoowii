# Lot 7 — Back-office de production PrintJob

## Objectif

Rendre les dossiers `PrintJob` exploitables depuis Sylius Admin, avant toute connexion automatique à un imprimeur ou à un transporteur.

## Périmètre livré

- entrée « Production print » dans le menu d’administration ;
- liste des dossiers avec recherche par référence, commande ou produit ;
- filtres par statut et fournisseur ;
- fiche d’un dossier : commande, client, fournisseur, configuration et snapshot de production ;
- consultation sécurisée de tous les fichiers, y compris les versions remplacées ;
- dépôt d’un BAT par un opérateur ;
- transitions de statut strictement autorisées ;
- référence de commande fournisseur et passage en production ;
- numéro et URL de suivi, puis passage en expédition ;
- historique horodaté des opérations opérateur, des dépôts de fichier client et de l’approbation du BAT.

## Transitions autorisées

```text
awaiting_files → files_received → bat_pending → bat_ready → bat_approved → in_production → shipped → delivered
```

`blocked` peut être choisi pendant le traitement puis reprendre une étape précédente appropriée. `delivered` et `cancelled` sont terminaux. Une tentative de transition invalide est refusée côté domaine, même si une requête HTTP est modifiée.

## Routes

Toutes les routes sont sous le préfixe d’administration Sylius :

| Action | Méthode | Chemin relatif |
|---|---|---|
| Liste | GET | `/print-production/jobs` |
| Détail | GET | `/print-production/jobs/{id}` |
| Télécharger un fichier | GET | `/print-production/jobs/{id}/assets/{assetId}` |
| Déposer un BAT | POST | `/print-production/jobs/{id}/bat` |
| Changer le statut | POST | `/print-production/jobs/{id}/status` |
| Référence fournisseur | POST | `/print-production/jobs/{id}/supplier-order` |
| Expédition | POST | `/print-production/jobs/{id}/shipment` |

Le pare-feu Sylius protège toutes ces routes et chaque mutation possède un jeton CSRF.

## Recette manuelle

1. Finaliser une commande print et déposer un fichier client depuis le storefront.
2. Ouvrir **Administration → Production print** puis le dossier correspondant.
3. Contrôler le snapshot, le fournisseur et le fichier client.
4. Choisir « BAT en préparation », puis importer un PDF BAT.
5. Vérifier que le dossier passe à `bat_ready` et que le client peut valider le BAT.
6. Après validation, saisir une référence fournisseur : le statut devient `in_production`.
7. Saisir le numéro et l’URL de suivi : le statut devient `shipped`.
8. Marquer le dossier comme `delivered` avec la transition proposée.
9. Vérifier l’historique, les auteurs et les horodatages.

## Hors périmètre

- envoi automatique au fournisseur ;
- synchronisation des transporteurs ;
- préflight PDF avancé ;
- e-mails, relances SLA et commentaires client lors du refus du BAT ;
- rôles métier distincts au-delà des droits Sylius Admin existants.

## Lot 7.2 — Pilotage opérationnel

Le complément 7.2 rend la file de production plus exploitable au quotidien :

- notes internes horodatées, non visibles depuis le storefront ;
- motif obligatoire lors d’un blocage ou d’une annulation ;
- échéance de production définie par un opérateur ;
- indicateurs de dossiers en retard, bloqués et en attente de fichier ;
- filtres supplémentaires par e-mail client, période de mise à jour et situation nécessitant une attention ;
- conservation des motifs et changements d’échéance dans le journal d’activité.

Les routes ajoutées restent administratives et protégées par Sylius Admin et CSRF :

| Action | Méthode | Chemin relatif |
|---|---|---|
| Ajouter une note interne | POST | `/print-production/jobs/{id}/notes` |
| Définir une échéance | POST | `/print-production/jobs/{id}/due-date` |

Une échéance est une cible de pilotage saisie par l’équipe Yoowii. Elle ne modifie ni le prix, ni l’engagement fournisseur, ni le statut du dossier. Un dossier livré ou annulé n’est jamais signalé en retard.

## Lot 7.3 — Alertes, notifications et rôle production

- les e-mails client sont placés dans une file persistante lorsque le BAT est prêt, lors du lancement de production, de l’expédition et de la livraison ;
- la commande quotidienne de retard alerte l’équipe de production une seule fois par dossier et par jour ;
- la file conserve un identifiant unique de notification : relancer une commande ne crée pas de doublon ;
- les actions de production sensibles requièrent désormais `ROLE_PRINT_PRODUCTION` ; la consultation reste disponible aux administrateurs Sylius ;
- les e-mails ne sont jamais envoyés depuis une mutation HTTP : ils sont traités via une commande, afin qu’une panne SMTP ne bloque pas le dossier.

Configuration requise :

```dotenv
MAILER_DSN=smtp://…
YOOWII_PRINT_NOTIFICATIONS_SENDER=no-reply@yoowii.fr
YOOWII_PRINT_PRODUCTION_ALERT_RECIPIENTS=production@yoowii.fr,ops@yoowii.fr
```

Planifier ensuite :

```cron
*/5 * * * * php bin/console yoowii:print-jobs:send-notifications --env=prod
5 8 * * * php bin/console yoowii:print-jobs:alert-late --env=prod
```

Dans Sylius Admin, attribuer `ROLE_PRINT_PRODUCTION` aux utilisateurs ou groupes autorisés à déposer un BAT, modifier un statut, saisir l’échéance, enregistrer une commande fournisseur ou une expédition.
