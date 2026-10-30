# Facturation et Super PDP

> État de référence — 30 octobre 2026. Cette section prévaut sur les notes
> historiques qui suivent.

## Pilote Ivo Correia : état actuel

Le connecteur **Super PDP production** est configuré par organisation avec des
identifiants serveur chiffrés. Une facture émise est immuable, reçoit son
numéro définitif, son PDF privé et une ligne de transmission réglementaire.

Le parcours disponible est volontairement contrôlé :

1. émettre une **vraie** facture (ne jamais créer de facture de test, son
   numéro est définitif) ;
2. utiliser **Valider le document électronique** : Cremona produit les données
   EN 16931, demande à Super PDP un CII, puis enregistre le CII et le rapport
   de validation en documents privés ; cette étape n'envoie rien ;
3. relire la facture, le client et le journal ;
4. seulement si la validation est positive, confirmer **Transmettre à Super
   PDP** ;
5. utiliser **Actualiser les statuts Super PDP** pour relever les événements
   de traitement.

L'envoi reste toujours manuel et confirmé. L'identifiant externe est stable
pour éviter les doublons lors d'une nouvelle tentative. Les statuts affichés
incluent notamment : document validé, remise à Super PDP, envoyée et rejetée.

### Préparation par Ivo avant la première facture réelle

- Vérifier dans le profil légal de l'organisation : raison sociale, SIRET,
  adresse, e-mail, numéro de TVA si assujetti, et instructions de règlement.
- Pour un **client professionnel français**, compléter sa fiche entreprise :
  raison sociale, adresse complète, SIREN ou SIRET et, si disponible, numéro
  de TVA intracommunautaire. Cremona bloque l'envoi B2B sans SIREN/SIRET.
- Pour un **particulier**, renseigner son nom et son adresse complète.
- Créer ensuite une facture correspondant à une opération réelle et la faire
  valider dans Cremona avant toute transmission.

### Prochains développements, hors pilote immédiat

1. Planifier la synchronisation automatique des événements Super PDP, tout en
   conservant l'action manuelle de contrôle.
2. Ajouter l'e-reporting des transactions et paiements selon le régime de TVA.
3. Générer un Factur-X lisible en complément du CII si le besoin de diffusion
   PDF hybride est confirmé.
4. Compléter le cycle des avoirs et factures rectificatives avec leur
   transmission réglementaire et leurs références de facture d'origine.

## Ce qui est disponible

Le module **Facturation** est transverse : il se choisit explicitement dans la configuration de chaque organisation, quel que soit son métier. Il permet de créer un brouillon, de composer ses lignes HT et TVA, puis d’émettre une facture.

À l’émission, Cremona attribue un numéro définitif, fige les coordonnées légales de l’émetteur et du client, calcule les totaux, génère un PDF privé et enregistre les règlements sans réouvrir la facture. Une correction doit passer par un avoir.

Chaque PDF doit devenir un document privé enregistré directement sur Object
Storage Scaleway, accessible seulement aux personnes autorisées dans
l’organisation. Le stockage Scaleway est configuré ; le disque applicatif et
la migration contrôlée des documents existants font partie du P0 documentaire.

## Configuration par organisation

Dans **Administration de l’organisation → Réglages de facturation**, l’administrateur définit notamment :

- le préfixe et le prochain numéro de facture ;
- la devise, le taux de TVA proposé et le régime de TVA ;
- les échéances, instructions de paiement et mentions de retard ;
- si la facturation de location est manuelle ou désactivée ;
- le canal réglementaire (Super PDP par défaut) ;
- une adresse de copie Pennylane facultative pour le comptable.

Ces réglages ne sont pas propres au métier de lutherie. Ils constituent le socle réutilisable d’une autre organisation et d’un autre métier.

## Super PDP et Pennylane

Super PDP est le canal réglementaire unique prévu par défaut. Lorsqu’une facture est émise, Cremona crée une ligne dans le journal de transmission :

- **En attente de configuration** tant que le compte Super PDP n’est pas connecté ;
- **Prête à transmettre** dès que le connecteur de l’organisation est activé.

Cette étape ne prétend pas envoyer une facture : aucun compte ni jeton Super PDP n’est encore configuré dans Cremona. Le connecteur OAuth/API et ses retours de statut feront l’objet du lot d’intégration, après ouverture de l’environnement Super PDP.

## Préparation sandbox (Burger Queen)

La production Cremona dispose des variables de l’application OAuth **sandbox** créée pour Burger Queen. Elles servent exclusivement aux essais techniques et ne sont ni versionnées ni affichées dans l’interface. Lors du passage de Burger Queen vers son entreprise Super PDP réelle, ces identifiants sandbox devront être remplacés par le `client_id` et le `client_secret` de l’application OAuth de production. Tricatel disposera de sa propre application OAuth, car Super PDP rattache chaque application à l’entreprise sélectionnée.

Pennylane reste une copie facultative destinée au comptable (par e-mail d’import des factures de vente) ; ce n’est pas une seconde transmission réglementaire et aucune API Pennylane payante n’est requise.

## Plan de configuration visible

Les intégrations doivent être administrables depuis **Configuration de
l’organisation**, au même endroit que les connecteurs Google Ads ou Brevo. La
configuration visible explique ce qui est actif et pour quelle organisation ;
elle ne révèle jamais un secret, un jeton ni une clé d’accès.

| Élément | Portée | Emplacement et règle |
| --- | --- | --- |
| Scaleway Object Storage | Infrastructure Cremona partagée | Une fiche d’état globale décrit le stockage privé et sa disponibilité. Chaque organisation voit, dans sa configuration, que ses documents privés y sont conservés. Les clés restent seulement dans l’environnement serveur. |
| Documents privés | Organisation | La configuration de l’organisation indique l’activation, les droits d’accès et le principe de conservation des PDF, contrats et pièces jointes. |
| Super PDP | Organisation | La configuration montre le canal choisi, l’environnement (sandbox ou production), l’entreprise Super PDP associée et le dernier contrôle de connexion. Les identifiants sont chiffrés et masqués. |
| Google Ads, Brevo et autres connecteurs | Organisation | Chaque connecteur conserve sa propre fiche, son état, son dernier contrôle et ses droits, sans mélanger les données d’une autre organisation. |

Pour le pilote Contempo, **Burger Queen est uniquement l’entreprise fictive de
la sandbox Super PDP** : ce n’est ni une organisation Cremona ni un choix à
faire par l’utilisateur. Cremona utilisera les identifiants serveur de cette
application pour ses contrôles et transmissions de test ; aucune autorisation
OAuth manuelle ne sera demandée au quotidien.

Le contrôle de connexion utilise OAuth `client_credentials` depuis le serveur :
il ne redirige personne vers Super PDP et ne conserve pas de jeton utilisateur.
Dans la configuration de l’organisation, le bouton **Vérifier la connexion
Super PDP** est seulement un diagnostic ponctuel. Il vérifie le jeton serveur
et l’état de l’entreprise sandbox ; il ne crée ni ne transmet une facture.

### Lot à réaliser

1. Préparer le stockage chiffré des identifiants propres à chaque organisation
   pour la production, afin de remplacer Burger Queen sans développement
   supplémentaire lorsque Contempo aura sa véritable entreprise Super PDP.

## Limites du premier lot

Une facture peut être créée manuellement ou depuis un devis accepté : les lignes
et le client sont alors repris une seule fois dans un brouillon. La facturation
d’échéance de location reste volontairement manuelle ou désactivée selon
l’organisation ; elle ne doit pas être automatisée avant validation métier.

L’envoi effectif et le suivi des retours Super PDP restent le prochain lot : il
nécessite les accès de la véritable entreprise émettrice. La sandbox Burger
Queen sert à valider la connexion technique, jamais à produire une facture
légale de Contempo.
