<?php

namespace Teams\Entity;

use Omeka\Entity\Asset;

/**
 *
 * @Entity
 * @Table(name="team_asset")
 */
class TeamAsset extends \Omeka\Entity\AbstractEntity
{
    /**
     * @var Team
     * @Id
     * @ManyToOne(
     *     targetEntity="Teams\Entity\Team",
     *     inversedBy="team_asset",
     *     cascade={"persist"}
     * )
     * @JoinColumn(
     *     onDelete="cascade",
     *     nullable=false
     * )
     */
    protected $team;

    /**
     *
     * @var Asset
     * @Id
     * @ManyToOne(
     *     targetEntity="Omeka\Entity\Asset",
     *     cascade={"persist"}
     * )
     * @JoinColumn(
     *     onDelete="cascade",
     *     nullable=false
     * )
     */
    protected $asset;

    public function __construct(Team $team, Asset $asset)
    {
        $this->team = $team;
        $this->asset = $asset;
    }

    public function setAsset(Asset $asset)
    {
        $this->asset = $asset;
    }

    public function setTeam(Team $team)
    {
        $this->team = $team;
    }

    public function getTeam(): Team
    {
        return $this->team;
    }

    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function __toString()
    {
        return json_encode([
            'team' => $this->getTeam()->getId(),
            'site' => $this->getAsset()->getId(),
        ]);
    }


    /**
     * @inheritDoc
     */
    public function getId(): array
    {
        return [
            'team' => $this->getTeam()->getId(),
            'asset' => $this->getAsset()->getId()
        ];
    }
}
