    <?php
require_once __DIR__ . '/config/database.php';

// 🔒 ADMIN ONLY — Lock this debug file down
if (!isAdmin()) {
    http_response_code(403);
    die('<h3 style="font-family:sans-serif;color:#c00;padding:2rem;">403 Forbidden — Admin access required.</h3>');
}

echo "<h1>Staff Password Fix Tool</h1>";

$database = new Database();
$db = $database->getConnection();

// Check current staff accounts
$query = "SELECT user_id, username, password, first_name, last_name, role FROM users WHERE role IN ('staff', 'admin')";
$stmt = $db->query($query);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Current Staff Accounts:</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Username</th><th>Name</th><th>Role</th><th>Password Hash</th><th>Action</th></tr>";

foreach ($users as $user) {
    echo "<tr>";
    echo "<td>" . $user['user_id'] . "</td>";
    echo "<td>" . $user['username'] . "</td>";
    echo "<td>" . $user['first_name'] . " " . $user['last_name'] . "</td>";
    echo "<td>" . $user['role'] . "</td>";
    echo "<td><code>" . substr($user['password'], 0, 30) . "...</code></td>";
    echo "<td><a href='?reset=" . $user['user_id'] . "' onclick='return confirm(\"Reset password for " . $user['username'] . "?\")'>Reset Password</a></td>";
    echo "</tr>";
}
echo "</table>";

// Handle password reset
if (isset($_GET['reset'])) {
    $user_id = (int)$_GET['reset'];
    
    // Set new password (staff123 for staff, admin123 for admin)
    $new_password = 'staff123';
    
    // Check if it's admin
    $check_query = "SELECT role FROM users WHERE user_id = :id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':id', $user_id);
    $check_stmt->execute();
    $user = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user['role'] == 'admin') {
        $new_password = 'admin123';
    }
    
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $update_query = "UPDATE users SET password = :password WHERE user_id = :id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':password', $hashed_password);
    $update_stmt->bindParam(':id', $user_id);
    
    if ($update_stmt->execute()) {
        echo "<div style='background: #d4edda; color: #155724; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "✅ Password reset successfully for user ID " . $user_id . "! New password: <strong>" . $new_password . "</strong>";
        echo "</div>";
        
        // Show the new hash for verification
        $verify_query = "SELECT password FROM users WHERE user_id = :id";
        $verify_stmt = $db->prepare($verify_query);
        $verify_stmt->bindParam(':id', $user_id);
        $verify_stmt->execute();
        $new_hash = $verify_stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<div style='background: #fff3cd; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "New hash: <code>" . $new_hash['password'] . "</code>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "❌ Failed to reset password";
        echo "</div>";
    }
}

// Add a test login form
echo "<h2>Test Login</h2>";
echo "<form method='POST' style='max-width: 300px; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>";
echo "<div style='margin-bottom: 10px;'>";
echo "<label>Username:</label>";
echo "<input type='text' name='test_username' style='width: 100%; padding: 5px;'>";
echo "</div>";
echo "<div style='margin-bottom: 10px;'>";
echo "<label>Password:</label>";
echo "<input type='password' name='test_password' style='width: 100%; padding: 5px;'>";
echo "</div>";
echo "<button type='submit' name='test_login' style='padding: 10px; background: #003366; color: white; border: none; border-radius: 5px;'>Test Login</button>";
echo "</form>";

if (isset($_POST['test_login'])) {
    $username = $_POST['test_username'];
    $password = $_POST['test_password'];
    
    $query = "SELECT user_id, username, password, first_name, last_name, role, status 
              FROM users WHERE username = :username OR email = :username2";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username',  $username);
    $stmt->bindParam(':username2', $username);
    $stmt->execute();
    
    if ($stmt->rowCount() == 1) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<div style='background: #e3f2fd; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "User found: " . $user['username'] . "<br>";
        echo "Stored hash: " . $user['password'] . "<br>";
        
        if (password_verify($password, $user['password'])) {
            echo "✅ Password verification: SUCCESS<br>";
            echo "Status: " . $user['status'];
        } else {
            echo "❌ Password verification: FAILED<br>";
            
            // Try to see what's wrong
            echo "<br>Debug info:<br>";
            echo "- Password length: " . strlen($password) . "<br>";
            echo "- Hash algorithm: " . password_get_info($user['password'])['algoName'] . "<br>";
        }
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "❌ User not found";
        echo "</div>";
    }
}

// Add direct fix button
echo "<h2>Quick Fix All Staff Passwords</h2>";
echo "<a href='?fix_all=1' style='display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;' onclick='return confirm(\"Reset all staff passwords?\")'>Reset All Staff Passwords</a>";

if (isset($_GET['fix_all'])) {
    // Reset all staff passwords
    $updates = [
        ['staff', 'staff123'],
        ['admin', 'admin123']
    ];
    
    foreach ($updates as $update) {
        $hashed = password_hash($update[1], PASSWORD_DEFAULT);
        $query = "UPDATE users SET password = :pass WHERE username = :user";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':pass', $hashed);
        $stmt->bindParam(':user', $update[0]);
        $stmt->execute();
    }
    
    echo "<div style='background: #d4edda; color: #155724; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
    echo "✅ All staff passwords reset!<br>";
    echo "Admin: admin123<br>";
    echo "Staff: staff123";
    echo "</div>";
}
?>