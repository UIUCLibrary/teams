<?php
namespace Teams\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Laminas\EventManager\Event;
use Omeka\Api\Adapter\AbstractAdapter;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Exception;
use Omeka\Api\Request;
use Omeka\Api\Response;
use Omeka\Entity\EntityInterface;
use Omeka\Entity\Item;
use Omeka\Entity\ItemSet;
use Omeka\Entity\Resource;
use Omeka\Stdlib\ErrorStore;
use Teams\Api\Representation\TeamResourceRepresentation;
use Teams\Entity\Team;
use Teams\Entity\TeamResource;
use Teams\Mvc\Controller\Plugin\TeamAuth;

//legacy from deciding how much of the module to expose to the API
class TeamResourceAdapter extends AbstractEntityAdapter
{
    protected $sortFields = [
        'resource_id' => 'resource_id',
        'team_id' => 'team_id',

    ];

    public function getResourceName()
    {
        return 'team-resource';
    }

    public function getRepresentationClass()
    {
        return TeamResourceRepresentation::class;
    }

    public function getEntityClass()
    {
        return TeamResource::class;
    }

    public function getMappedEntityClass()
    {
        return Resource::class;
    }

    public function getMappedEntityName()
    {
        return 'resource';
    }

    public function hydrate(
        Request $request,
        EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        if ($this->shouldHydrate($request, 'team')) {
            $name = $request->getValue('team');
            if (!is_null($name)) {
                $name = trim($name);
                $entity->setName($name);
            }
        }
        if ($this->shouldHydrate($request, 'resource')) {
            $description = $request->getValue('resource');
            if (!is_null($description)) {
                $description = trim($description);
                $entity->setDescription($description);
            }
        }
    }

    public function buildQuery(QueryBuilder $qb, array $query)
    {
        if (isset($query['team'])) {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root' . '.' . 'team',
                $this->createNamedParameter($qb, $query['team'])
            ));
        }

        if (isset($query['resource'])) {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root' . '.' . 'resource',
                $this->createNamedParameter($qb, $query['resource'])
            ));        }

    }

    public function buildBaseQuery(QueryBuilder $qb, array $query)
    {
        if (isset($query['id'])) {
            echo $query['id'];
            $ids = $query['id'];
            if (!is_array($ids)) {
                $ids = [$ids];
            }
            // Exclude null and empty-string ids. Previous resource-only version used
            // is_numeric, but we want this to be able to work for possible string IDs
            // also
            $ids = array_filter($ids, function ($id) {
                return !($id === null || $id === '');
            });
            if ($ids) {
                $qb->andWhere($qb->expr()->in(
                    'omeka_root.id',
                    $this->createNamedParameter($qb, $ids)
                ));
            }
        }
    }

    public function search(Request $request)
    {
        $search_fields = array();
        $group_by = 'team'; //default order by
        $query = $request->getContent();

        if ( array_key_exists('team', $query) ) {
            $search_fields['team'] = $query['team'];
            $group_by = 'resource';
        } elseif (array_key_exists('resource', $query)) {
            $search_fields['resource'] = $query['resource'];
        } else {
            throw new Exception\BadRequestException(sprintf(
                $this->getTranslator()->translate('%1$s entity requires team or resource search criteria'),
                $this->getEntityClass()
            ));
        }

        // Set default query parameters
        if (!isset($query['page'])) {
            $query['page'] = null;
        }
        if (!isset($query['per_page'])) {
            $query['per_page'] = null;
        }
        if (!isset($query['limit'])) {
            $query['limit'] = null;
        }
        if (!isset($query['offset'])) {
            $query['offset'] = null;
        }
        if (!isset($query['sort_by'])) {
            $query['sort_by'] = null;
        }
        if (isset($query['sort_order'])
            && in_array(strtoupper($query['sort_order']), ['ASC', 'DESC'])
        ) {
            $query['sort_order'] = strtoupper($query['sort_order']);
        } else {
            $query['sort_order'] = 'ASC';
        }
        if (!isset($query['return_scalar'])) {
            $query['return_scalar'] = null;
        }

        // Begin building the search query.
        $entityClass = $this->getEntityClass();

        $this->index = 0;
        $qb = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('omeka_root')
            ->from($entityClass, 'omeka_root');

            foreach ($search_fields as $field => $value) {
                $qb->andWhere($qb->expr()->eq(
                    "omeka_root.$field",
                    $this->createNamedParameter($qb, $value)
                ));
            }
        $this->buildBaseQuery($qb, $query);
        $this->buildQuery($qb, $query);
        $qb->groupBy("omeka_root." . $group_by);

        // Trigger the search.query event.
        $event = new Event('api.search.query', $this, [
            'queryBuilder' => $qb,
            'request' => $request,
        ]);
        $this->getEventManager()->triggerEvent($event);

        // Add the LIMIT clause.
        $this->limitQuery($qb, $query);

        // Before adding the ORDER BY clause, set a paginator responsible for
        // getting the total count. This optimization excludes the ORDER BY
        // clause from the count query, greatly speeding up response time.
        $countQb = clone $qb;
        $countQb->select('1')->resetDQLPart('orderBy');
        $countPaginator = new Paginator($countQb, false);

        // Add the ORDER BY clause. Always sort by entity ID in addition to any
        // sorting the adapters add.
        $this->sortQuery($qb, $query);
        $qb->addOrderBy("omeka_root.team", $query['sort_order']);


        $paginator = new Paginator($qb, false);
        $entities = [];
        // Don't make the request if the LIMIT is set to zero. Useful if the
        // only information needed is total results.
        if ($qb->getMaxResults() || null === $qb->getMaxResults()) {
            foreach ($paginator as $entity) {
                if (is_array($entity)) {
                    // Remove non-entity columns added to the SELECT. You can use
                    // "AS HIDDEN {alias}" to avoid this condition.
                    $entity = $entity[0];
                }
                $entities[] = $entity;
            }
        }

        $response = new Response($entities);
        $response->setTotalResults($countPaginator->count());
        return $response;
    }

    public function findEntity($criteria, $request = null)
    {
        if (!is_array($criteria)) {
            $criteria = ['id' => $criteria];
        }

        $entityClass = $this->getEntityClass();
        $this->index = 0;
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('omeka_root')->from($entityClass, 'omeka_root');
        foreach ($criteria as $field => $value) {
            $qb->andWhere($qb->expr()->eq(
                "omeka_root.$field",
                $this->createNamedParameter($qb, $value)
            ));
        }
        $qb->setMaxResults(1);

        $event = new Event('api.find.query', $this, [
            'queryBuilder' => $qb,
            'request' => $request,
        ]);

        $this->getEventManager()->triggerEvent($event);
        $entity = $qb->getQuery()->getOneOrNullResult();
        if (!$entity) {
            throw new Exception\NotFoundException(sprintf(
                $this->getTranslator()->translate('%1$s entity with criteria %2$s not found'),
                $entityClass, json_encode($criteria)
            ));
        }
        return $entity;
    }

    public function read(Request $request)
    {
        AbstractAdapter::read($request);
    }

    public function batchCreate(Request $request)
    {
        AbstractAdapter::batchCreate($request);
    }

    public function update(Request $request)
    {
        AbstractAdapter::batchCreate($request);
    }

    public function delete(Request $request)
    {
        AbstractAdapter::delete($request);
    }

    public function batchDelete(Request $request)
    {
        AbstractAdapter::batchDelete($request);
    }

    public function persistTeamResource(Team $team, Resource $resource)
    {
        $entity_exists = $this->getEntityManager()
            ->getRepository('Teams\Entity\TeamResource')
            ->findOneBy(['team'=>$team->getId(), 'resource'=>$resource->getId()]);
        if (! $entity_exists) {
            $entity = new TeamResource($team, $resource);
            $this->getEntityManager()->persist($entity);
            return $entity;
        } return $entity_exists;

    }
    /*
     * Hydrates resource and all of the resources it contains
     * E.g., if Item, hydrates all Media and Assets
     */
    public function explodeItem(Team $team, Item $item)
    {

        foreach ($item->getMedia() as $media)
            {
                $entity = $this->persistTeamResource($team, $media);
            }
        return $this->persistTeamResource($team,$item);

    }
    public function explodeItemSet(Team $team, ItemSet $itemSet)
    {
        foreach ($itemSet->getItems() as $item)
        {
            $this->explodeItem($team, $item);
        }
        return $this->persistTeamResource($team,$itemSet);

    }

    public function create(Request $request)
    {
        //authorized
        $this->teamAuthority($request);

        //validate
        //is the resource id the id of a resource
        //is the team id the id of a team
        //does the team resource already exist

        //is item set


        //hydrate

        $team = $this->getEntityManager()
            ->find('Teams\Entity\Team', $request->getContent()['o:team']);
        $resource = $this->getEntityManager()
            ->find('Omeka\Entity\Resource', $request->getContent()['o:resource']);

//        $services = $this->getServiceLocator();
//        $logger = $services->get('Omeka\Logger');
//        $logger->err($resource->getResourceName() );

        $resource_ids = [];
        //if the resource is an itemset, add the item set and explode the items it contains
        if ($resource->getResourceName() == 'item_sets'){
            $entity = $this->explodeItemSet($team,$resource);
        }

        if ($resource->getResourceName() == 'items'){
            $entity = $this->explodeItem($team,$resource);
        }

        if ($request->getOption('flushEntityManager', true)) {
            $this->getEntityManager()->flush();
            // Refresh the entity on the chance that it contains associations
            // that have not been loaded.
        }
        return new Response($entity);
    }

    public function teamAuthority($request)
    {
        $em = $this->getEntityManager();
        $user = $this->getServiceLocator()->get('Omeka\AuthenticationService')->getIdentity();
        $operation = $request->getOperation();
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');
        $teamAuth = new TeamAuth($em, $logger);
        $teamId = 0;
        if (array_key_exists('team',$request->getContent())){
            $teamId = $request->getContent()['team'];
        } elseif (array_key_exists('o:team', $request->getContent())){
            $teamId = $request->getContent()['o:team'];
        }
        echo "this is the team id: " . $teamId . '<br>';

        if (! $teamAuth->teamAuthorized($user, $operation, 'resource', $teamId)){
            throw new Exception\PermissionDeniedException(sprintf(
                    $this->getTranslator()->translate(
                        'Permission denied for the current user to %1$s a team resource in team_id = %2$s.'
                    ),
                    $operation, $request->getContent()['o:team'])
            );
        }
    }


    public function hydrateEntity(Request $request,
                                  EntityInterface $entity, ErrorStore $errorStore
    ) {

        $operation = $request->getOperation();
        // Before everything, check whether the current user has access to this
        // entity in its original state.
        $this->authorize($entity, $operation);

        // Trigger the operation's api.hydrate.pre event.
        $event = new Event('api.hydrate.pre', $this, [
            'entity' => $entity,
            'request' => $request,
            'errorStore' => $errorStore,
        ]);
        $this->getEventManager()->triggerEvent($event);

        // Validate the request.
        $this->validateRequest($request, $errorStore);

        if ($errorStore->hasErrors()) {
            $validationException = new Exception\ValidationException;
            $validationException->setErrorStore($errorStore);
            throw $validationException;
        }

        $this->hydrate($request, $entity, $errorStore);

        // Trigger the operation's api.hydrate.post event.
        $event = new Event('api.hydrate.post', $this, [
            'entity' => $entity,
            'request' => $request,
            'errorStore' => $errorStore,
        ]);
        $this->getEventManager()->triggerEvent($event);

        // Validate the entity.
        $this->validateEntity($entity, $errorStore);

        if ($errorStore->hasErrors()) {
            if (Request::UPDATE == $operation) {
                // Refresh the entity from the database, overriding any local
                // changes that have not yet been persisted
                $this->getEntityManager()->refresh($entity);
            }
            $validationException = new Exception\ValidationException;
            $validationException->setErrorStore($errorStore);
            throw $validationException;
        }
    }


    public function validateRequest(Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();
        if (array_key_exists('team', $data) && array_key_exists('resource', $data)) {
            return true;
        } else {
            throw new Exception\BadRequestException(
                $this->getTranslator()->translate('Not a vaild request')
            );
        }
    }

}
