<?php
namespace Teams\Controller;

use Doctrine\ORM\EntityManager;
use Omeka\Api\Exception\InvalidArgumentException;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class DeleteController extends AbstractActionController
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }
    public function teamDeleteAction()
    {
        //is there an id?
        $id = $this->params()->fromRoute('id');
        if (! $id) {
            $this->messenger()->addError("No team id found");
            return $this->redirect()->toRoute('admin/teams');
        }

        //does a team have that id
        try {
            $team = $this->api()->searchOne('team', ['id'=>$id]);
        } catch (InvalidArgumentException $exception) {
            $this->messenger()->addError("Invalid team id");
            return $this->redirect()->toRoute('admin/teams');
        }

        //is it a post request?
        $request = $this->getRequest();
        if (! $request->isPost()) {
            return new ViewModel(['team'=>$team]);
        }

        if (! $this->teamAuth()->teamAuthorized($this->identity(), 'delete', 'team')){
            $this->messenger()->addError("You aren't authorized to delete teams");
            return $this->redirect()->toRoute('admin/teams');
        }

        if ($request->getPost('confirm') == 'Delete') {
            $this->api()->delete('team', ['id'=>$id]);
            return $this->redirect()->toRoute('admin/teams');
        }


        return $this->redirect()->toRoute('admin/teams');
    }

    public function roleDeleteAction()
    {
        $user = $this->identity()->getRole();
        $id = $this->params()->fromRoute('id');
        $request = $this->getRequest();

        $role_users = $this->entityManager->getRepository('Teams\Entity\TeamUser')
            ->findBy(['role' => $id]);
        $view = new ViewModel(
            [
                'role_users' => $role_users,
                'user' => $user,
            ]
        );

        if (! $request->isPost()) {
            return $view;
        }
        if (! $this->teamAuth($user, 'delete', 'role')){
            $this->messenger()->addError('You are not authorized to delete roles');
            return $view;
        }
        if ($role_users) {
            $this->messenger()->addError('This role can not be deleted while users are assigned to it');
            return $view;
        }
        if ($request->getPost('confirm') == 'Delete') {
            $role = $this->entityManager->getRepository('Teams\Entity\TeamRole')
                ->findOneBy(['id' => $id]);
            $roleName = $role ? $role->getName() : $id;
            $this->api()->delete('team-role', $id);
            $this->messenger()->addSuccess(sprintf('Successfully deleted role "%s"', $roleName));
        }
        return $this->redirect()->toRoute('admin/teams/roles');
    }
}
