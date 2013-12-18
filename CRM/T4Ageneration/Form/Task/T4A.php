<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.1                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2011                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2011
 * $Id$
 *
 */

require_once 'CRM/Grant/Form/Task.php';

/**
 * This class provides the functionality to print T4A receipts
 */
class CRM_T4Ageneration_Form_Task_T4A extends CRM_Grant_Form_Task
{
  /**
   * build all the data structures needed to build the form
   *
   * @return void
   * @access public
   */
  function preProcess()
  {
    parent::preProcess();

    if (!CRM_Core_Permission::checkActionPermission('CiviGrant', CRM_Core_Action::PAY)) {
      CRM_Core_Error::fatal(ts('You do not have permission to access this page'));
    }

    $sinID = CRM_T4Ageneration_Form_GenerateXML::returnCustomFieldID();

    require_once "CRM/Core/PseudoConstant.php";
    require_once 'CRM/Core/OptionGroup.php';
    $grantStatus = CRM_Core_OptionGroup::values('grant_status', TRUE);

    $paidGrants = array();
    CRM_Core_PseudoConstant::populate($paidGrants, 'CRM_Grant_DAO_Grant', true, 'status_id', false,
        " id in (".implode (', ' , $this->_grantIds).") AND status_id = {$grantStatus['Paid']}");

    $this->_paidGrants = $paidGrants;
    $this->_notPaid = count($this->_grantIds) - count($this->_paidGrants);

    foreach ($paidGrants as $key => $value) {
      $grantProgram = new CRM_Grant_DAO_Grant();
      $grantArray =  array('id' => $key);
      $grantProgram->copyValues($grantArray);
      $grantProgram->find(true);
      $currencyDetails[$grantProgram->contact_id][$grantProgram->currency] = $key;
    }
    $curency = 0;
    if (!empty($currencyDetails)) {
      foreach ($currencyDetails as $key => $value) {
        if (count($value) > 1) {
          foreach ($value as $unsetKey => $unsetVal) {
            unset($paidGrants[$unsetVal]);
            $curency++;
          }
        }
      }
      $this->_curency = $curency;
    }
    $this->_paidGrants = $paidGrants;
  }

  function setDefaultValues()
  {
    $defaults = array();
    $defaults['t4a_year'] = date('Y') - 1;   // Supposedly last year?

    return $defaults;
  }

  /**
   * Build the form
   *
   * @access public
   * @return void
   */
  function buildQuickForm()
  {
    $message = "";
    if (count($this->_paidGrants)) {
      if ($this->_notPaid) {
        $message = 'Some of the selected grants have not been paid. ';
      }
      if ($this->_curency) {
        $message .=  $this->_curency.' of '.count($this->_grantIds).' grants have different currency of same user. ';
      }
      if (count($this->_paidGrants)) {
        $message .= 'Would you like to proceed to printing T4A forms for '.count($this->_paidGrants).' paid grants?';
        CRM_Core_Session::setStatus(ts($message), NULL, 'no-popup');
      }

      $this->add('text', 't4a_year', ts('Year to appear on T4A slips'), null, true);
      $this->add('text', 't4a_payer', ts('Payer\'s Name'), null, true);
      $this->add('text', 't4a_box', ts('Box #'), null, true);
      $this->addButtons(array(
        array (
          'type' => 'next',
          'name' => ts('Continue >>'),
          'spacing' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',
          'isDefault' => true,
        ),
        array (
          'type' => 'cancel',
          'name' => ts('Cancel'),
        ),
      ));
    } else {
        CRM_Core_Session::setStatus(ts('Please select at least one grant that has been paid.'), NULL, 'error');
        $this->addButtons(array(
          array (
            'type' => 'cancel',
            'name' => ts('Cancel')),
          )
        );
      }
  }

  /**
   * process the form after the input has been submitted and validated
   *
   * @access public
   * @return None
   */
  public function postProcess()
  {
    $details = $allGrants = $grantPayments = $grantAmount = array();
    $grandTotal = 0;
    CRM_Utils_System::flushCache('CRM_Grant_DAO_GrantPayment');
    $values  = $this->controller->exportValues($this->_name);
    $grantThresholds = CRM_Core_OptionGroup::values('grant_thresholds', TRUE);
    $maxLimit = $grantThresholds['Maximum number of checks per pdf file'];

    $query = "SELECT id as grant_id, amount_granted as total_amount, currency, grant_program_id, grant_type_id, contact_id as id
      FROM civicrm_grant WHERE id IN (".implode(', ', array_keys($this->_paidGrants)).")";
    $countQuery = "SELECT COUNT(id) as grant_id FROM civicrm_grant WHERE id IN (".implode(', ', array_keys($this->_paidGrants)).")";

    $daoCount = CRM_Grant_DAO_Grant::singleValueQuery($countQuery);
    for ($i=0; $i<$daoCount; $i=$i+$maxLimit) {
      $dao = CRM_Grant_DAO_Grant::executeQuery($query." LIMIT $i, $maxLimit");
      $grantPayment = $payment_details = $amountsTotal = $details = array();
      while($dao->fetch()) {
        if (isset($amountsTotal[$dao->id])) {
          $amountsTotal[$dao->id] += $dao->total_amount;
        }
        else {
          $amountsTotal[$dao->id] = $dao->total_amount;
        }

        // Aggregate payments per contact id
        if (!empty($details[$dao->id]['total_amount'])) {
          $details[$dao->id]['total_amount'] += $dao->total_amount;
        } else {
          $details[$dao->id]['total_amount']  = $dao->total_amount;
        }
        $details[$dao->id]['currency']        = $dao->currency;

        $contactGrants[$dao->grant_id] = $dao->id;

      }
      $totalAmount = 0;
      foreach ($details as $id => $value) {
        $grantPayment[$id]['contact_id'] = $id;
        $grantPayment[$id]['t4a_year'] = $values['t4a_year'];
        $grantPayment[$id]['first_name'] = $this->getFirstName($id);
        $grantPayment[$id]['last_name'] = $this->getLastName($id);
        $grantPayment[$id]['payable_to_address'] =
            CRM_Utils_Array::value('address', CRM_Grant_BAO_GrantProgram::getAddress($id, NULL, true));
        $grantPayment[$id]['amount']  = $details[$id]['total_amount'];
        $grantPayment[$id]['payer'] = $values['t4a_payer'];
        $grantPayment[$id]['box'] = $values['t4a_box'];

        // Get contact's SIN
        $sinID = CRM_T4Ageneration_Form_GenerateXML::returnCustomFieldID();
        $params = array('entityID' => $id, 'custom_' . $sinID => 1);
        $sinResult = CRM_Core_BAO_CustomValueTable::getValues($params);
        // Insert spaces in SIN
        $sinArray = str_split($sinResult['custom_' . $sinID], 3);
        $grantPayment[$id]['sin'] = implode(' ', $sinArray);

        $totalAmount += $details[$id]['total_amount'];
      }

      $grandTotal += $totalAmount;
      $downloadNamePDF  =  check_plain('T4');
      $downloadNamePDF .= '_'.date('Ymdhis');
      $this->assign('grantPayment', $grantPayment);
      $downloadNamePDF .= '.pdf';
      $fileName = CRM_Utils_File::makeFileName($downloadNamePDF);
      $files[] = $fileName = $this->makePDF($fileName, $grantPayment, 'Grant Payment T4');
    }
    $config = CRM_Core_Config::singleton();

    $fileDAO =& new CRM_Core_DAO_File();
    $fileDAO->uri           = $fileName;
    $fileDAO->mime_type = 'application/zip';
    $fileDAO->upload_date   = date('Ymdhis');
    $fileDAO->save();
    $grantPaymentFile = $fileDAO->id;

    $entityFileDAO =& new CRM_Core_DAO_EntityFile();
    $entityFileDAO->entity_table = 'civicrm_contact';
    $entityFileDAO->entity_id    = $_SESSION[ 'CiviCRM' ][ 'userID' ];
    $entityFileDAO->file_id      = $grantPaymentFile;
    $entityFileDAO->save();

    //make Zip
    $zipFile  =  check_plain('T4').'_'.date('Ymdhis').'.zip';
    foreach($files as $file) {
      $source[] = $config->customFileUploadDir.$file;
    }
    $zip = CRM_Financial_BAO_ExportFormat::createZip($source, $config->customFileUploadDir.$zipFile);

    foreach($source as $sourceFile) {
      unlink($sourceFile);
    }

    CRM_Core_Session::setStatus(ts('T4s have been generated.'), NULL, 'no-popup');

    $directory = strstr($config->customFileUploadDir, 'sites');
    global $base_url;
    $filePath = $base_url . '/' . $directory . $zipFile;
    $this->assign('download', $filePath);

    // Redirect to XML file generation. Maybe redirecting back to the grant search page would be best,
    // but we'd need a way to trigger the download ideally without overloading any templates
    CRM_Utils_System::redirect(CRM_Utils_System::url( 'civicrm/grant/t4adownload', 'reset=1&download='.$zipFile));

    parent::postProcess();
  }

  public function getFirstName($id) {
    $sql = "SELECT first_name FROM civicrm_contact WHERE civicrm_contact.id = $id ";
    return CRM_Core_DAO::singleValueQuery($sql);
  }

  public function getLastName($id) {
    $sql = "SELECT last_name FROM civicrm_contact WHERE civicrm_contact.id = $id ";
    return CRM_Core_DAO::singleValueQuery($sql);
  }

  /**
   * Lifted from CRM_Grant_BAO_GrantPayment.
   * Made generic to accept any template name.
   *
   * @param $fileName
   * @param $rows
   * @return mixed
   */
  static function makePDF($fileName, $rows, $template = 'Grant Payment T4') {
    $config = CRM_Core_Config::singleton();
    $pdf_filename = $config->customFileUploadDir . $fileName;
    $query = "SELECT msg_subject subject, msg_html html, msg_text text, pdf_format_id format
              FROM civicrm_msg_template
              WHERE msg_title = '" . $template . "' AND is_default = 1;";
    $grantDao = CRM_Core_DAO::executeQuery($query);
    $grantDao->fetch();

    if (!$grantDao->N) {
      if ($params['messageTemplateID']) {
        CRM_Core_Error::fatal(ts('No such message template.'));
      }
    }
    $subject = $grantDao->subject;
    $html = $grantDao->html;
    $text = $grantDao->text;
    $format = $grantDao->format;
    $grantDao->free();

    civicrm_smarty_register_string_resource();
    $smarty = CRM_Core_Smarty::singleton();
    foreach(array('text', 'html') as $elem) {
      $$elem = $smarty->fetch("string:{$$elem}");
    }

    $output = file_put_contents(
      $pdf_filename,
      CRM_Utils_PDF_Utils::html2pdf(
        $html,
        $fileName,
        TRUE,
        $format
      )
    );
    return $fileName;
  }
}
