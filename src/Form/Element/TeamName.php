<?php
namespace Teams\Form\Element;

use Doctrine\ORM\EntityManager;
use Omeka\Api\Manager as ApiManager;
use Zend\Authentication\AuthenticationService;
use Zend\Form\Element\Select;
use Zend\Form\Element\Text;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Helper\Url;

Class TeamName extends Text
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