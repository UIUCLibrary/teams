<?php
namespace Teams\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Teams\Api\Representation\TeamRoleRepresentation;
use Teams\Entity\Team;
use Teams\Entity\TeamRole;
use Teams\Entity\TeamUser;
use Teams\Entity\TeamResource;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Entity\Item;
use Omeka\Entity\ItemSet;
use Omeka\Entity\Media;
use Omeka\Entity\Resource;
use Omeka\Entity\User;
use Omeka\Stdlib\ErrorStore;
use Omeka\Stdlib\Message;

class TeamRoleAdapter extends AbstractEntityAdapter
{
    use QueryBuilderTrait;

    protected $sortFields = [
        'id' => 'id',
        'name' => 'name',
        'comment' => 'comment',
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
        return 'team-role';
    }
//needed for delete
    public function getRepresentationClass()
    {
        return TeamRoleRepresentation::class;
    }
//needed for read
    public function getEntityClass()
    {
        return TeamRole::class;
    }
//two ifs permits create
    public function hydrate(Request $request, EntityInterface $entity,
                            ErrorStore $errorStore
    ) {
        if ($this->shouldHydrate($request, 'name')) {
            $value = $request->getValue('name');
            if (!is_null($value)) {
                $value = trim($value);
                $entity->setName($value);
            }
        }
        if ($this->shouldHydrate($request, 'comment')) {
            $value = $request->getValue('comment');
            if (!is_null($value)) {
                $value = trim($value);
                $entity->setComment($value);
            }
        }

        if ($this->shouldHydrate($request, 'can_add_items')) {
            $value = $request->getValue('can_add_items');
            if (!is_null($value)) {
                if($value == 1 || 0){
                    $entity->setCanAddItems($value);
                }else{
                    $entity->setCanAddItems(null);
                }


            }

        }

        if ($this->shouldHydrate($request, 'can_add_users')) {
            $value = $request->getValue('can_add_users');
            if (!is_null($value)) {

                if($value == 1 || 0){
                    $entity->setCanAddUsers($value);
                }else{
                    $entity->setCanAddUsers(null);
                }

            }
        }

        if ($this->shouldHydrate($request, 'can_add_itemsets')) {
            $value = $request->getValue('can_add_itemsets');
            if (!is_null($value)) {
                if($value == 1 || 0){
                    $entity->setCanAddItemsets($value);
                }else{
                    $entity->setCanAddItemsets(null);
                }
            }
        }

        if ($this->shouldHydrate($request, 'can_modify_resources')) {
            $value = $request->getValue('can_modify_resources');
            if (!is_null($value)) {
                if($value == 1 || 0){
                    $entity->setCanModifyResources($value);
                }else{
                    $entity->setCanModifyResources(null);
                }
            }
        }

        if ($this->shouldHydrate($request, 'can_delete_resources')) {
            $value = $request->getValue('can_delete_resources');
            if (!is_null($value)) {
                if($value == 1 || 0){
                    $entity->setCanDeleteResources($value);
                }else{
                    $entity->setCanDeleteResources(null);
                }
            }
        }


        if ($this->shouldHydrate($request, 'can_add_site_pages')) {
            $value = $request->getValue('can_add_site_pages');
            if (!is_null($value)) {
                if($value == 1 || 0){
                    $entity->setCanAddSitePages($value);
                }else{
                    $entity->setCanAddSitePages(null);
                }
            }
        }
    }



/////3 ifs permit single return via specified column
//    public function buildQuery(QueryBuilder $qb, array $query)
//    {
//        if (isset($query['id'])) {
//            $this->buildQueryValuesItself($qb, $query['id'], 'id');
//        }
//
//        if (isset($query['name'])) {
//            $this->buildQueryValuesItself($qb, $query['name'], 'name');
//        }
//
//        if (isset($query['description'])) {
//            $this->buildQueryValuesItself($qb, $query['description'], 'description');
//        }
//
//    }

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
}
