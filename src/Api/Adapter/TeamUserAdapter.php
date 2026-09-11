<?php
namespace Teams\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Laminas\EventManager\Event;
use Omeka\Api\Adapter\AbstractAdapter;
use Omeka\Api\Response;
use Omeka\Api\Exception;
use Omeka\Entity\User;
use Teams\Api\Representation\TeamUserRepresentation;
use Teams\Entity\TeamRole;
use Teams\Service\SitePermissionManager;
use Teams\Entity\TeamUser;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

//legacy from deciding how much of the module to expose to the API

class TeamUserAdapter extends AbstractTeamEntityAdapter
{
    use QueryBuilderTrait;

    protected $sortFields = [
        'team' => 'team',
        'user' => 'user',
    ];

    protected $scalarFields = [
        'user' => 'user',
        'team' => 'team',
    ];

    public function getResourceName()
    {
        return 'team_user';
    }

    public function getRepresentationClass()
    {
        return TeamUserRepresentation::class;
    }

    public function getEntityClass()
    {
        return TeamUser::class;
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
        if ($this->shouldHydrate($request, 'user')) {
            $user_id = $request->getValue('user');
            if (!is_null($user_id)) {
                $user_id = trim($user_id);
                $entity->setUserId($user_id);
            }
        }

        // Accept 'role' (plain key used by update) or 'o:role' (JSON-LD key).
        // Both must resolve to a TeamRole entity before setting.
        $roleId = null;
        if ($this->shouldHydrate($request, 'role')) {
            $roleId = $request->getValue('role');
        } elseif ($this->shouldHydrate($request, 'o:role')) {
            $roleId = $request->getValue('o:role');
        }
        if (!is_null($roleId)) {
            $roleEntity = $this->getEntityManager()->find('Teams\Entity\TeamRole', (int) $roleId);
            if ($roleEntity) {
                $entity->setRole($roleEntity);
            }
        }

        if ($this->shouldHydrate($request, 'o:current')) {
            $current = $request->getValue('o:current');
            if (!is_null($current)) {
                $entity->setCurrent($current);
            }
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



    public function buildQuery(QueryBuilder $qb, array $query)
    {

        if (isset($query['team'])) {
            $qb->andWhere(
                $qb->expr()->eq(
                    'omeka_root' . '.' . 'team',
                $this->createNamedParameter($qb, $query['team'])
            )
            );
        }

        if (isset($query['user'])) {
            $qb->andWhere(
                $qb->expr()->eq(
                    'omeka_root' . '.' . 'user',
                $this->createNamedParameter($qb, $query['user'])
            )
            );
        }
        if (isset($query['role'])) {
            $qb->andWhere(
                $qb->expr()->eq(
                    'omeka_root' . '.' . 'role',
                $this->createNamedParameter($qb, $query['role'])
            )
            );
        }

        if (isset($query['current'])) {
            $qb->andWhere(
                $qb->expr()->eq(
                    'omeka_root' . '.' . 'is_current',
                $this->createNamedParameter($qb, $query['current'])
            )
            );
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
        $resource = $request->getValue('user');
        $role = $request->getValue('role');
        $this->teamAuthority($request, $request->getValue('team'), $user);
        if (!$this->resourceAuthority($request->getValue('resource'),$user)){
            throw new Exception\PermissionDeniedException('Permission denied for the current user to add this resource to a team.'
            );
        }
        $teamEntity = $this->getEntityManager()->getRepository('Teams\Entity\Team')->findOneBy(['id'=>$team]);
        $resourceEntity = $this->getEntityManager()->getRepository('Omeka\Entity\User')->findOneBy(['id'=>$resource]);
        $roleEntity = $this->getEntityManager()->getRepository('Teams\Entity\TeamRole')->findOneBy(['id'=>$role]);
        $teamResource = new TeamUser($teamEntity, $resourceEntity, $roleEntity);


        $this->getEntityManager()->persist($teamResource);
        if ($request->getOption('flushEntityManager', true)) {
            $this->getEntityManager()->flush();
            // Refresh the entity on the chance that it contains associations
            // that have not been loaded.
            $this->getEntityManager()->refresh($teamResource);
        }

        // Sync Omeka site permissions for the user now that the TeamUser row exists.
        $this->getServiceLocator()->get(SitePermissionManager::class)
            ->syncSitePermissionsForUser(
                $teamResource->getUser()->getId(),
                $teamResource->getTeam()->getId()
            );

        return new Response($teamResource);
    }

    public function batchCreate(Request $request)
    {
        AbstractAdapter::batchCreate($request);
    }

    public function update(Request $request)
    {
        $response = parent::update($request);

        // Re-sync site permissions in case the role changed.
        $teamUser = $response->getContent();
        if ($teamUser instanceof TeamUser) {
            $this->getServiceLocator()->get(SitePermissionManager::class)
                ->syncSitePermissionsForUser(
                    $teamUser->getUser()->getId(),
                    $teamUser->getTeam()->getId()
                );
        }

        return $response;
    }

    public function batchUpdate(Request $request)
    {
        AbstractAdapter::batchUpdate($request);
    }


    public function batchDelete(Request $request)
    {
        AbstractAdapter::batchDelete($request);
    }

    public function getMappedEntityClass()
    {
        return User::class;
    }

    public function getMappedEntityName()
    {
        return 'user';
    }

    public function getMappedEntityDBName()
    {
        return 'user';
    }
}
