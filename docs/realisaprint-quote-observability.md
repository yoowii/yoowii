# Guide d’utilisation, de test et d’observabilité de la cotation Realisaprint

Ce guide couvre la **cotation temps réel** des produits print. La transmission de commande, le dépôt FTP et la synchronisation de production sont documentés dans [Transmission et suivi Realisaprint](print-production/realisaprint-lot-10.1.md).

## Fonctionnement

Lorsqu’une route de sourcing Realisaprint est éligible, Yoowii transforme la configuration canonique du produit, appelle `save_configuration` puis `get_price`, et applique ensuite la politique de marge côté serveur. Aucun montant envoyé par le navigateur n’est utilisé.

Une configuration déjà cotée est mise en cache. Si l’API ne peut pas être utilisée ou si elle échoue, Yoowii tente la cotation par matrice : le parcours client reste donc disponible à condition qu’une matrice de secours existe.

Le connecteur protège également la limite fournisseur : deux appels à la même fonction API ne peuvent pas être lancés localement à moins de 15 secondes d’intervalle. Le TTL du cache doit donc être au moins de 15 secondes.

## Préparer l’intégration

1. Conserver les identifiants Realisaprint uniquement dans les variables d’environnement du serveur (jamais dans Git, un mapping ou un snapshot).
2. Activer l’intégration et la cotation dans l’environnement de test :

   ```dotenv
   YOOWII_REALISAPRINT_ENABLED=1
   YOOWII_REALISAPRINT_QUOTE_ENABLED=1
   YOOWII_REALISAPRINT_QUOTE_CACHE_TTL=900
   YOOWII_REALISAPRINT_SHOP_ID=<identifiant fourni par Realisaprint>
   YOOWII_REALISAPRINT_API_KEY=<secret fourni par Realisaprint>
   ```

3. Vérifier l’accès en lecture seule, puis consulter le catalogue et la configuration d’un produit. Attendre 15 secondes avant de relancer exactement la même commande si le limiteur local la refuse.

   ```bash
   docker compose run --rm php bin/console yoowii:realisaprint:check
   docker compose run --rm php bin/console yoowii:realisaprint:catalog
   docker compose run --rm php bin/console yoowii:realisaprint:catalog <id-produit>
   ```

4. Dans **Administration → Production print → Sourcing**, activer le fournisseur `realisaprint`, choisir le mode `api` ou `hybrid`, et lui attribuer la capacité `realtime_quote`.
5. Créer et activer une version de mapping pour la référence fournisseur. Le bloc `realisaprint` doit contenir `product`, `stock` et `variables`. Les clés de `values` sont les valeurs canoniques Yoowii ; elles sont converties en valeurs attendues par l’API.

   ```json
   {
     "realisaprint": {
       "product": "243",
       "stock": "988",
       "variables": {
         "VARTICLE_FORMAT_": {
           "option": "format",
           "values": {"a5": "2", "a6": "3"}
         },
         "VARTICLE_QUANTITY_": {
           "option": "quantity",
           "values": {"100": "100", "250": "250"}
         }
       }
     }
   }
   ```

   Pour une variable qui ne nécessite pas de conversion, utiliser `catalog_values`, par exemple `"catalog_values": [100, 250, 500]`.

La cotation Realisaprint est disponible uniquement en EUR. Un fournisseur sans capacité, un mapping absent/incompatible, une intégration désactivée ou un échec fournisseur déclenchent le repli matrice.

## Recette manuelle de cotation

Utiliser un code produit et un objet d’options valides pour le configurateur. La commande suivante ne divulgue pas les identifiants :

```bash
docker compose run --rm php bin/console yoowii:realisaprint:quote:diagnose PRINT_FLYER --options='{"format":"a5","quantity":100}'
```

Remplacer l’exemple par toutes les options obligatoires du produit. La sortie donne notamment `source`, le fournisseur retenu, le prix en EUR, `correlation_id` et `fallback_reason`.

Pour vérifier la validité de la configuration sans toucher à Realisaprint :

```bash
docker compose run --rm php bin/console yoowii:realisaprint:quote:diagnose PRINT_FLYER --options='{"format":"a5","quantity":100}' --no-network
```

Le résultat `source: matrix_fallback` et `fallback_reason: quote_disabled` est alors volontaire : cette option valide seulement le produit et les options, sans vérifier le mapping ni appeler l’API.

Pour une validation de bout en bout, effectuer la même configuration dans le configurateur storefront, vérifier le prix affiché, puis créer le dossier de production. Dans **Administration → Production print**, la carte **Source de cotation** expose le résultat conservé avec le snapshot tarifaire.

## Plan de test d’acceptation

| Scénario | Action | Résultat attendu |
| --- | --- | --- |
| API nominale | Coter une configuration compatible avec cache vide. | `source: realisaprint_api`; le snapshot contient le fournisseur, le produit, la version de mapping et le code de configuration fournisseur. |
| Cache | Refaire exactement la même cotation avant expiration du TTL. | `source: realisaprint_cache`; même prix de base et aucune nouvelle séquence de cotation fournisseur. |
| Cotation désactivée | Passer `YOOWII_REALISAPRINT_QUOTE_ENABLED` à `0`, avec une matrice disponible. | `source: matrix_fallback`, `fallback_reason: quote_disabled`; un prix matrice reste retourné. |
| Intégration désactivée | Passer `YOOWII_REALISAPRINT_ENABLED` à `0`, avec une matrice disponible. | `fallback_reason: realisaprint_disabled`. |
| Mapping invalide | Désactiver le mapping actif ou envoyer une option absente de `values`. | `fallback_reason: mapping_missing` ou `mapping_incompatible`; matrice de secours retenue. |
| Incident fournisseur | Provoquer un refus, un délai dépassé ou attendre le limiteur local, avec une matrice disponible. | Repli avec respectivement une raison `api_rejected_configuration`, `api_timeout` ou `rate_limited` (selon le cas). |
| Absence de secours | Rejouer un cas de repli sans matrice sélectionnable. | L’échec de cotation est explicite : ne pas publier un prix estimé ni réutiliser un prix navigateur. |

Restaurer les variables et le mapping après chaque scénario. Ne pas tester `save_configuration` / `get_price` en boucle : le fournisseur impose une limite par fonction et par IP, et le limiteur local complète cette protection.

## Observabilité

Chaque nouveau snapshot tarifaire peut contenir `quote_trace`. Son absence sur un snapshot historique est normale et rétrocompatible : la colonne `pricing_snapshot` est déjà un JSON et aucune migration n’est requise.

| Champ | Usage |
| --- | --- |
| `source` | `realisaprint_api`, `realisaprint_cache` ou `matrix_fallback`. |
| `supplier_code` / `supplier_product_code` | Route effectivement retenue. |
| `mapping_version` | Version de mapping utilisée pour une cotation API ; absente pour un repli matrice. |
| `provider_configuration_code` | Identifiant de configuration renvoyé par Realisaprint, utile au support fournisseur. |
| `correlation_id` | Identifiant à transmettre à l’équipe technique pour relier le snapshot au journal de repli. |
| `fallback_reason` | Motif normalisé du passage à la matrice ; absent pour une cotation API ou cache. |
| `technical_detail` | Détail technique bref, réservé aux utilisateurs ayant le rôle Production print. |

Les motifs de repli possibles sont : `realisaprint_disabled`, `quote_disabled`, `supplier_not_eligible`, `mapping_missing`, `mapping_incompatible`, `rate_limited`, `api_timeout`, `api_transport_error`, `api_response_invalid`, `api_rejected_configuration`, `api_price_missing` et `matrix_used`.

La carte **Source de cotation** du dossier de production affiche la source, la route, la version de mapping, l’horodatage, et — pour les utilisateurs autorisés — l’identifiant de corrélation et le détail technique. Le snapshot complet reste également visible dans la même page pour les investigations internes.

Lors d’un repli, l’application écrit un avertissement structuré `Print quote fell back to matrix pricing.` avec `correlation_id`, `source`, `fallback_reason`, `supplier_code`, `supplier_product_code` et `technical_detail`. Les cotations API nominales et servies du cache sont à contrôler via le `quote_trace` du snapshot ou la commande de diagnostic ; elles n’émettent pas ce journal de repli.

Ne jamais enregistrer ou partager les secrets, en-têtes d’autorisation, payloads bruts, réponses brutes de l’API ou fichiers client. Le traceur refuse déjà les détails contenant `api_key` ou `password` et limite le détail technique à 280 caractères, mais cette protection ne remplace pas une revue attentive des journaux.
