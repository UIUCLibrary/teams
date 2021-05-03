<?php
namespace Teams\Form;

use Laminas\Form\Form;
use Laminas\Form\Element;


class AddMemberForm extends Form
{
    public function init()
    {
        $this->setAttribute('id', 'add-member-form');

        $this->add([
            'type' => 'hidden',
            'name' => 'user_id'
        ]);


        $this->add([
            'type' => 'submit',
            'name' => 'submit',
            'attributes' => [
                'value' => 'Add User to Team',
            ],
        ]);
    }

}
