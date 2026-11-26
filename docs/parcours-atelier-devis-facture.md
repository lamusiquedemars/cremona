# Audit détaillé — dossier atelier → devis → facture

Date d'audit : 17 novembre 2026. Périmètre : comportement actuellement livré dans Cremona, sans modification des données Contempo.

Ce document distingue les URL que l'utilisateur ouvre des actions réellement exécutées par l'application. Cremona est une application Laravel + Filament/Livewire : les boutons métier ne redirigent pas vers une API REST propre à chaque action. Une page est chargée par `GET`, puis chaque clic métier appelle `POST /livewire/update` avec la session et le jeton CSRF de l'utilisateur. Le composant Livewire invoque ensuite le service indiqué dans les tableaux ci-dessous.

## Repères d'URL

Dans tous les exemples :

- `{org}` est le *slug* de l'organisation Contempo, pas son nom affiché ;
- `{dossier}`, `{devis}` et `{facture}` sont les `public_id` ULID, pas les références visibles `AT…`, `D…` ou `FAC…` ;
- le préfixe d'hôte est volontairement absent : les chemins ci-dessous sont relatifs à l'instance Cremona ouverte par l'utilisateur.

| Écran | URL de lecture |
| --- | --- |
| Liste des dossiers | `GET /dashboard/{org}/workshop-orders` |
| Créer un dossier | `GET /dashboard/{org}/workshop-orders/create` |
| Consulter un dossier | `GET /dashboard/{org}/workshop-orders/{dossier}` |
| Modifier / piloter un dossier | `GET /dashboard/{org}/workshop-orders/{dossier}/edit` |
| Liste des devis | `GET /dashboard/{org}/quotes` |
| Consulter un devis | `GET /dashboard/{org}/quotes/{devis}` |
| Modifier / piloter un devis | `GET /dashboard/{org}/quotes/{devis}/edit` |
| PDF du devis (utilisateur connecté) | `GET /quotes/{devis}/pdf` |
| Liste des factures | `GET /dashboard/{org}/invoices` |
| Brouillon de facture | `GET /dashboard/{org}/invoices/{facture}/edit` |
| Consulter une facture | `GET /dashboard/{org}/invoices/{facture}` |
| Télécharger le PDF d'une facture émise | `GET /documents/{document}/download` |

Les trois modules doivent être activés et accessibles à l'utilisateur : `workshop`, `quotes`, puis `invoicing`. Sinon, une partie des liens/boutons n'apparaît pas.

## Exemple Contempo à prendre comme fil conducteur

Le jeu de démonstration livré par Cremona prévoit, dans l'organisation Contempo, le dossier **« Exercice : révision et reméchage »** (référence de démonstration `DEMO-CONTEMPO-ATELIER`). Il contient :

- un client, Camille Martin — démonstration ;
- un diagnostic déjà saisi ;
- trois prestations, dont deux cochées « À ajouter au devis » ;
- trois articles de stock, dont deux cochés « À ajouter au devis ».

Il est volontairement placé à l'état **Diagnostic établi**. C'est donc le bon point de départ pour observer la transformation vers le devis sans créer de donnée. Dans la liste `Dossiers atelier`, rechercher le titre puis ouvrir **Modifier** ; les actions de workflow ne sont pas disponibles depuis la vue de consultation.

## Parcours réel, clic par clic

### 1. Créer et documenter le dossier atelier

1. L'utilisateur ouvre `GET /dashboard/{org}/workshop-orders`.
2. Il clique **Nouveau dossier atelier** ; la page `…/workshop-orders/create` s'ouvre.
3. Il renseigne l'intitulé (obligatoire), puis idéalement le client, l'instrument confié, la demande client, le diagnostic et les dates. En enregistrant, Cremona attribue automatiquement une référence `ATyyNNNN` et l'état initial **Reçu** ; `received_at` est renseigné automatiquement.
4. Dans les répétiteurs **Prestations prévues** et **Articles à consommer**, l'utilisateur choisit une prestation/un article ou saisit un libellé, une quantité et un prix HT. Il coche **À ajouter au devis** seulement pour les lignes qui seront proposées au client.
5. Il enregistre le formulaire. C'est une sauvegarde standard de l'écran Filament ; elle met à jour le dossier et ses lignes. Si le client est changé à ce stade, le client est également reporté vers le devis atelier tant que celui-ci est encore brouillon.

Important : cocher une ligne ne la met pas automatiquement au devis. Elle devient seulement éligible à l'action explicite de synchronisation décrite à l'étape 3. Les articles de stock ne sont pas déduits à ce moment-là.

### 2. Valider le diagnostic

1. Depuis la fiche en modification `GET …/workshop-orders/{dossier}/edit`, l'utilisateur ouvre le menu **Actions du dossier**.
2. À l'état **Reçu**, il clique **Valider le diagnostic** puis confirme la modale.
3. Le navigateur envoie `POST /livewire/update`; le composant `EditWorkshopOrder` appelle `WorkshopOrderWorkflowManager::confirmDiagnosis()`.
4. Le serveur refuse l'action si le champ `Diagnostic atelier` est vide. Sinon, il passe `received` → `diagnosed` et inscrit `diagnosed_at`.

Résultat visible : état **Diagnostic établi**. Aucune notification client et aucun devis ne sont créés automatiquement.

### 3. Créer ou compléter le devis depuis le dossier

1. Toujours sur l'URL d'édition du dossier, l'utilisateur ouvre **Actions du dossier**.
2. Lorsque le dossier est **Diagnostic établi** ou **En attente d'accord**, il clique **Créer ou compléter le devis**.
3. Le clic effectue `POST /livewire/update`; `EditWorkshopOrder` appelle `WorkshopOrderQuoteManager::sync($dossier)`.
4. Le service cherche un devis dont `workshop_order_id` est ce dossier.
   - S'il n'existe pas, il crée un devis **Brouillon**, titré `Intervention atelier — {intitulé du dossier}`, lié au client et à la demande initiale du dossier. Sa référence est `DyyNNNN`, numériquement appariée au numéro du dossier (`AT260001` → `D260001`).
   - S'il existe et est encore brouillon, le même devis est complété ; aucun doublon n'est créé.
   - S'il est envoyé, accepté ou refusé, le serveur refuse toute mise à jour automatique de ses lignes atelier.
5. Pour chaque prestation cochée, une ligne de devis de type `service` est créée si elle n'existe pas déjà. Pour chaque article coché, une ligne de type `product` est créée si elle n'existe pas déjà. Les libellés, descriptions, quantités et prix proviennent des instantanés saisis dans le dossier.
6. Cremona affiche seulement la notification « Devis mis à jour ». Elle reste sur le dossier : il faut ouvrir une nouvelle fois **Actions du dossier**, puis cliquer **Ouvrir le devis D…**. Ce dernier clic navigue en `GET /dashboard/{org}/quotes/{devis}`.

Limite importante : « compléter » signifie seulement *ajouter les lignes source absentes*. Le service utilise `firstOrCreate`; il ne propage pas les modifications ultérieures de quantité, description ou prix du dossier vers une ligne déjà créée dans le devis. La promesse du libellé est donc plus large que le comportement réel.

### 4. Relire, modifier et envoyer le devis

La page de consultation `GET …/quotes/{devis}` affiche un rendu documentaire. Elle permet :

- **Ouvrir le dossier atelier AT…** : navigation vers la vue du dossier ;
- **Télécharger le PDF** : `GET /quotes/{devis}/pdf`, disponible seulement si les coordonnées/mentions légales de l'organisation sont complètes ;
- **Modifier le devis** : seulement lorsque le devis est brouillon ;
- **Créer la facture** : seulement lorsque le devis est accepté, donc pas encore ici.

Pour modifier les lignes ou envoyer le document, il faut cliquer **Modifier le devis**, soit `GET /dashboard/{org}/quotes/{devis}/edit`.

1. L'utilisateur vérifie ou complète le destinataire, les lignes, la remise globale, la date, la validité, la note fiscale et les conditions de règlement ; il enregistre.
2. Sur ce même écran d'édition, il clique **Marquer comme envoyé**.
3. Une modale demande le **Mode d'envoi** : Email, Remis au client, ou Autre canal. Elle n'envoie aucun e-mail ; elle enregistre uniquement cette trace.
4. Après validation, le navigateur appelle `POST /livewire/update`, puis `QuoteWorkflowManager::markSent($devis, $mode)`.
5. Le serveur exige au moins une ligne et la complétude des coordonnées légales de l'émetteur ; il fige les instantanés émetteur/destinataire, enregistre `sent_at` et `sent_via`, et passe le devis `draft` → `sent`.
6. Comme ce devis est lié à un dossier, il appelle aussi le workflow atelier : dossier `diagnosed` → `awaiting_approval` (**En attente d'accord**).

Après envoi, le devis n'est plus modifiable dans l'interface. Le changement du client du dossier ne le modifiera donc plus non plus.

### 5. Enregistrer la réponse du client

1. Toujours sur `GET …/quotes/{devis}/edit`, l'utilisateur choisit l'un des boutons visibles seulement à l'état **Envoyé** : **Enregistrer l'accord client** ou **Enregistrer le refus**.
2. Il confirme la modale. L'appel passe par `POST /livewire/update` vers `QuoteWorkflowManager::markAccepted()` ou `markDeclined()`.
3. En cas d'accord, le devis passe `sent` → `accepted`, `accepted_at` est rempli, puis le synchroniseur fait passer le dossier lié de `diagnosed` ou `awaiting_approval` à `approved` (**Accord reçu — à planifier**) et renseigne `authorized_at`.
4. En cas de refus, le devis passe `sent` → `declined`. Le dossier n'est pas annulé automatiquement : il reste **En attente d'accord**. Il n'existe pas, dans cet écran, de bouton pour le clôturer ou le corriger après refus.

### 6. Réaliser et restituer l'intervention (parcours atelier distinct)

Le dossier déjà approuvé peut continuer son workflow sur `GET …/workshop-orders/{dossier}/edit` > **Actions du dossier** :

| Précondition | Bouton / saisie | Appel serveur | Effet |
| --- | --- | --- | --- |
| `diagnosed`, `awaiting_approval` ou `approved` | **Planifier l'intervention**, choisir une date. Sans devis lié, saisir aussi un motif d'accord. | `WorkshopOrderWorkflowManager::schedule()` | Le devis lié doit être accepté. État → `scheduled`, `due_at`, `scheduled_at` et `authorized_at` sont enregistrés. |
| `scheduled` | **Démarrer l'intervention**, confirmer. | `start()` | État → `in_progress`, `started_at`. |
| À tout moment où des articles restent à déduire | **Déduire le stock consommé**, vérifier l'aperçu, confirmer. | `StockManager::applyWorkshopConsumption()` | Crée un mouvement de stock par ligne non déjà appliquée. C'est séparé du devis et non conditionné par l'état du dossier. |
| `in_progress` | **Déclarer l'instrument prêt**, confirmer. | `markReady()` | État → `ready`, `ready_at`; aucun message client automatique. |
| `ready` | **Enregistrer la restitution**, confirmer. | `markReturned()` | État → `returned`, `returned_at`. |

L'émission de facture n'est pas liée à la planification, au démarrage, à la déduction du stock, à l'état prêt ou à la restitution. Dès que le devis est accepté, elle peut être faite.

### 7. Créer le brouillon de facture depuis le devis accepté

Deux entrées mènent à la même opération :

- sur le devis accepté en consultation `GET …/quotes/{devis}`, bouton **Créer la facture** ;
- dans le dossier, édition `GET …/workshop-orders/{dossier}/edit` > **Actions du dossier** > **Créer la facture**.

1. Le bouton n'est visible que si le devis est **Accepté** et que l'utilisateur peut accéder au module Facturation.
2. Son clic envoie `POST /livewire/update` et appelle `InvoiceFromQuoteManager::createDraft($devis)`.
3. Le service verrouille le devis, exige son statut `accepted` et au moins une ligne. S'il existe déjà une facture liée à ce devis, il retourne cette facture au lieu d'en créer une deuxième.
4. Il crée sinon une facture **Brouillon**, liée simultanément au devis, au dossier atelier, au client et à l'entreprise éventuelle. Il reprend le titre, l'introduction, la devise et les conditions de règlement du devis ; `supply_on` reçoit la **date d'émission du devis**.
5. Chaque ligne de devis devient une ligne de facture avec sa quantité et son prix HT. Le taux de TVA ne vient pas de la ligne de devis : il est appliqué uniformément depuis le taux de TVA par défaut de l'organisation au moment de la création.
6. L'utilisateur est redirigé directement vers `GET /dashboard/{org}/invoices/{facture}/edit` si la facture est un brouillon ; sinon vers sa vue.

### 8. Contrôler puis émettre la facture

1. Sur `GET …/invoices/{facture}/edit`, l'utilisateur contrôle et peut encore modifier : client particulier/professionnel, date d'émission, date de prestation, échéance, devise, lien atelier/devis, introduction, lignes, remises, TVA et conditions de paiement. Les données légales de l'organisation sont gérées dans `GET /dashboard/{org}/organization-details`.
2. Il enregistre, puis arrive/retourne sur `GET /dashboard/{org}/invoices/{facture}`.
3. Il clique **Émettre la facture**, lit l'avertissement (« numéro définitif » et données non modifiables), puis confirme.
4. L'action utilise `POST /livewire/update`, puis `InvoiceWorkflowManager::issue()`.
5. Le serveur refuse l'émission si la facture est vide, déjà émise, sans client, ou si le profil légal de l'organisation est incomplet (nom affiché, raison sociale, e-mail, adresse, code postal, ville, pays, SIRET/immatriculation). Il refuse également un avoir sans facture originale émise.
6. En cas de succès, il :
   - recalcule HT, TVA, TTC et solde ;
   - attribue le numéro définitif `{préfixe}-{année}-{compteur sur 5 chiffres}` ;
   - fixe dates, instructions de paiement, conditions de retard et instantanés émetteur/destinataire/réglages ;
   - passe la facture `draft` → `issued` ;
   - génère un PDF privé ;
   - crée un journal de transmission Super PDP, sans transmettre automatiquement la facture ;
   - verrouille toute modification ultérieure. Une correction doit passer par un avoir.

Après émission, le bouton **Télécharger le PDF** apparaît et appelle `GET /documents/{document}/download`. Le bouton **Enregistrer un règlement** permet ensuite de saisir date, montant, mode et référence ; chaque saisie recalcule le solde et fait évoluer `issued` vers `partially_paid` puis `paid`.

## Chaîne des états

```text
Dossier : Reçu
  └─ Valider le diagnostic → Diagnostic établi
       └─ Créer / compléter le devis → (dossier toujours Diagnostic établi)

Devis : Brouillon
  └─ Marquer comme envoyé → Envoyé
       ├─ Enregistrer l'accord → Accepté → dossier : Accord reçu — à planifier
       │                                      └─ Créer la facture → facture Brouillon
       └─ Enregistrer le refus → Refusé (dossier reste En attente d'accord)

Facture : Brouillon
  └─ Émettre la facture → Émise → Partiellement réglée → Réglée
```

En parallèle après l'accord, le dossier suit : **Accord reçu — à planifier** → **Planifié** → **En cours** → **Prêt à restituer** → **Restitué**. Cette branche ne bloque ni ne déclenche l'émission de facture.

## Points qui rendent le parcours compliqué ou fragile

1. **Les actions sont réparties entre deux écrans pour chaque objet.** La vue du dossier ne propose que **Modifier le dossier** ; toutes les actions sont cachées dans le menu de l'écran d'édition. La vue du devis est un joli document, mais l'envoi, l'accord et le refus sont uniquement dans son écran d'édition. L'utilisateur doit constamment alterner entre consulter et modifier pour « faire avancer » un document.

2. **Le menu générique « Actions du dossier » cache les décisions principales.** Créer le devis, ouvrir le devis, planifier, démarrer, gérer le stock et créer la facture sont au même niveau dans un menu déroulant. Leur ordre actuel commence même par **Créer la facture** (lorsqu'elle est possible), avant les actions atelier attendues.

3. **La création du devis ne mène pas au devis.** Après **Créer ou compléter le devis**, l'utilisateur obtient une notification, reste sur le dossier, doit rouvrir le menu et choisir **Ouvrir le devis**. C'est deux clics superflus et une source de doute (« le devis a-t-il réellement été créé ? »).

4. **« Créer ou compléter » ne synchronise pas les changements.** Les nouvelles lignes cochées sont ajoutées, mais une ligne déjà créée ne reçoit plus les modifications apportées dans le dossier. Une personne qui corrige quantité ou prix dans l'atelier peut croire que le devis est corrigé alors qu'il faut modifier le devis séparément.

5. **L'envoi est seulement une déclaration manuelle.** Le choix « Email » ne compose ni n'envoie de message. L'interface et le vocabulaire « Marquer comme envoyé » limitent ce malentendu, mais l'étape ne crée aucune preuve d'envoi hormis `sent_via` et l'horodatage.

6. **Après un refus, le dossier reste bloqué dans « En attente d'accord ».** Il n'y a pas de bouton de retour en brouillon, de duplication/révision du devis, ni d'annulation du dossier dans le parcours affiché. Le statut `cancelled` existe, mais aucune action de cette page ne l'atteint.

7. **La facture peut être créée avant que l'intervention soit faite ou restituée.** C'est peut-être souhaité pour un acompte, mais aucun choix explicite « facturer maintenant / à la restitution » ne l'explique. En plus, la date de prestation préremplie est la date du devis, pas la date de fin de l'intervention.

8. **La TVA est rompue entre devis et facture.** Le devis n'a pas de TVA par ligne ; lors de la création de facture, le taux est repris du réglage courant de l'organisation, pas du devis accepté. Un changement du réglage de TVA entre les deux étapes peut modifier le TTC sans alerte.

9. **La visibilité des prérequis est tardive.** Les mentions légales sont vérifiées au clic d'envoi du devis et à l'émission de la facture. Les règles d'accès à la Facturation peuvent aussi simplement masquer le bouton. L'utilisateur découvre donc certains blocages après avoir parcouru plusieurs écrans.

10. **Les liens emploient des identifiants techniques invisibles.** Les URL utilisent un ULID, alors que l'utilisateur parle avec `AT…`, `D…` et `FAC…`. C'est sûr techniquement, mais rend impossible une navigation manuelle à partir d'une référence métier.

## Recommandation de recadrage produit

Le meilleur premier lot serait un **poste de pilotage unique du dossier** : depuis la consultation du dossier, afficher un bandeau d'étape et un seul bouton principal contextuel — par exemple « Préparer le devis », « Envoyer le devis », « Enregistrer l'accord », « Planifier », « Clôturer l'intervention », « Préparer la facture ». Les actions secondaires (stock, PDF, liens croisés) resteraient accessibles mais séparées.

Avant de modifier le workflow, trois décisions métier sont nécessaires :

1. la facture doit-elle être proposée après l'accord, après l'intervention, après la restitution, ou selon une option explicite (acompte / solde) ?
2. après un refus, veut-on rouvrir/réviser le même devis, créer une nouvelle version, ou annuler le dossier ?
3. le devis doit-il devenir un véritable instantané commercial avec TVA, ou la TVA doit-elle être décidée seulement au stade facture ?

## Sources contrôlées

- Ressources et écrans : `app/Filament/Resources/WorkshopOrders`, `Quotes`, `Invoices`.
- Règles métier : `app/Services/WorkshopOrderWorkflowManager.php`, `WorkshopOrderQuoteManager.php`, `QuoteWorkflowManager.php`, `InvoiceFromQuoteManager.php`, `InvoiceWorkflowManager.php`.
- Parcours démonstration : `app/Console/Commands/SeedContempoDemoScenario.php`.
- Tests de référence : `tests/Feature/WorkshopOrderWorkflowTest.php`, `WorkshopReferenceTest.php`, `InvoiceWorkflowTest.php`.
