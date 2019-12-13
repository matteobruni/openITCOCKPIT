<?php
// Copyright (C) <2015>  <it-novum GmbH>
//
// This file is dual licensed
//
// 1.
//	This program is free software: you can redistribute it and/or modify
//	it under the terms of the GNU General Public License as published by
//	the Free Software Foundation, version 3 of the License.
//
//	This program is distributed in the hope that it will be useful,
//	but WITHOUT ANY WARRANTY; without even the implied warranty of
//	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//	GNU General Public License for more details.
//
//	You should have received a copy of the GNU General Public License
//	along with this program.  If not, see <http://www.gnu.org/licenses/>.
//

// 2.
//	If you purchased an openITCOCKPIT Enterprise Edition you can use this file
//	under the terms of the openITCOCKPIT Enterprise Edition license agreement.
//	License agreement and license key will be shipped with the order
//	confirmation.

namespace MapModule\Controller;

use App\Model\Table\HostgroupsTable;
use Cake\ORM\TableRegistry;
use itnovum\openITCOCKPIT\Core\AngularJS\Api;
use itnovum\openITCOCKPIT\Core\MapConditions;
use itnovum\openITCOCKPIT\Core\ServicestatusFields;
use itnovum\openITCOCKPIT\Core\System\FileUploadSize;
use itnovum\openITCOCKPIT\Core\Views\UserTime;
use itnovum\openITCOCKPIT\Filter\MapFilter;
use itnovum\openITCOCKPIT\Maps\MapForAngular;
use Statusengine\PerfdataParser;
use Symfony\Component\Finder\Finder;


/**
 * Class MapeditorsController
 * @property Map $Map
 * @property Mapitem $Mapitem
 * @property MapUpload $MapUpload
 * @property Mapline $Mapline
 * @property Mapgadget $Mapgadget
 * @property Maptext $Maptext
 * @property Mapicon $Mapicon
 * @property Mapsummaryitem $Mapsummaryitem
 * @property Host $Host
 * @property Service $Service
 * @property Hoststatus $Hoststatus
 * @property Servicestatus $Servicestatus
 *
 */
class MapeditorsController extends AppController {
    public $layout = 'blank';

    public $uses = [
        'MapModule.Map',
        'MapModule.MapNew',
        'MapModule.Mapitem',
        'MapModule.MapUpload',
        'MapModule.Mapline',
        'MapModule.Mapgadget',
        'MapModule.Maptext',
        'MapModule.Mapicon',
        'MapModule.Mapsummaryitem',
        'Host',
        'Service',
        'Hostgroup',
        'Servicegroup',
        MONITORING_HOSTSTATUS,
        MONITORING_SERVICESTATUS
    ];


    /**
     * @param int $id
     */
    ///map_module/mapeditors/view/20
    public function view($id = null) {
        if (!$this->isApiRequest() && $id === null) {
            //Only ship html template
            return;
        }

        $id = (int)$id;
        if (!$this->Map->exists($id)) {
            throw new NotFoundException();
        }
        $map = $this->Map->find('first', [
            'recursive'  => -1,
            'contain'    => [
                'Container',
                'Mapitem',
                'Mapline',
                'Mapgadget',
                'Mapicon',
                'Maptext',
                'Mapsummaryitem'
            ],
            'conditions' => [
                'Map.id' => $id
            ]
        ]);

        $containerIdsToCheck = Hash::extract($map, 'Container.{n}.MapsToContainer.container_id');
        if (!$this->allowedByContainerId($containerIdsToCheck, false)) {
            $this->render403();
            return;
        }

        $MapForAngular = new MapForAngular($map);
        $map = $MapForAngular->toArray();

        $acl = [
            'hosts'         => [
                'browser' => isset($this->PERMISSIONS['hosts']['browser']),
                'index'   => isset($this->PERMISSIONS['hosts']['index'])
            ],
            'services'      => [
                'browser' => isset($this->PERMISSIONS['services']['browser']),
                'index'   => isset($this->PERMISSIONS['services']['index'])

            ],
            'hostgroups'    => [
                'extended' => isset($this->PERMISSIONS['hostgroups']['extended'])
            ],
            'servicegroups' => [
                'extended' => isset($this->PERMISSIONS['servicegroups']['extended'])
            ]
        ];

        $this->set('map', $map);
        $this->set('ACL', $acl);

        $this->viewBuilder()->setOption('serialize', ['map', 'ACL']);
    }

    public function mapitem() {
        if (!$this->isApiRequest()) {
            return;
        }
        $objectId = (int)$this->request->getQuery('objectId');
        if ($objectId <= 0) {
            throw new RuntimeException('Invalid object id');
        }
        $mapId = (int)$this->request->getQuery('mapId');
        if ($mapId <= 0) {
            throw new RuntimeException('Invalid map id');
        }

        $data = $this->_mapitem(
            $objectId,
            $mapId,
            $this->request->getQuery('type'),
            $this->request->getQuery('includeServiceOutput')
        );

        $this->set('type', $this->request->getQuery('type'));
        $this->set('data', $data['data']);
        $this->set('allowView', $data['allowView']);
        $this->viewBuilder()->setOption('serialize', ['type', 'allowView', 'data']);
    }

    public function mapitemMulti() {
        if (!$this->isApiRequest()) {
            return;
        }

        if (!$this->request->is('post')) {
            throw new MethodNotAllowedException();
        }

        $items = $this->request->getData('items');

        $mapitems = [];
        foreach ($items as $item) {
            if (isset($item['mapId']) && isset($item['objectId']) && isset($item['type']) && isset($item['uuid'])) {

                try {
                    $data = $this->_mapitem(
                        $item['objectId'],
                        $item['mapId'],
                        $item['type']
                    );

                    $mapitems[$item['uuid']] = $data;

                } catch (\Exception $e) {
                    throw $e;
                }
            }

        }

        $this->set('mapitems', $mapitems);
        $this->viewBuilder()->setOption('serialize', ['mapitems']);

    }

    /**
     * @param $objectId
     * @param $mapId
     * @param $type
     * @param string $includeServiceOutput
     * @return array
     */
    private function _mapitem($objectId, $mapId, $type, $includeServiceOutput = 'true') {
        if ($objectId <= 0) {
            throw new RuntimeException('Invalid object id');
        }
        if ($mapId <= 0) {
            throw new RuntimeException('Invalid map id');
        }

        switch ($type) {
            case 'host':
                $host = $this->Host->find('first', [
                    'recursive'  => -1,
                    'contain'    => [
                        'Container'
                    ],
                    'fields'     => [
                        'Host.id',
                        'Host.uuid',
                        'Host.name',
                        'Host.disabled'
                    ],
                    'conditions' => [
                        'Host.id' => $objectId
                    ]
                ]);

                if (!empty($host)) {
                    if ($this->hasRootPrivileges === false) {
                        if (!$this->allowedByContainerId(Hash::extract($host, 'Container.{n}.HostsToContainer.container_id'), false)) {
                            $allowView = false;
                            break;
                        }
                    }
                    $allowView = true;
                    $properties = $this->Map->getHostInformation(
                        $this->Service,
                        $this->Hoststatus,
                        $this->Servicestatus,
                        $host
                    );
                    break;
                }
                $allowView = false;
                break;

            case 'service':
                $includeServiceOutput = $includeServiceOutput === 'true';
                $service = $this->Service->find('first', [
                    'recursive'  => -1,
                    'fields'     => [
                        'Service.id',
                        'Service.name',
                        'Service.uuid',
                        'Service.disabled'
                    ],
                    'contain'    => [
                        'Host'            => [
                            'fields' => [
                                'Host.id',
                                'Host.uuid',
                                'Host.name'
                            ],
                            'Container',
                        ],
                        'Servicetemplate' => [
                            'fields' => [
                                'Servicetemplate.name'
                            ]
                        ]
                    ],
                    'conditions' => [
                        'Service.id' => $objectId
                    ],
                ]);

                if (!empty($service)) {
                    if ($this->hasRootPrivileges === false) {
                        if (!$this->allowedByContainerId(Hash::extract($service, 'Host.Container.{n}.HostsToContainer.container_id'), false)) {
                            $allowView = false;
                            break;
                        }
                    }
                    $allowView = true;
                    $properties = $this->Map->getServiceInformation($this->Servicestatus, $service, $includeServiceOutput);
                    break;
                }
                $allowView = false;
                break;

            case 'hostgroup':
                try {
                    /** @var $HostgroupsTable HostgroupsTable */
                    $HostgroupsTable = TableRegistry::getTableLocator()->get('Hostgroups');

                    $hostgroup = $HostgroupsTable->getHostsByHostgroupForMaps($objectId, []);

                    if ($this->hasRootPrivileges === false) {
                        if (!$this->allowedByContainerId(Hash::extract($hostgroup, 'hosts.{n}.hosts_to_containers_sharing.{n}.id'), false)) {
                            $allowView = false;
                            break;
                        }
                    }
                    $allowView = true;
                    $properties = $this->Map->getHostgroupInformation(
                        $this->Service,
                        $hostgroup
                    );
                    break;
                } catch (\Exception $e) {
                    $allowView = false;
                }
                break;
            case 'servicegroup':
                $servicegroup = $this->Servicegroup->find('first', [
                    'recursive'  => -1,
                    'contain'    => [
                        'Container' => [
                            'fields' => [
                                'Container.name'
                            ]
                        ],
                        'Service'   => [
                            'Host'            => [
                                'Container',
                                'fields'     => [
                                    'Host.id',
                                    'Host.uuid'
                                ],
                                'conditions' => [
                                    'Host.disabled' => 0
                                ]
                            ],
                            'Servicetemplate' => [
                                'fields' => [
                                    'Servicetemplate.name'
                                ]
                            ],
                            'fields'          => [
                                'Service.id',
                                'Service.uuid',
                                'Service.name'
                            ],
                            'conditions'      => [
                                'Service.disabled' => 0
                            ]
                        ],

                    ],
                    'fields'     => [
                        'Servicegroup.id',
                        'Servicegroup.description'
                    ],
                    'conditions' => [
                        'Servicegroup.id' => $objectId
                    ]
                ]);

                if (!empty($servicegroup)) {
                    if ($this->hasRootPrivileges === false) {
                        if (!$this->allowedByContainerId(array_unique(Hash::extract($servicegroup, 'Service.{n}.Host.Container.{n}.HostsToContainer.container_id')), false)) {
                            $allowView = false;
                            break;
                        }
                    }
                    $allowView = true;
                    $properties = $this->Map->getServicegroupInformation(
                        $this->Service,
                        $this->Servicestatus,
                        $servicegroup
                    );
                    break;
                }
                $allowView = false;
                break;
            case 'map':
                $map = $this->Map->find('first', [
                    'recursive'  => -1,
                    'contain'    => [
                        'Container'
                    ],
                    'joins'      => [
                        [
                            'table'      => 'mapitems',
                            'type'       => 'INNER',
                            'alias'      => 'Mapitem',
                            'conditions' => 'Mapitem.object_id = Map.id',
                        ],
                    ],
                    'conditions' => [
                        'Map.id'         => $objectId,
                        'Mapitem.map_id' => $mapId
                    ]
                ]);
                if (!empty($map)) {
                    if ($this->hasRootPrivileges === false) {
                        if (!$this->allowedByContainerId(Hash::extract($map, 'Container.{n}.MapsToContainer.container_id'), false)) {
                            $allowView = false;
                            break;
                        }
                    }
                    //fetch all dependent map items after permissions check
                    $mapItemToResolve = $this->Mapitem->find('first', [
                        'recursive'  => -1,
                        'conditions' => [
                            'Mapitem.object_id' => $map['Map']['id'],
                            'Mapitem.type'      => 'map',
                            'Mapitem.map_id'    => $mapId
                        ],
                        'fields'     => [
                            'Mapitem.object_id'
                        ]
                    ]);
                    if (!empty($mapItemToResolve)) {
                        $query = [
                            'recursive'  => -1,
                            'joins'      => [
                                [
                                    'table'      => 'maps_to_containers',
                                    'type'       => 'INNER',
                                    'alias'      => 'MapsToContainers',
                                    'conditions' => 'MapsToContainers.map_id = Map.id',
                                ],
                            ],
                            'contain'    => [
                                'Map'
                            ],
                            'conditions' => [
                                'Mapitem.type' => 'map',
                                'NOT'          => [
                                    'Mapitem.map_id' => $map['Map']['id']
                                ]
                            ],
                            'fields'     => [
                                'Mapitem.map_id',
                                'Mapitem.object_id'
                            ]
                        ];
                        if (!$this->hasRootPrivileges) {
                            $query['conditions']['MapsToContainers.container_id'] = $this->MY_RIGHTS;
                        }
                        $allVisibleItems = $this->Mapitem->find('all', $query);


                        $mapItemIdToResolve = $mapItemToResolve['Mapitem']['object_id'];
                        $mapIdGroupByMapId = Hash::combine(
                            $allVisibleItems,
                            '{n}.Mapitem.object_id',
                            '{n}.Mapitem.object_id',
                            '{n}.Mapitem.map_id'
                        );

                        if (isset($mapIdGroupByMapId[$mapItemIdToResolve])) {
                            $dependentMapsIds = $this->getDependendMaps($mapIdGroupByMapId, $mapItemIdToResolve);
                        }
                        $dependentMapsIds[] = $mapItemIdToResolve;
                        // resolve all Elements (host and/or services of dependent map)
                        $allDependentMapElements = $this->Map->getAllDependentMapsElements(
                            $this->Map,
                            $dependentMapsIds,
                            $this->Hostgroup,
                            $this->Servicegroup
                        );

                        $hosts = [];
                        $services = [];
                        if (!empty($allDependentMapElements['hostIds'])) {
                            $hosts = $this->Host->find('all', [
                                'recursive'  => -1,
                                'contain'    => [
                                    'Container',
                                    'Service' => [
                                        'conditions' => [
                                            'Service.disabled' => 0
                                        ],
                                        'fields'     => [
                                            'Service.id',
                                            'Service.uuid'
                                        ]
                                    ]
                                ],
                                'conditions' => [
                                    'Host.id'       => $allDependentMapElements['hostIds'],
                                    'Host.disabled' => 0
                                ],
                                'fields'     => [
                                    'Host.uuid'
                                ]
                            ]);
                            if (!empty($hosts)) {
                                if ($this->hasRootPrivileges === false) {
                                    if (!$this->allowedByContainerId(Hash::extract($hosts, '{n}.Container.{n}.HostsToContainer.container_id'), false)) {
                                        $allowView = false;
                                        break;
                                    }
                                }
                                foreach ($hosts as $host) {
                                    foreach ($host['Service'] as $serviceData) {
                                        $services[$serviceData['id']] = [
                                            'Service' => $serviceData
                                        ];
                                    }
                                }
                            }
                        }
                        if (!empty($allDependentMapElements['serviceIds'])) {
                            $dependentServices = $this->Service->find('all', [
                                'recursive'  => -1,
                                'contain'    => [
                                    'Host' => [
                                        'Container',
                                        'fields' => [
                                            'Host.id',
                                            'Host.uuid'
                                        ]
                                    ]
                                ],
                                'conditions' => [
                                    'Service.id'       => $allDependentMapElements['serviceIds'],
                                    'Service.disabled' => 0
                                ],
                                'fields'     => [
                                    'Service.id',
                                    'Service.uuid'
                                ]
                            ]);
                            if (!empty($dependentServices)) {
                                if ($this->hasRootPrivileges === false) {
                                    if (!$this->allowedByContainerId(Hash::extract($dependentServices, '{n}.Host.Container.{n}.HostsToContainer.container_id'), false)) {
                                        $allowView = false;
                                        break;
                                    }
                                }
                                foreach ($dependentServices as $service) {
                                    $hosts[$service['Host']['id']] = ['Host' => $service['Host']];
                                    $services[$service['Service']['id']] = $service;
                                }
                            }
                        }
                        $allowView = true;
                        $properties = $this->Map->getMapInformation(
                            $this->Hoststatus,
                            $this->Servicestatus,
                            $map,
                            $hosts,
                            $services
                        );
                    }
                    break;
                }
                $allowView = false;
                break;
            default:
                throw new RuntimeException('Unknown map item type');
                break;
        }

        return [
            'type'      => $type,
            'data'      => $properties,
            'allowView' => $allowView
        ];
    }

    public function getDependendMaps($maps, $parentMapId) {
        $allRelatedMapIdsOfParent = [];

        $childMapIds = $maps[$parentMapId];
        foreach ($childMapIds as $childMapId) {
            $allRelatedMapIdsOfParent[] = $childMapId;
            //Is the children map used as parent map in an other relation?
            if (isset($maps[$childMapId])) {
                //Rec
                $allRelatedMapIdsOfParent = array_merge(
                    $allRelatedMapIdsOfParent,
                    $this->getDependendMaps($maps, $childMapId)
                );
            }
        }

        return $allRelatedMapIdsOfParent;

    }

    public function mapline() {
        //Only ship template
        return;
    }

    public function mapicon() {
        //Only ship template
        return;
    }

    public function maptext() {
        //Only ship template
        return;
    }

    public function perfdatatext() {
        //Only ship template
        return;
    }

    public function serviceOutput() {
        //Only ship template
        return;
    }

    public function mapsummaryitem() {
        if (!$this->isApiRequest()) {
            return;
        }
        $properties = [];
        $objectId = (int)$this->request->getQuery('objectId');
        if ($objectId <= 0) {
            throw new RuntimeException('Invalid object id');
        }
        $mapId = (int)$this->request->getQuery('mapId');
        if ($mapId <= 0) {
            throw new RuntimeException('Invalid map id');
        }


        switch ($this->request->getQuery('type')) {
            case 'host':
                $host = $this->Host->find('first', [
                    'recursive'  => -1,
                    'contain'    => [
                        'Container',
                        'Service' => [
                            'fields'     => [
                                'Service.uuid'
                            ],
                            'conditions' => [
                                'Service.disabled' => 0
                            ]
                        ]
                    ],
                    'fields'     => [
                        'Host.id',
                        'Host.name',
                        'Host.uuid',
                        'Host.disabled'
                    ],
                    'conditions' => [
                        'Host.id' => $objectId
                    ]
                ]);
                if (!empty($host)) {
                    if ($this->hasRootPrivileges === false) {
                        if (!$this->allowedByContainerId(Hash::extract($host, 'Container.{n}.HostsToContainer.container_id'), false)) {
                            $allowView = false;
                            break;
                        }
                    }
                    $allowView = true;
                    $properties = $this->Map->getHostInformationForSummaryIcon(
                        $this->Hoststatus,
                        $this->Servicestatus,
                        $host
                    );
                    break;
                }
                $allowView = false;
                break;

            case 'service':
                $service = $this->Service->find('first', [
                    'recursive'  => -1,
                    'fields'     => [
                        'Service.id',
                        'Service.name',
                        'Service.uuid',
                        'Service.disabled'
                    ],
                    'contain'    => [
                        'Host'            => [
                            'fields' => [
                                'Host.id',
                                'Host.uuid',
                                'Host.name'
                            ],
                            'Container',
                        ],
                        'Servicetemplate' => [
                            'fields' => [
                                'Servicetemplate.name'
                            ]
                        ]
                    ],
                    'conditions' => [
                        'Service.id' => $objectId
                    ],
                ]);

                if (!empty($service)) {
                    if ($this->hasRootPrivileges === false) {
                        if (!$this->allowedByContainerId(Hash::extract($service, 'Host.Container.{n}.HostsToContainer.container_id'), false)) {
                            $allowView = false;
                            break;
                        }
                    }
                    $allowView = true;
                    $properties = $this->Map->getServiceInformationForSummaryIcon(
                        $this->Hoststatus,
                        $this->Servicestatus,
                        $service
                    );
                    break;
                }
                $allowView = false;
                break;

            case 'hostgroup':
                $hostgroup = $this->Hostgroup->find('first', [
                    'recursive'  => -1,
                    'contain'    => [
                        'Container' => [
                            'fields' => [
                                'Container.name'
                            ]
                        ],
                        'Host'      => [
                            'Container',
                            'fields'     => [
                                'Host.id',
                                'Host.uuid'
                            ],
                            'conditions' => [
                                'Host.disabled' => 0
                            ],
                            'Service'    => [
                                'fields'     => [
                                    'Service.id',
                                    'Service.uuid'
                                ],
                                'conditions' => [
                                    'Service.disabled' => 0
                                ],
                            ]
                        ]
                    ],
                    'fields'     => [
                        'Hostgroup.id',
                        'Hostgroup.description'
                    ],
                    'conditions' => [
                        'Hostgroup.id' => $objectId
                    ]
                ]);
                if (!empty($hostgroup)) {
                    if ($this->hasRootPrivileges === false) {
                        if (!$this->allowedByContainerId(Hash::extract($hostgroup, 'Host.{n}.Container.{n}.HostsToContainer.container_id'), false)) {
                            $allowView = false;
                            break;
                        }
                    }
                    $allowView = true;
                    $properties = $this->Map->getHostgroupInformationForSummaryIcon(
                        $this->Hoststatus,
                        $this->Servicestatus,
                        $hostgroup
                    );
                    break;
                }
                $allowView = false;
                break;

            case 'servicegroup':
                $servicegroup = $this->Servicegroup->find('first', [
                    'recursive'  => -1,
                    'contain'    => [
                        'Container' => [
                            'fields' => [
                                'Container.name'
                            ]
                        ],
                        'Service'   => [
                            'Host'            => [
                                'Container',
                                'fields'     => [
                                    'Host.id',
                                    'Host.uuid'
                                ],
                                'conditions' => [
                                    'Host.disabled' => 0
                                ]
                            ],
                            'Servicetemplate' => [
                                'fields' => [
                                    'Servicetemplate.name'
                                ]
                            ],
                            'fields'          => [
                                'Service.id',
                                'Service.uuid',
                                'Service.name'
                            ],
                            'conditions'      => [
                                'Service.disabled' => 0
                            ]
                        ],

                    ],
                    'fields'     => [
                        'Servicegroup.id',
                        'Servicegroup.description'
                    ],
                    'conditions' => [
                        'Servicegroup.id' => $objectId
                    ]
                ]);

                if (!empty($servicegroup)) {
                    if ($this->hasRootPrivileges === false) {
                        if (!$this->allowedByContainerId(Hash::extract($servicegroup, 'Host.{n}.Container.{n}.HostsToContainer.container_id'), false)) {
                            $allowView = false;
                            break;
                        }
                    }
                    $allowView = true;
                    $properties = $this->Map->getServicegroupInformationForSummaryIcon(
                        $this->Hoststatus,
                        $this->Servicestatus,
                        $servicegroup
                    );
                    break;
                }
                $allowView = false;
                break;
            case 'map':
                $map = $this->Map->find('first', [
                    'recursive'  => -1,
                    'contain'    => [
                        'Container'
                    ],
                    'joins'      => [
                        [
                            'table'      => 'mapsummaryitems',
                            'type'       => 'INNER',
                            'alias'      => 'Mapsummaryitem',
                            'conditions' => 'Mapsummaryitem.object_id = Map.id',
                        ],
                    ],
                    'conditions' => [
                        'Map.id'                => $objectId,
                        'Mapsummaryitem.map_id' => $mapId
                    ]
                ]);
                if (!empty($map)) {
                    if ($this->hasRootPrivileges === false) {
                        if (!$this->allowedByContainerId(Hash::extract($map, 'Container.{n}.MapsToContainer.container_id'), false)) {
                            $allowView = false;
                            break;
                        }
                    }
                    //fetch all dependent map items after permissions check
                    $mapSummaryItemIdToResolve = $this->Mapsummaryitem->find('first', [
                        'recursive'  => -1,
                        'conditions' => [
                            'Mapsummaryitem.object_id' => $map['Map']['id'],
                            'Mapsummaryitem.type'      => 'map',
                            'Mapsummaryitem.map_id'    => $mapId
                        ],
                        'fields'     => [
                            'Mapsummaryitem.object_id'
                        ]
                    ]);
                    if (!empty($mapSummaryItemIdToResolve)) {
                        $query = [
                            'recursive'  => -1,
                            'joins'      => [
                                [
                                    'table'      => 'maps_to_containers',
                                    'type'       => 'INNER',
                                    'alias'      => 'MapsToContainers',
                                    'conditions' => 'MapsToContainers.map_id = Map.id',
                                ],
                            ],
                            'contain'    => [
                                'Map'
                            ],
                            'conditions' => [
                                'Mapsummaryitem.type' => 'map',
                                'NOT'                 => [
                                    'Mapsummaryitem.map_id' => $mapId
                                ]
                            ],
                            'fields'     => [
                                'Mapsummaryitem.map_id',
                                'Mapsummaryitem.object_id'
                            ]
                        ];
                        if (!$this->hasRootPrivileges) {
                            $query['conditions']['MapsToContainers.container_id'] = $this->MY_RIGHTS;
                        }
                        $allVisibleItems = $this->Mapsummaryitem->find('all', $query);

                        $mapSummaryItemIdToResolve = $mapSummaryItemIdToResolve['Mapsummaryitem']['object_id'];
                        $mapIdGroupByMapId = Hash::combine(
                            $allVisibleItems,
                            '{n}.Mapsummaryitem.object_id',
                            '{n}.Mapsummaryitem.object_id',
                            '{n}.Mapsummaryitem.map_id'
                        );
                        if (isset($mapIdGroupByMapId[$mapSummaryItemIdToResolve])) {
                            $dependentMapsIds = $this->getDependendMaps($mapIdGroupByMapId, $mapSummaryItemIdToResolve);
                        }
                        $dependentMapsIds[] = $mapSummaryItemIdToResolve;

                        // resolve all Elements (host and/or services of dependent map)
                        $allDependentMapElementsFromSubMaps['mapsummaryitem'] = $this->Map->getAllDependentMapsElements(
                            $this->Map,
                            $dependentMapsIds,
                            $this->Hostgroup,
                            $this->Servicegroup
                        );
                    }

                    //fetch all dependent map items after permissions check SIMPLE STATE ITEMS (TYPE MAP)
                    $mapItemToResolve = $this->Mapitem->find('first', [
                        'recursive'  => -1,
                        'conditions' => [
                            'Mapitem.map_id' => $map['Map']['id'],
                            'Mapitem.type'   => 'map'
                        ],
                        'fields'     => [
                            'Mapitem.object_id'
                        ]
                    ]);

                    if (!empty($mapItemToResolve)) {
                        $query = [
                            'recursive'  => -1,
                            'joins'      => [
                                [
                                    'table'      => 'maps_to_containers',
                                    'type'       => 'INNER',
                                    'alias'      => 'MapsToContainers',
                                    'conditions' => 'MapsToContainers.map_id = Map.id',
                                ],
                            ],
                            'contain'    => [
                                'Map'
                            ],
                            'conditions' => [
                                'Mapitem.type' => 'map',
                                'NOT'          => [
                                    'Mapitem.map_id' => $map['Map']['id']
                                ]
                            ],
                            'fields'     => [
                                'Mapitem.map_id',
                                'Mapitem.object_id'
                            ]
                        ];
                        if (!$this->hasRootPrivileges) {
                            $query['conditions']['MapsToContainers.container_id'] = $this->MY_RIGHTS;
                        }
                        $allVisibleItems = $this->Mapitem->find('all', $query);
                        $mapItemIdToResolve = $mapItemToResolve['Mapitem']['object_id'];
                        $mapIdGroupByMapId = Hash::combine(
                            $allVisibleItems,
                            '{n}.Mapitem.object_id',
                            '{n}.Mapitem.object_id',
                            '{n}.Mapitem.map_id'
                        );
                        if (isset($mapIdGroupByMapId[$mapItemIdToResolve])) {
                            $dependentMapsIds = $this->getDependendMaps($mapIdGroupByMapId, $mapItemIdToResolve);
                        }
                        $dependentMapsIds[] = $mapItemIdToResolve;
                        // resolve all Elements (host and/or services of dependent map)
                        $allDependentMapElementsFromSubMaps['mapitem'] = $this->Map->getAllDependentMapsElements(
                            $this->Map,
                            $dependentMapsIds,
                            $this->Hostgroup,
                            $this->Servicegroup
                        );
                    }

                    //simple item (host/hostgroup/service/servicegroup)
                    $allDependentMapElementsFromSubMaps['item'] = $this->Map->getAllDependentMapsElements(
                        $this->Map,
                        [$map['Map']['id']],
                        $this->Hostgroup,
                        $this->Servicegroup
                    );


                    $hostIds = Hash::extract($allDependentMapElementsFromSubMaps, '{s}.hostIds.{n}');
                    $serviceIds = Hash::extract($allDependentMapElementsFromSubMaps, '{s}.serviceIds.{n}');

                    $hosts = [];
                    $services = [];
                    if (!empty($hostIds)) {
                        $hostsById = $this->Host->find('all', [
                            'recursive'  => -1,
                            'contain'    => [
                                'Container',
                                'Service' => [
                                    'conditions' => [
                                        'Service.disabled' => 0
                                    ],
                                    'fields'     => [
                                        'Service.id',
                                        'Service.uuid'
                                    ]
                                ]
                            ],
                            'conditions' => [
                                'Host.id'       => $hostIds,
                                'Host.disabled' => 0
                            ],
                            'fields'     => [
                                'Host.uuid'
                            ]
                        ]);
                        if (!empty($hostsById)) {
                            if ($this->hasRootPrivileges === false) {
                                if (!$this->allowedByContainerId(Hash::extract($hostsById, '{n}.Container.{n}.HostsToContainer.container_id'), false)) {
                                    $allowView = false;
                                    break;
                                }
                            }
                            foreach ($hostsById as $host) {
                                $hosts[$host['Host']['id']] = $host;
                                foreach ($host['Service'] as $serviceData) {
                                    $services[$serviceData['id']] = [
                                        'Service' => $serviceData
                                    ];
                                }
                            }
                        }
                    }
                    if (!empty($serviceIds)) {
                        $dependentServices = $this->Service->find('all', [
                            'recursive'  => -1,
                            'contain'    => [
                                'Host' => [
                                    'Container',
                                    'fields' => [
                                        'Host.uuid'
                                    ]
                                ]
                            ],
                            'conditions' => [
                                'Service.id'       => $serviceIds,
                                'Service.disabled' => 0
                            ],
                            'fields'     => [
                                'Service.id',
                                'Service.uuid'
                            ]
                        ]);
                        if (!empty($dependentServices)) {
                            if ($this->hasRootPrivileges === false) {
                                if (!$this->allowedByContainerId(Hash::extract($dependentServices, '{n}.Host.Container.{n}.HostsToContainer.container_id'), false)) {
                                    $allowView = false;
                                    break;
                                }
                            }
                            foreach ($dependentServices as $service) {
                                $hosts[$service['Host']['id']] = ['Host' => $service['Host']];
                                $services[$service['Service']['id']] = $service;
                            }
                        }
                    }
                    $allowView = true;
                    $properties = $this->Map->getMapInformationForSummaryIcon(
                        $this->Hoststatus,
                        $this->Servicestatus,
                        $map,
                        $hosts,
                        $services
                    );
                    break;
                }
                $allowView = false;
                break;
            default:
                throw new RuntimeException('Unknown map item type');
                break;
        }

        $this->set('type', $this->request->getQuery('type'));
        $this->set('data', $properties);
        $this->set('allowView', $allowView);
        $this->viewBuilder()->setOption('serialize', ['type', 'allowView', 'data']);
    }

    public function graph() {
        if (!$this->isApiRequest()) {
            return;
        }

        $serviceId = (int)$this->request->getQuery('serviceId');
        $service = $this->Service->find('first', [
            'recursive'  => -1,
            'fields'     => [
                'Service.id',
                'Service.uuid',
                'Service.name'
            ],
            'contain'    => [
                'Host'            => [
                    'fields' => [
                        'Host.id',
                        'Host.uuid',
                        'Host.name'
                    ],
                    'Container',
                ],
                'Servicetemplate' => [
                    'fields' => [
                        'Servicetemplate.name'
                    ]
                ]
            ],
            'conditions' => [
                'Service.id' => $serviceId
            ],
        ]);
        if (empty($service)) {
            throw new NotFoundException('Service not found!');
        }

        if ($this->hasRootPrivileges === false) {
            if (!$this->allowedByContainerId(Hash::extract($service, 'Host.Container.{n}.HostsToContainer.container_id'), false)) {
                $this->set('allowView', false);
                $this->viewBuilder()->setOption('serialize', ['allowView']);
            }
        }

        $Service = new \itnovum\openITCOCKPIT\Core\Views\Service($service);
        $Host = new \itnovum\openITCOCKPIT\Core\Views\Host($service);
        $this->set('host', $Host->toArray());
        $this->set('service', $Service->toArray());
        $this->set('allowView', true);
        $this->viewBuilder()->setOption('serialize', ['allowView', 'host', 'service']);
    }

    public function tacho() {
        //Only ship template
        return;
    }

    public function cylinder() {
        //Only ship template
        return;
    }

    public function trafficlight() {
        //Only ship template
        return;
    }

    public function temperature() {
        //Only ship template
        return;
    }

    public function mapsummary() {
        if (!$this->isApiRequest()) {
            return;
        }

        $objectId = (int)$this->request->getQuery('objectId');
        $summaryStateItem = $this->request->getQuery('summary') === 'true';
        if ($objectId <= 0) {
            throw new RuntimeException('Invalid object id');
        }
        $UserTime = new UserTime($this->Auth->user('timezone'), $this->Auth->user('dateformat'));

        switch ($this->request->getQuery('type')) {
            case 'host':
                $host = $this->Host->find('first', [
                    'recursive'  => -1,
                    'contain'    => [
                        'Container'
                    ],
                    'fields'     => [
                        'Host.id',
                        'Host.uuid',
                        'Host.name',
                        'Host.description',
                        'Host.disabled'
                    ],
                    'conditions' => [
                        'Host.id' => $objectId
                    ]
                ]);
                if (!empty($host)) {
                    if ($this->hasRootPrivileges === false) {
                        if (!$this->allowedByContainerId(Hash::extract($host, 'Container.{n}.HostsToContainer.container_id'), false)) {
                            $this->render403();
                            return;
                        }
                    }
                    $summary = $this->Map->getHostSummary(
                        $this->Service,
                        $this->Hoststatus,
                        $this->Servicestatus,
                        $host,
                        $UserTime
                    );
                    $this->set('type', 'host');
                    $this->set('summary', $summary);
                    $this->viewBuilder()->setOption('serialize', ['host', 'summary']);
                    return;
                }

                throw new NotFoundException('Host not found!');
                return;
                break;
            case 'service':
                $service = $this->Service->find('first', [
                    'recursive'  => -1,
                    'fields'     => [
                        'Service.id',
                        'Service.name',
                        'Service.uuid',
                        'Service.description',
                        'Service.disabled'
                    ],
                    'contain'    => [
                        'Host'            => [
                            'fields' => [
                                'Host.id',
                                'Host.uuid',
                                'Host.name'
                            ],
                            'Container',
                        ],
                        'Servicetemplate' => [
                            'fields' => [
                                'Servicetemplate.name'
                            ]
                        ]
                    ],
                    'conditions' => [
                        'Service.id' => $objectId
                    ],
                ]);

                if (!empty($service)) {
                    if ($this->hasRootPrivileges === false) {
                        if (!$this->allowedByContainerId(Hash::extract($service, 'Host.Container.{n}.HostsToContainer.container_id'), false)) {
                            $this->render403();
                            return;
                        }
                    }
                    $summary = $this->Map->getServiceSummary(
                        $this->Service,
                        $this->Hoststatus,
                        $this->Servicestatus,
                        $service,
                        $UserTime
                    );
                    $this->set('type', 'service');
                    $this->set('summary', $summary);
                    $this->viewBuilder()->setOption('serialize', ['service', 'summary']);
                    return;

                }
                throw new NotFoundException('Service not found!');
                return;

                break;
            case 'hostgroup':
                try {
                    $MY_RIGHTS = [];
                    if (!$this->hasRootPrivileges) {
                        $MY_RIGHTS = $this->MY_RIGHTS;
                    }
                    /** @var $HostgroupsTable HostgroupsTable */
                    $HostgroupsTable = TableRegistry::getTableLocator()->get('Hostgroups');
                    $hostgroup = $HostgroupsTable->getHostsByHostgroupForMaps($objectId, $MY_RIGHTS);
                    if ($this->hasRootPrivileges === false) {
                        if (!$this->allowedByContainerId(
                            array_unique(\Cake\Utility\Hash::extract($hostgroup, 'hosts.{n}.hosts_to_containers_sharing.id'), false))) {
                            $this->render403();
                            return;
                        }
                    }
                    $summary = $this->Map->getHostgroupSummary(
                        $this->Host,
                        $this->Service,
                        $hostgroup
                    );
                    $this->set('type', 'hostgroup');
                    $this->set('summary', $summary);
                    $this->viewBuilder()->setOption('serialize', ['hostgroup', 'summary']);
                    return;
                } catch (\Exception $e) {
                    throw new NotFoundException('Host group not found!');
                }
                break;
            case 'servicegroup':
                $query = [
                    'recursive'  => -1,
                    'contain'    => [
                        'Container' => [
                            'fields' => [
                                'Container.name'
                            ]
                        ],
                        'Service'   => [
                            'Host'            => [
                                'Container',
                                'conditions' => [
                                    'Host.disabled' => 0
                                ]
                            ],
                            'Servicetemplate' => [
                                'fields' => [
                                    'Servicetemplate.id',
                                    'Servicetemplate.name'
                                ]
                            ],
                            'conditions'      => [
                                'Service.disabled' => 0
                            ],
                            'fields'          => [
                                'Service.id',
                                'Service.uuid',
                                'Service.name'
                            ]
                        ]
                    ],
                    'fields'     => [
                        'Servicegroup.description'
                    ],
                    'conditions' => [
                        'Servicegroup.id' => $objectId
                    ]
                ];
                if (!$this->hasRootPrivileges) {
                    $query['conditions']['Container.parent_id'] = $this->MY_RIGHTS;
                }
                $servicegroup = $this->Servicegroup->find('first', $query);

                if (!empty($servicegroup)) {
                    if ($this->hasRootPrivileges === false) {
                        if (!$this->allowedByContainerId(array_unique(Hash::extract($servicegroup, 'Service.{n}.Host.Container.{n}.HostsToContainer.container_id')), false)) {
                            $this->render403();
                            return;
                        }
                    }

                    $summary = $this->Map->getServicegroupSummary(
                        $this->Service,
                        $this->Servicestatus,
                        $servicegroup
                    );
                    $this->set('type', 'servicegroup');
                    $this->set('summary', $summary);
                    $this->viewBuilder()->setOption('serialize', ['servicegroup', 'summary']);
                    return;
                }

                throw new NotFoundException('Service group not found!');
                return;
                break;
            case 'map':
                if ($summaryStateItem) {
                    $map = $this->Map->find('first', [
                        'recursive'  => -1,
                        'contain'    => [
                            'Container'
                        ],
                        'joins'      => [
                            [
                                'table'      => 'mapsummaryitems',
                                'type'       => 'INNER',
                                'alias'      => 'Mapsummaryitem',
                                'conditions' => 'Mapsummaryitem.map_id = Map.id',
                            ],
                        ],
                        'conditions' => [
                            'Mapsummaryitem.object_id' => $objectId
                        ],
                        'fields'     => [
                            'Map.*',
                            'Mapsummaryitem.object_id'
                        ]
                    ]);
                } else {
                    $map = $this->Map->find('first', [
                        'recursive'  => -1,
                        'contain'    => [
                            'Container'
                        ],
                        'joins'      => [
                            [
                                'table'      => 'mapitems',
                                'type'       => 'INNER',
                                'alias'      => 'Mapitem',
                                'conditions' => 'Mapitem.object_id = Map.id',
                            ],
                        ],
                        'conditions' => [
                            'Mapitem.object_id' => $objectId
                        ],
                        'fields'     => [
                            'Map.*',
                            'Mapitem.object_id'
                        ]
                    ]);
                }

                if (!empty($map)) {
                    if ($this->hasRootPrivileges === false) {
                        if (!$this->allowedByContainerId(Hash::extract($map, 'Container.{n}.MapsToContainer.container_id'), false)) {
                            $allowView = false;
                            break;
                        }
                    }
                    //fetch all dependent map items after permissions check
                    $mapSummaryItemIdToResolve = $this->Mapsummaryitem->find('first', [
                        'recursive'  => -1,
                        'conditions' => [
                            'Mapsummaryitem.object_id' => $objectId,
                            'Mapsummaryitem.type'      => 'map',
                            'Mapsummaryitem.map_id'    => $map['Map']['id']
                        ],
                        'fields'     => [
                            'Mapsummaryitem.object_id'
                        ]
                    ]);
                    if (!empty($mapSummaryItemIdToResolve)) {
                        $query = [
                            'recursive'  => -1,
                            'joins'      => [
                                [
                                    'table'      => 'maps_to_containers',
                                    'type'       => 'INNER',
                                    'alias'      => 'MapsToContainers',
                                    'conditions' => 'MapsToContainers.map_id = Map.id',
                                ],
                            ],
                            'contain'    => [
                                'Map'
                            ],
                            'conditions' => [
                                'Mapsummaryitem.type' => 'map',
                                'NOT'                 => [
                                    'Mapsummaryitem.map_id' => $map['Map']['id']
                                ]
                            ],
                            'fields'     => [
                                'Mapsummaryitem.map_id',
                                'Mapsummaryitem.object_id'
                            ]
                        ];
                        if (!$this->hasRootPrivileges) {
                            $query['conditions']['MapsToContainers.container_id'] = $this->MY_RIGHTS;
                        }
                        $allVisibleItems = $this->Mapsummaryitem->find('all', $query);

                        $mapSummaryItemIdToResolve = $mapSummaryItemIdToResolve['Mapsummaryitem']['object_id'];
                        $mapIdGroupByMapId = Hash::combine(
                            $allVisibleItems,
                            '{n}.Mapsummaryitem.object_id',
                            '{n}.Mapsummaryitem.object_id',
                            '{n}.Mapsummaryitem.map_id'
                        );
                        if (isset($mapIdGroupByMapId[$mapSummaryItemIdToResolve])) {
                            $dependentMapsIds = $this->getDependendMaps($mapIdGroupByMapId, $mapSummaryItemIdToResolve);
                        }
                        $dependentMapsIds[] = $mapSummaryItemIdToResolve;

                        // resolve all Elements (host and/or services of dependent map)
                        $allDependentMapElementsFromSubMaps['mapsummaryitem'] = $this->Map->getAllDependentMapsElements(
                            $this->Map,
                            $dependentMapsIds,
                            $this->Hostgroup,
                            $this->Servicegroup
                        );
                    }

                    //fetch all dependent map items after permissions check SIMPLE STATE ITEMS (TYPE MAP)
                    $mapItemToResolve = $this->Mapitem->find('first', [
                        'recursive'  => -1,
                        'conditions' => [
                            'Mapitem.map_id' => $map['Map']['id'],
                            'Mapitem.type'   => 'map'
                        ],
                        'fields'     => [
                            'Mapitem.object_id'
                        ]
                    ]);

                    if (!empty($mapItemToResolve)) {
                        $query = [
                            'recursive'  => -1,
                            'joins'      => [
                                [
                                    'table'      => 'maps_to_containers',
                                    'type'       => 'INNER',
                                    'alias'      => 'MapsToContainers',
                                    'conditions' => 'MapsToContainers.map_id = Map.id',
                                ],
                            ],
                            'contain'    => [
                                'Map'
                            ],
                            'conditions' => [
                                'Mapitem.type' => 'map',
                                'NOT'          => [
                                    'Mapitem.map_id' => $map['Map']['id']
                                ]
                            ],
                            'fields'     => [
                                'Mapitem.map_id',
                                'Mapitem.object_id'
                            ]
                        ];
                        if (!$this->hasRootPrivileges) {
                            $query['conditions']['MapsToContainers.container_id'] = $this->MY_RIGHTS;
                        }
                        $allVisibleItems = $this->Mapitem->find('all', $query);
                        $mapItemIdToResolve = $mapItemToResolve['Mapitem']['object_id'];
                        $mapIdGroupByMapId = Hash::combine(
                            $allVisibleItems,
                            '{n}.Mapitem.object_id',
                            '{n}.Mapitem.object_id',
                            '{n}.Mapitem.map_id'
                        );
                        if (isset($mapIdGroupByMapId[$mapItemIdToResolve])) {
                            $dependentMapsIds = $this->getDependendMaps($mapIdGroupByMapId, $mapItemIdToResolve);
                        }
                        $dependentMapsIds[] = $mapItemIdToResolve;
                        // resolve all Elements (host and/or services of dependent map)
                        $allDependentMapElementsFromSubMaps['mapitem'] = $this->Map->getAllDependentMapsElements(
                            $this->Map,
                            $dependentMapsIds,
                            $this->Hostgroup,
                            $this->Servicegroup
                        );
                    }

                    //simple item (host/hostgroup/service/servicegroup)
                    $allDependentMapElementsFromSubMaps['item'] = $this->Map->getAllDependentMapsElements(
                        $this->Map,
                        [$map['Map']['id']],
                        $this->Hostgroup,
                        $this->Servicegroup
                    );


                    $hostIds = Hash::extract($allDependentMapElementsFromSubMaps, '{s}.hostIds.{n}');
                    $serviceIds = Hash::extract($allDependentMapElementsFromSubMaps, '{s}.serviceIds.{n}');

                    $hosts = [];
                    $services = [];
                    if (!empty($hostIds)) {
                        $hosts = $this->Host->find('all', [
                            'recursive'  => -1,
                            'contain'    => [
                                'Container',
                                'Service' => [
                                    'conditions'      => [
                                        'Service.disabled' => 0
                                    ],
                                    'fields'          => [
                                        'Service.uuid',
                                        'Service.id',
                                        'Service.name'
                                    ],
                                    'Servicetemplate' => [
                                        'fields' => [
                                            'Servicetemplate.name'
                                        ]
                                    ]
                                ]
                            ],
                            'conditions' => [
                                'Host.id'       => $hostIds,
                                'Host.disabled' => 0
                            ],
                            'fields'     => [
                                'Host.id',
                                'Host.uuid',
                                'Host.name'
                            ]
                        ]);
                        if (!empty($hosts)) {
                            if ($this->hasRootPrivileges === false) {
                                if (!$this->allowedByContainerId(Hash::extract($hosts, '{n}.Container.{n}.HostsToContainer.container_id'), false)) {
                                    break;
                                }
                            }
                            foreach ($hosts as $host) {
                                foreach ($host['Service'] as $serviceData) {
                                    $services[$serviceData['id']] = [
                                        'Service' => $serviceData,
                                        'Host'    => [
                                            'name' => $host['Host']['name']
                                        ]
                                    ];
                                }
                            }
                        }
                    }

                    if (!empty($serviceIds)) {
                        $servicesToMerge = $this->Service->find('all', [
                            'recursive'  => -1,
                            'contain'    => [
                                'Host'            => [
                                    'Container',
                                    'fields' => [
                                        'Host.id',
                                        'Host.uuid',
                                        'Host.name'
                                    ]
                                ],
                                'Servicetemplate' => [
                                    'fields' => [
                                        'Servicetemplate.name'
                                    ]
                                ]
                            ],
                            'conditions' => [
                                'Service.id'       => $serviceIds,
                                'Service.disabled' => 0
                            ],
                            'fields'     => [
                                'Service.id',
                                'Service.uuid',
                                'Service.name'
                            ]
                        ]);
                        if (!empty($servicesToMerge)) {
                            if ($this->hasRootPrivileges === false) {
                                if (!$this->allowedByContainerId(Hash::extract($servicesToMerge, '{n}.Host.Container.{n}.HostsToContainer.container_id'), false)) {
                                    break;
                                }
                            }
                            foreach ($servicesToMerge as $service) {
                                $hosts[$service['Host']['id']] = ['Host' => $service['Host']];
                            }
                        }
                    }

                    $summary = $this->Map->getMapSummary(
                        $this->Host,
                        $this->Hoststatus,
                        $this->Service,
                        $this->Servicestatus,
                        $map,
                        $hosts,
                        $services,
                        $UserTime,
                        $summaryStateItem
                    );
                    $this->set('type', 'map');
                    $this->set('summary', $summary);
                    $this->viewBuilder()->setOption('serialize', ['map', 'summary']);
                    return;
                }
                throw new NotFoundException('Map not found!');
                return;
                break;
            default:
                throw new RuntimeException('Unknown map item type');
                break;
        }

    }

    public
    function edit($id = null) {
        if (!$this->isApiRequest() && $id === null) {
            //Only ship template

            $gadgetPreviews = [
                'RRDGraph'      => 'graph_gadget.png',
                'Tacho'         => 'tacho_gadget.png',
                'TrafficLight'  => 'trafficlight_gadget.png',
                'Cylinder'      => 'cylinder_gadget.png',
                'Text'          => 'perfdata_gadget.png',
                'Temperature'   => 'temperature_gadget.png',
                'ServiceOutput' => 'serviceoutput_gadget.png'
            ];
            $this->set('gadgetPreviews', $gadgetPreviews);
            $this->set('requiredIcons', $this->MapUpload->getIconsNames());
            return;
        }

        $FileUploadSize = new FileUploadSize();

        $id = (int)$id;
        if (!$this->Map->exists($id)) {
            throw new NotFoundException();
        }
        $map = $this->Map->find('first', [
            'recursive'  => -1,
            'contain'    => [
                'Container',
                'Mapitem',
                'Mapline',
                'Mapgadget',
                'Mapicon',
                'Maptext',
                'Mapsummaryitem'
            ],
            'conditions' => [
                'Map.id' => $id
            ]
        ]);

        $MapForAngular = new MapForAngular($map);
        $map = $MapForAngular->toArray();


        $this->set('map', $map);
        $this->set('maxUploadLimit', $FileUploadSize->toArray());
        $this->set('max_z_index', $MapForAngular->getMaxZIndex());
        $this->set('layers', $MapForAngular->getLayers());
        $this->viewBuilder()->setOption('serialize', ['map', 'maxUploadLimit', 'max_z_index', 'layers']);
    }

    public
    function backgroundImages() {
        if (!$this->isApiRequest()) {
            throw new MethodNotAllowedException();
        }

        if (!is_dir(OLD_APP . 'Plugin' . DS . 'MapModule' . DS . 'webroot' . DS . 'img' . DS . 'backgrounds')) {
            mkdir(OLD_APP . 'Plugin' . DS . 'MapModule' . DS . 'webroot' . DS . 'img' . DS . 'backgrounds');
        }

        if (!is_dir(OLD_APP . 'Plugin' . DS . 'MapModule' . DS . 'webroot' . DS . 'img' . DS . 'backgrounds' . DS . 'thumb')) {
            mkdir(OLD_APP . 'Plugin' . DS . 'MapModule' . DS . 'webroot' . DS . 'img' . DS . 'backgrounds' . DS . 'thumb');
        }

        $finder = new Finder();
        $finder->files()->in(OLD_APP . 'Plugin' . DS . 'MapModule' . DS . 'webroot' . DS . 'img' . DS . 'backgrounds')->exclude('thumb');

        $backgrounds = [];
        /** @var \Symfony\Component\Finder\SplFileInfo $file */
        foreach ($finder as $file) {
            $backgrounds[] = [
                'image'     => $file->getFilename(),
                'path'      => sprintf('/map_module/img/backgrounds/%s', $file->getFilename()),
                'thumbnail' => sprintf('/map_module/img/backgrounds/thumb/thumb_%s', $file->getFilename()),
            ];
        }

        $this->set('backgrounds', $backgrounds);
        $this->viewBuilder()->setOption('serialize', ['backgrounds']);
    }

    public
    function getIconsets() {
        if (!$this->isApiRequest()) {
            throw new MethodNotAllowedException();
        }

        $iconsets = $this->MapUpload->getIconSets();

        $this->set('iconsets', $iconsets);
        $this->viewBuilder()->setOption('serialize', ['iconsets']);
    }

    public
    function loadMapsByString() {
        if (!$this->isAngularJsRequest()) {
            throw new MethodNotAllowedException();
        }

        $selected = $this->request->getQuery('selected');
        $excluded = $this->request->getQuery('excluded');

        $MapFilter = new MapFilter($this->request);

        $MapConditions = new MapConditions($MapFilter->indexFilter());
        $MapConditions->setContainerIds($this->MY_RIGHTS);

        $maps = Api::makeItJavaScriptAble(
            $this->Map->getMapsForAngular($MapConditions, $selected, $excluded)
        );

        $this->set(compact(['maps']));
        $this->viewBuilder()->setOption('serialize', ['maps']);
    }

    public
    function saveItem() {
        if (!$this->request->is('post') || !$this->isAngularJsRequest()) {
            throw new MethodNotAllowedException();
        }

        //Save new possition after drag and drop
        if ($this->request->getData('action') === 'dragstop') {
            $item = $this->Mapitem->find('first', [
                'recursive'  => -1,
                'conditions' => [
                    'Mapitem.id' => $this->request->getData('Mapitem.id')
                ]
            ]);

            $item['Mapitem']['x'] = (int)$this->request->getData('Mapitem.x');
            $item['Mapitem']['y'] = (int)$this->request->getData('Mapitem.y');
            if($this->request->getData('Mapitem.show_label') !== null) {
                $item['Mapitem']['show_label'] = (int)$this->request->getData('Mapitem.show_label');
            }
            if ($this->Mapitem->save($item)) {
                $mapitem = new \itnovum\openITCOCKPIT\Maps\ValueObjects\Mapitem($item['Mapitem']);

                $this->set('Mapitem', [
                    'Mapitem' => $mapitem->toArray()
                ]);

                $this->viewBuilder()->setOption('serialize', ['Mapitem']);
                return;
            }
            $this->serializeErrorMessageFromModel('Mapitem');
            return;
        }


        //Create new item or update existing one
        if (!isset($this->request->data['MapItem']['id'])) {
            $this->Mapitem->create();
        }

        $item = $this->request->data;
        $item['Mapitem']['show_label'] = (int)$this->request->getData('Mapitem.show_label');
        if ($this->Mapitem->save($item)) {
            $mapItem = $item;
            $mapItem['Mapitem']['id'] = (int)$this->Mapitem->id;

            $mapitem = new \itnovum\openITCOCKPIT\Maps\ValueObjects\Mapitem($mapItem['Mapitem']);


            $this->set('Mapitem', [
                'Mapitem' => $mapitem->toArray()
            ]);
            $this->viewBuilder()->setOption('serialize', ['Mapitem']);
            return;
        }
        $this->serializeErrorMessageFromModel('Mapitem');
    }

    public
    function deleteItem() {
        if (!$this->request->is('post') || !$this->isAngularJsRequest()) {
            throw new MethodNotAllowedException();
        }

        $id = $this->request->getData('Mapitem.id');


        if (!$this->Mapitem->exists($id)) {
            throw new NotFoundException();
        }

        if (!is_numeric($id)) {
            throw new InvalidArgumentException('Mapitem.id needs to be numeric');
        }

        if ($this->Mapitem->delete($id)) {
            $this->set('success', true);
            $this->viewBuilder()->setOption('serialize', ['success']);
            return;
        }

        $this->set('success', false);
        $this->viewBuilder()->setOption('serialize', ['success']);
    }

    public
    function saveLine() {
        if (!$this->request->is('post') || !$this->isAngularJsRequest()) {
            throw new MethodNotAllowedException();
        }

        //Save new possition after drag and drop
        if ($this->request->getData('action') === 'dragstop') {
            $line = $this->Mapline->find('first', [
                'recursive'  => -1,
                'conditions' => [
                    'Mapline.id' => $this->request->getData('Mapline.id')
                ]
            ]);

            $line['Mapline']['startX'] = (int)$this->request->getData('Mapline.startX');
            $line['Mapline']['startY'] = (int)$this->request->getData('Mapline.startY');
            $line['Mapline']['endX'] = (int)$this->request->getData('Mapline.endX');
            $line['Mapline']['endY'] = (int)$this->request->getData('Mapline.endY');
            if ($this->Mapline->save($line)) {
                $Mapline = new \itnovum\openITCOCKPIT\Maps\ValueObjects\Mapline($line['Mapline']);

                $this->set('Mapline', [
                    'Mapline' => $Mapline->toArray()
                ]);

                $this->viewBuilder()->setOption('serialize', ['Mapline']);
                return;
            }
            $this->serializeErrorMessageFromModel('Mapline');
            return;
        }

        //Create new item or update existing one
        if (!isset($this->request->data['Mapline']['id'])) {
            $this->Mapline->create();
        }

        $line = $this->request->data;
        $line['Mapline']['show_label'] = (int)$this->request->getData('Mapline.show_label');
        if ($this->Mapline->save($line)) {
            $line['Mapline']['id'] = (int)$this->Mapline->id;
            $Mapline = new \itnovum\openITCOCKPIT\Maps\ValueObjects\Mapline($line['Mapline']);
            $this->set('Mapline', [
                'Mapline' => $Mapline->toArray()
            ]);
            $this->viewBuilder()->setOption('serialize', ['Mapline']);
            return;
        }
        $this->serializeErrorMessageFromModel('Mapline');
    }

    public
    function deleteLine() {
        if (!$this->request->is('post') || !$this->isAngularJsRequest()) {
            throw new MethodNotAllowedException();
        }

        $id = $this->request->getData('Mapline.id');


        if (!$this->Mapline->exists($id)) {
            throw new NotFoundException();
        }

        if (!is_numeric($id)) {
            throw new InvalidArgumentException('Mapline.id needs to be numeric');
        }

        if ($this->Mapline->delete($id)) {
            $this->set('success', true);
            $this->viewBuilder()->setOption('serialize', ['success']);
            return;
        }

        $this->set('success', false);
        $this->viewBuilder()->setOption('serialize', ['success']);
    }

    public
    function saveGadget() {
        if (!$this->request->is('post') || !$this->isAngularJsRequest()) {
            throw new MethodNotAllowedException();
        }

        //Save new possition after drag and drop
        if ($this->request->getData('action') === 'dragstop') {
            $gadget = $this->Mapgadget->find('first', [
                'recursive'  => -1,
                'conditions' => [
                    'Mapgadget.id' => $this->request->getData('Mapgadget.id')
                ]
            ]);

            $gadget['Mapgadget']['x'] = (int)$this->request->getData('Mapgadget.x');
            $gadget['Mapgadget']['y'] = (int)$this->request->getData('Mapgadget.y');

            //NULL -> No metric selected. Cast to 0 and Gadget will use first existing metric in performance data string
            $gadget['Mapgadget']['metric'] = $gadget['Mapgadget']['metric'];
            if ($this->Mapgadget->save($gadget)) {
                $Mapgadget = new \itnovum\openITCOCKPIT\Maps\ValueObjects\Mapgadget($gadget['Mapgadget']);

                $this->set('Mapgadget', [
                    'Mapgadget' => $Mapgadget->toArray()
                ]);

                $this->viewBuilder()->setOption('serialize', ['Mapgadget']);
                return;
            }
            $this->serializeErrorMessageFromModel('Mapgadget');
            return;
        }

        //Save new gadget size
        if ($this->request->getData('action') === 'resizestop') {
            $gadget = $this->Mapgadget->find('first', [
                'recursive'  => -1,
                'conditions' => [
                    'Mapgadget.id' => $this->request->getData('Mapgadget.id')
                ]
            ]);

            $gadget['Mapgadget']['size_x'] = (int)$this->request->getData('Mapgadget.size_x');
            $gadget['Mapgadget']['size_y'] = (int)$this->request->getData('Mapgadget.size_y');

            //NULL -> No metric selected. Cast to 0 and Gadget will use first existing metric in performance data string
            $gadget['Mapgadget']['metric'] = $gadget['Mapgadget']['metric'];
            if ($this->Mapgadget->save($gadget)) {
                $Mapgadget = new \itnovum\openITCOCKPIT\Maps\ValueObjects\Mapgadget($gadget['Mapgadget']);

                $this->set('Mapgadget', [
                    'Mapgadget' => $Mapgadget->toArray()
                ]);

                $this->viewBuilder()->setOption('serialize', ['Mapgadget']);
                return;
            }
            $this->serializeErrorMessageFromModel('Mapgadget');
            return;
        }


        //Create new gadget or update existing one
        if (!isset($this->request->data['Mapgadget']['id'])) {
            $this->Mapgadget->create();
        }

        $gadget = $this->request->data;

        $gadget['Mapgadget']['show_label'] = (int)$this->request->getData('Mapgadget.show_label');
        $gadget['Mapgadget']['size_x'] = (int)$this->request->getData('Mapgadget.size_x');
        $gadget['Mapgadget']['size_y'] = (int)$this->request->getData('Mapgadget.size_y');

        if ($this->Mapgadget->save($gadget)) {
            $gadget['Mapgadget']['id'] = (int)$this->Mapgadget->id;
            $Mapgadget = new \itnovum\openITCOCKPIT\Maps\ValueObjects\Mapgadget($gadget['Mapgadget']);
            $this->set('Mapgadget', [
                'Mapgadget' => $Mapgadget->toArray()
            ]);
            $this->viewBuilder()->setOption('serialize', ['Mapgadget']);
            return;
        }
        $this->serializeErrorMessageFromModel('Mapgadget');
    }

    public
    function deleteGadget() {
        if (!$this->request->is('post') || !$this->isAngularJsRequest()) {
            throw new MethodNotAllowedException();
        }

        $id = $this->request->getData('Mapgadget.id');


        if (!$this->Mapgadget->exists($id)) {
            throw new NotFoundException();
        }

        if (!is_numeric($id)) {
            throw new InvalidArgumentException('Mapgadget.id needs to be numeric');
        }

        if ($this->Mapgadget->delete($id)) {
            $this->set('success', true);
            $this->viewBuilder()->setOption('serialize', ['success']);
            return;
        }

        $this->set('success', false);
        $this->viewBuilder()->setOption('serialize', ['success']);
    }

    public
    function getPerformanceDataMetrics($serviceId) {
        if (!$this->isAngularJsRequest()) {
            throw new MethodNotAllowedException();
        }

        if (!$this->Service->exists($serviceId)) {
            throw new NotFoundException();
        }

        $service = $this->Service->find('first', [
            'recursive'  => -1,
            'fields'     => [
                'Service.id',
                'Service.uuid'
            ],
            'conditions' => [
                'Service.id' => $serviceId,
            ],
        ]);


        $ServicestatusFields = new ServicestatusFields($this->DbBackend);
        $ServicestatusFields->perfdata();
        $servicestatus = $this->Servicestatus->byUuid($service['Service']['uuid'], $ServicestatusFields);

        if (!empty($servicestatus)) {
            $PerfdataParser = new PerfdataParser($servicestatus['Servicestatus']['perfdata']);
            $this->set('perfdata', $PerfdataParser->parse());
            $this->viewBuilder()->setOption('serialize', ['perfdata']);
            return;
        }
        $this->set('perfdata', []);
        $this->viewBuilder()->setOption('serialize', ['perfdata']);
    }

    public
    function saveText() {
        if (!$this->request->is('post') || !$this->isAngularJsRequest()) {
            throw new MethodNotAllowedException();
        }

        //Save new possition after drag and drop
        if ($this->request->getData('action') === 'dragstop') {
            $maptext = $this->Maptext->find('first', [
                'recursive'  => -1,
                'conditions' => [
                    'Maptext.id' => $this->request->getData('Maptext.id')
                ]
            ]);

            $maptext['Maptext']['x'] = (int)$this->request->getData('Maptext.x');
            $maptext['Maptext']['y'] = (int)$this->request->getData('Maptext.y');
            $maptext['Maptext']['font_size'] = 11;

            if ($this->Maptext->save($maptext)) {
                $Maptext = new \itnovum\openITCOCKPIT\Maps\ValueObjects\Maptext($maptext['Maptext']);

                $this->set('Maptext', [
                    'Maptext' => $Maptext->toArray()
                ]);

                $this->viewBuilder()->setOption('serialize', ['Maptext']);
                return;
            }
            $this->serializeErrorMessageFromModel('Maptext');
            return;
        }

        //Create new text or update existing one
        if (!isset($this->request->data['Maptext']['id'])) {
            $this->Maptext->create();
        }

        $text = $this->request->data;

        $text['Maptext']['x'] = (int)$this->request->getData('Maptext.x');
        $text['Maptext']['y'] = (int)$this->request->getData('Maptext.y');
        $maptext['Maptext']['font_size'] = 11;
        if ($this->Maptext->save($text)) {
            $text['Maptext']['id'] = (int)$this->Maptext->id;
            $Maptext = new \itnovum\openITCOCKPIT\Maps\ValueObjects\Maptext($text['Maptext']);
            $this->set('Maptext', [
                'Maptext' => $Maptext->toArray()
            ]);
            $this->viewBuilder()->setOption('serialize', ['Maptext']);
            return;
        }
        $this->serializeErrorMessageFromModel('Maptext');
    }

    public
    function deleteText() {
        if (!$this->request->is('post') || !$this->isAngularJsRequest()) {
            throw new MethodNotAllowedException();
        }

        $id = $this->request->getData('Maptext.id');


        if (!$this->Maptext->exists($id)) {
            throw new NotFoundException();
        }

        if (!is_numeric($id)) {
            throw new InvalidArgumentException('Maptext.id needs to be numeric');
        }

        if ($this->Maptext->delete($id)) {
            $this->set('success', true);
            $this->viewBuilder()->setOption('serialize', ['success']);
            return;
        }

        $this->set('success', false);
        $this->viewBuilder()->setOption('serialize', ['success']);
    }

    public
    function saveIcon() {
        if (!$this->request->is('post') || !$this->isAngularJsRequest()) {
            throw new MethodNotAllowedException();
        }

        //Save new possition after drag and drop
        if ($this->request->getData('action') === 'dragstop') {
            $mapicon = $this->Mapicon->find('first', [
                'recursive'  => -1,
                'conditions' => [
                    'Mapicon.id' => $this->request->getData('Mapicon.id')
                ]
            ]);

            $mapicon['Mapicon']['x'] = (int)$this->request->getData('Mapicon.x');
            $mapicon['Mapicon']['y'] = (int)$this->request->getData('Mapicon.y');

            if ($this->Mapicon->save($mapicon)) {
                $Mapicon = new \itnovum\openITCOCKPIT\Maps\ValueObjects\Mapicon($mapicon['Mapicon']);

                $this->set('Mapicon', [
                    'Mapicon' => $Mapicon->toArray()
                ]);

                $this->viewBuilder()->setOption('serialize', ['Mapicon']);
                return;
            }
            $this->serializeErrorMessageFromModel('Mapicon');
            return;
        }

        //Create new icon or update existing one
        if (!isset($this->request->data['Mapicon']['id'])) {
            $this->Mapicon->create();
        }

        $icon = $this->request->data;

        $icon['Mapicon']['x'] = (int)$this->request->getData('Mapicon.x');
        $icon['Mapicon']['y'] = (int)$this->request->getData('Mapicon.y');
        if ($this->Mapicon->save($icon)) {
            $icon['Mapicon']['id'] = (int)$this->Mapicon->id;
            $Mapicon = new \itnovum\openITCOCKPIT\Maps\ValueObjects\Mapicon($icon['Mapicon']);
            $this->set('Mapicon', [
                'Mapicon' => $Mapicon->toArray()
            ]);
            $this->viewBuilder()->setOption('serialize', ['Mapicon']);
            return;
        }
        $this->serializeErrorMessageFromModel('Mapicon');
    }

    public
    function deleteIcon() {
        if (!$this->request->is('post') || !$this->isAngularJsRequest()) {
            throw new MethodNotAllowedException();
        }

        $id = $this->request->getData('Mapicon.id');


        if (!$this->Mapicon->exists($id)) {
            throw new NotFoundException();
        }

        if (!is_numeric($id)) {
            throw new InvalidArgumentException('Mapicon.id needs to be numeric');
        }

        if ($this->Mapicon->delete($id)) {
            $this->set('success', true);
            $this->viewBuilder()->setOption('serialize', ['success']);
            return;
        }

        $this->set('success', false);
        $this->viewBuilder()->setOption('serialize', ['success']);
    }

    public
    function saveBackground() {
        if (!$this->request->is('post') || !$this->isAngularJsRequest()) {
            throw new MethodNotAllowedException();
        }

        $id = $this->request->getData('Map.id');

        if (!$this->Map->exists($id)) {
            throw new NotFoundException();
        }

        $map = $this->Map->find('first', [
            'recursive'  => -1,
            'conditions' => [
                'Map.id' => $id
            ]
        ]);

        $map['Map']['background'] = $this->request->getData('Map.background');

        if ($this->Map->save($map)) {
            $this->set('Map', [
                'Map' => $map
            ]);

            $this->viewBuilder()->setOption('serialize', ['Map']);
            return;
        }
        $this->serializeErrorMessageFromModel('Map');
        return;
    }

    public
    function getIcons() {
        if (!$this->isApiRequest()) {
            throw new MethodNotAllowedException();
        }

        $icons = $this->MapUpload->getIcons();

        $this->set('icons', $icons);
        $this->viewBuilder()->setOption('serialize', ['icons']);
    }

    public
    function saveSummaryitem() {
        if (!$this->request->is('post') || !$this->isAngularJsRequest()) {
            throw new MethodNotAllowedException();
        }

        //Save new possition after drag and drop
        if ($this->request->getData('action') === 'dragstop') {
            $mapsummaryitem = $this->Mapsummaryitem->find('first', [
                'recursive'  => -1,
                'conditions' => [
                    'Mapsummaryitem.id' => $this->request->getData('Mapsummaryitem.id')
                ]
            ]);

            $mapsummaryitem['Mapsummaryitem']['x'] = (int)$this->request->getData('Mapsummaryitem.x');
            $mapsummaryitem['Mapsummaryitem']['y'] = (int)$this->request->getData('Mapsummaryitem.y');
            if ($this->Mapsummaryitem->save($mapsummaryitem)) {
                $Mapsummaryitem = new \itnovum\openITCOCKPIT\Maps\ValueObjects\Mapsummaryitem($mapsummaryitem['Mapsummaryitem']);

                $this->set('Mapsummaryitem', [
                    'Mapsummaryitem' => $Mapsummaryitem->toArray()
                ]);

                $this->viewBuilder()->setOption('serialize', ['Mapsummaryitem']);
                return;
            }
            $this->serializeErrorMessageFromModel('Mapsummaryitem');
            return;
        }

        //Save new Mapsummaryitem size
        if ($this->request->getData('action') === 'resizestop') {
            $mapsummaryitem = $this->Mapsummaryitem->find('first', [
                'recursive'  => -1,
                'conditions' => [
                    'Mapsummaryitem.id' => $this->request->getData('Mapsummaryitem.id')
                ]
            ]);

            $mapsummaryitem['Mapsummaryitem']['size_x'] = (int)$this->request->getData('Mapsummaryitem.size_x');
            $mapsummaryitem['Mapsummaryitem']['size_y'] = (int)$this->request->getData('Mapsummaryitem.size_y');

            if ($this->Mapsummaryitem->save($mapsummaryitem)) {
                $Mapsummaryitem = new \itnovum\openITCOCKPIT\Maps\ValueObjects\Mapsummaryitem($mapsummaryitem['Mapsummaryitem']);

                $this->set('Mapsummaryitem', [
                    'Mapsummaryitem' => $Mapsummaryitem->toArray()
                ]);

                $this->viewBuilder()->setOption('serialize', ['Mapsummaryitem']);
                return;
            }
            $this->serializeErrorMessageFromModel('Mapsummaryitem');
            return;
        }


        //Create new Mapsummaryitem or update existing one
        if (!isset($this->request->data['Mapsummaryitem']['id'])) {
            $this->Mapsummaryitem->create();
        }

        $mapsummaryitem = $this->request->data;

        $mapsummaryitem['Mapsummaryitem']['show_label'] = (int)$this->request->getData('Mapsummaryitem.show_label');
        $mapsummaryitem['Mapsummaryitem']['size_x'] = (int)$this->request->getData('Mapsummaryitem.size_x');
        $mapsummaryitem['Mapsummaryitem']['size_y'] = (int)$this->request->getData('Mapsummaryitem.size_y');

        if ($this->Mapsummaryitem->save($mapsummaryitem)) {
            $mapsummaryitem['Mapsummaryitem']['id'] = $this->Mapsummaryitem->id;
            $Mapsummaryitem = new \itnovum\openITCOCKPIT\Maps\ValueObjects\Mapsummaryitem($mapsummaryitem['Mapsummaryitem']);
            $this->set('Mapsummaryitem', [
                'Mapsummaryitem' => $Mapsummaryitem->toArray()
            ]);
            $this->viewBuilder()->setOption('serialize', ['Mapsummaryitem']);
            return;
        }
        $this->serializeErrorMessageFromModel('Mapsummaryitem');
    }

    public
    function deleteSummaryitem() {
        if (!$this->request->is('post') || !$this->isAngularJsRequest()) {
            throw new MethodNotAllowedException();
        }

        $id = $this->request->getData('Mapsummaryitem.id');


        if (!$this->Mapsummaryitem->exists($id)) {
            throw new NotFoundException();
        }

        if (!is_numeric($id)) {
            throw new InvalidArgumentException('Mapsummaryitem.id needs to be numeric');
        }

        if ($this->Mapsummaryitem->delete($id)) {
            $this->set('success', true);
            $this->viewBuilder()->setOption('serialize', ['success']);
            return;
        }

        $this->set('success', false);
        $this->viewBuilder()->setOption('serialize', ['success']);
    }

    public
    function mapDetails($id) {
        $id = (int)$id;
        if (!$this->Map->exists($id)) {
            throw new NotFoundException();
        }
        $map = $this->Map->find('first', [
            'recursive'  => -1,
            'conditions' => [
                'Map.id' => $id
            ],
            'contain'    => [
                'Container'
            ]
        ]);

        $containerIdsToCheck = Hash::extract($map, 'Container.{n}.MapsToContainer.container_id');
        if (!$this->allowedByContainerId($containerIdsToCheck, false)) {
            $this->render403();
            return;
        }

        $MapForAngular = new MapForAngular($map);
        $map = $MapForAngular->toArray();

        $this->set('map', $map);

        $this->viewBuilder()->setOption('serialize', ['map']);
    }

    public
    function viewDirective() {
        $this->layout = 'blank';
        //Ship template of Mapeditors view directive.
        //It is a directive be able to also use the maps as an widget
        return;
    }

    public
    function mapWidget() {
        $this->layout = 'blank';
        if (!$this->isApiRequest()) {
            //Only ship HTML template
            return;
        }

        $this->loadModel('Widget');

        if ($this->request->is('get')) {
            $widgetId = (int)$this->request->getQuery('widgetId');
            if (!$this->Widget->exists($widgetId)) {
                throw new RuntimeException('Invalid widget id');
            }

            $widget = $this->Widget->find('first', [
                'recursive'  => -1,
                'conditions' => [
                    'Widget.id' => $widgetId
                ],
                'fields'     => [
                    'Widget.json_data'
                ]
            ]);

            $config = [
                'map_id' => null
            ];
            if ($widget['Widget']['json_data'] !== null && $widget['Widget']['json_data'] !== '') {
                $config = json_decode($widget['Widget']['json_data'], true);
                if (!isset($config['map_id'])) {
                    $config['map_id'] = null;
                }
            }

            //Check map permissions
            $map = $this->Map->find('first', [
                'recursive'  => -1,
                'contain'    => [
                    'Container',
                    'Mapitem',
                    'Mapline',
                    'Mapgadget',
                    'Mapicon',
                    'Maptext',
                    'Mapsummaryitem'
                ],
                'conditions' => [
                    'Map.id' => $config['map_id']
                ]
            ]);

            $containerIdsToCheck = Hash::extract($map, 'Container.{n}.MapsToContainer.container_id');
            if (!$this->allowedByContainerId($containerIdsToCheck, false)) {
                $config['map_id'] = null;
            }

            $this->set('config', $config);
            $this->viewBuilder()->setOption('serialize', ['config']);
            return;
        }


        if ($this->request->is('post')) {
            $mapId = (int)$this->request->getData('map_id');
            if ($mapId === 0) {
                $mapId = null;
            }

            $config = [
                'map_id' => $mapId
            ];

            $widgetId = (int)$this->request->getData('Widget.id');

            if (!$this->Widget->exists($widgetId)) {
                throw new RuntimeException('Invalid widget id');
            }
            $widget = $this->Widget->find('first', [
                'recursive'  => -1,
                'conditions' => [
                    'Widget.id' => $widgetId
                ],
            ]);

            $widget['Widget']['json_data'] = json_encode($config);
            if ($this->Widget->save($widget)) {
                $this->set('config', $config);
                $this->viewBuilder()->setOption('serialize', ['config']);
                return;
            }

            $this->serializeErrorMessageFromModel('Widget');
            return;
        }
        throw new MethodNotAllowedException();
    }
}