# Facturation et Super PDP

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

### Lot à réaliser

1. Retirer le parcours OAuth manuel présenté à tort comme une action utilisateur.
2. Ajouter le client serveur Super PDP (`client_credentials`) et un contrôle de
   connexion sans créer ni transmettre de facture.
3. Afficher les états Scaleway et Super PDP dans la configuration de
   l’organisation, avec une action explicite de **vérification** réservée au
   diagnostic — et non un bouton de connexion nécessaire au travail quotidien.
4. Préparer le stockage chiffré des identifiants propres à chaque organisation
   pour la production, afin de remplacer Burger Queen sans développement
   supplémentaire lorsque Contempo aura sa véritable entreprise Super PDP.

## Limites du premier lot

La création de facture est aujourd’hui manuelle, avec des liens possibles vers un devis, une location ou un dossier atelier. Les actions « créer depuis un devis », « facturer une échéance de location » et l’envoi effectif à Super PDP viendront ensuite, une fois les règles de chaque organisation validées.
