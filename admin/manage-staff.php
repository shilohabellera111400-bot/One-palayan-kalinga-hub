<?php
// admin/manage-staff.php
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Generate a new Employee ID and Staff Number for preview
$year = date('Y');
$count_query = "SELECT COUNT(*) as count FROM staff WHERE YEAR(created_at) = $year";
$count_result = $db->query($count_query);
$count = $count_result->fetch(PDO::FETCH_ASSOC)['count'];
$preview_employee_id = 'EMP-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
$preview_staff_number = 'STF-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

// Handle staff creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_staff'])) {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $middle_name = sanitize($_POST['middle_name'] ?? '');
    $contact_number = sanitize($_POST['contact_number']);
    $position = sanitize($_POST['position'] ?? 'Staff');
    $hire_date = sanitize($_POST['hire_date'] ?? date('Y-m-d'));
    
    // Get selected services (AICS, Housing, Senior, PWD)
    $assigned_services = isset($_POST['services']) ? $_POST['services'] : [];
    
    // Handle profile picture upload
    $profile_picture = '';
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_picture']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $upload_dir = __DIR__ . '/../uploads/profiles/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $new_filename = 'staff_' . time() . '_' . uniqid() . '.' . $ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                $profile_picture = 'uploads/profiles/' . $new_filename;
            } else {
                $error = "Failed to upload profile picture.";
            }
        } else {
            $error = "Invalid file type. Allowed: JPG, JPEG, PNG, GIF";
        }
    }
    
    // Validate
    if (empty($position)) {
        $error = "Position is required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters";
    } elseif (empty($assigned_services)) {
        $error = "Please select at least one service to assign";
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
            // Generate employee ID (format: EMP-YYYY-XXXX)
            $year = date('Y');
            $count_query = "SELECT COUNT(*) as count FROM staff WHERE YEAR(created_at) = $year";
            $count_result = $db->query($count_query);
            $count = $count_result->fetch(PDO::FETCH_ASSOC)['count'];
            $employee_id = 'EMP-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
            
            // Generate staff number (format: STF-YYYY-XXXX)
            $staff_number = 'STF-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
            
            $db->beginTransaction();
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $query = "INSERT INTO users (username, password, email, first_name, last_name, contact_number, role, status, profile_picture) 
                          VALUES (:username, :password, :email, :first_name, :last_name, :contact_number, 'staff', 'active', :profile_picture)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':first_name', $first_name);
                $stmt->bindParam(':last_name', $last_name);
                $stmt->bindParam(':contact_number', $contact_number);
                $stmt->bindParam(':profile_picture', $profile_picture);
                $stmt->execute();
                
                $user_id = $db->lastInsertId();
                
                // Build position string from selected services
                $position_list = [];
                $service_map = [
                    'AICS' => 'AICS Staff',
                    'HOUSING' => 'Housing Staff',
                    'SENIOR' => 'Senior Citizen Staff',
                    'PWD' => 'PWD Staff'
                ];
                
                foreach ($assigned_services as $svc) {
                    if (isset($service_map[$svc])) {
                        $position_list[] = $service_map[$svc];
                    }
                }
                $position = !empty($position_list) ? implode(', ', $position_list) : 'Staff';
                
                $staff_query = "INSERT INTO staff (user_id, staff_number, first_name, last_name, middle_name, position, contact_number, specialization, employee_id, hire_date, is_active) 
                                VALUES (:user_id, :staff_number, :first_name, :last_name, :middle_name, :position, :contact_number, :specialization, :employee_id, :hire_date, 1)";
                $staff_stmt = $db->prepare($staff_query);
                $staff_stmt->bindParam(':user_id', $user_id);
                $staff_stmt->bindParam(':staff_number', $staff_number);
                $staff_stmt->bindParam(':first_name', $first_name);
                $staff_stmt->bindParam(':last_name', $last_name);
                $staff_stmt->bindParam(':middle_name', $middle_name);
                $staff_stmt->bindParam(':position', $position);
                $staff_stmt->bindParam(':contact_number', $contact_number);
                $specialization = implode(', ', $assigned_services);
                $staff_stmt->bindParam(':specialization', $specialization);
                $staff_stmt->bindParam(':employee_id', $employee_id);
                $staff_stmt->bindParam(':hire_date', $hire_date);
                $staff_stmt->execute();
                
                $staff_id = $db->lastInsertId();
                
                // Map form values to exact category names in database
                $category_mapping = [
                    'AICS' => 'AICS',
                    'HOUSING' => 'Housing',
                    'SENIOR' => 'Senior Citizen',
                    'PWD' => 'PWD'
                ];
                
                foreach ($assigned_services as $service_key) {
                    if (isset($category_mapping[$service_key])) {
                        $category_name = $category_mapping[$service_key];
                        
                        // Get category_id from service_categories
                        $cat_query = "SELECT category_id FROM service_categories WHERE category_name = :category_name AND is_active = 1 LIMIT 1";
                        $cat_stmt = $db->prepare($cat_query);
                        $cat_stmt->bindParam(':category_name', $category_name);
                        $cat_stmt->execute();
                        $category = $cat_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($category) {
                            // Insert into staff_service_assignments
                            $assign_query = "INSERT INTO staff_service_assignments (staff_id, category_id, is_active, assigned_at) 
                                             VALUES (:staff_id, :category_id, 1, NOW())";
                            $assign_stmt = $db->prepare($assign_query);
                            $assign_stmt->bindParam(':staff_id', $staff_id);
                            $assign_stmt->bindParam(':category_id', $category['category_id']);
                            $assign_stmt->execute();
                        }
                    }
                }
                
                $db->commit();
                $success = "Staff account created successfully!<br>Employee ID: <strong>$employee_id</strong> | Staff Number: <strong>$staff_number</strong>";
                
                header("Location: manage-staff.php");
                exit();
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Failed to create staff: " . $e->getMessage();
            }
        }
    }
}

// Handle staff update / edit (including password reset & duty status)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_staff'])) {
    $staff_id = intval($_POST['staff_id'] ?? 0);
    $user_id = intval($_POST['user_id'] ?? 0);

    // Verify staff exists
    $verify_stmt = $db->prepare("SELECT s.*, u.email as user_email, u.username as user_uname, u.profile_picture as user_pic 
                                 FROM staff s 
                                 JOIN users u ON s.user_id = u.user_id 
                                 WHERE s.id = :id");
    $verify_stmt->execute([':id' => $staff_id]);
    $current_staff = $verify_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current_staff) {
        $error = "Staff record not found.";
    } else {
        $first_name = sanitize($_POST['first_name'] ?? '');
        $last_name = sanitize($_POST['last_name'] ?? '');
        $middle_name = sanitize($_POST['middle_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $contact_number = sanitize($_POST['contact_number'] ?? '');
        $position = sanitize($_POST['position'] ?? 'Staff');
        $hire_date = sanitize($_POST['hire_date'] ?? date('Y-m-d'));
        $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
        $assigned_services = isset($_POST['services']) && is_array($_POST['services']) ? $_POST['services'] : [];

        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $password_changed = false;

        // Validate names & email
        if (empty($first_name) || empty($last_name)) {
            $error = "First name and last name are required.";
        } elseif (empty($email)) {
            $error = "Email address is required.";
        } elseif (empty($assigned_services)) {
            $error = "Please select at least one assigned service.";
        }

        // Check email uniqueness if changed
        if (empty($error) && strtolower($email) !== strtolower($current_staff['user_email'])) {
            $chk = $db->prepare("SELECT user_id FROM users WHERE email = :email AND user_id != :uid");
            $chk->execute([':email' => $email, ':uid' => $current_staff['user_id']]);
            if ($chk->rowCount() > 0) {
                $error = "The email address is already in use by another account.";
            }
        }

        // Password validation if admin is setting/resetting password
        if (empty($error) && !empty($password)) {
            if ($password !== $confirm_password) {
                $error = "New password and password confirmation do not match.";
            } elseif (strlen($password) < 8) {
                $error = "New password must be at least 8 characters.";
            } else {
                $password_changed = true;
            }
        }

        // Handle profile picture upload if provided
        $profile_picture = $current_staff['user_pic'];
        if (empty($error) && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_picture']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {
                $upload_dir = __DIR__ . '/../uploads/profiles/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $new_filename = 'staff_' . time() . '_' . uniqid() . '.' . $ext;
                $upload_path = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                    $profile_picture = 'uploads/profiles/' . $new_filename;
                } else {
                    $error = "Failed to upload new profile picture.";
                }
            } else {
                $error = "Invalid file type for profile picture. Allowed: JPG, JPEG, PNG, GIF";
            }
        }

        if (empty($error)) {
            $db->beginTransaction();
            try {
                $user_status = $is_active == 1 ? 'active' : 'inactive';
                if ($password_changed) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $u_stmt = $db->prepare("UPDATE users SET first_name = :fn, last_name = :ln, email = :em, contact_number = :cn, status = :st, is_active = :ia, profile_picture = :pic, password = :pwd WHERE user_id = :uid");
                    $u_stmt->execute([
                        ':fn' => $first_name,
                        ':ln' => $last_name,
                        ':em' => $email,
                        ':cn' => $contact_number,
                        ':st' => $user_status,
                        ':ia' => $is_active,
                        ':pic' => $profile_picture,
                        ':pwd' => $hashed_password,
                        ':uid' => $current_staff['user_id']
                    ]);
                } else {
                    $u_stmt = $db->prepare("UPDATE users SET first_name = :fn, last_name = :ln, email = :em, contact_number = :cn, status = :st, is_active = :ia, profile_picture = :pic WHERE user_id = :uid");
                    $u_stmt->execute([
                        ':fn' => $first_name,
                        ':ln' => $last_name,
                        ':em' => $email,
                        ':cn' => $contact_number,
                        ':st' => $user_status,
                        ':ia' => $is_active,
                        ':pic' => $profile_picture,
                        ':uid' => $current_staff['user_id']
                    ]);
                }

                // Build specialization string
                $specialization = implode(', ', $assigned_services);

                // Update staff table
                $s_stmt = $db->prepare("UPDATE staff SET first_name = :fn, last_name = :ln, middle_name = :mn, position = :pos, contact_number = :cn, hire_date = :hd, is_active = :ia, specialization = :spec WHERE id = :id");
                $s_stmt->execute([
                    ':fn' => $first_name,
                    ':ln' => $last_name,
                    ':mn' => $middle_name,
                    ':pos' => $position,
                    ':cn' => $contact_number,
                    ':hd' => $hire_date,
                    ':ia' => $is_active,
                    ':spec' => $specialization,
                    ':id' => $staff_id
                ]);

                // Update service assignments
                $db->prepare("UPDATE staff_service_assignments SET is_active = 0 WHERE staff_id = :id")->execute([':id' => $staff_id]);

                $category_mapping = [
                    'AICS' => 'AICS',
                    'HOUSING' => 'Housing',
                    'SENIOR' => 'Senior Citizen',
                    'PWD' => 'PWD'
                ];

                foreach ($assigned_services as $svc_key) {
                    if (isset($category_mapping[$svc_key])) {
                        $cat_name = $category_mapping[$svc_key];
                        $cat_query = "SELECT category_id FROM service_categories WHERE category_name = :cname LIMIT 1";
                        $c_stmt = $db->prepare($cat_query);
                        $c_stmt->execute([':cname' => $cat_name]);
                        $cat_row = $c_stmt->fetch(PDO::FETCH_ASSOC);

                        if ($cat_row) {
                            $cat_id = $cat_row['category_id'];
                            $exist_stmt = $db->prepare("SELECT id FROM staff_service_assignments WHERE staff_id = :sid AND category_id = :cid");
                            $exist_stmt->execute([':sid' => $staff_id, ':cid' => $cat_id]);
                            if ($exist_stmt->rowCount() > 0) {
                                $db->prepare("UPDATE staff_service_assignments SET is_active = 1 WHERE staff_id = :sid AND category_id = :cid")->execute([':sid' => $staff_id, ':cid' => $cat_id]);
                            } else {
                                $db->prepare("INSERT INTO staff_service_assignments (staff_id, category_id, is_active, assigned_at) VALUES (:sid, :cid, 1, NOW())")->execute([':sid' => $staff_id, ':cid' => $cat_id]);
                            }
                        }
                    }
                }

                // Audit log
                $admin_id = intval($_SESSION['user_id'] ?? 0);
                if ($admin_id > 0) {
                    $details = "Staff #$staff_id ($first_name $last_name) updated by Admin." . ($password_changed ? " Password was also reset." : "");
                    $db->prepare("INSERT INTO audit_logs (user_id, action, table_affected, new_values) VALUES (:aid, 'update_staff', 'staff', :details)")->execute([':aid' => $admin_id, ':details' => $details]);
                }

                $db->commit();

                if ($password_changed) {
                    $success = "Staff account <strong>" . htmlspecialchars($first_name . ' ' . $last_name) . "</strong> updated successfully, and their <strong>password was reset</strong>!";
                } else {
                    $success = "Staff account <strong>" . htmlspecialchars($first_name . ' ' . $last_name) . "</strong> updated successfully!";
                }

            } catch (Exception $e) {
                $db->rollBack();
                $error = "Failed to update staff: " . $e->getMessage();
            }
        }
    }
}

// Handle staff status toggle
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $staff_id = sanitize($_GET['id']);
    
    $query = "SELECT is_active FROM staff WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $staff_id);
    $stmt->execute();
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($staff) {
        $new_status = $staff['is_active'] == 1 ? 0 : 1;
        $update = "UPDATE staff SET is_active = :status WHERE id = :id";
        $update_stmt = $db->prepare($update);
        $update_stmt->bindParam(':status', $new_status);
        $update_stmt->bindParam(':id', $staff_id);
        $update_stmt->execute();
        
        $user_update = "UPDATE users SET status = :status WHERE user_id = (SELECT user_id FROM staff WHERE id = :id)";
        $user_stmt = $db->prepare($user_update);
        $user_status = $new_status == 1 ? 'active' : 'inactive';
        $user_stmt->bindParam(':status', $user_status);
        $user_stmt->bindParam(':id', $staff_id);
        $user_stmt->execute();
        
        $success = "Staff status updated!";
        header("Location: manage-staff.php");
        exit();
    }
}

// Handle staff deletion
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $staff_id = sanitize($_GET['id']);
    
    try {
        $db->beginTransaction();
        
        $get_user = "SELECT user_id FROM staff WHERE id = :id";
        $get_stmt = $db->prepare($get_user);
        $get_stmt->bindParam(':id', $staff_id);
        $get_stmt->execute();
        $staff_data = $get_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($staff_data) {
            $user_id = $staff_data['user_id'];
            
            // Delete profile picture if exists
            $get_pic = "SELECT profile_picture FROM users WHERE user_id = :user_id";
            $pic_stmt = $db->prepare($get_pic);
            $pic_stmt->bindParam(':user_id', $user_id);
            $pic_stmt->execute();
            $pic_data = $pic_stmt->fetch(PDO::FETCH_ASSOC);
            if ($pic_data && !empty($pic_data['profile_picture'])) {
                $file_path = __DIR__ . '/../' . $pic_data['profile_picture'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            
            $delete_assignments = "DELETE FROM staff_service_assignments WHERE staff_id = :id";
            $del_assign = $db->prepare($delete_assignments);
            $del_assign->bindParam(':id', $staff_id);
            $del_assign->execute();
            
            $delete_staff = "DELETE FROM staff WHERE id = :id";
            $del_stmt = $db->prepare($delete_staff);
            $del_stmt->bindParam(':id', $staff_id);
            $del_stmt->execute();
            
            $delete_audit_logs = "DELETE FROM audit_logs WHERE user_id = :user_id";
            $del_audit = $db->prepare($delete_audit_logs);
            $del_audit->bindParam(':user_id', $user_id);
            $del_audit->execute();
            
            $delete_notifications = "DELETE FROM notifications WHERE user_id = :user_id";
            $del_notif = $db->prepare($delete_notifications);
            $del_notif->bindParam(':user_id', $user_id);
            $del_notif->execute();
            
            $delete_verifications = "DELETE FROM verification_requests WHERE user_id = :user_id";
            $del_verif = $db->prepare($delete_verifications);
            $del_verif->bindParam(':user_id', $user_id);
            $del_verif->execute();
            
            $delete_user = "DELETE FROM users WHERE user_id = :user_id";
            $del_user = $db->prepare($delete_user);
            $del_user->bindParam(':user_id', $user_id);
            $del_user->execute();
            
            $db->commit();
            $success = "Staff account deleted successfully!";
            header("Location: manage-staff.php");
            exit();
        }
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Failed to delete staff: " . $e->getMessage();
        error_log("Delete staff error: " . $e->getMessage());
    }
}

// Pagination settings
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM staff s JOIN users u ON s.user_id = u.user_id WHERE u.role = 'staff'";
$count_result = $db->query($count_query);
$total_records = $count_result->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $per_page);

// Get all staff with their assigned services with pagination
$query = "SELECT s.*, u.username, u.email, u.status as user_status, u.profile_picture 
          FROM staff s
          JOIN users u ON s.user_id = u.user_id
          WHERE u.role = 'staff'
          ORDER BY s.created_at DESC
          LIMIT :offset, :per_page";
$stmt = $db->prepare($query);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();
$staff_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get staff assigned services for each staff
$staff_services = [];
foreach ($staff_list as $staff) {
    $serv_query = "SELECT sc.category_name 
                   FROM staff_service_assignments ssa
                   JOIN service_categories sc ON ssa.category_id = sc.category_id
                   WHERE ssa.staff_id = :staff_id AND ssa.is_active = 1";
    $serv_stmt = $db->prepare($serv_query);
    $serv_stmt->bindParam(':staff_id', $staff['id']);
    $serv_stmt->execute();
    $staff_services[$staff['id']] = $serv_stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get all staff count for stats (without pagination)
$all_staff_query = "SELECT s.* FROM staff s JOIN users u ON s.user_id = u.user_id WHERE u.role = 'staff'";

// Page configuration
$page_title = 'Manage Staff';
$active_sidebar = 'staff';
$active_nav = 'staff';
$show_form = isset($_GET['show_form']) || (isset($_POST['create_staff']) && !empty($error));

require_once __DIR__ . '/includes/admin-head.php';
?>

<style>
    /* ========== PAGE SPECIFIC LAYOUT & COMPONENTS ========== */
    .main-content {
        padding: 24px 32px !important;
    }

    /* Subheader Branding */
    .dash-subheader {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }
    .subheader-title-wrap h1 {
        font-size: 1.55rem;
        font-weight: 800;
        color: var(--text-primary);
        margin: 0 0 4px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .subheader-title-wrap p {
        font-size: 0.85rem;
        color: var(--text-muted);
        margin: 0;
    }
    @font-face {
        font-family: 'Bulgash';
        src: url('../fonts/Bulgash.otf') format('opentype'),
             url('fonts/Bulgash.otf') format('opentype');
        font-weight: normal;
        font-style: normal;
        font-display: swap;
    }

    .dash-cursive-branding-inline {
        margin-left: auto;
        padding-right: 4px;
    }
    .dash-cursive-title {
        font-family: 'Bulgash', 'Playfair Display', cursive, serif !important;
        font-size: 2.1rem !important;
        font-weight: normal !important;
        font-style: normal !important;
        color: var(--ref-maroon, #7F0303) !important;
        margin: 0 !important;
        white-space: nowrap !important;
        user-select: none;
    }
    [data-theme="dark"] .dash-cursive-title {
        color: #F87171 !important;
    }
    .subheader-status-wrap {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    /* Statistics Row */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 18px;
        margin-bottom: 24px;
    }
    .stat-card {
        background: var(--bg-card, #ffffff);
        border: 1px solid var(--border-color, #C8D0DC);
        border-radius: var(--radius-card, 20px);
        padding: 18px 22px;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: var(--shadow-sm);
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-md);
    }
    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        flex-shrink: 0;
    }
    .stat-icon.total {
        background: rgba(127, 3, 3, 0.1);
        color: var(--maroon, #7F0303);
    }
    .stat-icon.active {
        background: rgba(76, 175, 80, 0.12);
        color: #2E7D32;
    }
    .stat-icon.inactive {
        background: rgba(244, 67, 54, 0.12);
        color: #C62828;
    }
    .stat-info h3 {
        font-size: 1.6rem;
        font-weight: 800;
        margin: 0 0 2px;
        color: var(--text-primary);
        line-height: 1.1;
    }
    .stat-info p {
        margin: 0;
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Table Card */
    .table-card {
        background: var(--bg-card, #ffffff);
        border: 1px solid var(--border-color, #C8D0DC);
        border-radius: var(--radius-card, 20px);
        padding: 24px;
        box-shadow: var(--shadow-sm);
        margin-bottom: 30px;
    }
    .card-header-flex {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 12px;
    }
    .card-header-flex h2 {
        font-size: 1.15rem;
        font-weight: 700;
        margin: 0;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .card-header-flex h2 i {
        color: var(--maroon, #7F0303);
    }
    [data-theme="dark"] .card-header-flex h2 i {
        color: var(--maroon-light, #ff7b7b);
    }
    .card-actions-right {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .count-badge {
        background: var(--badge-bg, rgba(0,0,0,0.05));
        color: var(--text-muted);
        padding: 5px 14px;
        border-radius: 50px;
        font-size: 0.76rem;
        font-weight: 600;
    }

    /* Table & Container */
    .table-container {
        overflow-x: auto;
        border-radius: 14px;
        border: 1px solid var(--border-color, #C8D0DC);
    }
    .data-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: 0.85rem;
    }
    .data-table thead tr {
        background: rgba(15, 65, 74, 0.05);
        border-bottom: 1.5px solid var(--border-color, #C8D0DC);
    }
    [data-theme="dark"] .data-table thead tr {
        background: rgba(255, 255, 255, 0.03);
    }
    .data-table th {
        padding: 13px 16px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: var(--text-muted);
        white-space: nowrap;
    }
    .data-table td {
        padding: 14px 16px;
        border-bottom: 1px solid var(--border-color, #C8D0DC);
        color: var(--text-primary);
        vertical-align: middle;
    }
    .data-table tbody tr:hover {
        background: rgba(127, 3, 3, 0.02);
    }
    [data-theme="dark"] .data-table tbody tr:hover {
        background: rgba(255, 255, 255, 0.02);
    }

    /* Column Sizing */
    .col-id { width: 130px; }
    .col-name { min-width: 220px; }
    .col-position { min-width: 160px; }
    .col-contact { min-width: 130px; }
    .col-email { min-width: 200px; }
    .col-services { min-width: 170px; }
    .col-status { width: 110px; text-align: center; }
    .col-actions { width: 240px; min-width: 240px; text-align: center; white-space: nowrap; }

    /* Badges & Pills */
    .emp-id-badge {
        display: inline-block;
        padding: 3px 9px;
        background: rgba(127, 3, 3, 0.08);
        color: var(--maroon, #7F0303);
        font-weight: 700;
        font-size: 0.74rem;
        border-radius: 6px;
        letter-spacing: 0.3px;
        white-space: nowrap;
    }
    [data-theme="dark"] .emp-id-badge {
        background: rgba(255, 123, 123, 0.15);
        color: #ff9b9b;
    }

    .staff-cell {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .staff-avatar-small {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        overflow: hidden;
        background: rgba(127, 3, 3, 0.1);
        color: var(--maroon, #7F0303);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.8rem;
        flex-shrink: 0;
        border: 1.5px solid rgba(127, 3, 3, 0.15);
    }
    .staff-avatar-small img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .staff-name {
        font-weight: 700;
        color: var(--text-primary);
        font-size: 0.88rem;
        line-height: 1.2;
    }
    .staff-id-small {
        font-size: 0.72rem;
        color: var(--text-muted);
        margin-top: 2px;
    }

    .position-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 11px;
        background: rgba(0, 0, 0, 0.03);
        color: var(--text-primary);
        border: 1px solid var(--border-color, #C8D0DC);
        border-radius: 50px;
        font-size: 0.73rem;
        font-weight: 600;
        white-space: nowrap;
    }
    [data-theme="dark"] .position-pill {
        background: rgba(255, 255, 255, 0.05);
    }
    .position-pill i {
        color: var(--maroon, #7F0303);
        font-size: 0.72rem;
    }
    [data-theme="dark"] .position-pill i {
        color: var(--maroon-light, #ff7b7b);
    }

    .contact-info, .email-info {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.82rem;
        color: var(--text-secondary);
        white-space: nowrap;
    }
    .contact-info i, .email-info i {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    /* UNIFIED SERVICES PILL - Horizontal & Clean */
    .service-unified-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        background: rgba(127, 3, 3, 0.08);
        color: var(--maroon, #7F0303);
        border: 1px solid rgba(127, 3, 3, 0.18);
        border-radius: 50px;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.2px;
        white-space: nowrap;
    }
    [data-theme="dark"] .service-unified-pill {
        background: rgba(255, 123, 123, 0.12);
        color: #ff9b9b;
        border-color: rgba(255, 123, 123, 0.25);
    }
    .service-unified-pill i {
        font-size: 0.72rem;
        color: var(--maroon, #7F0303);
    }
    [data-theme="dark"] .service-unified-pill i {
        color: #ff9b9b;
    }

    /* Duty Status Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 50px;
        font-size: 0.72rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .status-badge.on-duty {
        background: rgba(76, 175, 80, 0.12);
        color: #2E7D32;
        border: 1px solid rgba(76, 175, 80, 0.25);
    }
    .status-badge.off-duty {
        background: rgba(244, 67, 54, 0.12);
        color: #C62828;
        border: 1px solid rgba(244, 67, 54, 0.25);
    }

    /* Action Buttons */
    .btn-sm {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 11px;
        border-radius: 50px;
        text-decoration: none;
        font-size: 0.72rem;
        font-weight: 600;
        transition: all 0.2s ease;
        border: none;
        cursor: pointer;
        line-height: 1.4;
    }
    .btn-sm:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-sm);
    }
    .btn-edit {
        background: rgba(33, 150, 243, 0.12);
        color: #1976D2;
        border: 1px solid rgba(33, 150, 243, 0.25);
    }
    .btn-edit:hover {
        background: #1976D2;
        color: white;
    }
    .btn-toggle {
        background: rgba(255, 152, 0, 0.12);
        color: #F57C00;
        border: 1px solid rgba(255, 152, 0, 0.25);
    }
    .btn-toggle:hover {
        background: #F57C00;
        color: white;
    }
    .btn-delete {
        background: rgba(244, 67, 54, 0.12);
        color: #D32F2F;
        border: 1px solid rgba(244, 67, 54, 0.25);
    }
    .btn-delete:hover {
        background: #D32F2F;
        color: white;
    }

    /* Modal Styling */
    .staff-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(10, 14, 23, 0.75);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        opacity: 0;
        visibility: hidden;
        transition: all 0.25s ease-in-out;
    }
    .staff-modal-overlay.active {
        opacity: 1;
        visibility: visible;
    }
    .staff-modal-dialog {
        background: var(--bg-card, #FFFFFF);
        color: var(--text-primary);
        border-radius: var(--radius-card, 20px);
        box-shadow: var(--shadow-lg, 0 16px 48px rgba(0,0,0,0.22));
        width: 100%;
        max-width: 680px;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transform: scale(0.96) translateY(10px);
        transition: transform 0.25s ease-in-out;
        border: 1px solid var(--border-color, rgba(0,0,0,0.08));
    }
    .staff-modal-overlay.active .staff-modal-dialog {
        transform: scale(1) translateY(0);
    }
    .staff-modal-header {
        padding: 18px 24px;
        border-bottom: 1px solid var(--border-color, rgba(0,0,0,0.08));
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: var(--bg-secondary, rgba(0,0,0,0.02));
    }
    .staff-modal-header h3 {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--maroon, #7F0303);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    [data-theme="dark"] .staff-modal-header h3 {
        color: var(--maroon-light, #ff7b7b);
    }
    .staff-modal-close-btn {
        background: transparent;
        border: none;
        color: var(--text-muted);
        font-size: 1.6rem;
        line-height: 1;
        cursor: pointer;
        padding: 0 4px;
        transition: color 0.2s;
    }
    .staff-modal-close-btn:hover {
        color: #EF5350;
    }
    .staff-modal-body {
        padding: 24px;
        overflow-y: auto;
        flex: 1;
    }
    .staff-modal-footer {
        padding: 16px 24px;
        border-top: 1px solid var(--border-color, rgba(0,0,0,0.08));
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        background: var(--bg-secondary, rgba(0,0,0,0.02));
    }

    /* Form Layouts inside Modal */
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 16px;
    }
    .form-group {
        margin-bottom: 16px;
    }
    .form-group label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        margin-bottom: 6px;
        color: var(--text-primary);
    }
    .form-group label .required {
        color: #DC2626;
    }
    .form-group input, .form-group select {
        width: 100%;
        padding: 9px 14px;
        border: 1.5px solid var(--border-color, #C8D0DC);
        border-radius: 10px;
        font-size: 0.85rem;
        background: var(--bg-primary, #ffffff);
        color: var(--text-primary);
        box-sizing: border-box;
        transition: border-color 0.2s;
    }
    .form-group input:focus, .form-group select:focus {
        border-color: var(--maroon, #7F0303);
        outline: none;
    }
    .profile-upload {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .profile-upload .preview {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        overflow: hidden;
        background: rgba(127,3,3,0.08);
        color: var(--maroon, #7F0303);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        border: 2px solid var(--border-color);
        flex-shrink: 0;
    }
    .profile-upload .preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .profile-upload .upload-info {
        flex: 1;
    }
    .profile-upload .upload-info p {
        font-size: 0.74rem;
        color: var(--text-muted);
        margin: 4px 0 0;
    }

    .checkbox-group {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-top: 6px;
    }
    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        background: var(--bg-primary, #ffffff);
        border: 1.5px solid var(--border-color, #C8D0DC);
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .checkbox-item input[type="checkbox"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
        accent-color: var(--maroon, #7F0303);
    }
    .checkbox-item label {
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        margin: 0;
        color: var(--text-primary);
    }

    /* Pagination */
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        margin-top: 24px;
        padding-top: 16px;
        border-top: 1px solid var(--border-color, #C8D0DC);
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
        transition: all 0.25s;
        border: 1px solid var(--border-color);
        background: var(--bg-card);
        color: var(--text-primary);
    }
    .pagination a:hover, .pagination .active {
        background: var(--maroon, #7F0303);
        color: white;
        border-color: var(--maroon, #7F0303);
    }
    .pagination .disabled {
        opacity: 0.45;
        cursor: not-allowed;
        pointer-events: none;
    }

    @media (max-width: 992px) {
        .main-content { padding: 20px 16px !important; }
        .stats-row { grid-template-columns: 1fr; }
        .form-row { grid-template-columns: 1fr; }
        .checkbox-group { grid-template-columns: 1fr; }
    }
</style>

<?php
require_once __DIR__ . '/includes/admin-sidebar.php';
require_once __DIR__ . '/includes/admin-header.php';
?>

<!-- Subheader Branding -->
<div class="dash-subheader">
    <div class="subheader-title-wrap">
        <h1><i class="fas fa-users-cog" style="color: var(--ref-maroon, #7F0303);"></i> Manage Staff</h1>
        <p>CSWDO employee directory, service assignments, and duty status</p>
    </div>
    <div class="dash-cursive-branding-inline">
        <h2 class="dash-cursive-title">One Palayan - Kalinga Hub</h2>
    </div>
</div>

<!-- Flash Alerts -->
<?php if(!empty($error)): ?>
    <div class="alert alert-error" style="padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; background: rgba(244, 67, 54, 0.12); color: #EF5350; border: 1px solid rgba(244, 67, 54, 0.25);">
        <i class="fas fa-exclamation-circle" style="font-size: 1.1rem;"></i>
        <span><?php echo strip_tags($error, '<strong><b><br><i>'); ?></span>
    </div>
<?php endif; ?>
<?php if(!empty($success)): ?>
    <div class="alert alert-success" style="padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; background: rgba(76, 175, 80, 0.12); color: #2E7D32; border: 1px solid rgba(76, 175, 80, 0.25);">
        <i class="fas fa-check-circle" style="font-size: 1.1rem;"></i>
        <span><?php echo strip_tags($success, '<strong><b><br><i>'); ?></span>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="stats-row">
    <div class="stat-card">
        <div class="stat-icon total"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format($total_records); ?></h3>
            <p>Total Employees</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon active"><i class="fas fa-user-check"></i></div>
        <div class="stat-info">
            <?php 
            $active_count = 0;
            $inactive_count = 0;
            if (!empty($all_staff)) {
                foreach ($all_staff as $as) {
                    if (($as['is_active'] ?? 1) == 1) $active_count++;
                    else $inactive_count++;
                }
            }
            ?>
            <h3><?php echo number_format($active_count); ?></h3>
            <p>Active (On Duty)</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon inactive"><i class="fas fa-user-slash"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format($inactive_count); ?></h3>
            <p>Inactive (Off Duty)</p>
        </div>
    </div>
</div>

<!-- Employees Table Card -->
<div class="table-card">
    <div class="card-header-flex">
        <h2><i class="fas fa-list"></i> Employees</h2>
        <div class="card-actions-right">
            <span class="count-badge">Showing <?php echo $total_records; ?> records</span>
            <button type="button" class="portal-hdr-pill" onclick="openStaffModal()" style="cursor: pointer; border: none; background: var(--ref-maroon, #7F0303); color: #fff; display: inline-flex; align-items: center; gap: 6px; font-weight: 700; padding: 8px 18px; border-radius: 50px; font-size: 0.82rem;">
                <i class="fas fa-plus"></i> Add Employee
            </button>
        </div>
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-id">ID</th>
                    <th class="col-name">Name</th>
                    <th class="col-position">Position</th>
                    <th class="col-contact">Contact</th>
                    <th class="col-email">Email</th>
                    <th class="col-services">Assigned Services</th>
                    <th class="col-status">Status</th>
                    <th class="col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($staff_list)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 48px 20px; color: var(--text-muted);">
                            <i class="fas fa-users" style="font-size: 2.5rem; opacity: 0.3; margin-bottom: 10px; display: block;"></i>
                            No employees found. Click <strong>Add Employee</strong> to create one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach($staff_list as $staff): 
                        $staff_initials = strtoupper(substr($staff['first_name'] ?? 'S', 0, 1) . substr($staff['last_name'] ?? 'T', 0, 1));
                        $profile_pic = !empty($staff['profile_picture']) && file_exists(__DIR__ . '/../' . $staff['profile_picture']) 
                            ? '../' . $staff['profile_picture'] 
                            : '';
                        
                        $services = isset($staff_services[$staff['id']]) ? $staff_services[$staff['id']] : [];
                        $clean_services = [];
                        foreach ($services as $svc) {
                            $trimmed = trim($svc);
                            if (in_array($trimmed, ['Senior Citizen', 'Senior', 'PWD'])) continue;
                            $clean_services[] = $trimmed;
                        }

                        $raw_pos = trim($staff['position'] ?? 'Staff');
                        if (strpos($raw_pos, ',') !== false || strlen($raw_pos) > 28) {
                            $display_pos = 'CSWDO Caseworker';
                        } else {
                            $display_pos = $raw_pos ?: 'Staff';
                        }

                        $staff_modal_data = [
                            'id' => (int)$staff['id'],
                            'user_id' => (int)$staff['user_id'],
                            'employee_id' => $staff['employee_id'] ?? '',
                            'staff_number' => $staff['staff_number'] ?? '',
                            'first_name' => $staff['first_name'] ?? '',
                            'middle_name' => $staff['middle_name'] ?? '',
                            'last_name' => $staff['last_name'] ?? '',
                            'username' => $staff['username'] ?? '',
                            'email' => $staff['email'] ?? '',
                            'contact_number' => $staff['contact_number'] ?? '',
                            'position' => $staff['position'] ?? '',
                            'hire_date' => !empty($staff['hire_date']) ? date('Y-m-d', strtotime($staff['hire_date'])) : date('Y-m-d'),
                            'is_active' => (int)($staff['is_active'] ?? 1),
                            'profile_picture' => !empty($staff['profile_picture']) && file_exists(__DIR__ . '/../' . $staff['profile_picture']) ? '../' . $staff['profile_picture'] : '',
                            'services' => $clean_services
                        ];
                    ?>
                    <tr>
                        <td class="col-id">
                            <span class="emp-id-badge"><?php echo htmlspecialchars($staff['employee_id'] ?? 'N/A'); ?></span>
                        </td>
                        <td class="col-name">
                            <div class="staff-cell">
                                <div class="staff-avatar-small">
                                    <?php if(!empty($profile_pic)): ?>
                                        <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile">
                                    <?php else: ?>
                                        <span><?php echo $staff_initials; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="staff-name"><?php echo htmlspecialchars(($staff['first_name'] ?? '') . ' ' . ($staff['last_name'] ?? '')); ?></div>
                                    <div class="staff-id-small"><?php echo htmlspecialchars($staff['staff_number'] ?? ''); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="col-position">
                            <span class="position-pill">
                                <i class="fas fa-briefcase"></i>
                                <?php echo htmlspecialchars($display_pos); ?>
                            </span>
                        </td>
                        <td class="col-contact">
                            <span class="contact-info">
                                <i class="fas fa-phone-alt"></i>
                                <?php echo htmlspecialchars(!empty($staff['contact_number']) ? $staff['contact_number'] : '—'); ?>
                            </span>
                        </td>
                        <td class="col-email">
                            <span class="email-info" title="<?php echo htmlspecialchars($staff['email'] ?? ''); ?>">
                                <i class="fas fa-envelope"></i>
                                <?php echo htmlspecialchars($staff['email'] ?? ''); ?>
                            </span>
                        </td>
                        <td class="col-services">
                            <?php if (!empty($clean_services)): 
                                $unified_text = implode(' • ', $clean_services);
                            ?>
                                <span class="service-unified-pill" title="<?php echo htmlspecialchars(implode(', ', $clean_services)); ?>">
                                    <i class="fas fa-layer-group"></i>
                                    <?php echo htmlspecialchars($unified_text); ?>
                                </span>
                            <?php else: ?>
                                <span class="service-unified-pill" style="background: rgba(0,0,0,0.04); color: var(--text-muted); border-color: var(--border-color);" title="No services assigned">
                                    <i class="fas fa-minus-circle"></i> Unassigned
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="col-status" style="text-align: center;">
                            <span class="status-badge <?php echo ($staff['is_active'] ?? 1) == 1 ? 'on-duty' : 'off-duty'; ?>">
                                <i class="fas fa-<?php echo ($staff['is_active'] ?? 1) == 1 ? 'check-circle' : 'times-circle'; ?>"></i>
                                <?php echo ($staff['is_active'] ?? 1) == 1 ? 'On Duty' : 'Off Duty'; ?>
                            </span>
                        </td>
                        <td class="col-actions" style="text-align: center;">
                            <div style="display: inline-flex; gap: 6px; justify-content: center; align-items: center; white-space: nowrap;">
                                <button type="button" class="btn-sm btn-edit" onclick="openEditStaffModal(<?php echo htmlspecialchars(json_encode($staff_modal_data), ENT_QUOTES, 'UTF-8'); ?>)" title="Edit Staff & Reset Password">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <a href="?toggle_status=1&id=<?php echo $staff['id']; ?>" class="btn-sm btn-toggle" title="Change duty status">
                                    <i class="fas fa-toggle-<?php echo ($staff['is_active'] ?? 1) == 1 ? 'off' : 'on'; ?>"></i>
                                    <?php echo ($staff['is_active'] ?? 1) == 1 ? 'Off Duty' : 'On Duty'; ?>
                                </a>
                                <a href="?delete=1&id=<?php echo $staff['id']; ?>" class="btn-sm btn-delete" onclick="return confirm('Delete this staff account? This action cannot be undone.')" title="Delete staff member">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if($total_pages > 1): ?>
    <div class="pagination">
        <span class="page-info">Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?></span>
        
        <?php if($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>"><i class="fas fa-chevron-left"></i></a>
        <?php else: ?>
            <span class="disabled"><i class="fas fa-chevron-left"></i></span>
        <?php endif; ?>

        <?php for($i = 1; $i <= $total_pages; $i++): ?>
            <?php if($i == $page): ?>
                <span class="active"><?php echo $i; ?></span>
            <?php elseif($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php elseif($i == $page - 3 || $i == $page + 3): ?>
                <span class="disabled">...</span>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>"><i class="fas fa-chevron-right"></i></a>
        <?php else: ?>
            <span class="disabled"><i class="fas fa-chevron-right"></i></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ================= ADD EMPLOYEE MODAL ================= -->
<div class="staff-modal-overlay <?php echo $show_form ? 'active' : ''; ?>" id="staffModal" onclick="handleStaffModalBackdrop(event)">
    <div class="staff-modal-dialog">
        <div class="staff-modal-header">
            <h3><i class="fas fa-user-plus"></i> Add Employee Record</h3>
            <button type="button" class="staff-modal-close-btn" onclick="closeStaffModal()" aria-label="Close modal">&times;</button>
        </div>
        <div class="staff-modal-body">
            <form method="POST" action="manage-staff.php" enctype="multipart/form-data">
                <!-- Profile Picture -->
                <div class="form-group">
                    <label>Profile Picture</label>
                    <div class="profile-upload">
                        <div class="preview" id="profilePreview">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="upload-info">
                            <input type="file" name="profile_picture" id="profilePicture" accept="image/*" onchange="previewImage(event)">
                            <p>Upload a profile picture (JPG, PNG, GIF - Max 5MB)</p>
                        </div>
                    </div>
                </div>

                <!-- Auto-generated preview badges -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Employee ID <span style="font-size: 0.72rem; color: var(--text-muted);">(Auto-generated)</span></label>
                        <input type="text" value="<?php echo $preview_employee_id; ?>" readonly style="opacity: 0.75; cursor: not-allowed;">
                    </div>
                    <div class="form-group">
                        <label>Staff Number <span style="font-size: 0.72rem; color: var(--text-muted);">(Auto-generated)</span></label>
                        <input type="text" value="<?php echo $preview_staff_number; ?>" readonly style="opacity: 0.75; cursor: not-allowed;">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" required placeholder="e.g. Juan">
                    </div>
                    <div class="form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" required placeholder="e.g. Dela Cruz">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name" placeholder="Optional">
                    </div>
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" name="contact_number" placeholder="e.g., 09123456789">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Username <span class="required">*</span></label>
                        <input type="text" name="username" required placeholder="Choose a username">
                    </div>
                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" required placeholder="staff@example.com">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Password <span class="required">*</span></label>
                        <input type="password" name="password" required placeholder="Min 8 characters">
                    </div>
                    <div class="form-group">
                        <label>Confirm Password <span class="required">*</span></label>
                        <input type="password" name="confirm_password" required placeholder="Re-enter password">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Position <span class="required">*</span></label>
                        <input type="text" name="position" required placeholder="e.g., CSWDO Caseworker">
                    </div>
                    <div class="form-group">
                        <label>Hire Date</label>
                        <input type="date" name="hire_date" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <!-- Service Assignments -->
                <div class="services-group">
                    <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-primary);">Assign Services <span class="required" style="color: #DC2626;">*</span></label>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" name="services[]" value="AICS" id="service_aics">
                            <label for="service_aics"><i class="fas fa-hand-holding-heart" style="color: #7F0303; margin-right: 4px;"></i> AICS</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="services[]" value="HOUSING" id="service_housing">
                            <label for="service_housing"><i class="fas fa-home" style="color: #0F414A; margin-right: 4px;"></i> Housing</label>
                        </div>
                    </div>
                    <small style="color: var(--text-muted); font-size: 0.75rem; margin-top: 4px; display: block;">Select the public service departments assigned to this staff member</small>
                </div>

                <div class="staff-modal-footer" style="margin-top: 24px;">
                    <button type="button" onclick="closeStaffModal()" class="btn-secondary" style="padding: 9px 20px; border-radius: 50px; border: 1px solid var(--border-color); background: var(--bg-card); cursor: pointer;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" name="create_staff" class="btn-primary" style="padding: 9px 26px; border-radius: 50px; background: var(--maroon, #7F0303); color: #fff; border: none; font-weight: 700; cursor: pointer;">
                        <i class="fas fa-save"></i> Save Employee
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= EDIT EMPLOYEE MODAL ================= -->
<div class="staff-modal-overlay" id="editStaffModal" onclick="handleEditStaffModalBackdrop(event)">
    <div class="staff-modal-dialog">
        <div class="staff-modal-header">
            <h3><i class="fas fa-user-edit"></i> Edit Employee Information</h3>
            <button type="button" class="staff-modal-close-btn" onclick="closeEditStaffModal()" aria-label="Close modal">&times;</button>
        </div>
        <div class="staff-modal-body">
            <form method="POST" action="manage-staff.php" enctype="multipart/form-data" id="editStaffForm">
                <input type="hidden" name="staff_id" id="edit_staff_id">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <!-- Profile Picture -->
                <div class="form-group">
                    <label>Profile Picture</label>
                    <div class="profile-upload">
                        <div class="preview" id="editProfilePreview">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="upload-info">
                            <input type="file" name="profile_picture" id="editProfilePicture" accept="image/*" onchange="previewEditImage(event)">
                            <p>Upload a new photo or leave empty to keep current picture (JPG, PNG - Max 5MB)</p>
                        </div>
                    </div>
                </div>

                <!-- Readonly System Identifiers -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Employee ID <span style="font-size: 0.72rem; color: var(--text-muted);">(System Assigned)</span></label>
                        <input type="text" id="edit_employee_id" readonly style="opacity: 0.75; cursor: not-allowed;">
                    </div>
                    <div class="form-group">
                        <label>Staff Number <span style="font-size: 0.72rem; color: var(--text-muted);">(System Assigned)</span></label>
                        <input type="text" id="edit_staff_number" readonly style="opacity: 0.75; cursor: not-allowed;">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" id="edit_first_name" required placeholder="e.g. Juan">
                    </div>
                    <div class="form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" id="edit_last_name" required placeholder="e.g. Dela Cruz">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name" id="edit_middle_name" placeholder="Optional">
                    </div>
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" name="contact_number" id="edit_contact_number" placeholder="e.g., 09123456789">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Username <span style="font-size: 0.72rem; color: var(--text-muted);">(Login Username)</span></label>
                        <input type="text" id="edit_username" readonly style="opacity: 0.75; cursor: not-allowed;">
                    </div>
                    <div class="form-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="email" id="edit_email" required placeholder="staff@example.com">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Position <span class="required">*</span></label>
                        <input type="text" name="position" id="edit_position" required placeholder="e.g., CSWDO Caseworker">
                    </div>
                    <div class="form-group">
                        <label>Hire Date</label>
                        <input type="date" name="hire_date" id="edit_hire_date">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Duty / Work Status <span class="required">*</span></label>
                        <select name="is_active" id="edit_is_active">
                            <option value="1">🟢 On Duty (Active / Available for Assignments)</option>
                            <option value="0">🔴 Off Duty (Off Work / On Leave / Inactive)</option>
                        </select>
                        <small style="color: var(--text-muted); font-size: 0.75rem; margin-top: 4px; display: block;">Set whether this staff member is currently on duty or off work / on leave.</small>
                    </div>
                </div>

                <!-- Service Assignments -->
                <div class="services-group">
                    <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-primary);">Assign Services <span class="required" style="color: #DC2626;">*</span></label>
                    <div class="checkbox-group">
                        <div class="checkbox-item" id="edit_item_aics">
                            <input type="checkbox" name="services[]" value="AICS" id="edit_service_aics">
                            <label for="edit_service_aics"><i class="fas fa-hand-holding-heart" style="color: #7F0303; margin-right: 4px;"></i> AICS</label>
                        </div>
                        <div class="checkbox-item" id="edit_item_housing">
                            <input type="checkbox" name="services[]" value="HOUSING" id="edit_service_housing">
                            <label for="edit_service_housing"><i class="fas fa-home" style="color: #0F414A; margin-right: 4px;"></i> Housing</label>
                        </div>
                    </div>
                    <small style="color: var(--text-muted); font-size: 0.75rem; margin-top: 4px; display: block;">Select the public service departments assigned to this staff member</small>
                </div>

                <!-- PASSWORD RESET / FORGOT PASSWORD SECTION -->
                <div style="margin-top: 20px; padding: 16px; border-radius: 14px; background: rgba(33, 150, 243, 0.05); border: 1.5px dashed rgba(33, 150, 243, 0.35);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 8px;">
                        <div style="font-weight: 700; color: #1976D2; font-size: 0.9rem; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-key"></i> Password Management (Forgot Password Reset)
                        </div>
                        <button type="button" onclick="generateStaffRandomPassword()" style="background: rgba(33, 150, 243, 0.12); color: #1976D2; border: 1px solid rgba(33, 150, 243, 0.3); border-radius: 50px; font-size: 0.75rem; font-weight: 600; padding: 5px 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;">
                            <i class="fas fa-magic"></i> Generate Temporary Password
                        </button>
                    </div>
                    <p style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 12px; line-height: 1.4;">
                        If the staff member forgot their password or cannot log in, the administrator can enter a new password below or click <strong>Generate Temporary Password</strong> to reset it instantly. <em>Leave these fields blank to keep their existing password.</em>
                    </p>
                    <div class="form-row">
                        <div class="form-group">
                            <label style="font-size: 0.82rem;">New Password</label>
                            <input type="password" name="password" id="edit_password" placeholder="Min 8 characters (Leave blank to keep)" autocomplete="new-password">
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.82rem;">Confirm New Password</label>
                            <input type="password" name="confirm_password" id="edit_confirm_password" placeholder="Re-enter new password" autocomplete="new-password">
                        </div>
                    </div>
                    <div id="generatedPassNotice" style="display: none; margin-top: 8px; padding: 10px 14px; border-radius: 10px; background: rgba(76, 175, 80, 0.12); border: 1px solid rgba(76, 175, 80, 0.3); color: #2E7D32; font-size: 0.82rem;">
                        <i class="fas fa-check-circle"></i> Temporary password generated: <strong id="generatedPassValue" style="letter-spacing: 0.5px; font-size: 0.95rem; font-family: monospace;"></strong>. Give this password to the employee after saving!
                    </div>
                </div>

                <div class="staff-modal-footer" style="margin-top: 24px;">
                    <button type="button" onclick="closeEditStaffModal()" class="btn-secondary" style="padding: 9px 20px; border-radius: 50px; border: 1px solid var(--border-color); background: var(--bg-card); cursor: pointer;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" name="update_staff" class="btn-primary" style="padding: 9px 26px; border-radius: 50px; background: var(--maroon, #7F0303); color: #fff; border: none; font-weight: 700; cursor: pointer;">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ========== STAFF MODAL CONTROLS ==========
function openStaffModal() {
    const modal = document.getElementById('staffModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeStaffModal() {
    const modal = document.getElementById('staffModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function handleStaffModalBackdrop(event) {
    if (event.target && event.target.id === 'staffModal') {
        closeStaffModal();
    }
}

// ========== EDIT STAFF MODAL CONTROLS ==========
function openEditStaffModal(data) {
    const modal = document.getElementById('editStaffModal');
    if (!modal || !data) return;

    document.getElementById('edit_staff_id').value = data.id || '';
    document.getElementById('edit_user_id').value = data.user_id || '';
    document.getElementById('edit_employee_id').value = data.employee_id || '';
    document.getElementById('edit_staff_number').value = data.staff_number || '';
    document.getElementById('edit_first_name').value = data.first_name || '';
    document.getElementById('edit_middle_name').value = data.middle_name || '';
    document.getElementById('edit_last_name').value = data.last_name || '';
    document.getElementById('edit_username').value = data.username || '';
    document.getElementById('edit_email').value = data.email || '';
    document.getElementById('edit_contact_number').value = data.contact_number || '';
    document.getElementById('edit_position').value = data.position || '';
    document.getElementById('edit_hire_date').value = data.hire_date || '';
    document.getElementById('edit_is_active').value = (data.is_active !== undefined) ? String(data.is_active) : '1';

    // Clear password reset fields and notice
    const passField = document.getElementById('edit_password');
    const confField = document.getElementById('edit_confirm_password');
    if (passField) {
        passField.value = '';
        passField.type = 'password';
    }
    if (confField) {
        confField.value = '';
        confField.type = 'password';
    }
    const passNotice = document.getElementById('generatedPassNotice');
    if (passNotice) passNotice.style.display = 'none';

    // Profile picture preview
    const preview = document.getElementById('editProfilePreview');
    if (preview) {
        if (data.profile_picture) {
            preview.innerHTML = `<img src="${data.profile_picture}" alt="Profile Preview" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
        } else {
            preview.innerHTML = `<i class="fas fa-user-circle"></i>`;
        }
    }

    const picInput = document.getElementById('editProfilePicture');
    if (picInput) picInput.value = '';

    // Services checkboxes
    const svcs = Array.isArray(data.services) ? data.services.map(s => String(s).toUpperCase()) : [];
    const chkAics = document.getElementById('edit_service_aics');
    const chkHousing = document.getElementById('edit_service_housing');
    if (chkAics) {
        chkAics.checked = svcs.includes('AICS');
        updateEditCheckboxItemStyle(chkAics);
    }
    if (chkHousing) {
        chkHousing.checked = svcs.includes('HOUSING');
        updateEditCheckboxItemStyle(chkHousing);
    }

    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeEditStaffModal() {
    const modal = document.getElementById('editStaffModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function handleEditStaffModalBackdrop(event) {
    if (event.target && event.target.id === 'editStaffModal') {
        closeEditStaffModal();
    }
}

function previewImage(event) {
    const preview = document.getElementById('profilePreview');
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Profile Preview" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
        };
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = `<i class="fas fa-user-circle"></i>`;
    }
}

function previewEditImage(event) {
    const preview = document.getElementById('editProfilePreview');
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Profile Preview" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
        };
        reader.readAsDataURL(file);
    }
}

function generateStaffRandomPassword() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    let rand = '';
    for (let i = 0; i < 6; i++) {
        rand += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    const tempPass = 'Palayan@' + rand;

    const passField = document.getElementById('edit_password');
    const confField = document.getElementById('edit_confirm_password');
    const passNotice = document.getElementById('generatedPassNotice');
    const passVal = document.getElementById('generatedPassValue');

    if (passField && confField) {
        passField.type = 'text';
        confField.type = 'text';
        passField.value = tempPass;
        confField.value = tempPass;
    }
    if (passNotice && passVal) {
        passVal.textContent = tempPass;
        passNotice.style.display = 'block';
    }
}

function updateEditCheckboxItemStyle(chk) {
    const item = chk.closest('.checkbox-item');
    if (!item) return;
    if (chk.checked) {
        item.style.borderColor = 'var(--maroon-light, #7F0303)';
        item.style.background = 'rgba(127, 3, 3, 0.05)';
    } else {
        item.style.borderColor = 'var(--border-color, #C8D0DC)';
        item.style.background = 'var(--bg-primary, #ffffff)';
    }
}

// Checkbox items click binding
document.querySelectorAll('.checkbox-item').forEach(item => {
    const checkbox = item.querySelector('input[type="checkbox"]');
    if (checkbox) {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                item.style.borderColor = 'var(--maroon-light, #7F0303)';
                item.style.background = 'rgba(127, 3, 3, 0.05)';
            } else {
                item.style.borderColor = 'var(--border-color, #C8D0DC)';
                item.style.background = 'var(--bg-primary, #ffffff)';
            }
        });
    }
});

// Escape key to close modals
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeStaffModal();
        closeEditStaffModal();
    }
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
