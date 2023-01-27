<?php
namespace Teams\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

//legacy from deciding how much of the module to expose to the API
/**
 * Team representation.
 */
class TeamRepresentation extends AbstractEntityRepresentation
{
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
            'o:sites' => $this->sites(),
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

        return $this->resource->getTeamUsers();
    }


    public function resources()
    {
        return $this->resource->getResources();
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
    public function urlEntities($resourceType = null, $canonical = false)
    {
        $mapResource = [
            null => 'item',
            'resources' => 'resource',
            'items' => 'item',
            'item_sets' => 'item-set',
            'users' => 'user',
        ];
        if (isset($mapResource[$resourceType])) {
            $resourceType = $mapResource[$resourceType];
        }
        $routeMatch = $this->getServiceLocator()->get('Application')
            ->getMvcEvent()->getRouteMatch();
        $url = $this->getViewHelper('Url');
        return $url(
            'admin/default',
            ['controller' => $resourceType, 'action' => 'browse'],
            [
                'query' => ['team' => $this->name()],
                'force_canonical' => $canonical,
            ]
        );
    }
}
