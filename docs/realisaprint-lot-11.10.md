# Lot 11.10 — Cache séparé de configuration et de prix

Le tunnel de devis Realisaprint utilise désormais deux caches complémentaires :

- `save_configuration` : le code fournisseur est conservé 30 jours, par fingerprint canonique et version de mapping ;
- `get_price` : le prix reste conservé selon `YOOWII_REALISAPRINT_QUOTE_CACHE_TTL` (15 minutes actuellement), par configuration, devise, quantité et pays.

Quand le prix expire mais que la configuration est inchangée, Yoowii relance uniquement `get_price` avec le code déjà connu. Il ne rappelle pas `save_configuration` et évite donc un appel fournisseur inutile.

Les validations de mapping en back-office gardent volontairement leurs appels directs et leurs diagnostics complets : elles ne réutilisent aucun cache de production.

Le snapshot de devis enregistre les deux TTL afin de faciliter le diagnostic d’un prix affiché.
