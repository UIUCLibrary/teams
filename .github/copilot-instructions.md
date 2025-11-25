# Copilot Agent Instructions for Teams Module

## Repository Overview

**Teams** is an Omeka S module that enables global administrators to organize work by controlling resource visibility and user permissions through teams and roles. Users see only resources relevant to their team and role assignments.

### Core Functionality
- **Team-based Organization**: Users, sites, and resources (items, media, assets, item sets, resource templates) are organized into teams
- **Team Roles**: Additive/subtractive permission overlays on top of core Omeka S roles, allowing fine-grained control within teams
- **Visibility Control**: Users only see resources associated with their current team
- **Global Admin Controls**: Global toggles for site creation and user management permissions
- **Soft Delete**: Resources shared across teams move to 'Trash' rather than being permanently deleted
- **Safe to Disable**: Module can be turned off without breaking the Omeka installation

## Technical Details

### Repository Type
Omeka S Module (PHP-based)

### Languages and Frameworks
- **PHP**: Primary language for Omeka S modules
- **Laminas Framework** (formerly Zend): Routing, forms, dependency injection
- **Doctrine ORM**: Database entity management and queries
- **HTML/PHTML**: View rendering and templates
- **JavaScript**: Asset interactions (limited use)

### Repository Structure

#### Root Directory
```
.
├── Module.php              # Main module class and event handlers
├── README.md               # User-facing documentation
├── LICENSE                 # Apache 2.0 license
├── config/                 # Configuration files
│   ├── module.config.php   # Routes, navigation, services, ACL
│   └── module.ini          # Module metadata
├── src/                    # Source code
│   ├── Api/                # API adapters
│   ├── Controller/         # Request handlers
│   ├── Entity/             # Doctrine entities
│   ├── Form/               # Laminas form definitions
│   ├── Job/                # Background jobs
│   ├── Mvc/                # MVC customizations
│   ├── Service/            # Service factories
│   └── View/               # View helpers
├── view/                   # Template files
│   ├── teams/              # Team-specific views
│   └── common/             # Shared templates
├── asset/                  # CSS, JavaScript, images
└── data/                   # SQL migrations and data files
```

#### Key Files
- **Main Configuration**: `config/module.config.php` - Routes, navigation, services, ACL resources
- **Forms**: `src/Form/ConfigForm.php`, `src/Form/Element/RoleSelect.php`, etc.
- **Entities**: `src/Entity/Team.php`, `src/Entity/TeamUser.php`, `src/Entity/TeamResource.php`, etc.
- **API Adapters**: `src/Api/Adapter/TeamAdapter.php` and related adapters
- **Controllers**: `src/Controller/UpdateController.php`, `src/Controller/AddController.php`, etc.
- **Views**: `view/teams/index/resources.phtml`, `view/teams/partial/team-form-no-id.phtml`, etc.

## Code Quality and Standards

### Coding Standards
**Always seek improvement over existing code.** Follow these principles:

1. **Refactor and Clean**: Remove code smells, improve structure, prefer clean idiomatic Omeka S code—even if not strictly required for the task
2. **Follow Omeka S Patterns**: Emulate best practices from:
   - Main Omeka S repository: [omeka/omeka-s](https://github.com/omeka/omeka-s)
   - Official Omeka S modules: [omeka-s-modules](https://github.com/omeka-s-modules/)
   - Official Omeka S themes: [omeka-s-themes](https://github.com/omeka-s-themes/)
3. **Treat Local Examples as Non-Authoritative**: While this repository contains working code, it should not be considered a canonical reference for best practices. Always prefer official Omeka S patterns.
4. **Follow the Boy Scout Rule**: Leave code better than you found it

### PHP Standards
- Follow PSR-12 coding standards where applicable
- Use proper type hints and return types
- Document complex logic with clear comments
- Prefer dependency injection over global state
- Use Omeka S service manager patterns

### Laminas/Zend Patterns
- Forms should extend `Laminas\Form\Form` or appropriate Omeka S base classes
- Use form fieldsets for reusable form components
- Controllers extend `Laminas\Mvc\Controller\AbstractActionController`
- Use view helpers for reusable view logic

### Doctrine Best Practices
- Entities should be in `src/Entity/` namespace
- Use annotations for ORM mapping
- Prefer DQL or QueryBuilder over native SQL
- Follow repository pattern where appropriate

## Build, Validation, and Testing

### Current State
**No explicit build steps, CI/CD pipelines, or automated validation are currently present** in this repository.

### What This Means for Agents
- **Skip automatic build/validate steps** unless explicitly directed by task requirements
- **No linting, testing, or compilation** commands to run by default
- **Manual validation** may be performed by viewing files and inspecting code
- **Future improvements**: Agents may recommend or prototype build/test infrastructure as part of code quality improvements

### Testing in Omeka Context
- Module is tested by installing in an Omeka S instance
- Functional testing requires full Omeka S environment
- No unit tests currently exist in the repository

## Architecture and Design

### Critical Design Constraint
**The module must be safely turn-off-able**: Users should be able to disable the Teams module without breaking their Omeka S installation. This means:
- Sync relationships with core Omeka features where they exist (e.g., item-site relationships, user default sites)
- Avoid hard dependencies that would cause errors when module is disabled
- Gracefully degrade functionality

### Key Architectural Patterns

#### MVC Structure
- **Models**: Doctrine entities in `src/Entity/`
- **Views**: PHTML templates in `view/teams/`
- **Controllers**: Action controllers in `src/Controller/`

#### Routing
Configured in `config/module.config.php` using Laminas routing:
- Literal routes for fixed URLs
- Segment routes for parameterized URLs

#### Forms
- Form classes in `src/Form/`
- Form elements in `src/Form/Element/`
- Form factories in `src/Service/Form/`
- Fieldsets for reusable form components

#### API Layer
- Custom API adapters extend Omeka S adapter classes
- Located in `src/Api/Adapter/`
- Provide CRUD operations for team entities

#### Event System
- Module hooks into Omeka S events via `Module.php`
- Event handlers modify behavior (filtering queries, checking permissions, etc.)

### Database Entities
- `Team`: Core team entity
- `TeamUser`: User-team-role relationships
- `TeamResource`: Resource-team relationships
- `TeamSite`: Site-team relationships
- `TeamRole`: Custom role definitions
- `TeamResourceTemplate`, `TeamAsset`: Additional resource type mappings

## Known Issues and TODOs

### TODO Items in Codebase
The codebase contains numerous TODO markers indicating areas for improvement. **Copilot agents should flag, document, and prioritize remediation** of these issues:

#### Controller Refactoring Needed
- `src/Controller/UpdateController.php`: Multiple TODOs for refactoring form handling, simplifying data retrieval, and cleaning up view data
- `src/Controller/AddController.php`: Form creation and asset handling need improvement

#### API Improvements
- `src/Api/Adapter/TeamAdapter.php`: Should use Doctrine native queries instead of custom query building; sorting needs implementation
- `src/Api/Adapter/AbstractTeamEntityAdapter.php`: Missing method implementations

#### Entity Completion
- `src/Entity/TeamResource.php`: Missing `getResourceName()` implementation

#### General Patterns
- Many `//TODO (refactor)` comments indicate code that works but needs structural improvement
- Multiple `//TODO (emulate)` comments mark patterns that should be copied elsewhere
- Look for opportunities to consolidate duplicated code

### Code Quality Flags
When working in this codebase, watch for and address:
- Repeated patterns that could be extracted into reusable methods
- Complex controller actions that could be simplified by moving logic to services
- Missing error handling
- Unclear variable names or magic values
- Opportunities to use Omeka S/Laminas built-in features instead of custom code

## Agent Best Practices

### Trust This Document
**This onboarding document is your primary reference.** Only search or explore the codebase when:
- This document is incomplete or unclear on a specific topic
- You need specific implementation details not covered here
- You're looking for existing patterns to emulate

### Boy Scout Rule
**Always leave the repository better than you found it:**
- Fix code smells when you encounter them
- Improve naming and structure
- Add missing documentation
- Consolidate duplicated code
- Document your improvements in commit messages

### Recommend Improvements
When working on tasks, consider recommending:
- Introduction of automated testing
- Code linting and style checking
- CI/CD pipeline setup
- Documentation improvements
- Refactoring opportunities

### Respect the Omeka S Ecosystem
- This module operates within Omeka S and must respect its conventions
- Changes should be compatible with Omeka S upgrade paths
- Follow Omeka S security best practices
- Respect Omeka S event system and service manager patterns

## Living Document

**This is a living document** that should be maintained collaboratively. As the repository evolves:
- Update this file to reflect new patterns and practices
- Add new sections as the architecture changes
- Remove outdated information
- Keep examples current

### Meta Notes
If a file named `meta-notes-on-building-coding-agent-instructions.txt` exists in this repository, **ignore it**. That file is for internal documentation purposes and not relevant to code development tasks.

## Quick Reference

### Common Tasks

#### Adding a New Entity
1. Create entity class in `src/Entity/`
2. Add Doctrine annotations for ORM mapping
3. Create API adapter in `src/Api/Adapter/`
4. Register adapter in `config/module.config.php`
5. Update database schema in `data/` if needed

#### Adding a New Form
1. Create form class in `src/Form/`
2. Create form factory in `src/Service/Form/`
3. Register factory in `config/module.config.php` service manager
4. Use form in controller action
5. Create view template in `view/teams/`

#### Adding a New Route
1. Add route configuration to `config/module.config.php`
2. Create controller action method
3. Create view template
4. Update navigation if needed (in `module.config.php`)

### Useful Omeka S Resources
- [Omeka S Developer Documentation](https://omeka.org/s/docs/developer/)
- [Omeka S GitHub Repository](https://github.com/omeka/omeka-s)
- [Laminas Documentation](https://docs.laminas.dev/)
- [Doctrine ORM Documentation](https://www.doctrine-project.org/projects/orm.html)

---

**Remember**: Code quality matters. Take pride in making this codebase better with each change.
