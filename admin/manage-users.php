<?php
// admin/manage-users.php
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    csrf_verify();
    $user_id = intval($_POST['user_id']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_password) || empty($confirm_password)) {
        $error = "Passwords cannot be empty.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        try {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $query = "UPDATE users SET password = :password WHERE user_id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':password', $hashed);
            $stmt->bindParam(':id', $user_id);
            $stmt->execute();

            // Log activity
            $log_query = "INSERT INTO audit_logs (user_id, action, table_affected, new_values) VALUES (:admin_id, 'reset_password', 'users', :details)";
            $log_stmt = $db->prepare($log_query);
            $details = "Reset password for User ID: " . $user_id;
            $log_stmt->bindParam(':admin_id', $_SESSION['user_id']);
            $log_stmt->bindParam(':details', $details);
            $log_stmt->execute();

            $success = "Password updated successfully.";
        } catch (Exception $e) {
            $error = "Failed to update password: " . $e->getMessage();
        }
    }
}

// Handle status / role updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    csrf_verify();
    $user_id = intval($_POST['user_id']);
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $contact_number = sanitize($_POST['contact_number']);
    $role = sanitize($_POST['role']);
    $status = sanitize($_POST['status']);
    $verification_status = sanitize($_POST['verification_status']);

    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error = "Required fields cannot be empty.";
    } else {
        $db->beginTransaction();
        try {
            // Update users table
            $query = "UPDATE users SET 
                        first_name = :first_name, 
                        last_name = :last_name, 
                        email = :email, 
                        contact_number = :contact_number, 
                        role = :role, 
                        status = :status, 
                        verification_status = :verification_status 
                      WHERE user_id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':last_name', $last_name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':contact_number', $contact_number);
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':verification_status', $verification_status);
            $stmt->bindParam(':id', $user_id);
            $stmt->execute();

            // Update linked profiles if they exist
            if ($role === 'resident') {
                $res_check = "SELECT id FROM residents WHERE user_id = :id";
                $res_check_stmt = $db->prepare($res_check);
                $res_check_stmt->bindParam(':id', $user_id);
                $res_check_stmt->execute();
                if ($res_check_stmt->rowCount() == 0) {
                    // Create resident profile
                    $year = date('Y');
                    $count_query = "SELECT COUNT(*) as count FROM residents";
                    $count = $db->query($count_query)->fetch(PDO::FETCH_ASSOC)['count'];
                    $res_num = 'RES-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

                    $ins = "INSERT INTO residents (user_id, resident_number, first_name, last_name, contact_number, is_active) 
                            VALUES (:id, :res_num, :first_name, :last_name, :contact, 1)";
                    $ins_stmt = $db->prepare($ins);
                    $ins_stmt->bindParam(':id', $user_id);
                    $ins_stmt->bindParam(':res_num', $res_num);
                    $ins_stmt->bindParam(':first_name', $first_name);
                    $ins_stmt->bindParam(':last_name', $last_name);
                    $ins_stmt->bindParam(':contact', $contact_number);
                    $ins_stmt->execute();
                } else {
                    $up = "UPDATE residents SET first_name = :first_name, last_name = :last_name, contact_number = :contact WHERE user_id = :id";
                    $up_stmt = $db->prepare($up);
                    $up_stmt->bindParam(':first_name', $first_name);
                    $up_stmt->bindParam(':last_name', $last_name);
                    $up_stmt->bindParam(':contact', $contact_number);
                    $up_stmt->bindParam(':id', $user_id);
                    $up_stmt->execute();
                }
            } elseif ($role === 'staff') {
                $staff_check = "SELECT id FROM staff WHERE user_id = :id";
                $staff_check_stmt = $db->prepare($staff_check);
                $staff_check_stmt->bindParam(':id', $user_id);
                $staff_check_stmt->execute();
                if ($staff_check_stmt->rowCount() == 0) {
                    $year = date('Y');
                    $count = $db->query("SELECT COUNT(*) as count FROM staff")->fetch(PDO::FETCH_ASSOC)['count'];
                    $emp_id = 'EMP-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
                    $stf_num = 'STF-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

                    $ins = "INSERT INTO staff (user_id, employee_id, staff_number, first_name, last_name, contact_number, position, is_active, hire_date) 
                            VALUES (:id, :emp, :stf, :first, :last, :contact, 'Staff Member', 1, NOW())";
                    $ins_stmt = $db->prepare($ins);
                    $ins_stmt->bindParam(':id', $user_id);
                    $ins_stmt->bindParam(':emp', $emp_id);
                    $ins_stmt->bindParam(':stf', $stf_num);
                    $ins_stmt->bindParam(':first', $first_name);
                    $ins_stmt->bindParam(':last', $last_name);
                    $ins_stmt->bindParam(':contact', $contact_number);
                    $ins_stmt->execute();
                } else {
                    $up = "UPDATE staff SET first_name = :first_name, last_name = :last_name, contact_number = :contact WHERE user_id = :id";
                    $up_stmt = $db->prepare($up);
                    $up_stmt->bindParam(':first_name', $first_name);
                    $up_stmt->bindParam(':last_name', $last_name);
                    $up_stmt->bindParam(':contact', $contact_number);
                    $up_stmt->bindParam(':id', $user_id);
                    $up_stmt->execute();
                }
            }

            $db->commit();

            // Log activity
            $log_query = "INSERT INTO audit_logs (user_id, action, table_affected, new_values) VALUES (:admin_id, 'edit_user', 'users', :details)";
            $log_stmt = $db->prepare($log_query);
            $details = "Updated user details for User ID: $user_id (Role: $role, Status: $status)";
            $log_stmt->bindParam(':admin_id', $_SESSION['user_id']);
            $log_stmt->bindParam(':details', $details);
            $log_stmt->execute();

            $success = "User account updated successfully.";
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Failed to update user account: " . $e->getMessage();
        }
    }
}

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {
    csrf_verify();
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $contact_number = sanitize($_POST['contact_number']);
    $role = sanitize($_POST['role']);
    $status = sanitize($_POST['status']);

    if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
        $error = "Please fill in all required fields.";
    } else {
        // Check if username/email exists
        $check = "SELECT user_id FROM users WHERE username = :u OR email = :e";
        $c_stmt = $db->prepare($check);
        $c_stmt->bindParam(':u', $username);
        $c_stmt->bindParam(':e', $email);
        $c_stmt->execute();

        if ($c_stmt->rowCount() > 0) {
            $error = "Username or Email already exists.";
        } else {
            $db->beginTransaction();
            try {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $query = "INSERT INTO users (username, password, email, first_name, last_name, contact_number, role, status, verification_status) 
                          VALUES (:username, :password, :email, :first_name, :last_name, :contact_number, :role, :status, 'verified')";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $hashed);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':first_name', $first_name);
                $stmt->bindParam(':last_name', $last_name);
                $stmt->bindParam(':contact_number', $contact_number);
                $stmt->bindParam(':role', $role);
                $stmt->bindParam(':status', $status);
                $stmt->execute();
                
                $new_id = $db->lastInsertId();

                // Profile initialization
                if ($role === 'resident') {
                    $year = date('Y');
                    $count = $db->query("SELECT COUNT(*) as count FROM residents")->fetch(PDO::FETCH_ASSOC)['count'];
                    $res_num = 'RES-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

                    $ins = "INSERT INTO residents (user_id, resident_number, first_name, last_name, contact_number, is_active) 
                            VALUES (:id, :res_num, :first_name, :last_name, :contact, 1)";
                    $ins_stmt = $db->prepare($ins);
                    $ins_stmt->bindParam(':id', $new_id);
                    $ins_stmt->bindParam(':res_num', $res_num);
                    $ins_stmt->bindParam(':first_name', $first_name);
                    $ins_stmt->bindParam(':last_name', $last_name);
                    $ins_stmt->bindParam(':contact', $contact_number);
                    $ins_stmt->execute();
                } elseif ($role === 'staff') {
                    $year = date('Y');
                    $count = $db->query("SELECT COUNT(*) as count FROM staff")->fetch(PDO::FETCH_ASSOC)['count'];
                    $emp_id = 'EMP-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
                    $stf_num = 'STF-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

                    $ins = "INSERT INTO staff (user_id, employee_id, staff_number, first_name, last_name, contact_number, position, is_active, hire_date) 
                            VALUES (:id, :emp, :stf, :first, :last, :contact, 'Staff Member', 1, NOW())";
                    $ins_stmt = $db->prepare($ins);
                    $ins_stmt->bindParam(':id', $new_id);
                    $ins_stmt->bindParam(':emp', $emp_id);
                    $ins_stmt->bindParam(':stf', $stf_num);
                    $ins_stmt->bindParam(':first', $first_name);
                    $ins_stmt->bindParam(':last', $last_name);
                    $ins_stmt->bindParam(':contact', $contact_number);
                    $ins_stmt->execute();
                }

                $db->commit();

                // Log activity
                $log_query = "INSERT INTO audit_logs (user_id, action, table_affected, new_values) VALUES (:admin_id, 'create_user', 'users', :details)";
                $log_stmt = $db->prepare($log_query);
                $details = "Created new user: $username (Role: $role)";
                $log_stmt->bindParam(':admin_id', $_SESSION['user_id']);
                $log_stmt->bindParam(':details', $details);
                $log_stmt->execute();

                $success = "User account created successfully.";
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Failed to create user account: " . $e->getMessage();
            }
        }
    }
}

// Handle user deletion
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    if ($delete_id == $_SESSION['user_id']) {
        $error = "You cannot delete your own admin account.";
    } else {
        $db->beginTransaction();
        try {
            // Disable foreign key checks temporarily
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");

            // Delete child dependencies
            $db->exec("DELETE FROM residents WHERE user_id = $delete_id");
            $db->exec("DELETE FROM staff WHERE user_id = $delete_id");
            $db->exec("DELETE FROM verification_requests WHERE user_id = $delete_id");
            $db->exec("DELETE FROM notifications WHERE user_id = $delete_id");
            $db->exec("DELETE FROM appointments WHERE user_id = $delete_id");
            $db->exec("DELETE FROM audit_logs WHERE user_id = $delete_id");
            $db->exec("DELETE FROM documents WHERE user_id = $delete_id");
            $db->exec("DELETE FROM email_logs WHERE user_id = $delete_id");
            $db->exec("DELETE FROM housing_beneficiaries WHERE user_id = $delete_id");
            $db->exec("DELETE FROM aics_requests WHERE user_id = $delete_id");
            $db->exec("DELETE FROM aics_requests_backup WHERE user_id = $delete_id");

            // Delete the main user record
            $del_user = "DELETE FROM users WHERE user_id = :id";
            $stmt = $db->prepare($del_user);
            $stmt->bindParam(':id', $delete_id);
            $stmt->execute();

            // Re-enable foreign key checks
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");

            $db->commit();

            // Log activity (logged under admin's ID, so it remains intact)
            $log_query = "INSERT INTO audit_logs (user_id, action, table_affected, new_values) VALUES (:admin_id, 'delete_user', 'users', :details)";
            $log_stmt = $db->prepare($log_query);
            $details = "Deleted User ID: " . $delete_id;
            $log_stmt->bindParam(':admin_id', $_SESSION['user_id']);
            $log_stmt->bindParam(':details', $details);
            $log_stmt->execute();

            $success = "User account deleted successfully.";
        } catch (Exception $e) {
            $db->rollBack();
            // Ensure foreign key checks are re-enabled even on failure
            try { $db->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch(Exception $ex) {}
            $error = "Failed to delete user account: " . $e->getMessage();
        }
    }
}

// Filter and Search parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$filter_role = isset($_GET['role']) ? sanitize($_GET['role']) : '';
$filter_status = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Build Query
$query_str = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query_str .= " AND (username LIKE :search OR email LIKE :search OR first_name LIKE :search OR last_name LIKE :search)";
    $search_val = "%$search%";
    $params[':search'] = $search_val;
}

if (!empty($filter_role)) {
    $query_str .= " AND role = :role";
    $params[':role'] = $filter_role;
}

if (!empty($filter_status)) {
    $query_str .= " AND status = :status";
    $params[':status'] = $filter_status;
}

$query_str .= " ORDER BY created_at DESC";

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Get total count
$count_query = "SELECT COUNT(*) as count FROM (" . $query_str . ") as subquery";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $val) {
    $count_stmt->bindValue($key, $val);
}
$count_stmt->execute();
$total_rows = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
$total_pages = ceil($total_rows / $limit);

// Execute actual query with limits
$query_str .= " LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($query_str);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get counts for stats
$total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$active_users = $db->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$resident_users = $db->query("SELECT COUNT(*) FROM users WHERE role = 'resident'")->fetchColumn();
$staff_users = $db->query("SELECT COUNT(*) FROM users WHERE role = 'staff'")->fetchColumn();

// Initials for avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach ($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage System Users - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
        /* ========== CSS VARIABLES - LIGHT/DARK THEME ========== */
        :root {
            --tan: #D8BA98;
            --maroon: #7F0303;
            --maroon-light: #9a1515;
            --maroon-dark: #5a0202;
            --midnight: #0F414A;
            --white: #FFFFFF;
            
            /* Light theme defaults */
            --bg-primary: #E8ECF2;
            --bg-secondary: #FFFFFF;
            --bg-card: #FFFFFF;
            --bg-sidebar: #7F0303;
            --bg-sidebar-hover: rgba(255,255,255,0.12);
            --bg-sidebar-active: rgba(255,255,255,0.15);
            --text-primary: #263238;
            --text-secondary: #3A4A5A;
            --text-muted: #6B7A8A;
            --text-sidebar: rgba(255,255,255,0.7);
            --text-sidebar-active: #FFFFFF;
            --border-color: #C8D0DC;
            
            /* Enhanced Shadows */
            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 8px 30px rgba(0, 0, 0, 0.10);
            --shadow-lg: 0 12px 40px rgba(15,65,74,0.15), 0 4px 12px rgba(0,0,0,0.08);
            --shadow-hover: 0 12px 40px rgba(127, 3, 3, 0.15);
            
            --search-bg: #FFFFFF;
            --stat-bg: #FFFFFF;
            --filter-bg: #FFFFFF;
            --table-bg: #FFFFFF;
            --badge-bg: #F1F3F8;
            --divider: rgba(255,255,255,0.08);
            
            --radius-card: 20px;
            --radius-element: 12px;
        }

        /* ========== DARK THEME ========== */
        [data-theme="dark"] {
            --bg-primary: #0a0e17;
            --bg-secondary: #111827;
            --bg-card: #1a2332;
            --bg-sidebar: #0f1629;
            --bg-sidebar-hover: rgba(255,255,255,0.06);
            --bg-sidebar-active: rgba(255,255,255,0.10);
            --text-primary: #E8EDF5;
            --text-secondary: #B0C4DE;
            --text-muted: #6B85A0;
            --text-sidebar: rgba(255,255,255,0.6);
            --text-sidebar-active: #FFFFFF;
            --border-color: #2a3a4f;
            
            /* Enhanced Shadows - Dark Mode */
            --shadow-sm: 0 4px 16px rgba(0, 0, 0, 0.40);
            --shadow-md: 0 8px 32px rgba(0, 0, 0, 0.50);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.60);
            --shadow-hover: 0 12px 40px rgba(139, 26, 26, 0.25);
            
            --search-bg: #1a2332;
            --stat-bg: #1a2332;
            --filter-bg: #1a2332;
            --table-bg: #1a2332;
            --badge-bg: #2a3a4f;
            --divider: rgba(255,255,255,0.05);
            
            --maroon: #8B1A1A;
            --maroon-light: #A82828;
            --maroon-dark: #5a0202;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-secondary);
            line-height: 1.5;
            min-height: 100vh;
            transition: background 0.3s ease, color 0.3s ease;
        }

        /* ========== LAYOUT ========== */
        .app-container { display: flex; min-height: 100vh; }

        /* ========== SIDEBAR ========== */
        .sidebar {
            width: 260px;
            background: var(--bg-sidebar);
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            overflow-y: auto;
            z-index: 100;
            display: flex;
            flex-direction: column;
            transition: background 0.3s ease;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }

        .sidebar-header {
            padding: 28px 20px 20px;
            border-bottom: 1px solid var(--divider);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            min-width: 0;
        }
        .logo-image {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            object-fit: contain;
            background: rgba(255,255,255,0.10);
            padding: 4px;
            flex-shrink: 0;
        }
        .logo-icon {
            width: 44px;
            height: 44px;
            background: rgba(255,255,255,0.10);
            border-radius: 12px;
            display: none;
            align-items: center;
            justify-content: center;
            color: var(--maroon-light);
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .logo-text {
            flex: 1;
            min-width: 0;
        }
        .logo-text h2 {
            color: var(--text-sidebar-active);
            font-size: 1.1rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }
        .logo-text span {
            color: rgba(255,255,255,0.4);
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 16px 14px;
            flex: 1;
        }
        .sidebar-menu li { margin-bottom: 2px; }
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 14px;
            border-radius: 12px;
            color: var(--text-sidebar);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.25s;
        }
        .sidebar-menu li a i { width: 20px; font-size: 1rem; color: rgba(255,255,255,0.4); }
        .sidebar-menu li a:hover { background: var(--bg-sidebar-hover); color: var(--text-sidebar-active); }
        .sidebar-menu li a:hover i { color: rgba(255,255,255,0.8); }
        .sidebar-menu li a.active {
            background: var(--bg-sidebar-active);
            color: var(--text-sidebar-active);
        }
        .sidebar-menu li a.active i { color: var(--text-sidebar-active); }
        .menu-divider {
            border-top: 1px solid var(--divider);
            margin: 12px 0;
            padding-top: 10px;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: rgba(255,255,255,0.3);
            padding-left: 14px;
        }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--divider);
        }
        .sidebar-footer a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 12px;
            color: var(--text-sidebar);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            background: rgba(255,255,255,0.04);
            transition: all 0.25s;
        }
        .sidebar-footer a:hover { background: rgba(255,255,255,0.10); color: var(--text-sidebar-active); }
        .sidebar-footer .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            margin-bottom: 8px;
        }
        .sidebar-footer .avatar {
            width: 38px;
            height: 38px;
            background: var(--maroon-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            font-size: 0.8rem;
        }
        .sidebar-footer .user-details h4 {
            color: var(--text-sidebar-active);
            font-size: 0.85rem;
            font-weight: 600;
        }
        .sidebar-footer .user-details p {
            color: rgba(255,255,255,0.4);
            font-size: 0.7rem;
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 28px 36px 40px;
            min-height: 100vh;
            background: var(--bg-primary);
            transition: background 0.3s ease;
        }

        /* ========== TOP BAR ========== */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .page-title h1 {
            font-size: 1.5rem;
            color: var(--text-primary);
            font-weight: 700;
            transition: color 0.3s ease;
        }
        .page-title p {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 2px;
        }
        .top-bar-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .search-box {
            display: flex;
            align-items: center;
            background: var(--search-bg);
            border-radius: 50px;
            padding: 8px 16px;
            border: 1px solid var(--border-color);
            gap: 10px;
            transition: background 0.3s ease, border-color 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        .search-box input {
            border: none;
            outline: none;
            font-size: 0.85rem;
            background: transparent;
            min-width: 180px;
            color: var(--text-primary);
        }
        .search-box input::placeholder { color: var(--text-muted); }
        .search-box i { color: var(--text-muted); }

        .theme-toggle {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.3s ease;
            font-size: 1.1rem;
            box-shadow: var(--shadow-sm);
        }
        .theme-toggle:hover {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
            transform: scale(1.05);
            box-shadow: var(--shadow-md);
        }

        .top-icon {
            color: var(--text-muted);
            font-size: 1.1rem;
            transition: color 0.3s ease;
            text-decoration: none;
            background: var(--bg-card);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
        .top-icon:hover { 
            color: var(--text-primary);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        /* ========== ALERTS ========== */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius-element);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid transparent;
        }
        .alert-success {
            background: rgba(76, 175, 80, 0.12);
            color: #4CAF50;
            border-left-color: #4CAF50;
        }
        .alert-error {
            background: rgba(244, 67, 54, 0.12);
            color: #EF5350;
            border-left-color: #EF5350;
        }
        .alert i { font-size: 1.2rem; }

        /* ========== STATS GRID ========== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: var(--stat-bg);
            border-radius: 20px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, var(--maroon), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 20px;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
            border-color: var(--maroon-light);
        }
        .stat-card:hover::after {
            opacity: 0.03;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
            position: relative;
            z-index: 1;
            box-shadow: var(--shadow-sm);
        }
        .stat-icon.total { background: rgba(127, 3, 3, 0.12); color: var(--maroon-light); }
        .stat-icon.active { background: rgba(76, 175, 80, 0.12); color: #4CAF50; }
        .stat-icon.senior { background: rgba(33, 150, 243, 0.12); color: #2196F3; }
        .stat-icon.pwd { background: rgba(156, 39, 176, 0.12); color: #9C27B0; }

        .stat-info {
            position: relative;
            z-index: 1;
        }
        .stat-info h3 {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.2;
            transition: color 0.3s ease;
        }
        .stat-info p {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 500;
            margin-top: 2px;
        }

        /* ========== QUICK ACTIONS BAR ========== */
        .quick-actions-bar {
            background: linear-gradient(135deg, var(--maroon), var(--maroon-dark));
            border-radius: var(--radius-card);
            padding: 20px 28px;
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            color: white;
            box-shadow: var(--shadow-md);
        }
        .resident-info h4 {
            font-size: 0.8rem;
            opacity: 0.85;
            margin-bottom: 4px;
        }
        .resident-info .count {
            font-size: 1.6rem;
            font-weight: 800;
        }
        .quick-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .quick-btn {
            background: rgba(255,255,255,0.15);
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            color: white;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }
        .quick-btn:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        /* ========== FILTER BAR ========== */
        .filter-bar {
            background: var(--filter-bg);
            border-radius: var(--radius-card);
            padding: 20px 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 16px;
            align-items: end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .filter-group label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-group input,
        .filter-group select {
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all 0.3s;
        }
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .btn-primary {
            background: var(--maroon);
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: var(--shadow-sm);
        }
        .btn-primary:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .btn-secondary {
            background: var(--badge-bg);
            color: var(--text-secondary);
            padding: 10px 20px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .btn-secondary:hover {
            background: var(--border-color);
        }

        /* ========== TABLE ========== */
        .table-container {
            background: var(--table-bg);
            border-radius: var(--radius-card);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table thead {
            background: var(--badge-bg);
            border-bottom: 2px solid var(--border-color);
        }
        .data-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .data-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.82rem;
            color: var(--text-secondary);
            vertical-align: middle;
        }
        .data-table tbody tr:hover {
            background: var(--badge-bg);
        }

        /* Resident Cell with Avatar */
        .resident-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .resident-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--badge-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
            border: 2px solid var(--border-color);
        }
        .resident-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .resident-avatar .initials {
            font-weight: 700;
            font-size: 0.7rem;
            color: var(--text-primary);
        }
        .resident-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        .resident-id {
            font-weight: 600;
            color: var(--maroon-light);
            font-size: 0.75rem;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 14px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            box-shadow: var(--shadow-sm);
        }
        .status-badge.active {
            background: rgba(76, 175, 80, 0.15);
            color: #4CAF50;
        }
        .status-badge.inactive {
            background: rgba(244, 67, 54, 0.15);
            color: #EF5350;
        }

        /* Category Badges */
        .category-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 50px;
            font-size: 0.6rem;
            font-weight: 600;
            margin: 2px;
        }
        .category-badge.senior {
            background: rgba(33, 150, 243, 0.12);
            color: #2196F3;
        }
        .category-badge.pwd {
            background: rgba(156, 39, 176, 0.12);
            color: #9C27B0;
        }

        /* Action Buttons */
        .btn-sm {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn-sm:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        .btn-edit {
            background: rgba(33, 150, 243, 0.12);
            color: #2196F3;
        }
        .btn-edit:hover {
            background: #2196F3;
            color: white;
        }
        .btn-toggle {
            background: rgba(255, 152, 0, 0.12);
            color: #FF9800;
        }
        .btn-toggle:hover {
            background: #FF9800;
            color: white;
        }
        .btn-delete {
            background: rgba(244, 67, 54, 0.12);
            color: #EF5350;
        }
        .btn-delete:hover {
            background: #EF5350;
            color: white;
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
        }
        .no-data i {
            font-size: 48px;
            color: var(--text-muted);
            opacity: 0.3;
            display: block;
            margin-bottom: 16px;
        }
        .no-data p {
            color: var(--text-muted);
        }

        /* ========== PAGINATION ========== */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
            flex-wrap: wrap;
        }
        .pagination .page-info {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-right: 12px;
        }
        .pagination a, .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 12px;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-secondary);
        }
        .pagination a:hover {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        .pagination .active {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
        }
        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        .pagination .ellipsis {
            border: none;
            background: transparent;
            cursor: default;
        }

        /* ========== MODAL ========== */
        .modal {
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            display: flex;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(6px);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }
        .modal.active {
            opacity: 1;
            visibility: visible;
        }
        .modal-content {
            background: var(--bg-card);
            border-radius: 24px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideIn 0.3s ease;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
        }
        @keyframes slideIn {
            from { transform: translateY(-30px) scale(0.95); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }
        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--badge-bg);
            border-radius: 24px 24px 0 0;
        }
        .modal-header h2 {
            font-size: 1.2rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-header h2 i {
            color: var(--maroon-light);
        }
        .modal-header .close-btn {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: var(--text-muted);
            transition: all 0.3s;
            line-height: 1;
        }
        .modal-header .close-btn:hover {
            color: var(--maroon);
            transform: rotate(90deg);
        }
        .modal-body {
            padding: 25px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        .form-group label .required {
            color: #EF5350;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }
        .form-group .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-weight: 500;
            color: var(--text-secondary);
        }
        .form-group .checkbox-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--maroon);
            cursor: pointer;
        }
        .modal-footer {
            padding: 16px 25px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: var(--badge-bg);
            border-radius: 0 0 24px 24px;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
            .filter-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar-actions { justify-content: stretch; flex-wrap: wrap; }
            .search-box { flex: 1; }
            .search-box input { min-width: 120px; }
            .stats-grid { grid-template-columns: 1fr; }
            .filter-grid { grid-template-columns: 1fr; }
            .filter-actions { flex-wrap: wrap; }
            .form-row { grid-template-columns: 1fr; }
            .data-table { display: block; overflow-x: auto; }
            .quick-actions-bar { flex-direction: column; text-align: center; }
            .quick-actions { justify-content: center; }
            .modal-content { width: 95%; padding: 0; }
            .modal-body { padding: 16px; }
            .pagination .page-info { width: 100%; text-align: center; margin-right: 0; margin-bottom: 8px; }
            .resident-avatar { width: 28px; height: 28px; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .data-table th, .data-table td { padding: 8px 10px; font-size: 0.7rem; }
            .btn-sm { font-size: 0.6rem; padding: 3px 8px; }
            .quick-btn { font-size: 0.7rem; padding: 8px 14px; }
            .pagination a, .pagination span { min-width: 30px; height: 30px; font-size: 0.7rem; padding: 0 8px; }
            .resident-cell { gap: 8px; }
            .resident-avatar { width: 24px; height: 24px; }
        }
    </style>
</head>

</head>
<body>
<div class="app-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-wrapper">
                <img src="../images/logo.png" alt="Palayan City Logo" class="logo-image" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="logo-icon" style="display: none;"><i class="fas fa-crown"></i></div>
                <div class="logo-text">
                    <h2>One Palayan-Kalinga Hub</h2>
                    <span>Admin Panel</span>
                </div>
            </div>
        </div>
        
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            <li><a href="calendar.php"><i class="fas fa-calendar-alt"></i> Calendar Manager</a></li>
            <li class="menu-divider">User Management</li>
            <li><a href="manage-verifications.php"><i class="fas fa-shield-alt"></i> Verifications</a></li>
            <li><a href="manage-staff.php"><i class="fas fa-users"></i> Manage Staff</a></li>
            <li><a href="manage-residents.php"><i class="fas fa-user-friends"></i> Residents</a></li>
            <li><a href="manage-staff-services.php"><i class="fas fa-user-tag"></i> Staff Services</a></li>
            <li><a href="manage-users.php" class="active"><i class="fas fa-users-cog"></i> System Users</a></li>
            <li class="menu-divider">System</li>
            <li><a href="audit-logs.php"><i class="fas fa-history"></i> Audit Logs</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> System Settings</a></li>
            <li><a href="backup.php"><i class="fas fa-database"></i> Database Backup</a></li>
            <li class="menu-divider"></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="avatar"><?php echo $initials; ?></div>
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($_SESSION['full_name']); ?></h4>
                    <p>Administrator</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1><i class="fas fa-users-cog" style="color: var(--maroon-light); margin-right: 10px;"></i> Manage Users</h1>
                <p>Manage all administrative, staff, and resident user credentials</p>
            </div>
            <div class="top-bar-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search accounts..." id="globalSearch" value="<?php echo htmlspecialchars($search); ?>" onkeyup="if(event.key === 'Enter') runSearch()">
                </div>
                <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                </button>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='manage-users.php'">
                <div class="stat-icon total"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_users; ?></h3>
                    <p>Total Accounts</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-users.php?status=active'">
                <div class="stat-icon active"><i class="fas fa-user-check"></i></div>
                <div class="stat-info">
                    <h3><?php echo $active_users; ?></h3>
                    <p>Active Users</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-users.php?role=staff'">
                <div class="stat-icon pwd"><i class="fas fa-user-shield"></i></div>
                <div class="stat-info">
                    <h3><?php echo $staff_users; ?></h3>
                    <p>CSWDO Staff</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-users.php?role=resident'">
                <div class="stat-icon senior"><i class="fas fa-user-friends"></i></div>
                <div class="stat-info">
                    <h3><?php echo $resident_users; ?></h3>
                    <p>Residents</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions Bar -->
        <div class="quick-actions-bar">
            <div class="resident-info">
                <h4>Registered System Accounts</h4>
                <div class="count"><?php echo $total_users; ?></div>
            </div>
            <div class="quick-actions">
                <button onclick="openCreateModal()" class="quick-btn">
                    <i class="fas fa-user-plus"></i> Add New User
                </button>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" id="filterForm">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label><i class="fas fa-search"></i> Search</label>
                        <input type="text" name="search" placeholder="Username, email, or name..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-user-shield"></i> Role</label>
                        <select name="role">
                            <option value="">All Roles</option>
                            <option value="admin" <?php echo $filter_role == 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="staff" <?php echo $filter_role == 'staff' ? 'selected' : ''; ?>>CSWDO Staff</option>
                            <option value="resident" <?php echo $filter_role == 'resident' ? 'selected' : ''; ?>>Resident</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-filter"></i> Status</label>
                        <select name="status">
                            <option value="">All Statuses</option>
                            <option value="active" <?php echo $filter_status == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $filter_status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-search"></i> Apply
                        </button>
                        <?php if($search || $filter_role || $filter_status): ?>
                        <a href="manage-users.php" class="btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>User Profile</th>
                        <th>Email Address</th>
                        <th>Contact No.</th>
                        <th>System Role</th>
                        <th>Verification</th>
                        <th>Status</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?php echo $u['user_id']; ?></td>
                                <td>
                                    <div class="resident-cell">
                                        <div class="resident-avatar">
                                            <?php echo strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="name"><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></div>
                                            <div class="sub"><?php echo htmlspecialchars($u['username']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td><?php echo htmlspecialchars($u['contact_number'] ?? 'N/A'); ?></td>
                                <td><span class="badge-role role-<?php echo $u['role']; ?>"><?php echo $u['role']; ?></span></td>
                                <td><span class="badge-verify verify-<?php echo $u['verification_status']; ?>"><?php echo $u['verification_status']; ?></span></td>
                                <td><span class="badge-status status-<?php echo $u['status']; ?>"><?php echo $u['status']; ?></span></td>
                                <td style="text-align: center;">
                                    <div class="action-btns" style="justify-content: center;">
                                        <button class="btn-primary" style="padding: 6px 12px; font-size: 0.8rem; border-radius: 4px;" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($u)); ?>)" title="Edit User"><i class="fas fa-edit"></i></button>
                                        <button class="btn-secondary" style="padding: 6px 12px; font-size: 0.8rem; border-radius: 4px;" onclick="openResetModal(<?php echo $u['user_id']; ?>, '<?php echo htmlspecialchars($u['username']); ?>')" title="Reset Password"><i class="fas fa-key"></i></button>
                                        <?php if ($u['user_id'] != $_SESSION['user_id']): ?>
                                            <a href="manage-users.php?delete_id=<?php echo $u['user_id']; ?>" class="btn-secondary" style="padding: 6px 12px; font-size: 0.8rem; border-radius: 4px; background-color: var(--color-danger); color: white; border-color: var(--color-danger);" onclick="return confirm('Are you sure you want to delete this user?')" title="Delete User"><i class="fas fa-trash-alt"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="padding: 30px; text-align: center; color: var(--text-muted);">No users found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <div class="page-info">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_rows); ?> of <?php echo $total_rows; ?> entries
                </div>
                <div class="page-links">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $filter_role; ?>&status=<?php echo $filter_status; ?>"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>
                    <?php for($i=1; $i<=$total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $filter_role; ?>&status=<?php echo $filter_status; ?>" class="<?php echo $page == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $filter_role; ?>&status=<?php echo $filter_status; ?>"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create User Modal -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-plus"></i> Create User Account</h2>
            <span class="close" onclick="closeCreateModal()">&times;</span>
        </div>
        <form method="POST" action="manage-users.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="create_user" value="1">
            <div class="form-row">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" required placeholder="Enter username">
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" required placeholder="Enter temporary password">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" required placeholder="Enter first name">
                </div>
                <div class="form-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" required placeholder="Enter last name">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required placeholder="Enter email address">
                </div>
                <div class="form-group">
                    <label>Contact Number</label>
                    <input type="text" name="contact_number" placeholder="Enter contact number">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Role</label>
                    <select name="role">
                        <option value="resident">Resident</option>
                        <option value="staff">CSWDO Staff</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeCreateModal()">Cancel</button>
                <button type="submit" class="btn-primary">Save User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Edit User Account</h2>
            <span class="close" onclick="closeEditModal()">&times;</span>
        </div>
        <form method="POST" action="manage-users.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="update_user" value="1">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="form-row">
                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" id="edit_first_name" required>
                </div>
                <div class="form-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" id="edit_last_name" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" id="edit_email" required>
                </div>
                <div class="form-group">
                    <label>Contact Number</label>
                    <input type="text" name="contact_number" id="edit_contact_number">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="edit_role">
                        <option value="resident">Resident</option>
                        <option value="staff">CSWDO Staff</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Verification Status</label>
                <select name="verification_status" id="edit_verification_status">
                    <option value="pending">Pending</option>
                    <option value="verified">Verified</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetModal" class="modal">
    <div class="modal-content" style="width: 40%;">
        <div class="modal-header">
            <h2><i class="fas fa-key"></i> Reset Password</h2>
            <span class="close" onclick="closeResetModal()">&times;</span>
        </div>
        <form method="POST" action="manage-users.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="reset_password" value="1">
            <input type="hidden" name="user_id" id="reset_user_id">
            <div class="form-group">
                <label>Username</label>
                <input type="text" id="reset_username" disabled style="background: var(--border-color); font-weight: bold;">
            </div>
            <div class="form-group">
                <label>New Password *</label>
                <input type="password" name="new_password" required placeholder="Enter new password (min 8 chars)">
            </div>
            <div class="form-group">
                <label>Confirm Password *</label>
                <input type="password" name="confirm_password" required placeholder="Confirm new password">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeResetModal()">Cancel</button>
                <button type="submit" class="btn-primary">Update Password</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Search & Filter Actions
    function runSearch() {
        const query = document.getElementById('globalSearch').value;
        const role = document.querySelector('select[name="role"]').value;
        const status = document.querySelector('select[name="status"]').value;
        window.location.href = 'manage-users.php?search=' + encodeURIComponent(query) + '&role=' + role + '&status=' + status;
    }

    // Modal Control functions
    const createModal = document.getElementById('createModal');
    const editModal = document.getElementById('editModal');
    const resetModal = document.getElementById('resetModal');

    function openCreateModal() {
        createModal.style.display = 'block';
    }
    function closeCreateModal() {
        createModal.style.display = 'none';
    }

    function openEditModal(user) {
        document.getElementById('edit_user_id').value = user.user_id;
        document.getElementById('edit_first_name').value = user.first_name;
        document.getElementById('edit_last_name').value = user.last_name;
        document.getElementById('edit_email').value = user.email;
        document.getElementById('edit_contact_number').value = user.contact_number || '';
        document.getElementById('edit_role').value = user.role;
        document.getElementById('edit_status').value = user.status;
        document.getElementById('edit_verification_status').value = user.verification_status;
        editModal.style.display = 'block';
    }
    function closeEditModal() {
        editModal.style.display = 'none';
    }

    function openResetModal(userId, username) {
        document.getElementById('reset_user_id').value = userId;
        document.getElementById('reset_username').value = username;
        resetModal.style.display = 'block';
    }
    function closeResetModal() {
        resetModal.style.display = 'none';
    }

    // Close on overlay click
    window.onclick = function(event) {
        if (event.target == createModal) closeCreateModal();
        if (event.target == editModal) closeEditModal();
        if (event.target == resetModal) closeResetModal();
    }

    // Dark Mode integration toggle
    const themeToggleBtn = document.getElementById('themeToggle');
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const targetTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', targetTheme);
            localStorage.setItem('theme', targetTheme);
            const icon = themeToggleBtn.querySelector('i');
            icon.className = targetTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            document.cookie = `dark_mode=${targetTheme === 'dark' ? 'enabled' : 'disabled'}; path=/; max-age=31536000`;
        });
    }
</script>
</body>
</html>
