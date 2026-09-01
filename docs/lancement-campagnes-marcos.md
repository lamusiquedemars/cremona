# Lancement campagnes — Marcos Túlio

> Référence unique au 24 août 2026. Aucun secret, mot de passe ou token ne doit être écrit dans ce document.

## Décision d’architecture

- **Marcos Túlio** reste un site autonome : formulaires, demandes et tableau marketing fonctionnent localement.
- **Cremona** est le centre de pilotage : organisations, campagnes, rapprochement des demandes et synthèse.
- **Google** reste propriétaire de ses plateformes : Ads diffuse, Analytics mesure les visites, Tag Manager pose les tags, Cloud Console autorise l’API.
- `ivo@maracujadigital.fr` est l’administrateur technique ; Marcos est propriétaire du compte publicitaire et de la facturation.

## Déjà construit et vérifié localement

- Organisation réelle `Marcos Túlio Advocacia`, site rattaché et membre Marcos créés dans Cremona.
- Brouillon de campagne `SEARCH | Criminal | Cuiabá` créé dans l’organisation Marcos ; il ne diffuse aucune annonce.
- Cloisonnement des organisations, secrets d’intégration chiffrés et API sécurisée entre site et Cremona.
- Tableau marketing autonome dans le site Marcos, synchronisation horaire prête, module générique dans le Starter.
- Suites de tests vertes le 24 août : Cremona 77, Marcos 202, Starter 189.

## État réel de la mesure Google

- Le **Starter** et **Marcos** possèdent déjà le mécanisme générique : lorsqu’un identifiant `GTM-…` valide est enregistré dans le site, il charge Google Tag Manager en respectant le consentement. Aucun identifiant réel n’est enregistré aujourd’hui, donc aucune balise Google ne se charge.
- **Maracuja CMS** ne possède pas encore ce mécanisme générique ; il devra recevoir le même module avant de pouvoir gérer les sites multi-sites de la même manière.
- **Cremona** centralise aujourd’hui les campagnes et Google Ads. Le plan directeur Acquisition prévoit aussi une connexion GA4 centralisée, limitée à la lecture de statistiques agrégées via l’API Google Analytics Data ; elle n’est pas encore développée. Elle est distincte de l’installation GTM dans les sites.

## À faire avant la première diffusion

### Google Ads — Marcos / Ivo

1. Marcos accepte la demande de rattachement au compte gestionnaire Maracuja Digital.
2. Marcos finalise lui-même la facturation de son compte Ads, s’il choisit d’utiliser l’offre promotionnelle. Ne jamais lancer une campagne sans son accord explicite.
3. Dans le compte Ads, préparer les mots-clés avec Keyword Planner, puis les annonces, les exclusions et le budget réel.

### Mesure du site — Marcos / Ivo

4. Créer une propriété Google Analytics 4 pour `marcostulioadvocacia.com.br` et ajouter Ivo comme administrateur.
5. Créer un conteneur Web Google Tag Manager pour ce site et ajouter Ivo comme administrateur.
6. Renseigner le seul identifiant `GTM-…` dans l’administration du site Marcos ; publier et tester le consentement, le formulaire, WhatsApp, téléphone et la demande de consultation.

### API Google Ads — Ivo

7. Dans le client OAuth Google Cloud déjà créé, vérifier l’URI de redirection de production :
   `https://cremona.maracujadigital.fr/integrations/google-ads/callback`
8. Attendre l’approbation de la demande Google Ads API Basic. Le niveau `test` actuel ne doit pas servir au compte réel Marcos.
9. Après approbation, relier le compte Google Ads client depuis
   `Configuration de l’organisation > Publicité` dans Cremona. Les secrets
   d’agence (developer token et application OAuth) restent dans
   l'infrastructure Maracuja ; ne jamais les saisir dans l'organisation Marcos.
   Autoriser Google avec `ivo@maracujadigital.fr` seulement si le flux le
   demande.

### Mise en production — technique Maracuja

10. Déployer les versions locales validées de Cremona et du site Marcos, exécuter leurs migrations et vider les caches.
11. Générer dans Cremona le jeton API propre au site Marcos et le renseigner seulement dans la configuration de production du site. Activer le planificateur horaire du site.
12. Vérifier une synchronisation de test sans donnée personnelle dans Analytics, puis seulement ensuite activer la campagne Ads.

### Contrôle obligatoire juste après activation

13. Dans Cremona, utiliser `Activer dans Google Ads`. Cette action active les
    groupes et annonces, puis la campagne ; ne pas modifier manuellement leurs
    statuts dans Google Ads pendant ce contrôle.
14. Dans Google Ads, vérifier que les raisons de statut ne contiennent pas
    `AD_GROUPS_PAUSED` ni `AD_GROUP_ADS_PAUSED`. `BIDDING_STRATEGY_LEARNING`
    est normal lors du démarrage.
15. Si une campagne active comporte encore des groupes ou annonces en pause,
    utiliser `Réparer la diffusion Google Ads` dans Cremona. Cette action ne
    modifie ni budget, ni dates, ni mots-clés, ni textes d'annonce.

## Règle de démarrage

La campagne reste en brouillon tant que les événements du site ne sont pas visibles et vérifiés. Le premier canal est Google Search. YouTube et TikTok servent d’abord au contenu organique ; leurs campagnes payantes sont une étape ultérieure.
