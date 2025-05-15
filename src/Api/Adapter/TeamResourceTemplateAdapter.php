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
use Omeka\Entity\ResourceTemplate;
use Omeka\Stdlib\ErrorStore;
use Omeka\Stdlib\Message;
use Teams\Entity\Team;
use Teams\Entity\TeamResource;
use Teams\Entity\TeamResourceTemplate;
use Teams\Api\Representation\TeamResourceTemplateRepresentation;

class TeamResourceTemplateAdapter extends AbstractTeamEntityAdapter
{
    protected $sortFields = [
        'resource-template' => 'resource-template',
        'team' => 'team',

    ];



    public function getResourceName()
    {
        return 'team-resource-template';
    }

    public function getRepresentationClass()
    {
        return TeamResourceTemplateRepresentation::class;
    }

    public function hydrate(
        Request $request,
        EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        if ($this->shouldHydrate($request, 'team')) {
            $team_id = $request->getValue('team');
            if (!is_null($team_id)) {
                $team_id = trim($team_id);
                $entity->setTeamId($team_id);
            }
        }
        if ($this->shouldHydrate($request, 'resource-template')) {
            $team_resource_id = $request->getValue('resource-template');
            if (!is_null($team_resource_id)) {
                $team_resource_id = trim($team_resource_id);
                $entity->setTeamResourceId($team_resource_id);
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

        if (isset($query['resource-template'])) {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root' . '.' . 'resource_template',
                $this->createNamedParameter($qb, $query['resource-template'])
            ));        }

    }

    public function buildBaseQuery(QueryBuilder $qb, array $query)
    {
        if (isset($query['id'])) {
            $ids = $query['id'];
            if (!is_array($ids)) {
                $ids = [$ids];
            }
            // Exclude null and empty-string ids. Previous resource_template-only version used
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
            $group_by = 'resource_template';
        } elseif (array_key_exists('resource-template', $query)) {
            $search_fields['resource_template'] = $query['resource-template'];
        } else {
            throw new Exception\BadRequestException(sprintf(
                $this->getTranslator()->translate('%1$s entity requires team or resource-template search criteria'),
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
                        $this->getTranslator()->translate('The "%1$s" field is not available in the %2$s entity class.'),
                        $scalarField, $entityClass
                    ));
                }
                $qb->select(["IDENTITY(omeka_root.team) AS team, IDENTITY(omeka_root.resource_template) as resource_template"]);
            } else {
                $qb->select(['omeka_root.id', 'omeka_root.' . $scalarField]);
            }
            //just putting this note here because it has been confusing before: returns the id as the index and key
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

    public function read(Request $request)
    {
        AbstractAdapter::read($request);
    }

    public function validateRequest(Request $request, ErrorStore $errorStore)
    {
        $logger = $this->getServiceLocator()->get('Omeka\Logger');

        if (Request::CREATE === $request->getOperation()) {
            //validate correct payload data exists
            if (!$request->getValue('team') || !is_numeric($request->getValue('team'))) {
                $logger->err('team must have a numeric value');
                $errorStore->addError('o-module-teams:team', 'Your payload needs to indicate team with a numeric value');
            } else {
                $team = $this->getEntityManager()
                    ->getRepository('Teams\Entity\Team')
                    ->findOneBy(['id' => $request->getValue('team')]);
                if (!$team) {
                    $logger->err("a team with that id = {$request->getValue('team')} doesnt exist");

                    $errorStore->addError('o-module-teams:team', new Message(
                        'A team with id %s does not exist.', // @translate
                        $request->getValue('team')));
                }
            }
            if (!$request->getValue($this->getMappedEntityName()) || !is_numeric($request->getValue($this->getMappedEntityName()))) {
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
    }

        public function create(Request $request)
    {
        if ($request->getValue('batch')){
            $this->batchCreate($request);
        }

        $user = $this->getServiceLocator()->get('Omeka\AuthenticationService')->getIdentity();

        $this->validateRequest($request, new ErrorStore());

        $team = $request->getValue('team');
        $resource = $request->getValue('resource-template');
        $this->teamAuthority($request, $request->getValue('team'), $user);
        if (!$this->resourceAuthority($request->getValue('resource'),$user)){
            throw new Exception\PermissionDeniedException('Permission denied for the current user to add this resource to a team.'
            );
        }
        $teamEntity = $this->getEntityManager()->getRepository('Teams\Entity\Team')->findOneBy(['id'=>$team]);
        $resourceEntity = $this->getEntityManager()->getRepository('Omeka\Entity\ResourceTemplate')->findOneBy(['id'=>$resource]);
        $teamResource = new TeamResourceTemplate($teamEntity, $resourceEntity);
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
        AbstractAdapter::batchCreate($request);
    }

    public function update(Request $request)
    {
        AbstractAdapter::batchCreate($request);
    }

    public function batchUpdate(Request $request)
    {
        AbstractAdapter::batchUpdate($request);
    }

    public function delete(Request $request)
    {
        $entity = $this->deleteEntity($request);
        if ($request->getOption('flushEntityManager', true)) {
            $this->getEntityManager()->flush();
        }
        return new Response($entity);
    }

    public function batchDelete(Request $request)
    {
        AbstractAdapter::batchDelete($request);
    }

    public function deleteEntity(Request $request): EntityInterface
    {
        $entity = $this->findEntity(['team'=>$request->getValue('team'), 'resource_template' => $request->getValue('resource-template')]);
        $user = $this->getServiceLocator()->get('Omeka\AuthenticationService')->getIdentity();

        $this->validateRequest($request, new ErrorStore());
        $team = $request->getValue('team');
        $this->teamAuthority($request, $team, $user);

        $event = new Event('api.find.post', $this, [
            'entity' => $entity,
            'request' => $request,
        ]);
        $this->getEventManager()->triggerEvent($event);
        $this->getEntityManager()->remove($entity);
        return $entity;
    }


    public function getMappedEntityClass()
    {
        return ResourceTemplate::class;

    }

    public function getMappedEntityName()
    {
        return 'resource-template';
    }

    public function getEntityClass()
    {
        return TeamResourceTemplate::class;
    }
}
