<?php


namespace Teams\Form;

use Laminas\Form\Form;

class ConfigForm extends Form
{
    protected $globalSettings;

    public function init()
    {
        $this->add([
            'type' => 'checkbox',
            'name' => 'teams_site_admin_make_site',
            'options' => [
                'label' => 'Allow site admins to create new sites', // @translate
            ],
            'attributes' => [
                'checked' => $this->globalSettings->get('teams_site_admin_make_site') ? 'checked' : '',
                'id' => 'teams_site_admin_make_site',
            ],
        ]);

        $this->add([
            'type' => 'checkbox',
            'name' => 'teams_editor_make_site',
            'options' => [
                'label' => 'Allow editors to create new sites', // @translate
            ],
            'attributes' => [
                'checked' => $this->globalSettings->get('teams_editor_make_site') ? 'checked' : '',
                'id' => 'teams_editor_make_site',
            ],
        ]);

        $this->add([
            'type' => 'checkbox',
            'name' => 'teams_site_admin_make_user',
            'options' => [
                'label' => 'Allow site admins to create new users', // @translate
            ],
            'attributes' => [
                'checked' => $this->globalSettings->get('teams_site_admin_make_user') ? 'checked' : '',
                'id' => 'teams_site_admin_make_user',
            ],
        ]);
    }
    public function setGlobalSettings($globalSettings)
    {
        $this->globalSettings = $globalSettings;
    }
}
