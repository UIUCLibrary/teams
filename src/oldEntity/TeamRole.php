<?php
namespace Teams\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Omeka\Entity\AbstractEntity;
use phpDocumentor\Reflection\Types\Boolean;
use Zend\Form\Annotation\Name;

/**
 *
 * @Entity
 */
class TeamRole extends AbstractEntity
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
     * @Column(name="name", length=190, unique=true, nullable=false)
     */
    protected $name;

    /**
     * @Column(type="boolean")
     */
    protected $can_add_users = false;

    /**
     * @Column(type="boolean")
     */
    protected $can_add_items = false;

    /**
     * @Column(type="boolean")
     */
    protected $can_add_itemsets = false;

    /**
     * @Column(type="boolean")
     */
    protected $can_add_items_to_itemsets = false;

    /**
     * @Column(type="boolean")
     */
    protected $can_add_site_pages = false;

    /**
     * @Column(type="text", nullable=true)
     */
    protected $comment;

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        return $this->name = $name;
    }

    public function getCanAddUsers()
    {
        return $this->can_add_users;
    }

    public function setCanAddUsers(Boolean $bool)
    {
        $this->can_add_users = $bool;
    }

    public function getCanAddItems()
    {
        return $this->can_add_items;
    }

    public function setCanAddItems(Boolean $bool)
    {
        $this->can_add_users = $bool;
    }

    public function getCanAddItemsets()
    {
        return $this->can_add_itemsets;
    }

    public function setCanAdItemsets(Boolean $bool)
    {
        $this->can_add_itemsets = $bool;
    }

    public function getCanAddItemsToItemsets()
    {
        return $this->can_add_items_to_itemsets;
    }

    public function setCanAddItemsToItemsets(Boolean $bool)
    {
        $this->can_add_items_to_itemsets = $bool;
    }

    public function getCanAddSitePages()
    {
        return $this->can_add_site_pages;
    }

    public function setCanAddSitePages(Boolean $bool)
    {
        $this->can_add_site_pages = $bool;
    }
}

