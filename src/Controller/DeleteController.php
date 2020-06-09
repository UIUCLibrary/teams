<?php
namespace Teams\Controller;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Exception\InvalidArgumentException;
use Omeka\Api\Request;
use Zend\EventManager\Event;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

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

    }

}
