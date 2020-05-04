<?php
namespace Teams\Form;

use Zend\Form\Fieldset;
use Zend\Form\Element;


class TeamFieldset extends Fieldset
{


    public function init()
    {
        $this->setAttribute('id', 'team-form');

        $this->add([
            'type' => 'hidden',
            'name' => 'id',

        ]);

        $this->add([
            //extremely important  that this match what is in the API adapter: Teams\Api\Representation getJsonLd()
            'name' => 'o:name',
            'type' => 'Text',
            'options' => [
                'label' => 'Name', // @translate
            ],
            'attributes' => [
                'id' => 'name',
                'required' => true,
            ],
        ]);

        $this->add([

            //extremely important  that this match what is in the API adapter: Teams\Api\Representation getJsonLd()
            'name' => 'o:description',
            'type' => 'Text',
            'options' => [
                'label' => 'Description', // @translate
            ],
            'attributes' => [
                'id' => 'comment',
                'required' => false,
            ],
        ]);


//        $this->add([
//
//            //extremely important  that this match what is in the API adapter: Teams\Api\Representation getJsonLd()
//            'name' => 'o:user_id',
//            'type' => Element\MultiCheckbox::class,
//            'options' => [
//                'label' => 'Add User', // @translate
//                'value_options' =>[
//                    '0' => 'zero',
//                    '1' => 'one',
//                    '2' => 'two'
//                ]
//            ],
//            'attributes' => [
//                'id' => 'comment',
//                'required' => false,
//            ],
//        ]);

    }

}
