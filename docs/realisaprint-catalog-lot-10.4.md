# Catalogue Realisaprint — lot 10.4

Le catalogue est une copie locale **en lecture seule** de l'API Realisaprint. Il ne crée aucune commande fournisseur, ne transmet aucun fichier et n'active aucun produit storefront.

## Synchroniser les produits

Depuis `Administration > Sourcing print > Catalogue Realisaprint`, utiliser **Synchroniser les produits**, ou lancer :

```bash
docker compose run --rm php bin/console yoowii:realisaprint:catalog:sync
```

Une entrée absente de la réponse fournisseur est archivées localement, jamais supprimée : les anciens mappings et snapshots restent donc lisibles.

## Lire une configuration

Ouvrir un produit puis utiliser **Charger / actualiser la configuration**. Cette action appelle uniquement `configurations` pour le produit choisi et stocke la réponse sans secret. Elle est volontairement manuelle afin de respecter la limite Realisaprint d'un appel identique toutes les 15 secondes.

La prochaine étape de l'assistant exploite ces données pour créer un brouillon Sylius, proposer les correspondances d'options et publier une version de mapping après validation de la couverture et du prix.
