# Publication rapide Préscript

La publication rapide est un parcours additif, activé seulement par
`YOOWII_REALISAPRINT_PRESCRIPT_QUICK_PUBLICATION_ENABLED=1`. Sa valeur par défaut
est `0`.

Un mapping Préscript doit choisir explicitement `configurator: prescript` et ne
requiert pas de mapping `variables`. La publication contrôle la référence
fournisseur, le stock Préscript du catalogue synchronisé, la route et l'absence
d'un autre mapping ou d'une autre définition active pour le produit Yoowii.
Elle n'appelle pas `show_variables`, `save_configuration` ni `get_price`.

Le configurateur `classic` conserve intégralement sa validation et sa publication
existantes. Préscript n'est jamais sélectionné comme solution de repli.

La cotation Préscript reste serveur et appelle `get_price` lors de la création
d'un devis. Le snapshot de prix et le panier Sylius restent donc inchangés.

## Précondition de sécurité

Le flag ne doit pas être activé en production tant que le HTML fournisseur rendu
avec `|raw` n'est pas isolé. Le lot de sécurisation indépendant doit placer le
configurateur dans un iframe sandboxé sur une origine dédiée, appliquer une CSP
restrictive, valider les `postMessage`, et remplacer l'URL callback stable par
un jeton court, à usage unique, lié à la session et au mapping.
