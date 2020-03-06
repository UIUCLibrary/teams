<?php
namespace Teams\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\UserRepresentation;

/**
 * TeamRole representation.
 */
class TeamRoleRepresentation extends AbstractEntityRepresentation
{


    public function getControllerName()
    {
        return 'team-role';
    }

    //Class Teams\Api\Representation\TeamRepresentation contains 1 abstract method and must therefore be declared
    // abstract or implement the remaining methods
    // (Omeka\Api\Representation\AbstractResourceRepresentation::getJsonLdType)
    public function getJsonLdType()
    {
        return 'o-module-teams:team-role';
    }

    //Fatal error: Class Teams\Api\Representation\TeamRepresentation contains 1 abstract method and must therefore be
    // declared abstract or implement the remaining methods
    // (Omeka\Api\Representation\AbstractResourceRepresentation::getJsonLd)
    public function getJsonLd()
    {
        return [
            'id' => $this->id(),
            'name' => $this->name(),
            'comment' => $this->getComment(),
            'can_add_users' => $this->canAddUsers(),
            'can_add_items' => $this->canAddItems(),
            'can_add_itemsets' => $this->canAddItemsets(),
            'can_modify_resources' => $this->canModifyResources(),
            'can_delete_resources' => $this->canDeleteResources(),
            'can_add_site_pages' => $this->canAddSitePages()
        ];
    }

    public function getReference()
    {
        return new TeamRoleRepresentation($this->resource, $this->getAdapter());
    }

    public function name()
    {
        return $this->resource->getName();
    }

    public function getComment()
    {
        return $this->resource->getComment();
    }

    public function canAddUsers()
    {

        return $this->resource->getCanAddUsers();
    }

    public function canAddItems()
    {

        return $this->resource->getCanAddItems();
    }

    public function canAddItemsets()
    {

        return $this->resource->getCanAddItemsets();
    }

    public function canModifyResources()
    {

        return $this->resource->getCanModifyResources();
    }

    public function canDeleteResources()
    {

        return $this->resource->getCanDeleteResources();
    }

    public function canAddSitePages()
    {

        return $this->resource->getCanAddSitePages();
    }



    public function resources()
    {
        $this->resource->getResources();
    }



}
