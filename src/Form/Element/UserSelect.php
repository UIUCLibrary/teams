<?php


namespace Teams\Form\Element;

class UserSelect extends TeamSelect
{
    protected $data_placeholder = 'Select Users';

    protected $data_base_url = ['resource' => 'user'];

    public function getValueOptions(): array
    {
        $valueOptions = [];
        $em = $this->getEntityManager();
        $users = $em->getRepository('Omeka\Entity\User')->findAll();

        foreach ($users as $user):
//            $user = $user->getUser();
            $user_name = $user->getName();
        $user_id = $user->getId();
        $valueOptions[$user_id] = $user_name;
        endforeach;



        $prependValueOptions = $this->getOption('prepend_value_options');
        if (is_array($prependValueOptions)) {
            $valueOptions = $prependValueOptions + $valueOptions;
        }
        return $valueOptions;
    }

    public function setTeam(int $team_id)
    {
        $this->team_id = $team_id;
    }

    public function setValueOptions(array $options)
    {
        $this->valueOptions = $options;
    }
}
