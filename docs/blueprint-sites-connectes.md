# Blueprint — sites autonomes et gestion connectée

> Statut : décision d'architecture validée pour le prochain cycle de construction.
>
> Dernière mise à jour : 8 septembre 2026.

## 1. But produit

Maracuja Digital propose des sites qui restent simples à exploiter lorsqu'ils
n'ont besoin que de présenter une activité, et qui peuvent devenir connectés à
un espace de gestion lorsqu'une entreprise en a réellement besoin. Le client ne
se voit pas vendre le nom interne **Cremona** : il utilise, si nécessaire, son
espace de gestion à ses couleurs.

Les applications restent séparées, avec leurs propres bases de données et leurs
propres déploiements. Une intégration n'autorise ni accès direct à la base de
l'autre application, ni double édition d'une même donnée.

## 2. Deux profils d'offre

| Profil | CMS Maracuja | Espace de gestion Cremona |
| --- | --- | --- |
| **Site vitrine autonome** | Site, contenu, médias, SEO, formulaires, emails et éventuellement liste locale des demandes | absent |
| **Site connecté à la gestion** | Site, contenu, médias publics, SEO, formulaire et communication marketing | demandes, contacts, emails directs, correspondances, tâches, rendez-vous, devis et modules métier |

Le formulaire public reste toujours une capacité du CMS : affichage, validation,
anti-spam, accusé de réception et disponibilité du site. Dans le profil
connecté, le traitement métier est changé : le CMS transmet l'événement à
Cremona et son écran local `Inquiries` est désactivé ou masqué. Il ne doit pas
rester deux files de demandes à traiter.

Un client peut passer du premier profil au second sans refaire son site : on
active le connecteur, on rattache le site à une organisation et on choisit la
politique de reprise des demandes locales existantes.

## 3. Demandes et correspondances : parcours canonique connecté

```text
Formulaire CMS ─┐
Email direct ───┼→ Cremona : contact, demande, conversation et suivi
Téléphone ──────┤
Rencontre ──────┘
                    ↓
              tâches, rendez-vous, devis, dossiers métier
```

- Le formulaire appelle une API Cremona versionnée par une outbox rejouable ;
  la clé d'idempotence empêche un doublon lors d'une nouvelle tentative.
- Cremona crée ou rapproche le contact, puis crée la demande et le message
  initial avec la source exacte (`site`, `email`, `telephone`, `rencontre`,
  etc.).
- Les emails écrits directement à l'entreprise sont relevés par la boîte IMAP
  de l'organisation dans Cremona ; les réponses partent de cette même boîte.
- Un lien « Gestion commerciale » dans l'admin CMS ouvre l'organisation
  Cremona concernée. Il n'encastre pas une seconde application dans Filament.
- La connexion unique entre CMS et gestion est une évolution explicite ; elle
  ne doit pas retarder le premier flux, qui peut ouvrir l'espace sécurisé par
  lien distinct.

## 4. Propriété des données

| Donnée | Propriétaire | Projection autorisée |
| --- | --- | --- |
| Pages, actualités, SEO, thème, médias publics | CMS | état ou lien vers Cremona |
| Formulaire public, validation, anti-spam | CMS | événement de demande vers Cremona |
| Demande, contact CRM, conversation, email direct, tâche, rendez-vous, devis | Cremona | lien ou résumé non éditable dans le CMS |
| Consentement newsletter, segments et campagnes Brevo | CMS + Brevo | signal ciblé depuis Cremona après consentement explicite |
| Devis envoyé, facture, paiement | Pennylane | identifiant, PDF/lien et statut dans Cremona |
| Catalogue métier | **un seul propriétaire choisi par objet** | projection publique ou commerciale en lecture seule |

Le CMS et Cremona ne synchronisent jamais l'ensemble de leurs contacts. Un
événement transmet seulement ce qui est nécessaire à son but : une demande du
site ouvre un contact CRM ; un consentement marketing explicite peut créer ou
mettre à jour un abonné Brevo.

## 5. Offre, stock et métier

Le noyau Cremona contient les capacités réutilisables : organisations, CRM,
correspondances, tâches, rendez-vous, devis et modèles de lignes de devis. Il
ne contient pas une table universelle `Products`.

Un pack métier ajoute les objets dont le comportement est réellement propre au
métier. Le premier est le pack **Luthier** : instruments physiques, articles de
stock, prestations, dossiers atelier, locations, essais et leurs statuts.

Pour Contempo, les instruments à vendre ou louer sont des unités physiques
individuelles ; les cordes et accessoires sont des articles de stock ; un
reméchage ou un ajustement est une prestation. Une tâche n'est facturable que
si l'utilisateur la qualifie et la transforme explicitement en ligne de devis :
« appeler un fournisseur » ne peut pas le devenir par accident.

Le propriétaire du catalogue dépend du cas :

- **Atelier Ivo, état actuel** : Arcus/CMS reste propriétaire des archets et de
  leur catalogue public ; Cremona référence et capture les informations utiles
  au suivi commercial sans maintenir un second catalogue éditable.
- **Contempo, cible connectée** : le pack Luthier/Cremona est propriétaire du
  parc, du stock et des prestations ; le CMS reçoit la sélection publiable,
  son statut public et les médias autorisés.

## 6. Briques versionnées, sans copie de dossiers

La cible est une installation guidée et mise à jour par version, non une copie
de fichiers entre projets :

1. le CMS conserve ses modules natifs versionnés, dont `ContactForm`, `Gallery`
   et `Audience` ;
2. un module optionnel **Cremona Bridge** apporte la configuration du lien,
   l'outbox, la signature des appels, les reprises et le lien de gestion ;
3. Cremona active ses capacités par organisation et conserve les secrets de
   connexion chiffrés ;
4. le pack Luthier possède ses migrations, politiques, tests et version propre ;
5. chaque installation passe par une commande contrôlée, avec sauvegarde et
   migration additive, jamais par copier-coller ou écrasement de données.

Dans l'immédiat, ces briques restent des modules Laravel versionnés dans les
applications où elles vivent. Elles ne seront extraites en packages Composer
privés qu'après une seconde installation réelle, lorsque leur contrat sera
stabilisé. L'expérience d'installation doit néanmoins être la même dès le
départ : activation explicite, contrôle des prérequis, migrations et tests.

## 7. Ordre de réalisation

1. Écrire les contrats du bridge CMS ↔ Cremona et les scénarios de reprise :
   [contrat du bridge](contrat-bridge-cms-cremona.md).
2. Tester le bridge en local avec deux organisations et deux sites simulés,
   sans fuite ; puis le déployer de façon limitée sur le premier site de
   production et le vérifier sur des données réelles.
3. Basculer le traitement des demandes d'Atelier Ivo sans supprimer son
   catalogue Arcus ni ses historiques locaux.
4. Construire le socle commercial réutilisable dans Cremona : modèles de lignes
   de devis, références externes et statut de synchronisation Pennylane.
5. Construire et tester le pack Luthier sur Contempo : parc, stock,
   prestations, dossier atelier, devis et location.
6. Ajouter la projection publique Contempo vers le CMS, puis seulement importer
   les données Dynamics et raccorder Pennylane.

Chaque étape a un plan de migration, un test de reprise et un retour arrière
documenté. Aucun site existant n'est basculé ou nettoyé par une suppression
automatique.
