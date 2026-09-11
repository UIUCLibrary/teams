<?php
namespace Teams\Service;

use Doctrine\ORM\EntityManager;
use Omeka\Api\Manager as ApiManager;

/**
 * Keeps Omeka item-site membership (the item_site table) in sync with Teams
 * site associations.
 *
 * Whenever a site is added to or removed from a team, every item belonging
 * to that team must gain or lose membership on that site. Because an item
 * can belong to more than one team, a single item's membership is always
 * recomputed from scratch as the union of the sites granted by every team
 * the item currently belongs to, rather than adding or removing one site in
 * isolation. This guarantees a site is never stripped from an item while
 * another one of the item's teams still grants access to it.
 *
 * All Teams entity reads go through the Omeka API (with bypass_team_filter
 * so this sync is never restricted by the current user's active team). The
 * entity manager is used only to read/write the item's Omeka `Site`
 * collection directly, since Omeka's `items` API adapter exposes no way to
 * add or remove a single site from an item's membership in isolation.
 */
class ItemSiteSyncManager
{
    /**
     * @var ApiManager
     */
    private ApiManager $api;

    /**
     * @var EntityManager
     */
    private EntityManager $entityManager;

    /**
     * @param ApiManager    $api
     * @param EntityManager $entityManager
     */
    public function __construct(ApiManager $api, EntityManager $entityManager)
    {
        $this->api = $api;
        $this->entityManager = $entityManager;
    }

    /**
     * Recompute one item's site membership as the union of the sites
     * granted by every team the item currently belongs to.
     *
     * @param int  $itemId
     * @param bool $flush Whether to flush the entity manager after syncing.
     *                     Pass false when calling in a loop and flush once
     *                     afterward.
     */
    public function syncSitesForItem(int $itemId, bool $flush = true): void
    {
        $em = $this->entityManager;

        $item = $em->find('Omeka\Entity\Item', $itemId);
        if (!$item) {
            return;
        }

        $teamResourceReps = $this->api->search('team-resource', [
            'resource' => $itemId,
            'bypass_team_filter' => true,
        ])->getContent();

        $grantedSiteIds = [];
        foreach ($teamResourceReps as $teamResourceRep) {
            $teamSiteReps = $this->api->search('team-site', [
                'team' => $teamResourceRep->team(),
                'bypass_team_filter' => true,
            ])->getContent();
            foreach ($teamSiteReps as $teamSiteRep) {
                $grantedSiteIds[$teamSiteRep->resource()] = true;
            }
        }
        $grantedSiteIds = array_keys($grantedSiteIds);

        $itemSites = $item->getSites();
        $currentSiteIds = [];
        foreach ($itemSites as $site) {
            $currentSiteIds[] = $site->getId();
        }

        $removeSiteIds = array_diff($currentSiteIds, $grantedSiteIds);
        $addSiteIds = array_diff($grantedSiteIds, $currentSiteIds);

        foreach ($removeSiteIds as $siteId) {
            $site = $itemSites->get($siteId);
            if ($site) {
                $itemSites->removeElement($site);
            }
        }

        foreach ($addSiteIds as $siteId) {
            $site = $em->find('Omeka\Entity\Site', $siteId);
            if ($site) {
                $itemSites->set($site->getId(), $site);
            }
        }

        if ($flush) {
            $em->flush();
        }
    }

    /**
     * Recompute site membership for every item belonging to a team.
     *
     * Call this whenever a team's site associations change (a site is added
     * to or removed from the team): every item on the team needs to gain or
     * lose that site's membership accordingly. Media and item sets are
     * skipped since only items carry site membership.
     *
     * @param int  $teamId
     * @param bool $flush Whether to flush the entity manager after syncing.
     */
    public function syncItemSitesForTeam(int $teamId, bool $flush = true): void
    {
        $teamResourceReps = $this->api->search('team-resource', [
            'team' => $teamId,
            'bypass_team_filter' => true,
        ])->getContent();

        foreach ($teamResourceReps as $teamResourceRep) {
            if ($teamResourceRep->joinResourceName() !== 'items') {
                continue;
            }
            $this->syncSitesForItem($teamResourceRep->resource(), false);
        }

        if ($flush) {
            $this->entityManager->flush();
        }
    }
}
