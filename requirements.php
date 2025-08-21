<?php
header('Content-Type: application/json');

$requirements = [
  [
    'name'    => 'PHP Version',
    'status'  => version_compare(PHP_VERSION, '8.0.0', '>='),
    'current' => 'PHP ' . PHP_VERSION
  ],
  [
    'name'    => 'MySQLi Extension',
    'status'  => extension_loaded('mysqli'),
    'current' => extension_loaded('mysqli') ? 'Enabled' : 'Missing'
  ],
  [
    'name'    => 'JSON Extension',
    'status'  => extension_loaded('json'),
    'current' => extension_loaded('json') ? 'Enabled' : 'Missing'
  ],
  [
    'name'    => 'Write Permissions',
    'status'  => is_writable(__DIR__ . '/data'),
    'current' => is_writable(__DIR__ . '/data') ? '/data directory writable' : 'Not writable'
  ],
  [
    'name'    => 'WireGuard',
    'status'  => trim(shell_exec('which wg 2>/dev/null')) !== '',
    'current' => trim(shell_exec('which wg 2>/dev/null')) !== '' ? 'Installed' : 'Not Installed'
  ]
];

echo json_encode([
  'requirements' => $requirements
]);
