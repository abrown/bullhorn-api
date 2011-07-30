<?php
/**
 * @package		Bullhorn
 * @version		1.0
 * @copyright           Copyright (C)2011 Andrew Brown. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('_JEXEC') or die;
?>
<ul>
    <?php if ( !$jobs ): ?>
    <li>No jobs found</li>
    <?php else: foreach($jobs as $job): ?>
    <?php 
    $url = JRoute::_('index.php?option=com_bullhorn&task=view&jobId='.$job->jobOrderID); 
    $date = date('Y-m-d', strtotime($job->dateAdded));
    ?>
    <li><a href="<?php echo $url; ?>"><?php echo $job->title; ?></a><br/>
    <?php echo $job->address->city.', '.$job->address->state.' | Posted: '.$date; ?></li>    
    <?php endforeach; endif; ?>
</ul>
