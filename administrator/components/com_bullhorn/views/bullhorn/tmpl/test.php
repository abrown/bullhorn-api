<h1>Test Connection</h1>
<p>
    This script will attempt to create a session with the Bullhorn servers. The
    result should be a session key:
</p>
<?php 
    if( $this->key ) echo '<p class="success">'.$this->key.'</p><p>You have connected successfully.</p>';
    else echo '<p class="error">No connection was made</p>';
?>
