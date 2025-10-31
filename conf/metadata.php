<?php

/**
 * Options for the kanboardsync plugin
 *
 * @author Thomas Schäfer <thomas@hilbershome.de>
 */

$meta['kanboard_url']       = array('string');
$meta['kanboard_user']      = array('string');
$meta['kanboard_token']     = array('string');
$meta['project_id']         = array('numeric','_min' => '1');
$meta['tasktag']            = array('string');
$meta['ssl_verifypeer']     = array('onoff');
$meta['namespace_persons']  = array('string');
$meta['namespace_roles']    = array('string');
