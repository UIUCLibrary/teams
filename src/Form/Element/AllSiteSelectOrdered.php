<?php


namespace Teams\Form\Element;

use Doctrine\ORM\EntityManager;
use Omeka\Api\Representation\UserRepresentation;
use Omeka\Form\Element\SiteSelect;
use Teams\Entity\Team;
use Teams\Entity\TeamSite;

class AllSiteSelectOrdered extends SiteSelect
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @return EntityManager
     */
    public function getEntityManager()
    {
        return $this->entityManager;
    }

    /**
     * @param EntityManager $entityManager
     */
    public function setEntityManager(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getValueOptions(): array
    {
        $query = $this->getOption('query');

        //is the exact same as Omeka\Form\Element\SiteSelect with the exception of this line.
        $query['bypass_team_filter'] = true;

        if (!is_array($query)) {
            $query = [];
        }

        $response = $this->getApiManager()->search($this->getResourceName(), $query);

        $em = $this->getEntityManager();
        $all_teams_sites = $em->getRepository('Teams\Entity\TeamSite')->findAll();

        if ($this->getOption('disable_group_by_owner')) {
            // Group alphabetically by resource label without grouping by owner.
            $resources = [];
            foreach ($response->getContent() as $resource) {
                $resources[$this->getValueLabel($resource)][] = $resource->id();
            }
            ksort($resources);
            $valueOptions = [];
            foreach ($resources as $label => $ids) {
                foreach ($ids as $id) {
                    $valueOptions[$id] = $label;
                }
            }
        } else {
            // Group alphabetically by owner email.
            $resourceOwners = [];
            foreach ($all_teams_sites as $resource) {
                $owner = $resource->getTeam();
                $index = $owner ? $owner->getName() : null;
                $resourceOwners[$index]['owner'] = $owner;
                $resourceOwners[$index]['resources'][] = $resource;
            }
            ksort($resourceOwners);

            $valueOptions = [];
            foreach ($resourceOwners as $resourceOwner) {
                $options = [];
                foreach ($resourceOwner['resources'] as $resource) {
                    $options[$resource->getSite()->getId()] = $resource->getSite()->getTitle();
                    if (!$options) {
                        continue;
                    }
                }
                $owner = $resourceOwner['owner'];
                if ($owner instanceof Team) {
                    $label = $owner->getName();
                } else {
                    $label = '[No Team]';
                }
                $valueOptions[] = ['label' => $label, 'options' => $options];
            }
        }

        $prependValueOptions = $this->getOption('prepend_value_options');
        if (is_array($prependValueOptions)) {
            $valueOptions = $prependValueOptions + $valueOptions;
        }
        return $valueOptions;
    }
}
