<?php


namespace Teams\Form;

use Laminas\Form\Form;

class AddSitesToTeam extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'site',
            'type' => AddSiteToTeamFieldset::class,
        ]);
//        $this->add([
//            'name' => 'user',
//            'type' => TeamUserFieldset::class,
//        ]);
    }
}
