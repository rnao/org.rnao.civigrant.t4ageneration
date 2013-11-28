<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_T4generation_Form_GenerateXML extends CRM_Core_Form {

  function buildQuickForm() {
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
      switch (CRM_Core_PseudoConstant::countryIsoCode(
        $result['values'][0]['domain_address']['country_id']
      )) {
        case 'CA':
          $country = "CAN";
          break;
        case 'US':
          $country = "USA";
          break;
        default:
          $country = CRM_Core_PseudoConstant::countryIsoCode($result['values'][0]['domain_address']['country_id']);
      }
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
    ));

    // Validation
    $this->addRule('min_amount', ts('Please enter a valid amount.'), 'money');
    $this->addRule('contact_ext', ts('Please enter a numeric phone extension.'), 'integer');
    $this->addRule('contact_area_code', ts('Please enter a numeric phone area code.'), 'integer');

    $this->registerRule('phoneNumberMain', 'callback', 'validatePhoneNumber', 'CRM_T4generation_Form_GenerateXML');
    $this->addRule('contact_phone', ts('Please enter a phone number formatted as xxx-xxxx.'), 'phoneNumberMain');
    $this->registerRule('business_number', 'callback', 'validateBN', 'CRM_T4generation_Form_GenerateXML');
    $this->addRule('business_number', ts('Please enter a valid business number.'), 'business_number');

    parent::buildQuickForm();
  }

  function postProcess() {
    $values = $this->exportValues();
    CRM_Core_Session::setStatus(ts('Done!'));
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
}
