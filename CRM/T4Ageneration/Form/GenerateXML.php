<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_T4Ageneration_Form_GenerateXML extends CRM_Core_Form {

  function buildQuickForm() {
    // Check if SIN custom field has been set and valid
    $sinID = $this->returnCustomFieldID();

    $programs = CRM_Grant_BAO_GrantProgram::getGrantPrograms();
    // add form elements
    $this->add(
      'select', // field type
      'grant_program', // field name
      ts('Grant Program'), // field label
      $programs, // list of options
      true // is required
    );
    $this->add(
      'text', // field type
      'min_amount', // field name
      ts('Minimum granted amount'), // field label
      array('size' => 5),
      true // is required
    );
    $this->add(
      'text', // field type
      'business_number', // field name
      ts('Business number'), // field label
      array('size' => 15, 'maxlength' => 15),
      true // is required
    );
    $this->add(
      'text', // field type
      'payer_name_1', // field name
      ts('Payer name - line 1'), // field label
      array('size' => 30, 'maxlength' => 30),
      true // is required
    );
    $this->add(
      'text',
      'payer_name_2',
      ts('Payer name - line 2'),
      array('size' => 30, 'maxlength' => 30)
    );
    $this->add(
      'text',
      'payer_name_3',
      ts('Payer name - line 3'),
      array('size' => 30, 'maxlength' => 30)
    );
    $this->add(
      'text',
      'payer_addr_1',
      ts('Payer address - line 1'),
      array('size' => 30, 'maxlength' => 30)
    );
    $this->add(
      'text',
      'payer_addr_2',
      ts('Payer address - line 2'),
      array('size' => 30, 'maxlength' => 30)
    );
    $this->add(
      'text',
      'payer_city',
      ts('Payer city'),
      array('size' => 28, 'maxlength' => 28)
    );
    $this->add(
      'text',
      'payer_province',
      ts('Payer province or territory code'),
      array('size' => 2, 'maxlength' => 2)
    );
    $this->add(
      'text',
      'payer_country',
      ts('Payer country code'),
      array('size' => 3, 'maxlength' => 3)
    );
    $this->add(
      'text',
      'payer_postal',
      ts('Payer postal code'),
      array('size' => 10, 'maxlength' => 10)
    );
    $this->add(
      'text',
      'contact_name',
      ts('Contact name'),
      array('size' => 22, 'maxlength' => 22),
      true
    );
    $this->add(
      'text',
      'contact_area_code',
      ts('Contact phone area code'),
      array('size' => 3, 'maxlength' => 3),
      true
    );
    $this->add(
      'text',
      'contact_phone',
      ts('Contact phone (xxx-xxxx)'),
      array('size' => 8, 'maxlength' => 8),
      true
    );
    $this->add(
      'text',
      'contact_ext',
      ts('Contact Extension'),
      array('size' => 5, 'maxlength' => 5)
    );
    $this->add(
      'text',
      'tax_year',
      ts('Taxation year'),
      array('size' => 4, 'maxlength' => 4),
      true
    );
    $this->add(
      'checkbox',
      'inc_address',
      ts('Include recipient\'s address?')
    );
    $this->add(
      'hidden',
      'sin_custom_field'
    );

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    // which elements have help
    $this->assign('helpElements', array(
      'min_amount' => 1,
      'business_number' => 1,
      'payer_name_1' => 1,
      'payer_name_3' => 1,
      'payer_province' => 1,
      'payer_country' => 1,
    ));

    $params = array(
      'version' => 3,
      'sequential' => 1,
    );
    $result = civicrm_api('Domain', 'get', $params);

    if ($result['is_error'] == 0 and isset($result['values'][0])) {
      $country = $this->getCountryISO($result['values'][0]['domain_address']['country_id']);

      $this->setDefaults(array(
        'payer_name_1' => $result['values'][0]['name'],
        'payer_addr_1' => $result['values'][0]['domain_address']['street_address'],
        'payer_addr_2' => $result['values'][0]['domain_address']['supplemental_address_1'],
        'payer_city' => $result['values'][0]['domain_address']['city'],
        'payer_postal' => $result['values'][0]['domain_address']['postal_code'],
        'payer_province' => CRM_Core_PseudoConstant::stateProvinceAbbreviation(
              $result['values'][0]['domain_address']['state_province_id']
            ),
        'payer_country' => $country
      ));
    }

    // Get current contact info to prefill their details
    $session = CRM_Core_Session::singleton();
    $contactID = $session->get('userID');
    $params = array(
      'version' => 3,
      'sequential' => 1,
      'id' => $contactID,
    );
    $result = civicrm_api('Contact', 'get', $params);

    if ($result['is_error'] == 0 && !empty($result['values'])) {
      if (isset($result['values'][0]['first_name']) && isset($result['values'][0]['last_name'])) {
        $this->setDefaults(array(
          'contact_name' => $result['values'][0]['first_name'] . ' ' . $result['values'][0]['last_name'],
        ));
      }
      if (isset($result['values'][0]['phone']) && strlen($result['values'][0]['phone']) == 10 &&
          is_numeric($result['values'][0]['phone'])) {
        // Attempt phone breakdown
        $areacode = substr($result['values'][0]['phone'], 0, 3);
        $main = substr($result['values'][0]['phone'], 3, 3) . '-' .
            substr($result['values'][0]['phone'], 6);
        $this->setDefaults(array(
          'contact_area_code' => $areacode,
          'contact_phone' => $main,
        ));
      }
    }

    $this->setDefaults(array(
      'grant_program' => max(array_keys($programs)), // presumably use last grant program by default?
      'tax_year' => date('Y') - 1,  // most often last year (if prepared in January)
      'inc_address' => 1,
      'sin_custom_field' => $sinID,
    ));

    // Validation
    $this->addRule('min_amount', ts('Please enter a valid amount.'), 'money');
    $this->addRule('contact_ext', ts('Please enter a numeric phone extension.'), 'integer');
    $this->addRule('contact_area_code', ts('Please enter a numeric phone area code.'), 'integer');

    $this->registerRule('phoneNumberMain', 'callback', 'validatePhoneNumber', 'CRM_T4Ageneration_Form_GenerateXML');
    $this->addRule('contact_phone', ts('Please enter a phone number formatted as xxx-xxxx.'), 'phoneNumberMain');
    $this->registerRule('business_number', 'callback', 'validateBN', 'CRM_T4Ageneration_Form_GenerateXML');
    $this->addRule('business_number', ts('Please enter a valid business number.'), 'business_number');

    parent::buildQuickForm();
  }

  function postProcess() {
    $values = $this->exportValues();

    // Get paid status ID
    $params = array(
      'version' => 3,
      'sequential' => 1,
      'name' => 'grant_status',
    );
    $result = civicrm_api('OptionGroup', 'get', $params);
    if ($result['is_error'] || empty($result['values'])) {
      CRM_Core_Error::fatal(ts('No "grant_status" option group. Please refer to extension installation instructions.'));
    }
    $optionID = $result['values'][0]['id'];
    $params = array(
      'version' => 3,
      'sequential' => 1,
      'option_group_id' => $optionID,
      'name' => 'Paid',
    );
    $result = civicrm_api('OptionValue', 'get', $params);
    if ($result['is_error'] || empty($result['values'])) {
      CRM_Core_Error::fatal(ts('No "Paid" grant status option. Please refer to extension installation instructions.'));
    }
    $paidStatus = $result['values'][0]['value'];
    $programID = $values['grant_program'];
    $minAmount = $values['min_amount'];

    // Get column name
    $params = array(
      'version' => 3,
      'sequential' => 1,
      'id' => $values['sin_custom_field'],
    );
    $result = civicrm_api('CustomField', 'get', $params);

    if ($result['count'] == 0) {  // Something has gone terrible wrong
      CRM_Core_Error::fatal(ts('Make sure the T4AGeneration extension is configured correctly.'));
    }
    $column = $result['values'][0]['column_name'];

    // Get table name
    $params = array(
      'version' => 3,
      'sequential' => 1,
      'id' => $result['values'][0]['custom_group_id'],
    );
    $result = civicrm_api('CustomGroup', 'get', $params);
    $table = $result['values'][0]['table_name'];

    $query = "SELECT sum(g.amount_granted) as total, c.first_name, c.middle_name, c.last_name, " .
      "i.$column as sin, g.contact_id " .
      "FROM civicrm_grant g " .
      "INNER JOIN civicrm_contact c ON g.contact_id = c.id " .
      "LEFT JOIN $table i ON g.contact_id = i.entity_id " .
      "WHERE g.grant_program_id = $programID and g.status_id = $paidStatus and g.amount_granted >= $minAmount " .
      "GROUP by g.contact_id";

    $dao = CRM_Core_DAO::executeQuery($query);

    $config = CRM_Core_Config::singleton();

    // Build XML
    $xml = '<?xml version="1.0" encoding="UTF-8"?><Return></Return>';
    $craFile = new SimpleXMLElement($xml);

    $totalSlips = 0;  // count slips
    $totalAmount = 0; // sum total amount
    $t4 = $craFile->addChild('T4A');

    while ($dao->fetch()) {
      $totalSlips++;
      $totalAmount += $dao->total;

      $slip = $t4->addChild('T4ASlip');
      $recipient = $slip->addChild('RCPNT_NM');
      $recipient->addChild('snm', $dao->last_name);
      $recipient->addChild('gvn_nm', $dao->first_name);
      if (isset($dao->middle_name)) {
        $recipient->addChild('init', substr($dao->middle_name, 0, 1));
      }

      $slip->addChild('sin', isset($dao->sin) ? $this->cleanSIN($dao->sin) : '000000000');
      $slip->addChild('rcpnt_bn', '000000000RP0000');
      $slip->addChild('RCPNT_CORP_NM');

      if (isset($values['inc_address']) && $values['inc_address']) {
        // Get recipient address
        $params = array(
          'version' => 3,
          'sequential' => 1,
          'contact_id' => $dao->contact_id,
          'is_primary' => 1,
        );
        $result = civicrm_api('Address', 'get', $params);
        if ($result['is_error'] == 0 && !empty($result['values'])) {
          $addr = $slip->addChild('RCPNT_ADDR');
          $this->addChild($addr, 'addr_l1_txt', $result['values'][0]['street_address']);
          $this->addChild($addr, 'cty_nm', $result['values'][0]['city']);
          if (isset($result['values'][0]['state_province_id'])) {
            $this->addChild($addr, 'prov_cd', CRM_Core_PseudoConstant::stateProvinceAbbreviation(
                $result['values'][0]['state_province_id']
            ));
          }

          if (isset($result['values'][0]['country_id'])) {
            $country = $this->getCountryISO($result['values'][0]['country_id']);
          }
          $this->addChild($addr, 'cntry_cd', $country);

          if (isset($result['values'][0]['postal_code'])) {
            $this->addChild($addr, 'pstl_cd', $result['values'][0]['postal_code']);
          }
        }
      }

      $slip->addChild('bn', $values['business_number']);
      $slip->addChild('rpt_tcd', 'O');

      $other = $slip->addChild('OTH_INFO');
      $other->addChild('brsy_amt', $dao->total);
    }

    $summary = $t4->addChild('T4ASummary');
    $summary->addChild('bn', $values['business_number']);

    $payer = $summary->addChild('PAYR_NM');
    $payer->addChild('l1_nm', $values['payer_name_1']);
    $this->addChild($payer, 'l2_nm', $values['payer_name_2']);
    $this->addChild($payer, 'l3_nm', $values['payer_name_3']);

    $payerAddr = $summary->addChild('PAYR_ADDR');
    $this->addChild($payerAddr, 'addr_l1_txt', $values['payer_addr_1']);
    $this->addChild($payerAddr, 'addr_l2_txt', $values['payer_addr_2']);
    $this->addChild($payerAddr, 'cty_nm', $values['payer_city']);
    $this->addChild($payerAddr, 'prov_cd', $values['payer_province']);
    $this->addChild($payerAddr, 'cntry_cd', $values['payer_country']);
    $this->addChild($payerAddr, 'pstl_cd', $values['payer_postal']);

    $contact = $summary->addChild('CNTC');
    $contact->addChild('cntc_nm', $values['contact_name']);
    $contact->addChild('cntc_area_cd', $values['contact_area_code']);
    $contact->addChild('cntc_extn_nbr', $values['contact_phone']);
    $this->addChild($contact, 'cntc_extn_nbr', $values['contact_ext']);

    $summary->addChild('tx_yr', $values['tax_year']);
    $summary->addChild('slp_cnt', $totalSlips);
    $summary->addChild('rpt_tcd', 'O');

    $totals = $summary->addChild('T4A_TAMT');
    $totals->addChild('rpt_tot_oth_info_amt', $totalAmount);

    $fileName = 'CRA_Grants_' . date('Ymdhis') . '.xml';

    $output = $craFile->asXML($config->customFileUploadDir . $fileName);
    CRM_Core_Session::setStatus(ts('Done!'));
    $directory = strstr($config->customFileUploadDir, 'sites');
    global $base_url;
    $filePath = $base_url . '/' . $directory . $fileName;
    $this->assign('download', $filePath);

    parent::postProcess();
  }

  /**
   * Make sure we're given a phone number formatted as xxx-xxxx.
   *
   * @param $input
   * @return boolean
   */
  function validatePhoneNumber($input) {
    if (preg_match('/[0-9]{3}-[0-9]{4}/', $input)) {
      return true;
    } else {
      return false;
    }
  }

  /**
   * Validate business number according to CRA requirements.
   *
   * @param $input
   * @return boolean
   */
  function validateBN($input) {
    if (preg_match('/[0-9]{9}RP[0-9]{4}/', $input)) {
      return true;
    } else {
      return false;
    }
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

  /**
   * Remove any dashes and spaces
   *
   * @param $sin
   * @return string
   */
  function cleanSIN($sin) {
    return preg_replace('/[ -]/', '', $sin);
  }

  /**
   * Helper function that checks if value exist or is blank before adding it as a child
   * to the given XML section
   *
   * @param $section object     XML parent to which we're adding children
   * @param $name    string     Name of child
   * @param $value   string     Value of child
   */
  function addChild(&$section, $name, $value) {
    if (isset($value) && $value != '') {
      $child = $section->addChild($name);
      // Adding the value separately instead of using addChild(name, value)
      // fixes an issue with "&" not being escaped.
      $child->value = $value;
    }
  }

  function getCountryISO($country) {
    if ($country == null) {
      return null;
    }

    switch (CRM_Core_PseudoConstant::countryIsoCode($country)) {
      case 'CA':
        $ret = "CAN";
        break;
      case 'US':
        $ret = "USA";
        break;
      default:
        $ret = CRM_Core_PseudoConstant::countryIsoCode($country);
    }

    return $ret;
  }

  /**
   * Returns the SIN custom field ID.
   * Errors out if this has not been configured.
   *
   * @return int The SIN custom field ID
   */
  static function returnCustomFieldID() {
    $params = array(
      'version' => 3,
      'sequential' => 1,
      'name' => 'SIN number field label',
    );
    $result = civicrm_api('OptionValue', 'get', $params);
    if ($result['count'] == 0) {
      CRM_Core_Error::fatal('The extension doesn\'t appear to be installed correctly. Please re-install.');
    }

    $url = CRM_Utils_System::url( 'civicrm/admin/optionValue', 'reset=1&gid=' .
      $result['values'][0]['option_group_id']);

    if ($result['values'][0]['value'] == '<enter SIN custom field label>') {  // don't bother checking
      CRM_Core_Error::fatal('The extension has not been properly configured.<br />' .
        'Please <a href="' . $url . '">set the SIN custom field label</a>.');
    }

    // Get ID
    $params = array(
      'version' => 3,
      'sequential' => 1,
      'label' => $result['values'][0]['value'],
    );
    $result = civicrm_api('CustomField', 'get', $params);

    if ($result['count'] == 0) {
      CRM_Core_Error::fatal('The SIN custom field value set is incorrect.<br />' .
        'Please <a href="' . $url . '">set the SIN custom field label</a>.');
    }

    return $result['values'][0]['id'];
  }
}
