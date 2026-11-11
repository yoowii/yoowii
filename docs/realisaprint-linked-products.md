# Produits Yoowii liés à Realisaprint

## Parcours courant

1. Synchroniser le catalogue et charger la configuration de la référence fournisseur.
2. Créer un produit Yoowii avec son code, son nom et le stock choisi. Le brouillon, la définition du configurateur, la route et le mapping initial sont créés ensemble, désactivés.
3. Depuis la fiche de référence, ouvrir « Vérifier la configuration et le prix ». Cette action teste la couverture et une cotation API avec les valeurs par défaut, puis permet la publication si le résultat est valide.
4. « Corriger les correspondances » est un outil avancé pour une incohérence constatée. Il part du mapping précédent et conserve les codes Yoowii, sans créer de nouveaux slugs depuis les libellés fournisseur.

Les identifiants et codes fournisseur sont conservés dans le mapping pour les cotations. Les options Yoowii définissent le formulaire. Les versions de mapping restent internes à la traçabilité des commandes.

## Synchronisation surveillée

La commande `bin/console yoowii:realisaprint:linked:sync` actualise seulement les configurations des références reliées à un produit Yoowii. Elle espace les appels `configurations` pour respecter la limite Realisaprint. Elle ne modifie ni le mapping publié, ni le formulaire, ni une cotation déjà enregistrée. En cas de code fournisseur disparu ou de stock invalide, elle retourne un code d'échec et indique la référence concernée. La fiche catalogue affiche également « Intervention nécessaire ».

Programmer cette commande dans le planificateur existant (par exemple chaque nuit) et surveiller son code de sortie. Pour les nouvelles références, utiliser d'abord `yoowii:realisaprint:catalog:sync`.

Une nouvelle option fournisseur ou une nouvelle valeur n'est pas ajoutée automatiquement à un produit publié : la version actuelle de la définition est immuable. Ce changement nécessite une évolution contrôlée du configurateur. La cotation API utilise toujours les prix courants du fournisseur pour les configurations déjà prises en charge.

## Réviser un produit publié

Depuis la fiche de référence, le bouton « Créer une révision » duplique la définition et le mapping publiés dans une version inactive. La version actuellement publiée continue d’être proposée aux clients. Modifier ensuite la définition liée, relancer le contrôle de couverture et de prix, puis publier la révision. La publication désactive l’ancienne définition et l’ancien mapping au même moment ; les versions restent conservées pour la traçabilité.

## Points de recette

- Agenda, stock 837 : le mapping initial est présent dès la création du brouillon.
- Les zones inactives utilisent le code canonique `sans`, et la quantité libre ne demande pas de table de correspondance.
- Vérifier la configuration et le prix, puis publier. Le configurateur doit accepter la quantité libre.
- Resynchroniser après suppression simulée d'une valeur fournisseur : la fiche signale une intervention et la publication est refusée.
- En cas de nouveau mapping publié, une seule version est active pour la référence et le produit.

## Corriger un brouillon déjà généré

Depuis « Produits Yoowii liés », ouvrir « Prévisualiser / corriger le brouillon ». La page compare les champs texte du produit au catalogue synchronisé : une variable en lecture seule reprend sa valeur fournisseur exacte, tandis qu'un texte libre sans défaut demande une valeur d'essai pour la cotation API. Le produit Sylius et son code restent les mêmes ; la définition du brouillon est mise à jour et un nouveau mapping inactif est créé. Refaire ensuite le contrôle du prix avant publication.

Cette opération concerne la dernière révision inactive. Pour un produit actif, créer d'abord une révision de définition versionnée.

## Rétention des versions

La commande quotidienne `bin/console yoowii:sourcing:mapping-retention` archive les versions publiées puis désactivées. Elle supprime seulement les brouillons jamais publiés, inactifs, sans commande liée et âgés de plus de 90 jours. Les validations Realisaprint de ces brouillons sont supprimées par la contrainte de base de données `ON DELETE CASCADE`. Utiliser d'abord `--dry-run` pour contrôler le résultat.
