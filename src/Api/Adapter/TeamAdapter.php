<?php
namespace Teams\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractAdapter;
use Teams\Api\Representation\TeamRepresentation;
use Teams\Entity\Team;
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
        AbstractAdapter::batchCreate($request);
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
