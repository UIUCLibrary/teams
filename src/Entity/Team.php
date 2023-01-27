<?php
namespace Teams\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Omeka\Entity\AbstractEntity;

/**
 *
 * @Entity
 * @Table(name="team")
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
     * @Column(type="string", length=240, unique=true, nullable=false)
     */
    protected $name;

    /**
     * @Column(type="text", nullable=false)
     */
    protected $description;

    /* *
     *
     * @var ArrayCollection|User[]
     * @ManyToMany(
     *     targetEntity="Omeka\Entity\User",
     *     mappedBy="team",
     *     inversedBy="user"
     * )
     * @JoinTable(
     *     name="team_user",
     *     joinColumns={
     *         @JoinColumn(
     *             name="team_id",
     *             referencedColumnName="id",
     *             onDelete="cascade",
     *             nullable=false
     *         )
     *     },
     *     inverseJoinColumns={
     *         @JoinColumn(
     *             name="user_id",
     *             referencedColumnName="id",
     *             onDelete="cascade",
     *             nullable=false
     *         )
     *     }
     * )
     */
    protected $users;

    /* *
 *
 * @var ArrayCollection|Assets[]
 * @ManyToMany(
 *     targetEntity="Omeka\Entity\Asset",
 *     mappedBy="team",
 *     inversedBy="asset"
 * )
 * @JoinTable(
 *     name="team_asset",
 *     joinColumns={
 *         @JoinColumn(
 *             name="team_id",
 *             referencedColumnName="id",
 *             onDelete="cascade",
 *             nullable=false
 *         )
 *     },
 *     inverseJoinColumns={
 *         @JoinColumn(
 *             name="asset_id",
 *             referencedColumnName="id",
 *             onDelete="cascade",
 *             nullable=false
 *         )
 *     }
 * )
 */
    protected $assets;



    /* *
     *
     * @var ArrayCollection|Site[]
     * @ManyToMany(
     *     targetEntity="Omeka\Entity\Site",
     *     mappedBy="team",
     *     inversedBy="site"
     * )
     * @JoinTable(
     *     name="team_site",
     *     joinColumns={
     *         @JoinColumn(
     *             name="site_id",
     *             referencedColumnName="id",
     *             onDelete="cascade",
     *             nullable=false
     *         )
     *     },
     *     inverseJoinColumns={
     *         @JoinColumn(
     *             name="site_id",
     *             referencedColumnName="id",
     *             onDelete="cascade",
     *             nullable=false
     *         )
     *     }
     * )
     */
    protected $sites;

    /**
     * @var ArrayCollection|TeamUser[]
     * @OneToMany(
     *     targetEntity="Teams\Entity\TeamUser",
     *     mappedBy="team",
     *     cascade={"persist", "remove"}
     * )
     */
    protected $team_users;


    /**
     * @var ArrayCollection|TeamSite[]
     * @OneToMany(
     *     targetEntity="Teams\Entity\TeamSite",
     *     mappedBy="team",
     *     cascade={"persist", "remove"}
     * )
     */
    protected $team_sites;


    /**
     * @var ArrayCollection|TeamAsset[]
     * @OneToMany(
     *     targetEntity="Teams\Entity\TeamAsset",
     *     mappedBy="team",
     *     cascade={"persist", "remove"}
     * )
     */
    protected $team_assets;

    /* *
     *
     * Many Teams have Many Resources.
     * @var Collection|Resource[]
     * @ManyToMany(
     *     targetEntity="Omeka\Entity\Resource",
     *     mappedBy="team",
     *     inversedBy="resource"
     * )
     * @JoinTable(
     *     name="team_resource",
     *     joinColumns={
     *         @JoinColumn(
     *             name="team_id",
     *             referencedColumnName="id",
     *             onDelete="cascade",
     *             nullable=false
     *         )
     *     },
     *     inverseJoinColumns={
     *         @JoinColumn(
     *             name="resource_id",
     *             referencedColumnName="id",
     *             onDelete="cascade",
     *             nullable=false
     *         )
     *     }
     * )
     */
    protected $resources;

    /* *
 *
 * Many Teams have Many Resources Templates.
 * @var Collection|ResourceTemplate[]
 * @ManyToMany(
 *     targetEntity="Omeka\Entity\ResourceTemplate",
 *     mappedBy="team",
 *     inversedBy="resource_template"
 * )
 * @JoinTable(
 *     name="team_resource_template",
 *     joinColumns={
 *         @JoinColumn(
 *             name="team_id",
 *             referencedColumnName="id",
 *             onDelete="cascade",
 *             nullable=false
 *         )
 *     },
 *     inverseJoinColumns={
 *         @JoinColumn(
 *             name="resource_template_id",
 *             referencedColumnName="id",
 *             onDelete="cascade",
 *             nullable=false
 *         )
 *     }
 * )
 */
    protected $resource_templates;

    /**
     * @var Collection|TeamResourceTemplate[]
     * @OneToMany(
     *     targetEntity="Teams\Entity\TeamResourceTemplate",
     *     mappedBy="team",
     *     cascade={"persist", "remove"}
     * )
     */
    protected $team_resource_templates;

    /**
     * @var Collection|TeamResource[]
     * @OneToMany(
     *     targetEntity="Teams\Entity\TeamResource",
     *     mappedBy="team",
     *     cascade={"persist", "remove"}
     * )
     */
    protected $team_resources;

    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->team_users = new ArrayCollection();
        $this->resources = new ArrayCollection();
        $this->team_resources = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setDescription($description)
    {
        $this->description = $description;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getUsers()
    {
        return $this->users;
    }

    public function getTeamUsers()
    {
        return $this->team_users;
    }


    public function getResources()
    {
        return $this->resources;
    }

    public function getTeamResources()
    {
        return $this->team_resources;
    }

    public function getResourceTemplates()
    {
        return $this->resource_templates;
    }

    public function getTeamResourceTemplates()
    {
        return $this->team_resource_templates;
    }

    public function getTeamSites()
    {
        return $this->team_sites;
    }

    public function getSites()
    {
        return $this->sites;
    }

    public function getTeamAssets()
    {
        return $this->team_assets;
    }


}
