<?php


namespace Teams\Controller;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Omeka\Form\ConfirmForm;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

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

    public function indexAction()
    {

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('r')
            ->from('Omeka\Entity\Item ', 'r')
            ->leftJoin(
                'Teams\Entity\TeamResource',
                'tr',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'r.id = tr.resource'
            )
            ->where('tr.team is NULL')
//            ->setMaxResults(10)
//            ->setFirstResult(0)
        ;

        $orphans =  $qb->getQuery()->getResult();

        $this->paginator(count($orphans));

        $page = $this->params()->fromQuery('page');
        $offset = ($page * 10) - 10;
        $orphans = array_slice($orphans,$offset,10);
        $formDeleteSelected = $this->getForm(ConfirmForm::class);
        $formDeleteSelected->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete'], true));
        $formDeleteSelected->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteSelected->setAttribute('id', 'confirm-delete-selected');

        $formDeleteAll = $this->getForm(ConfirmForm::class);
        $formDeleteAll->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete-all'], true));
        $formDeleteAll->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteAll->setAttribute('id', 'confirm-delete-all');
        $formDeleteAll->get('submit')->setAttribute('disabled', true);


        $view = new ViewModel;
        $view->setVariable('orphan', $orphans);

        $view->setVariable('formDeleteSelected', $formDeleteSelected);
        $view->setVariable('formDeleteAll', $formDeleteAll);

        return $view;
    }

}