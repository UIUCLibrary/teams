<?php
namespace Teams\Controller;

use Omeka\Api\Exception\InvalidArgumentException;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class DeleteController extends AbstractActionController
{
    public function teamDeleteAction()
    {
        //is there an id?
        $id = $this->params()->fromRoute('id');
        if (! $id){
            return $this->redirect()->toRoute('admin');
        }

        //does a team have that id
        try {
            $team = $this->api()->search('team', ['id'=>$id]);
        } catch (InvalidArgumentException $exception) {
            return $this->redirect()->toRoute('admin');
        }


        //is it a post request?
        $request = $this->getRequest();
        if (! $request->isPost()) {
            return new ViewModel(['team'=>$team]);
        }

        //is it the right id and did they say confirm?
//        if ($id != $request->getPost('id')
//            || 'Delete' != $request->getPost('confirm')
//        ) {
//            return $this->redirect()->toRoute('admin/teams');
//        }
        if ($request->getPost('confirm') == 'Delete'){
            $this->api()->delete('team', ['id'=>$id]);
            return $this->redirect()->toRoute('admin/teams');
        }


        return $this->redirect()->toRoute('admin/teams');

    }

    public function roleDeleteAction()
    {

    }

}
