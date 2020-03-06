<?php
namespace Teams\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Omeka\Entity\AbstractEntity;
use Zend\Form\Annotation\Name;

/**
 *
 * @Entity
 */
class Team extends AbstractEntity
{
    /**
     * @var int
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @var string
     * @Name
     * @Column(type="string", length=240, unique=true, nullable=false)
     */
    protected $name;

    /**
     * @var string
     * @Column(type="text", nullable=false)
     */
    protected $description;

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function setDescription($description)
    {
        $this->description = $description;
    }


}

