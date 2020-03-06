<?php


namespace Teams\Api\Representation;


use Omeka\Api\Representation\AbstractEntityRepresentation;

class TeamResourceRepresentation extends AbstractEntityRepresentation
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
        return 'o-module-teams:TeamResource';
    }

    //Fatal error: Class Teams\Api\Representation\TeamRepresentation contains 1 abstract method and must therefore be
    // declared abstract or implement the remaining methods
    // (Omeka\Api\Representation\AbstractResourceRepresentation::getJsonLd)
    public function getJsonLd()
    {
        return [
            'team' => $this->team(),
            'resource' => $this->resource(),
//            'o:item_sets' => $this->urlEntities('item-set'),
//            'o:items' => $this->urlEntities('item'),
//            'o:media' => $this->urlEntities('media'),
        ];
    }

    public function getReference()
    {
        return new TeamReference($this->resource, $this->getAdapter());
    }

    public function team()
    {
        return $this->resource->getTeam();
    }

    //Call to undefined method Teams\Api\Representation\TeamRepresentation::description()
    public function resource()
    {
        return $this->resource->getResource();
    }


//    public function newUser($user)
//    {
//        $this->resource->setTeamUsers($user);
//    }



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


}