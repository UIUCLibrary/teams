<?php
namespace Teams\Controller;


use Doctrine\ORM\EntityManager;
use Omeka\Form\ConfirmForm;
use Omeka\Form\ResourceBatchUpdateForm;
use Omeka\Form\ResourceForm;
use Omeka\Media\Ingester\Manager;
use Omeka\Stdlib\Message;
use phpDocumentor\Reflection\Types\This;
use Teams\Entity\TeamUser;
use Teams\Form\TeamRoleForm;
use Teams\Form\TeamForm;
use Teams\Form\TeamUserForm;
use Zend\Authentication\AuthenticationServiceInterface;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\ServiceManager\ServiceLocatorInterface;


Class AddController extends AbstractActionController
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


    public function teamAddAction()
    {
        $all_u_array = array();
        $all_u_collection = $this->api()->search('users')->getContent();
        foreach ($all_u_collection as $u):
            $all_u_array[$u->name()] = $u->id();
        endforeach;

        $role_query = $this->entityManager->createQuery('select partial r.{id, name} from Teams\Entity\TeamRole r');
        $roles_array =  $role_query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);
        $roles = array();

        foreach ($roles_array as $role):
            $roles[$role['name']] = $role['id'];
        endforeach;



        $form = $this->getForm(TeamForm::class);
        $userForm = $this->getForm(TeamUserForm::class);
        $request   = $this->getRequest();
        $view = new ViewModel(['form' => $form, 'available_u_array' => $all_u_array, 'roles' => $roles, "userForm" => $userForm]);

        //if it is get, then give them the form
        if (! $request->isPost()){
            return $view;
        }

        //otherwise, set the data
        $form->setData($request->getPost());

        //if the form isn't valid, return it
        if (! $form->isValid()){
            return $view;
        }

        //get the data from the post
        $data = $request->getPost('team');

        $newTeam = $this->api($form)->create('team', $data);

        //looks like this was a diagnostic i used to see what was in the data variable
        $view->setVariable('post_data', $data);
        $view->setVariable('team', $newTeam);

        foreach ($request->getPost('user_role') as $userId => $roleId):
            $user = $this->entityManager->getRepository('Omeka\Entity\User')
                ->findOneBy(['id' => (int)$userId]);
            $role = $this->entityManager->getRepository('Teams\Entity\TeamRole')
                ->findOneBy(['id' => (int)$roleId]);
            $team = $this->entityManager->getRepository('Teams\Entity\Team')
                ->findOneBy(['id' => (int)$newTeam->getContent()->id() ]);
            $teamUser = new TeamUser($team, $user, $role);

            $this->entityManager->persist($teamUser);
        endforeach;
        $this->entityManager->flush();




//        return $this->redirect()->toRoute('admin/teams');
        return $view;
    }

    public function roleAddAction()
    {
        $form = $this->getForm(TeamRoleForm::class);
        $request   = $this->getRequest();
        $view = new ViewModel(['form' => $form]);

        //if it is get, then give them the form
        if (! $request->isPost()){
            return $view;
        }

        //otherwise, set the data
        $form->setData($request->getPost());

        //if the form isn't valid, return it
        if (! $form->isValid()){
            return $view;
        }

        //get the data from the post
        $data = $request->getPost('role');

        $new_role = $this->api($form)->create('team-role', $data);

//        return new ViewModel(['data' => $data]);
        return $this->redirect()->toRoute('admin/teams/roles');


    }




}
