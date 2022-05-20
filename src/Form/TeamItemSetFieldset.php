<?php


namespace Teams\Form;

use Teams\Form\Element\AllItemSetSelect;
use Teams\Form\Element\RoleSelect;
use Teams\Form\Element\UserSelect;
use Laminas\Form\Fieldset;

class TeamItemSetFieldset extends Fieldset
{
    public function init()
    {
        $this->add([
            'name' => 'o:itemset',
            'type' => AllItemSetSelect::class,
            'options' => [
                'label' => 'Item Set', // @translate
                'chosen' => true,
            ],
            'attributes' => [
                'multiple' => true,
            ],

        ]);

        $this->add([
            'name' => 'o:user',
            'type' => UserSelect::class,
            'options' => [
                'label' => 'Users', // @translate
                'chosen' => true,
            ],
            'attributes' => [
                'multiple' => true,
            ],

        ]);
    }
}
