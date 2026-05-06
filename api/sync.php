<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = db();
$action = $_GET['action'] ?? 'push';

if ($action === 'push') {
    $pendingRecords = $db->select("SELECT * FROM sync_queue WHERE sync_status = 'pending' ORDER BY created_at LIMIT 100");
    
    foreach ($pendingRecords as $record) {
        $data = json_decode($record['data'], true);
        
        if ($record['operation'] === 'insert') {
            $db->update($record['table_name'], array_merge($data, ['sync_status' => 'synced']), 'id = :id', ['id' => $record['record_id']]);
        } elseif ($record['operation'] === 'update') {
            $db->update($record['table_name'], array_merge($data, ['sync_status' => 'synced']), 'id = :id', ['id' => $record['record_id']]);
        } elseif ($record['operation'] === 'delete') {
            $db->delete($record['table_name'], 'id = :id', ['id' => $record['record_id']]);
        }
        
        $db->update('sync_queue', ['synced_at' => date('Y-m-d H:i:s'), 'sync_status' => 'synced'], 'id = :id', ['id' => $record['id']]);
    }
    
    $db->insert('sync_logs', [
        'sync_direction' => 'push',
        'records_synced' => count($pendingRecords),
        'records_failed' => 0,
        'started_at' => date('Y-m-d H:i:s'),
        'completed_at' => date('Y-m-d H:i:s'),
        'details' => json_encode($pendingRecords)
    ]);
    
    echo json_encode(['success' => true, 'synced' => count($pendingRecords)]);
}

if ($action === 'pull') {
    $lastSync = $db->selectOne("SELECT completed_at FROM sync_logs WHERE sync_direction = 'pull' ORDER BY started_at DESC LIMIT 1");
    $since = $lastSync ? $lastSync['completed_at'] : '2024-01-01 00:00:00';
    
    $tables = ['students', 'teachers', 'attendance', 'grades', 'fees', 'messages', 'announcements'];
    $updates = [];
    
    foreach ($tables as $table) {
        $records = $db->select("SELECT * FROM $table WHERE updated_at > ? AND sync_status = 'pending'", [$since]);
        if (!empty($records)) {
            $updates[$table] = $records;
        }
    }
    
    echo json_encode(['success' => true, 'updates' => $updates, 'since' => $since]);
}

if ($action === 'status') {
    $pending = $db->count('sync_queue', "sync_status = 'pending'");
    $synced = $db->count('sync_queue', "sync_status = 'synced'");
    $failed = $db->count('sync_queue', "sync_status = 'failed'");
    
    echo json_encode([
        'pending' => $pending,
        'synced' => $synced,
        'failed' => $failed,
        'last_sync' => $db->selectOne("SELECT completed_at FROM sync_logs ORDER BY started_at DESC LIMIT 1")['completed_at'] ?? null
    ]);
}