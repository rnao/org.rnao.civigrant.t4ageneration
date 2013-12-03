<?php

require_once 't4generation.civix.php';

/**
 * Implementation of hook_civicrm_config
 */
function t4generation_civicrm_config(&$config) {
  _t4generation_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function t4generation_civicrm_xmlMenu(&$files) {
  _t4generation_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function t4generation_civicrm_install() {
  $config = CRM_Core_Config::singleton();
  $dir = $config->extensionsDir . 'org.rnao.civigrant.t4generation/';

  // Insert T4 template
  $template = file_get_contents($dir . 'message_templates/grant_payment_t4_html.tpl');
  $query = "INSERT INTO civicrm_msg_template (msg_title, msg_subject, msg_text, msg_html, is_active) " .
      "VALUES ('Grant Payment T4', 'Grant Payment T4', 'Grant Payment T4', '" .
      addslashes($template) . "', 1)";
  CRM_Core_DAO::singleValueQuery($query);

  CRM_Utils_File::sourceSQLFile(CIVICRM_DSN, $dir . 't4generation.sql');
  return _t4generation_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function t4generation_civicrm_uninstall() {
  $config = CRM_Core_Config::singleton();
  $dir = $config->extensionsDir . 'org.rnao.civigrant.t4generation/';

  CRM_Utils_File::sourceSQLFile(CIVICRM_DSN, $dir . 't4generation.uninstall.sql');
  return _t4generation_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function t4generation_civicrm_enable() {
  return _t4generation_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function t4generation_civicrm_disable() {
  return _t4generation_civix_civicrm_disable();
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
function t4generation_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _t4generation_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function t4generation_civicrm_managed(&$entities) {
  return _t4generation_civix_civicrm_managed($entities);
}

function t4generation_civicrm_searchTasks($objectName, &$tasks) {
  if ($objectName == 'grant' && !strstr($_GET['q'], 'payment/search')
      && CRM_Core_Permission::check('create payments in CiviGrant')) {
    // Make sure this hasn't fired yet
    $key = max(array_keys($tasks));
    if ($tasks[$key]['title'] != ts('Print T4')) {
      $tasks[$key + 1] = array(
        'title' => ts('Print T4'),
        'class' => array('CRM_T4generation_Form_Task_T4'),
        'result' => FALSE,
      );
    }
  }
}

/**
 * Add navigation for XML generation under Grants menu
 *
 * @param $params associated array of navigation menus
 */
function t4generation_civicrm_navigationMenu(&$params) {
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
        'url'        => 'civicrm/grant/genxml&reset=1',
        'permission' => 'access CiviGrant',
        'operator'   => NULL,
        'separator'  => TRUE,
        'parentID'   => $grantsMenuID,
        'navID'      => $maxKey+1,
        'active'     => 1
      )
    );
  }
}
