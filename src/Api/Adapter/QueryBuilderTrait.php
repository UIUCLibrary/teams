<?php
namespace Teams\Api\Adapter;

use Doctrine\ORM\QueryBuilder;

/**
 * Trait to build queries.
 */
trait QueryBuilderTrait
{

// on delete Call to undefined method Teams\Api\Adapter\TeamAdapter::buildQueryOneValue()
    /**
     * Helper to search one value.
     *
     * @param QueryBuilder $qb
     * @param mixed $value
     * @param string $column
     */
    protected function buildQueryOneValue(QueryBuilder $qb, $value, $column)
    {
        if (is_null($value)) {
            $qb->andWhere($qb->expr()->isNull(
                'omeka_root' . '.' . $column
            ));
        } else {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root' . '.' . $column,
                $this->createNamedParameter($qb, $value)
            ));
        }
    }


// on details Call to undefined method Teams\Api\Adapter\TeamAdapter::buildQueryValuesItself()
    /**
     * Helper to search one or multiple values on the same entity.
     *
     * @param QueryBuilder $qb
     * @param mixed $values One or multiple values.
     * @param string $target
     */
    protected function buildQueryValuesItself(QueryBuilder $qb, $values, $target)
    {
        if (is_array($values)) {
            if (count($values) == 1) {
                $this->buildQueryOneValue($qb, reset($values), $target);
            } else {
                $this->buildQueryMultipleValuesItself($qb, $values, $target);
            }
        } else {
            $this->buildQueryOneValue($qb, $values, $target);
        }
    }
}
