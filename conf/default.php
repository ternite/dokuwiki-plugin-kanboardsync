<?php
/**
 * Default settings for the kanboardsync plugin
 *
 * @author Thomas Schäfer <thomas@hilbershome.de>
 */

$conf['kanboard_url']      = '<KANBOARD_URL>/kanboard/jsonrpc.php';
$conf['kanboard_user']     = 'jsonrpc'; // first entry: odt file, second entry: MS Office file
$conf['kanboard_token']    = ''; // needs to be set to die API key provided within Kanboard->Settings->API
$conf['project_id']        = 1;
$conf['tasktag']           = "task";
$conf['ssl_verifypeer']    = true;
$conf['namespace_persons'] = '';
$conf['namespace_roles']   = '';

