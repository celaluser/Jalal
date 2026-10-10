<?php

return [
    'title' => 'Add-ons', 'sub' => 'Extra features delivered as signed packages. They run with full access to the application, so install only add-ons from sources you trust.',
    'install' => 'Install an add-on', 'install_help' => 'Upload the .zip you received. The signature and every file are checked first. New add-ons start switched off.',
    'trust_warning' => 'An add-on can read and change everything, including your database. Back up first.', 'install_button' => 'Check and install',
    'installed_title' => 'Installed add-ons', 'installed' => ':name installed. Switch it on to start using it.', 'updated' => 'Add-on updated.', 'removed' => 'Add-on removed. Its data stays in the database.',
    'on' => 'On', 'off' => 'Off', 'turn_on' => 'Turn on', 'turn_off' => 'Turn off', 'remove_confirm' => 'Remove this add-on? Its files are deleted; its database tables stay.',
    'empty' => 'No add-ons installed', 'empty_text' => 'Add-ons you buy or build appear here.',
    'not_an_addon' => 'This package is not an add-on (every file must live in addons/<name>/).', 'invalid_manifest' => 'The add-on description (addon.json) is missing or invalid.',
    'needs_core' => 'Needs application version :version or newer.', 'provider_missing' => 'The add-on code could not be loaded.', 'not_found' => 'Add-on not found.',
];
