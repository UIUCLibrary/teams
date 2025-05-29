<?php

namespace Teams\Api\Adapter;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Laminas\EventManager\Event;
use Omeka\Api\Adapter\AbstractAdapter;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Api\Response;
use Omeka\Db\Event\Subscriber\Entity;
use Omeka\Entity\EntityInterface;
use Omeka\Entity\User;
use Omeka\Stdlib\ErrorStore;
use Omeka\Stdlib\Message;
use Teams\Entity\Team;
use Teams\Mvc\Controller\Plugin\TeamAuth;
use Omeka\Api\Exception;


abstract class AbstractTeamEntityAdapter extends \Omeka\Api\Adapter\AbstractEntityAdapter
{

    protected array $searchFields = [];

    protected bool $compositeID = true;

    /**
     * @inheritDoc
     */
    public function getRepresentationClass()
    {
        // TODO: Implement getRepresentationClass() method.
    }

    /**
     * @inheritDoc
     */
    public function hydrate(Request $request, EntityInterface $entity, ErrorStore $errorStore)
    {
        // TODO: Implement hydrate() method.
    }

    /**
     * @inheritDoc
     */
    abstract public function getResourceName();

    /**
     * @inheritDoc
     */
    abstract public function getEntityClass();

    abstract public function getMappedEntityClass();

    abstract public function getMappedEntityName();

    abstract public function getMappedEntityDBName();

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
        $entity = $qb->getQuery()->getOneOrNullResult();
        if (!$entity) {
            throw new Exception\NotFoundException(sprintf(
                $this->getTranslator()->translate('%1$s entity with criteria %2$s not found'),
                $entityClass, json_encode($criteria)
            ));
        }
        return $entity;
    }

    public function entityExists($criteria, $request = null)
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
        $entity =  $qb->getQuery()->getOneOrNullResult();

        if (!$entity){
            return false;

        } return  true;
    }

    public function validateRequest(Request $request, ErrorStore $errorStore)
    {
        $logger = $this->getServiceLocator()->get('Omeka\Logger');

        if (Request::CREATE === $request->getOperation()){
            $logger->err('in the validator::create');
            //validate correct payload data exists
            if(!$request->getValue('team') || !is_numeric($request->getValue('team'))){
                $logger->err('our payload needs to indicate team with a numeric value');

                $errorStore->addError('o-module-teams:team', 'Your payload needs to indicate team with a numeric value');
            } else {
                $team = $this->getEntityManager()
                    ->getRepository('Teams\Entity\Team')
                    ->findOneBy(['id'=>$request->getValue('team')]);
                if($team){
                    $logger->err('a team with that id doesnt exist');

                    $errorStore->addError('o-module-teams:team', new Message(
                        'A team with id %s does not exist.', // @translate
                        $request->getValue('team') ));

                }
            }
            if(!$request->getValue($this->getMappedEntityName()) || !is_numeric($request->getValue($this->getMappedEntityName()))){
                $errorStore->addError('o-module-teams:team', "Your payload needs to indicate {$this->getMappedEntityName()} with a numeric value");
                $logger->err("the value of {$this->getMappedEntityName()} needs to be numeric");

            }

            //validate team and resource exist

        }
        if ($errorStore->hasErrors()) {
            $validationException = new Exception\ValidationException;
            $validationException->setErrorStore($errorStore);
            throw $validationException;
        }

        $entity_index = 'o:' . $this->getMappedEntityName();
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');
        //does the request contain a team and resource
        $data = [];
        if (Request::CREATE === $request->getOperation()){
            $data = $request->getContent();
        } elseif (Request::DELETE === $request->getOperation()) {
            $data = $request->getId();
        }
        if (!is_array($data)){
            $errorStore->addError('o:id', new Message('The %s id must be an array.', $this->getResourceName())); // @translate
            return;
        }
        if (!array_key_exists('o:team',$data)){
            $errorStore->addError('o:team', 'The request lacks a team id.'); // @translate

        }
        if (!array_key_exists($entity_index,$data)){
            $errorStore->addError($entity_index, new Message('The request lacks a %s id.',$this->getMappedEntityName())); // @translate
        }


        //is that id a team

        $team = $this->getEntityManager()
            ->getRepository('Teams\Entity\Team')
            ->findOneBy(['id'=>$data['o:team']]);
        if (! $team) {
            $errorStore->addError('o:team', new Message(
                'A team with id = "%s" can not be found', // @translate
                $data['o:team']
            ));
        }

        //is that a resource
        $mapped_entity = $this->getEntityManager()
            ->find($this->getMappedEntityClass(), $data[$entity_index]);

        if (! $mapped_entity) {
            $errorStore->addError($entity_index, new Message(
                'A %1$s with id = "%2$s" can not be found', // @translate
                $this->getMappedEntityName(),
                $data[$entity_index]
            ));
        }

        //does the team resource already exist
        if ($team && $mapped_entity){
            if (Request::CREATE === $request->getOperation() && $this->teamEntityExists($team, $mapped_entity)){
                $errorStore->addError('o:resource', 'That team resource already exists.'); // @translate
            } elseif (Request::DELETE === $request->getOperation() && ! $this->teamEntityExists($team, $mapped_entity)){
                $errorStore->addError('o:resource', 'That team resource you are trying to delete does not exists.'); // @translate
            }
        }

    }

    //PHP 8 can implement multiple types as type hint: Resource|User|ResourceTemplate|Asset|Site
    public function teamEntityExists(Team $team, EntityInterface $entity )
    {
        $entity_name = $this->getMappedEntityName();
        return $this->getEntityManager()
            ->getRepository($this->getEntityClass())
            ->findOneBy(['team'=>$team->getId(), $entity_name => $entity->getId()]);

    }

    public function teamAuthority($request, $team, $user, $resource=null)
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

        if (! $teamAuth->teamAuthorized($user, $operation, 'resource', $teamId)){
            throw new Exception\PermissionDeniedException(sprintf(
                    $this->getTranslator()->translate(
                        'Permission denied for the current user to %1$s a team resource in team_id = %2$s.'
                    ),
                    $operation, $request->getContent()['o:team'])
            );
        }
    }

    public function findEntity($criteria, $request = null)
    {
        if ($this->compositeID){
            if (is_string($criteria) && str_contains($criteria,'-')){
                $compositeId = explode('-', $criteria);
                if (count($compositeId) == 2) {
                    $teamId = $compositeId[0];
                    $resourceId = $compositeId[1];
                    $criteria = array();
                    $criteria['team'] = $teamId;
                    $criteria[$this->getMappedEntityDBName()] = $resourceId;
                } else {
                    throw new Exception\BadRequestException(sprintf(
                        $this->getTranslator()->translate('Bad id. Composite ids for "%1$s" should take the form of "api/%2$s/teamId-%3$sId".'),
                        get_class($this),$this->getResourceName(),$this->getMappedEntityName()
                    ));
                }
            } else {
                throw new Exception\BadRequestException(sprintf(
                    $this->getTranslator()->translate('Bad id. Composite ids for "%1$s" should take the form of "api/%2$s/teamId-%3$sId".'),
                    get_class($this),$this->getResourceName(),$this->getMappedEntityName()
                ));
            }

        }
        return AbstractEntityAdapter::findEntity($criteria, $request=null);
    }

    public function batchCreate(Request $request)
    {
        AbstractAdapter::batchCreate($request);
    }

    public function batchDelete(Request $request)
    {
        AbstractAdapter::batchDelete($request);
    }

    public function update(Request $request)
    {
        AbstractAdapter::update($request);
    }

    public function batchUpdate(Request $request)
    {
        AbstractAdapter::batchUpdate($request);
    }

    public function search(Request $request): Response
    {
        $searchFields = array();
        $group_by = 'team'; //default order by
        $query = $request->getContent();
        $mappedEntityDBName = $this->getMappedEntityDBName();

        if ( array_key_exists('team', $query) ) {
            $searchFields['team'] = $query['team'];
            $group_by = $mappedEntityDBName;
        } elseif (array_key_exists($mappedEntityDBName, $query)) {
            $searchFields[$mappedEntityDBName] = $query[$mappedEntityDBName];
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

        foreach ($searchFields as $field => $value) {
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

        $scalarField = $request->getOption('returnScalar');
        if (!$scalarField && $query['return_scalar']) {
            if (!array_key_exists($query['return_scalar'], $this->scalarFields)) {
                throw new Exception\BadRequestException(sprintf(
                    $this->getTranslator()->translate('The "%1$s" field is not available in the %2$s adapter class.'),
                    $query['return_scalar'], get_class($this)
                ));
            }
            // The return_scalar passed in the query is valid. Note that we must
            // set returnScalar to the request so the API manager skips validation.
            $scalarField = $query['return_scalar'];
            $request->setOption('returnScalar', $scalarField);
        }
        if ($scalarField) {
            $classMetadata = $this->getEntityManager()->getClassMetadata($entityClass);
            $fieldNames = $classMetadata->getFieldNames();
            if (!in_array($scalarField, $fieldNames)) {
                $associationNames = $classMetadata->getAssociationNames();
                if (!in_array($scalarField, $associationNames)) {
                    throw new Exception\BadRequestException(sprintf(
                        $this->getTranslator()->translate('The "%1$s" field is not available in the %2$s entity class. Must be in: %3$s'),
                        $scalarField, $entityClass, implode('|', $associationNames)
                    ));
                }

                $qb->select(["IDENTITY(omeka_root.team) AS team, IDENTITY(omeka_root.{$mappedEntityDBName}) as {$mappedEntityDBName}"]);
            } else {
                $qb->select(['omeka_root.id', 'omeka_root.' . $scalarField]);
            }
            $content = array_column($qb->getQuery()->getScalarResult(), $scalarField, $scalarField);
            $response = new Response($content);
            $response->setTotalResults($countPaginator->count());
            return $response;
        }


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

    /**
     * @param $request
     * @return bool
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