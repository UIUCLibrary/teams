# Copilot Instructions for the Omeka S Teams Module

This document provides a high-level overview of the Teams module, its purpose, architecture, and key development patterns. It also contains guidance for future work, which will focus on improving code quality.

## 1. Repository Purpose & Scope

The primary goal of this repository is to create the **Teams Module** for the Omeka S platform. This module provides granular, team-based access control over resources within an Omeka S instance.

**Core Functionality:**
-   Allows Global Administrators to create and manage "Teams".
-   Users can be assigned to one or more teams.
-   Each team is given access to a specific set of Omeka S resources (Items, Item Sets, Media, etc.).
-   Within a team, users are assigned a "Team Role" which defines their specific permissions (e.g., `can_update_resources`).
-   A user's ability to act upon a resource is determined by their team membership and their role within that team.

## 2. Architectural Overview

-   **`Module.php`:** The main entry point for the module. It registers services and attaches listeners to the Omeka S lifecycle. Core setup logic, including ACL rules, is initiated in the `onBootstrap()` method.
-   **Entities (`src/Entity/`):** Custom Doctrine entities define the core data model (Team, TeamUser, TeamRole, etc.).
-   **ACL & Assertions (`src/Acl/`):** The module's permission logic is implemented via the `Laminas\Permissions\Acl` component. The `InTeamAssertion` class is the heart of the system, containing the logic to verify a user's permissions for a given resource.

## 3. Key Development Pattern: Modifying ACL Rules

After extensive research, a specific, reliable pattern has been established for overriding Omeka S's default permissions.

**The Solution:** The `addAclRules()` method in `Module.php` iterates through all roles, resources, and privileges that need to be controlled. For each combination, it adds a conditional `deny` rule.

**This is the crucial insight:**
Instead of removing Omeka's `allow` rules, we leave them in place as a default "allow." We then use a more specific, conditional `deny` rule that only activates when our custom logic says it should. Since `deny` takes precedence over `allow` in a direct conflict, this correctly revokes access. This is accomplished using Omeka's `AssertionNegation` class to wrap our `InTeamAssertion`.

```php
// The correct, working pattern in addAclRules():

// Get the assertion that returns TRUE if a user has permission.
$inTeamAssertion = $this->getServiceLocator()->get(\Teams\Acl\InTeamAssertion::class);

// Wrap it in a negation. This new assertion now returns TRUE when the user does NOT have permission.
$denyAssertion = new \Omeka\Permissions\Assertion\AssertionNegation($inTeamAssertion);

// Add a DENY rule that is gated by the negated assertion.
// This rule will only deny access if the user fails the original InTeamAssertion check.
foreach ($rolesToControl as $role) {
    $acl->deny($role, $resourcesToControl, $privilegesToControl, $denyAssertion);
}
```

This pattern is the authoritative method for controlling permissions in this module.

## 4. Primary Goal for Future Work: Code Quality Improvement

The primary focus of future development is to refactor and improve the existing codebase. The module has grown organically, and now is the time to address technical debt and align it with modern best practices.

### Agent Instructions:

1.  **Look for Code Smells:** Proactively identify areas for improvement. This includes, but is not limited to:
   -   Large, complex methods that could be broken down (`Module.php` is a key candidate).
   -   Duplicate code blocks.
   -   Inconsistent naming or coding styles.
   -   Lack of adherence to SOLID principles.

2.  **Find Authoritative Examples:** When refactoring or adding features, do not assume the existing code in this repository is the best example. Instead, **look for authoritative and idiomatic implementation patterns in the core Omeka S codebase itself.** The framework's own code should be your primary guide for how to structure services, forms, controllers, and entities.

3.  **Prioritize Clarity and Maintainability:** Any proposed changes should make the code easier to read, understand, and maintain for future developers. Add comments where the logic is complex, and use clear, descriptive variable and method names.

Your main directive is not just to add features, but to leave the codebase in a significantly better state than you found it.