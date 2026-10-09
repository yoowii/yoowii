# Lot 11.9 — Délais HTTP bornés pour Realisaprint

Les appels API Realisaprint sont désormais explicitement limités :

- `timeout=10` secondes : durée maximale sans activité réseau ;
- `max_duration=30` secondes : durée totale maximale de la requête, réponse comprise.

Ces limites évitent qu'une API lente immobilise un worker PHP et le verrou Redis de `show_variables`. Elles restent inférieures à la durée de vie de 180 secondes du verrou distribué, qui couvre aussi l'attente éventuelle du throttle global.

Les valeurs sont centralisées dans `config/packages/yoowii_realisaprint.yaml`. Le client refuse une configuration incohérente (valeurs non positives ou durée totale inférieure au délai d'inactivité).

Un timeout remonte comme erreur de transport ; le calcul de devis conserve alors son fallback vers les matrices tarifaires existant.
