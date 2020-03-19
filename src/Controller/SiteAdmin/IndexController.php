<?php
namespace Teams\Controller\SiteAdmin;

use Doctrine\ORM\EntityManager;
use Omeka\Db\Event\Subscriber\Entity;
use Omeka\Form\ConfirmForm;
use Omeka\Form\SiteForm;
use Omeka\Form\SitePageForm;
use Omeka\Form\SiteSettingsForm;
use Omeka\Mvc\Exception;
use Omeka\Site\Navigation\Link\Manager as LinkManager;
use Omeka\Site\Navigation\Translator;
use Omeka\Site\Theme\Manager as ThemeManager;
use Teams\Entity\TeamSite;
use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    /**
     * @var ThemeManager
     */
    protected $themes;

    /**
     * @var LinkManager
     */
    protected $navLinks;

    /**
     * @var Translator
     */
    protected $navTranslator;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    public function __construct(ThemeManager $themes, LinkManager $navLinks,
        Translator $navTranslator, EntityManager $entityManager
    ) {
        $this->themes = $themes;
        $this->navLinks = $navLinks;
        $this->navTranslator = $navTranslator;
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
            if ($old_current){
                $old_current->setCurrent(null);
                //no idea why, but this function fails in this controller only unless the action is flushed first
                $em->flush();}
            $new_current->setCurrent(true);
            $em->flush();


        }
    }

    public function teamResources($resource_type, $page, $user_id, $active = true, $team_id = null)
    {
        //get the get the user's team_user with the is_current identifier
        $team_user = $this->entityManager->getRepository('Teams\Entity\TeamUser')->findOneBy(['user' => $user_id, 'is_current' => 1 ]);

        //get their current team
        $team = $team_user->getTeam();

        //get that team's sites
        $team_sites = $team->getTeamSites();

        $sites_api_obj = array();


        //TODO refactor so that this foreach starts at the appropriate index so we are only fetching what is needed
        // for the requested page
        foreach ($team_sites as $site):
            $sites_api_obj[] =  $this->api()->read($resource_type, $site->getSite()->getId())->getContent();
        endforeach;



        //this logic looks at which page they requested, how many items per page, and collects the appropriate results.
        $per_page = 10;

        //get what page of the results is requested, if null set to page 1
        if($page == null){
            $page = 1;
        }



        //starting index for that page
        $start_i = ($per_page * $page) - $per_page;

        //max index for all results sentinel
        $max_i = count($sites_api_obj);

        //get per_page number of results unless on the last page
        if ($max_i < $start_i + $per_page){
            $end_i = $max_i;
        }else{$end_i = $start_i + $per_page;}

        //an array to hold the resources for just the page
        $page_resources = array();
        for ($i = $start_i; $i < $end_i; $i++) {

            $page_resources[] = $sites_api_obj[$i];
        }


        return array('page_resources'=>$page_resources, 'team_resources'=>$sites_api_obj);



    }


    public function indexAction()
    {
        $user_id = $this->identity()->getId();
        $response = $this->teamResources('sites', $this->params()->fromQuery('page'),$user_id);


        $this->setBrowseDefaults('title', 'asc');
        $this->paginator(count($response['team_resources']), $this->params()->fromQuery('page'));

        $request = $this->getRequest();
        if ($request->isPost()){
            $this->changeCurrentTeamAction($user_id);
            return $this->redirect()->toRoute('admin/site',['controller'=>'index', 'action'=>'browse']);

        }

        $view = new ViewModel;
        $view->setVariable('sites', $response['page_resources']);
        return $view;
    }

    public function addAction()
    {
        $form = $this->getForm(SiteForm::class);
        $themes = $this->themes->getThemes();
        if ($this->getRequest()->isPost()) {
            $formData = $this->params()->fromPost();
            $itemPool = $formData;
            unset($itemPool['csrf'], $itemPool['o:is_public'], $itemPool['o:title'], $itemPool['o:slug'],
                $itemPool['o:theme']);
            $formData['o:item_pool'] = $itemPool;
            foreach ($formData['team'] as $team):

//just added this part from the resources section and got the to the **here** part before I had to call it a night
                $get_items = $this->entityManager->createQuery("SELECT resource.id FROM Omeka\Entity\Resource resource WHERE resource INSTANCE OF Omeka\Entity\Item");
                $all_items = $get_items->getScalarResult();
                $site_resources = array();
                foreach ($formData['team'] as $team_id):
                    $site_team = $this->entityManager->getRepository('Teams\Entity\Team')->findOneBy(['id'=>$team_id]);
                    $team_resources = $site_team->getTeam()->getTeamResources()->toArray();
                    $site_resources = array_merge($site_resources, $team_resources);
                endforeach;
/// **here**
                $getIds = function ($resource){
                    if (is_object($resource)){
                        return $resource->getResource()->getId();
                    }elseif(is_array($resource)){
                        return $resource['id'];
                    }else{return null;}
                };
                $team_res_ids = array_map($getIds, $site_resources);
                $site_items = array_intersect($all_item_ids, $team_res_ids);





            array_push($itemPool['property'], array('joiner'=>'or', 'property'=>'', 'type'=>'res', 'text'=> '5'));

            $form->setData($formData);
            if ($form->isValid()) {
                $response = $this->api($form)->create('sites', $formData);
                $em  = $this->entityManager;

                $site_id = $response->getContent()->id();
                $site = $em->getRepository('Omeka\Entity\Site')->findOneBy(['id'=>$site_id]);

                foreach ($formData['team'] as $team_id):
                    $team = $em->getRepository('Teams\Entity\Team')->findOneBy(['id' => $team_id]);
                    $trt = new TeamSite($team, $site);
                    $em->persist($trt);
                endforeach;
                $em->flush();


                if ($response) {
                    $this->messenger()->addSuccess('Site successfully created'); // @translate
//                    return $this->redirect()->toUrl($response->getContent()->url());
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $user_id = $this->identity()->getId();
        $em = $this->entityManager;

        $teams = $em->getRepository('Teams\Entity\TeamUser');
        if ($teams->findOneBy(['user'=>$user_id, 'is_current'=>1])){
            $default_team = $teams->findOneBy(['user'=>$user_id, 'is_current'=>1]);
        } elseif ($teams->findBy(['user' => $user_id])){
            $default_team = $teams->findOneBy(['user' => $user_id], ['name']);
        } else {
            $default_team = null;
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $view->setVariable('themes', $themes);
        $view->setVariable('default_team', $default_team);
        if ($this->request->isPost()){
            $view->setVariable('item_pool',$itemPool['property']);
        }
        return $view;
    }

    public function editAction()
    {
        $site = $this->currentSite();
        $form = $this->getForm(SiteForm::class);
        $form->setData($site->jsonSerialize());

        if ($this->getRequest()->isPost()) {
            $formData = $this->params()->fromPost();
            $form->setData($formData);
            if ($form->isValid()) {
                $response = $this->api($form)->update('sites', $site->id(), $formData, [], ['isPartial' => true]);
                if ($response) {
                    $this->messenger()->addSuccess('Site successfully updated'); // @translate
                    // Explicitly re-read the site URL instead of using
                    // refresh() so we catch updates to the slug
                    return $this->redirect()->toUrl($site->url());
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }
        $em = $this->entityManager;
        $site_id = $this->currentSite()->id();
        $team_sites = $em->getRepository('Teams\Entity\TeamSite')->findBy(['site'=>$site_id]);
        $current_teams = array();
        foreach ($team_sites as $team_site):
            $current_teams[] = $team_site->getTeam()->getId();
        endforeach;

        $view = new ViewModel;
        $view->setVariable('site', $site);
        $view->setVariable('resourceClassId', $this->params()->fromQuery('resource_class_id'));
        $view->setVariable('itemSetId', $this->params()->fromQuery('item_set_id'));
        $view->setVariable('form', $form);
        $view->setVariable('current_teams' , $current_teams);
        return $view;
    }

    public function settingsAction()
    {
        $site = $this->currentSite();
        if (!$site->userIsAllowed('update')) {
            throw new Exception\PermissionDeniedException(
                'User does not have permission to edit site settings'
            );
        }
        $form = $this->getForm(SiteSettingsForm::class);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $data = $form->getData();
                $fieldsets = $form->getFieldsets();
                unset($data['csrf']);
                foreach ($data as $id => $value) {
                    if (array_key_exists($id, $fieldsets) && is_array($value)) {
                        // De-nest fieldsets.
                        foreach ($value as $fieldsetId => $fieldsetValue) {
                            $this->siteSettings()->set($fieldsetId, $fieldsetValue);
                        }
                    } else {
                        $this->siteSettings()->set($id, $value);
                    }
                }
                $this->messenger()->addSuccess('Settings successfully updated'); // @translate
                return $this->redirect()->refresh();
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('site', $site);
        $view->setVariable('form', $form);
        return $view;
    }

    public function addPageAction()
    {
        $site = $this->currentSite();
        $form = $this->getForm(SitePageForm::class, ['addPage' => $site->userIsAllowed('update')]);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $formData = $form->getData();
                $formData['o:site']['o:id'] = $site->id();
                $response = $this->api($form)->create('site_pages', $formData);
                if ($response) {
                    $page = $response->getContent();
                    if (isset($formData['add_to_navigation']) && $formData['add_to_navigation']) {
                        // Add page to navigation.
                        $navigation = $site->navigation();
                        $navigation[] = [
                            'type' => 'page',
                            'links' => [],
                            'data' => ['id' => $page->id(), 'label' => null],
                        ];
                        $this->api()->update('sites', $site->id(), ['o:navigation' => $navigation], [], ['isPartial' => true]);
                    }
                    $sp = json_decode("{'o:summary':'','property':[{'joiner':'and','property':'','type':'res','text':'470'}, {'joiner':'or','property':'','type':'res','text':'4'}],'resource_class_id':[''],'resource_template_id':[''],'item_set_id':[''],'site_id':''}", true);
                    $new_item = ['joiner'=>'or', 'property'=>'', 'type'=>'res', 'text'=>'7'];
                    array_push($sp["property"],$new_item);

                    $json_sp = json_encode($sp);
                    $this->api()->update('sites', $site->id(), ['o:navigation' => $navigation], [], ['isPartial' => true]);
                    $this->api()->update('sites', $site->id(), ['o:item_pool' => $json_sp], [], ['isPartial'=>true]);
                    $site_entity = $this->entityManager->getRepository('Omeka\Entity\Site')->findOneBy(['id'=>$site->id()]);
                    $site_entity->setItemPool($json_sp);
                    $this->entityManager->flush();
                    $this->messenger()->addSuccess('Page successfully created testing'); // @translate
                    return $this->redirect()->toRoute(
                        'admin/site/slug/page/default',
                        [
                            'page-slug' => $page->slug(),
                            'action' => 'edit',
                        ],
                        true
                    );
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('site', $site);
        $view->setVariable('form', $form);
        return $view;
    }

    public function navigationAction()
    {
        $site = $this->currentSite();
        $form = $this->getForm(Form::class)->setAttribute('id', 'site-form');

        if ($this->getRequest()->isPost()) {
            $formData = $this->params()->fromPost();
            $jstree = json_decode($formData['jstree'], true);
            $formData['o:navigation'] = $this->navTranslator->fromJstree($jstree);
            $form->setData($formData);
            if ($form->isValid()) {
                $response = $this->api($form)->update('sites', $site->id(), $formData, [], ['isPartial' => true]);
                if ($response) {
                    $this->messenger()->addSuccess('Navigation successfully updated'); // @translate
                    return $this->redirect()->refresh();
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('navTree', $this->navTranslator->toJstree($site));
        $view->setVariable('form', $form);
        $view->setVariable('site', $site);
        return $view;
    }


    public function teamDetailAction()
    {
        $view = new ViewModel;
        $id = $this->params()->fromRoute('id');
        $response = $this->api()->read('team', ['id' => $id]);


        $em = $this->entityManager;

        $resources = [
            'items'=> ['count' => 0, 'entity' => 'Item', 'team_entity' => 'TeamResource', 'fk' => 'resource'],
            'item sets' => ['count' => 0, 'entity' => 'ItemSet', 'team_entity' => 'TeamResource', 'fk' => 'resource'],
            'media' => ['count' => 0, 'entity' => 'Media', 'team_entity' => 'TeamResource', 'fk' => 'resource'],
            'resource templates' => ['count' => 0, 'entity' => 'ResourceTemplate', 'team_entity' => 'TeamResourceTemplate', 'fk' => 'resource_template'],
            'sites' => ['count' => 0, 'entity' => 'Site', 'team_entity' => 'TeamSite', 'fk' => 'site']
        ];

        foreach ($resources as $key => $resource):
            //I imagine this as like a subquery that gets the list of item ids
            $sub_query = $em->createQueryBuilder();
            $sub_query->select('r.id')
                ->from('Omeka\Entity\\' . $resource['entity'], 'r');

            $ids = $sub_query->getQuery()->getArrayResult();

            //get the count of the total number of team items
            $qb = $em->createQueryBuilder();

            $qb->select('count(tr.' . $resource['fk'] . ')')
                ->from('Teams\Entity\\' . $resource['team_entity'], 'tr')
                ->where('tr.team = ?1')
                ->andWhere('tr.' . $resource['fk'] . ' in (:ids)')
                ->setParameter('ids', $ids)
            ;
            $qb->setParameter(1, $this->params('id'));
            $resources[$key]['count'] += $qb->getQuery()->getSingleScalarResult();
        endforeach;

        $view->setVariable('resources', $resources);


        $view->setVariable('response', $response);


        return $view;
    }




    public function resourcesAction()
    {
        $site = $this->currentSite();
        $site_id = $site->id();
        $em = $this->entityManager;
        $site_teams = $em->getRepository('Teams\Entity\TeamSite')->findBy(['site'=>$site_id]);
        $site_resources = array();
        $site_resource_templates = array();
        $get_item_sets = $this->entityManager->createQuery("SELECT resource.id FROM Omeka\Entity\Resource resource WHERE resource INSTANCE OF Omeka\Entity\ItemSet");
        $get_items = $this->entityManager->createQuery("SELECT resource.id FROM Omeka\Entity\Resource resource WHERE resource INSTANCE OF Omeka\Entity\Item");
        $get_media = $this->entityManager->createQuery("SELECT resource.id FROM Omeka\Entity\Resource resource WHERE resource INSTANCE OF Omeka\Entity\Media");

        //thought that a scalar would be easier but from what I can tell in this case getScalarResult===getArrayResult===getResult
        //leaving it this way because it sounds closest to the kind of data I actually want to get out
        $all_item_sets = $get_item_sets->getScalarResult();
        $all_items = $get_items->getScalarResult();
        $all_media = $get_media->getScalarResult();

        //combine all of the resources from all of the teams the site is associated with
        //maintaining the distinction between resources and resource templates from core omeka
        foreach ($site_teams as $site_team):
            $team_resources = $site_team->getTeam()->getTeamResources()->toArray();
            $team_resource_templates = $site_team->getTeam()->getTeamResourceTemplates()->toArray();
            $site_resources = array_merge($site_resources, $team_resources);
            $site_resource_templates = array_merge($site_resource_templates, $team_resource_templates);

        endforeach;

        //returns the id when either a TeamResource object or row from a doctrine result set is provided
        $getIds = function ($resource){
            if (is_object($resource)){
                return $resource->getResource()->getId();
            }elseif(is_array($resource)){
                return $resource['id'];
            }else{return null;}
        };

        //if there is a way to do this by testing TeamResources with INSTANCE OF it would be simpler, but I was not
        //able to get that to work. So using an array of Omeka itemsets as discriminator

        //get just the resource ids from TeamResources associated with the site
        $team_res_ids = array_map($getIds, $site_resources);

        //get the ids from all omeka item sets
        $all_item_set_ids = array_map($getIds, $all_item_sets);

        //get the ids from all omeka items
        $all_item_ids = array_map($getIds, $all_items);

        //get the ids from all omeka media
        $all_media_ids = array_map($getIds, $all_media);

        //filter out just the site's itemsets
        $site_item_sets = array_intersect($all_item_set_ids, $team_res_ids);

        //filterout just the site's items
        $site_items = array_intersect($all_item_ids, $team_res_ids);

        //filter out just the site's media
        $site_media = array_intersect($all_media_ids, $team_res_ids);




        $form = $this->getForm(Form::class)->setAttribute('id', 'site-form');

        if ($this->getRequest()->isPost()) {
            $formData = $this->params()->fromPost();
            $form->setData($formData);
            if ($form->isValid()) {
                $itemPool = $formData;
                unset($itemPool['form_csrf']);
                unset($itemPool['site_item_set']);

                $itemSets = isset($formData['o:site_item_set']) ? $formData['o:site_item_set'] : [];

                $updateData = ['o:item_pool' => $itemPool, 'o:site_item_set' => $itemSets];
                $response = $this->api($form)->update('sites', $site->id(), $updateData, [], ['isPartial' => true]);
                if ($response) {
                    $this->messenger()->addSuccess('Site resources successfully updated'); // @translate
                    return $this->redirect()->refresh();
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $itemCount = $this->api()
            ->search('items', ['limit' => 0, 'site_id' => $site->id()])
            ->getTotalResults();

        $itemSets = [];
//        foreach ($site_item_sets as $item_set_id) {
//            $itemSet = $this->api()->read('item_sets', ['id'=>$item_set_id])->getContent();
        foreach ($site->siteItemSets() as $siteItemSet) {
            $itemSet = $siteItemSet->itemSet();
            $owner = $itemSet->owner();
            $itemSets[] = [
                'id' => $itemSet->id(),
                'title' => $itemSet->displayTitle(),
                'email' => $owner ? $owner->email() : null,
            ];
        }


        $view = new ViewModel;
        $view->setVariable('site', $site);
        $view->setVariable('form', $form);
        $view->setVariable('itemCount', $itemCount);
        $view->setVariable('itemSets', $itemSets);
        $view->setVariable('site_resources', $site_resources);
        $view->setVariable('site_media', $site_media);
        $view->setVariable('site_items', $site_items);
        $view->setVariable('site_item_sets', $site_item_sets);
        $view->setVariable('site_teams', $site_teams);
        return $view;
    }

    public function usersAction()
    {
        $site = $this->currentSite();
        $form = $this->getForm(Form::class)->setAttribute('id', 'site-form');

        if ($this->getRequest()->isPost()) {
            $formData = $this->params()->fromPost();
            $form->setData($formData);
            if ($form->isValid()) {
                $response = $this->api($form)->update('sites', $site->id(), $formData, [], ['isPartial' => true]);
                if ($response) {
                    $this->messenger()->addSuccess('User permissions successfully updated'); // @translate
                    return $this->redirect()->refresh();
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $users = $this->api()->search('users', ['sort_by' => 'name']);

        $view = new ViewModel;
        $view->setVariable('site', $site);
        $view->setVariable('form', $form);
        $view->setVariable('users', $users->getContent());
        return $view;
    }

    public function themeAction()
    {
        $site = $this->currentSite();
        if (!$site->userIsAllowed('update')) {
            throw new Exception\PermissionDeniedException(
                'User does not have permission to edit site theme settings'
            );
        }
        $form = $this->getForm(Form::class)->setAttribute('id', 'site-theme-form');
        if ($this->getRequest()->isPost()) {
            $formData = $this->params()->fromPost();
            $form->setData($formData);
            if ($form->isValid()) {
                $response = $this->api($form)->update('sites', $site->id(), $formData, [], ['isPartial' => true]);
                if ($response) {
                    $this->messenger()->addSuccess('Site theme successfully updated'); // @translate
                    return $this->redirect()->refresh();
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $view->setVariable('site', $site);
        $view->setVariable('themes', $this->themes->getThemes());
        $view->setVariable('currentTheme', $this->themes->getCurrentTheme());
        return $view;
    }

    public function themeSettingsAction()
    {
        $site = $this->currentSite();

        if (!$site->userIsAllowed('update')) {
            throw new Exception\PermissionDeniedException(
                'User does not have permission to edit site theme settings'
            );
        }

        $theme = $this->themes->getTheme($site->theme());
        $config = $theme->getConfigSpec();

        $view = new ViewModel;
        if (!($config && $config['elements'])) {
            return $view;
        }

        /** @var Form $form */
        $form = $this->getForm(Form::class)->setAttribute('id', 'site-form');

        foreach ($config['elements'] as $elementSpec) {
            $form->add($elementSpec);
        }

        // Fix to manage empty values for selects and multicheckboxes.
        $inputFilter = $form->getInputFilter();
        foreach ($form->getElements() as $element) {
            if ($element instanceof \Zend\Form\Element\MultiCheckbox
                || ($element instanceof \Zend\Form\Element\Select
                    && $element->getOption('empty_option') !== null)
            ) {
                $inputFilter->add([
                    'name' => $element->getName(),
                    'allow_empty' => true,
                ]);
            }
        }

        $oldSettings = $this->siteSettings()->get($theme->getSettingsKey());
        if ($oldSettings) {
            $form->setData($oldSettings);
        }

        $view->setVariable('form', $form);
        $view->setVariable('theme', $theme);
        if (!$this->getRequest()->isPost()) {
            return $view;
        }

        $postData = $this->params()->fromPost();
        $form->setData($postData);
        if ($form->isValid()) {
            $data = $form->getData();
            unset($data['form_csrf']);
            $this->siteSettings()->set($theme->getSettingsKey(), $data);
            $this->messenger()->addSuccess('Theme settings successfully updated'); // @translate
            return $this->redirect()->refresh();
        }

        $this->messenger()->addFormErrors($form);

        return $view;
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            $form = $this->getForm(ConfirmForm::class);
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $response = $this->api($form)->delete('sites', ['slug' => $this->params('site-slug')]);
                if ($response) {
                    $this->messenger()->addSuccess('Site successfully deleted'); // @translate
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }
        return $this->redirect()->toRoute('admin/site');
    }

    public function showAction()
    {
        $site = $this->currentSite();
        $site_id = $site->id();
        $em = $this->entityManager;
        $site_teams = $em->getRepository('Teams\Entity\TeamSite')->findBy(['site'=>$site_id]);
        $view = new ViewModel;

        $view->setVariable('site', $site);
        $view->setVariable('site_teams', $site_teams);
        return $view;
    }

    public function deleteConfirmAction()
    {
        $site = $this->currentSite();
        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate('common/delete-confirm-details');
        $view->setVariable('resourceLabel', 'site'); // @translate
        $view->setVariable('partialPath', 'omeka/site-admin/index/show-details');
        $view->setVariable('resource', $site);
        return $view;
    }

    public function navigationLinkFormAction()
    {
        $site = $this->currentSite();
        $link = $this->navLinks->get($this->params()->fromPost('type'));

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate($link->getFormTemplate());
        $view->setVariable('data', $this->params()->fromPost('data'));
        $view->setVariable('site', $site);
        $view->setVariable('link', $link);
        return $view;
    }

    public function sidebarItemSelectAction()
    {
        $this->setBrowseDefaults('created');
        $site = $this->currentSite();

        $query = $this->params()->fromQuery();
        $query['site_id'] = $site->id();

        $response = $this->api()->search('items', $query);
        $items = $response->getContent();
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate('omeka/admin/item/sidebar-select');
        $view->setVariable('search', $this->params()->fromQuery('search'));
        $view->setVariable('resourceClassId', $this->params()->fromQuery('resource_class_id'));
        $view->setVariable('itemSetId', $this->params()->fromQuery('item_set_id'));
        $view->setVariable('items', $items);
        $view->setVariable('showDetails', false);
        return $view;
    }
}
