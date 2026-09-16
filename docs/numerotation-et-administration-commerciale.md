# Numérotation et future administration commerciale

Décision du 16 septembre 2026. Le nom de la rubrique d’administration reste à définir.

## Devis : règle implémentée

- Format `DAANNNN`, par exemple `D260003` : préfixe D et six chiffres, sept caractères au total.
- AA correspond à l’année de création dans le fuseau de l’application ; modifier la date du devis ne renumérote pas le document.
- Compteur annuel propre à chaque organisation, de 0001 à 9999, remis à 0001 au changement d’année.
- Allocation sérialisée par verrou de base de données ; compteur conservé séparément des devis pour ne pas réutiliser les numéros supprimés. Des trous sont possibles si une création échoue après réservation.
- Blocage explicite à 9999, sans allongement silencieux du format.
- Les références existantes sont conservées. Les références importées qui correspondent au format sont prises en compte pour poursuivre au-delà du plus grand numéro.
- Dans le formulaire, la référence est attribuée automatiquement et n’est pas modifiable.
- La migration `2026_09_16_000000_create_quote_number_sequences` et les
  caches ont été appliqués en production le 16 septembre 2026.
- Le téléchargement d’un devis existant a été vérifié en production. La
  prochaine création réelle doit confirmer l’attribution de la référence
  automatique ; aucun devis fictif ne doit être créé à cette fin.

## Administration à implémenter ultérieurement

Prévoir un paramétrage par organisation pour les devis et les autres objets numérotés : préfixe, nombre de chiffres, périodicité du compteur, prochain numéro, aperçu du résultat et droits de modification. Toute modification devra préserver les références déjà attribuées, empêcher les doublons et être journalisée.

Pour les dossiers, la proposition validée est un compteur continu sur six chiffres (`000001`), propre à chaque organisation, sans remise à zéro annuelle. Cette règle n’est pas implémentée dans ce lot ; préciser les types de dossiers concernés avant généralisation.

Regrouper ou relier dans cette future administration les coordonnées de l’émetteur, mentions légales, conditions de règlement, note fiscale, durée de validité et lien vers les CGV. Prévoir un aperçu complet du document et un signalement des champs manquants ; le nom définitif de la rubrique reste ouvert.

## Audit du PDF de devis

Le PDF reprend la référence, l’intitulé, les dates, l’introduction, les coordonnées émetteur/destinataire, les lignes ordonnées (type, description multiligne, prix, quantité, total), la remise, les totaux et la devise, la note fiscale, le lien CGV, les conditions de règlement, les mentions légales et le bloc d’accord/signature. Téléphone et site de l’émetteur sont inclus lorsqu’ils sont renseignés. Les coordonnées figées à l’envoi restent prioritaires.

Les champs facultatifs non remplis ne sont pas inventés. La validité, les conditions fiscales et les conditions de règlement sont signalées comme à préciser lorsqu’elles sont absentes. Les champs obligatoires de l’émetteur et la présence d’un destinataire restent contrôlés avant export. Les notes internes ne sont jamais imprimées.

Le téléchargement d’un devis existant a été confirmé en production le
16 septembre 2026. Cette vérification confirme l’accès au document après
activation ; elle ne remplace pas le contrôle métier du prochain nouveau devis
numéroté.

Limites du modèle actuel : pas de calcul de TVA détaillée par ligne, pas de coordonnées bancaires structurées, pas de snapshot du lien CGV. Ces évolutions relèvent du futur paramétrage documentaire ; la correction ne prétend pas les implémenter ni constituer un audit juridique.
