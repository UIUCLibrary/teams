# Service Layer Pattern — Rationale and Guide

## The Problem: A 2,778-Line Monolith

`Module.php` is the heart of the Teams module. Every Omeka S module has one, and for
small modules that is fine. Teams is not a small module. At the time this document was
written, `Module.php` contained approximately **60 public methods** and nearly
**2,800 lines** in a single class. The methods covered:

- ACL rule wiring
- Query filtering
- Event-driven entity sync (items, sites, users, assets, resource templates)
- View helpers and form injection
- Configuration handling
- Admin UI display logic

When everything lives in one place, the following problems compound over time:

1. **No single concern is traceable.** To understand how site permissions work, a reader
   must grep for scattered method calls and follow a chain through `Module.php`,
   `UpdateController.php`, and form events — none of which have a shared namespace or
   naming convention.

2. **Logic cannot be reused without copy-paste.** Because business logic is embedded in
   event-handler methods (which receive raw `Event` objects), it cannot be called from
   another entry point (e.g., a CLI command, a background job, or a second controller)
   without duplicating the code.

3. **Testing is impossible in practice.** An event handler that calls
   `$this->getServiceLocator()` is tightly coupled to the Laminas service container.
   There is no way to instantiate just the relevant behaviour for a unit test without
   bootstrapping the entire framework.

4. **The class grows without bound.** Every new feature becomes a new method on
   `Module.php` because there is no obvious alternative home for it. This is the
   definition of the *Big Ball of Mud* anti-pattern.

---

## Why Service Layer Is the Right Pattern Here

### What it is

The **Service Layer** pattern (Fowler, *Patterns of Enterprise Application Architecture*,
2002) draws a boundary between the application's *entry points* (HTTP controllers, event
handlers, CLI commands) and its *domain logic* (business rules, entity manipulation,
cross-cutting sync). Domain logic is placed in dedicated **service classes** that are
injected into entry points as dependencies.

In Laminas/Omeka S this is spelled out concretely:

```
Entry point           Service Layer              Domain / ORM
─────────────────     ────────────────────────   ──────────────────
Module.php            SitePermissionManager      Doctrine EntityManager
UpdateController      AclRuleManager             Omeka\Entity\*
                      (future) ItemSyncManager   Teams\Entity\*
```

### Why not one of the alternatives?

**Repository pattern alone** — Repositories handle query building, not business rules.
They are the right home for "give me all TeamUsers for team X" but not for "given a
TeamUser change, update the matching SitePermissions". Mixing rules into repositories
produces the same coupling problem in a different class.

**Fat controller** — Putting logic in controllers makes it unreachable from event
handlers, and vice versa. The existing code already shows the pain: `updateUserSites`
is defined on `Module.php` and called from both `siteCreate` and `siteUpdate` purely
because controllers cannot directly call `Module` methods.

**Traits on Module** — PHP traits are a copy-paste mechanism, not a boundary. A trait
on `Module.php` still has access to `$this->getServiceLocator()`, still cannot be
constructed independently, and still cannot be tested in isolation.

**Doctrine lifecycle listeners** — These are appropriate for generic, entity-level
concerns (timestamps, soft-delete flags). They are inappropriate for business logic
that is specific to the Teams module, because they introduce a hidden dependency
between the ORM layer and module-specific rules that would survive even if the module
were disabled.

**Service Layer** fits because:

- Services receive their dependencies through the **constructor**, not through a global
  service locator. This is standard Laminas Dependency Injection and makes every
  dependency explicit and mockable.
- A service class has a **single, nameable concern**. `SitePermissionManager` does
  exactly one thing: keep Omeka site user permissions in sync with Teams role
  assignments. A reader can open the file and understand its purpose in two minutes.
- The same service can be **called from any entry point**: an event handler in
  `Module.php`, a controller action, a background job, a future REST endpoint, or a
  unit test.
- Services are registered in the **Laminas service manager** via factory classes.
  This is the established Omeka S convention for injectable objects — the same mechanism
  used for API adapters, form elements, and authentication — so no new conventions need
  to be introduced or learned.

### Relationship to Omeka S conventions

Omeka S itself follows this pattern. The core `application/Module.php` is thin; heavy
logic lives in dedicated classes (`SiteAdapter`, `ItemAdapter`, `Acl`, etc.). Official
Omeka S modules such as [CSVImport] and [BulkImport] extract complex processing into
service classes registered through factories. Adopting the same pattern keeps the Teams
module aligned with the ecosystem and makes it easier for Omeka-familiar developers to
contribute.

---

## The Concrete Example: `SitePermissionManager`

### Before

`Module.php` contained `updateUserSites()`, a method that:
- called `$this->getServiceLocator()` to fetch `Omeka\EntityManager` and
  `Omeka\Settings\User` — two separate service-locator calls buried inside the method
  body
- was duplicated at three call sites inside `Module.php` and implicitly depended on
  those call sites knowing to call `Module::updateUserSites()` instead of having
  access to the logic directly
- could not be called from `UpdateController` without going back through the module
  event system

The new feature (issue #189 — auto-generate site permissions) would have required
adding *more* methods to `Module.php` with the same problems, and wiring them from
`UpdateController` was impossible without adding a second copy of the same code.

### After: `Teams\Service\SitePermissionManager`

`SitePermissionManager` is a plain PHP class:

```php
class SitePermissionManager
{
    public function __construct(
        EntityManager $entityManager,
        UserSettings  $userSettings
    ) { … }

    public function syncSitePermissionsForUser(int $userId, int $teamId): void { … }
    public function removeSitePermissionsForUser(int $userId, int $teamId, ?int $siteId = null): void { … }
    public function syncSitePermissionsForTeamOnSiteAdded(int $teamId, int $siteId): void { … }
    public function removeSitePermissionsForTeamOnSiteRemoved(int $teamId, int $siteId): void { … }
    public function updateUserDefaultSites(int $userId): void { … }
    public function updateAllUserDefaultSites(): void { … }
}
```

`SitePermissionManagerFactory` creates it:

```php
class SitePermissionManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, …)
    {
        return new SitePermissionManager(
            $container->get('Omeka\EntityManager'),
            $container->get('Omeka\Settings\User')
        );
    }
}
```

`config/module.config.php` registers it like any other Laminas service:

```php
'service_manager' => [
    'factories' => [
        SitePermissionManager::class => SitePermissionManagerFactory::class,
    ],
],
```

**`Module.php`** now delegates in one line:

```php
public function updateUserSites($user_id)
{
    $this->getServiceLocator()->get(SitePermissionManager::class)
        ->updateUserDefaultSites($user_id);
}
```

**`UpdateController`** receives the service through its constructor:

```php
public function __construct(
    EntityManager          $entityManager,
    SitePermissionManager  $sitePermissionManager
) { … }
```

And calls it directly after mutating team membership:

```php
$this->sitePermissionManager->syncSitePermissionsForUser($user_id, $team_id);
```

---

## Design Decisions within `SitePermissionManager`

### Multi-team safety

A user can belong to multiple teams that share an Omeka site. Naively assigning the
role dictated by one team could silently downgrade a privilege granted by another team.
`SitePermissionManager` uses `resolveHighestRole()` to ensure that the role stored on
the `SitePermission` entity is always the *highest* role granted by any of the user's
current team memberships. When a membership is removed, `getHighestRoleFromOtherTeams()`
recalculates from the remaining memberships before deciding whether to downgrade or
remove the `SitePermission` entirely.

### Role mapping

Omeka S defines three site-level roles: `viewer`, `editor`, `admin`. The Teams module
maps:

| TeamRole condition           | Omeka site role              |
|------------------------------|------------------------------|
| `can_add_site_pages = true`  | `SitePermission::ROLE_ADMIN` |
| `can_add_site_pages = false` | `SitePermission::ROLE_VIEWER`|

`ROLE_EDITOR` is not currently produced by the Teams module because TeamRole does not
have a finer-grained "can edit but not administer" concept. If that is added later,
only `SitePermissionManager` needs to be changed.

### Flush discipline

Each public method calls `$em->flush()` once at the end, after all entity mutations
for that operation are complete. This avoids partial writes and reduces round trips.
The exception is `updateAllUserDefaultSites`, which delegates to `updateUserDefaultSites`
per user; the settings API (`UserSettings::set`) has its own persistence and does not
require explicit flushes.

---

## The Broader Roadmap

`SitePermissionManager` and `AclRuleManager` are examples of the pattern. They are
not the finish line. The same extraction should be applied, incrementally, to other
cohesive groups of methods currently living in `Module.php`:

| Candidate service            | Methods to extract                                                           |
|------------------------------|------------------------------------------------------------------------------|
| `ItemSyncManager`            | `updateItemSites`, `itemCreate`, `itemUpdate`, `itemDelete`, `itemBatchCreate`|
| `TeamQueryFilter`            | `filterByTeam`, `getTeamContext`, `getOrphans`                               |
| `ResourceTemplateSyncManager`| `resourceTemplateCreate`, `resourceTemplateUpdate`                           |
| `AssetSyncManager`           | `assetCreate`, `assetUpdate`                                                 |

Each extraction follows the same four-step recipe:

1. Create a service class in `src/Service/` with constructor-injected dependencies.
2. Create a factory in `src/Service/` that pulls those dependencies from the container.
3. Register the factory in `config/module.config.php`.
4. Replace the body of the `Module.php` method(s) with a single-line delegation call.

No existing public API or event wiring needs to change. `Module.php` retains its
methods as thin delegators, so callers (including third-party modules that may be
listening to Teams events) are unaffected.

---

## Summary

| Concern              | Before                         | After                          |
|----------------------|--------------------------------|--------------------------------|
| Where logic lives    | `Module.php` method bodies     | Named service class            |
| Dependencies         | `$this->getServiceLocator()`   | Constructor injection          |
| Reuse across entry points | Copy-paste or impossible  | Call the service directly      |
| Testability          | Requires full framework boot   | Plain PHP instantiation        |
| Discoverability      | Grep for scattered methods     | Open the service class         |
| Aligned with Omeka S | Partially                      | Fully (same factory pattern)   |
