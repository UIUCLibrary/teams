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
use Omeka\Entity\Site;
use Omeka\Stdlib\ErrorStore;
use Teams\Entity\TeamSite;
use Teams\Api\Representation\TeamSiteRepresentation;

class TeamSiteAdapter extends AbstractTeamEntityAdapter
{
    protected $sortFields = [
        'site' => 'site',
        'team' => 'team',

    ];

    protected $scalarFields =
        [
            'site' => 'site',
            'team' => 'team'
        ];

    public function getResourceName()
    {
        return 'team-site';
    }

    public function getRepresentationClass()
    {
        return TeamSiteRepresentation::class;
    }

    public function getEntityClass()
    {
        return TeamSite::class;
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
        if ($this->shouldHydrate($request, 'site')) {
            $team_site_id = $request->getValue('site');
            if (!is_null($team_site_id)) {
                $team_site_id = trim($team_site_id);
                $entity->setTeamSiteId($team_site_id);
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

        if (isset($query['site'])) {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root' . '.' . 'site',
                $this->createNamedParameter($qb, $query['site'])
            ));        }

    }

    public function buildBaseQuery(QueryBuilder $qb, array $query)
    {
        if (isset($query['id'])) {
            $ids = $query['id'];
            if (!is_array($ids)) {
                $ids = [$ids];
            }
            // Exclude null and empty-string ids. Previous site-only version used
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
        AbstractTeamEntityAdapter::read($request);
    }
    public function create(Request $request)
    {
        AbstractTeamEntityAdapter::create($request);
    }

    public function batchCreate(Request $request)
    {
        AbstractTeamEntityAdapter::batchCreate($request);
    }

    public function update(Request $request)
    {
        AbstractTeamEntityAdapter::batchCreate($request);
    }

    public function batchUpdate(Request $request)
    {
        AbstractTeamEntityAdapter::batchUpdate($request);
    }

    public function delete(Request $request)
    {
        AbstractTeamEntityAdapter::delete($request);
    }

    public function batchDelete(Request $request)
    {
        AbstractTeamEntityAdapter::batchDelete($request);
    }

    public function getMappedEntityClass()
    {
        return Site::class;
    }

    public function getMappedEntityName()
    {
        return 'site';
    }

    public function getMappedEntityDBName()
    {
        return 'site';
    }
}
