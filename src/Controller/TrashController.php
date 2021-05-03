<?php


namespace Teams\Controller;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Omeka\Form\ConfirmForm;
use Teams\Form\TrashForms\DeleteAllForm;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class TrashController extends AbstractActionController
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

//    public function batchDeleteAllAction(){
//        echo "batch delete all";
//        return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
//    }
//
//    public function batchDeleteAction(){
//        echo "you made it";
//    }

    public function indexAction()
    {

        $qb = $this->entityManager->createQueryBuilder();

        if ($this->request->isPost()){
            if ($this->identity()->getRole() == 'global_admin'){
                if ($this->getRequest()->getPost('submit') == 'Delete Selected'){
                    foreach ($this->getRequest()->getPost('resource_ids') as $id):
                        $id = (int) $id;
                        $this->api()->delete('items', ['id' =>$id]);
                    endforeach;
                } elseif ($this->getRequest()->getPost('submit') == 'Delete All'){

                    $qb1 = $this->entityManager->createQueryBuilder();
                    $qb1->select('r')
                        ->from('Omeka\Entity\Item ', 'r')
                        ->leftJoin(
                            'Teams\Entity\TeamResource',
                            'tr',
                            \Doctrine\ORM\Query\Expr\Join::WITH,
                            'r.id = tr.resource'
                        )
                        ->where('tr.team is NULL');

                    $orphans = $qb1->getQuery()->getResult();

                    foreach ($orphans as $resource):
                        $this->api()->delete('items', $resource->getId());
                    endforeach;


                }

            }


        }
        $params = $this->params();
        if ($params()->fromQuery('sort_order') === 'asc'){
            $order = 'asc';
        } else {
            $order = 'desc';
        }
        $sort_options = [
            'title' => 'title',
            'id' => 'id',
            'resource_class_label' => 'class',
            'owner_name' => 'owner',
            'created' => 'created',
        ];
        if (key_exists($params->fromQuery('sort_by'), $sort_options)){
            $sort = $sort_options[$params->fromQuery('sort_by')];
        } else {
            $sort = 'created';
        }



        $qb->select('r_trash')
            ->from('Omeka\Entity\Item ', 'r_trash')
            ->leftJoin(
                'Teams\Entity\TeamResource',
                'tr_trash',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'r_trash.id = tr_trash.resource'
            )
            ->where('tr_trash.team is NULL')
            ->orderBy('r_trash.' . $sort, $order);

        $orphans =  $qb->getQuery()->getResult();


        $this->paginator(count($orphans));

        $page = $this->params()->fromQuery('page');
        $offset = ($page * 10) - 10;
        $orphans = array_slice($orphans,$offset,10);
        $formDeleteSelected = $this->getForm(ConfirmForm::class);
        $formDeleteSelected->setAttribute('action', $this->url()->fromRoute('admin/trash', ['action' => 'batch-delete'], true));
        $formDeleteSelected->setButtonLabel('Delete Selected'); // @translate
        $formDeleteSelected->setAttribute('id', 'confirm-delete-selected');

        $formDeleteAll = $this->getForm(DeleteAllForm::class);
        $formDeleteAll->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete-all'], true));
        $formDeleteAll->setButtonLabel('Delete All'); // @translate
        $formDeleteAll->setAttribute('id', 'confirm-delete-all');
        $formDeleteAll->get('submit')->setAttribute('disabled', true);


        $view = new ViewModel;
        $view->setVariable('orphan', $orphans);



        $view->setVariable('formDeleteSelected', $formDeleteSelected);
        $view->setVariable('formDeleteAll', $formDeleteAll);

        return $view;


    }

}