<?php


namespace Teams\Form;

use Laminas\Form\Fieldset;
use Laminas\Form\Element\Checkbox;
use Teams\Form\Element\RoleName;

class TeamRoleFieldset extends Fieldset
{
    public function init()
    {
        $this->add([
            'type' => 'hidden',
            'name' => 'id',

        ]);

        $this->add([
            'name' => 'o:name',
            'type' => RoleName::class,
            'options' => [
                'label' => 'Role Title', // @translate
            ],
            'attributes' => [
                'id' => 'name',
                'required' => true,
            ],
        ]);


        $this->add([
            'name'=> 'comment',
            'type' => 'Text',
            'options' => [
                'label' => 'Role Description'
            ],
            'attributes' => [
                'id' => 'comment',
                'required' => false
            ]
        ]);

        $this->add([
            'name' => 'can_add_users',
            'type' => Checkbox::class,
            'options' => [
                'label' => '<strong class="o-icon-users"> Can add users to team</strong>',
                'label_options' => [
                    'disable_html_escape' => true,
                ],
            ],
            'attributes' => [
                'id' => 'can_add_users',
            ]
        ]);

        $this->add([
            'name' => 'can_add_items',
            'type' => 'Checkbox',
            'options' => [
                'label' => '<strong class="o-icon-items"> Can add resources to team repository</strong>',
                'label_options' => [
                    'disable_html_escape' => true,
                ],
            ],
            'attributes' => [
                'id' => 'can_add_items'
            ]
        ]);

//        $this->add([
//            'name' => 'can_add_itemsets',
//            'type' => 'Checkbox',
//            'options' => [
//                'label' => '<strong class="o-icon-item-sets"> <strike>Can add itemsets to team repository</strike> Disabled</strong>',
//                'label_options' => [
//                    'disable_html_escape' => true,
//                ],
//            ],
//            'attributes' => [
//                'id' => 'can_add_itemsets',
//                'disabled' => 'disabled'
//            ]
//        ]);

        $this->add([
            'name' => 'can_modify_resources',
            'type' => 'Checkbox',
            'options' => [
                'label' => '<strong class="o-icon-edit"> Can modify resources in team repository </strong>',
                'label_options' => [
                    'disable_html_escape' => true,
                ],
            ],
            'attributes' => [
                'id' => 'can_modify_resources'
            ]
        ]);

        $this->add([
            'name' => 'can_delete_resources',
            'type' => 'Checkbox',
            'options' => [
                'label' => '<strong class="o-icon-delete"> Can delete resources in team repository</strong>',
                'label_options' => [
                    'disable_html_escape' => true,
                ],
            ],
            'attributes' => [
                'id' => 'can_delete_resources'
            ]
        ]);

        $this->add([
            'name' => 'can_add_site_pages',
            'type' => 'Checkbox',
            'options' => [
                'label' => '<strong class="o-icon-site"> Can modify linked site </strong>',
                'label_options' => [
                    'disable_html_escape' => true,
                ],
            ],
            'attributes' => [
                'id' => 'can_add_site_pages'
            ]
        ]);
    }

}