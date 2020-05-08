<?php


namespace Teams\Form;


use Teams\Form\Element\AllItemSetSelect;
use Zend\Form\Fieldset;

class TeamItemSetFieldset extends Fieldset
{

    public function init()
    {


        $this->add([
            'name' => 'o:itemset',
            'type' => AllItemSetSelect::class,
            'options' => [
                'label' => 'ItemSet', // @translate
                'chosen' => true,
            ],
            'attributes' => [
                'multiple' => true,
            ],

        ]);







    }


}