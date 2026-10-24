# Facturation et Super PDP

## Ce qui est disponible

Le module **Facturation** est transverse : il se choisit explicitement dans la configuration de chaque organisation, quel que soit son métier. Il permet de créer un brouillon, de composer ses lignes HT et TVA, puis d’émettre une facture.

À l’émission, Cremona attribue un numéro définitif, fige les coordonnées légales de l’émetteur et du client, calcule les totaux, génère un PDF privé et enregistre les règlements sans réouvrir la facture. Une correction doit passer par un avoir.

Chaque PDF est un document privé : en production, il est enregistré sur l’espace Object Storage Scaleway déjà configuré pour Cremona, et reste accessible seulement aux personnes autorisées dans l’organisation.

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

Pennylane reste une copie facultative destinée au comptable (par e-mail d’import des factures de vente) ; ce n’est pas une seconde transmission réglementaire et aucune API Pennylane payante n’est requise.

## Limites du premier lot

La création de facture est aujourd’hui manuelle, avec des liens possibles vers un devis, une location ou un dossier atelier. Les actions « créer depuis un devis », « facturer une échéance de location » et l’envoi effectif à Super PDP viendront ensuite, une fois les règles de chaque organisation validées.
