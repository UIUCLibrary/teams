<?php


namespace Teams\Controller;


use Doctrine\ORM\EntityManager;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;


class CurrentTeamController extends AbstractActionController
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
    public function getCurrentTeam(){
        return $this->api()->read('team-user', ['id'=>1])->getContent()->user();

    }

    public function changeCurrentTeamAction()
    {
        $request   = $this->getRequest();
        if (! $this->request->isPost()){
            return $this->redirect()->toRoute('admin\teams');
        }else {
            $data = $request->getPost();
            $em = $this->entityManager;
            $team_user = $em->getRepository('Teams\Entity\TeamUser');
            $old_current = $team_user->findOneBy(['user'=>$data['user_id'], 'is_current'=>true]);
            $new_current = $team_user->findOneBy(['user'=>$data['user_id'], 'team'=>$data['team_id']]);
            $old_current->setCurrent(false);
            $new_current->setCurrent(true);
            $em->flush();


        }


    }
}