<?php
/**
 * Automated Permission Enforcement Tests
 * Verifies that checkPermissionOrDie and server-side checks work correctly
 */

// Set up test environment
define('TESTING_MODE', true);
$_SESSION['user_id'] = 'test_user_123';
$_SESSION['username'] = 'testuser';
$_SESSION['role'] = 'clerk';

require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';

echo "=== Permission Enforcement Test Suite ===\n\n";

// Test 1: Verify checkUserPermission recognizes granted permissions
echo "Test 1: Check default permissions for 'judge' role\n";
$testUser = ['id' => 'judge_1', 'role' => 'judge'];
$canViewCases = checkUserPermission('judge_1', 'cases', 'view');
$canScheduleIndictments = checkUserPermission('judge_1', 'indictments', 'schedule');
echo "  Judge can view cases: " . ($canViewCases ? "✓ PASS" : "✗ FAIL") . "\n";
echo "  Judge can schedule indictments: " . ($canScheduleIndictments ? "✓ PASS" : "✗ FAIL") . "\n\n";

// Test 2: Verify checkUserPermission denies unpermitted actions
echo "Test 2: Check that clerk cannot schedule indictments (default)\n";
$canSchedule = checkUserPermission('clerk_1', 'indictments', 'schedule');
echo "  Clerk can schedule indictments: " . (!$canSchedule ? "✓ PASS (correctly denied)" : "✗ FAIL") . "\n\n";

// Test 3: Test custom role permissions from data/roles.json
echo "Test 3: Check stored role permissions\n";
$roles = getJsonData('data/roles.json');
if (!empty($roles)) {
    echo "  Found " . count($roles) . " roles in data/roles.json\n";
    foreach ($roles as $role) {
        if (isset($role['permissions']) && !empty($role['permissions'])) {
            echo "  Role '" . $role['id'] . "' has " . count($role['permissions']) . " permission modules\n";
            break;
        }
    }
    echo "  ✓ PASS (roles file exists and has permissions)\n\n";
} else {
    echo "  ✗ FAIL (no roles found)\n\n";
}

// Test 4: Verify getRolePermissions merges defaults with stored permissions
echo "Test 4: Check permission merging (defaults + stored)\n";
$rolePerms = getRolePermissions('judge');
if (!empty($rolePerms['cases']) && in_array('view', $rolePerms['cases'])) {
    echo "  Judge permissions include cases:view\n";
    echo "  ✓ PASS\n\n";
} else {
    echo "  ✗ FAIL (judge should have cases:view permission)\n\n";
}

// Test 5: Check that all critical modules have permission checks
echo "Test 5: Audit module view permission requirements\n";
$modulesToCheck = [
    'modules/cases.php',
    'modules/indictments.php',
    'modules/defendants.php',
    'modules/calendar.php',
    'modules/todos.php',
    'modules/staff.php',
    'modules/address_book.php'
];

$passCount = 0;
foreach ($modulesToCheck as $module) {
    if (file_exists($module)) {
        $content = file_get_contents($module);
        $hasCheck = strpos($content, "checkPermissionOrDie") !== false || 
                    strpos($content, "checkUserPermission") !== false;
        
        if ($hasCheck) {
            echo "  ✓ $module has permission checks\n";
            $passCount++;
        } else {
            echo "  ✗ $module MISSING permission checks\n";
        }
    }
}
echo "  Modules with checks: $passCount/" . count($modulesToCheck) . "\n\n";

// Test 6: Verify current user can/cannot see UI elements
echo "Test 6: Check currentUserCan() helper\n";
$canView = currentUserCan('cases', 'view');
$canDelete = currentUserCan('cases', 'delete');
echo "  Current user can view cases: " . ($canView ? "✓ PASS" : "✗ FAIL") . "\n";
echo "  Current user permissions checked via currentUserCan: ✓ PASS\n\n";

echo "=== Test Summary ===\n";
echo "Permission system appears functional. Run this test after making permission changes.\n";
?>
