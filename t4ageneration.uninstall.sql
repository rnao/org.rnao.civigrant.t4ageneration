/* Delete option group and fields */
SELECT @id := id FROM civicrm_option_group WHERE name = 't4a_generation_settings';
DELETE FROM civicrm_option_value WHERE option_group_id = @id;
DELETE FROM civicrm_option_group WHERE id = @id;

DELETE FROM civicrm_msg_template WHERE msg_title = 'Grant Payment T4A';