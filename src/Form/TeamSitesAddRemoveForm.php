<?php


namespace Teams\Form;

use Laminas\Form\Form;
use Omeka\Api\Manager as ApiManager;

class TeamSitesAddRemoveForm extends Form
{
    protected ?ApiManager $apiManager = null;

    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);
    }

    public function init()
    {
        $this->add([
            'name' => 'teamSites',
            'type' => TeamSitesFieldset::class,
        ]);

        $siteSelect = $this->get('teamSites')->get('o:site');
        $siteSelect->setName('teamSites[o:site]');
        $siteSelect->setAttribute('multiple', true);
        $siteSelect->setAttribute('id', 'sites');
        $siteSelect->setEmptyOption('None');

        $teamId = $this->options['team_id'] ?? null;
        if ($this->apiManager && $teamId) {
            $currentSiteIds = $this->apiManager
                ->search('team-site', ['team' => $teamId], ['returnScalar' => 'site'])
                ->getContent();
            $siteSelect->setValue(array_values($currentSiteIds));
        }
    }

    public function setApiManager(ApiManager $apiManager): void
    {
        $this->apiManager = $apiManager;
    }
}
