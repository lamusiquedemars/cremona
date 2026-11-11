# Cremona

Cremona is the modular, multi-organization management suite developed by Maracuja Digital.

The public websites remain independent Maracuja Site deployments. Cremona provides the shared business layer: organizations, users, permissions, contacts, workflows, private documents, integrations, and vertical business packs.

## Déploiement

La procédure et le script LWS actuels sont en quarantaine : ne pas les exécuter
ni reprendre leurs accès/destinations avant audit et validation. Voir le
[registre central des documents et outils retirés/en quarantaine](../maracuja-control/docs/documents-a-retirer.md)
et la [politique commune](../maracuja-control/docs/deploiement-multi-sites.md).

## Foundation scope

The first milestone contains only the SaaS kernel:

- organizations and memberships;
- organization-scoped roles and permissions;
- active organization context;
- strict tenant isolation;
- a central Filament administration;
- MySQL-only local and test environments.

Business modules are extracted through Maracuja Site Core only after this isolation layer is covered by tests.

## Acquisition Google

L’architecture, les credentials d’agence et le cycle de publication Cremona
sont décrits dans `docs/architecture-google-ads.md`. Ces deux documents et la
fiche `docs/acquisition-status.md` de chaque client sont obligatoires avant
toute création, activation ou diagnostic Google Ads.
