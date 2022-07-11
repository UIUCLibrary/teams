# Teams

## Overview

### Purpose
Teams helps global admins organize work by controlling what resources users see and what they can do with those resources. 

### Methods

#### Teams
Users, sites and resources (items, media, assets, item sets and resource templates) are attached to teams. When logged in, users only see the sites and resources that are part of their current team. 

#### Team Roles
Within a team, a user is given a team role. These establish additional restrictions on user permissions on top of the permissions granted by the core Omeka-s user profile. It also allows a single user to have different roles and permissions in different teams to which they belong.

Team roles are defined by the global administrator and global admins can establish as many roles with different permissions as you need. Each role is configurable by selecting whether or not it can:

    Add Team Members
    Add Resources 
    Modify Resources
    Delete Resources    
    Modify Team Sites

A global toggle determines who can make new sites and who can add new users to the Omeka S application (more on that below).

#### Mechanics of permission 
A user’s permissions within a team are determined by their Team role. However, permissions in Team roles are subtractive, not additive, to the permissions created by their core Omeka role (e.g. Site Admin, Editor, Researcher, etc.). If the permissions of Teams and Omeka were a Venn diagram, users can only do the things in the center of the diagram—-those things permitted by both their core Omeka and their team role. 

Therefore, if a user with the core Omeka role of “Author” is given a team role with the ability to modify the team’s sites, that user will be unable to do so because “Authors” in Omeka can not modify sites. The global admin would need to either change that user’s core Omeka role, or add them via the site’s User Permissions page. 

While Teams will not grant extra privileges, it does help you provide finer-grained control over where they can exercise those privileges. A user who has the ability to add and delete items through their core Omeka role, a “Site Admin” for example, who also has a team role that allows them to add and delete items can only add and delete items in the context of their current team. Items belonging to another team are inaccessible to them. 

#### Global Admin Team Permissions
Global admins will see an interface similar to standard users (e.g., the list of items displayed for admin/items is limited to their current team). However, they won't be stopped from making changes on items/pages that they navigate to manually. This is to assist in troubleshooting by allowing admins to get a sense of what users are seeing without preventing them from making changes they feel are necessary.

### Deleting

When users delete resources, they are not permanently deleted to prevent one team member deleting a resource that is shared among more than one team. Instead, when clicking delete that resource is removed from the user’s current team. Global Admins can see resources that no longer belong to any teams in the Trash section of the Admin menu.


### Global Toggles

We have found that global admins may want to change some permissions globally or often. It would be cumbersome to make these changes through Team Roles, so they are instead part of a global toggle in the Teams configuration page. Currently, the only global toggle changes who can make new sites and who can add new users to Omeka. Admins can turn on or off the ability to make new sites for Site Admins and Editors.

#### Use case for global toggles

You may not want to allow users to generally make new sites, but find that when hosting a class tutorial granting that ability speeds up your prep work because you don't have to make a stub site for all your participants: You can endable site creation before the workshop and disable it after. 

### Versioning and Release Naming Conventions
To make finding the right release more convenient, releases are named according to the Omeka version they are tested against. So a release named v3.1.0-0 was tested against Omeka version 3.1.0, and this represents the initial release of Teams tested against that version. 

### Warning 
Use at your own risk. 

It’s always recommended to back up your files and your databases and to check your archives regularly so you can roll back if needed. Backup before installing, uninstalling or upgrading the Teams module. 

Test Teams in a development environment before installation and before any upgrades. 

## Common Workflows

### Overview:
These instructions cover the setup for some common use cases. The basic workflow is covered in Installing on an Existing 3.x Installation, with some additional details for other situations. Version 2.x will no longer be supported, so if you don't have Teams installed already, it is recommended that you upgrade to 3.x first. 

### Upgrading from 2.x to 3.x
If you were using Teams in a 2.x Omeka environment, updating to 3.x will automatically generate item-site relationships and default site settings for users based on current team configuration. No additional migration steps are needed beyond clicking "update" in the module page. The current recommended release for Omeka 3.0 and 3.1 is v3.1.0-1. 

### Installing on an Existing 3.x Installation
If you are adding Teams to an existing installation, you will want to plan a time when your sites can experience some downtime. Because Teams filters results for the front and back end interfaces, search results for public facing sites will not function properly until that site and the resources associated with it are added to the same team. Similarly, Omeka users will not interact with items, item sets, resource templates or sites on the back end until they are added to a team with thouse resources.

1. Before Installation 
    * You are going to want an easy way to bulk add resources to teams. Currently, admins can bulk add resources to a team based on item sets or owners. The easiest way to prepare for Teams is to make sure that all of the items that will belong to a team are in an item set. If you need another method, like via a search query or through existing item-site relationships, contact the developer or open an issue. 
    * Back up your installation and ideally test in a development environment. Teams will modify settings like a user's default site and an item's sites.
    * Consider making a no permissions role. This will allow new users to observer the items, resource templates and sites of existing teams without being able to make any changes. 
    
2. Decide on Team Roles
    * First decide what your individual users will be tasked with, and make roles that include only the permissions for that activity. So, if you will have students remediating metadata, but not building website, you could build a role called Metadata Specialist with the permission "Can modify in team repository". 
    * Now, keep in mind that Teams *only* takes permissions away and doesn't grant any permissions that a user isn't already granted by their core Omeka Role, defined in their user profile. So you must make sure that you are starting with a core Omeka Role for the user that permits the kinds of tasks that they will be doing. E.g., only Supervisors can edit sites that they don't own. So, if you are making a user who will edit a site, make sure they have a core Omeka Role of at least Supervisor, and a team role that includes the "Can modify linked site" permission. 
    * Make sure that you include a 'Superuser' role that you can assign to the global admin. In our workflows, it has generally been easier if the global admin is a member of all teams with all privileges.

3. Create teams
    * The most common configuration is one site per team, one team per site. If this fits your needs, it is easiest to name the team after the site or the group that publishes it. Then, in the same form, you will add all the users that will work on that project; all the items associated with that project; and finally, the site itself.
    * For other configurations: resources, sites and users can all belong to multiple teams. So, if one person will build all the web pages, they can be added to all the teams.
    * Unless you have a compelling reason not to, we think it is best to add your global admin in charge of user support and troubleshooting to each team. 

4. Decide if you want Editors and Supervisors to be able to make new sites. This is not a permission built into roles. Rather, it can be toggled on or off for all Editors and all Supervisors on the module's configuration page. We find that we don't generally want our users to make new sites, but it can make prep for workshops easier if the workshop leader doesn't have to make a site for each participant beforehand and users can make their own on during the workshop. 

5. **(Feature Planned for 3.1.0-2 Release)** Decide if you want Supervisors to be able to add new users. The easiest way we have found to make sure everyone can do what we want them to do is to make all non-admin users a supervisor, and then limit what they can based on their team and their team role. But Teams Roles do not limit core Omeka functions, like adding users. So if you would like to prevent Supervisors from adding new users, see the module config. 
6. Adding new users: To help support use cases where 1 person, 1 site, 1 team is the most common configuration (e.g. student projects), you can optionally make a new team while creating a new users. Otherwise, create the team the user will be in before makaing the new user. 

### Installing on a New 3.x Omeka Installation

The steps will be largely the same, except you won't have any items or sites to add to the teams you create. Users will see error messages if they log in and try to do something, like create an item, before being added to a team. 

    
### Integration with Core Features and Future Considerations  
Since we started developing Teams, Omeka has started to natively implement some of the features we built into Teams, like the item<=>site (i.e. item-site) and user=>default site relationships introduced in Omeka 3. Teams makes significant changes to how users access resources, but tries wherever possible to be "side-effect free" if you need to stop using the module. So, relationships built in Teams are synced with their core Omeka analogs where they exist.

For example, when a user changes their current team, it will also update their default sites in their user profile based on the sites associated with their new current team. Likewise, when an item is added to a team, all of that team’s sites are added to that item, generating new item-site entries in the core Omeka database. Likewise, when a site is added to a team, that site is also added to all of the team's items and new item-sites are generated. That way, if you need to stop using Teams, you can uninstall it without having to re-associate all of your items with the appropriate sites.

