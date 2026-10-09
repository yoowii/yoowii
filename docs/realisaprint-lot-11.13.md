# Lot 11.13 — Préchauffage lors de la génération de l’état initial

La génération d’un état initial Realisaprint pour une publication vérifie déjà `show_variables` avec la configuration d’affichage du catalogue. Ce même résultat normalisé préchauffe maintenant `RealisaprintVariableStateCache`.

Le premier chargement correspondant peut donc être servi par Redis sans un second appel fournisseur. Le préchauffage utilise la clé canonique habituelle : produit, stock, variables, version de mapping et version de schéma.

Il est non destructif : si un worker a déjà stocké un état pour cette clé, la valeur existante est conservée. Les diagnostics bruts restent uniquement dans l’état initial de publication et ne sont pas mis dans le cache storefront.
