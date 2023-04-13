<?php
namespace Teams\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

//legacy from deciding how much of the module to expose to the API
/**
 * Team representation.
 */
class TeamRepresentation extends AbstractEntityRepresentation
{
    public function getControllerName()
    {
        return 'teams';
    }
    public function getJsonLdType()
    {
        return 'o-module-teams:Team';
    }

    public function getJsonLd()
    {
        return [
            'o:id' => $this->id(),
            'o:name' => $this->name(),
            'o:description' => $this->description(),
            'o:team-sites' => $this->sites(),
            'o:team-resources' => $this->resources(),
            'o:team-users' => $this->users(),
            'o:team-resource-templates' => $this->resourseTemplates(),
            'o:team-assets' => $this->assets()
            //this will render an admin advanced search query like:
            //"base-url/admin/user?team=teamName" but that search feature isn't implemented yet
            //'o:users' => $this->urlEntities('user'),
        ];
    }

    public function getReference()
    {
        return new TeamReference($this->resource, $this->getAdapter());
    }

    public function name()
    {
        return $this->resource->getName();
    }

    public function description()
    {
        return $this->resource->getDescription();
    }

    public function users()
    {
        $users = [];
        $userAdapter = $this->getAdapter('users');
        foreach ($this->resource->getTeamUsers() as $teamUserEntity) {
            $userEntity = $teamUserEntity->getUser();
            $users[] = $userAdapter->getRepresentation($userEntity);
        }
        return $users;
    }

    public function teamUsers()
    {
        return $this->resource->getTeamUsers();
    }

    public function resources()
    {
        $resources = [];
        $resourceAdapter = $this->getAdapter('resources');
        foreach ($this->resource->getTeamResources() as $teamResourceEntity) {
            $resourceEntity = $teamResourceEntity->getResource();
            $resources[] = $resourceAdapter->getRepresentation($resourceEntity);
        }
        return $resources;
    }

    public function sites()
    {
        $sites = [];
        $siteAdapter = $this->getAdapter('sites');
        foreach ($this->resource->getTeamSites() as $teamSiteEntity) {
            $siteEntity = $teamSiteEntity->getSite();
            $sites[] = $siteAdapter->getRepresentation($siteEntity);
        }
        return $sites;
    }

    public function resourseTemplates()
    {
        $templates = [];
        $templateAdapter = $this->getAdapter('resource_templates');
        foreach ($this->resource->getTeamResourceTemplates() as $teamTemplateEntity) {
            $templateEntity = $teamTemplateEntity->getResourceTemplate();
            $templates[] = $templateAdapter->getRepresentation($templateEntity);
        }
        return $templates;

    }

    public function assets()
    {
        $assets = [];
        $assetAdapter = $this->getAdapter('assets');
        foreach ($this->resource->getTeamAssets() as $teamAssetEntity) {
            $assetEntity = $teamAssetEntity->getAsset();
            $assets[] = $assetAdapter->getRepresentation($assetEntity);
        }
        return $assets;
    }

    /**
     * Return the admin URL to the resource browse page for the team.
     *
     * Similar to url(), but with the type of resources.
     *
     * @param string|null $resourceType May be "resource" (unsupported),
     * "item-set", "item", "media" or "user".
     * @param bool $canonical Whether to return an absolute URL
     * @return string
     */

    //leaving this for now. This idea of how to return data on the elements of the team would make this call
    //less resource intensive
//    public function urlEntities($resourceType = null, $canonical = false)
//    {
//        $mapResource = [
//            null => 'item',
//            'resources' => 'resource',
//            'items' => 'item',
//            'item_sets' => 'item-set',
//            'users' => 'user',
//        ];
//        if (isset($mapResource[$resourceType])) {
//            $resourceType = $mapResource[$resourceType];
//        }
//        $routeMatch = $this->getServiceLocator()->get('Application')
//            ->getMvcEvent()->getRouteMatch();
//        $url = $this->getViewHelper('Url');
//        return $url(
//            'admin/default',
//            ['controller' => $resourceType, 'action' => 'browse'],
//            [
//                'query' => ['team' => $this->name()],
//                'force_canonical' => $canonical,
//            ]
//        );
//    }
}
