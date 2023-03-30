<?php
namespace Teams\Form;

use Teams\Form\Element\AllTeamSelect;
use Teams\Form\Element\TeamName;
use Teams\Form\Element\TeamSelect;
use Teams\Form\Element\UserSelect;
use Laminas\Form\Fieldset;
use Laminas\Form\Element;


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
            'type' => TeamName::class,
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
    }
}
