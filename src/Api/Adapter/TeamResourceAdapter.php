<?php
namespace Teams\Api\Adapter;

use CSVImport\Api\Adapter\EntityAdapter;
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

    protected $scalarFields = [
        'team'=>'team',
        'resource'=>'resource',
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
    public function getMappedEntityDBName()
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
            ));
        }

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
        if ($request->getOption('recursive')){
            $syncSites = (bool)$request->getOption('syncSites');
            $mappedEntity = $this->findMappedEntity($request->getValue('resource'));
            //item sets => contain items
            if ($mappedEntity->getResourceName() === 'item_sets') {
                $childIds = $this->getServiceLocator()->get('Omeka\ApiManager')
                    ->search('items', ['item_set_id' => $mappedEntity->getId(), 'bypass_team_filter'=>true], ['returnScalar' => 'id'])
                    ->getContent();
                foreach ($childIds as $resourceId) {
                        $exists = $this->getServiceLocator()->get('Omeka\ApiManager')
                            ->search('team-resource',
                                [
                                    'team' => $request->getValue('team'),
                                    'resource' => $resourceId
                                ])
                            ->getContent();
                        if (count($exists) < 1)
                        {
                            $this->getServiceLocator()
                                ->get('Omeka\ApiManager')
                                ->create('team-resource',
                                    [
                                        'team' => $request->getValue('team'),
                                        'resource' => $resourceId
                                    ],
                                    [],
                                    [
                                        'recursive'=>true,
                                        'syncSites' => $syncSites
                                    ]
                                );
                        }
                    }
                }
                //items => contain media
            if ($mappedEntity->getResourceName() === 'items') {

                $childIds = [];
                if (method_exists($mappedEntity, 'getMedia')){
                    $media = $mappedEntity->getMedia();
                    foreach ($media as $m) {
                        $childIds[] =  $m->getId();
                    }
                }
                foreach ($childIds as $resourceId) {
                    $exists = $this->getServiceLocator()->get('Omeka\ApiManager')
                        ->search('team-resource',
                            [
                                'team' => $request->getValue('team'),
                                'resource' => $resourceId
                            ])
                        ->getContent();
                    if (count($exists) < 1)
                    {
                        $this->getServiceLocator()
                            ->get('Omeka\ApiManager')
                            ->create('team-resource',
                                [
                                    'team' => $request->getValue('team'),
                                    'resource' => $resourceId
                                ],
                                ['recursive'=>false]
                            );
                    }
                }
            }
        }
        if ($request->getOption('syncSites')){
            $sites = $this->getServiceLocator()->get('Omeka\ApiManager')
                ->search('team-site', ['team' => $team], ['returnScalar' => 'site'])
                ->getContent();

            if (method_exists($resourceEntity, 'getSites')) {
                $siteAdapter = $this->getServiceLocator()->get('Omeka\ApiAdapterManager')->get('sites');
                $item_sites = $resourceEntity->getSites();
                foreach ($sites as $site) {
                    $add_site = $siteAdapter->findEntity($site);
                    $item_sites->set($add_site->getId(), $add_site);
                }
            }

        }

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
        EntityAdapter::batchCreate($request);
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
        if ($request->getOption('recursive')){
            $syncSites = (bool)($request->getOption('syncSites'));
            $mappedEntity = $this->findMappedEntity($request->getValue('resource'));
            //item sets => contain items
            if ($mappedEntity->getResourceName() === 'item_sets') {
                $childIds = $this->getServiceLocator()->get('Omeka\ApiManager')
                    ->search('items', ['item_set_id' => $mappedEntity->getId(), 'bypass_team_filter'=>true], ['returnScalar' => 'id'])
                    ->getContent();
                foreach ($childIds as $resourceId) {
                    $exists = $this->getServiceLocator()->get('Omeka\ApiManager')
                        ->search('team-resource', ['team' => $request->getValue('team'), 'resource' => $resourceId] )
                        ->getContent();
                    if (count($exists) > 0)
                    {
                        $this->getServiceLocator()
                            ->get('Omeka\ApiManager')
                            ->delete('team-resource',
                                [],
                                [
                                    'team' => $request->getValue('team'),
                                    'resource' => $resourceId
                                ],
                                [
                                    'recursive' => true,
                                    'syncSites' => $syncSites,
                                ]);
                    }
                }
            }

            if ($mappedEntity->getResourceName() === 'items') {
                $childIds = [];
                if (method_exists($mappedEntity, 'getMedia')){
                    $media = $mappedEntity->getMedia();
                    foreach ($media as $m) {
                        $childIds[] =  $m->getId();
                    }
                }

                foreach ($childIds as $resourceId) {
                    $exists = $this->getServiceLocator()->get('Omeka\ApiManager')
                        ->search('team-resource', ['team' => $request->getValue('team'), 'resource' => $resourceId])
                        ->getContent();
                    if (count($exists) > 0)
                    {
                        $this->getServiceLocator()
                            ->get('Omeka\ApiManager')
                            ->delete('team-resource',
                                [],
                                [
                                    'team' => $request->getValue('team'),
                                    'resource' => $resourceId
                                ]
                            );
                    }
                }
            }
        }

        $exists = $this->getServiceLocator()->get('Omeka\ApiManager')
            ->search('team-resource', ['team' => $request->getValue('team'), 'resource' => $request->getValue('resource')])
            ->getContent();
        if (count($exists) > 0) {
            $entity = $this->deleteEntity($request);
        }

        if ($request->getOption('syncSites')){
            $sites = $this->getServiceLocator()->get('Omeka\ApiManager')
                ->search('team-site', ['team' => $request->getValue('team')], ['returnScalar' => 'site'])
                ->getContent();
            $resourceEntity = $this->getEntityManager()
                ->getRepository('Omeka\Entity\Resource')
                ->findOneBy(['id'=>$request->getValue('resource')]);

            if (method_exists($resourceEntity, 'getSites')) {
                $item_sites = $resourceEntity->getSites();
                foreach ($sites as $site) {
                    $target_site = $item_sites->get($site);
                    $item_sites->removeElement($target_site);
                }
            }

        }


        if ($request->getOption('flushEntityManager', true)) {
            $this->getEntityManager()->flush();
        }
        return new Response($entity);
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
            if(!$request->getValue('team') || !is_numeric($request->getValue('team'))){
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
            if(!$request->getValue('resource') || !is_numeric($request->getValue('resource'))){
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
    public function deleteEntity(Request $request): EntityInterface
    {
        $entity = $this->findEntity(['team'=>$request->getValue('team'), 'resource' => $request->getValue('resource')]);
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
    public function teamAuthority($request, $team, $user, $resource=null)
    {
        $em = $this->getEntityManager();
        $operation = $request->getOperation();
        $logger = $this->getServiceLocator()->get('Omeka\Logger');
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
     * @param $resource
     * @param User $user
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
