<?php


namespace Teams\Form;

use Laminas\Form\Form;

class TeamSelect extends Form
{
    public function init()
    {
        $this->add([
            //extremely important  that this match what is in the API adapter: Teams\Api\Representation getJsonLd()
            'name' => 'team',
            'type' => 'Select',
            'options' => [
                'label' => 'Team', // @translate
            ],
            'attributes' => [
                'id' => 'team',
                'required' => true,
            ],
        ]);
    }
}
