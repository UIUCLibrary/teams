<?php
namespace Teams\Form;

use Laminas\Form\Form;

class TeamForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'team',
            'type' => TeamFieldset::class,
        ]);

//        $this->add([
//            'type' => 'submit',
//            'name' => 'submit',
//            'attributes' => [
//                'value' => 'Create New Team',
//            ],
//        ]);
    }
}
