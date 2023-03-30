<?php
namespace Teams\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Teams\Form\TeamForm;
use Teams\Form\TeamItemSetForm;


class TeamController extends AbstractActionController
{

    public function addAction()
    {
        //TODO:if there are no roles yet, submit an error message and provide link to create roles

        $view = new ViewModel;
        $itemsetForm = $this->getForm(TeamItemSetForm::class);
        $teamForm = $this->getForm(TeamForm::class);
        $users =  $this->api()->search('users')->getContent();


        $view->setVariable('itemSetForm', $itemsetForm);
        $view->setVariable('teamForm', $teamForm);
        $view->setVariable('users', $users);

        return $view;
    }

    public function deleteAction()
    {
        echo "delete";

    }

    public function editAction()
    {
        echo "edit";

    }

    public function showAction()
    {
        echo "show";

    }

    public function browseAction()
    {
        echo "browse";

    }

}