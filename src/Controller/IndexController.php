<?php
namespace Teams\Controller;


use Doctrine\ORM\EntityManager;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Model\ViewModel;
use Omeka\Entity\User;
use Teams\Entity\Team;
use Teams\Entity\TeamRole;
use Omeka\Permissions\Acl;



class IndexController extends AbstractActionController
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
    public function changeCurrentTeamAction($user_id)
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return $this->redirect()->toRoute('admin');
        } else {
            $data = $request->getPost();
            $em = $this->entityManager;
            $team_user = $em->getRepository('Teams\Entity\TeamUser');
            $old_current = $team_user->findOneBy(['user' => $user_id, 'is_current' => true]);
            $new_current = $team_user->findOneBy(['user'=> $user_id, 'team'=>$data['team_id']]);
            $old_current->setCurrent(null);
            $new_current->setCurrent(true);
            $em->flush();


        }
    }

    public function indexAction()
    {
        $user_id = $this->identity()->getId();

        $request = $this->getRequest();
        if ($request->isPost()){
            $this->changeCurrentTeamAction($user_id);
            $this->redirect()->toRoute('admin/teams');

        }


        $view = new ViewModel;
        $user_id = $this->identity()->getId();
        $team_user = $this->entityManager->getRepository('Teams\Entity\TeamUser');
        $user_teams = $team_user->findBy(['user'=>$user_id]);
        $current_team = $team_user->findOneBy(['user'=>$user_id,'is_current'=>true])->getTeam();

        $response = $this->api()->search('team');

        $view->setVariable('response', $response);
        $view->setVariable('current_team', $current_team);
        $view->setVariable('user_teams', $user_teams);
        $view->setVariable('user_id', $user_id);
        $view->setVariable('data', $this->getRequest()->getPost());




        return $view;
    }

    public function teamDetailAction()
    {
        $view = new ViewModel;
        $id = $this->params()->fromRoute('id');
        $response = $this->api()->read('team', ['id' => $id]);
        $team_entity = $this->entityManager->getRepository('Teams\Entity\Team')->findOneBy(['id'=>$id]);
        $items = array();
        foreach ($team_entity->getTeamResources() as $tr):
            $items[] = $this->api()->read('items', $tr->getResource()->getId())->getContent();
        endforeach;
        $view->setVariable('response', $response);
        $view->setVariable('items', $items);


        return $view;
    }

    public function roleDetailAction()

    {
        $id = $this->params()->fromRoute('id');
        $response = $this->api()->read('team-role', ['id' => $id]);
        return new ViewModel(['response'=>$response]);


    }
    public function roleIndexAction()
    {

        $view = new ViewModel;

        $response = $this->entityManager->getRepository('Teams\Entity\TeamRole')->findAll();
//        $response = $this->api()->search('team');
        $routeMatch = $this->getEvent()->getRouteMatch()->getMatchedRouteName();

        $view->setVariable('response', $response);
        $view->setVariable('route', $routeMatch);
        return $view;

    }

    //delete works. update, read, and create do not. Get errors like this:
    // Doctrine\DBAL\Exception\NotNullConstraintViolationException
    //An exception occurred while executing 'INSERT INTO team_user (team_id, user_id_id, team_user_role)
    // VALUES (?, ?, ?)' with params [null, null, null]: SQLSTATE[23000]: Integrity constraint violation:
    // 1048 Column 'user_id_id' cannot be null
    public function usersAction(){


        $team_users = $this->api()->search('team-user');
        $users = $this->api()->search('users');
        $view = new ViewModel(['users'=> $users, 'team_users'=>$team_users]);
        return $view;
//        $view->setVariable('response', $response);


    }



}
