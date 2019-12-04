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

declare(strict_types=1);

namespace App\Controller;


use App\Model\Table\ContainersTable;
use App\Model\Table\LocationsTable;
use Cake\ORM\TableRegistry;
use itnovum\openITCOCKPIT\Core\AngularJS\Api;
use itnovum\openITCOCKPIT\Core\DbBackend;
use itnovum\openITCOCKPIT\Database\PaginateOMat;
use itnovum\openITCOCKPIT\Filter\LocationFilter;

/**
 * Class LocationsController
 * @property AppPaginatorComponent $Paginator
 * @property DbBackend $DbBackend
 * @property Changelog $Changelog
 */
class LocationsController extends AppController {

    public $layout = 'blank';

    public function index() {
        if (!$this->isAngularJsRequest()) {
            //Only ship HTML Template
            return;
        }

        /** @var $LocationsTable LocationsTable */
        $LocationsTable = TableRegistry::getTableLocator()->get('Locations');
        $LocationFilter = new LocationFilter($this->request);

        $PaginateOMat = new PaginateOMat($this->Paginator, $this, $this->isScrollRequest(), $LocationFilter->getPage());
        $all_locations = $LocationsTable->getLocationsIndex($LocationFilter, $PaginateOMat);

        foreach ($all_locations as $key => $location) {
            $all_locations[$key]['Location']['allowEdit'] = false;
            $locationContainerId = $location['Location']['container_id'];
            if (isset($this->MY_RIGHTS_LEVEL[$locationContainerId])) {
                if ((int)$this->MY_RIGHTS_LEVEL[$locationContainerId] === WRITE_RIGHT) {
                    $all_locations[$key]['Location']['allowEdit'] = true;
                }
            }
        }

        $this->set('all_locations', $all_locations);
        $toJson = ['all_locations', 'paging'];
        if ($this->isScrollRequest()) {
            $toJson = ['all_locations', 'scroll'];
        }
        $this->viewBuilder()->setOption('serialize', $toJson);
    }

    /**
     * @param $id
     */
    public function view($id) {
        if (!$this->isApiRequest()) {
            throw new MethodNotAllowedException();
        }

        /** @var $LocationsTable LocationsTable */
        $LocationsTable = TableRegistry::getTableLocator()->get('Locations');

        if (!$LocationsTable->existsById($id)) {
            throw new NotFoundException(__('Invalid location'));
        }

        $location = $LocationsTable->getLocationById($id);

        if (!$this->allowedByContainerId($location['container_id'])) {
            $this->render403();
            return;
        }

        $this->set('location', $location);
        $this->viewBuilder()->setOption('serialize', ['location']);
    }

    public function add() {
        $this->layout = 'blank';
        if (!$this->isApiRequest()) {
            //Only ship HTML template for angular
            return;
        }

        /** @var $LocationsTable LocationsTable */
        $LocationsTable = TableRegistry::getTableLocator()->get('Locations');

        if ($this->request->is('post') && $this->isAngularJsRequest()) {
            $this->request->data['uuid'] = UUID::v4();
            $location = $LocationsTable->newEmptyEntity();
            $location = $LocationsTable->patchEntity($location, $this->request->data);
            $location->container->containertype_id = CT_LOCATION;

            $LocationsTable->save($location);
            if ($location->hasErrors()) {
                $this->response = $this->response->withStatus(400);
                $this->set('error', $location->getErrors());
                $this->viewBuilder()->setOption('serialize', ['error']);
                return;
            } else {
                $User = new User($this->getUser());

                $changelog_data = $this->Changelog->parseDataForChangelog(
                    'add',
                    'locations',
                    $location->get('id'),
                    OBJECT_LOCATION,
                    [$location->get('container')->get('parent_id')],
                    $User->getId(),
                    $location->container->name,
                    [
                        'location' => $location->toArray()
                    ]
                );
                if ($changelog_data) {
                    CakeLog::write('log', serialize($changelog_data));
                }

                //@todo refactor with cake4
                Cache::clear(false, 'permissions');

                if ($this->isJsonRequest()) {
                    $this->serializeCake4Id($location); // REST API ID serialization
                    return;
                }
            }
            $this->set('location', $location);
            $this->viewBuilder()->setOption('serialize', ['location']);
        }
    }

    /**
     * @param null $id
     */
    public function edit($id = null) {
        $this->layout = 'blank';

        if (!$this->isApiRequest()) {
            //Only ship HTML template for angular
            return;
        }

        /** @var $LocationsTable LocationsTable */
        $LocationsTable = TableRegistry::getTableLocator()->get('Locations');

        if (!$LocationsTable->existsById($id)) {
            throw new NotFoundException(__('Invalid location'));
        }

        if ($this->request->is('get')) {
            $location = $LocationsTable->getLocationById($id);

            if (!$this->allowedByContainerId($location['container_id'])) {
                $this->render403();
                return;
            }

            $this->set('location', $location);
            $this->viewBuilder()->setOption('serialize', ['location']);
            return;
        }

        if ($this->request->is('post') && $this->isAngularJsRequest()) {
            $oldLocation = $LocationsTable->get($id, [
                'contain' => ['Containers']
            ]);
            $oldLocationForChangelog = $oldLocation->toArray();
            if (!$this->allowedByContainerId($oldLocation->get('container_id'))) {
                $this->render403();
                return;
            }

            $location = $LocationsTable->patchEntity($oldLocation, $this->request->data);

            $location->container_id = $oldLocation->get('container_id');
            $location->container->id = $oldLocation->get('container_id');
            $location->container->containertype_id = CT_LOCATION;

            $LocationsTable->save($location);
            if ($location->hasErrors()) {
                $this->response = $this->response->withStatus(400);
                $this->set('error', $location->getErrors());
                $this->viewBuilder()->setOption('serialize', ['error']);
                return;
            } else {
                $User = new User($this->getUser());
                $changelog_data = $this->Changelog->parseDataForChangelog(
                    'edit',
                    'locations',
                    $location->get('id'),
                    OBJECT_LOCATION,
                    [$location->get('container')->get('parent_id')],
                    $User->getId(),
                    $location->container->name,
                    [
                        'location' => $location->toArray()
                    ],
                    [
                        'location' => $oldLocationForChangelog
                    ]
                );
                if ($changelog_data) {
                    CakeLog::write('log', serialize($changelog_data));
                }

                //@todo refactor with cake4
                Cache::clear(false, 'permissions');

                if ($this->isJsonRequest()) {
                    $this->serializeCake4Id($location); // REST API ID serialization
                    return;
                }
            }
            $this->set('location', $location);
            $this->viewBuilder()->setOption('serialize', ['location']);
        }
    }

    /**
     * @param null $id
     * @todo allowDelete -> for eventcorrelation module
     */
    public function delete($id = null) {
        if (!$this->request->is('post')) {
            throw new MethodNotAllowedException();
        }
        /** @var $LocationsTable LocationsTable */
        $LocationsTable = TableRegistry::getTableLocator()->get('Locations');
        /** @var $ContainersTable ContainersTable */
        $ContainersTable = TableRegistry::getTableLocator()->get('Containers');

        if (!$LocationsTable->existsById($id)) {
            throw new NotFoundException(__('Invalid location'));
        }

        $location = $LocationsTable->getLocationById($id);
        $container = $ContainersTable->get($location->get('container')->get('id'));

        if (!$this->allowedByContainerId($location->get('container')->get('parent_id'))) {
            $this->render403();
            return;
        }

        if ($ContainersTable->delete($container)) {
            $User = new User($this->getUser());
            $changelog_data = $this->Changelog->parseDataForChangelog(
                'delete',
                'locations',
                $id,
                OBJECT_LOCATION,
                $container->get('parent_id'),
                $User->getId(),
                $container->get('name'),
                [
                    'location' => $location->toArray()
                ]
            );
            if ($changelog_data) {
                CakeLog::write('log', serialize($changelog_data));
            }

            $this->set('success', true);
            $this->viewBuilder()->setOption('serialize', ['success']);
            return;
        }

        $this->response = $this->response->withStatus(500);
        $this->set('success', false);
        $this->viewBuilder()->setOption('serialize', ['success']);
    }

    /****************************
     *       AJAX METHODS       *
     ****************************/

    /**
     * @throws Exception
     */
    public function loadContainers() {
        if (!$this->isAngularJsRequest()) {
            throw new MethodNotAllowedException();
        }

        /** @var $ContainersTable ContainersTable */
        $ContainersTable = TableRegistry::getTableLocator()->get('Containers');

        if ($this->hasRootPrivileges === true) {
            $containers = $ContainersTable->easyPath($this->MY_RIGHTS, OBJECT_LOCATION, [], $this->hasRootPrivileges);
        } else {
            $containers = $ContainersTable->easyPath($this->getWriteContainers(), OBJECT_LOCATION, [], $this->hasRootPrivileges);
        }
        $containers = Api::makeItJavaScriptAble($containers);


        $this->set('containers', $containers);
        $this->viewBuilder()->setOption('serialize', ['containers']);
    }

}
