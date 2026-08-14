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
