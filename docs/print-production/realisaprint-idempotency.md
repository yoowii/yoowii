# Idempotence des transmissions Realisaprint

La base de données est la source de vérité des transmissions : Redis ne sert qu'au
throttling HTTP. Avant `create_order`, Yoowii réserve une tentative unique par
`PrintJob` dans une transaction courte. L'appel HTTP est toujours fait hors
transaction SQL.

Les états sont `pending`, `submitting`, `submitted`, `pending_confirmation`,
`failed` et `simulated`. Un timeout, une réponse malformée ou l'expiration d'un
lease `submitting` devient `pending_confirmation`. Cet état bloque toute
nouvelle transmission : l'API Realisaprint disponible dans ce projet ne fournit
pas de recherche fiable par référence/idempotency key, donc la réconciliation
est manuelle. Les anciens `failed` sont migrés vers `pending_confirmation`.

Les ajouts Prescript utilisent une opération persistée, indexée par le hash de
la clé d'idempotence. Elle conserve le panier, l'empreinte canonique de la
demande, la version de mapping, le code de configuration fournisseur et la
ligne Sylius. Un rejeu ne réussit que si cette ligne existe toujours dans le
bon panier ; sinon il renvoie un conflit sans recréer de ligne.

Une lease Prescript expire en `pending_confirmation` lorsqu'aucun code
fournisseur n'a été persisté : Realisaprint ne documente pas de recherche
fiable par référence ou de clé d'idempotence pour `save_configuration`. Cette
fenêtre ne peut donc pas être reprise automatiquement. En revanche, une lease
expirée après persistance du code peut reprendre l'insertion locale sans
nouvel appel `save_configuration`. La migration qui met en quarantaine les
anciens échecs fournisseur est volontairement irréversible ; la restauration
exige un backup antérieur et une réconciliation manuelle.
