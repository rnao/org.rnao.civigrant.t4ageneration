<?php

/*
  T4AGeneration CiviCRM Extension
  Copyright (C) 2013 Registered Nurses Association of Ontario

  This program is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU Affero General Public License for more details.

  You should have received a copy of the GNU Affero General Public License
  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once 't4ageneration.civix.php';

/**
 * Implementation of hook_civicrm_config
 */
function t4ageneration_civicrm_config(&$config) {
  _t4ageneration_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function t4ageneration_civicrm_xmlMenu(&$files) {
  _t4ageneration_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function t4ageneration_civicrm_install() {
  $config = CRM_Core_Config::singleton();
  $dir = $config->extensionsDir . 'org.rnao.civigrant.t4ageneration/';

  // Insert T4A template
  $template = file_get_contents($dir . 'message_templates/grant_payment_t4a_html.tpl');
  $query = "INSERT INTO civicrm_msg_template (msg_title, msg_subject, msg_text, msg_html, is_active) " .
      "VALUES ('Grant Payment T4', 'Grant Payment T4', 'Grant Payment T4', '" .
      addslashes($template) . "', 1)";
  CRM_Core_DAO::singleValueQuery($query);

  CRM_Utils_File::sourceSQLFile(CIVICRM_DSN, $dir . 't4ageneration.sql');
  return _t4ageneration_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function t4ageneration_civicrm_uninstall() {
  $config = CRM_Core_Config::singleton();
  $dir = $config->extensionsDir . 'org.rnao.civigrant.t4ageneration/';

  CRM_Utils_File::sourceSQLFile(CIVICRM_DSN, $dir . 't4ageneration.uninstall.sql');
  return _t4ageneration_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function t4ageneration_civicrm_enable() {
  return _t4ageneration_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function t4ageneration_civicrm_disable() {
  return _t4ageneration_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function t4ageneration_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _t4ageneration_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function t4ageneration_civicrm_managed(&$entities) {
  return _t4ageneration_civix_civicrm_managed($entities);
}

/**
 * Add navigation for XML generation under Grants menu
 *
 * @param $params associated array of navigation menus
 */
function t4ageneration_civicrm_navigationMenu(&$params) {
  // get the id of Administer Menu
  $grantsMenuID = CRM_Core_DAO::getFieldValue('CRM_Core_BAO_Navigation', 'Grants', 'id', 'name');
  $otherMenuID = CRM_Core_DAO::getFieldValue('CRM_Core_BAO_Navigation', 'Other', 'id', 'name');

  // skip adding menu if there is no grants or other menu
  if ($grantsMenuID && $otherMenuID) {
    // get max key
    $maxKey = max( array_keys($params[$otherMenuID]['child'][$grantsMenuID]['child']));
    $params[$otherMenuID]['child'][$grantsMenuID]['child'][$maxKey+1] =  array (
      'attributes' => array (
        'label'      => 'Generate CRA XML File',
        'name'       => 'Generate CRA XML File',
        'url'        => 'civicrm/grant/genxml',
        'permission' => 'access CiviGrant',
        'operator'   => NULL,
        'separator'  => FALSE,
        'parentID'   => $grantsMenuID,
        'navID'      => $maxKey+1,
        'active'     => 1
      )
    );

    $params[$otherMenuID]['child'][$grantsMenuID]['child'][$maxKey+2] =  array (
        'attributes' => array (
            'label'      => 'Generate T4A Forms',
            'name'       => 'Generate T4A Forms',
            'url'        => 'civicrm/grant/gent4a',
            'permission' => 'access CiviGrant',
            'operator'   => NULL,
            'separator'  => FALSE,
            'parentID'   => $grantsMenuID,
            'navID'      => $maxKey+2,
            'active'     => 1
        )
    );
  }
}
