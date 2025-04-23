<?php


namespace Teams\Form;

use Omeka\Permissions\Acl;
use Laminas\Form\Form;

class ConfigForm extends Form
{
    /**
     * @var Acl
     */
    protected $acl;
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
        $roles = $this->getAcl()->getRoleLabels(false);

        $this->add([
                'name' => 'teams_filter_bypass_roles',
                'type' => 'select',
                'options' => [
                    'label' => 'Grant "Bypass Teams Filter" ability', // @translate
                    'info' =>'Minimum role to bypass Teams filter on admin search interface',
                    'value_options' => $roles,

                ],
                'attributes' => [
                    'id' => 'role',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'value' => $this->globalSettings->get('teams_filter_bypass_roles'),
                ],
            ]);

    }
    public function setGlobalSettings($globalSettings)
    {
        $this->globalSettings = $globalSettings;
    }

    /**
     * @param Acl $acl
     */
    public function setAcl(Acl $acl)
    {
        $this->acl = $acl;
    }

    /**
     * @return Acl
     */
    public function getAcl()
    {
        return $this->acl;
    }
}
