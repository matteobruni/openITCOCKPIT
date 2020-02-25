<?php
// Copyright (C) <2019>  <it-novum GmbH>
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
$timezones = \Cake\I18n\FrozenTime::listTimezones();
?>
<ol class="breadcrumb page-breadcrumb">
    <li class="breadcrumb-item">
        <a ui-sref="DashboardsIndex">
            <i class="fa fa-link fa-fw"></i> <?php echo __('System'); ?>
        </a>
    </li>
    <li class="breadcrumb-item">
        <a ui-sref="ContainersIndex">
            <i class="fas fa-clipboard-list"></i> <?php echo __('Containers'); ?>
        </a>
    </li>
    <li class="breadcrumb-item">
        <i class="fa fa-sitemap"></i> <?php echo __('Overview'); ?>
    </li>
</ol>


<div class="row">
    <div class="col-xl-12">
        <div id="panel-1" class="panel">
            <div class="panel-hdr">
                <h2>
                    <?php echo __('Containers'); ?>
                    <span class="fw-300"><i><?php echo __('overview'); ?></i></span>
                </h2>
            </div>
            <div class="panel-container">
                <div class="panel-content">
                    <form class="form-horizontal">
                        <div class="form-group">
                            <label class="control-label">
                                <?php echo __('Container'); ?>
                            </label>
                            <select
                                data-placeholder="<?php echo __('Please choose'); ?>"
                                class="form-control"
                                chosen="containers"
                                ng-model="selectedContainer.id"
                                ng-options="container.key as container.value for container in containers">
                            </select>
                        </div>
                    </form>
                </div>
                <div class="panel-content pull-right">
                    <div class="frame-wrap margin-bottom-10">
                        <div>
                            <i class="fa fa-globe"></i>
                            <em class="padding-right-20">
                                <?php echo __('Global'); ?>
                            </em>
                            <i class="fa fa-home"></i>
                            <em class="padding-right-20">
                                <?php echo __('Tenant'); ?>
                            </em>
                            <i class="fa fa-location-arrow"></i>
                            <em class="padding-right-20">
                                <?php echo __('Location'); ?>
                            </em>
                            <i class="fa fa-link"></i>
                            <em class="padding-right-20">
                                <?php echo __('Node'); ?>
                            </em>
                            <i class="fa fa-users"></i>
                            <em class="padding-right-20">
                                <?php echo __('Contactgroup'); ?>
                            </em>
                            <i class="fa fa-server"></i>
                            <em class="padding-right-20">
                                <?php echo __('Hostgroup'); ?>
                            </em>
                            <i class="fa fa-cogs"></i>
                            <em class="padding-right-20">
                                <?php echo __('Servicegroup'); ?>
                            </em>
                            <i class="fa fa-pencil-square-o"></i>
                            <em>
                                <?php echo __('Servicetemplategroup'); ?>
                            </em>
                        </div>
                    </div>
                </div>
                <div class="panel-content">
                    <div class="row padding-top-15">
                        <div class="col col-sm-12 col-lg-12">
                            <header>
                                <h4>
                                    <i class="fa fa-link"></i>
                                    <?php echo __('Tree'); ?>
                                </h4>
                            </header>
                        </div>
                    </div>
                </div>
                <div class="panel-content">
                    <div class="row padding-top-15">

                        <div class="col col-sm-12 col-lg-12">
                            <div ng-if="subcontainers" ng-nestable ng-model="subcontainers">
                                <div>
                                    <i class="fa fa-globe"
                                       ng-if="$Container.containertype_id == <?php echo CT_GLOBAL; ?>"></i>
                                    <i class="fa fa-home"
                                       ng-if="$Container.containertype_id == <?php echo CT_TENANT; ?>"></i>
                                    <i class="fa fa-location-arrow"
                                       ng-if="$Container.containertype_id == <?php echo CT_LOCATION; ?>"></i>
                                    <i class="fa fa-link"
                                       ng-if="$Container.containertype_id == <?php echo CT_NODE; ?>"></i>
                                    <i class="fa fa-users"
                                       ng-if="$Container.containertype_id == <?php echo CT_CONTACTGROUP; ?>"></i>
                                    <i class="fa fa-sitemap"
                                       ng-if="$Container.containertype_id == <?php echo CT_HOSTGROUP; ?>"></i>
                                    <i class="fa fa-cogs"
                                       ng-if="$Container.containertype_id == <?php echo CT_SERVICEGROUP; ?>"></i>
                                    <i class="fa fa-pencil-square-o"
                                       ng-if="$Container.containertype_id == <?php echo CT_SERVICETEMPLATEGROUP; ?>"></i>

                                    <div class="nodes-container-name" title="{{ $Container.name }}">
                                        <span class="ellipsis"">{{ $Container.name }}</span>
                                    </div>

                                    <?php if ($this->Acl->hasPermission('edit', 'containers')): ?>
                                        <span ng-if="$Container.allow_edit === true">
                                            <a ng-if="$Container.containertype_id == <?php echo CT_NODE; ?> ||
                                                    $Container.containertype_id == <?php echo CT_TENANT; ?> ||
                                                    $Container.containertype_id == <?php echo CT_LOCATION; ?>"
                                               class="txt-color-red padding-left-10 font-xs pointer"
                                               ng-click="openEditNodeModal($Container)">
                                                <i class="fas fa-pencil-alt"></i>
                                                <?php echo __('Edit'); ?>
                                            </a>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($this->Acl->hasPermission('add', 'containers')): ?>
                                        <a ng-if="$Container.containertype_id == <?php echo CT_GLOBAL; ?> ||
                                            $Container.containertype_id == <?php echo CT_NODE; ?> ||
                                            $Container.containertype_id == <?php echo CT_TENANT; ?> ||
                                            $Container.containertype_id == <?php echo CT_LOCATION; ?>"
                                           class="txt-color-green padding-left-10 font-xs pointer"
                                           ng-click="openAddNodeModal($Container)">
                                            <i class="fa fa-plus"></i>
                                            <?php echo __('Add'); ?>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($this->Acl->hasPermission('showDetails', 'containers')): ?>
                                        <a ng-if="$Container.containertype_id == <?php echo CT_NODE; ?> ||
                                            $Container.containertype_id == <?php echo CT_TENANT; ?> ||
                                            $Container.containertype_id == <?php echo CT_LOCATION; ?>"
                                           class="text-info padding-left-10 font-xs pointer"
                                           ui-sref="ContainersShowDetails({id:$Container.id, tenant:selectedContainer.id})">
                                            <i class="fa fa-info"></i>
                                            <?php echo __('Show details'); ?>
                                        </a>
                                    <?php endif; ?>


                                    <i class="note pull-right"
                                       ng-if="(($Container.rght-$Container.lft)/2-0.5) == 0">
                                        <?php echo __('empty'); ?>
                                    </i>
                                    <span class="badge bg-color-blue txt-color-white pull-right"
                                          ng-if="(($Container.rght-$Container.lft)/2-0.5) > 0">{{
                                        ($Container.rght-$Container.lft)/2-0.5 }}</span>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="angularAddNodeModal" class="modal" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form onsubmit="return false;">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><?php echo __('Add new container'); ?></h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <div class="row">
                            <label class="col-xs-12 control-label">
                                <?php echo __('Select container type'); ?>
                            </label>
                            <div class="col-xs-12" ng-show="selectedContainerTypeId == <?php echo CT_GLOBAL; ?>">
                                <select class="form-control" ng-model="post.Container.containertype_id">
                                    <?php if ($this->Acl->hasPermission('add', 'tenants')): ?>
                                        <option value="<?php echo CT_TENANT; ?>">
                                            <?php echo __('Tenant'); ?>
                                        </option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-xs-12" ng-show="selectedContainerTypeId !== <?php echo CT_GLOBAL; ?>">
                                <select class="form-control" ng-model="post.Container.containertype_id">
                                    <?php if ($this->Acl->hasPermission('add', 'locations')): ?>
                                        <option value="<?php echo CT_LOCATION; ?>">
                                            <?php echo __('Location'); ?>
                                        </option>
                                    <?php endif; ?>
                                    <?php if ($this->Acl->hasPermission('add', 'containers')): ?>
                                        <option value="<?php echo CT_NODE; ?>" selected="selected">
                                            <?php echo __('Node'); ?>
                                        </option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row" ng-class="{'has-error': errors.Container.name || errors.name}"
                         ng-show="post.Container.containertype_id==5">
                        <label class="col-xs-12 control-label">
                            <?php echo __('Name'); ?>
                        </label>
                        <div class="col-xs-12">
                            <div class="form-group smart-form">
                                <label class="input"> <i class="icon-prepend fa fa-folder-open"></i>
                                    <input type="text" class="input-sm"
                                           placeholder="<?php echo __('Container name'); ?>"
                                           ng-model="post.Container.name">
                                </label>
                                <div ng-repeat="error in errors.Container.name">
                                    <div class="help-block font-xs text-danger">{{ error }}</div>
                                </div>
                                <div ng-repeat="error in errors.name">
                                    <div class="help-block font-xs text-danger">{{ error }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <fieldset class="margin-top-10" ng-show="post.Container.containertype_id==3">
                        <div class="row" ng-class="{'has-error': errors.container.name || errors.name}">
                            <label class="col-xs-12 control-label">
                                <?php echo __('Name'); ?>
                            </label>
                            <div class="col-xs-12">
                                <div class="form-group smart-form">
                                    <label class="input"> <i class="icon-prepend fa fa-folder-open"></i>
                                        <input type="text" class="input-sm"
                                               placeholder="<?php echo __('Container name'); ?>"
                                               ng-model="post.Location.container.name">
                                    </label>
                                    <div ng-repeat="error in errors.container.name">
                                        <div class="help-block font-xs text-danger">{{ error }}</div>
                                    </div>
                                    <div ng-repeat="error in errors.name">
                                        <div class="help-block font-xs text-danger">{{ error }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <legend><?php echo __('Optional fields for location'); ?></legend>
                        <div class="row">
                            <label class="col-xs-12 control-label">
                                <?php echo __('Description'); ?>
                            </label>
                            <div class="col-xs-12">
                                <div class="form-group smart-form">
                                    <label class="input"> <i class="icon-prepend fa fa-info"></i>
                                        <input type="text" class="input-sm"
                                               placeholder="<?php echo __('Description'); ?>"
                                               ng-model="post.Location.description">
                                    </label>
                                    <div ng-repeat="error in errors.name">
                                        <div class="help-block font-xs text-danger">{{ error }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="row" ng-class="{'has-error': errors.latitude}">
                                <label class="col-xs-12 control-label">
                                    <?php echo __('Latitude'); ?>
                                </label>
                                <div class="col-xs-12">
                                    <div class="form-group smart-form">
                                        <label class="input"> <i class="icon-prepend fa fa-map-marker"></i>
                                            <input type="text" class="input-sm"
                                                   placeholder="<?php echo '50.5558095'; ?>"
                                                   ng-model="post.Location.latitude">
                                        </label>
                                        <div class="info-block-helptext font-xs">
                                            <?php echo __(' Latitude must be a number between -90 and 90 degree inclusive.'); ?>
                                        </div>
                                        <div ng-repeat="error in errors.latitude">
                                            <div class="help-block font-xs text-danger">{{ error }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row" ng-class="{'has-error': errors.longitude}">
                                <label class="col-xs-12 control-label">
                                    <?php echo __('Longitude'); ?>
                                </label>
                                <div class="col-xs-12">
                                    <div class="form-group smart-form">
                                        <label class="input"> <i class="icon-prepend fa fa-map-marker"></i>
                                            <input type="text" class="input-sm"
                                                   placeholder="<?php echo '9.6808449'; ?>"
                                                   ng-model="post.Location.longitude">
                                        </label>
                                        <div class="info-block-helptext font-xs">
                                            <?php echo __('Longitude must be a number -180 and 180 degree inclusive.'); ?>
                                        </div>
                                        <div ng-repeat="error in errors.longitude">
                                            <div class="help-block font-xs text-danger">{{ error }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <label class="col-xs-12 control-label">
                                <?php echo __('Timezone'); ?>
                            </label>
                            <div class="col col-xs-12">
                                <select class="form-control"
                                        chosen="{}"
                                        ng-model="post.Location.timezone">
                                    <?php foreach ($timezones as $continent => $continentTimezons): ?>
                                        <optgroup label="<?php echo h($continent); ?>">
                                            <?php foreach ($continentTimezons as $timezoneKey => $timezoneName): ?>
                                                <option
                                                    value="<?php echo h($timezoneKey); ?>"><?php echo h($timezoneName); ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach;; ?>
                                </select>
                            </div>
                        </div>
                    </fieldset>
                    <fieldset class="margin-top-10" ng-show="post.Container.containertype_id==2">
                        <div class="row" ng-class="{'has-error': errors.container.name || errors.name}">
                            <label class="col-xs-12 control-label">
                                <?php echo __('Name'); ?>
                            </label>
                            <div class="col-xs-12">
                                <div class="form-group smart-form">
                                    <label class="input"> <i class="icon-prepend fa fa-folder-open"></i>
                                        <input type="text" class="input-sm"
                                               placeholder="<?php echo __('Container name'); ?>"
                                               ng-model="post.Tenant.container.name">
                                    </label>
                                    <div ng-repeat="error in errors.container.name">
                                        <div class="help-block font-xs text-danger">{{ error }}</div>
                                    </div>
                                    <div ng-repeat="error in errors.name">
                                        <div class="help-block font-xs text-danger">{{ error }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <legend><?php echo __('Optional fields for tenant'); ?></legend>
                        <div class="row">
                            <label class="col-xs-12 control-label">
                                <?php echo __('Description'); ?>
                            </label>
                            <div class="col-xs-12">
                                <div class="form-group smart-form">
                                    <label class="input"> <i class="icon-prepend fa fa-info"></i>
                                        <input type="text" class="input-sm"
                                               placeholder="<?php echo __('Info text ...'); ?>"
                                               ng-model="post.Tenant.description">
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <label class="col-xs-12 control-label">
                                <?php echo __('First name'); ?>
                            </label>
                            <div class="col-xs-12">
                                <div class="form-group smart-form">
                                    <label class="input"> <i class="icon-prepend fa fa-user"></i>
                                        <input type="text" class="input-sm"
                                               placeholder="<?php echo __('John'); ?>"
                                               ng-model="post.Tenant.firstname">
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <label class="col-xs-12 control-label">
                                <?php echo __('Last name'); ?>
                            </label>
                            <div class="col-xs-12">
                                <div class="form-group smart-form">
                                    <label class="input"> <i class="icon-prepend fa fa-user"></i>
                                        <input type="text" class="input-sm"
                                               placeholder="<?php echo __('Doe'); ?>"
                                               ng-model="post.Tenant.lastname">
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <label class="col-xs-12 control-label">
                                <?php echo __('Street'); ?>
                            </label>
                            <div class="col-xs-12">
                                <div class="form-group smart-form">
                                    <label class="input"> <i class="icon-prepend fa fa-road"></i>
                                        <input type="text" class="input-sm"
                                               placeholder="<?php echo __('Any street'); ?>"
                                               ng-model="post.Tenant.street">
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <label class="col-xs-12 control-label">
                                <?php echo __('Zip code'); ?>
                            </label>
                            <div class="col-xs-12">
                                <div class="form-group smart-form">
                                    <label class="input"> <i class="icon-prepend fa fa-building-o"></i>
                                        <input type="text" class="input-sm"
                                               placeholder="<?php echo __('12345'); ?>"
                                               ng-model="post.Tenant.zipcode">
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <label class="col-xs-12 control-label">
                                <?php echo __('City'); ?>
                            </label>
                            <div class="col-xs-12">
                                <div class="form-group smart-form">
                                    <label class="input"> <i class="icon-prepend fa fa-building-o"></i>
                                        <input type="text" class="input-sm"
                                               placeholder="<?php echo __('Any city'); ?>"
                                               ng-model="post.Tenant.city">
                                    </label>
                                </div>
                            </div>
                        </div>
                    </fieldset>
                </div>
                <div class="modal-footer">
                    <div class="pull-left" ng-repeat="error in errors.id">
                        <div class="help-block text-danger">{{ error }}</div>
                    </div>
                    <button type="submit" class="btn btn-primary" ng-click="saveNode()"
                            ng-show="post.Container.containertype_id==<?php echo CT_NODE; ?>">
                        <?php echo __('Create new node'); ?>
                    </button>
                    <button type="submit" class="btn btn-primary" ng-click="saveTenant()"
                            ng-show="post.Container.containertype_id==<?php echo CT_TENANT; ?>">
                        <?php echo __('Create new tenant'); ?>
                    </button>
                    <button type="submit" class="btn btn-primary" ng-click="saveLocation()"
                            ng-show="post.Container.containertype_id==<?php echo CT_LOCATION; ?>">
                        <?php echo __('Create new location'); ?>
                    </button>
                    <button type="button" class="btn btn-default" data-dismiss="modal">
                        <?php echo __('Cancel'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="angularEditNodeModal" class="modal" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form onsubmit="return false;">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><?php echo __('Edit Node'); ?></h4>
                </div>
                <div class="modal-body" ng-class="{'has-error': errors.name}">
                    <div class="row">
                        <div class="col-xs-2">
                            <label class="control-label">
                                <?php echo __('Edit node name '); ?>
                            </label>
                        </div>
                        <div class="col-xs-10">
                            <input type="text"
                                   class="form-control"
                                   maxlength="255"
                                   required="required"
                                   placeholder="<?php echo __('Node name'); ?>"
                                   ng-model="post.Container.name">
                            <div ng-repeat="error in errors.name">
                                <div class="help-block text-danger">{{ error }}</div>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger pull-left" ng-click="deleteNode()"
                            ng-class="{'has-error': errors.id}">
                        <i class="fa fa-refresh fa-spin" ng-show="isDeleting"></i>
                        <?php echo __('Delete'); ?>
                    </button>
                    <div class="pull-left" ng-repeat="error in errors.id">
                        <div class="help-block text-danger">{{ error }}</div>
                    </div>
                    <button type="submit" class="btn btn-primary" ng-click="updateNode()">
                        <?php echo __('Save'); ?>
                    </button>
                    <button type="button" class="btn btn-default" data-dismiss="modal">
                        <?php echo __('Cancel'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>