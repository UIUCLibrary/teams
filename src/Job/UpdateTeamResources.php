<?php

namespace Teams\Job;

use Doctrine\DBAL\Connection;
use Omeka\Job\Exception\InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Update resources assigned to a team.
 *
 * The job accepts two arguments:
 *
 * - teams: An array of item queries keyed by their respective site IDs
 * - action: The update action
 *   - add: Keep existing items and assign the result set
 *   - replace: Unassign all items and assign the result set
 *   - remove: Unassign all items in the result set
 *   - remove_all: Unassign all items
 */
class UpdateTeamResources extends \Omeka\Job\UpdateSiteItems
{
    public function perform()
    {
        $services = $this->getServiceLocator();
        $conn = $services->get('Omeka\Connection');

        $action = $this->getArg('action');
        $teams = $this->getArg('teams');

        // Validate the user data.
        if (!is_string($action)) {
            throw new InvalidArgumentException('No "action" string passed to the UpdateTeamResources job');
        }
        if (!in_array($action, $this->actions)) {
            throw new InvalidArgumentException(sprintf('Invalid "action" string "%s" passed to the UpdateTeamResources job', $action));
        }
        if (!is_array($teams)) {
            throw new InvalidArgumentException('No "teams" array passed to the UpdateTeamResources job');
        }
        foreach ($teams as $teamId => $query) {
            if (!is_array($query)) {
                // If the query is not an array, assume an all-inclusive query.
                $teams[$teamId] = [];
            }
            $teamExists = $conn->fetchColumn('SELECT 1 FROM team WHERE id = ?', [$teamId], 0);
            if (false === $teamExists) {
                throw new InvalidArgumentException(sprintf('Invalid team ID "%s" passed to the UpdateTeamResources job', $teamId));
            }
        }

        // Update the team resource assignments.
        foreach ($teams as $teamId => $query) {
            $this->updateTeamResources($teamId, $query, $action);
        }    }

    /**
     * Update resource assignments for one team.
     *
     * Note that we chunk item IDs to avoid query/buffer/packet size limits.
     *
     * @param int $teamId
     * @param array $query
     * @param string $action
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function updateTeamResources(int $teamId, array $query, string $action) : void
    {
        $logger = $this->getServiceLocator()->get('Omeka\Logger');

        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $conn = $services->get('Omeka\Connection');

        //we need to cover items, item-sets, resource templates, media, so we'd need to do some kind of
        //array merge after fetching the ids from those other endpoints. Then, resource templates are not
        //in the resource table or team_resource table, so they would need to be done separately


        //get the item ids before adding media ids to sync item_site
        $itemIds = $api->search('items', $query, ['returnScalar' => 'id'])->getContent();

        $resourceIds = $itemIds;

        $sites = $api->search('team-site', ['team' => $teamId], ['returnScalar' => 'site'])->getContent();

        foreach($sites as $siteId) {
            $this->syncItemSites($siteId, $teamId, $action, $itemIds);
        }

        //get the item's media
        $resources = $api->search('items', $query)->getContent();

        foreach ($resources as $resource) {
            if (method_exists($resource, 'media')){
                $media = $resource->media();
                foreach ($media as $m) {
                   $resourceIds[] =  $m->id();
                }
            }
        }

        if (in_array($action, ['replace', 'remove_all'])) {
            $conn->delete('team_resource', ['team_id' => $teamId]);
        }

        if (in_array($action, ['add', 'replace'])) {
            foreach (array_chunk($resourceIds, 1000) as $resourceIdsChunk) {
                $values = [];
                $bindValues = [];
                foreach ($resourceIdsChunk as $resourceId) {
                    $values[] = '(?,?)';
                    $bindValues[] = $resourceId;
                    $bindValues[] = $teamId;
                }
                // Note the use of IGNORE here to prevent duplicate-key errors.
                $sql = sprintf('INSERT IGNORE INTO team_resource (resource_id, team_id) VALUES %s', implode(',', $values));
                $stmt = $conn->prepare($sql);
                foreach ($bindValues as $position => $value) {
                    $stmt->bindValue($position + 1, $value);
                }
                $stmt->execute();
            }
        }

        if (in_array($action, ['remove'])) {
            foreach (array_chunk($resourceIds, 1000) as $resourceIdsChunk) {
                $sql = sprintf('DELETE FROM team_resource WHERE team_id = ? AND resource_id IN (?)');
                $stmt = $conn->executeQuery($sql, [$teamId, $resourceIdsChunk], [null, Connection::PARAM_INT_ARRAY]);
                $stmt->execute();
            }
        }
    }

    public function syncItemSites(int $siteId, int $teamId, string $action, array $resourceIds)
    {
        $logger = $this->getServiceLocator()->get('Omeka\Logger');

        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $conn = $services->get('Omeka\Connection');

        $removalResourceIds = array();
        $additionalResourceIds = array();

        if (in_array($action, ['replace', 'remove_all']))
        {
            $removalResourceIds = $api->search('team-resource', ['team' => $teamId], ['returnScalar'=>'resource'])->getContent();
        }
        if ($action == 'remove') {
            $removalResourceIds = $resourceIds;
        }
        if (in_array($action,['add', 'replace'] )) {
            $additionalResourceIds = $resourceIds;
        }

        //remove items from sites
        foreach (array_chunk($removalResourceIds, 1000) as $resourceIdsChunk) {
            $sql = sprintf('DELETE FROM item_site WHERE site_id = ? AND item_id IN (?)');
            $stmt = $conn->executeQuery($sql, [$siteId, $resourceIdsChunk], [null, Connection::PARAM_INT_ARRAY]);
            $stmt->execute();
        }

        //add items to sites
        foreach (array_chunk($additionalResourceIds, 1000) as $resourceIdsChunk) {
            $values = [];
            $bindValues = [];
            foreach ($resourceIdsChunk as $resourceId) {
                $values[] = '(?,?)';
                $bindValues[] = $resourceId;
                $bindValues[] = $siteId;
            }
            // Note the use of IGNORE here to prevent duplicate-key errors.
            $sql = sprintf('INSERT IGNORE INTO item_site (item_id, site_id) VALUES %s', implode(',', $values));
            $stmt = $conn->prepare($sql);
            foreach ($bindValues as $position => $value) {
                $stmt->bindValue($position + 1, $value);
            }
            $stmt->execute();
        }
    }
}