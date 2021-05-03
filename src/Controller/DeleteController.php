<?php
namespace Teams\Controller;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Exception\InvalidArgumentException;
use Omeka\Api\Request;
use Laminas\EventManager\Event;
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
    public function createNamedParameter(QueryBuilder $qb, $value,
                                         $prefix = 'omeka_'
    ) {
        $index = 0;
        $placeholder = $prefix . $index;
        $index++;
        $qb->setParameter($placeholder, $value);
        return ":$placeholder";
    }
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

        $criteria = ['id' => $id];

        $qb = $this->entityManager->createQueryBuilder();
        $entityClass = 'Teams\Entity\Team';

        $qb->select('omeka_root')->from($entityClass, 'omeka_root');
        foreach ($criteria as $field => $value) {
            $qb->andWhere($qb->expr()->eq(
                "omeka_root.$field",
                $this->createNamedParameter($qb, $value)
            ));
        }
        $qb->setMaxResults(1);

        $entity = $qb->getQuery()->getOneOrNullResult();


        $request = new Request('delete','team');
        $event = new Event('api.hydrate.pre', $this, [
            'entity' => $entity,
            'request' => $request,
        ]);
        $this->getEventManager()->triggerEvent($event);


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

        $user = $this->identity()->getRole();
        $id = $this->params()->fromRoute('id');
        $role = $this->entityManager->getRepository('Teams\Entity\TeamRole')
            ->findOneBy(['id'=> $id]);

        $request = $this->getRequest();

        //test to see if anyone has this role. If they do, can't delete.
        $role_users = $this->entityManager->getRepository('Teams\Entity\TeamUser')
            ->findBy(['role'=>$id]);
        if (! $request->isPost()) {
            return new ViewModel(
                [
                    'role'=>$role,
                    'role_users' => $role_users,
                    'user' => $user,
                ]
            );
        }
        if ($request->isPost()){
            if (! $role_users){
                if ($this->identity()->getRole() == 'global_admin' ){
                    if ($request->getPost('confirm') == 'Delete'){

                        $this->entityManager->remove($role);
                        $this->entityManager->flush();
                        $this->messenger()->addSuccess(sprintf('Successfully deleted role "%s"', $role->getName()));

                        return $this->redirect()->toRoute('admin/teams/roles');

                    } else{ return $this->redirect()->toRoute('admin/teams/roles');}
                } else {$this->messenger()->addError('Only global admins can delete roles');}

            } else {$this->messenger()->addError("Can't be deleted because teams are using the role");}
        }

    }

}
