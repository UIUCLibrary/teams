<?php
namespace Teams\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\UserRepresentation;

//legacy from deciding how much of the module to expose to the API
/**
 * TeamRole representation.
 */
class TeamRoleRepresentation extends AbstractEntityRepresentation
{


    public function getControllerName()
    {
        return 'team-role';
    }

    public function getJsonLdType()
    {
        return 'o-module-teams:team-role';
    }


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

    public function id()
    {
        return $this->resource->getId();
    }



}
