# Plan directeur produit — Cremona

> Statut : cap produit validé, plan de réalisation à lancer par étapes.
>
> Dernière mise à jour : 8 septembre 2026.
>
> Ce document est la référence de reprise du chantier. Il décrit la cible ; il ne vaut pas autorisation de supprimer, migrer ou masquer une capacité sans audit, tests et validation explicite.

## 1. Intention du produit

Cremona est le poste de pilotage d'une entreprise. Il doit rester simple à comprendre pour une personne qui dirige un atelier, un cabinet ou une entreprise de service, alors que l'infrastructure sous-jacente peut devenir très puissante.

L'utilisateur ne vient pas « utiliser Google Ads », « GA4 », « Search Console », un CMS ou une API. Il veut répondre à une demande, préparer un rendez-vous, suivre une proposition, présenter son activité ou savoir si sa communication fonctionne. Les services externes sont des moyens techniques, pas les grands menus de travail.

Les mots affichés doivent parler au métier de l'organisation. Les modèles, droits, contrats d'intégration et règles d'isolation restent canoniques et ne dépendent jamais du vocabulaire affiché.

## 2. Navigation cible

La navigation de référence est la suivante. Elle décrit des capacités produit ; un élément absent ou incomplet ne doit pas être affiché comme s'il fonctionnait déjà.

```text
Accueil
  Tableau de bord

Relation client
  Demandes · Contacts · Entreprises · Devis · Correspondances

Organisation
  Agenda · Tâches · Documents

Offre
  Produits · Services · Réalisations

Présence en ligne
  Site · Contenus · Fiche Google

Acquisition
  Campagnes · Résultats · Référencement

Paramètres
  Entreprise · Utilisateurs · Connexions · Réglages
```

### Principes de vocabulaire

- **Demande** est le mot courant pour une intention entrante. « Lead » ne doit pas devenir une entrée principale.
- **Agenda** est le nom utilisateur de la capacité temporelle. Il pourra réunir rendez-vous, essais, appels, visites et rappels. Tant que seuls les rendez-vous existent, l'interface ne doit pas promettre davantage.
- **Campagnes**, **Résultats** et **Référencement** expriment une intention de pilotage. Google Ads, GA4 et Search Console restent dans le détail, l'aide ou les connexions.
- **Devis** reste le nom canonique de la capacité commerciale ; une organisation peut le présenter comme « Proposition d'atelier » sans changer la nature de la donnée ni sa valeur juridique.
- Les exemples « Clients et atelier », « Dossiers et échéances », « À faire à l'atelier », « archets » ou « violons » sont des exemples de configuration, jamais des noms codés en dur.

## 3. État constaté au 8 septembre 2026

| Capacité | État | Limite ou suite |
| --- | --- | --- |
| Demandes, contacts, entreprises, correspondances | opérationnel | champs encore peu configurables par organisation |
| Tâches, rendez-vous, documents privés | opérationnel | rendez-vous n'est pas encore un agenda multi-types |
| Devis | socle opérationnel | PDF, règles d'émission et cycle complet à approfondir |
| Campagnes Google Ads | opérationnel | GA4 et Search Console non connectés |
| Tableau de bord | présent | doit devenir la synthèse prioritaire de tous les domaines |
| Adaptation de présentation | opérationnelle | labels, groupes et visibilité ; registre à généraliser |
| Email, SMTP, import IMAP | opérationnel | formation et usages réels à consolider |
| Offre | non construite | aucun objet métier ne doit être forcé dans un catalogue universel |
| Présence en ligne | fondations site seulement | le site public demeure la responsabilité de Maracuja CMS |
| Résultats et Référencement | non construits | audit comparatif des sources obligatoire |
| IA | bases de correspondance seulement | aucune automatisation externe sans garde-fous |

## 4. Architecture à viser

### Registre canonique des capacités

La navigation ne doit pas être une liste de ressources Filament indépendantes. Elle doit provenir d'un registre de capacités stable. Chaque capacité possède :

- un identifiant technique immuable, par exemple `campaigns`, `quotes`, `appointments` ;
- un groupe canonique, par exemple `acquisition`, `relation_client`, `organisation` ;
- son ordre, ses dépendances et son état de disponibilité ;
- son libellé de secours et les éléments éditables de sa présentation ;
- les rôles et permissions nécessaires ;
- les intégrations qui la soutiennent, sans les exposer par défaut au menu.

Les organisations peuvent personnaliser les libellés et la visibilité explicitement prévues. Elles ne peuvent pas renommer un identifiant technique, contourner un droit ou changer le sens d'une donnée.

La personnalisation actuelle (`settings.presentation`) est une première étape. Avant de multiplier les clés, le registre devra devenir la source unique des groupes, des éléments éditables et de leur présentation. Le JSON ne doit pas devenir une structure métier arbitraire.

### Visibilité, droit et données : trois choses distinctes

- Masquer un module le retire de la navigation et des raccourcis ordinaires. Cela ne supprime aucune donnée et ne modifie aucun droit.
- Un accès direct, une relation ou une API continue à respecter les politiques d'autorisation et l'isolation de l'organisation.
- Désactiver une intégration ne détruit ni les historiques synchronisés ni les identifiants nécessaires à leur traçabilité.
- Réorganiser un écran ne justifie jamais une suppression de table, migration ou fichier sans inventaire et plan de conservation.

### Données métier et modules propres

Les capacités communes gardent des modèles canoniques. Un objet réellement propre à un métier doit avoir son module, ses migrations, politiques et tests.

Un archet, une intervention de lutherie, un dossier juridique ou un chantier ne doivent pas être forcés dans une table générique de « produits ». Le groupe **Offre** est une promesse d'expérience utilisateur ; il ne décide pas à l'avance du schéma de données.

## 5. Chantiers, ordre et critères de fin

### A. Stabiliser la navigation produit

**But :** faire correspondre progressivement la navigation réelle à la cible, sans créer de lien vers un écran vide.

- Valider la configuration d'organisation déjà livrée : groupes, libellés et interrupteurs.
- Ajouter au registre les groupes canoniques cibles ; n'afficher que les groupes contenant une capacité réellement livrée.
- Déplacer : Demandes, Contacts, Entreprises, Devis, Correspondances vers Relation client ; Tâches et Documents vers Organisation ; Campagnes vers Acquisition.
- Présenter les rendez-vous comme **Agenda** seulement après livraison de la vue correspondant à cette promesse.
- Réserver Offre, Présence en ligne, Résultats et Référencement sans les afficher avant leur première capacité utilisable.
- Réunir les ressources techniques sous Paramètres en distinguant Entreprise, Utilisateurs, Connexions et Réglages.

**Fin :** chaque entrée répond à un besoin compréhensible ; aucun menu ne porte le nom d'un prestataire ; aucune URL, permission ou donnée existante n'est perdue.

### B. Accueil et priorités quotidiennes

**But :** faire du tableau de bord l'entrée naturelle de Cremona.

- Afficher les nouvelles demandes, conversations en attente, rendez-vous à venir, tâches échues ou prochaines, devis à suivre et campagnes nécessitant attention.
- Distinguer information, alerte et action possible.
- Lier chaque bloc au contexte utile, pas à une liste générale sans filtre.
- Respecter le fuseau horaire de chaque organisation.
- Sourcer chaque chiffre et montrer la dernière synchronisation.

**Fin :** il répond à « que dois-je faire aujourd'hui ? » en moins d'un regard.

### C. Organisation : véritable Agenda

**But :** faire évoluer le rendez-vous existant vers une vue Agenda fiable.

1. Auditer rendez-vous, tâches avec échéance, événements Brevo éventuels et fuseaux.
2. Concevoir une vue qui agrège plusieurs sources sans les dupliquer.
3. Introduire des types d'événements seulement après validation : rendez-vous, essai, appel, visite, rappel, indisponibilité.
4. Définir si un rappel est une tâche datée, un événement agenda ou les deux ; éviter les doublons invisibles.
5. Prévoir les liens vers contact, demande, devis et conversation.

**À ne pas faire :** renommer seulement « Rendez-vous » en « Agenda ».

### D. Offre et premier module métier validé

**But :** construire la première capacité de l'offre à partir d'un métier réel.

1. Cadrer Atelier Ivo sur trois cas : vente d'archet, essai, commande ou service éventuel.
2. Distinguer ce qui est catalogue, prestation, réalisation publiée et document interne.
3. Créer un module métier si l'objet le requiert, plutôt que des colonnes génériques sans fin dans le noyau.
4. Prévoir une projection contrôlée vers Maracuja CMS lorsque la donnée est publique ; Cremona reste la source de vérité métier.
5. N'afficher Produits, Services ou Réalisations qu'une fois le premier flux complet livré, testé et documenté.

**Fin :** l'entreprise gère une offre réelle sans vocabulaire emprunté à un autre métier ni doublon avec le site public.

### E. Présence en ligne

**But :** donner une vue métier de ce que les clients voient, sans transformer Cremona en CMS.

- **Site** : état des sites rattachés, domaine, disponibilité et liens utiles.
- **Contenus** : accès aux contenus publiables ou synchronisés selon les API validées, jamais une copie concurrente du CMS.
- **Fiche Google** : état ou gestion Google Business Profile derrière une connexion technique sécurisée.
- Définir la propriété de chaque donnée et l'API entre Cremona et Maracuja CMS avant construction.

**Fin :** l'artisan comprend ce que le public voit, sans connaître le CMS, le SEO technique ou les clés API.

### F. Acquisition : Campagnes, Résultats, Référencement

**But :** rendre les résultats lisibles sans confondre les sources.

#### F1. Campagnes

Conserver le module existant : synchronisation, statuts expliqués, mots-clés, écarts de configuration et actions explicites. Ne pas réintroduire un aperçu de création dans le pilotage d'une campagne déjà diffusée.

#### F2. Résultats : audit et frontière avec Campagnes

| Information | Campagnes | Résultats |
| --- | --- | --- |
| Budget, mots-clés, statuts, annonces | oui : pilotage détaillé | non : lien vers la campagne |
| Impressions, clics, coût Ads | oui : par campagne | oui : synthèse, source Ads explicite |
| Visiteurs, sessions, parcours | non | oui : source GA4 explicitée |
| Demandes et attribution Cremona | lien contextuel | oui : résultat commercial et source connue |
| Conversion Google Ads | oui : métrique plateforme | oui : ne pas la confondre avec une demande Cremona |
| Coût par prospect | calcul possible | oui, avec formule, période et source explicites |

L'écran Résultats affiche toujours période, fuseau, source, dernière synchronisation et limites de comparaison. Une conversion Ads n'est pas automatiquement une demande validée dans Cremona.

La future connexion GA4 est centrale et en lecture de statistiques agrégées via l'API Google Analytics Data. Elle est distincte de Tag Manager et de la pose de balises sur les sites.

#### F3. Référencement

Présenter recherches, pages trouvées, clics, impressions et tendances issus de Search Console. Employer un langage métier ; ne nommer Search Console que dans les connexions ou l'aide. Ne pas fabriquer de score SEO opaque.

**Fin :** on répond à « est-ce que ma publicité et ma présence en ligne produisent des résultats ? » avec des chiffres sourcés, non un tableau technique fragmenté.

### G. Paramètres et connexions

**But :** sortir les réglages techniques du travail quotidien sans les rendre opaques aux administrateurs autorisés.

- **Entreprise** : identité, fuseau, présentation et sites rattachés.
- **Utilisateurs** : membres, rôles, accès par organisation.
- **Connexions** : email/SMTP/IMAP, Google Ads, agenda, Brevo, OpenAI, GA4, Search Console et futurs prestataires.
- **Réglages** : préférences non sensibles.

Les secrets ne sont jamais placés dans les écrans métier, migrations, Git, exports ou chat. Les actions externes restent explicites et auditables.

### H. Adaptation métier de niveau 2

**But :** simplifier les formulaires selon l'organisation, sans moteur de formulaires généraliste prématuré.

Pour chaque champ explicitement prévu : visible ou masqué, facultatif ou obligatoire, libellé, aide contextuelle et éventuellement ordre d'affichage.

La décision vient de flux observés. Pour Atelier Ivo, documenter d'abord une demande d'archet, un essai et une proposition/vente. Vérifier avant chaque masquage son rôle dans les historiques, intégrations, exports et validations.

### I. IA, après stabilisation des données et des écrans

L'IA est une couche d'assistance, pas un nouveau menu ni une source de vérité.

Ordre possible :

1. résumer une correspondance et extraire des éléments à vérifier ;
2. proposer une qualification de demande, une tâche ou une relance ;
3. préparer une réponse, toujours éditable et envoyée explicitement ;
4. expliquer les variations de résultats à partir de données réelles, en distinguant fait, hypothèse et recommandation ;
5. assister une campagne ou un contenu sans publier ni activer automatiquement.

Garde-fous obligatoires :

- isolation stricte par `organization_id` ;
- aucun secret, document privé ou donnée hors périmètre transmis ;
- historique de l'entrée, de la proposition et de l'action humaine ;
- sources et incertitude explicites ;
- validation humaine avant envoi, publication, modification ou activation ;
- coût, quotas et consentement administrables dans Paramètres.

## 6. Méthode obligatoire pour chaque livraison

1. Partir d'un besoin et de cas réels, pas d'un formulaire abstrait.
2. Cartographier données, modèles, routes, politiques, tests, intégrations, tâches planifiées et écrans.
3. Décider explicitement : conserver, déplacer, renommer, masquer, migrer ou remplacer. Réorganiser ne signifie jamais supprimer.
4. Préserver production, historiques, URLs utiles et intégrations actives. Toute migration est additive et réversible autant que possible.
5. Utiliser des identifiants techniques stables et ne jamais coder le nom d'un client ou métier dans le noyau.
6. Tester syntaxe, unitaires, intégration, isolation multi-organisation, permissions, migrations et interface réelle selon le risque.
7. Vérifier le diff, commiter les seuls fichiers liés, pousser puis déployer sans toucher à `.env`, `vendor`, `storage` ou la base hors besoin explicite.
8. Après publication, vérifier l'interface, les tâches planifiées et les données existantes avant de clôturer.

## 7. Questions de lancement d'un prochain chat

- Quel chantier est prioritaire, et quel résultat concret doit être visible à la fin ?
- Quels trois cas métier réels servent de référence ?
- Quelles organisations et quels rôles sont concernés ?
- Quelles données et intégrations existantes ne doivent surtout pas être perturbées ?
- Quel est le plus petit périmètre utile avant les variantes et automatisations ?

## 8. Point de départ recommandé

Après l'appropriation d'Atelier Ivo, lancer **B. Accueil et priorités quotidiennes**, parallèlement au cadrage des trois flux Atelier Ivo qui prépareront Agenda et Offre.

Le chantier **Résultats** ne commence qu'après l'audit comparatif du module Campagnes, des métriques Google Ads existantes, des futures données GA4 et de l'attribution des demandes Cremona.
