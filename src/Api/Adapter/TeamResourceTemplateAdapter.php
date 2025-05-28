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

    protected $scalarFields = [
        'resource_template' => 'resource_template',
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

    public function getMappedEntityDBName()
    {
        return 'resource_template';
    }


    public function getEntityClass()
    {
        return TeamResourceTemplate::class;
    }
}
