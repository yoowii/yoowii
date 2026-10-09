# Lot 11.8 — Observabilité du cache et du throttle Realisaprint

Le chemin de devis réutilise déjà `RealisaprintVariableStateCache` : lorsque le configurateur a obtenu l'état `show_variables` de la même configuration, la validation du devis lit le cache serveur et ne relance pas le fournisseur.

Ce lot rend ce comportement mesurable dans les journaux applicatifs :

- un événement `debug` `Realisaprint show_variables state resolved.` indique `cache_outcome=hit` ou `cache_outcome=miss` ;
- le contexte contient seulement le hash de la clé et le code produit, jamais les variables fournisseur ni des choix client ;
- un événement `info` `Realisaprint request waited for a rate-limit slot.` indique l'endpoint et le temps réellement passé dans le throttle Redis.

En préproduction, filtrer ces deux messages pendant un parcours configurateur. Après le premier chargement d'une configuration, les refresh et devis suivants doivent produire `cache_outcome=hit`. Les événements d'attente doivent devenir rares grâce au debounce, au cache navigateur et au verrou distribué.
