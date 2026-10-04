# Yoowii Realisaprint — parcours produit lié

Archive cumulative construite à partir de `feat/bootstrap-sylius-commerce` au commit `b48279e947a960e9a4a2c0f1f0f2165238cd6176`.
Elle contient les fichiers finaux du parcours produit lié, du contrôle de couverture et de la réparation des options texte sur les brouillons existants.

## Installation

Depuis la racine du dépôt Yoowii, contrôler les fichiers existants avant de les remplacer, puis extraire :

```bash
tar -tzf yoowii-realisaprint-draft-repair.tar.gz
tar -xzf yoowii-realisaprint-draft-repair.tar.gz -C /chemin/vers/yoowii
php bin/console lint:twig templates/admin/sourcing
php bin/phpunit tests/Yoowii/Sourcing/Application/RealisaprintValidationPreviewTest.php
```

Cette archive cumulative remplace les fichiers modifiés dans le dépôt ; elle ne nécessite pas d'appliquer les anciens patchs. Aucune migration de base de données n'est incluse.

## Vérification manuelle

Créer un brouillon depuis un stock Realisaprint, lancer « Vérifier la configuration et le prix », lire la couverture par option et le coût de la configuration d'essai, puis publier si le résultat est valide. Vérifier enfin le configurateur et une cotation storefront. La cotation d'essai ne couvre pas toutes les combinaisons.

Pour un brouillon existant en échec, ouvrir « Prévisualiser / corriger le brouillon », vérifier les changements, renseigner la valeur d'essai d'un texte libre si nécessaire, appliquer, puis relancer la validation du nouveau mapping. Le produit reste désactivé pendant l'opération.

La commande `php bin/console yoowii:realisaprint:linked:sync` actualise les configurations des références liées ; la planifier séparément si souhaité.
