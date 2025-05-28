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

        if ($this->shouldHydrate($request, 'o:role')) {
            $role = $request->getValue('o:role');
            if (!is_null($role)) {
                $role = trim($role);
                $entity->setRole($role);
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

    public function read(Request $request)
    {
        AbstractAdapter::read($request);
    }
    public function create(Request $request)
    {
        AbstractAdapter::create($request);
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
        AbstractAdapter::delete($request);
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
