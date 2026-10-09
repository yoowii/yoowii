# Lot 11.15 — Rafraîchissement détaché des états d’affichage

La commande suivante reconstruit l’état `show_variables` des mappings Realisaprint publiés, actualise `initial_display_state` et réchauffe le cache partagé :

```bash
php bin/console yoowii:realisaprint:display-state:warm
```

Elle est conçue pour une exécution planifiée, par exemple toutes les 12 heures après le déploiement :

```cron
15 */12 * * * cd /var/www/vhosts/example/httpdocs && php bin/console yoowii:realisaprint:display-state:warm --env=prod --no-interaction
```

Le traitement ne manipule que la configuration d’affichage déterministe d’un produit publié (valeurs par défaut, minimums et valeurs fixes du mapping). Il ne sérialise ni valeurs de configurateur navigateur ni champs texte client dans Messenger : les données de devis restent donc hors de la file asynchrone.

Une erreur fournisseur sur un produit ne bloque pas les autres produits. La commande termine en échec pour permettre à la supervision de détecter le produit concerné ; l’état précédent reste conservé.
