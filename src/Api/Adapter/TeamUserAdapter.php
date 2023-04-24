<?php
namespace Teams\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Laminas\EventManager\Event;
use Omeka\Api\Adapter\AbstractAdapter;
use Omeka\Api\Response;
use Omeka\Api\Exception;
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

    public function search(Request $request)
    {
        $search_fields = array();
        $group_by = 'team'; //default order by
        $query = $request->getContent();

        if ( array_key_exists('team', $query) ) {
            $search_fields['team'] = $query['team'];
            $group_by = 'user';
        } elseif (array_key_exists('user', $query)) {
            $search_fields['user'] = $query['user'];
        } else {
            throw new Exception\BadRequestException(sprintf(
                $this->getTranslator()->translate('%1$s entity requires team or user search criteria'),
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
}
