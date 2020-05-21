<?php
namespace Teams;

use Omeka\Api\Adapter\ItemAdapter;
use Teams\Controller\UpdateController;
use Zend\Db\Sql\Sql;
use Zend\Router\Http\Literal;
use Zend\Router\Http\Segment;
use Teams\Controller\CurrentTeamController;

//$teamname = (new Controller\CurrentTeamController)->getCurrentTeam();
$teamname = 'Example Team';
return [
    'navigation' => [
        'AdminResource' => [
            [
                'label' => 'My Teams', // @translate
                'class' => 'o-icon-users',
                'route' => 'admin/teams',
                'pages' => [
                    [
                        'label' => 'Roles', // @translate
                        'route' => 'admin/teams/roles',
                        'controller' => 'Index',
                        'action' => 'roleIndex',
//                        'privilege' => 'roleIndex',
                        'visible' => true,
                    ],
                ]
            ],

        ]
    ],
    'permissions' => [
        'acl_resources' => [
            Entity\TeamUser::class,
            Entity\TeamResource::class,
            Controller\AddController::class,
            Controller\IndexController::class,
            Controller\DeleteController::class,
            Controller\ItemController::class,
            Controller\UpdateController::class,

        ],
    ],
    'api_adapters' => [
        'invokables' => [
            'team' => Api\Adapter\TeamAdapter::class,
            'team-user' => Api\Adapter\TeamUserAdapter::class,
            'team-role' => Api\Adapter\TeamRoleAdapter::class,
            'team-resource' => Api\Adapter\TeamResourceAdapter::class,
//            'items' => Api\Adapter\ItemAdapter::class,
//            'nothing' => ItemAdapter::class,


        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            __DIR__ . '/../view',
        ]
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\TeamForm::class => Form\TeamForm::class,
        ],
        'factories' => [
            Form\Element\TeamSelect::class => Service\Form\Element\TeamSelectFactory::class,
            Form\Element\AllTeamSelect::class => Service\Form\Element\AllTeamSelectFactory::class,
            Form\Element\UserSelect::class => Service\Form\Element\UserSelectFactory::class,
            Form\Element\AllItemSetSelect::class => Service\Form\Element\AllItemSetSelectFactory::class,
            Form\Element\RoleSelect::class => Service\Form\Element\RoleSelectFactor::class,



        ],
    ],
    'view_helpers' =>[
        'invokables' => [
            'addTeam' => 'Teams\View\Helper\AddTeam',
            'searchFilters' => 'Teams\View\Helper\SearchFilters',

        ]
    ],
    'controllers' => [

        'invokables' => [
            'Omeka\Controller\SiteAdmin\Page' => 'Teams\Controller\SiteAdmin\PageController',

        ],
        'factories' => [
            'Teams\Controller\Index' => 'Teams\Model\IndexControllerFactory',
            'Teams\Controller\Delete' => 'Teams\Model\DeleteControllerFactory',
            'Teams\Controller\Add' => 'Teams\Model\AddControllerFactory',
            'Teams\Controller\Update' => 'Teams\Model\UpdateControllerFactory',
            //to make the item controlller do what I made it do by editing the Omeka ItemController, just add this
            //and route it to a different factory that invokes a controller of my design
            'Omeka\Controller\Admin\Item' => 'Teams\Model\ItemControllerFactory',
//            'Omeka\Controller\Admin\ItemSet' => 'Teams\Model\ItemSetControllerFactory',
//            'Omeka\Controller\Admin\Media' => 'Teams\Model\MediaControllerFactory',
            'Omeka\Controller\Admin\ResourceTemplate' => 'Teams\Model\ResourceTemplateControllerFactory',
            'Omeka\Controller\SiteAdmin\Index' => 'Teams\Model\SiteIndexControllerFactory',
            'Omeka\Controller\Admin\User' => 'Teams\Model\UserControllerFactory',





//            'Teams\Controller\TeamResourceFilter' => 'Teams\Module\TeamResourceFilterFilter'

            //this error means that it is a bad route
//            'Omeka\Controller\Admin\Teams' => 'Teams\Model\IndexControllerFactory',
        ]
    ],
    // This lines opens the configuration for the RouteManager
    'router' => [
        // Open configuration for all possible routes
        'routes' => [
            'admin' =>[
                'child_routes' => [
                    'teams' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/teams',
                            'defaults' => [
                                '__NAMESPACE__' => 'Teams\Controller',
                                'controller' => 'Index',
                                'action' => 'index',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'detail' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/:id',
                                    'defaults' => [
                                        'action' => 'teamDetail',
                                    ],
                                    'constraints' => [
                                        'id' => '[1-9]\d*'
                                    ]
                                ],
                                'may_terminate' => true,
                                'child_routes' => [
                                    'delete' => [
                                        'type' => 'Literal',
                                        'options' => [
                                            'route' => '/delete',
                                            'defaults' => [
                                                'controller' => 'Delete',
                                                'action' => 'teamDelete',
                                            ],

                                        ],
                                    ],
                                    'update' => [
                                        'type' => 'Literal',
                                        'options' => [
                                            'route' => '/update',
                                            'defaults' => [
                                                //TODO change to correct controller when complete
                                                'controller' => 'Update',
                                                'action' => 'teamUpdate',
                                            ],

                                        ],
                                    ],

                                ]
                            ],
                            'add' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/add',
                                    'defaults' => [
                                        //TODO change to correct controller when complete
                                        'controller' => 'Add',
                                        'action' => 'teamAdd'
                                    ]

                                ]
                            ],
                            'user' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/user',
                                    'defaults' => [
                                        //TODO change to correct controller when complete
                                        'controller' => 'Update',
                                        'action' => 'user'
                                    ]

                                ]
                            ],
                            'roles' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/roles',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'Teams\Controller',
                                        'controller' => 'Index',
                                        'action' => 'roleIndex',
                                    ]
                                ],
                                'may_terminate' => true,
                                'child_routes' => [
                                    'detail' => [
                                        'type' => 'Segment',
                                        'options' => [
                                            'route' => '/:id',
                                            'defaults' => [
                                                'action' => 'roleDetail',
                                            ],
                                            'constraints' => [
                                                'id' => '[1-9]\d*'
                                            ]
                                        ],
                                        'may_terminate' => true,
                                        'child_routes' => [
                                            'delete' => [
                                                'type' => 'Literal',
                                                'options' => [
                                                    'route' => '/delete',
                                                    'defaults' => [
                                                        'controller' => 'Delete',
                                                        'action' => 'roleDelete',
                                                    ],

                                                ],
                                            ],
                                            'update' => [
                                                'type' => 'Literal',
                                                'options' => [
                                                    'route' => '/update',
                                                    'defaults' => [
                                                        //TODO change to correct controller when complete
                                                        'controller' => 'Update',
                                                        'action' => 'roleUpdate',
                                                    ],

                                                ],
                                            ],

                                        ]
                                    ],
                                    'add' => [
                                        'type' => 'Literal',
                                        'options' => [
                                            'route' => '/add',
                                            'defaults' => [
                                                //TODO change to correct controller when complete
                                                'controller' => 'Add',
                                                'action' => 'roleAdd'
                                            ]

                                        ]
                                    ],
                                    'roles' => [
                                        'type' => Literal::class,
                                        'options' => [
                                            'route' => '/roles',
                                            'defaults' => [
                                                '__NAMESPACE__' => 'Teams\Controller',
                                                'controller' => 'Index',
                                                'action' => 'roleIndex',
                                            ]
                                        ],
                                    ],
                                ],

                            ],
                            'current' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/current',
                                    'defaults' => [
                                        'controller' => 'Update',
                                        'action' => 'currentTeam'
                                    ]
                                ]
                            ],
                            'change' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/change',
                                    'defaults' => [
                                        'controller' => 'ChangeTeam',
                                        'action' => 'change'
                                    ]
                                ]
                            ],
                        ],
                    ],
//                    'resource-template' => [
//                        'type' => Literal::class,
//                        'options' => [
//                            'route' => 'resource-template',
//                            'defaults' => [
//                                '__NAMESPACE__' => 'Teams\Controller',
//                                'controller' => 'Index',
//                                'action' => 'index',
//                            ],
//                        ]
//
//                    ]
                ],
            ],
        ],
    ],
];