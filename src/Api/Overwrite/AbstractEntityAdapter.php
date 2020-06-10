<?php
namespace Omeka\Api\Adapter;

use DateTime;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Omeka\Api\Exception;
use Omeka\Api\Request;
use Omeka\Api\Response;
use Omeka\Entity\ItemSet;
use Omeka\Entity\User;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;
use Zend\EventManager\Event;
use Omeka\Entity\Site;

/**
 * Abstract entity API adapter.
 */
abstract class OverwriteAbstractEntityAdapter extends AbstractEntityAdapter
{

}
