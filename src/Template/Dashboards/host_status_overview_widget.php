<?php
// Copyright (C) <2015>  <it-novum GmbH>
//
// This file is dual licensed
//
// 1.
//  This program is free software: you can redistribute it and/or modify
//  it under the terms of the GNU General Public License as published by
//  the Free Software Foundation, version 3 of the License.
//
//  This program is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
//  You should have received a copy of the GNU General Public License
//  along with this program.  If not, see <http://www.gnu.org/licenses/>.
//
// 2.
//  If you purchased an openITCOCKPIT Enterprise Edition you can use this file
//  under the terms of the openITCOCKPIT Enterprise Edition license agreement.
//  License agreement and license key will be shipped with the order
//  confirmation.
?>
<div>
    <flippy vertical
            class="col-lg-12"
            flip="['custom:FLIP_EVENT_OUT']"
            flip-back="['custom:FLIP_EVENT_IN']"
            duration="800"
            timing-function="ease-in-out">

        <flippy-front
            class="bg-host-{{filter.Hoststatus.current_state}} bg-host-background-icon bg-host-front-{{filter.Hoststatus.current_state}} fixFlippy">
            <a href="javascript:void(0);" class="btn btn-default btn-xs txt-color-blueDark" ng-click="showConfig()">
                <i class="fa fa-cog fa-sm"></i>
            </a>
            <div class="padding-5" style="font-size:{{fontSize}}px;">
                <?php if ($this->Acl->hasPermission('index', 'hosts')): ?>
                    <a ng-click="goToState()" class="pointer">
                        <div class="row text-center">
                            <div class="col col-lg-12 txt-color-white">
                                {{ statusCount | number }}
                            </div>
                        </div>
                    </a>
                <?php else: ?>
                    <div class="row text-center">
                        <div class="col col-lg-12 txt-color-white">
                            {{ statusCount | number }}
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </flippy-front>
        <flippy-back class="fixFlippy">
            <a href="javascript:void(0);" class="btn btn-default btn-xs txt-color-blueDark" ng-click="hideConfig()">
                <i class="fa fa-eye fa-sm"></i>
            </a>
            <div class="padding-top-10">
                <div class="row">
                    <div class="col-xs-12 col-lg-12 margin-bottom-5">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="icon-prepend fa fa-desktop"></i></span>
                            </div>
                            <input type="text" class="form-control"
                                   placeholder="<?php echo __('Filter by host name'); ?>"
                                   ng-model="filter.Host.name"
                                   ng-model-options="{debounce: 500}">
                        </div>
                    </div>
                </div>
                <div class="row padding-top-10">
                    <div class="col-xs-12 col-lg-6">
                        <h4><?php echo __('Host status'); ?></h4>
                        <div class="custom-control custom-radio custom-control-left margin-right-10">
                            <input type="radio"
                                   class="custom-control-input"
                                   id="widget-radio0-{{widget.id}}"
                                   ng-value="0"
                                   ng-model="filter.Hoststatus.current_state"
                                   ng-model-options="{debounce: 500}">
                            <label class="custom-control-label custom-control-label-up"
                                   for="widget-radio0-{{widget.id}}">
                                <?php echo __('Up'); ?>
                            </label>
                        </div>
                        <div class="custom-control custom-radio custom-control-left margin-right-10">
                            <input type="radio"
                                   class="custom-control-input"
                                   id="widget-radio1-{{widget.id}}"
                                   ng-value="1"
                                   ng-model="filter.Hoststatus.current_state"
                                   ng-model-options="{debounce: 500}">
                            <label class="custom-control-label custom-control-label-down"
                                   for="widget-radio1-{{widget.id}}">
                                <?php echo __('Down'); ?>
                            </label>
                        </div>
                        <div class="custom-control custom-radio custom-control-left margin-right-10">
                            <input type="radio"
                                   class="custom-control-input"
                                   id="widget-radio2-{{widget.id}}"
                                   ng-value="2"
                                   ng-model="filter.Hoststatus.current_state"
                                   ng-model-options="{debounce: 500}">
                            <label class="custom-control-label custom-control-label-unreachable"
                                   for="widget-radio2-{{widget.id}}">
                                <?php echo __('Unreachable'); ?>
                            </label>
                        </div>
                    </div>

                    <div class="col-xs-12 col-sm-6" ng-show="filter.Hoststatus.current_state > 0">
                        <h4><?php echo __('Properties'); ?></h4>

                        <div class="form-group">
                            <div class="custom-control custom-checkbox custom-control-left margin-right-10">
                                <input type="checkbox"
                                       class="custom-control-input"
                                       ng-true-value="1"
                                       ng-false-value="0"
                                       id="not_acknowledged_{{widget.id}}"
                                       ng-model="filter.Hoststatus.not_acknowledged"
                                       ng-model-options="{debounce: 500}">
                                <label class="custom-control-label" for="not_acknowledged_{{widget.id}}">
                                    <?php echo __('Not Acknowledged'); ?>
                                </label>
                            </div>

                            <div class="custom-control custom-checkbox custom-control-left margin-right-10">
                                <input type="checkbox"
                                       class="custom-control-input"
                                       ng-true-value="1"
                                       ng-false-value="0"
                                       id="not_in_downtime_{{widget.id}}"
                                       ng-model="filter.Hoststatus.not_in_downtime"
                                       ng-model-options="{debounce: 500}">
                                <label class="custom-control-label" for="not_in_downtime_{{widget.id}}">
                                    <?php echo __('Not in Downtime'); ?>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-12">
                        <button class="btn btn-primary float-right" ng-click="saveHoststatusOverview()">
                            <?php echo __('Save'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </flippy-back>
    </flippy>
</div>
