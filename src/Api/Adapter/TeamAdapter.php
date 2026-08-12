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
        if ($this->shouldHydrate($request, 'o:item_sets')) {
            $submittedItemSetIds = array_map('intval', (array) $request->getValue('o:item_sets', []));
            $recursive = (bool) $request->getValue('o:recursive_item_sets', false);
            $teamId = $entity->getId();

            $apiManager = $this->getServiceLocator()->get('Omeka\ApiManager');

            $existingItemSetIds = $apiManager
                ->search('team-resource', ['team' => $teamId], ['returnScalar' => 'resource'])
                ->getContent();

            // Remove item sets no longer in submitted list.
            foreach ($existingItemSetIds as $existingId) {
                if (!in_array((int) $existingId, $submittedItemSetIds)) {
                    $apiManager->delete(
                        'team-resource',
                        [],
                        ['team' => $teamId, 'resource' => $existingId],
                        ['recursive' => $recursive, 'syncSites' => true, 'flushEntityManager' => false]
                    );
                }
            }

            // Add new item sets.
            foreach ($submittedItemSetIds as $itemSetId) {
                if (!in_array($itemSetId, array_map('intval', $existingItemSetIds))) {
                    $apiManager->create(
                        'team-resource',
                        ['team' => $teamId, 'resource' => $itemSetId],
                        [],
                        ['recursive' => $recursive, 'syncSites' => true, 'flushEntityManager' => false]
                    );
                }
            }
        }

        // Hydrate resource-template associations: accepts o:resource_templates
        // as an array of resource-template IDs.
        if ($this->shouldHydrate($request, 'o:resource_templates')) {
            $submittedTemplateIds = array_map('intval', (array) $request->getValue('o:resource_templates', []));
            $teamId = $entity->getId();

            $apiManager = $this->getServiceLocator()->get('Omeka\ApiManager');

            $existingTemplateIds = $apiManager
                ->search('team-resource-template', ['team' => $teamId], ['returnScalar' => 'resource_template'])
                ->getContent();

            // Remove templates no longer in submitted list.
            foreach ($existingTemplateIds as $existingId) {
                if (!in_array((int) $existingId, $submittedTemplateIds)) {
                    $apiManager->delete(
                        'team-resource-template',
                        [],
                        ['team' => $teamId, 'resource-template' => $existingId],
                        ['flushEntityManager' => false]
                    );
                }
            }

            // Add new templates.
            foreach ($submittedTemplateIds as $templateId) {
                if (!in_array($templateId, array_map('intval', $existingTemplateIds))) {
                    $apiManager->create(
                        'team-resource-template',
                        ['team' => $teamId, 'resource-template' => $templateId],
                        [],
                        ['flushEntityManager' => false]
                    );
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
        return parent::update($request);
    }

    public function batchUpdate(Request $request)
    {
        AbstractAdapter::batchUpdate($request);
    }

    public function batchDelete(Request $request)
    {
        AbstractAdapter::batchDelete($request);
    }
}
