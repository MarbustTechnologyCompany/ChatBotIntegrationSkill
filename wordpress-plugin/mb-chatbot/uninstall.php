<?php
// Limpieza al desinstalar el plugin.
if (!defined('WP_UNINSTALL_PLUGIN')) exit;
delete_option('mbchat_settings');
