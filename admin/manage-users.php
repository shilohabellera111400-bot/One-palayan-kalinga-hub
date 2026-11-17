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

if (isset($_SESSION['user_action_success'])) {
    $success = $_SESSION['user_action_success'];
    unset($_SESSION['user_action_success']);
}
if (isset($_SESSION['user_action_error'])) {
    $error = $_SESSION['user_action_error'];
    unset($_SESSION['user_action_error']);
}

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
            // Check previous verification status for email notifications
            $old_v_stmt = $db->prepare("SELECT verification_status FROM users WHERE user_id = :id");
            $old_v_stmt->execute([':id' => $user_id]);
            $old_verification_status = $old_v_stmt->fetchColumn();

            $role_id = match($role) {
                'admin' => 1,
                'staff' => 2,
                default => 3
            };

            // Update users table
            $query = "UPDATE users SET 
                        first_name = :first_name, 
                        last_name = :last_name, 
                        email = :email, 
                        contact_number = :contact_number, 
                        role = :role, 
                        role_id = :role_id, 
                        status = :status, 
                        verification_status = :verification_status 
                      WHERE user_id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':last_name', $last_name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':contact_number', $contact_number);
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':role_id', $role_id);
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
                    $res_num = generateResidentNumber($db);

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

            // Optional password update from unified edit modal
            if (!empty($_POST['new_password'])) {
                $new_password = $_POST['new_password'];
                if (strlen($new_password) < 8) {
                    throw new Exception("New password must be at least 8 characters long.");
                }
                $hashed_pwd = password_hash($new_password, PASSWORD_DEFAULT);
                $pwd_stmt = $db->prepare("UPDATE users SET password = :pwd WHERE user_id = :id");
                $pwd_stmt->execute([':pwd' => $hashed_pwd, ':id' => $user_id]);
            }

            $db->commit();

            // If verification status changed to verified or rejected, send notification email
            if ($old_verification_status !== $verification_status && !empty($email)) {
                try {
                    require_once __DIR__ . '/../includes/Mailer.php';
                    $mailer = new Mailer();
                    $recipient_name = trim($first_name . ' ' . $last_name) ?: 'Resident';
                    if ($verification_status === 'verified') {
                        $mailer->sendVerificationApprovedEmail($email, $recipient_name, $user_id);
                    } elseif ($verification_status === 'rejected') {
                        $mailer->sendVerificationRejectedEmail($email, $recipient_name, 'Verification disapproved during administrator account review.', $user_id);
                    }
                } catch (Exception $mailEx) {
                    error_log("Failed to send verification status email from manage-users: " . $mailEx->getMessage());
                }
            }

            // Log activity
            $log_query = "INSERT INTO audit_logs (user_id, action, table_affected, new_values) VALUES (:admin_id, 'edit_user', 'users', :details)";
            $log_stmt = $db->prepare($log_query);
            $details = "Updated user details for User ID: $user_id (Role: $role, Status: $status)";
            $log_stmt->bindParam(':admin_id', $_SESSION['user_id']);
            $log_stmt->bindParam(':details', $details);
            $log_stmt->execute();

            $_SESSION['user_action_success'] = "User account <strong>" . htmlspecialchars($first_name . ' ' . $last_name) . "</strong> updated successfully.";
            header("Location: manage-users.php");
            exit();
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
                $role_id = match($role) {
                    'admin' => 1,
                    'staff' => 2,
                    default => 3
                };
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $query = "INSERT INTO users (username, password, email, first_name, last_name, contact_number, role, role_id, status, verification_status) 
                          VALUES (:username, :password, :email, :first_name, :last_name, :contact_number, :role, :role_id, :status, 'verified')";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $hashed);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':first_name', $first_name);
                $stmt->bindParam(':last_name', $last_name);
                $stmt->bindParam(':contact_number', $contact_number);
                $stmt->bindParam(':role', $role);
                $stmt->bindParam(':role_id', $role_id);
                $stmt->bindParam(':status', $status);
                $stmt->execute();
                
                $new_id = $db->lastInsertId();

                // Profile initialization
                if ($role === 'resident') {
                    $res_num = generateResidentNumber($db);

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

// Handle user deletion & deactivation
$is_delete_req = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['delete_user', 'archive_user'])) 
                 || (isset($_GET['delete_id']));

if ($is_delete_req) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
        $target_id = intval($_POST['user_id'] ?? 0);
        $req_action = $_POST['action'] ?? 'delete_user';
    } else {
        $target_id = intval($_GET['delete_id'] ?? 0);
        $req_action = 'delete_user';
    }

    if ($target_id <= 0) {
        $error = "Invalid user account specified.";
    } elseif ($target_id === intval($_SESSION['user_id'] ?? 0)) {
        $error = "You cannot delete or deactivate your own admin account.";
    } else {
        // Fetch target user info
        $t_stmt = $db->prepare("SELECT * FROM users WHERE user_id = :id");
        $t_stmt->execute([':id' => $target_id]);
        $target_user = $t_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$target_user) {
            $error = "User account not found or has already been removed.";
        } else {
            $user_display_name = trim(($target_user['first_name'] ?? '') . ' ' . ($target_user['last_name'] ?? ''));
            if (empty($user_display_name)) {
                $user_display_name = $target_user['username'];
            }

            if ($req_action === 'archive_user') {
                // Deactivate / Soft Archive
                try {
                    $db->beginTransaction();
                    $db->prepare("UPDATE users SET status = 'inactive' WHERE user_id = :id")->execute([':id' => $target_id]);
                    $db->prepare("UPDATE staff SET is_active = 0 WHERE user_id = :id")->execute([':id' => $target_id]);

                    $log_query = "INSERT INTO audit_logs (user_id, action, table_affected, new_values) VALUES (:admin_id, 'archive_user', 'users', :details)";
                    $log_stmt = $db->prepare($log_query);
                    $details = "Deactivated User: " . $user_display_name . " (@" . $target_user['username'] . ", ID: " . $target_id . ")";
                    $log_stmt->bindParam(':admin_id', $_SESSION['user_id']);
                    $log_stmt->bindParam(':details', $details);
                    $log_stmt->execute();

                    $db->commit();
                    $_SESSION['user_action_success'] = "User account <strong>" . htmlspecialchars($user_display_name) . "</strong> (@" . htmlspecialchars($target_user['username']) . ") has been deactivated.";
                    header("Location: manage-users.php");
                    exit();
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = "Failed to deactivate user: " . $e->getMessage();
                }
            } else {
                // Permanent Cascade Delete
                $db->beginTransaction();
                try {
                    // 1. Clear foreign key reviewer/approver/verifier references on other records
                    $db->prepare("UPDATE users SET verified_by = NULL WHERE verified_by = :id")->execute([':id' => $target_id]);
                    $db->prepare("UPDATE verification_requests SET reviewed_by = NULL WHERE reviewed_by = :id")->execute([':id' => $target_id]);
                    $db->prepare("UPDATE aics_requests SET reviewed_by = NULL WHERE reviewed_by = :id")->execute([':id' => $target_id]);
                    $db->prepare("UPDATE aics_requests SET approved_by = NULL WHERE approved_by = :id")->execute([':id' => $target_id]);
                    try {
                        $db->prepare("UPDATE aics_requests_backup SET reviewed_by = NULL WHERE reviewed_by = :id")->execute([':id' => $target_id]);
                        $db->prepare("UPDATE aics_requests_backup SET approved_by = NULL WHERE approved_by = :id")->execute([':id' => $target_id]);
                    } catch (Exception $e) {}
                    $db->prepare("UPDATE housing_beneficiaries SET interviewed_by = NULL WHERE interviewed_by = :id")->execute([':id' => $target_id]);
                    $db->prepare("UPDATE housing_beneficiaries SET approved_by = NULL WHERE approved_by = :id")->execute([':id' => $target_id]);
                    $db->prepare("UPDATE documents SET verified_by = NULL WHERE verified_by = :id")->execute([':id' => $target_id]);
                    $db->prepare("UPDATE appointments SET staff_id = NULL WHERE staff_id = :id")->execute([':id' => $target_id]);

                    // 2. Clear AICS applicant requests and sub-details
                    $aics_stmt = $db->prepare("SELECT aics_id FROM aics_requests WHERE user_id = :id");
                    $aics_stmt->execute([':id' => $target_id]);
                    $aics_ids = $aics_stmt->fetchAll(PDO::FETCH_COLUMN);
                    if (!empty($aics_ids)) {
                        $in_clause = implode(',', array_map('intval', $aics_ids));
                        $detail_tables = ['aics_medical_details', 'aics_funeral_details', 'aics_educational_details', 'aics_food_details', 'aics_material_details', 'aics_cash_relief_details', 'aics_transportation_details', 'aics_documents', 'aics_comments'];
                        foreach ($detail_tables as $tbl) {
                            try { $db->exec("DELETE FROM $tbl WHERE aics_id IN ($in_clause)"); } catch (Exception $e) {}
                        }
                        $db->exec("DELETE FROM aics_requests WHERE user_id = $target_id");
                    }
                    try { $db->exec("DELETE FROM aics_comments WHERE user_id = $target_id"); } catch (Exception $e) {}
                    try { $db->exec("DELETE FROM aics_requests_backup WHERE user_id = $target_id"); } catch (Exception $e) {}

                    // 3. Clear Housing records
                    $db->prepare("DELETE FROM housing_beneficiaries WHERE user_id = :id")->execute([':id' => $target_id]);

                    // 4. Clear Documents & requests
                    $db->prepare("DELETE FROM documents WHERE user_id = :id")->execute([':id' => $target_id]);
                    try { $db->prepare("DELETE FROM document_requests WHERE user_id = :id")->execute([':id' => $target_id]); } catch (Exception $e) {}

                    // 5. Clear Resident & Social Applications
                    $res_stmt = $db->prepare("SELECT id FROM residents WHERE user_id = :id");
                    $res_stmt->execute([':id' => $target_id]);
                    $res_id = $res_stmt->fetchColumn();
                    if ($res_id) {
                        try { $db->exec("DELETE FROM senior_applications WHERE resident_id = $res_id"); } catch (Exception $e) {}
                        try { $db->exec("DELETE FROM pwd_applications WHERE resident_id = $res_id"); } catch (Exception $e) {}
                        try { $db->exec("DELETE FROM service_requests WHERE resident_id = $res_id"); } catch (Exception $e) {}
                    }
                    try { $db->prepare("DELETE FROM senior_applications WHERE user_id = :id")->execute([':id' => $target_id]); } catch (Exception $e) {}
                    try { $db->prepare("DELETE FROM pwd_applications WHERE user_id = :id")->execute([':id' => $target_id]); } catch (Exception $e) {}
                    $db->prepare("DELETE FROM residents WHERE user_id = :id")->execute([':id' => $target_id]);

                    // 6. Clear Staff & assignments
                    $stf_stmt = $db->prepare("SELECT id FROM staff WHERE user_id = :id");
                    $stf_stmt->execute([':id' => $target_id]);
                    $stf_id = $stf_stmt->fetchColumn();
                    if ($stf_id) {
                        try { $db->exec("DELETE FROM staff_service_assignments WHERE staff_id = $stf_id"); } catch (Exception $e) {}
                    }
                    $db->prepare("DELETE FROM staff WHERE user_id = :id")->execute([':id' => $target_id]);

                    // 7. Clear secondary user tables
                    $db->prepare("DELETE FROM verification_requests WHERE user_id = :id")->execute([':id' => $target_id]);
                    $db->prepare("DELETE FROM appointments WHERE user_id = :id")->execute([':id' => $target_id]);
                    $db->prepare("DELETE FROM notifications WHERE user_id = :id")->execute([':id' => $target_id]);
                    $db->prepare("DELETE FROM email_logs WHERE user_id = :id")->execute([':id' => $target_id]);
                    $db->prepare("DELETE FROM password_resets WHERE user_id = :id")->execute([':id' => $target_id]);
                    try { $db->prepare("DELETE FROM feedback WHERE user_id = :id")->execute([':id' => $target_id]); } catch (Exception $e) {}
                    try { $db->prepare("DELETE FROM rate_limits WHERE user_id = :id")->execute([':id' => $target_id]); } catch (Exception $e) {}

                    // 8. Dissociate audit logs
                    $db->prepare("UPDATE audit_logs SET user_id = NULL WHERE user_id = :id")->execute([':id' => $target_id]);

                    // 9. Delete avatar file if exists
                    if (!empty($target_user['profile_picture'])) {
                        $avatar_path = __DIR__ . '/../' . $target_user['profile_picture'];
                        if (file_exists($avatar_path)) {
                            @unlink($avatar_path);
                        }
                    }

                    // 10. Delete main user record
                    $del_user = $db->prepare("DELETE FROM users WHERE user_id = :id");
                    $del_user->execute([':id' => $target_id]);

                    // 11. Log deletion activity under admin's ID
                    $log_query = "INSERT INTO audit_logs (user_id, action, table_affected, new_values) VALUES (:admin_id, 'delete_user', 'users', :details)";
                    $log_stmt = $db->prepare($log_query);
                    $details = "Permanently deleted user: " . $user_display_name . " (Username: " . $target_user['username'] . ", ID: " . $target_id . ")";
                    $log_stmt->bindParam(':admin_id', $_SESSION['user_id']);
                    $log_stmt->bindParam(':details', $details);
                    $log_stmt->execute();

                    $db->commit();

                    $_SESSION['user_action_success'] = "User account <strong>" . htmlspecialchars($user_display_name) . "</strong> (@" . htmlspecialchars($target_user['username']) . ") was permanently deleted successfully.";
                    header("Location: manage-users.php");
                    exit();
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = "Failed to delete user account: " . $e->getMessage();
                }
            }
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

$page_title = 'System Users';
$active_sidebar = 'users';
require_once __DIR__ . '/includes/admin-head.php';
?>
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
            --bg-primary: var(--ref-canvas, #E4EFE7);
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
            --bg-primary: var(--ref-canvas, #0C1417);
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
        /* ========== MODERN DELETE CONFIRMATION MODAL ========== */
        .modal-modern-wrap {
            position: fixed;
            inset: 0;
            z-index: 999999;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.72);
            padding: 16px;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        .modal-modern-wrap.active {
            opacity: 1;
        }
        .modal-modern-card {
            background: var(--bg-card, #ffffff);
            color: var(--text-primary, #1e293b);
            width: 100%;
            max-width: 480px;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35), 0 0 0 1px var(--border-color, rgba(0,0,0,0.08));
            position: relative;
            overflow: hidden;
            transform: scale(0.96) translateY(10px);
            transition: transform 0.2s ease;
        }
        .modal-modern-wrap.active .modal-modern-card {
            transform: scale(1) translateY(0);
        }
        .modal-modern-accent {
            height: 5px;
            width: 100%;
            background: linear-gradient(90deg, #b91c1c, #ef4444, #f87171);
        }
        .modal-modern-close {
            position: absolute;
            top: 14px;
            right: 16px;
            background: rgba(0,0,0,0.05);
            border: none;
            font-size: 20px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            color: var(--text-muted, #64748b);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        .modal-modern-close:hover {
            background: rgba(220, 38, 38, 0.12);
            color: #dc2626;
            transform: rotate(90deg);
        }
        .modal-modern-body {
            padding: 26px 26px 18px 26px;
            text-align: center;
        }
        .danger-beacon {
            position: relative;
            width: 68px;
            height: 68px;
            margin: 0 auto 16px auto;
            border-radius: 50%;
            background: rgba(239, 68, 68, 0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ef4444;
            font-size: 28px;
        }
        .danger-beacon-ping {
            position: absolute;
            inset: -6px;
            border-radius: 50%;
            border: 2px solid rgba(239, 68, 68, 0.4);
            animation: beaconPulse 2s cubic-bezier(0, 0, 0.2, 1) infinite;
        }
        @keyframes beaconPulse {
            0% { transform: scale(0.9); opacity: 1; }
            100% { transform: scale(1.35); opacity: 0; }
        }
        .modal-modern-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary, #0f172a);
            margin: 0 0 6px 0;
        }
        .modal-modern-desc {
            font-size: 0.85rem;
            color: var(--text-muted, #64748b);
            margin: 0 0 18px 0;
            line-height: 1.45;
        }
        .target-user-strip {
            background: var(--badge-bg, rgba(0, 0, 0, 0.03));
            border: 1px solid var(--border-color, rgba(0, 0, 0, 0.08));
            border-radius: 14px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-align: left;
            margin-bottom: 16px;
        }
        .target-user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--maroon, #7f0303);
            color: #ffffff;
            font-weight: 700;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
            border: 2px solid var(--border-color, rgba(255,255,255,0.2));
        }
        .target-user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .target-user-details {
            flex: 1;
            min-width: 0;
        }
        .target-user-name {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text-primary, #0f172a);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .target-user-sub {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
            color: var(--text-muted, #64748b);
            margin-top: 2px;
        }
        .target-user-role-badge {
            text-transform: uppercase;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 50px;
            background: rgba(0,0,0,0.08);
            color: var(--text-primary, #0f172a);
        }
        .modal-warning-callout {
            background: rgba(245, 158, 11, 0.1);
            border: 1px dashed rgba(245, 158, 11, 0.45);
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 0.78rem;
            color: #b45309;
            display: flex;
            align-items: center;
            gap: 10px;
            text-align: left;
            line-height: 1.4;
        }
        .modal-modern-footer {
            padding: 14px 20px 20px 20px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            background: var(--badge-bg, rgba(0, 0, 0, 0.02));
            border-top: 1px solid var(--border-color, rgba(0, 0, 0, 0.06));
            border-radius: 0 0 22px 22px;
            flex-wrap: wrap;
        }
        .btn-modern-ghost {
            background: transparent;
            border: 1px solid var(--border-color, #cbd5e1);
            color: var(--text-secondary, #475569);
            padding: 9px 18px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-modern-ghost:hover {
            background: var(--border-color, #f1f5f9);
            color: var(--text-primary, #0f172a);
        }
        .btn-modern-deactivate {
            background: rgba(245, 158, 11, 0.12);
            border: 1px solid rgba(245, 158, 11, 0.35);
            color: #d97706;
            padding: 9px 16px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-modern-deactivate:hover {
            background: #d97706;
            color: #ffffff;
            border-color: #d97706;
            transform: translateY(-1px);
        }
        .btn-modern-danger {
            background: #dc2626;
            border: none;
            color: #ffffff;
            padding: 9px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
        }
        .btn-modern-danger:hover {
            background: #b91c1c;
            box-shadow: 0 6px 16px rgba(220, 38, 38, 0.35);
            transform: translateY(-1px);
        }

        /* ========== MODERN TOAST POPUP NOTIFICATIONS ========== */
        .modern-toast-host {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 100000;
            display: flex;
            flex-direction: column;
            gap: 12px;
            pointer-events: none;
            max-width: 440px;
            width: calc(100vw - 48px);
        }
        .modern-toast-card {
            pointer-events: auto;
            background: var(--bg-card, #ffffff);
            color: var(--text-primary, #0f172a);
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.18), 0 8px 10px -6px rgba(0, 0, 0, 0.1), 0 0 0 1px var(--border-color, rgba(0,0,0,0.08));
            border-left: 5px solid #10b981;
            overflow: hidden;
            position: relative;
            display: flex;
            align-items: flex-start;
            padding: 16px;
            gap: 12px;
            transform: translateX(120%);
            opacity: 0;
            transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .modern-toast-card.show {
            transform: translateX(0);
            opacity: 1;
        }
        .modern-toast-card.toast-error {
            border-left-color: #ef4444;
        }
        .modern-toast-card.toast-info {
            border-left-color: #0288d1;
        }
        .modern-toast-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 16px;
            background: rgba(16, 185, 129, 0.14);
            color: #10b981;
        }
        .toast-error .modern-toast-icon {
            background: rgba(239, 68, 68, 0.14);
            color: #ef4444;
        }
        .toast-info .modern-toast-icon {
            background: rgba(2, 136, 209, 0.14);
            color: #0288d1;
        }
        .modern-toast-content {
            flex: 1;
            min-width: 0;
        }
        .modern-toast-title {
            font-weight: 700;
            font-size: 0.88rem;
            margin-bottom: 2px;
            color: var(--text-primary, #0f172a);
        }
        .modern-toast-msg {
            font-size: 0.8rem;
            color: var(--text-muted, #64748b);
            line-height: 1.4;
            word-break: break-word;
        }
        .modern-toast-close {
            background: transparent;
            border: none;
            font-size: 18px;
            color: var(--text-muted, #94a3b8);
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 6px;
            line-height: 1;
            transition: all 0.2s ease;
        }
        .modern-toast-close:hover {
            color: var(--text-primary, #0f172a);
            background: rgba(0,0,0,0.05);
        }
        .modern-toast-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            width: 100%;
            background: #10b981;
            transform-origin: left;
            animation: toastProgress 5s linear forwards;
        }
        .toast-error .modern-toast-bar {
            background: #ef4444;
        }
        .toast-info .modern-toast-bar {
            background: #0288d1;
        }
        @keyframes toastProgress {
            0% { transform: scaleX(1); }
            100% { transform: scaleX(0); }
        }
    </style>
<?php
require_once __DIR__ . '/includes/admin-sidebar.php';
require_once __DIR__ . '/includes/admin-header.php';
?>

<div class="dash-subheader">
    <div class="subheader-title-wrap">
        <h1><i class="fas fa-users-cog"></i> System Users</h1>
        <p>Manage administrative, staff, and resident user credentials & access</p>
    </div>
    <div class="dash-cursive-branding-inline">
        <h2 class="dash-cursive-title">One-Palayan Kalinga Hub</h2>
    </div>
    <div class="subheader-status-wrap">
        <button onclick="openCreateModal()" class="btn-primary" style="padding:8px 18px;border-radius:50px;background:var(--dash-maroon);color:#fff;font-weight:600;font-size:0.85rem;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:8px;">
            <i class="fas fa-user-plus"></i> Add New User
        </button>
    </div>
</div>

        <!-- Modern Floating Toast Host -->
        <div id="modernToastContainer" class="modern-toast-host" aria-live="polite" aria-atomic="true"></div>

        <?php if ($error): ?>
            <div class="alert alert-error" id="topInlineErrorAlert" style="border-radius: 14px; display: flex; align-items: center; justify-content: space-between; padding: 14px 18px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-exclamation-circle" style="font-size: 1.25rem;"></i>
                    <span><?php echo $error; ?></span>
                </div>
                <button type="button" onclick="this.closest('.alert').remove()" style="background:none;border:none;color:inherit;font-size:1.2rem;cursor:pointer;opacity:0.7;">&times;</button>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success" id="topInlineSuccessAlert" style="border-radius: 14px; display: flex; align-items: center; justify-content: space-between; padding: 14px 18px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-check-circle" style="font-size: 1.25rem;"></i>
                    <span><?php echo $success; ?></span>
                </div>
                <button type="button" onclick="this.closest('.alert').remove()" style="background:none;border:none;color:inherit;font-size:1.2rem;cursor:pointer;opacity:0.7;">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="compact-stats-grid" style="margin-bottom: 20px;">
            <div class="stat-pill-card" onclick="window.location.href='manage-users.php'" style="cursor:pointer;">
                <div class="stat-pill-icon maroon"><i class="fas fa-users"></i></div>
                <div class="stat-pill-data">
                    <span class="stat-pill-val"><?php echo $total_users; ?></span>
                    <span class="stat-pill-lbl">Total Accounts</span>
                </div>
            </div>
            <div class="stat-pill-card" onclick="window.location.href='manage-users.php?status=active'" style="cursor:pointer;">
                <div class="stat-pill-icon emerald"><i class="fas fa-user-check"></i></div>
                <div class="stat-pill-data">
                    <span class="stat-pill-val"><?php echo $active_users; ?></span>
                    <span class="stat-pill-lbl">Active Users</span>
                </div>
            </div>
            <div class="stat-pill-card" onclick="window.location.href='manage-users.php?role=staff'" style="cursor:pointer;">
                <div class="stat-pill-icon sapphire"><i class="fas fa-user-shield"></i></div>
                <div class="stat-pill-data">
                    <span class="stat-pill-val"><?php echo $staff_users; ?></span>
                    <span class="stat-pill-lbl">CSWDO Staff</span>
                </div>
            </div>
            <div class="stat-pill-card" onclick="window.location.href='manage-users.php?role=resident'" style="cursor:pointer;">
                <div class="stat-pill-icon amber"><i class="fas fa-user-friends"></i></div>
                <div class="stat-pill-data">
                    <span class="stat-pill-val"><?php echo $resident_users; ?></span>
                    <span class="stat-pill-lbl">Residents</span>
                </div>
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
                                    <div class="action-btns" style="justify-content: center; gap: 6px;">
                                        <button type="button" class="btn-primary" style="padding: 6px 12px; font-size: 0.8rem; border-radius: 6px; cursor: pointer;" data-user="<?php echo htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8'); ?>" onclick="openEditModal(this)" title="Edit User"><i class="fas fa-edit" style="pointer-events: none;"></i></button>
                                        <?php if ($u['user_id'] != $_SESSION['user_id']): ?>
                                            <button type="button" class="btn-secondary btn-delete" style="padding: 6px 12px; font-size: 0.8rem; border-radius: 6px; background-color: var(--color-danger); color: white; border-color: var(--color-danger); cursor: pointer;" onclick="openDeleteModal(<?php echo (int)$u['user_id']; ?>, '<?php echo htmlspecialchars(addslashes($u['username']), ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes(trim($u['first_name'] . ' ' . $u['last_name'])), ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($u['role']), ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($u['email']), ENT_QUOTES); ?>')" title="Delete User"><i class="fas fa-trash-alt"></i></button>
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
                    <option value="rejected">Disapproved</option>
                </select>
            </div>
            <div class="form-group" style="margin-top: 14px; padding-top: 14px; border-top: 1px dashed var(--border-color, #cbd5e1);">
                <label style="display: flex; align-items: center; justify-content: space-between;">
                    <span><i class="fas fa-key" style="color: var(--maroon, #7F0303);"></i> Reset / Change Password</span>
                    <span style="font-size: 0.72rem; color: var(--text-muted, #64748b); font-weight: normal;">(Leave blank to keep current)</span>
                </label>
                <input type="password" name="new_password" id="edit_new_password" placeholder="Enter new password (optional, min 8 chars)" autocomplete="new-password">
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
</div><!-- /#resetModal -->

<!-- Modern Delete User Modal -->
<div id="modernDeleteModal" class="modal-modern-wrap">
    <div class="modal-modern-card" role="dialog" aria-modal="true" aria-labelledby="delModalTitle">
        <div class="modal-modern-accent"></div>
        <button type="button" class="modal-modern-close" onclick="closeDeleteModal()" aria-label="Close dialog">&times;</button>
        <div class="modal-modern-body">
            <div class="danger-beacon">
                <div class="danger-beacon-ping"></div>
                <i class="fas fa-trash-alt"></i>
            </div>
            <h3 class="modal-modern-title" id="delModalTitle">Delete User Account</h3>
            <p class="modal-modern-desc">Are you sure you want to delete this user? Please confirm your action below.</p>

            <div class="target-user-strip">
                <div class="target-user-avatar" id="delModalAvatar">
                    <span>U</span>
                </div>
                <div class="target-user-details">
                    <div class="target-user-name" id="delModalName">User Name</div>
                    <div class="target-user-sub">
                        <span id="delModalUsername">@username</span>
                        <span>&bull;</span>
                        <span class="target-user-role-badge" id="delModalRole">resident</span>
                    </div>
                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;" id="delModalEmail">user@email.com</div>
                </div>
            </div>

            <div class="modal-warning-callout">
                <i class="fas fa-exclamation-triangle" style="font-size: 1.1rem; flex-shrink: 0;"></i>
                <div>
                    <strong>Permanent Action:</strong> Deleting permanently will purge this account, login access, and all associated applications & documents.
                </div>
            </div>
        </div>
        <div class="modal-modern-footer">
            <button type="button" class="btn-modern-ghost" onclick="closeDeleteModal()">Cancel</button>
            <form method="POST" action="manage-users.php" id="deleteUserForm" style="display:inline-flex; gap:8px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="user_id" id="delModalUserId" value="">
                <button type="submit" name="action" value="archive_user" class="btn-modern-deactivate" title="Deactivate user and keep historical records">
                    <i class="fas fa-user-slash"></i> Deactivate Only
                </button>
                <button type="submit" name="action" value="delete_user" class="btn-modern-danger" id="delConfirmSubmitBtn">
                    <i class="fas fa-trash-alt"></i> Delete Permanently
                </button>
            </form>
        </div>
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
    const modernDeleteModal = document.getElementById('modernDeleteModal');

    function openCreateModal() {
        if (!createModal) return;
        createModal.style.display = 'flex';
        void createModal.offsetWidth;
        createModal.classList.add('active');
    }
    function closeCreateModal() {
        if (!createModal) return;
        createModal.classList.remove('active');
        setTimeout(() => { createModal.style.display = 'none'; }, 200);
    }

    function openEditModal(target) {
        if (!editModal) return;
        let user = target;
        if (target instanceof Element) {
            const raw = target.getAttribute('data-user') || target.dataset.user;
            try {
                user = JSON.parse(raw);
            } catch(e) {
                console.error('Invalid user JSON:', e);
                return;
            }
        } else if (typeof target === 'string') {
            try {
                user = JSON.parse(target);
            } catch(e) {
                console.error('Invalid user JSON:', e);
                return;
            }
        }
        if (!user || typeof user !== 'object') return;

        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.value = (val !== null && val !== undefined) ? val : '';
        };

        setVal('edit_user_id', user.user_id);
        setVal('edit_first_name', user.first_name);
        setVal('edit_last_name', user.last_name);
        setVal('edit_email', user.email);
        setVal('edit_contact_number', user.contact_number);
        setVal('edit_role', user.role);
        setVal('edit_status', user.status);
        setVal('edit_verification_status', user.verification_status);
        setVal('edit_new_password', '');

        editModal.style.display = 'flex';
        void editModal.offsetWidth;
        editModal.classList.add('active');
    }

    function closeEditModal() {
        if (!editModal) return;
        editModal.classList.remove('active');
        setTimeout(() => { editModal.style.display = 'none'; }, 200);
    }

    function closeResetModal() {
        if (!resetModal) return;
        resetModal.classList.remove('active');
        setTimeout(() => { resetModal.style.display = 'none'; }, 200);
    }

    // Modern Delete Modal Controls
    function openDeleteModal(userId, username, name, role, email) {
        if (!modernDeleteModal) return;
        // Support both openDeleteModal(object) and openDeleteModal(id, username, name, role, email)
        if (typeof userId === 'object' && userId !== null) {
            const user = userId;
            userId = user.user_id;
            username = user.username;
            name = user.name;
            role = user.role;
            email = user.email;
        }

        const idInput = document.getElementById('delModalUserId');
        if (idInput) idInput.value = userId || '';

        const nameEl = document.getElementById('delModalName');
        if (nameEl) nameEl.textContent = name || username || 'User Account';

        const userEl = document.getElementById('delModalUsername');
        if (userEl) userEl.textContent = '@' + (username || '');

        const emailEl = document.getElementById('delModalEmail');
        if (emailEl) emailEl.textContent = email || 'No email registered';
        
        const roleEl = document.getElementById('delModalRole');
        if (roleEl) {
            roleEl.textContent = role || 'resident';
            roleEl.className = 'target-user-role-badge role-' + (role || 'resident');
        }

        const avatarEl = document.getElementById('delModalAvatar');
        if (avatarEl) {
            const displayName = String(name || username || 'U').trim();
            const initials = displayName.split(/\s+/).map(n => n[0]).join('').substring(0, 2).toUpperCase() || 'U';
            avatarEl.innerHTML = '<span>' + escapeHtml(initials) + '</span>';
        }

        modernDeleteModal.style.display = 'flex';
        void modernDeleteModal.offsetWidth; // Force layout reflow
        modernDeleteModal.classList.add('active');
    }

    function closeDeleteModal() {
        if (!modernDeleteModal) return;
        modernDeleteModal.classList.remove('active');
        setTimeout(() => {
            modernDeleteModal.style.display = 'none';
        }, 180);
    }

    // Modern Toast Notification System
    function showModernToast(type, title, message, duration = 5000) {
        const container = document.getElementById('modernToastContainer');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = 'modern-toast-card toast-' + (type === 'error' ? 'error' : (type === 'info' ? 'info' : 'success'));
        
        let iconHtml = '<i class="fas fa-check-circle"></i>';
        if (type === 'error') iconHtml = '<i class="fas fa-exclamation-circle"></i>';
        else if (type === 'info') iconHtml = '<i class="fas fa-info-circle"></i>';

        toast.innerHTML = `
            <div class="modern-toast-icon">${iconHtml}</div>
            <div class="modern-toast-content">
                <div class="modern-toast-title">${escapeHtml(title)}</div>
                <div class="modern-toast-msg">${message}</div>
            </div>
            <button type="button" class="modern-toast-close" aria-label="Close notification">&times;</button>
            <div class="modern-toast-bar"></div>
        `;

        const closeBtn = toast.querySelector('.modern-toast-close');
        closeBtn.addEventListener('click', () => {
            dismissToast(toast);
        });

        container.appendChild(toast);
        requestAnimationFrame(() => {
            toast.classList.add('show');
        });

        const timer = setTimeout(() => {
            dismissToast(toast);
        }, duration);

        function dismissToast(el) {
            clearTimeout(timer);
            el.classList.remove('show');
            setTimeout(() => {
                if (el.parentNode) el.parentNode.removeChild(el);
            }, 360);
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Close on overlay click
    window.onclick = function(event) {
        if (event.target == createModal) closeCreateModal();
        if (event.target == editModal) closeEditModal();
        if (event.target == resetModal) closeResetModal();
        if (event.target == modernDeleteModal) closeDeleteModal();
    }

    // Escape key closes modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (modernDeleteModal && modernDeleteModal.classList.contains('active')) {
                closeDeleteModal();
            }
        }
    });

    // Auto-trigger modern toast on load if session message is present
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($success)): ?>
            showModernToast('success', 'Action Successful', <?php echo json_encode($success); ?>);
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            showModernToast('error', 'Notice', <?php echo json_encode($error); ?>);
        <?php endif; ?>
    });

</script>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
