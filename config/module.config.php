<?php
namespace Teams;

use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Segment;

return [
    'navigation' => [

        'AdminResource' => [
            [
                'label' => 'My Teams', // @translate
                'class' => 'o-icon-users teams',
                'route' => 'admin/teams',
                'pages' => [
                    [
                        'label' => 'Roles', // @translate
                        'route' => 'admin/teams/roles',
                        'resource' => 'Teams\Controller\Add',
                    ],
                ]
            ],
        ],
        'AdminGlobal' => [
            [
                'label' => 'All Teams', // @translate
                'class' => 'o-icon-users',
                //make new route
                'route' => 'admin/teams/all',
                //etc
                'controller' => 'setting',
                'action' => 'browse',
                'resource' => 'Omeka\Controller\Admin\Setting',
                'privilege' => 'browse',
            ],
            [
                'label' => 'Trash', // @translate
                'class' => 'fa-trash',
                //make new route
                'route' => 'admin/trash',
                //etc

                'resource' => 'Teams\Controller\Trash',
                'privilege' => 'update',
            ],
        ],

    ],
    'permissions' => [
        'acl_resources' => [
//            Entity\TeamUser::class,
//            Entity\TeamResource::class,
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
            'team-resource-template' => Api\Adapter\TeamResourceTemplateAdapter::class,
            'team-site' => Api\Adapter\TeamSiteAdapter::class,
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
            Form\Element\BlankTeamSelect::class => Service\Form\Element\BlankTeamSelectFactory::class,
            Form\Element\UserSelect::class => Service\Form\Element\UserSelectFactory::class,
            Form\Element\AllItemSetSelect::class => Service\Form\Element\AllItemSetSelectFactory::class,
            Form\Element\RoleSelect::class => Service\Form\Element\RoleSelectFactor::class,
            Form\Element\AllSiteSelect::class => Service\Form\Element\AllSiteSelectFactory::class,
            Form\Element\AllSiteSelectOrdered::class => Service\Form\Element\AllSiteSelectOrderedFactory::class,
            Form\ConfigForm::class => Service\Form\ConfigFormFactory::class,
            Form\Element\TeamName::class => Service\Form\Element\TeamNameFactory::class,
            Form\Element\RoleName::class => Service\Form\Element\RoleNameFactory::class,

        ],
    ],
    'view_helpers' =>[
        'invokables' => [
            'addTeam' => 'Teams\View\Helper\AddTeam',
            'bypassTeamsSelector' => 'Teams\View\Helper\BypassTeamsSortSelector',
            'teamUserSelector' => 'Teams\View\Helper\teamUserSelector',


        ],
        'factories' => [
            'roleAuth' => Service\ViewHelper\RoleAuthFactory::class,
        ]
    ],
    'controllers' => [
        'factories' => [
            'Teams\Controller\Index' => 'Teams\Service\IndexControllerFactory',
            'Teams\Controller\Delete' => 'Teams\Service\DeleteControllerFactory',
            'Teams\Controller\Add' => 'Teams\Service\AddControllerFactory',
            'Teams\Controller\Update' => 'Teams\Service\UpdateControllerFactory',
            'Teams\Controller\Trash' => 'Teams\Service\TrashControllerFactory',
        ]
    ],
    'controller_plugins' => [
        'factories' => [
            'teamAuth' => Service\ControllerPlugin\TeamAuthFactory::class
        ]
    ],
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
                            'all' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route' => '/all',
                                    'defaults' => [
                                        'controller' => 'Index',
                                        'action' => 'all'
                                    ]
                                ]
                            ],
                        ],
                    ],
                    //need to make a route for the resource page because there are no events inside the view
//                    'site' => [
//                        'type' => \Laminas\Router\Http\Literal::class,
//                        'options' => [
//                            'route' => '/site',
//                            'defaults' => [
//                                '__NAMESPACE__' => 'Omeka\Controller\SiteAdmin',
//                                '__SITEADMIN__' => true,
//                                'controller' => 'Index',
//                                'action' => 'index',
//                            ],
//                        ],
//                        'may_terminate' => true,
//                        'child_routes' => [
//                            'slug' => [
//                                'type' => \Laminas\Router\Http\Segment::class,
//                                'options' => [
//                                    'route' => '/s/:site-slug',
//                                    'constraints' => [
//                                        'site-slug' => '[a-zA-Z0-9_-]+',
//                                    ],
//                                    'defaults' => [
//                                        'action' => 'edit',
//                                    ],
//                                ],
//                                'may_terminate' => true,
//
//                                //this is the child route for the new site resources page
//                                'child_routes' => [
//                                    'resources' => [
//                                        'type' => \Laminas\Router\Http\Literal::class,
//                                        'options' => [
//                                            'route' => '/resources',
//                                            'defaults' => [
//                                                '__NAMESPACE__' => 'Teams\Controller',
//                                                'controller' => 'Index',
//                                                'action' => 'resources',
//                                            ],
//                                        ],
//                                    ],
//                                ],
//                            ],
//                            'add' => [
//                                'type' => \Laminas\Router\Http\Literal::class,
//                                'options' => [
//                                    'route' => '/add',
//                                    'defaults' => [
//                                        'action' => 'add',
//                                    ],
//                                ],
//                            ],
//                        ],
//                    ],

                    //TODO: move this out of the index controller
                    'del' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/item/:id/delete',
                            'defaults' => [
                                '__NAMESPACE__' => 'Teams\Controller',
                                //TODO make new action for the delete function
                                'controller' => 'Index',
                                'action' => 'delete',
                            ],
                        ],
                        ],
                    //TODO: move this out of the index controller
                    'batch_del'  => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/item/batch-delete',
                            'defaults' => [
                                '__NAMESPACE__' => 'Teams\Controller',
                                //need to make a anew action for the delete function?
                                'controller' => 'Index',
                                'action' => 'batch-delete',
                            ],
                        ],
                    ],

                    //this might be a cleaner route
//                    'perm_del'  => [
//                        'type' => Segment::class,
//                        'options' => [
//                            'route' => '/item/:id/perm-delete-confirm',
//                            'defaults' => [
//                                '__NAMESPACE__' => 'Teams\Controller',
//                                //need to make a anew action for the delete function?
//                                'controller' => 'Delete',
//                                'action' => 'perm-delete',
//                            ],
//                            'constraints' => [
//                                'id' => '\d+',
//                            ],
//                        ],
//                    ],
                    'trash' => [
                        'type' => Segment::class,
                        'options' => [
                            'route' => '/trash',
                            'defaults' => [
                                '__NAMESPACE__' => 'Teams\Controller',
                                'controller' => 'Trash',
                                'action' => 'index',
                                ],
                        ],

                    ]
                ],
            ],
        ],
    ],
];
