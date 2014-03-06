<?php

require_once 'CRM/Core/Form.php';

/**
 * This class provides the functionality to print T4A receipts
 */
class CRM_T4Ageneration_Form_GenerateT4A extends CRM_Core_Form
{
  /**
   * Build the form
   *
   * @access public
   * @return void
   */
  function buildQuickForm()
  {
    $this->add('text', 't4a_year', ts('Year to appear on T4A slips'), null, true);
    $this->add('text', 't4a_payer', ts('Payer\'s Name'), null, true);
    $this->add('text', 't4a_box', ts('Box #'), null, true);
    $this->add('text', 't4a_min_payment', ts('Minimum payment'), null, true);

    $programs = CRM_Grant_BAO_GrantProgram::getGrantPrograms();
    // add form elements
    $element = $this->add(
      'select', // field type
      'grant_program', // field name
      ts('Grant Program'), // field label
      $programs, // list of options
      true // is required
    );
    $element->setMultiple(TRUE);

    $this->add(
      'date',
      'start_date',
      ts('Payment Start Date'),
      array (
        'maxYear' => date('Y'),
        'addEmptyOption' => TRUE,
      )
    );
    $this->add(
      'date',
      'end_date',
      ts('Payment End Date'),
      array (
        'maxYear' => date('Y'),
        'addEmptyOption' => TRUE,
      )
    );

    $this->add('text', 't4a_contact_id', ts('Contact IDs'), null);

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());

    $this->assign('helpElements', array(
      't4a_min_payment' => 1,
      't4a_contact_id' => 1,
    ));
  }

  function setDefaultValues()
  {
    $defaults = array();
    $defaults['t4a_year'] = date('Y') - 1;   // Supposedly last year?

    return $defaults;
  }

  /**
   * process the form after the input has been submitted and validated
   *
   * @access public
   * @return None
   */
  public function postProcess()
  {
    $grandTotal = 0;
    CRM_Utils_System::flushCache('CRM_Grant_DAO_GrantPayment');
    $values  = $this->controller->exportValues();
    $grantThresholds = CRM_Core_OptionGroup::values('grant_thresholds', TRUE);
    $maxLimit = $grantThresholds['Maximum number of checks per pdf file'];
    $programID = implode(',',$values['grant_program']);

    // Get payment status ID
    $params = array(
      'version' => 3,
      'sequential' => 1,
      'name' => 'grant_payment_status',
    );
    $result = civicrm_api('OptionGroup', 'get', $params);
    if ($result['is_error'] || empty($result['values'])) {
      CRM_Core_Error::fatal(ts('No "grant_payment_status" option group. Please refer to extension installation instructions.'));
    }
    $optionID = $result['values'][0]['id'];
    $params = array(
      'version' => 3,
      'sequential' => 1,
      'option_group_id' => $optionID,
      'name' => 'Printed',
    );
    $result = civicrm_api('OptionValue', 'get', $params);
    if ($result['is_error'] || empty($result['values'])) {
      CRM_Core_Error::fatal(ts('No "Printed" grant payment status option. Please refer to extension installation instructions.'));
    }
    $paidStatus[] = $result['values'][0]['value'];

    // Reprinted payment status
    $params = array(
      'version' => 3,
      'sequential' => 1,
      'option_group_id' => $optionID,
      'name' => 'Reprinted',
    );
    $result = civicrm_api('OptionValue', 'get', $params);
    if (!empty($result['values'])) {
      $paidStatus[] = $result['values'][0]['value'];
    }

    $paid = implode(',', $paidStatus);  // payment status to include
    $minAmount = $values['t4a_min_payment'];

    // Get dates
    if ($values['start_date']['d'] != '' && $values['start_date']['M'] != '' && $values['start_date']['Y'] != '' &&
        $values['end_date']['d'] != '' && $values['end_date']['M'] != '' && $values['end_date']['Y'] != '') {
      $startDate = $values['start_date']['Y'] . '-' . $values['start_date']['M'] . '-' . $values['start_date']['d'];
      $endDate = $values['end_date']['Y'] . '-' . $values['end_date']['M'] . '-' . $values['end_date']['d'];
    }

    $select = "select * from ";
    $count = "select count(1) from ";
    $query = "(select a.contact_id as id, a.currency, sum(a.amount) as total_amount from
        (select distinct p.id, p.contact_id, p.currency, p.payment_created_date, p.amount from civicrm_payment p
        inner join civicrm_entity_payment ep on p.id = ep.payment_id and ep.entity_table = 'civicrm_grant'
        inner join civicrm_grant g on ep.entity_id = g.id
        where g.grant_program_id in ($programID) and p.payment_status_id in ($paid) ";

    if (isset($startDate)) {
      $query .= "and p.payment_date >= '" . $startDate . "' and p.payment_date <= '" . $endDate . "' ";
    }

    // Filter by contact
    if (isset($values['t4a_contact_id']) && $values['t4a_contact_id'] != '') {
      $query .= "AND p.contact_id in (" . $values['t4a_contact_id'] . ') ';
    }

    $query .= ") a
        group by a.contact_id) b
        WHERE b.total_amount > $minAmount";

    $daoCount = CRM_Grant_DAO_Grant::singleValueQuery($count . $query); // Surely there's a better way of doing this?

    for ($i=0; $i<$daoCount; $i=$i+$maxLimit) {
      $dao = CRM_Grant_DAO_Grant::executeQuery($select . $query." LIMIT $i, $maxLimit");
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
          $details[$dao->id]['total_amount'] = $dao->total_amount;
        }
        $details[$dao->id]['currency'] = $dao->currency;

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

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }
}


