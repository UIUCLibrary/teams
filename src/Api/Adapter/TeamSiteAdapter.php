<?php
namespace Teams\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
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
use Teams\Service\ItemSiteSyncManager;
use Teams\Service\SitePermissionManager;

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
            $teamId = $request->getValue('team');
            if (!is_null($teamId)) {
                $team = $this->getEntityManager()
                    ->getRepository('Teams\Entity\Team')
                    ->findOneBy(['id' => (int) $teamId]);
                if ($team) {
                    $entity->setTeam($team);
                }
            }
        }
        if ($this->shouldHydrate($request, 'site')) {
            $siteId = $request->getValue('site');
            if (!is_null($siteId)) {
                $site = $this->getEntityManager()
                    ->getRepository('Omeka\Entity\Site')
                    ->findOneBy(['id' => (int) $siteId]);
                if ($site) {
                    $entity->setSite($site);
                }
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
        $user = $this->getServiceLocator()->get('Omeka\AuthenticationService')->getIdentity();
        $this->validateRequest($request, new ErrorStore());
        $this->teamAuthority($request, $request->getValue('team'), $user);

        $teamEntity = $this->getEntityManager()
            ->getRepository('Teams\Entity\Team')
            ->findOneBy(['id' => $request->getValue('team')]);
        $siteEntity = $this->getEntityManager()
            ->getRepository('Omeka\Entity\Site')
            ->findOneBy(['id' => $request->getValue('site')]);

        $teamSite = new TeamSite($teamEntity, $siteEntity);
        $this->getEntityManager()->persist($teamSite);
        if ($request->getOption('flushEntityManager', true)) {
            $this->getEntityManager()->flush();
            $this->getEntityManager()->refresh($teamSite);
        }

        // Sync Omeka site permissions for all users in the team now that
        // this site has been added to it.
        $this->getServiceLocator()->get(SitePermissionManager::class)
            ->syncSitePermissionsForTeamOnSiteAdded(
                $teamEntity->getId(),
                $siteEntity->getId()
            );

        // Sync item-site membership: every item belonging to this team
        // must now include this site in its own site membership.
        $this->getServiceLocator()->get(ItemSiteSyncManager::class)
            ->syncItemSitesForTeam($teamEntity->getId());

        return new Response($teamSite);
    }

    public function batchCreate(Request $request)
    {
        AbstractAdapter::batchCreate($request);
    }

    public function update(Request $request)
    {
        return parent::update($request);
    }

    public function batchUpdate(Request $request)
    {
        AbstractAdapter::batchUpdate($request);
    }

    public function delete(Request $request)
    {
        $id = $request->getId();
        $entity = $this->getEntityManager()
            ->getRepository(TeamSite::class)
            ->findOneBy(['team' => $id['team'], 'site' => $id['site']]);

        if (!$entity) {
            throw new Exception\NotFoundException(sprintf(
                $this->getTranslator()->translate('TeamSite with team=%1$s site=%2$s not found'),
                $id['team'], $id['site']
            ));
        }

        $this->getEntityManager()->remove($entity);
        if ($request->getOption('flushEntityManager', true)) {
            $this->getEntityManager()->flush();
        }

        // Remove Omeka site permissions for all users in the team now that
        // this site has been removed from it.
        $this->getServiceLocator()->get(SitePermissionManager::class)
            ->removeSitePermissionsForTeamOnSiteRemoved(
                $id['team'],
                $id['site']
            );

        // Sync item-site membership: every item belonging to this team
        // must lose this site's membership, unless another one of the
        // item's teams still grants it.
        $this->getServiceLocator()->get(ItemSiteSyncManager::class)
            ->syncItemSitesForTeam($id['team']);

        return new Response($entity);
    }

    public function batchDelete(Request $request)
    {
        AbstractAdapter::batchDelete($request);
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
