<?php
namespace Teams\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;
use Omeka\Entity\AbstractEntity;
use phpDocumentor\Reflection\Types\Boolean;
use Laminas\Form\Annotation\Name;

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
     * @Column(name="name", length=240, unique=true, nullable=false)
     */
    protected $name;

    /**
     * An array of TeamUsers who share this role
     * @var TeamUser[]
     * @OneToMany(targetEntity="TeamUser", mappedBy="role")
     */
    protected $team_users;

    /**
     * @Column(type="boolean", nullable=true)
     */
    protected $can_add_users = null;

    /**
     * @Column(type="boolean", nullable=true)
     */
    protected $can_add_items = null;

    /**
     * @Column(type="boolean", nullable=true)
     */
    protected $can_add_itemsets = null;

    /**
     * @Column(type="boolean", nullable=true)
     */
    protected $can_modify_resources = null;

    /**
     * @Column(type="boolean", nullable=true)
     */
    protected $can_delete_resources = null;

    /**
     * @Column(type="boolean", nullable=true)
     */
    protected $can_add_site_pages = null;

    /**
     * @Column(type="text", nullable=true)
     */
    protected $comment;

    public function __construct()
    {
        $this->team_users = new ArrayCollection();

    }

    public function setComment($comment)
    {
        $this->comment = $comment;
    }

    public function getComment()
    {
        return $this->comment;
    }

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

    public function setCanAddUsers( $bool)
    {
        $this->can_add_users = $bool;
    }

    public function getCanAddItems()
    {
        return $this->can_add_items;
    }

    public function setCanAddItems( $bool)
    {
        $this->can_add_items = $bool;
    }

    public function getCanAddItemsets()
    {
        return $this->can_add_itemsets;
    }

    public function setCanAddItemsets( $bool)
    {
        $this->can_add_itemsets = $bool;
    }

    public function getCanModifyResources()
    {
        return $this->can_modify_resources;
    }

    public function setCanModifyResources( $bool)
    {
        $this->can_modify_resources = $bool;
    }

    public function getCanDeleteResources()
    {
        return $this->can_delete_resources;
    }

    public function setCanDeleteResources( $bool)
    {
        $this->can_delete_resources = $bool;
    }

    public function getCanAddSitePages()
    {
        return $this->can_add_site_pages;
    }

    public function setCanAddSitePages( $bool)
    {
        $this->can_add_site_pages = $bool;
    }

    public function getTeamUsers()
    {
        return $this->team_users;
    }
}

