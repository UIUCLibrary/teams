<?php
namespace Teams\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Laminas\EventManager\Event;
use NumericDataTypes\DataType\Integer;
use Omeka\Api\Adapter\AbstractAdapter;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Exception;
use Omeka\Api\Request;
use Omeka\Api\Response;
use Omeka\Entity\EntityInterface;
use Omeka\Entity\Item;
use Omeka\Entity\Resource;
use Omeka\Entity\User;
use Omeka\Mvc\Controller\Plugin\Api;
use Omeka\Stdlib\ErrorStore;
use Omeka\Stdlib\Message;
use Teams\Api\Representation\TeamResourceRepresentation;
use Teams\Entity\TeamResource;
use Teams\Mvc\Controller\Plugin\TeamAuth;

//legacy from deciding how much of the module to expose to the API
class TeamResourceAdapter extends AbstractTeamEntityAdapter
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

    public function findMappedEntity($criteria, $request = null)
    {
        if (!is_array($criteria)) {
            $criteria = ['id' => $criteria];
        }

        $entityClass = $this->getMappedEntityClass();
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
        return $qb->getQuery()->getOneOrNullResult();
    }

    public function read(Request $request)
    {
        AbstractAdapter::read($request);
    }
    public function create(Request $request)
    {

        if ($request->getValue('batch')){
            $this->batchCreate($request);
        }

        $user = $this->getServiceLocator()->get('Omeka\AuthenticationService')->getIdentity();

        $this->validateRequest($request, new ErrorStore());
        $team = $request->getValue('team');
        $resource = $request->getValue('resource');
        $this->teamAuthority($request, $request->getValue('team'), $user);
        if (!$this->resourceAuthority($request->getValue('resource'),$user)){
            throw new Exception\PermissionDeniedException('Permission denied for the current user to add this resource to a team.'
                );
        }
        $teamEntity = $this->getEntityManager()->getRepository('Teams\Entity\Team')->findOneBy(['id'=>$team]);
        $resourceEntity = $this->getEntityManager()->getRepository('Omeka\Entity\Resource')->findOneBy(['id'=>$resource]);
        $teamResource = new TeamResource($teamEntity, $resourceEntity);
        $this->getEntityManager()->persist($teamResource);
        if ($request->getOption('flushEntityManager', true)) {
            $this->getEntityManager()->flush();
            // Refresh the entity on the chance that it contains associations
            // that have not been loaded.
            $this->getEntityManager()->refresh($teamResource);
        }
        return new Response($teamResource);


    }

    public function batchCreate(Request $request)
    {
        AbstractEntityAdapter::batchCreate($request);
    }

    public function update(Request $request)
    {
        AbstractAdapter::update($request);
    }

    public function batchUpdate(Request $request)
    {
        AbstractAdapter::batchUpdate($request);
    }

    public function delete(Request $request)
    {
        AbstractAdapter::delete($request);
    }

    public function batchDelete(Request $request)
    {
        AbstractAdapter::batchDelete($request);
    }

    public function validateRequest(Request $request, ErrorStore $errorStore)
    {
        if (Request::CREATE === $request->getOperation()){
            //validate payload data refers to real entities

            //validate team data
            if(!$request->getValue('team') || !is_int($request->getValue('team'))){
                $errorStore->addError('o-module-teams:team', 'Your payload needs to indicate team with a numeric value');
            } else {
                $team = $this->getEntityManager()
                    ->getRepository('Teams\Entity\Team')
                    ->findOneBy(['id'=>$request->getValue('team')]);
                if(is_null($team)){
                    $errorStore->addError('o-module-teams:team', new Message(
                        'A team with id %s does not exist.', // @translate
                        $request->getValue('team') ));
                }
            }

            //validate resource data
            if(!$request->getValue('resource') || !is_int($request->getValue('resource'))){
                $errorStore->addError('o-module-teams:team', 'Your payload needs to indicate resource with a numeric value');
            } else {
                $mappedEntity = $this->findMappedEntity($request->getValue('resource'), $request);
                if(is_null($mappedEntity)){
                    $errorStore->addError('o-module-teams:team', new Message(
                        'A resource with id %s does not exist.', // @translate
                        $request->getValue('resource') ));
                }
            }
        }
        if ($errorStore->hasErrors()) {
            $validationException = new Exception\ValidationException;
            $validationException->setErrorStore($errorStore);
            throw $validationException;
        }

    }
    public function teamAuthority($request, $team, $user, $resource=null)
    {
        $em = $this->getEntityManager();
        $operation = $request->getOperation();
        $teamAuth = new TeamAuth($em, $logger);
        if (! $teamAuth->teamAuthorized($user, $operation, 'resource', $team)){
            throw new Exception\PermissionDeniedException(sprintf(
                    $this->getTranslator()->translate(
                        'Permission denied for the current user to %1$s a team resource in team_id = %2$s.'
                    ),
                    $operation, $team)
            );
        }
    }

    /**
     * @param $request
     * @return void
     *
     * Does the user have the authority to modify the resource
     */
    public function resourceAuthority($resource, User $user ):bool
    {
        //if the resource belongs to any team where the user has resource authority, or if the resource belongs to no team

        //iterate through the teams of the resource

        if ($user->getRole() == 'global_admin'){
            return true;
        }
        $resourceTeams = $this->getEntityManager()
            ->getRepository('Teams\Entity\TeamResource')
                ->findBy(['resource'=>$resource]);
        if (!$resourceTeams){
            return true;
        } else {
            $userTeams = $this->getEntityManager()
                ->getRepository('Teams\Entity\TeamUser')
                ->findBy(['user'=>$user->getId()]);

            //if the user has a resource permission in any team the resource belongs to, return true
            foreach ($resourceTeams as $resourceTeam) {
                $resourceTeamId = $resourceTeam->getTeam()->getId();
                foreach($userTeams as $userTeam){
                    if ($resourceTeamId == $userTeam->getTeam()->getId()){
                        if ($userTeam->getRole()->getCanAddItems()){
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }
}
