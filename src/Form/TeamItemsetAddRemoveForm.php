<?php


namespace Teams\Form;

use Laminas\Form\Form;

class TeamItemsetAddRemoveForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'addCollections',
            'type' => TeamItemSetFieldset::class,
        ]);

        $this->add([
            'name' => 'rmCollections',
            'type' => TeamItemSetFieldset::class,

        ]);
    }
}
