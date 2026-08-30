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
