<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache reset successfully!";
} else {
    echo "OPcache is not enabled or function does not exist.";
}
