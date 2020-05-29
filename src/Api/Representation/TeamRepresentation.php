<?php
namespace Teams\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\UserRepresentation;

/**
 * Team representation.
 */
class TeamRepresentation extends AbstractEntityRepresentation
{
//    /**
//     * Cache for the counts of resources.
//     *
//     * @var array
//     */
//    protected $cacheCounts = [];

//    public function getControllerName()
//    {
//        return 'team';
//    }

    //Class Teams\Api\Representation\TeamRepresentation contains 1 abstract method and must therefore be declared
    // abstract or implement the remaining methods
    // (Omeka\Api\Representation\AbstractResourceRepresentation::getJsonLdType)
    public function getJsonLdType()
    {
        return 'o-module-teams:Team';
    }

    //Fatal error: Class Teams\Api\Representation\TeamRepresentation contains 1 abstract method and must therefore be
    // declared abstract or implement the remaining methods
    // (Omeka\Api\Representation\AbstractResourceRepresentation::getJsonLd)
    public function getJsonLd()
    {
        return [
            'o:id' => $this->id(),
            'o:name' => $this->name(),
            'o:description' => $this->description(),
            'o:users' => $this->urlEntities('user'),
//            'o:item_sets' => $this->urlEntities('item-set'),
//            'o:items' => $this->urlEntities('item'),
//            'o:media' => $this->urlEntities('media'),
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

    //Call to undefined method Teams\Api\Representation\TeamRepresentation::description()
    public function description()
    {
        return $this->resource->getDescription();
    }

    public function users()
    {

        return $this->resource->getTeamUsers();
    }

//    public function newUser($user)
//    {
//        $this->resource->setTeamUsers($user);
//    }

    public function resources()
    {
        return $this->resource->getResources();
    }

    public function sites()
    {
        return $this->resource->getTeamSites();
    }

//    /**
//     * Get the resources associated with this team.
//     *
//     * @return AbstractResourceEntityRepresentation[]
//     */
//    public function resources()
//    {
//        $result = [];
//        $adapter = $this->getAdapter('resources');
//        // Note: Use a workaround because the reverse doctrine relation cannot
//        // be set. See the entity.
//        // TODO Fix entities for many to many relations.
//        // foreach ($this->resource->getResources() as $entity) {
//        foreach ($this->resource->getTeamResources() as $teamResourceEntity) {
//            $entity = $teamResourceEntity->getResource();
//            $result[$entity->getId()] = $adapter->getRepresentation($entity);
//        }
//        return $result;
//    }

//    /**
//     * Get the users associated with this team.
//     *
//     * @return UserRepresentation[]
//     */
//    public function users()
//    {
//        $result = [];
//        $adapter = $this->getAdapter('users');
//        // Note: Use a workaround because the reverse doctrine relation cannot
//        // be set. See the entity.
//        // TODO Fix entities for many to many relations.
//        // foreach ($this->resource->getUsers() as $entity) {
//        foreach ($this->resource->getTeamUsers() as $teamUserEntity) {
//            $entity = $teamUserEntity->getUser();
//            $result[$entity->getId()] = $adapter->getRepresentation($entity);
//        }
//        return $result;
//    }

//    /**
//     * Get this team's specific resource count.
//     *
//     * @param string $resourceType
//     * @return int
//     */
//    public function count($resourceType = 'resources')
//    {
//        if (!isset($this->cacheCounts[$resourceType])) {
//            $response = $this->getServiceLocator()->get('Omeka\ApiManager')
//                ->search('team', [
//                    'id' => $this->id(),
//                ]);
//            $this->cacheCounts[$resourceType] = $response->getTotalResults();
//        }
//        return $this->cacheCounts[$resourceType];
//    }

//    public function adminUrl($action = null, $canonical = false)
//    {
//        $url = $this->getViewHelper('Url');
//        return $url(
//            'admin/team-name',
//            [
//                'action' => $action ?: 'show',
//                'name' => $this->name(),
//            ],
//            ['force_canonical' => $canonical]
//        );
//    }
//
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
