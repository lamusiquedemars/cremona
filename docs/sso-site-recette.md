# Recette SSO — Sites connectés et Cremona

Cette matrice complète le pilote décrit dans `docs/sso-site-pilote.md`. Elle
ne demande ni changement de configuration, ni connexion à la production.

| Cas | Résultat attendu | Preuve automatisée |
| --- | --- | --- |
| Membre actif du site et de l'organisation | L'administration du site s'ouvre ; seuls les raccourcis autorisés sont proposés. | `SitePortalAuthenticationTest`, `SitePortalNavigationTest` |
| Non-membre, organisation ou site désactivé | Aucun profil, raccourci ni ticket n'est accordé. | `SitePortalOAuthClientTest`, `SitePortalAuthenticationTest` |
| Site connecté supplémentaire | Son identifiant, son secret et son organisation restent isolés du pilote Demo Maracuja. | `SitePortalAuthenticationTest`, `SitePortalLogoutTest`, `SitePortalSessionLinkTest` |
| Signature expirée ou altérée | La demande est refusée avant l'émission du ticket. | `SitePortalSessionLinkTest` |
| Ticket ouvert dans un autre navigateur, réutilisé ou arrivé après retrait d'accès | Une seule consommation est possible et les droits sont revérifiés à l'arrivée dans Cremona. | `SitePortalSessionLinkTest` |
| Changement de compte central | Une déconnexion du site ne ferme jamais la session Cremona d'un compte sans accès à l'organisation du site. | `SitePortalLogoutTest` |
| Retour et déconnexion fédérée | L'état court doit correspondre ; les deux sessions ne sont fermées qu'après le retour signé vers l'URL préconfigurée. | `CremonaSsoRedirectTest` (site), `SitePortalLogoutTest` (Cremona) |
| Visiteur public | L'accès public et les formulaires ne déclenchent aucun parcours SSO ni accès Cremona. | Vérification de routes : seuls `/admin` et les routes techniques protégées entrent dans le flux. |

## Recette manuelle avant adoption d'un site

1. Avec un membre actif des deux applications, se connecter sur
   `https://domaine-client/admin`, puis ouvrir chacun des raccourcis visibles.
2. Retirer un module ou le droit correspondant dans Cremona, se reconnecter
   au site et confirmer sa disparition du menu.
3. Tester un compte local sans appartenance Cremona, puis un membre Cremona
   sans compte local : aucune des deux situations ne doit créer d'accès.
4. Depuis un raccourci ouvert, utiliser « Se déconnecter de l'espace » et
   vérifier le retour vers la connexion du site ; un raccourci Cremona doit
   ensuite exiger une nouvelle connexion au site.

Les identifiants de test, secrets HMAC et URL de production ne sont jamais
placés dans cette recette ou dans Git.
