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

use App\Model\Table\SystemsettingsTable;
use Authentication\Controller\Component\AuthenticationComponent;
use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\ORM\TableRegistry;
use Cake\View\Exception\MissingTemplateException;


/**
 * Class PagesController
 * @package App\Controller
 * @property AuthenticationComponent $Authentication
 */
class PagesController extends AppController {

    public function index() {

        if ($this->isApiRequest() === false) {
            $user = $this->Authentication->getIdentity();

            /** @var SystemsettingsTable $SystemsettingsTable */
            $SystemsettingsTable = TableRegistry::getTableLocator()->get('Systemsettings');
            $systemsettingsArray = $SystemsettingsTable->findAsArray();

            $exportRunningHeaderInfo = false;
            if (isset($systemsettingsArray['FRONTEND']['FRONTEND.SHOW_EXPORT_RUNNING'])) {
                if ($systemsettingsArray['FRONTEND']['FRONTEND.SHOW_EXPORT_RUNNING'] === 'yes') {
                    $exportRunningHeaderInfo = true;
                }
            }

            $userImage = '/img/fallback_user.png';
            if ($user->get('image') != null && $user->get('image') != '') {
                if (file_exists(WWW_ROOT . 'img' . DS . 'userimages' . DS . $user->get('image'))) {
                    $userImage = '/img/userimages' . DS . $user->get('image');
                }
            }

            $userFullName = sprintf('%s %s', $user->get('firstname'), $user->get('lastname'));

            $this->set('systemname', $systemsettingsArray['FRONTEND']['FRONTEND.SYSTEMNAME']);
            $this->set('exportRunningHeaderInfo', $exportRunningHeaderInfo);
            $this->set('showstatsinmenu', $user->get('showstatsinmenu'));
            $this->set('userImage', $userImage);
            $this->set('userFullName', $userFullName);
            $this->set('hasRootPrivileges', $this->hasRootPrivileges);
            // Ship the HTML layout to load JS and CSS files
            $this->viewBuilder()->setLayout('app_frame');
        }
    }

    /**
     * Displays a view
     *
     * @param array ...$path Path segments.
     * @return Response|null
     * @throws ForbiddenException When a directory traversal attempt.
     * @throws MissingTemplateException When the view file could not
     *   be found and in debug mode.
     * @throws NotFoundException When the view file could not
     *   be found and not in debug mode.
     * @throws MissingTemplateException In debug mode.
     */
    public function display(...$path): ?Response {
        if (!$path) {
            return $this->redirect('/');
        }
        if (in_array('..', $path, true) || in_array('.', $path, true)) {
            throw new ForbiddenException();
        }
        $page = $subpage = null;

        if (!empty($path[0])) {
            $page = $path[0];
        }
        if (!empty($path[1])) {
            $subpage = $path[1];
        }
        $this->set(compact('page', 'subpage'));

        try {
            $this->render(implode('/', $path));
        } catch (MissingTemplateException $exception) {
            if (Configure::read('debug')) {
                throw $exception;
            }
            throw new NotFoundException();
        }

        return $this->render();
    }
}
