INSERT INTO civicrm_option_group (name, title, description, is_active)
    VALUES('t4_generation_settings', 'Grant T4 Generation Settings', 'Grant T4 Generation Settings', 1);
SET @id := LAST_INSERT_ID();

INSERT INTO civicrm_option_value (option_group_id, label, value, name, description, weight, is_active)
    VALUES(@id, 'SIN number field label', '<enter SIN custom field label>', 'SIN number field label',
           'Enter the field label so we can query it when the SIN number is required.', 1, 1);