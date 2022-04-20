<?php
namespace Teams\Form\Element;

use Doctrine\ORM\EntityManager;
use Omeka\Api\Manager as ApiManager;
use Laminas\Authentication\AuthenticationService;
use Laminas\Form\Element\Select;
use Laminas\Form\Element\Text;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Helper\Url;

class RoleName extends Text
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @param EntityManager $entityManager
     */
    public function setEntityManager(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }
}
