# Plan directeur produit — Cremona

> Statut : architecture cible validée ; plusieurs fondations produit sont
> livrées et doivent maintenant être consolidées par des parcours réels.
>
> Dernière mise à jour : 27 octobre 2026.
>
> Ce document est la référence de reprise du chantier. Il décrit la cible ; il ne vaut pas autorisation de supprimer, migrer ou masquer une capacité sans audit, tests et validation explicite.
>
> Le suivi des lots livrés et du reste à faire est tenu dans [Avancement du plan directeur](avancement-plan-directeur.md).

## 1. Intention du produit

Cremona est le poste de pilotage d'une entreprise. Il doit rester simple à comprendre pour une personne qui dirige un atelier, un cabinet ou une entreprise de service, alors que l'infrastructure sous-jacente peut devenir très puissante.

L'utilisateur ne vient pas « utiliser Google Ads », « GA4 », « Search Console », un CMS ou une API. Il veut répondre à une demande, préparer un rendez-vous, suivre une proposition, présenter son activité ou savoir si sa communication fonctionne. Les services externes sont des moyens techniques, pas les grands menus de travail.

Les mots affichés doivent parler au métier de l'organisation. Les modèles, droits, contrats d'intégration et règles d'isolation restent canoniques et ne dépendent jamais du vocabulaire affiché.

## 2. Navigation cible

La navigation de référence est la suivante. Elle décrit des capacités produit ; un élément absent ou incomplet ne doit pas être affiché comme s'il fonctionnait déjà.

```text
Accueil
  Tableau de bord

Suivi client
  À traiter · Clients · Demandes · Correspondances · Rendez-vous · Tâches

Activité commerciale
  Propositions d’atelier · Documents

Atelier
  Dossiers atelier · Instruments · Locations

Référentiels métier (selon le pack actif)
  Tarifs de location · Prestations atelier · Modèles de propositions

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
- **Demande client** désigne, dans la réflexion produit, un besoin concret qui mérite un suivi : conseil, réparation, achat, location ou rendez-vous. Une conversation n'est transformée en demande que lorsqu'une action ou une décision commerciale est nécessaire. Le libellé définitif de l'interface reste à valider.
- **Clients** est l’entrée unique qui réunit les particuliers et les entreprises. Elle appartient à la rubrique **Suivi client**, qui rassemble également les demandes, correspondances, rendez-vous et tâches. Il n’y a ni deuxième entrée Clients, ni rubrique orpheline.
- **À traiter** est une vue de travail, non une nouvelle table : elle rassemble les demandes ouvertes, les correspondances auxquelles une réponse est attendue et les tâches ouvertes. Chaque élément conserve son modèle, ses règles et son écran propre.
- Un particulier est un client en propre. Une entreprise est une cliente professionnelle ; ses personnes liées sont des **contacts** avec un rôle (direction, professeur, administration, comptabilité). Le mot « contact » ne sert donc pas à renommer indistinctement tous les clients.
- **Agenda** est le nom utilisateur de la capacité temporelle. Il pourra réunir rendez-vous, essais, appels, visites et rappels. Tant que seuls les rendez-vous existent, l'interface ne doit pas promettre davantage.
- **Campagnes**, **Résultats** et **Référencement** expriment une intention de pilotage. Google Ads, GA4 et Search Console restent dans le détail, l'aide ou les connexions.
- **Devis** reste le nom canonique de la capacité commerciale ; une organisation peut le présenter comme « Proposition d'atelier » sans changer la nature de la donnée ni sa valeur juridique.
- Les exemples « Clients et atelier », « Dossiers et échéances », « À faire à l'atelier », « archets » ou « violons » sont des exemples de configuration, jamais des noms codés en dur.

## 3. État produit au 27 octobre 2026

| Capacité | État | Limite ou suite |
| --- | --- | --- |
| Suivi client | opérationnel, à consolider | Clients unifiés et file **À traiter** livrés ; le rapprochement métier des emails directs, appels et rencontres reste à valider |
| Tâches, rendez-vous, documents privés | opérationnel | les rendez-vous ne forment pas encore un agenda multi-types ; les documents privés restent locaux par défaut, avec Object Storage Scaleway activable explicitement |
| Devis et dossiers atelier | opérationnel | PDF, contexte d’organisation, numérotation annuelle et référence partagée dossier/devis livrés ; une création réelle reste à observer |
| Location Luthier | opérationnel, à valider en situation réelle | contrat, assurance facultative, acceptation interne, remise, restitution et suivi manuel d’encaissement livrés ; aucun prélèvement bancaire n’est géré |
| Campagnes Google Ads | opérationnel | GA4 et Search Console non connectés |
| Tableau de bord | opérationnel | priorités CRM et Location visibles ; la pertinence doit être confirmée avec les usages réels |
| Modules, packs et présentation | opérationnels | registre de modules, dépendances et personnalisation de présentation livrés ; leur source de définition reste répartie entre registre et présentation |
| Email, SMTP et relève IMAP | opérationnel, périmètre explicite | import et réponse possibles ; une suppression ou un archivage distant ne ferme jamais une donnée Cremona |
| Offre et présence en ligne | fondations et pilote Luthier | aucun catalogue universel ; CMS ou Cremona est propriétaire d’un objet, jamais les deux |
| Résultats, référencement et IA | non construits | audit des sources, des droits et des garde-fous requis avant tout écran ou automatisation |

### 3.1. Suivi client : une file de travail sans fusion des données

Les objets canoniques restent distincts — demandes, personnes, entreprises,
correspondances, rendez-vous et tâches — car ils ont des cycles, droits et
preuves différents. La navigation ne les impose plus comme seul point d’entrée :
la page **À traiter** est une projection de travail des demandes ouvertes, des
messages entrant restés sans réponse et des tâches ouvertes. Elle ne crée pas de
copie de données et chaque ligne ouvre son objet source.

Le parcours de référence devient :

```text
Message, formulaire ou appel
  → À traiter, correspondance ou création rapide
  → demande client, seulement si un besoin mérite une qualification
  → réponse, note, tâche, rendez-vous, essai ou devis selon le besoin
  → résultat : réalisé, vendu, essai organisé, sans suite, etc.
```

Une demande est le dossier léger d'un besoin réel ; elle ne duplique ni les
e-mails ni la fiche client. Un simple message, une information ponctuelle ou un
spam ne doit pas déclencher de suivi artificiel. Un email direct importé reste
d’abord une correspondance : son rattachement ou sa transformation en demande
reste explicite. La relève IMAP est une capture et non un miroir de la boîte
distante ; supprimer un email de la boîte ne clôture jamais une demande.

| Besoin quotidien | Élément affiché | Rôle et règle de simplicité |
| --- | --- | --- |
| Voir ce qui appelle une action | À traiter | Projection unifiée livrée : demandes ouvertes, réponses attendues et tâches ouvertes ; aucune donnée n’est fusionnée. |
| Retrouver l'historique d'une personne ou d'une structure | Clients | Une entrée livrée, avec vues Particuliers et Professionnels ; les deux modèles existants sont conservés. |
| Lire, répondre, classer | Correspondances | Reste le lieu de la conversation ; un message ne devient pas automatiquement une demande. |
| Qualifier un besoin commercial ou atelier | Demandes | Dossier léger avec origine, qualification, action et issue à éprouver par les cas réels. |
| Savoir quoi faire et quand | Rendez-vous et Tâches | Objets transversaux ; la vue Agenda ne sera créée qu’après audit de leurs règles de coexistence. |
| Préparer ou suivre une proposition | Propositions d’atelier | Elles restent dans Activité commerciale. |
| Gérer une opération de lutherie | Atelier | Les dossiers atelier et locations ont leur propre cycle métier. |

Les décisions validées sont la vue Clients unifiée et la file **À traiter**.
Elles ne fusionnent pas les modèles ni ne suppriment les historiques. Le
rapprochement entre un appel, un email direct, un formulaire et une rencontre
reste un chantier d’usage : il ne doit pas être automatisé avant observation.

Pour un client professionnel, l’entreprise est la partie cliente ; les personnes
qui y travaillent sont ses contacts liés, avec leur fonction et un contact
principal éventuel. Les devis peuvent déjà désigner l’entreprise et son
interlocuteur. Les locations et dossiers atelier ne gèrent encore qu’une
personne : avant de les proposer à un conservatoire ou à une école, il faudra
ajouter proprement la distinction entre le contractant/payeur (personne ou
entreprise) et l’utilisateur de l’instrument. Ce n’est pas une capacité à
simuler avant sa conception métier et documentaire.

### 3.2. Référentiels métier : proposition de regroupement

Les paramètres techniques de l'organisation — identité légale, membres,
boîtes email, canaux entrants, connecteurs, intégrations et sécurité — restent
dans **Configuration de l'organisation**. Ils administrent l'environnement
commun de l'entreprise ; ils ne décrivent pas son activité quotidienne.

À l'inverse, certains objets actuellement répartis entre Atelier et Catalogue
et stock définissent les règles réutilisables de l'activité. Ce sont des
**référentiels métier** : ils ne sont ni des opérations à traiter aujourd'hui,
ni des réglages techniques globaux.

| Objet actuel | Nature | Proposition de présentation |
| --- | --- | --- |
| Gammes de location | Classification commerciale d'un instrument : Étude, Avancé, Professionnel ; sans prix | Référentiels métier → Location |
| Grilles de location | Règle tarifaire : famille, tailles, gamme et loyer mensuel | Référentiels métier → Tarifs de location |
| Prestations atelier | Catalogue de travaux réutilisables, tarif indicatif, TVA et description | Référentiels métier → Atelier |
| Lignes de devis enregistrées | Modèles commerciaux réutilisables : prestation, produit, location ou frais | Référentiels métier → Propositions d'atelier |
| Articles de stock | Quantités, seuils et mouvements réellement suivis | Reste une opération de stock, pas un paramètre |
| Instruments, dossiers atelier, locations | Parc, intervention ou contrat réellement en cours | Restent des opérations Atelier |

Cette proposition ne crée pas une section « Paramètres » concurrente et ne
change aucun modèle. Elle vise une zone secondaire de navigation, par exemple
**Référentiels métier**, qui reste contextualisée par le pack actif. Elle doit
être validée après observation de la fréquence réelle de modification de ces
objets : un référentiel régulièrement utilisé peut mériter un raccourci depuis
son opération, sans devenir pour autant une entrée principale.

## 4. Architecture produit et technique

### Structure livrée : cinq niveaux à ne pas confondre

1. **Organisation et isolation** : une organisation est l’unité de données,
   de droits et de configuration. Toutes les données métier sont isolées par
   `organization_id` ; une action sans contexte d’organisation est refusée.
2. **Modules canoniques** : `crm`, `appointments`, `quotes`, `marketing`,
   `luthier_catalog`, `workshop`, `rentals` et `inventory` sont activés par
   organisation, avec dépendances contrôlées. Un module porte une capacité ; il
   ne correspond pas nécessairement à une entrée du menu.
3. **Rubriques de navigation** : Suivi client, Activité commerciale, Atelier,
   Catalogue et stock, Marketing et Configuration organisent visuellement les
   capacités. Ce sont des chapeaux globaux, non des interrupteurs par
   organisation. Leur libellé peut être adapté dans les limites prévues ; leur
   identifiant et leur structure ne deviennent pas des données métier libres.
4. **Packs métier et présentation** : un pack propose un ensemble de modules
   et de libellés par défaut. Il adapte l’expérience sans dupliquer le noyau,
   les droits ou les données. Les objets propres — instrument, dossier atelier,
   location — vivent dans leur module métier.
5. **Configuration et connexions** : boîtes email, canaux entrants, CMS Bridge,
   Google Ads, Brevo et stockage objet sont des intégrations administrées. Elles
   ne sont pas des modules activables dans la navigation quotidienne.

La file **À traiter** est une couche d’expérience au-dessus des objets CRM ; ce
n’est ni une nouvelle table ni un quatrième type de donnée client. De même, un
site CMS connecté et Cremona restent deux applications séparées : le bridge
transmet un événement fiable, sans accès direct à la base de l’autre application
ni double édition.

### Registre canonique des capacités

La navigation ne doit pas être une liste de ressources Filament indépendantes. Elle doit provenir d'un registre de capacités stable. Chaque capacité possède :

- un identifiant technique immuable, par exemple `campaigns`, `quotes`, `appointments` ;
- un groupe canonique, par exemple `acquisition`, `relation_client`, `organisation` ;
- son ordre, ses dépendances et son état de disponibilité ;
- son libellé de secours et les éléments éditables de sa présentation ;
- les rôles et permissions nécessaires ;
- les intégrations qui la soutiennent, sans les exposer par défaut au menu.

Les organisations peuvent personnaliser les libellés et la visibilité explicitement prévues. Elles ne peuvent pas renommer un identifiant technique, contourner un droit ou changer le sens d'une donnée.

Le registre de modules et `settings.presentation` sont aujourd’hui deux couches
complémentaires. La prochaine consolidation devra les faire converger vers une
source de définition unique des groupes, éléments éditables et dépendances. Le
JSON ne doit pas devenir une structure métier arbitraire.

### Visibilité, droit et données : trois choses distinctes

- Masquer un module le retire de la navigation et des raccourcis ordinaires. Cela ne supprime aucune donnée et ne modifie aucun droit. Masquer une rubrique ne doit jamais être utilisé comme mécanisme de permission.
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
- Livrer l'entrée **Clients** unifiée : vues Particuliers et Professionnels, sans migration des personnes ni des entreprises et sans casser les URL ou droits existants.
- Comparer, à partir de parcours réels, la place de Demandes, Correspondances, Rendez-vous et Tâches avant toute nouvelle réorganisation de la navigation.
- Valider la rubrique secondaire **Référentiels métier**, distincte de Configuration de l'organisation, avant de déplacer les gammes et grilles de location, prestations atelier ou modèles de propositions.
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
4. Prévoir une projection contrôlée vers Maracuja CMS lorsque la donnée est publique. Pour chaque objet, décider une seule source de vérité : Cremona lorsqu'il est géré dans l'espace métier ; CMS lorsqu'un catalogue autonome existant reste propriétaire. Aucun objet n'est éditable dans les deux applications.
5. N'afficher Produits, Services ou Réalisations qu'une fois le premier flux complet livré, testé et documenté.

**Fin :** l'entreprise gère une offre réelle sans vocabulaire emprunté à un autre métier ni doublon avec le site public.

Le contrat complet des sites autonomes et connectés est documenté dans le
[Blueprint — sites autonomes et gestion connectée](blueprint-sites-connectes.md).

### D.1. Domaine Location Luthier

Le pilote Contempo confirme qu'une location n'est pas un simple attribut d'un
instrument ni une ligne de devis : c'est un domaine métier avec son cycle de
vie, ses preuves et ses conséquences sur le parc. L'état réellement livré et
la décision de conception sont consignés dans l’
[Audit — domaine Location Luthier](audit-location-luthier-2026-09-30.md).

**Décision :** conserver `Rental` comme racine historique et fonctionnelle de
la location, puis l'élever en domaine dédié. Ne pas créer un second `Contract`
concurrent, et ne pas accumuler assurance, mandat, signature, banque et
restitution dans une table unique.

Le cycle livré est : **Dossier en préparation → Dossier envoyé → Dossier
accepté → Instrument remis → Instrument restitué**. Les contrats de location et
d’assurance, lorsqu’elle est choisie, sont générés, archivés comme documents
privés immuables et acceptés par lien personnel. Cette acceptation interne est
traçable ; elle n’est pas présentée comme une signature électronique qualifiée.

La tarification mensuelle par grille (famille, taille, gamme) est figée dans la
location ; une exception par instrument reste explicite. À la remise et à la
restitution, Cremona crée respectivement les actions internes « encaissement à
mettre en place » et « encaissement à arrêter ou ajuster ». Il n’exécute aucun
prélèvement, ne stocke ni IBAN ni RUM, et n’intègre pas de banque.

**Suite :** valider un dossier réel avec Giovanni, de l’émission à la
restitution, puis corriger uniquement les règles révélées par cet usage. Les
contrats entreprises, prélèvements et signature qualifiée ne sont pas engagés.

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

### G. Paramètres, connexions et stockage privé

**But :** sortir les réglages techniques du travail quotidien sans les rendre opaques aux administrateurs autorisés.

- **Entreprise** : identité, fuseau, présentation et sites rattachés.
- **Utilisateurs** : membres, rôles, accès par organisation.
- **Connexions** : email/SMTP/IMAP, Google Ads, CMS Bridge, Brevo, agenda, OpenAI, GA4, Search Console et futurs prestataires.
- **Réglages** : préférences non sensibles.

Les documents métier confidentiels restent locaux par défaut. Une installation
peut activer explicitement le bucket privé Scaleway pour ce seul flux ; les
galeries et médias éditoriaux du CMS ne sont ni déplacés ni mélangés à ces
documents. Cremona contrôle alors le droit avant diffusion et ne livre jamais
d’URL publique ou présignée.

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

## 8. Priorité produit recommandée

1. **Valider la relation client réelle** : faire fonctionner la file À traiter
   avec la boîte Atelier Ivo, les demandes du site, les appels et les rencontres,
   sans automatiser de rapprochement avant observation.
2. **Valider les domaines déjà construits** : un dossier atelier/devis réel et
   une location Contempo complète avant toute extension documentaire, bancaire
   ou commerciale.
3. **Valider le bridge sur deux organisations** : soumission réelle,
   idempotence, reprise et absence de fuite ; basculer ensuite les sites un par
   un sans supprimer leurs historiques.
4. **Consolider le registre produit** : faire converger registre de modules,
   rubriques globales et présentation éditable avant d’ajouter de nouveaux packs.

Le chantier **Résultats** ne commence qu'après l'audit comparatif du module
Campagnes, des métriques Google Ads existantes, des futures données GA4 et de
l'attribution des demandes Cremona.
