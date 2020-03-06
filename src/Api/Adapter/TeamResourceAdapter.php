<?php


namespace Teams\Api\Adapter;


use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;
use Teams\Api\Representation\TeamResourceRepresentation;
use Teams\Entity\TeamResource;

class TeamResourceAdapter extends AbstractEntityAdapter
{

//    use QueryBuilderTrait;

    protected $sortFields = [
        'resource_id' => 'resource_id',
        'team_id' => 'team_id',
        // For info.
        // 'count' => 'count',
        // 'users' => 'users',
        // 'resources' => 'resources',
        // 'item_sets' => 'item_sets',
        // 'items' => 'items',
        // 'media' => 'media',
        // 'recent' => 'recent',
    ];

// Class Teams\Api\Adapter\TeamAdapter contains 1 abstract method and must therefore be declared abstract or implement
// the remaining methods (Omeka\Api\Adapter\AdapterInterface::getResourceName)
    public function getResourceName()
    {
        return 'team-resource';
    }
//needed for delete
    public function getRepresentationClass()
    {
        return TeamResourceRepresentation::class;
    }
//needed for read
    public function getEntityClass()
    {
        return TeamResource::class;
    }
//two ifs permits create
    public function hydrate(Request $request, EntityInterface $entity,
                            ErrorStore $errorStore
    ) {
        if ($this->shouldHydrate($request, 'team')) {
            $name = $request->getValue('team');
            if (!is_null($name)) {
                $name = trim($name);
                $entity->setName($name);
            }
        }
        if ($this->shouldHydrate($request, 'resource')) {
            $description = $request->getValue('resource');
            if (!is_null($description)) {
                $description = trim($description);
                $entity->setDescription($description);
            }
        }
    }

//    public function validateRequest(Request $request, ErrorStore $errorStore)
//    {
//        $data = $request->getContent();
//        if (array_key_exists('o:name', $data)) {
//            $result = $this->validateName($data['o:name'], $errorStore);
//        }
//    }

//    public function validateEntity(EntityInterface $entity, ErrorStore $errorStore)
//    {
//        $name = $entity->getName();
//        $result = $this->validateName($name, $errorStore);
//        if (!$this->isUnique($entity, ['name' => $name])) {
//            $errorStore->addError('o:name', new Message(
//                'The name "%s" is already taken.', // @translate
//                $name
//            ));
//        }
//    }
//
//    /**
//     * Validate a name.
//     *
//     * @param string $name
//     * @param ErrorStore $errorStore
//     * @return bool
//     */
//    protected function validateName($name, ErrorStore $errorStore)
//    {
//        $result = true;
//        $sanitized = $this->sanitizeLightString($name);
//        if (is_string($name) && $sanitized !== '') {
//            $name = $sanitized;
//            $sanitized = $this->sanitizeString($sanitized);
//            if ($name !== $sanitized) {
//                $errorStore->addError('o:name', new Message(
//                    'The name "%s" contains forbidden characters.', // @translate
//                    $name
//                ));
//                $result = false;
//            }
//            if (preg_match('~^[\d]+$~', $name)) {
//                $errorStore->addError('o:name', 'A name can’t contain only numbers.'); // @translate
//                $result = false;
//            }
//            $reserved = [
//                'id', 'name', 'description',
//                'show', 'browse', 'add', 'edit', 'delete', 'delete-confirm', 'batch-edit', 'batch-edit-all',
//            ];
//            if (in_array(strtolower($name), $reserved)) {
//                $errorStore->addError('o:name', 'A name cannot be a reserved word.'); // @translate
//                $result = false;
//            }
//        } else {
//            $errorStore->addError('o:name', 'A team must have a name.'); // @translate
//            $result = false;
//        }
//        return $result;
//    }

///3 ifs permit single return via specified column
    public function buildQuery(QueryBuilder $qb, array $query)
    {
        if (isset($query['id'])) {
            $this->buildQueryValuesItself($qb, $query['team'], 'team');
        }

        if (isset($query['name'])) {
            $this->buildQueryValuesItself($qb, $query['resource'], 'resource');
        }

//
//        // All teams for these entities ("OR"). If multiple, mixed with "AND",
//        // so, for mixed resources, use "resource_id".
//        $mapResourceTypes = [
//            'user_id' => User::class,
//            'resource_id' => Resource::class,
//            'item_set_id' => ItemSet::class,
//            'item_id' => Item::class,
//            'media_id' => Media::class,
//        ];
//        $subQueryKeys = array_intersect_key($mapResourceTypes, $query);
//        foreach ($subQueryKeys as $queryKey => $resourceType) {
//            if ($queryKey === 'user_id') {
//                $teamEntity = TeamUser::class;
//                $teamEntityColumn = 'user';
//            } else {
//                $teamEntity = TEamResource::class;
//                $teamEntityColumn = 'resource';
//            }
//            $entities = is_array($query[$queryKey]) ? $query[$queryKey] : [$query[$queryKey]];
//            $entities = array_filter($entities, 'is_numeric');
//            if (empty($entities)) {
//                continue;
//            }
//            $teamEntityAlias = $this->createAlias();
//            $entityAlias = $this->createAlias();
//            $qb
//                // Note: This query may be used if the annotation is set in
//                // core on Resource. In place, the relation is recreated.
//                // ->innerJoin(
//                //     $this->getEntityClass() . ($queryKey === 'user_id' ?  '.users' : '.resources'),
//                //     $entityAlias, 'WITH',
//                //     $qb->expr()->in("$entityAlias.id", $this->createNamedParameter($qb, $entities))
//                // );
//                ->innerJoin(
//                    $teamEntity,
//                    $teamEntityAlias,
//                    'WITH',
//                    $qb->expr()->andX(
//                        $qb->expr()->eq($teamEntityAlias . '.team', $this->getEntityClass() . '.id'),
//                        $qb->expr()->in(
//                            $teamEntityAlias . '.' . $teamEntityColumn,
//                            $this->createNamedParameter($qb, $entities)
//                        )
//                    )
//                );
//            // This check avoids bad result for bad request mixed ids.
//            if (!in_array($queryKey, ['user_id', 'resource_id'])) {
//                $resourceAlias = $this->createAlias();
//                $qb
//                    ->innerJoin(
//                        $resourceType,
//                        $resourceAlias,
//                        'WITH',
//                        $qb->expr()->eq(
//                            $teamEntityAlias . '.resource',
//                            $resourceAlias . '.id'
//                        )
//                    );
//            }
//        }
//
//        if (array_key_exists('resource_type', $query)) {
//            $mapResourceTypes = [
//                'users' => User::class,
//                'resources' => Resource::class,
//                'item_sets' => ItemSet::class,
//                'items' => Item::class,
//                'media' => Media::class,
//            ];
//            if (isset($mapResourceTypes[$query['resource_type']])) {
//                $entityJoinClass = $query['resource_type'] === 'users'
//                    ? TeamUser::class
//                    : TeamResource::class;
//                $entityJoinAlias = $this->createAlias();
//                $qb
//                    ->linnerJoin(
//                        $entityJoinClass,
//                        $entityJoinAlias,
//                        'WITH',
//                        $qb->expr()->eq($entityJoinAlias . '.team', Team::class)
//                    );
//                if (!in_array($query['resource_type'], ['users', 'resources'])) {
//                    $entityAlias = $this->createAlias();
//                    $qb
//                        ->innerJoin(
//                            $mapResourceTypes[$query['resource_type']],
//                            $entityAlias,
//                            'WITH',
//                            $qb->expr()->eq(
//                                $entityJoinClass . '.resource',
//                                $entityAlias . '.id'
//                            )
//                        );
//                }
//            } elseif ($query['resource_type'] !== '') {
//                $qb
//                    ->andWhere('1 = 0');
//            }
//        }
    }

//    public function sortQuery(QueryBuilder $qb, array $query)
//    {
//        if (is_string($query['sort_by'])) {
//            // TODO Use Doctrine native queries (here: ORM query builder).
//            switch ($query['sort_by']) {
//                // TODO Sort by count.
//                case 'count':
//                    break;
//                // TODO Sort by user ids.
//                case 'users':
//                    break;
//                // TODO Sort by resource ids.
//                case 'resources':
//                case 'item_sets':
//                case 'items':
//                case 'media':
//                    break;
//                case 'team':
//                    $query['sort_by'] = 'name';
//                // No break.
//                default:
//                    parent::sortQuery($qb, $query);
//                    break;
//            }
//        }
//    }


}