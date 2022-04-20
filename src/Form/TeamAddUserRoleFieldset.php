<?php


namespace Teams\Form;

use Teams\Form\Element\RoleSelect;
use Teams\Form\Element\UserSelect;
use Laminas\Form\Fieldset;

class TeamAddUserRoleFieldset extends Fieldset
{
    public function init()
    {
        $this->add([
            'name' => 'o:user',
            'type' => UserSelect::class,
            'options' => [
                'label' => 'User', // @translate
                'chosen' => true,
            ],
            'attributes' => [
            ],

        ]);

        $this->add([
            'name' => 'o:role',
            'type' => RoleSelect::class,
            'options' => [
                'label' => 'Role', // @translate
                'chosen' => true,
            ],
            'attributes' => [
            ],

        ]);
    }
}
