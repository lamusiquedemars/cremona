# Cremona

Cremona is the modular, multi-organization management suite developed by Maracuja Digital.

The public websites remain independent deployments based on Maracuja CMS Starter. Cremona provides the shared business layer: organizations, users, permissions, contacts, workflows, private documents, integrations, and vertical business packs.

## Foundation scope

The first milestone contains only the SaaS kernel:

- organizations and memberships;
- organization-scoped roles and permissions;
- active organization context;
- strict tenant isolation;
- a central Filament administration;
- MySQL-only local and test environments.

Business modules from Maracuja CMS Starter will be adapted only after this isolation layer is covered by tests.

## Acquisition Google

L’exécution reproductible côté site est décrite dans le runbook canonique du
Starter : `../maracuja-cms-starter/docs/google-acquisition-runbook.md`.
L’architecture, les credentials d’agence et le cycle de publication Cremona
sont décrits dans `docs/architecture-google-ads.md`. Ces deux documents et la
fiche `docs/acquisition-status.md` de chaque client sont obligatoires avant
toute création, activation ou diagnostic Google Ads.
