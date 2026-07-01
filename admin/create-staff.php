<?php
require_once __DIR__ . '/../config/database.php';

// Only admin can create staff accounts
if (!isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $contact_number = sanitize($_POST['contact_number']);
    $position = sanitize($_POST['position']);
    $employee_id = sanitize($_POST['employee_id']);
    
    // Validation
    if ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters";
    } else {
        // Check if username or email exists
        $check_query = "SELECT user_id FROM users WHERE username = :username OR email = :email";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':username', $username);
        $check_stmt->bindParam(':email', $email);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $error = "Username or email already exists";
        } else {
            // Start transaction
            $db->beginTransaction();
            
            try {
                // Create user account
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $query = "INSERT INTO users (username, password, email, first_name, last_name, contact_number, role, status) 
                          VALUES (:username, :password, :email, :first_name, :last_name, :contact_number, 'staff', 'active')";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':first_name', $first_name);
                $stmt->bindParam(':last_name', $last_name);
                $stmt->bindParam(':contact_number', $contact_number);
                $stmt->execute();
                
                $user_id = $db->lastInsertId();
                
                // Create staff record
                $staff_query = "INSERT INTO staff (user_id, position, employee_id, hire_date) 
                                VALUES (:user_id, :position, :employee_id, CURDATE())";
                $staff_stmt = $db->prepare($staff_query);
                $staff_stmt->bindParam(':user_id', $user_id);
                $staff_stmt->bindParam(':position', $position);
                $staff_stmt->bindParam(':employee_id', $employee_id);
                $staff_stmt->execute();
                
                $db->commit();
                $success = "Staff account created successfully!";
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Failed to create staff account: " . $e->getMessage();
            }
        }
    }
}

// Get existing staff
$staff_query = "SELECT u.*, s.position, s.employee_id, s.hire_date 
                FROM users u
                JOIN staff s ON u.user_id = s.user_id
                WHERE u.role IN ('staff', 'admin')
                ORDER BY u.created_at DESC";
$staff_stmt = $db->query($staff_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Staff Account - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="user-profile">
                <h3><?php echo htmlspecialchars($_SESSION['full_name']); ?></h3>
                <p>Administrator</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="../staff-dashboard.php">Dashboard</a></li>
                <li><a href="create-staff.php" class="active">Manage Staff</a></li>
                <li><a href="system-settings.php">System Settings</a></li>
                <li><a href="audit-logs.php">Audit Logs</a></li>
                <li><a href="../logout.php">Logout</a></li>            
                <li><a href="manage-staff-services.php"><i class="fas fa-user-tag"></i> Staff Services</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <h1>Create Staff Account</h1>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="form-container">
                <form method="POST" action="" id="staffForm" onsubmit="return validateStaffForm()">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" required>
                            <small>At least 8 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="contact_number">Contact Number</label>
                            <input type="text" id="contact_number" name="contact_number">
                        </div>
                        
                        <div class="form-group">
                            <label for="employee_id">Employee ID *</label>
                            <input type="text" id="employee_id" name="employee_id" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="position">Position *</label>
                        <select id="position" name="position" required>
                            <option value="">-- Select Position --</option>
                            <option value="CSWDO Staff">CSWDO Staff</option>
                            <option value="Social Worker">Social Worker</option>
                            <option value="Project Coordinator">Project Coordinator</option>
                            <option value="Records Officer">Records Officer</option>
                            <option value="Administrative Officer">Administrative Officer</option>
                            <option value="Department Head">Department Head</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Create Staff Account</button>
                        <button type="reset" class="btn-secondary">Clear Form</button>
                    </div>
                </form>
            </div>
            
            <!-- Staff List -->
            <div class="staff-list">
                <h2>Current Staff Members</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>Name</th>
                            <th>Position</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Hire Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($staff = $staff_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($staff['employee_id']); ?></td>
                            <td><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($staff['position']); ?></td>
                            <td><?php echo htmlspecialchars($staff['email']); ?></td>
                            <td><?php echo htmlspecialchars($staff['contact_number']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($staff['hire_date'])); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $staff['status']; ?>">
                                    <?php echo ucfirst($staff['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <style>
    .form-container {
        background: white;
        padding: 30px;
        border-radius: 10px;
        margin-bottom: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        color: #333;
        font-weight: 500;
    }
    
    .form-group input,
    .form-group select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 1rem;
    }
    
    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #003366;
    }
    
    .form-group small {
        display: block;
        margin-top: 5px;
        color: #666;
        font-size: 0.8rem;
    }
    
    .form-actions {
        display: flex;
        gap: 15px;
        margin-top: 20px;
    }
    
    .staff-list {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .staff-list h2 {
        color: #333;
        margin-bottom: 20px;
    }
    
    .status-active {
        background: #d4edda;
        color: #155724;
    }
    
    .status-inactive {
        background: #f8d7da;
        color: #721c24;
    }
    
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
            gap: 0;
        }
        
        .form-actions {
            flex-direction: column;
        }
    }
    </style>
    
    <script>
    function validateStaffForm() {
        const password = document.getElementById('password').value;
        const confirm = document.getElementById('confirm_password').value;
        
        if (password.length < 8) {
            alert('Password must be at least 8 characters long');
            return false;
        }
        
        if (password !== confirm) {
            alert('Passwords do not match');
            return false;
        }
        
        return confirm('Create this staff account?');
    }
    </script>
    
    <script src="../js/main.js"></script>
</body>
</html>