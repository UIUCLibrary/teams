<?php
namespace Teams\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Omeka\Entity\AbstractEntity;
use Omeka\Entity\User;
use Zend\Form\Annotation\Name;

/**
 *
 * @Entity
 */
class TeamUserId extends AbstractEntity
{
    /**
     * @var int
     * @Id
     * @Column(type="integer")
     */
    protected $id;


    public function getId()
    {
        return $this->id;
    }

}

