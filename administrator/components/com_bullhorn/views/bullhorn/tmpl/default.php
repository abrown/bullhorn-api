<?php
/**
 * @version		$Id: view.html.php 21097 2011-04-07 15:38:03Z dextercowley $
 * @package		Joomla.Site
 * @subpackage	com_contact
 * @copyright	Copyright (C) 2005 - 2011 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' ); ?>
<h1>Configuration</h1>
<p style="max-width: 800px;">
    Before adding this component to your site, please add your Bullhorn account
    information with the <code style="font-size: 1.5em;">Options</code> button in
    the upper right. Then <code style="font-size: 1.5em;">Test</code> the 
    connection to ensure the information is correct.
</p>
<p style="max-width: 800px;">
    The Bullhorn API component uses SOAP to connect to the Bullhorn servers. You 
    may notice that the first request takes a long time to execute; this is due
    to the PHP SOAP client downloading various WSDL files from the Bullhorn 
    servers. To alleviate the delay, edit the <code style="font-size: 1.5em">
    php.ini</code> file with the following:
</p>
<pre style="font-size: 1.5em;">
    ; Ensure the cache is enabled
    soap.wsdl_cache_enabled=1
    ; Set a valid directory to cache the WSDL files
    soap.wsdl_cache_dir="/valid/path/to/a/cache/folder"
    ; Set a rather high TTL (time to live) for the cached files. We can safely
    ; set a high number here (a week) because Bullhorn is unlikely to change 
    ; their API soon
    soap.wsdl_cache_ttl=604800
    ; Sets a high cache limit (maximum number of WSDL files to cache). Bullhorn
    ; uses quite a few different WSDL/XSD files
    soap.wsdl_cache_limit = 60
</pre>
