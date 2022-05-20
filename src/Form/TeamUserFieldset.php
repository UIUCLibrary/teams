<?php


namespace Teams\Form;

use Teams\Form\Element\UserSelect;
use Laminas\Form\Fieldset;

class TeamUserFieldset extends Fieldset
{
    public function init()
    {
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
