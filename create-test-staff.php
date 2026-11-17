<?php
require_once __DIR__ . '/config/database.php';

// 🔒 ADMIN ONLY — Lock this debug file down
if (!isAdmin()) {
    http_response_code(403);
    die('<h3 style="font-family:sans-serif;color:#c00;padding:2rem;">403 Forbidden — Admin access required.</h3>');
}

$database = new Database();
$db = $database->getConnection();

echo "<h1>Create Test Staff Account</h1>";

// Delete existing test accounts
$db->exec("DELETE FROM users WHERE username IN ('teststaff', 'testadmin')");

// Create test staff account
$staff_password = password_hash('staff123', PASSWORD_DEFAULT);
$query = "INSERT INTO users (username, password, email, first_name, last_name, role, role_id, status) 
          VALUES (:username, :pass, :email, :first, :last, :role, :role_id, 'active')";

$stmt = $db->prepare($query);

// Create staff account (Role ID 2)
$stmt->execute([
    ':username' => 'teststaff',
    ':pass' => $staff_password,
    ':email' => 'teststaff@palayan.gov.ph',
    ':first' => 'Test',
    ':last' => 'Staff',
    ':role' => 'staff',
    ':role_id' => 2
]);
$staff_id = $db->lastInsertId();

// Create staff record
$staff_query = "INSERT INTO staff (user_id, position, employee_id, hire_date) 
                VALUES (:user_id, 'Test Staff', 'TEST001', CURDATE())";
$staff_stmt = $db->prepare($staff_query);
$staff_stmt->execute([':user_id' => $staff_id]);

// Create admin account (Role ID 1)
$admin_password = password_hash('admin123', PASSWORD_DEFAULT);
$stmt->execute([
    ':username' => 'testadmin',
    ':pass' => $admin_password,
    ':email' => 'testadmin@palayan.gov.ph',
    ':first' => 'Test',
    ':last' => 'Admin',
    ':role' => 'admin',
    ':role_id' => 1
]);
$admin_id = $db->lastInsertId();

// Create admin staff record
$staff_stmt->execute([':user_id' => $admin_id]);

echo "<div style='background: #d4edda; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
echo "<h2>✅ Test Accounts Created!</h2>";
echo "<p><strong>Staff Account:</strong><br>";
echo "Username: teststaff<br>";
echo "Password: staff123<br>";
echo "Role: Staff</p>";
echo "<p><strong>Admin Account:</strong><br>";
echo "Username: testadmin<br>";
echo "Password: admin123<br>";
echo "Role: Admin</p>";
echo "</div>";

// Verify the passwords work
echo "<h3>Verification Test:</h3>";

$test_users = ['teststaff', 'testadmin'];
foreach ($test_users as $test_user) {
    $check = $db->prepare("SELECT * FROM users WHERE username = ?");
    $check->execute([$test_user]);
    $user = $check->fetch(PDO::FETCH_ASSOC);
    
    echo "<div style='margin: 10px 0; padding: 10px; background: #f5f5f5; border-radius: 5px;'>";
    echo "Testing: <strong>" . $test_user . "</strong><br>";
    echo "Stored hash: " . $user['password'] . "<br>";
    
    $test_pass = ($test_user == 'teststaff') ? 'staff123' : 'admin123';
    if (password_verify($test_pass, $user['password'])) {
        echo "✅ Password verification successful!";
    } else {
        echo "❌ Password verification failed!";
    }
    echo "</div>";
}

echo "<p><a href='login.php' style='display: inline-block; padding: 10px 20px; background: #003366; color: white; text-decoration: none; border-radius: 5px;'>Go to Unified Login</a></p>";
?>