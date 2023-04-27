<?php
namespace Teams\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractAdapter;
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

//legacy from deciding how much of the module to expose to the API
class TeamRoleAdapter extends AbstractEntityAdapter
{
    use QueryBuilderTrait;

    protected $sortFields = [
        'id' => 'id',
        'name' => 'name',
        'comment' => 'comment',

    ];


    public function getResourceName()
    {
        return 'team-role';
    }
    public function getRepresentationClass()
    {
        return TeamRoleRepresentation::class;
    }

    public function getEntityClass()
    {
        return TeamRole::class;
    }

    public function hydrate(
        Request $request,
        EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        if ($this->shouldHydrate($request, 'o:name')) {
            $value = $request->getValue('o:name');
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
                if ($value == 1 || 0) {
                    $entity->setCanAddItems($value);
                } else {
                    $entity->setCanAddItems(null);
                }
            }
        }

        if ($this->shouldHydrate($request, 'can_add_users')) {
            $value = $request->getValue('can_add_users');
            if (!is_null($value)) {
                if ($value == 1 || 0) {
                    $entity->setCanAddUsers($value);
                } else {
                    $entity->setCanAddUsers(null);
                }
            }
        }

        if ($this->shouldHydrate($request, 'can_add_itemsets')) {
            $value = $request->getValue('can_add_itemsets');
            if (!is_null($value)) {
                if ($value == 1 || 0) {
                    $entity->setCanAddItemsets($value);
                } else {
                    $entity->setCanAddItemsets(null);
                }
            }
        }

        if ($this->shouldHydrate($request, 'can_modify_resources')) {
            $value = $request->getValue('can_modify_resources');
            if (!is_null($value)) {
                if ($value == 1 || 0) {
                    $entity->setCanModifyResources($value);
                } else {
                    $entity->setCanModifyResources(null);
                }
            }
        }

        if ($this->shouldHydrate($request, 'can_delete_resources')) {
            $value = $request->getValue('can_delete_resources');
            if (!is_null($value)) {
                if ($value == 1 || 0) {
                    $entity->setCanDeleteResources($value);
                } else {
                    $entity->setCanDeleteResources(null);
                }
            }
        }


        if ($this->shouldHydrate($request, 'can_add_site_pages')) {
            $value = $request->getValue('can_add_site_pages');
            if (!is_null($value)) {
                if ($value == 1 || 0) {
                    $entity->setCanAddSitePages($value);
                } else {
                    $entity->setCanAddSitePages(null);
                }
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
                'id', 'o:name', 'comment',
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
