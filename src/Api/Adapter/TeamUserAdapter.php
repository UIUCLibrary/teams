<?php
namespace Teams\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractAdapter;
use Teams\Api\Representation\TeamUserRepresentation;
use Teams\Entity\TeamUser;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

//legacy from deciding how much of the module to expose to the API

class TeamUserAdapter extends AbstractEntityAdapter
{
    use QueryBuilderTrait;

    protected $sortFields = [
            'id' => 'team'.'user',
        'team' => 'team',
        'user' => 'user',
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

    public function read(Request $request)
    {
        AbstractAdapter::read($request);
    }

    public function search(Request $request)
    {
        AbstractAdapter::search($request);
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

    public function delete(Request $request)
    {
        AbstractAdapter::delete($request);
    }

    public function batchDelete(Request $request)
    {
        AbstractAdapter::batchDelete($request);
    }


    public function hydrate(
        Request $request,
        EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        if ($this->shouldHydrate($request, 'o:team')) {
            $team_id = $request->getValue('o:team');
            if (!is_null($team_id)) {
                $team_id = trim($team_id);
                $entity->setTeamId($team_id);
            }
        }
        if ($this->shouldHydrate($request, 'o:user')) {
            $user_id = $request->getValue('o:user');
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



    public function buildQuery(QueryBuilder $qb, array $query)
    {
        if (isset($query['id'])) {
            $qb->andWhere(
                $qb->expr()->eq(
                "Teams\Entity\TeamUser.id",
                $this->createNamedParameter($qb, $query['id'])
            )
            );
        }

        if (isset($query['team_id'])) {
            $qb->andWhere(
                $qb->expr()->eq(
                "Teams\Entity\TeamUser.team",
                $this->createNamedParameter($qb, $query['team_id'])
            )
            );
        }

        if (isset($query['user_id'])) {
            $qb->andWhere(
                $qb->expr()->eq(
                "Teams\Entity\TeamUser.user",
                $this->createNamedParameter($qb, $query['user_id'])
            )
            );
        }
        if (isset($query['role'])) {
            $qb->andWhere(
                $qb->expr()->eq(
                "Teams\Entity\TeamUser.role",
                $this->createNamedParameter($qb, $query['role'])
            )
            );
        }

        if (isset($query['current'])) {
            $qb->andWhere(
                $qb->expr()->eq(
                "Teams\Entity\TeamUser.is_current",
                $this->createNamedParameter($qb, $query['is_current'])
            )
            );
        }
    }
}
