<?php
require_once 'config/config.php';
require_once 'config/database.php';

$db = db();

echo "<h2>Settings Debug</h2>";

$settings = $db->select("SELECT * FROM settings");
echo "<h3>All Settings:</h3>";
echo "<pre>";
print_r($settings);
echo "</pre>";

echo "<h3>Offline Mode Setting:</h3>";
$offline = $db->getSetting('offline_mode', 'NOT_FOUND');
echo "offline_mode = " . var_export($offline, true) . "<br>";

echo "<p><a href='settings/'>Go back to Settings</a></p>";
