<?php

require_once 'CRM/Core/Page.php';

class CRM_T4Ageneration_Page_T4ADownload extends CRM_Core_Page {
  function run() {
    $this->_download = CRM_Utils_Request::retrieve('download', 'String', $this, FALSE);

    if ($this->_download) {
      global $base_url;
      $config = CRM_Core_Config::singleton();
      $directory = strstr($config->customFileUploadDir, 'sites');
      $file_name = $base_url . '/' . $directory . $this->_download;
      $this->assign('download', $file_name);
    }

    $this->assign('backLink', CRM_Utils_System::url( 'civicrm/grant/search', 'reset=1'));
    parent::run();
  }
}
