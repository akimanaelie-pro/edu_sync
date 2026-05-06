<?php
require_once 'config/config.php';
require_once 'config/database.php';

$db = db();

echo "<h2>Settings Debug</h2>";

// Check all settings
$settings = $db->select("SELECT * FROM settings");
echo "<h3>All Settings in Database:</h3>";
if (empty($settings)) {
    echo "<p>No settings found in database.</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Key</th><th>Value</th></tr>";
    foreach ($settings as $s) {
        echo "<tr><td>{$s['id']}</td><td>{$s['setting_key']}</td><td>{$s['setting_value']}</td></tr>";
    }
    echo "</table>";
}

// Test updateSetting
echo "<h3>Testing updateSetting('offline_mode', '1'):</h3>";
$result = $db->updateSetting('offline_mode', '1');
echo "Result: ";
var_dump($result);

$value = $db->getSetting('offline_mode');
echo "Value after update: " . var_dump($value);

echo "<p><a href='settings/'>Go back to Settings</a></p>";
