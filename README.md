# Teams

## Overview

### Purpose
Teams helps global admins organize work by controlling what resources users see and what they can do with those resources. 

### Methods

#### Teams
Users, sites and resources (items, media, item sets and resource templates) are attached to teams. When logged in, users only see the sites and resources that are part of their current team. 

#### Team Roles
Within a team, a user is given a team role. These establish additional restrictions on user permissions on top of the permissions granted by the core Omeka-s user profile. It also allows a single user to have different roles and permissions in different teams to which they belong.

Team roles are defined by the global administrator and global admins can establish as many roles with different permissions as you need. Each role is configurable by selecting whether or not it can:

    Add Team Members
    Add Resources 
    Modify Resources
    Delete Resources    
    Modify Team Sites

A global toggle determines who can make new sites (more on that below).

#### Mechanics of permission 
A user’s permissions within a team are determined by their role. However, permissions in Team roles are subtractive, not additive, to the permissions created by their core Omeka role (e.g. Site Admin, Editor, Researcher, etc.). If the permissions of Teams and Omeka were a Venn diagram, users can only do the things in the center of the diagram—those things permitted by both their core Omeka and their team role. 

Therefore, if a user with the core Omeka role of “Author” is given a team role with the ability to modify the team’s sites, that user will be unable to do so because “Authors” in Omeka can not modify sites. The global admin would need to either change that user’s core Omeka role, or add them via the site’s User Permissions page. 

While Teams will not grant extra privileges, it does help you provide finer-grained control over where they can exercise those privileges. A user who has the ability to add and delete items through their core Omeka role, a “Site Admin” for example, who also has a team role that allows them to add and delete items can only add and delete items in the context of their current team. Items belonging to another team are inaccessible to them. 

### Deleting

When users delete resources, they are not permanently deleted to prevent. Instead, that resource is removed from the user’s current team. This is primarily for situations where multiple teams share certain resources.  Global Admins can see resources that no longer belong to any teams in the Trash section of the Admin menu.


### Global Toggles

We have found that global admins may want to change some permissions globally or often. It would be cumbersome to make these changes through Team Roles, so they are instead part of a global toggle in the Teams configuration page. Currently, the only global toggle changes who can make new sites. Admins can turn on or off the ability to make new sites for Site Admins and Editors.
