# Référence de développement - API Realisaprint v2.23

Source : `docs/api_realisaprint_com_v2_23.pdf` (documentation fournie, datée du 09/12/2024).

## Contrat de transport et sécurité

- URL de base : `https://www.realisaprint.com/api`
- API classique : requêtes `POST` exclusivement, avec un corps `application/x-www-form-urlencoded`.
- Authentification obligatoire sur chaque requête classique : `shop_id` (entier) et `api_key` (secret).
- Ne jamais exposer `api_key` au navigateur, dans une URL, une trace, un message d'erreur ou un cURL de diagnostic. Conserver les deux identifiants dans des variables d'environnement serveur.
- Limite documentée : un appel toutes les 15 secondes, par IP et par fonction. Une file, un verrou ou un cache par opération est donc nécessaire.
- Une réponse métier en erreur contient généralement `error`; elle peut aussi contenir `variable_alerts`. Une réponse HTTP 200 ne garantit pas le succès métier.

Variables recommandées :

```dotenv
YOOWII_REALISAPRINT_BASE_URL=https://www.realisaprint.com/api
YOOWII_REALISAPRINT_SHOP_ID=123
YOOWII_REALISAPRINT_API_KEY=change-me
```

## Endpoints

Tous les endpoints suivants héritent de `shop_id` et `api_key`, obligatoires sauf indication contraire.

| Endpoint | Méthode | Paramètres spécifiques obligatoires | Usage |
| --- | --- | --- | --- |
| `/products` | POST | Aucun | Catalogue des produits accessibles. |
| `/configurations` | POST | `product` (INT) | Stocks et schéma des variables d’un produit. |
| `/show_variables` | POST | `product` (INT), `stock` (INT), `variables` (ARRAY) | Visibilité, valeurs disponibles et valeurs courantes selon la configuration. |
| `/save_configuration` | POST | `product` (INT), `stock` (INT), `variables` (ARRAY) | Crée ou retrouve un `code` de liaison. |
| `/get_price` | POST | `code` (INT), `quantity` (INT) | Prix HT et options pour un code de liaison. `country` (STRING) est facultatif, `FR` par défaut. |
| `/config_details` | POST | `code` (INT) | Détails, fichiers attendus et gabarit associés au code. |
| `/create_order` | POST | `code`, `quantity`, `reference`, adresse de livraison | Crée une commande. Accès à demander à Realisaprint. |
| `/get_order` | POST | `id_order` **ou** `ref_order` | Consulte une commande. Pour une commande hors API : `id_order`, `search_all=true` et `line`. |
| `/get_iso_countries` | POST | Aucun | Pays et codes ISO de livraison. |
| `/products_for_prescript` | POST | Aucun | Produits/stocks disponibles pour l'intégration Préscript. |
| `/get_prescript` | GET | `shop_id`, `api_key` ou `api_key_encoded`, `product`, `stock` | Template Préscript. En iFrame, `iframe` et `api_key_encoded` MD5 sont requis. |

Les échanges de fichiers et les retours de statut FTP sont décrits dans la documentation, mais ne sont pas des endpoints HTTP de cette API.

## Schéma de configuration

`/configurations` renvoie :

- `stocks` : `{ idStock: libellé }` ;
- `variables` : `{ idVariable: définition }`.

Une définition de variable contient notamment :

| Champ | Rôle |
| --- | --- |
| `name` | Libellé affichable. |
| `type` | `checkbox`, `float`, `text`, `select` ou `session`. |
| `default` | Valeur par défaut fournisseur. |
| `values` | Valeurs autorisées pour `checkbox` et `select`; `false` sinon. |
| `readonly` | Variable non éditable si `true`. |
| `quantity` | Indique la variable de quantité. |
| `production_time` | Indique la variable de délai de fabrication. |
| `area`, `position` | Zone et ordre d’affichage. |

Le tableau `variables` envoyé aux endpoints porte les identifiants fournisseur en clé, par exemple :

```text
variables[VARTICLE_22442_]=3
variables[VARTICLE_22419_]=8
```

Ne jamais envoyer les libellés utilisateur (`A4 21 x 29,7cm`, par exemple) : envoyer la clé fournisseur (`3`).

## Cycle de cotation recommandé

1. Appeler `/products`, puis `/configurations` et persister un mapping versionné vers les codes canoniques Yoowii.
2. À chaque changement de configuration, appeler `/show_variables`.
3. Appliquer les booléens racine `VARTICLE_*` : `true` affiche l’option, `false` la masque.
4. Utiliser les **clés** de `variable_values[VARTICLE_*]` comme valeurs disponibles. Les valeurs de ce tableau sont des libellés, pas des indicateurs booléens.
5. Lire la sélection réelle dans `current_values` (la table du PDF l'appelle `current_variables`, mais son exemple JSON utilise `current_values`; accepter les deux pour compatibilité).
6. Si `invalid_variables` n’est pas vide, corriger chaque valeur avec la première valeur valide, ou appeler `/show_variables` avec `retry=1` : Realisaprint effectue alors au plus cinq tentatives récursives et renvoie `tries` et `updated_variables`.
7. Appeler `/save_configuration`, conserver le `code` obtenu, puis `/get_price` avec la quantité et le pays.
8. Juste avant la création de commande, réenregistrer la configuration : la documentation indique que les codes de liaison peuvent évoluer.

Cas particulier : la variable de délai (`production_time`) ne figure jamais dans `/show_variables`; ses valeurs proviennent de `/configurations`.

## Exemple complet - configurer puis calculer un prix

Les identifiants et valeurs ci-dessous sont des exemples. Les IDs de variables doivent provenir de `/configurations` pour le produit et le stock effectivement utilisés.

### 1. Vérifier les champs et valeurs disponibles

```bash
curl --fail-with-body --silent --show-error \
  --request POST "${YOOWII_REALISAPRINT_BASE_URL}/show_variables" \
  --data-urlencode "shop_id=${YOOWII_REALISAPRINT_SHOP_ID}" \
  --data-urlencode "api_key=${YOOWII_REALISAPRINT_API_KEY}" \
  --data-urlencode 'product=247' \
  --data-urlencode 'stock=1234' \
  --data-urlencode 'variables[VARTICLE_22442_]=3' \
  --data-urlencode 'variables[VARTICLE_22419_]=8' \
  --data-urlencode 'variables[VARTICLE_22427_]=4' \
  --data-urlencode 'variables[VARTICLE_22439_]=3' \
  --data-urlencode 'retry=1'
```

Exemple de lecture de réponse :

```json
{
  "VARTICLE_22442_": true,
  "variable_values": {
    "VARTICLE_22442_": {
      "2": "A5 14,8 x 21cm",
      "3": "A4 21 x 29,7cm"
    }
  },
  "current_values": {
    "VARTICLE_22442_": "3"
  },
  "invalid_variables": []
}
```

### 2. Enregistrer la configuration

```bash
curl --fail-with-body --silent --show-error \
  --request POST "${YOOWII_REALISAPRINT_BASE_URL}/save_configuration" \
  --data-urlencode "shop_id=${YOOWII_REALISAPRINT_SHOP_ID}" \
  --data-urlencode "api_key=${YOOWII_REALISAPRINT_API_KEY}" \
  --data-urlencode 'product=247' \
  --data-urlencode 'stock=1234' \
  --data-urlencode 'variables[VARTICLE_22442_]=3' \
  --data-urlencode 'variables[VARTICLE_22419_]=8' \
  --data-urlencode 'variables[VARTICLE_22427_]=4' \
  --data-urlencode 'variables[VARTICLE_22439_]=3'
```

Conserver `code` de la réponse JSON :

```json
{ "code": "11667", "is_new": true, "has_files": true }
```

### 3. Demander le prix

```bash
curl --fail-with-body --silent --show-error \
  --request POST "${YOOWII_REALISAPRINT_BASE_URL}/get_price" \
  --data-urlencode "shop_id=${YOOWII_REALISAPRINT_SHOP_ID}" \
  --data-urlencode "api_key=${YOOWII_REALISAPRINT_API_KEY}" \
  --data-urlencode 'code=11667' \
  --data-urlencode 'quantity=100' \
  --data-urlencode 'country=FR'
```

Le prix HT total fournisseur est : `price + somme(options[*].price)`. Vérifier `error` avant toute conversion monétaire; stocker les montants en centimes dans Yoowii.

## Exemple PHP Symfony réutilisable

```php
/** @return array<string, mixed> */
function realisaprintPost(HttpClientInterface $http, string $operation, array $parameters): array
{
    $response = $http->request('POST', rtrim($_ENV['YOOWII_REALISAPRINT_BASE_URL'], '/') . '/' . $operation, [
        'body' => [
            'shop_id' => $_ENV['YOOWII_REALISAPRINT_SHOP_ID'],
            'api_key' => $_ENV['YOOWII_REALISAPRINT_API_KEY'],
        ] + $parameters,
    ]);

    $payload = $response->toArray(false);
    if (isset($payload['error'])) {
        throw new \DomainException((string) $payload['error']);
    }

    return $payload;
}

$saved = realisaprintPost($http, 'save_configuration', [
    'product' => 247,
    'stock' => 1234,
    'variables' => [
        'VARTICLE_22442_' => '3',
        'VARTICLE_22419_' => '8',
        'VARTICLE_22427_' => '4',
        'VARTICLE_22439_' => '3',
    ],
]);

$price = realisaprintPost($http, 'get_price', [
    'code' => (string) $saved['code'],
    'quantity' => 100,
    'country' => 'FR',
]);
```

## Règles Yoowii

- Le navigateur ne choisit jamais un prix : le serveur enregistre la configuration, demande le prix, applique la politique de marge, puis stocke un snapshot immuable sur la ligne de commande.
- Les valeurs frontend sont des codes canoniques Yoowii. La conversion en IDs/valeurs `VARTICLE_*` est faite côté serveur par un mapping versionné.
- Les appels externes doivent être idempotents, limités, observables sans secrets et exécutés derrière une interface/handler Messenger lorsque le flux n’est pas synchrone.
- Ne pas exposer au client les IDs fournisseur, l’API key, le détail technique des réponses, ni les erreurs brutes.
