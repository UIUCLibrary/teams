<?php

/*
 * Define the kinds of actions we want to control
 *  CUD (all can read):
 *      TeamItem
 *      TeamSite
 *      TeamSite page
 *      TeamItemset
 *      TeamUser
 *  -Add a user to a team
 *  -Remove a user from a team
 *  -Add items to a team
 *  -Remove items from a team
 *  -Add items to a team's itemset
 *  -Remove items from a team's itemset
 *  -Add pages to a team's site
 *  -Delete pages from a team's site
 *
 * Design for a partial:
 *  1. Pass in a variable that indicates the kind of resource or action that is taking place (perhaps from the route?)
 *  2. Map that variable to one of the CUD+sub actions above
 *  3. For read, would still need to test if the user's current team shares team with item
 *  4. Based on the the mapping, execute a query to see if the user's role in the current team fits the context
 *  5a. If it does, show them the page
 *  5b. If not, show them an error page
 *  6a. On execution, check again to make sure the instruction was issued
 *
 * Inject that partial into all of the relevant views
 *
 * Design for the checking function
 *  1. Get the user's current team
 *  1-ex. Prompt user to select a team or tell them that they aren't currently assigned to any teams
 *  2. Get resource type and id
 *  3. Check relevant Teams table to see if resource_id and user's current_team together
 *  4a. If true, pass to check permissions (5)
 *  4b. If false, pass error view that tells user that the resource doesn't belong to their current team. Offer error
 *      recovery.
 *  5. Check user's role.
 *  6. Check role permissions.
 *  7a. Check current context. If context is allowed by permissions, pass view.
 *  7b. If context is not allowed, show error message and offer recover.
 *  */