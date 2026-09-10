<?php
namespace Teams\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractAdapter;
use Teams\Api\Representation\TeamRepresentation;
use Teams\Entity\Team;
use Teams\Entity\TeamResource;
use Teams\Entity\TeamResourceTemplate;
use Teams\Entity\TeamSite;
use Teams\Entity\TeamUser;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;
use Omeka\Stdlib\Message;
use Teams\Service\SitePermissionManager;

class TeamAdapter extends AbstractEntityAdapter
{
    use QueryBuilderTrait;

    public function getResourceName()
    {
        return 'team';
    }

    public function getRepresentationClass()
    {
        return TeamRepresentation::class;
    }

    public function getEntityClass()
    {
        return Team::class;
    }

    public function hydrate(
        Request $request,
        EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        if ($this->shouldHydrate($request, 'o:name')) {
            $name = $request->getValue('o:name');
            if (!is_null($name)) {
                $name = trim($name);
                $entity->setName($name);
            }
        }
        if ($this->shouldHydrate($request, 'o:description')) {
            $description = $request->getValue('o:description');
            if (!is_null($description)) {
                $description = trim($description);
                $entity->setDescription($description);
            }
        }

        $em = $this->getEntityManager();

        // Association diffs are only meaningful on UPDATE, when the entity
        // already exists. The AddController handles these independently.
        if (Request::UPDATE === $request->getOperation()) {
        // Hydrate team users: accepts o:team_users as an array of
        // {o:user: {o:id: N}, o:team_role: {o:id: N}} entries.
        if ($this->shouldHydrate($request, 'o:team_users')) {
            $teamUsers = $request->getValue('o:team_users', []);
            $teamId = $entity->getId();

            $submittedUserIds = array_column(
                array_column($teamUsers, 'o:user'),
                'o:id'
            );

            $existingTeamUsers = $em->getRepository(TeamUser::class)
                ->findBy(['team' => $teamId]);

            // Remove users no longer in the submitted list.
            foreach ($existingTeamUsers as $existingTeamUser) {
                $userId = $existingTeamUser->getUser()->getId();
                if (!in_array($userId, $submittedUserIds)) {
                    $em->remove($existingTeamUser);
                }
            }

            // Index existing by user id for quick lookup.
            $existingByUserId = [];
            foreach ($existingTeamUsers as $existingTeamUser) {
                $existingByUserId[$existingTeamUser->getUser()->getId()] = $existingTeamUser;
            }

            foreach ($teamUsers as $teamUserData) {
                $userId = (int) $teamUserData['o:user']['o:id'];
                $roleId = (int) $teamUserData['o:team_role']['o:id'];

                $userEntity = $em->find('Omeka\Entity\User', $userId);
                $roleEntity = $em->find('Teams\Entity\TeamRole', $roleId);

                if (!$userEntity || !$roleEntity) {
                    continue;
                }

                if (isset($existingByUserId[$userId])) {
                    // Update role if changed.
                    $existingByUserId[$userId]->setRole($roleEntity);
                } else {
                    $newTeamUser = new TeamUser($entity, $userEntity, $roleEntity);
                    $em->persist($newTeamUser);
                }
            }
        }

        // Hydrate site associations: accepts o:team_sites as an array of site IDs.
        if ($this->shouldHydrate($request, 'o:team_sites')) {
            $submittedSiteIds = array_map('intval', (array) $request->getValue('o:team_sites', []));
            $teamId = $entity->getId();

            $existingTeamSites = $em->getRepository(TeamSite::class)
                ->findBy(['team' => $teamId]);

            $existingSiteIds = array_map(
                fn(TeamSite $ts) => $ts->getSite()->getId(),
                $existingTeamSites
            );

            // Remove sites no longer in submitted list.
            foreach ($existingTeamSites as $existingTeamSite) {
                if (!in_array($existingTeamSite->getSite()->getId(), $submittedSiteIds)) {
                    $em->remove($existingTeamSite);
                }
            }

            // Add new sites.
            foreach ($submittedSiteIds as $siteId) {
                if (!in_array($siteId, $existingSiteIds)) {
                    $siteEntity = $em->find('Omeka\Entity\Site', $siteId);
                    if ($siteEntity) {
                        $em->persist(new TeamSite($entity, $siteEntity));
                    }
                }
            }
        }

        // Hydrate item-set associations: accepts o:item_sets as an array of
        // resource IDs and o:recursive_item_sets as a boolean flag.
        // Direct EntityManager operations are used here (not the API manager)
        // to avoid nested API calls from within hydrate(), which would trigger
        // Omeka's response-validation and cause a BadResponseException.
        if ($this->shouldHydrate($request, 'o:item_sets')) {
            $submittedItemSetIds = array_map('intval', (array) $request->getValue('o:item_sets', []));
            $recursive = (bool) $request->getValue('o:recursive_item_sets', false);
            $teamId = $entity->getId();

            // Fetch only TeamResource entries whose resource is an item set,
            // so that plain-item associations are not accidentally removed.
            $existingTeamResources = $em->createQuery(
                'SELECT tr FROM Teams\Entity\TeamResource tr'
                . ' JOIN Omeka\Entity\ItemSet iset WITH iset.id = tr.resource'
                . ' WHERE tr.team = :teamId'
            )->setParameter('teamId', $teamId)->getResult();

            $existingItemSetIds = array_map(
                fn(TeamResource $tr) => $tr->getResource()->getId(),
                $existingTeamResources
            );

            // Build a lookup of existing TeamResource entities by resource id.
            $existingByResourceId = [];
            foreach ($existingTeamResources as $tr) {
                $existingByResourceId[$tr->getResource()->getId()] = $tr;
            }

            // Collect team sites once if syncSites is needed.
            $teamSiteEntities = $em->getRepository(TeamSite::class)->findBy(['team' => $teamId]);

            // Remove item sets no longer in submitted list.
            foreach ($existingItemSetIds as $existingId) {
                if (!in_array($existingId, $submittedItemSetIds)) {
                    $resourceEntity = $em->find('Omeka\Entity\Resource', $existingId);
                    if ($resourceEntity) {
                        // Recursively remove child resources when requested.
                        if ($recursive && $resourceEntity->getResourceName() === 'item_sets') {
                            $childResources = $em->getRepository('Omeka\Entity\Resource')
                                ->createQueryBuilder('r')
                                ->join('Omeka\Entity\Item', 'i', 'WITH', 'i.id = r.id')
                                ->join('i.itemSets', 'iset')
                                ->where('iset.id = :itemSetId')
                                ->setParameter('itemSetId', $existingId)
                                ->getQuery()
                                ->getResult();
                            foreach ($childResources as $childResource) {
                                $childTeamResource = $em->getRepository(TeamResource::class)
                                    ->findOneBy(['team' => $teamId, 'resource' => $childResource->getId()]);
                                if ($childTeamResource) {
                                    $this->removeSiteAssociations($childResource, $teamSiteEntities);
                                    $em->remove($childTeamResource);
                                }
                            }
                        }
                        // Sync site associations.
                        $this->removeSiteAssociations($resourceEntity, $teamSiteEntities);
                        $em->remove($existingByResourceId[$existingId]);
                    }
                }
            }

            // Add new item sets.
            foreach ($submittedItemSetIds as $itemSetId) {
                if (!in_array($itemSetId, $existingItemSetIds)) {
                    $resourceEntity = $em->find('Omeka\Entity\Resource', $itemSetId);
                    if (!$resourceEntity) {
                        continue;
                    }
                    $em->persist(new TeamResource($entity, $resourceEntity));
                    // Sync site associations.
                    $this->addSiteAssociations($resourceEntity, $teamSiteEntities);
                    // Recursively add child resources when requested.
                    if ($recursive && $resourceEntity->getResourceName() === 'item_sets') {
                        $childResources = $em->getRepository('Omeka\Entity\Resource')
                            ->createQueryBuilder('r')
                            ->join('Omeka\Entity\Item', 'i', 'WITH', 'i.id = r.id')
                            ->join('i.itemSets', 'iset')
                            ->where('iset.id = :itemSetId')
                            ->setParameter('itemSetId', $itemSetId)
                            ->getQuery()
                            ->getResult();
                        foreach ($childResources as $childResource) {
                            $alreadyExists = $em->getRepository(TeamResource::class)
                                ->findOneBy(['team' => $teamId, 'resource' => $childResource->getId()]);
                            if (!$alreadyExists) {
                                $em->persist(new TeamResource($entity, $childResource));
                                $this->addSiteAssociations($childResource, $teamSiteEntities);
                            }
                        }
                    }
                }
            }
        }

        // Hydrate resource-template associations: accepts o:resource_templates
        // as an array of resource-template IDs. Direct EM ops for same reason
        // as item sets above.
        if ($this->shouldHydrate($request, 'o:resource_templates')) {
            $submittedTemplateIds = array_map('intval', (array) $request->getValue('o:resource_templates', []));
            $teamId = $entity->getId();

            $existingTeamTemplates = $em->getRepository(TeamResourceTemplate::class)
                ->findBy(['team' => $teamId]);

            $existingTemplateIds = array_map(
                fn(TeamResourceTemplate $trt) => $trt->getResourceTemplate()->getId(),
                $existingTeamTemplates
            );

            $existingByTemplateId = [];
            foreach ($existingTeamTemplates as $trt) {
                $existingByTemplateId[$trt->getResourceTemplate()->getId()] = $trt;
            }

            // Remove templates no longer in submitted list.
            foreach ($existingTemplateIds as $existingId) {
                if (!in_array($existingId, $submittedTemplateIds)) {
                    $em->remove($existingByTemplateId[$existingId]);
                }
            }

            // Add new templates.
            foreach ($submittedTemplateIds as $templateId) {
                if (!in_array($templateId, $existingTemplateIds)) {
                    $templateEntity = $em->find('Omeka\Entity\ResourceTemplate', $templateId);
                    if ($templateEntity) {
                        $em->persist(new TeamResourceTemplate($entity, $templateEntity));
                    }
                }
            }
        }
        } // end if (Request::UPDATE === ...)
    }

    public function buildQuery(QueryBuilder $qb, array $query)
    {
        if (isset($query['id'])) {
            $this->buildQueryValuesItself($qb, $query['id'], 'id');
        }

        if (isset($query['name'])) {
            $this->buildQueryValuesItself($qb, $query['name'], 'name');
        }

        if (isset($query['description'])) {
            $this->buildQueryValuesItself($qb, $query['description'], 'description');
        }
    }

    public function sortQuery(QueryBuilder $qb, array $query)
    {
        if (is_string($query['sort_by'])) {
            // TODO Use Doctrine native queries (here: ORM query builder).
            switch ($query['sort_by']) {
                // TODO Sort by count.
                case 'count':
                    break;
                // TODO Sort by user ids.
                case 'users':
                    break;
                // TODO Sort by resource ids.
                case 'resources':
                case 'item_sets':
                case 'items':
                case 'media':
                    break;
                case 'team':
                    $query['sort_by'] = 'name';
                    // no break.
                default:
                    parent::sortQuery($qb, $query);
                    break;
            }
        }
    }

    /**
     * Returns a sanitized string.
     *
     * @param string $string The string to sanitize.
     * @return string The sanitized string.
     */
    protected function sanitizeString($string)
    {
        // Quote is allowed.
        $string = strip_tags($string);
        // The first character is a space and the last one is a no-break space.
        $string = trim($string, ' /\\?<>:*%|"`&; ' . "\t\n\r");
        $string = preg_replace('/[\(\{]/', '[', $string);
        $string = preg_replace('/[\)\}]/', ']', $string);
        $string = preg_replace('/[[:cntrl:]\/\\\?<>\*\%\|\"`\&\;#+\^\$\s]/', ' ', $string);
        //don't allow double apostrophe
        $string = str_replace("''","",$string);
        return trim(preg_replace('/\s+/', ' ', $string));
    }

    /**
     * Returns a light sanitized string.
     *
     * @param string $string The string to sanitize.
     * @return string The sanitized string.
     */
    protected function sanitizeLightString($string)
    {
        return trim(preg_replace('/\s+/', ' ', $string));
    }

    public function validateEntity(EntityInterface $entity, ErrorStore $errorStore)
    {
        $name = $entity->getName();
        if (!$this->isUnique($entity, ['name' => $name])) {
            $errorStore->addError('o:name', new Message(
                'The name "%s" is already taken.', // @translate
                $name
            ));
        }
    }

    protected function validateName($name, ErrorStore $errorStore)
    {
        $result = true;
        $sanitized = $this->sanitizeLightString($name);
        if (is_string($name) && $sanitized !== '') {
            $name = $sanitized;
            $sanitized = $this->sanitizeString($sanitized);
            if ($name !== $sanitized) {
                $errorStore->addError('o:name', new Message(
                    'The name "%s" contains forbidden characters.', // @translate
                    $name
                ));
                $result = false;
            }
            if (preg_match('~^[\d]+$~', $name)) {
                $errorStore->addError('o:name', 'A name can’t contain only numbers.'); // @translate
                $result = false;
            }
            $reserved = [
                'id', 'name', 'comment',
                'show', 'browse', 'add', 'edit', 'delete', 'delete-confirm', 'batch-edit', 'batch-edit-all',
            ];
            if (in_array(strtolower($name), $reserved)) {
                $errorStore->addError('o:name', 'A name cannot be a reserved word.'); // @translate
                $result = false;
            }
        } else {
            $errorStore->addError('o:name', 'A group must have a name.'); // @translate
            $result = false;
        }
        return $result;
    }

    public function validateRequest(Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();
        if (array_key_exists('o:name', $data)) {
            $result = $this->validateName($data['o:name'], $errorStore);
        }
    }

    public function batchCreate(Request $request)
    {
        AbstractAdapter::batchCreate($request);
    }

    public function update(Request $request)
    {
        $teamId = $request->getId();
        $em = $this->getEntityManager();

        // Snapshot pre-update state so we can compute targeted sync diffs.
        // Queried directly from the repositories rather than through the
        // Team entity's o:team_users/o:team_sites collections: hydrate()
        // below persists and removes TeamUser/TeamSite entities directly via
        // the EntityManager (not via those inverse-side collections), so an
        // already-loaded collection on $team would never reflect the change,
        // making an "after" diff computed from it always empty. Repository
        // queries always hit the database, so they see the true state both
        // before and after hydration.
        $beforeUserRoles = $this->getTeamUserRolesByUserId($teamId);
        $beforeSiteIds = $this->getTeamSiteIds($teamId);

        $response = parent::update($request);

        // Sync site permissions based on the user and site diffs.
        $sitePermissionManager = $this->getServiceLocator()->get(SitePermissionManager::class);

        $afterUserRoles = $this->getTeamUserRolesByUserId($teamId);

        $addedUserIds   = array_diff_key($afterUserRoles, $beforeUserRoles);
        $removedUserIds = array_diff_key($beforeUserRoles, $afterUserRoles);
        $keptUserIds    = array_intersect_key($beforeUserRoles, $afterUserRoles);

        foreach (array_keys($addedUserIds) as $userId) {
            $sitePermissionManager->syncSitePermissionsForUser($userId, $teamId, false);
        }
        foreach (array_keys($removedUserIds) as $userId) {
            $sitePermissionManager->removeSitePermissionsForUser($userId, $teamId, null, false);
        }
        foreach (array_keys($keptUserIds) as $userId) {
            if ($beforeUserRoles[$userId] !== $afterUserRoles[$userId]) {
                $sitePermissionManager->syncSitePermissionsForUser($userId, $teamId, false);
            }
        }

        $afterSiteIds   = $this->getTeamSiteIds($teamId);
        $addedSiteIds   = array_diff($afterSiteIds, $beforeSiteIds);
        $removedSiteIds = array_diff($beforeSiteIds, $afterSiteIds);

        foreach ($addedSiteIds as $siteId) {
            $sitePermissionManager->syncSitePermissionsForTeamOnSiteAdded($teamId, $siteId, false);
        }
        foreach ($removedSiteIds as $siteId) {
            $sitePermissionManager->removeSitePermissionsForTeamOnSiteRemoved($teamId, $siteId, false);
        }

        if ($addedUserIds || $removedUserIds || $keptUserIds || $addedSiteIds || $removedSiteIds) {
            $em->flush();
        }

        return $response;
    }

    /**
     * Gets a team's current team-role ID for each of its users, freshly
     * queried from the database.
     *
     * @param int $teamId
     * @return array Team-role ID keyed by user ID.
     */
    private function getTeamUserRolesByUserId(int $teamId): array
    {
        $roles = [];
        $teamUsers = $this->getEntityManager()
            ->getRepository(TeamUser::class)
            ->findBy(['team' => $teamId]);
        foreach ($teamUsers as $teamUser) {
            $roles[$teamUser->getUser()->getId()] = $teamUser->getRole()->getId();
        }
        return $roles;
    }

    /**
     * Gets the IDs of the sites currently associated with a team, freshly
     * queried from the database.
     *
     * @param int $teamId
     * @return int[]
     */
    private function getTeamSiteIds(int $teamId): array
    {
        $teamSites = $this->getEntityManager()
            ->getRepository(TeamSite::class)
            ->findBy(['team' => $teamId]);
        return array_map(fn(TeamSite $ts) => $ts->getSite()->getId(), $teamSites);
    }

    public function batchUpdate(Request $request)
    {
        AbstractAdapter::batchUpdate($request);
    }

    public function batchDelete(Request $request)
    {
        AbstractAdapter::batchDelete($request);
    }

    /**
     * Adds team-site site memberships to a resource entity.
     *
     * Called when an item set or child item is added to a team so that the
     * resource is also associated with every site the team belongs to.
     *
     * @param \Omeka\Entity\Resource $resource
     * @param TeamSite[] $teamSites
     */
    private function addSiteAssociations($resource, array $teamSites): void
    {
        if (!method_exists($resource, 'getSites') || empty($teamSites)) {
            return;
        }
        $itemSites = $resource->getSites();
        $em = $this->getEntityManager();
        foreach ($teamSites as $teamSite) {
            $siteEntity = $em->find('Omeka\Entity\Site', $teamSite->getSite()->getId());
            if ($siteEntity && !$itemSites->contains($siteEntity)) {
                $itemSites->add($siteEntity);
            }
        }
    }

    /**
     * Removes team-site memberships from a resource entity.
     *
     * Called when an item set or child item is removed from a team so that
     * the resource is disassociated from the team's sites.
     *
     * @param \Omeka\Entity\Resource $resource
     * @param TeamSite[] $teamSites
     */
    private function removeSiteAssociations($resource, array $teamSites): void
    {
        if (!method_exists($resource, 'getSites') || empty($teamSites)) {
            return;
        }
        $itemSites = $resource->getSites();
        $em = $this->getEntityManager();
        foreach ($teamSites as $teamSite) {
            $siteEntity = $em->find('Omeka\Entity\Site', $teamSite->getSite()->getId());
            if ($siteEntity) {
                $itemSites->removeElement($siteEntity);
            }
        }
    }
}
