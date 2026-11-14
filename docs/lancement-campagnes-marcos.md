# Lancement campagnes — Marcos Túlio

> Référence de reprise au 1er septembre 2026. Aucun secret, mot de passe ou token ne doit être écrit dans ce document.

## Décision d’architecture

- **Marcos Túlio** reste un site autonome : formulaires, demandes et tableau marketing fonctionnent localement.
- **Cremona** est le centre de pilotage : organisations, campagnes, rapprochement des demandes et synthèse.
- **Google** reste propriétaire de ses plateformes : Ads diffuse, Analytics mesure les visites, Tag Manager pose les tags, Cloud Console autorise l’API.
- `ivo@maracujadigital.fr` est l’administrateur technique ; Marcos est propriétaire du compte publicitaire et de la facturation.

## Déjà construit et vérifié

- Organisation réelle `Marcos Túlio Advocacia`, site rattaché et membre Marcos créés dans Cremona.
- Brouillon de campagne `SEARCH | Defesa Penal | Mato Grosso` créé dans l’organisation Marcos : R$ 25/jour (référence R$ 760/mois), Brésil, Cuiabá/Várzea Grande/Mato Grosso, langue portugaise. Il ne diffuse aucune annonce.
- Cloisonnement des organisations, secrets d’intégration chiffrés et API sécurisée entre site et Cremona.
- Tableau marketing autonome dans le site Marcos, synchronisation horaire prête, module générique dans le Starter.
- Les trois groupes préparés sont : défense pénale et enquête ; arrestation, préventive et habeas corpus ; crimes économiques et colarinho branco. Les mots-clés négatifs documentaires/emploi/formation sont préparés dans chaque groupe.

## État réel de la mesure Google

- Le **Starter** et **Marcos** possèdent déjà le mécanisme générique : lorsqu’un identifiant `GTM-…` valide est enregistré dans le site, il charge Google Tag Manager en respectant le consentement. Le conteneur et la configuration de consentement sont déployés sur Marcos ; la balise GA4 est encore un brouillon GTM non publié.
- **Maracuja CMS** ne possède pas encore ce mécanisme générique ; il devra recevoir le même module avant de pouvoir gérer les sites multi-sites de la même manière.
- **Cremona** centralise aujourd’hui les campagnes et Google Ads. Le plan directeur Acquisition prévoit aussi une connexion GA4 centralisée, limitée à la lecture de statistiques agrégées via l’API Google Analytics Data ; elle n’est pas encore développée. Elle est distincte de l’installation GTM dans les sites.

## À faire avant la première diffusion

### Google Ads — Marcos / Ivo

1. Le rattachement au compte gestionnaire Maracuja Digital est actif. Marcos reste propriétaire du compte et de sa facturation.
2. Une campagne Smart créée directement par Marcos a été mise en pause : elle est conservée pour son historique, mais ne doit pas être utilisée pour ce lancement Search structuré.
3. Valider les mots-clés, annonces et exclusions du brouillon Cremona ; ne jamais lancer une campagne sans son accord explicite.

### Mesure du site — Marcos / Ivo

4. La propriété GA4 et le flux Web existent ; ajouter Marcos administrateur GA4 quand cela sera utile.
5. Le conteneur Web GTM existe ; ajouter Marcos administrateur GTM quand cela sera utile.
6. Prévisualiser puis publier la balise GA4 uniquement après accord explicite ; tester le consentement, le formulaire, WhatsApp, téléphone et la demande de consultation.

### API Google Ads — Ivo

7. Dans le client OAuth Google Cloud déjà créé, vérifier l’URI de redirection de production :
   `https://cremona.maracujadigital.fr/integrations/google-ads/callback`
8. Confirmer dans Google Ads API Center que le token a le niveau **Basic** ou **Standard**. Le niveau `test` ne doit pas servir au compte réel Marcos.
9. L’agent installe une seule fois les credentials centraux Maracuja sur la production Cremona, sans les afficher ni les stocker dans une organisation. Ensuite, depuis
   `Configuration de l’organisation > Publicité` dans Cremona. Les secrets
   d’agence (developer token et application OAuth) restent dans
   l'infrastructure Maracuja ; ne jamais les saisir dans l'organisation Marcos.
   Ivo ouvre **Autoriser Google Ads** et choisit `ivo@maracujadigital.fr`, le
   compte gestionnaire qui a déjà accès à Marcos. Cette action n’autorise que
   la lecture API ; elle ne crée ni ne lance de campagne.

### Mise en production — technique Maracuja

10. Vérifier une première synchronisation Ads en lecture seule dans Cremona. Elle n’importe que les métriques de campagnes dont la référence Google est déjà connue ; elle ne remplace pas la validation du brouillon Search.
11. Publier GA4/GTM et confirmer les événements de conversion, avec accord explicite.
12. Associer la campagne Search créée par Cremona à son identifiant Google, puis vérifier une synchronisation sans donnée personnelle avant toute activation.

### Contrôle obligatoire juste après activation

13. Dans Cremona, utiliser `Activer dans Google Ads`. Cette action active les
    groupes et annonces, puis la campagne ; ne pas modifier manuellement leurs
    statuts dans Google Ads pendant ce contrôle.
14. Dans Google Ads, vérifier que les raisons de statut ne contiennent pas
    `AD_GROUPS_PAUSED` ni `AD_GROUP_ADS_PAUSED`. `BIDDING_STRATEGY_LEARNING`
    est normal lors du démarrage.
## Règle de démarrage

La campagne reste en brouillon tant que les événements du site ne sont pas visibles et vérifiés. Le premier canal est Google Search. YouTube et TikTok servent d’abord au contenu organique ; leurs campagnes payantes sont une étape ultérieure.
