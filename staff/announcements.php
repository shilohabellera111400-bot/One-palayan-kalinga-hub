<?php
// staff/announcements.php - CSWDO Staff Announcements & Bulletins Hub
require_once __DIR__ . '/../config/database.php';

if (!isStaff() && !isAdmin()) {
    redirect('../login.php');
}

// Mark announcements as viewed by staff
setcookie('last_viewed_staff_announcements', (string)time(), time() + (86400 * 365), '/');
$_COOKIE['last_viewed_staff_announcements'] = (string)time();

$database = new Database();
$db = $database->getConnection();

$staff_id_query = "SELECT s.id, s.position, u.profile_picture FROM staff s JOIN users u ON s.user_id = u.user_id WHERE s.user_id = :user_id";
$staff_id_stmt = $db->prepare($staff_id_query);
$staff_id_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_id_stmt->execute();
$staff_info = $staff_id_stmt->fetch(PDO::FETCH_ASSOC);
$staff_id = $staff_info ? $staff_info['id'] : 0;

$ann_success = '';
$ann_error = '';

if (isset($_GET['created'])) {
    $ann_success = 'Announcement broadcasted and published successfully!';
}
if (isset($_GET['deleted'])) {
    $ann_success = 'Announcement removed successfully.';
}
if (isset($_GET['updated'])) {
    $ann_success = 'Notice / Announcement updated successfully!';
}

// Handle New Announcement Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_announcement') {
    csrf_verify();

    $title = trim(sanitize($_POST['title'] ?? ''));
    $content = trim(sanitize($_POST['content'] ?? ''));
    $category = trim(sanitize($_POST['category'] ?? 'general'));
    $ann_date = trim($_POST['announcement_date'] ?? '');
    $ann_time = trim($_POST['announcement_time'] ?? '');
    $notify_residents = isset($_POST['notify_residents']) && $_POST['notify_residents'] === '1';

    $created_at = date('Y-m-d H:i:s');
    if (!empty($ann_date)) {
        if (!empty($ann_time)) {
            $created_at = date('Y-m-d H:i:s', strtotime("$ann_date $ann_time"));
        } else {
            $created_at = date('Y-m-d H:i:s', strtotime("$ann_date " . date('H:i:s')));
        }
    }

    if (empty($title) || empty($content)) {
        $ann_error = 'Please provide both an announcement title and content/description.';
    } else {
        $image_path = null;

        // Handle Image / File Attachment Upload
        if (isset($_FILES['announcement_image']) && $_FILES['announcement_image']['error'] === UPLOAD_ERR_OK) {
            require_once __DIR__ . '/../includes/FileUploader.php';
            try {
                $uploader = new FileUploader();
                $file = [
                    'name' => $_FILES['announcement_image']['name'],
                    'type' => $_FILES['announcement_image']['type'],
                    'tmp_name' => $_FILES['announcement_image']['tmp_name'],
                    'error' => $_FILES['announcement_image']['error'],
                    'size' => $_FILES['announcement_image']['size']
                ];
                $image_path = $uploader->upload($file, 'announcements');
            } catch (Exception $e) {
                $ann_error = "File upload error: " . $e->getMessage();
            }
        }

        if (empty($ann_error)) {
            $query = "INSERT INTO announcements (title, content, category, created_by, image_path, created_at) 
                      VALUES (:title, :content, :category, :created_by, :image_path, :created_at)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':created_by', $_SESSION['user_id']);
            $stmt->bindParam(':image_path', $image_path);
            $stmt->bindParam(':created_at', $created_at);

            if ($stmt->execute()) {
                // Dispatch notification to residents if notify_residents is checked
                if ($notify_residents) {
                    try {
                        $notif_title = 'New Announcement: ' . mb_strimwidth($title, 0, 50, '...');
                        $notif_msg = mb_strimwidth(strip_tags($content), 0, 150, '...');
                        $notif_type = in_array(strtolower($category), ['aics', 'housing', 'appointment']) ? strtolower($category) : 'general';

                        $n_stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) SELECT user_id, :title, :msg, :type, 0, NOW() FROM users WHERE role = 'resident' AND is_active = 1");
                        $n_stmt->execute([
                            ':title' => $notif_title,
                            ':msg' => $notif_msg,
                            ':type' => $notif_type
                        ]);
                    } catch (Exception $ne) {}
                }

                // Add audit log
                try {
                    $log_query = "INSERT INTO audit_logs (user_id, action, table_affected, new_values) VALUES (:user_id, 'create_announcement', 'announcements', :details)";
                    $log_stmt = $db->prepare($log_query);
                    $log_details = "Staff published bulletin: $title";
                    $log_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $log_stmt->bindParam(':details', $log_details);
                    $log_stmt->execute();
                } catch (Exception $ae) {}

                header('Location: announcements.php?created=1');
                exit();
            } else {
                $ann_error = 'Database error: Could not save announcement.';
            }
        }
    }
}

// Handle Announcement Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_announcement') {
    csrf_verify();

    $ann_id = intval($_POST['announcement_id'] ?? 0);
    $title = trim(sanitize($_POST['title'] ?? ''));
    $content_body = trim(sanitize($_POST['content'] ?? ''));
    $category = trim(sanitize($_POST['category'] ?? 'general'));
    $ann_date = trim($_POST['announcement_date'] ?? '');
    $ann_time = trim($_POST['announcement_time'] ?? '');
    $remove_image = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';
    $notify_residents = isset($_POST['notify_residents']) && $_POST['notify_residents'] === '1';

    $chk_stmt = $db->prepare("SELECT * FROM announcements WHERE announcement_id = :id");
    $chk_stmt->execute([':id' => $ann_id]);
    $current_ann = $chk_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current_ann) {
        $ann_error = 'Notice not found or already removed.';
    } elseif (empty($title) || empty($content_body)) {
        $ann_error = 'Please provide both a notice title and content description.';
    } else {
        $image_path = $current_ann['image_path'];

        if ($remove_image && !empty($image_path)) {
            $f_disk = __DIR__ . '/../' . ltrim($image_path, '/');
            if (file_exists($f_disk)) @unlink($f_disk);
            $image_path = null;
        }

        if (isset($_FILES['announcement_image']) && $_FILES['announcement_image']['error'] === UPLOAD_ERR_OK) {
            require_once __DIR__ . '/../includes/FileUploader.php';
            try {
                $uploader = new FileUploader();
                $file = [
                    'name' => $_FILES['announcement_image']['name'],
                    'type' => $_FILES['announcement_image']['type'],
                    'tmp_name' => $_FILES['announcement_image']['tmp_name'],
                    'error' => $_FILES['announcement_image']['error'],
                    'size' => $_FILES['announcement_image']['size']
                ];
                $new_image_path = $uploader->upload($file, 'announcements');
                if (!empty($current_ann['image_path'])) {
                    $old_disk = __DIR__ . '/../' . ltrim($current_ann['image_path'], '/');
                    if (file_exists($old_disk)) @unlink($old_disk);
                }
                $image_path = $new_image_path;
            } catch (Exception $e) {
                $ann_error = "File upload error: " . $e->getMessage();
            }
        }

        if (empty($ann_error)) {
            $created_at = $current_ann['created_at'];
            if (!empty($ann_date)) {
                if (!empty($ann_time)) {
                    $created_at = date('Y-m-d H:i:s', strtotime("$ann_date $ann_time"));
                } else {
                    $created_at = date('Y-m-d H:i:s', strtotime("$ann_date " . date('H:i:s')));
                }
            }

            $up_stmt = $db->prepare("UPDATE announcements SET title = :title, content = :content, category = :category, image_path = :image_path, created_at = :created_at WHERE announcement_id = :id");
            $up_stmt->bindParam(':title', $title);
            $up_stmt->bindParam(':content', $content_body);
            $up_stmt->bindParam(':category', $category);
            $up_stmt->bindParam(':image_path', $image_path);
            $up_stmt->bindParam(':created_at', $created_at);
            $up_stmt->bindParam(':id', $ann_id);

            if ($up_stmt->execute()) {
                if ($notify_residents) {
                    try {
                        $notif_title = 'Notice Updated: ' . mb_strimwidth($title, 0, 50, '...');
                        $notif_msg = mb_strimwidth(strip_tags($content_body), 0, 150, '...');
                        $notif_type = in_array(strtolower($category), ['aics', 'housing', 'appointment']) ? strtolower($category) : 'general';

                        $n_stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) SELECT user_id, :title, :msg, :type, 0, NOW() FROM users WHERE role = 'resident' AND is_active = 1");
                        $n_stmt->execute([
                            ':title' => $notif_title,
                            ':msg' => $notif_msg,
                            ':type' => $notif_type
                        ]);
                    } catch (Exception $ne) {}
                }

                try {
                    $log_query = "INSERT INTO audit_logs (user_id, action, table_affected, new_values) VALUES (:user_id, 'edit_announcement', 'announcements', :details)";
                    $log_stmt = $db->prepare($log_query);
                    $log_details = "Staff updated bulletin ID #$ann_id: $title";
                    $log_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $log_stmt->bindParam(':details', $log_details);
                    $log_stmt->execute();
                } catch (Exception $ae) {}

                header('Location: announcements.php?updated=1');
                exit();
            } else {
                $ann_error = 'Database error: Could not update announcement.';
            }
        }
    }
}

// Handle Delete Announcement
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $chk_stmt = $db->prepare("SELECT created_by, image_path, title FROM announcements WHERE announcement_id = :id");
    $chk_stmt->execute([':id' => $del_id]);
    $ann_item = $chk_stmt->fetch(PDO::FETCH_ASSOC);

    if ($ann_item && (isAdmin() || isStaff() || $ann_item['created_by'] == $_SESSION['user_id'])) {
        if (!empty($ann_item['image_path'])) {
            $f_disk = __DIR__ . '/../' . ltrim($ann_item['image_path'], '/');
            if (file_exists($f_disk)) @unlink($f_disk);
        }
        $del_stmt = $db->prepare("DELETE FROM announcements WHERE announcement_id = :id");
        $del_stmt->execute([':id' => $del_id]);

        // Add audit log
        try {
            $log_query = "INSERT INTO audit_logs (user_id, action, table_affected, old_values) VALUES (:user_id, 'delete_announcement', 'announcements', :details)";
            $log_stmt = $db->prepare($log_query);
            $log_details = "Deleted bulletin ID: $del_id (" . $ann_item['title'] . ")";
            $log_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $log_stmt->bindParam(':details', $log_details);
            $log_stmt->execute();
        } catch (Exception $ae) {}

        header('Location: announcements.php?deleted=1');
        exit();
    }
}

// Fetch announcements with author details
$query = "SELECT a.*, u.first_name, u.last_name, u.role, u.email 
          FROM announcements a 
          LEFT JOIN users u ON a.created_by = u.user_id 
          ORDER BY a.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Counts & Analytics
$total_announcements = count($announcements);
$media_count = 0;
$aics_count = 0;
$housing_count = 0;

$slideshow_items = [];
foreach ($announcements as $ann) {
    $img_on_disk = __DIR__ . '/../' . ltrim($ann['image_path'], '/');
    if (!empty($ann['image_path']) && file_exists($img_on_disk)) {
        $slideshow_items[] = $ann;
        $media_count++;
    }
    $cat = strtolower($ann['category'] ?? '');
    if ($cat === 'aics') $aics_count++;
    if ($cat === 'housing') $housing_count++;
}

$page_title = 'Announcements & Bulletins - CSWDO Staff Portal';
$active_nav = 'announcements';
require_once __DIR__ . '/includes/staff-head.php';
?>
<style>
/* ==========================================================================
   STAFF ANNOUNCEMENTS HUB STYLING
   ========================================================================== */
.staff-announcements-wrap {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

/* Stat Grid */
.ann-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}

.ann-stat-card {
    background: var(--ref-card, #FFFFFF);
    border: 1px solid var(--ref-border, rgba(0,0,0,0.08));
    border-radius: 12px;
    padding: 10px 14px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.02);
    display: flex;
    align-items: center;
    gap: 12px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.ann-stat-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(15, 65, 74, 0.06);
}

.ann-stat-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

.icon-maroon { background: rgba(127, 3, 3, 0.1); color: #7F0303; }
.icon-teal { background: rgba(15, 65, 74, 0.12); color: #0F414A; }
.icon-khaki { background: rgba(201, 168, 108, 0.2); color: #8A6421; }
.icon-green { background: rgba(0, 150, 136, 0.12); color: #009688; }

.ann-stat-info h3 {
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--ref-text, #191D1E);
    margin: 0;
    line-height: 1.1;
}

.ann-stat-info p {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--ref-muted, #71818B);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin: 2px 0 0 0;
}

/* ==========================================================================
   FEATURED SLIDESHOW / CAROUSEL (IMAGE BULLETINS)
   ========================================================================== */
.staff-carousel-wrapper {
    position: relative;
    background: var(--ref-card, #FFFFFF);
    border-radius: 20px;
    border: 1px solid var(--ref-border, rgba(0,0,0,0.08));
    overflow: hidden;
    box-shadow: 0 8px 26px rgba(15, 65, 74, 0.07);
}

.carousel-corner-tag {
    position: absolute;
    top: 18px;
    left: 20px;
    z-index: 10;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    background: rgba(15, 65, 74, 0.9);
    backdrop-filter: blur(8px);
    border-radius: 999px;
    color: #F6D8A8;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border: 1px solid rgba(246, 216, 168, 0.3);
}

.carousel-stage {
    position: relative;
    width: 100%;
    height: 380px;
    overflow: hidden;
}

.staff-slide {
    position: absolute;
    inset: 0;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.6s cubic-bezier(0.4, 0, 0.2, 1), transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    transform: scale(1.02);
    display: flex;
    align-items: stretch;
}

.staff-slide.active {
    opacity: 1;
    visibility: visible;
    transform: scale(1);
    z-index: 2;
}

.slide-img-cover {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    filter: brightness(0.9);
    transition: transform 7s ease;
}

.staff-slide.active .slide-img-cover {
    transform: scale(1.04);
}

.slide-overlay-mask {
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, 
        rgba(5, 26, 22, 0.94) 0%, 
        rgba(5, 26, 22, 0.82) 48%, 
        rgba(5, 26, 22, 0.35) 78%, 
        rgba(5, 26, 22, 0.15) 100%);
    z-index: 1;
}

.slide-text-box {
    position: relative;
    z-index: 3;
    max-width: 640px;
    padding: 50px 36px 42px 36px;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    color: #FFFFFF;
    height: 100%;
}

.pill-badge-cat {
    align-self: flex-start;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 12px;
}

.cat-bg-general { background: #2196F3; color: #FFFFFF; }
.cat-bg-aics { background: #7F0303; color: #FFFFFF; }
.cat-bg-housing { background: #009688; color: #FFFFFF; }
.cat-bg-appointment { background: #FF9800; color: #FFFFFF; }

.slide-heading {
    font-size: 1.7rem;
    font-weight: 800;
    line-height: 1.25;
    color: #FFFFFF;
    margin: 0 0 10px 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.slide-snippet {
    font-size: 0.88rem;
    line-height: 1.6;
    color: rgba(255, 255, 255, 0.85);
    margin: 0 0 18px 0;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.slide-meta-strip {
    display: flex;
    align-items: center;
    gap: 16px;
    font-size: 0.76rem;
    color: rgba(255, 255, 255, 0.72);
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.slide-meta-strip span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.slide-meta-strip i {
    color: #F6D8A8;
}

.btn-slide-detail {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 22px;
    background: linear-gradient(135deg, #7F0303, #9A1515);
    color: #FFFFFF;
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 4px 14px rgba(127, 3, 3, 0.35);
    transition: all 0.25s ease;
}

.btn-slide-detail:hover {
    background: linear-gradient(135deg, #9A1515, #B31E1E);
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(127, 3, 3, 0.45);
    color: #FFFFFF;
}

/* Carousel Arrow Controls */
.carousel-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 10;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: rgba(15, 65, 74, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.25);
    backdrop-filter: blur(6px);
    color: #FFFFFF;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.9rem;
}

.carousel-arrow:hover {
    background: #7F0303;
    transform: translateY(-50%) scale(1.08);
}

.carousel-arrow.left { left: 20px; }
.carousel-arrow.right { right: 20px; }

/* Bottom Indicators */
.carousel-nav-dock {
    position: absolute;
    bottom: 18px;
    right: 22px;
    z-index: 10;
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(5, 26, 22, 0.7);
    padding: 6px 12px;
    border-radius: 999px;
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.dock-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.4);
    cursor: pointer;
    transition: all 0.3s ease;
}

.dock-dot.active {
    width: 22px;
    background: #F6D8A8;
}

.dock-counter {
    font-size: 0.72rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.85);
    margin-left: 4px;
}

.dock-pause-btn {
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.75);
    font-size: 0.75rem;
    cursor: pointer;
    padding: 2px 4px;
    transition: color 0.2s;
}

.dock-pause-btn:hover {
    color: #FFFFFF;
}

/* ==========================================================================
   TOOLBAR & CATEGORY FILTERING
   ========================================================================== */
.staff-ann-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    padding: 12px 18px;
    background: var(--ref-card, #FFFFFF);
    border-radius: 16px;
    border: 1px solid var(--ref-border, rgba(0,0,0,0.08));
}

.staff-filter-tabs {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.staff-filter-tab {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
    background: var(--ref-canvas, #E4EFE7);
    color: var(--ref-text, #191D1E);
    border: 1px solid var(--ref-border, rgba(0,0,0,0.08));
    cursor: pointer;
    transition: all 0.2s ease;
}

.staff-filter-tab:hover {
    background: rgba(127, 3, 3, 0.08);
}

.staff-filter-tab.active {
    background: #7F0303;
    color: #FFFFFF;
    border-color: #7F0303;
}

.staff-filter-tab .tab-cnt {
    background: rgba(0,0,0,0.08);
    padding: 2px 6px;
    border-radius: 999px;
    font-size: 0.65rem;
}

.staff-filter-tab.active .tab-cnt {
    background: rgba(255, 255, 255, 0.25);
    color: #FFFFFF;
}

.staff-search-wrap {
    position: relative;
    min-width: 250px;
    flex-grow: 1;
    max-width: 340px;
}

.staff-search-wrap i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--ref-muted, #71818B);
    font-size: 0.85rem;
}

.staff-search-wrap input {
    width: 100%;
    padding: 8px 36px 8px 38px;
    background: var(--ref-canvas, #E4EFE7);
    border: 1px solid var(--ref-border, rgba(0,0,0,0.08));
    border-radius: 999px;
    font-size: 0.8rem;
    color: var(--ref-text, #191D1E);
    outline: none;
    transition: all 0.2s ease;
}

.staff-search-wrap input:focus {
    border-color: #7F0303;
    background: var(--ref-card, #FFFFFF);
    box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
}

.staff-search-clear {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--ref-muted, #71818B);
    cursor: pointer;
    font-size: 0.8rem;
    display: none;
}

/* ==========================================================================
   CARDS GRID (WITH & WITHOUT IMAGES)
   ========================================================================== */
.staff-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 22px;
}

.staff-ann-card {
    background: var(--ref-card, #FFFFFF);
    border-radius: 18px;
    border: 1px solid var(--ref-border, rgba(0,0,0,0.08));
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.staff-ann-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 24px rgba(15, 65, 74, 0.1);
    border-color: rgba(201, 168, 108, 0.4);
}

/* Image Banner */
.card-thumb-wrap {
    position: relative;
    height: 180px;
    width: 100%;
    overflow: hidden;
    background: #092721;
}

.card-thumb-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    transition: transform 0.4s ease;
}

.staff-ann-card:hover .card-thumb-wrap img {
    transform: scale(1.05);
}

.card-thumb-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    z-index: 2;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    box-shadow: 0 2px 6px rgba(0,0,0,0.25);
}

/* No-Image Header */
.card-no-img-bar {
    position: relative;
    padding: 16px 20px 12px 20px;
    border-top: 5px solid #7F0303;
    background: linear-gradient(180deg, rgba(167, 215, 197, 0.14) 0%, transparent 100%);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.card-no-img-bar.cat-general { border-top-color: #2196F3; }
.card-no-img-bar.cat-aics { border-top-color: #7F0303; }
.card-no-img-bar.cat-housing { border-top-color: #009688; }
.card-no-img-bar.cat-appointment { border-top-color: #FF9800; }

.card-cat-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--ref-canvas, #E4EFE7);
    color: #7F0303;
    border: 1px solid var(--ref-border, rgba(0,0,0,0.08));
    font-size: 0.95rem;
}

.card-inner-body {
    padding: 20px;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
}

.card-meta-stamp {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.74rem;
    color: var(--ref-muted, #71818B);
    margin-bottom: 10px;
}

.card-meta-stamp span {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.card-ann-headline {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--ref-text, #191D1E);
    line-height: 1.35;
    margin: 0 0 10px 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.card-ann-desc {
    font-size: 0.84rem;
    color: var(--ref-muted, #71818B);
    line-height: 1.6;
    margin: 0 0 18px 0;
    flex-grow: 1;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.card-bottom-bar {
    border-top: 1px solid var(--ref-border, rgba(0,0,0,0.08));
    padding-top: 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.card-author-pill {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--ref-muted, #71818B);
}

.author-chip-avatar {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0F414A, #1A5A66);
    color: #FFFFFF;
    font-size: 0.65rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-card-edit {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 14px;
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 700;
    color: #FFF3DD !important; /* Cream color */
    background: rgba(254, 243, 219, 0.16) !important;
    border: 1px solid rgba(254, 243, 219, 0.4) !important;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-card-edit:hover {
    background: #FFF3DD !important;
    color: #7F0303 !important;
    border-color: #FFF3DD !important;
    transform: translateY(-1px);
}

.btn-card-delete {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 700;
    color: #FCA5A5 !important;
    background: rgba(239, 68, 68, 0.18) !important;
    border: 1px solid rgba(239, 68, 68, 0.35) !important;
    cursor: pointer;
    text-decoration: none !important;
    transition: all 0.2s ease;
}

.btn-card-delete:hover {
    background: #DC2626 !important;
    color: #FFFFFF !important;
    border-color: #DC2626 !important;
    transform: translateY(-1px);
}

.btn-card-inspect {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 700;
    color: #7F0303;
    background: rgba(127, 3, 3, 0.08);
    border: 1px solid rgba(127, 3, 3, 0.18);
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-card-inspect:hover {
    background: #7F0303;
    color: #FFFFFF;
    border-color: #7F0303;
}

/* Modal */
.staff-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(5, 26, 22, 0.78);
    backdrop-filter: blur(8px);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.staff-modal-backdrop.active {
    opacity: 1;
    visibility: visible;
}

.staff-modal-box {
    background: var(--ref-card, #FFFFFF);
    border-radius: 22px;
    width: 100%;
    max-width: 740px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
    border: 1px solid var(--ref-border, rgba(0,0,0,0.1));
    transform: scale(0.96) translateY(8px);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.staff-modal-backdrop.active .staff-modal-box {
    transform: scale(1) translateY(0);
}

.modal-bar-top {
    padding: 18px 24px;
    border-bottom: 1px solid var(--ref-border, rgba(0,0,0,0.08));
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--ref-canvas, #E4EFE7);
}

.btn-staff-modal-close {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: var(--ref-card, #FFFFFF);
    border: 1px solid var(--ref-border, rgba(0,0,0,0.1));
    color: var(--ref-text, #191D1E);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.9rem;
    transition: all 0.2s ease;
}

.btn-staff-modal-close:hover {
    background: #7F0303;
    color: #FFFFFF;
    border-color: #7F0303;
    transform: rotate(90deg);
}

.modal-content-scroller {
    padding: 24px;
    overflow-y: auto;
    flex-grow: 1;
}

.modal-hero-photo {
    width: 100%;
    max-height: 340px;
    object-fit: cover;
    border-radius: 14px;
    margin-bottom: 20px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.08);
}

.modal-headline-lg {
    font-size: 1.45rem;
    font-weight: 800;
    color: var(--ref-text, #191D1E);
    line-height: 1.3;
    margin: 0 0 12px 0;
}

.modal-facts-strip {
    display: flex;
    align-items: center;
    gap: 16px;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--ref-border, rgba(0,0,0,0.08));
    margin-bottom: 18px;
    font-size: 0.78rem;
    color: var(--ref-muted, #71818B);
    flex-wrap: wrap;
}

.modal-facts-strip span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.modal-facts-strip i {
    color: #7F0303;
}

.modal-body-prose {
    font-size: 0.92rem;
    line-height: 1.7;
    color: var(--ref-text, #191D1E);
    margin-bottom: 20px;
    white-space: pre-line;
}

.modal-bar-bottom {
    padding: 14px 24px;
    border-top: 1px solid var(--ref-border, rgba(0,0,0,0.08));
    background: var(--ref-canvas, #E4EFE7);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

@media (max-width: 900px) {
    .carousel-stage { height: 340px; }
    .slide-text-box { padding: 36px 20px; }
    .slide-heading { font-size: 1.35rem; }
    .carousel-arrow { display: none; }
}

@media (max-width: 768px) {
    .staff-ann-toolbar { flex-direction: column; align-items: stretch; }
    .staff-search-wrap { max-width: 100%; }
    .staff-cards-grid { grid-template-columns: 1fr; }
}

/* ==========================================================================
   CREATE ANNOUNCEMENT MODAL & BUTTON STYLES
   ========================================================================== */
.btn-create-announcement {
    background: linear-gradient(135deg, #7F0303 0%, #A31212 100%);
    color: #FFFFFF !important;
    border: none;
    padding: 10px 22px;
    border-radius: 50px;
    font-size: 0.84rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(127, 3, 3, 0.28);
    transition: all 0.25s ease;
}
.btn-create-announcement:hover {
    background: linear-gradient(135deg, #9E1A1A 0%, #B72222 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(127, 3, 3, 0.4);
    color: #FFFFFF !important;
}

.ann-alert {
    padding: 12px 18px;
    border-radius: 12px;
    font-size: 0.86rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}
.ann-alert-success {
    background: rgba(46, 125, 50, 0.12);
    color: #1B5E20;
    border: 1px solid rgba(46, 125, 50, 0.25);
}
.ann-alert-error {
    background: rgba(211, 47, 47, 0.12);
    color: #C62828;
    border: 1px solid rgba(211, 47, 47, 0.25);
}

.create-modal-grid {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.form-group-custom {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.form-group-custom label {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--ref-text, #191D1E);
    display: flex;
    align-items: center;
    gap: 6px;
}
.form-group-custom label i {
    color: #7F0303;
}
.form-input-custom,
.form-select-custom,
.form-textarea-custom {
    width: 100%;
    padding: 10px 14px;
    border-radius: 10px;
    border: 1px solid var(--ref-border, rgba(0,0,0,0.12));
    background: var(--ref-card, #FFFFFF);
    color: var(--ref-text, #191D1E);
    font-size: 0.85rem;
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    box-sizing: border-box;
    font-family: inherit;
}
.form-input-custom:focus,
.form-select-custom:focus,
.form-textarea-custom:focus {
    border-color: #7F0303;
    box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.12);
}
.form-row-2col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}
@media (max-width: 600px) {
    .form-row-2col { grid-template-columns: 1fr; }
}
.file-drop-zone {
    border: 2px dashed rgba(15, 65, 74, 0.3);
    background: rgba(167, 215, 197, 0.08);
    border-radius: 12px;
    padding: 16px 18px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}
.file-drop-zone:hover {
    border-color: #7F0303;
    background: rgba(127, 3, 3, 0.04);
}
.file-drop-zone input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
    width: 100%;
    height: 100%;
}
.notify-toggle-box {
    display: flex;
    align-items: center;
    gap: 12px;
    background: rgba(15, 65, 74, 0.06);
    border: 1px solid rgba(15, 65, 74, 0.15);
    border-radius: 12px;
    padding: 12px 16px;
    cursor: pointer;
}
.notify-toggle-box input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #7F0303;
    cursor: pointer;
}
.notify-toggle-text h4 {
    margin: 0 0 2px 0;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--ref-text, #191D1E);
}
.notify-toggle-text p {
    margin: 0;
    font-size: 0.72rem;
    color: var(--ref-muted, #71818B);
}

/* Official Spotlight Announcement Banner */
.official-spotlight-banner {
    background: linear-gradient(135deg, #09231A 0%, #0F3B2E 50%, #081B15 100%);
    border-radius: 24px;
    border: 1.5px solid rgba(201, 168, 108, 0.45);
    padding: 24px;
    margin-bottom: 28px;
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 28px;
    align-items: center;
    box-shadow: 0 14px 40px rgba(8, 27, 21, 0.25);
    position: relative;
    overflow: hidden;
}

.spotlight-banner-media {
    position: relative;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
    border: 2px solid rgba(201, 168, 108, 0.5);
    background: #000;
}

.spotlight-banner-img {
    width: 100%;
    height: auto;
    display: block;
    object-fit: cover;
    transition: transform 0.4s ease;
}

.spotlight-banner-media:hover .spotlight-banner-img {
    transform: scale(1.03);
}

.spotlight-banner-content {
    display: flex;
    flex-direction: column;
    gap: 12px;
    color: #FFFFFF;
}

.spotlight-badge {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.spotlight-badge .badge-pulse {
    background: rgba(220, 38, 38, 0.25);
    border: 1px solid rgba(239, 68, 68, 0.6);
    color: #FCA5A5;
    padding: 5px 12px;
    border-radius: 50px;
    font-size: 0.74rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.spotlight-badge .badge-pulse i {
    font-size: 0.55rem;
    color: #EF4444;
    animation: pulseDot 1.4s infinite;
}

@keyframes pulseDot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(1.25); }
}

.spotlight-badge .badge-cat {
    background: rgba(201, 168, 108, 0.2);
    border: 1px solid rgba(201, 168, 108, 0.45);
    color: #E8D5A8;
    padding: 5px 12px;
    border-radius: 50px;
    font-size: 0.74rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.spotlight-title {
    font-size: clamp(1.3rem, 2.2vw, 1.75rem);
    font-weight: 800;
    color: #FFFFFF;
    margin: 0;
    line-height: 1.25;
    letter-spacing: -0.01em;
}

.spotlight-desc {
    font-size: 0.92rem;
    color: rgba(255, 255, 255, 0.85);
    line-height: 1.55;
    margin: 0;
}

.spotlight-meta {
    display: flex;
    align-items: center;
    gap: 18px;
    flex-wrap: wrap;
    font-size: 0.8rem;
    color: #D8BA98;
    padding-top: 4px;
}

.spotlight-meta span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.spotlight-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 6px;
}

.btn-spotlight-primary {
    background: #7F0303;
    color: #FFFFFF;
    border: none;
    border-radius: 50px;
    padding: 10px 22px;
    font-size: 0.84rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    box-shadow: 0 4px 16px rgba(127, 3, 3, 0.35);
    transition: all 0.2s ease;
}

.btn-spotlight-primary:hover {
    background: #9A0404;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(127, 3, 3, 0.45);
}

.btn-spotlight-secondary {
    background: rgba(255, 255, 255, 0.12);
    color: #FFFFFF;
    border: 1.5px solid rgba(255, 255, 255, 0.3);
    border-radius: 50px;
    padding: 9px 20px;
    font-size: 0.84rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: all 0.2s ease;
    backdrop-filter: blur(8px);
}

.btn-spotlight-secondary:hover {
    background: rgba(255, 255, 255, 0.22);
    color: #FFFFFF;
    transform: translateY(-2px);
}

.staff-btn-create-ann {
    background: #7F0303;
    color: #FFFFFF;
    border: none;
    border-radius: 50px;
    padding: 9px 20px;
    font-size: 0.82rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    white-space: nowrap;
    box-shadow: 0 4px 12px rgba(127, 3, 3, 0.25);
    transition: all 0.2s ease;
}

.staff-btn-create-ann:hover {
    background: #9A0404;
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(127, 3, 3, 0.35);
}

/* Card Background using Dark Green Abstract Texture */
.staff-ann-card {
    background: linear-gradient(180deg, rgba(12, 38, 30, 0.88) 0%, rgba(7, 24, 19, 0.94) 100%), url('../images/announcements/announcement_card_bg.jpg') center/cover no-repeat !important;
    border-radius: 20px !important;
    border: 1.5px solid rgba(201, 168, 108, 0.35) !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.18) !important;
    color: #FFFFFF !important;
}

.staff-ann-card:hover {
    transform: translateY(-6px) !important;
    border-color: rgba(229, 179, 56, 0.7) !important;
    box-shadow: 0 16px 36px rgba(12, 38, 30, 0.35) !important;
}

.staff-ann-card .card-ann-headline {
    color: #FFFFFF !important;
}

.staff-ann-card .card-ann-desc {
    color: rgba(255, 255, 255, 0.82) !important;
}

.staff-ann-card .card-meta-stamp span {
    color: #E8D5A8 !important;
}

.staff-ann-card .card-author-pill span {
    color: rgba(255, 255, 255, 0.9) !important;
}

.staff-ann-card .card-bottom-bar {
    border-top: 1px solid rgba(255, 255, 255, 0.12) !important;
}

.staff-ann-card .btn-card-edit {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 14px;
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 700;
    color: #FFF3DD !important; /* Cream color */
    background: rgba(254, 243, 219, 0.16) !important;
    border: 1px solid rgba(254, 243, 219, 0.4) !important;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-card-edit:hover {
    background: #FFF3DD !important;
    color: #7F0303 !important;
    border-color: #FFF3DD !important;
    transform: translateY(-1px);
}

.btn-card-delete {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 700;
    color: #FCA5A5 !important;
    background: rgba(239, 68, 68, 0.18) !important;
    border: 1px solid rgba(239, 68, 68, 0.35) !important;
    cursor: pointer;
    text-decoration: none !important;
    transition: all 0.2s ease;
}

.btn-card-delete:hover {
    background: #DC2626 !important;
    color: #FFFFFF !important;
    border-color: #DC2626 !important;
    transform: translateY(-1px);
}

.btn-card-inspect {
    background: rgba(255, 255, 255, 0.15) !important;
    color: #FFFFFF !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
}

.staff-ann-card .btn-card-inspect:hover {
    background: #FFFFFF !important;
    color: #0F414A !important;
}

@media (max-width: 900px) {
    .official-spotlight-banner {
        grid-template-columns: 1fr;
        gap: 20px;
        padding: 18px;
    }
    .spotlight-banner-media {
        max-width: 380px;
        margin: 0 auto;
    }
}

</style>
</head>
<body>
<div class="app-container">
    <!-- Modern Staff Sidebar -->
    <?php require_once __DIR__ . '/includes/staff-sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Unified Staff Header Bar -->
        <?php require_once __DIR__ . '/includes/staff-header.php'; ?>

        <!-- Elevated Big Card Canvas -->
        <div class="staff-big-card">
            <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = 'Public Announcements & Bulletins';
            $banner_subtitle = 'Manage and review official bulletins and service advisories broadcasted to residents';
            $banner_icon = 'fa-bullhorn';
            // Redundant top banner buttons removed; actions are available in the spotlight banner and toolbar below
            include __DIR__ . '/../includes/portal-banner.php';
            ?>

            <?php if (!empty($ann_success)): ?>
                <div class="ann-alert ann-alert-success">
                    <i class="fas fa-check-circle" style="font-size: 1.1rem;"></i>
                    <span><?php echo htmlspecialchars($ann_success); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($ann_error)): ?>
                <div class="ann-alert ann-alert-error">
                    <i class="fas fa-exclamation-triangle" style="font-size: 1.1rem;"></i>
                    <span><?php echo htmlspecialchars($ann_error); ?></span>
                </div>
            <?php endif; ?>

            <div class="staff-announcements-wrap">
                <!-- 4 Top Stat Cards -->
                <div class="ann-stats-grid">
                    <div class="ann-stat-card">
                        <div class="ann-stat-icon icon-maroon"><i class="fas fa-bullhorn"></i></div>
                        <div class="ann-stat-info">
                            <h3><?php echo $total_announcements; ?></h3>
                            <p>Total Bulletins</p>
                        </div>
                    </div>
                    <div class="ann-stat-card">
                        <div class="ann-stat-icon icon-teal"><i class="fas fa-camera"></i></div>
                        <div class="ann-stat-info">
                            <h3><?php echo $media_count; ?></h3>
                            <p>Photo Bulletins</p>
                        </div>
                    </div>
                    <div class="ann-stat-card">
                        <div class="ann-stat-icon icon-khaki"><i class="fas fa-hand-holding-heart"></i></div>
                        <div class="ann-stat-info">
                            <h3><?php echo $aics_count; ?></h3>
                            <p>AICS Advisories</p>
                        </div>
                    </div>
                    <div class="ann-stat-card">
                        <div class="ann-stat-icon icon-green"><i class="fas fa-house-chimney"></i></div>
                        <div class="ann-stat-info">
                            <h3><?php echo $housing_count; ?></h3>
                            <p>Housing Notices</p>
                        </div>
                    </div>
                </div>

                <!-- ========================================================
                     OFFICIAL SPOTLIGHT ANNOUNCEMENT BANNER
                     ======================================================== -->
                <section class="official-spotlight-banner">
                    <div class="spotlight-banner-media">
                        <img src="../images/announcements/announcement_main_banner.jpg" alt="Official CSWDO Announcement - New Date" class="spotlight-banner-img">
                    </div>
                    <div class="spotlight-banner-content">
                        <div class="spotlight-badge">
                            <span class="badge-pulse"><i class="fas fa-circle"></i> Live Official Broadcast</span>
                            <span class="badge-cat"><i class="fas fa-bullhorn"></i> CSWDO Advisory</span>
                        </div>
                        <h2 class="spotlight-title">Schedule Adjustment &amp; New Date Announcement</h2>
                        <p class="spotlight-desc">City Social Welfare and Development Office (CSWDO) - Palayan City official public advisory regarding updated service schedules, beneficiary aid distributions, and office appointments.</p>
                        <div class="spotlight-meta">
                            <span><i class="far fa-calendar-check"></i> Active &amp; Broadcasted</span>
                            <span><i class="fas fa-building-columns"></i> CSWDO Operations</span>
                            <span><i class="fas fa-users"></i> All Palayan Residents</span>
                        </div>
                        <div class="spotlight-actions">
                            <button type="button" class="btn-spotlight-primary" onclick="openCreateModal()">
                                <i class="fas fa-plus-circle"></i> Post New Bulletin
                            </button>
                            <a href="../announcements.php" target="_blank" class="btn-spotlight-secondary">
                                <i class="fas fa-external-link-alt"></i> Resident View
                            </a>
                        </div>
                    </div>
                </section>

                <!-- ========================================================
                     TOOLBAR & CATEGORY FILTERING
                     ======================================================== -->
                <div class="staff-ann-toolbar">
                    <div class="staff-filter-tabs" id="staffCatFilters">
                        <button type="button" class="staff-filter-tab active" data-category="all">
                            <span>All Bulletins</span>
                            <span class="tab-cnt"><?php echo count($announcements); ?></span>
                        </button>
                        <button type="button" class="staff-filter-tab" data-category="aics">
                            <i class="fas fa-hand-holding-heart" style="color: #7F0303;"></i>
                            <span>AICS</span>
                        </button>
                        <button type="button" class="staff-filter-tab" data-category="housing">
                            <i class="fas fa-house-chimney" style="color: #009688;"></i>
                            <span>Housing</span>
                        </button>
                        <button type="button" class="staff-filter-tab" data-category="appointment">
                            <i class="fas fa-calendar-check" style="color: #FF9800;"></i>
                            <span>Appointments</span>
                        </button>
                        <button type="button" class="staff-filter-tab" data-category="general">
                            <i class="fas fa-info-circle" style="color: #2196F3;"></i>
                            <span>General</span>
                        </button>
                    </div>

                    <div style="display: flex; align-items: center; gap: 12px; margin-left: auto;">
                        <div class="staff-search-wrap">
                            <i class="fas fa-search"></i>
                            <input type="text" id="staffAnnSearch" placeholder="Search by title, keyword, or author..." autocomplete="off">
                            <button type="button" class="staff-search-clear" id="staffSearchClear"><i class="fas fa-times"></i></button>
                        </div>
                        <button type="button" class="staff-btn-create-ann" onclick="openCreateModal()" title="Create and Broadcast a New Announcement to Palayan City Residents">
                            <i class="fas fa-plus-circle"></i>
                            <span>Broadcast Announcement</span>
                        </button>
                    </div>
                </div>

                <!-- ========================================================
                     ANNOUNCEMENT CARDS GRID
                     ======================================================== -->
                <div class="staff-cards-grid" id="staffAnnGrid">
                    <?php if (empty($announcements)): ?>
                        <div style="grid-column: 1 / -1; background: var(--ref-card); border-radius: 16px; padding: 50px 20px; text-align: center; border: 1px dashed var(--ref-border);">
                            <i class="fas fa-bullhorn" style="font-size: 3rem; color: var(--ref-muted); opacity: 0.4; margin-bottom: 14px;"></i>
                            <h3 style="margin: 0 0 6px 0; color: var(--ref-text);">No Bulletins Published</h3>
                            <p style="margin: 0; color: var(--ref-muted); font-size: 0.88rem;">There are currently no active announcements in the database.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($announcements as $item): 
                            $img_disk = __DIR__ . '/../' . ltrim($item['image_path'], '/');
                            $has_img = !empty($item['image_path']) && file_exists($img_disk);
                            $web_img = '../' . ltrim($item['image_path'], '/');

                            $cat_key = strtolower($item['category'] ?? 'general');
                            $cat_label = 'General Notice';
                            $cat_icon = 'fa-info-circle';
                            if ($cat_key === 'aics') {
                                $cat_label = 'AICS Assistance';
                                $cat_icon = 'fa-hand-holding-heart';
                            } elseif ($cat_key === 'housing') {
                                $cat_label = 'Housing Program';
                                $cat_icon = 'fa-house-chimney';
                            } elseif ($cat_key === 'appointment') {
                                $cat_label = 'Appointments';
                                $cat_icon = 'fa-calendar-check';
                            }

                            $author_full = (!empty($item['first_name']) && !empty($item['last_name']))
                                ? $item['first_name'] . ' ' . $item['last_name']
                                : 'CSWDO Administrator';

                            $author_init = strtoupper(substr($author_full, 0, 1));
                        ?>
                            <article class="staff-ann-card" 
                                     data-category="<?php echo htmlspecialchars($cat_key); ?>"
                                     data-title="<?php echo htmlspecialchars(strtolower($item['title'])); ?>"
                                     data-content="<?php echo htmlspecialchars(strtolower($item['content'])); ?>"
                                     data-author="<?php echo htmlspecialchars(strtolower($author_full)); ?>">
                                
                                <?php if ($has_img): ?>
                                    <div class="card-thumb-wrap">
                                        <img src="<?php echo htmlspecialchars($web_img); ?>" 
                                             alt="<?php echo htmlspecialchars($item['title']); ?>" 
                                             loading="lazy">
                                        <span class="card-thumb-badge cat-bg-<?php echo $cat_key; ?>">
                                            <?php echo htmlspecialchars($cat_label); ?>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <div class="card-no-img-bar cat-<?php echo $cat_key; ?>">
                                        <span class="pill-badge-cat cat-bg-<?php echo $cat_key; ?>" style="margin: 0;">
                                            <?php echo htmlspecialchars($cat_label); ?>
                                        </span>
                                        <div class="card-cat-icon">
                                            <i class="fas <?php echo $cat_icon; ?>"></i>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="card-inner-body">
                                    <div class="card-meta-stamp">
                                        <span><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($item['created_at'])); ?></span>
                                        <span><i class="far fa-clock"></i> <?php echo date('h:i A', strtotime($item['created_at'])); ?></span>
                                    </div>

                                    <h3 class="card-ann-headline"><?php echo htmlspecialchars($item['title']); ?></h3>
                                    <p class="card-ann-desc"><?php echo htmlspecialchars($item['content']); ?></p>

                                    <div class="card-bottom-bar">
                                        <div class="card-author-pill">
                                            <div class="author-chip-avatar"><?php echo htmlspecialchars($author_init); ?></div>
                                            <span><?php echo htmlspecialchars($author_full); ?></span>
                                        </div>
                                        <div style="display: flex; gap: 6px; align-items: center;">
                                            <button type="button" class="btn-card-edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8'); ?>)">
                                                <i class="fas fa-edit"></i> Edit Notice
                                            </button>
                                            <a href="announcements.php?delete_id=<?php echo $item['announcement_id']; ?>" class="btn-card-delete" onclick="return confirm('Sigurado ka bang nais mong tanggalin ang anunsyong ito?');" title="Remove Announcement">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                            <button type="button" class="btn-card-inspect" onclick="inspectAnnouncement(<?php echo htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8'); ?>)">
                                                Inspect <i class="fas fa-chevron-right"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>

                <!-- ========================================================
                     FEATURED PHOTO SLIDESHOW / CAROUSEL (FOR IMAGE BULLETINS)
                     ======================================================== -->
                <?php if (!empty($slideshow_items)): ?>
                    <section class="staff-carousel-wrapper" id="staffCarousel" aria-label="Featured Media Announcements">
                        <div class="carousel-corner-tag">
                            <i class="fas fa-photo-film"></i> Featured Media Bulletins
                        </div>

                        <div class="carousel-stage">
                            <?php foreach ($slideshow_items as $idx => $slide): 
                                $cat_key = strtolower($slide['category'] ?? 'general');
                                $cat_label = 'General Notice';
                                if ($cat_key === 'aics') $cat_label = 'AICS Program';
                                elseif ($cat_key === 'housing') $cat_label = 'Housing Program';
                                elseif ($cat_key === 'appointment') $cat_label = 'Appointments';

                                $slide_img_web = '../' . ltrim($slide['image_path'], '/');
                                $author_str = (!empty($slide['first_name']) && !empty($slide['last_name']))
                                    ? $slide['first_name'] . ' ' . $slide['last_name']
                                    : 'CSWDO Administrator';
                            ?>
                                <div class="staff-slide <?php echo $idx === 0 ? 'active' : ''; ?>" data-slide-index="<?php echo $idx; ?>">
                                    <img src="<?php echo htmlspecialchars($slide_img_web); ?>" 
                                         alt="<?php echo htmlspecialchars($slide['title']); ?>" 
                                         class="slide-img-cover" 
                                         loading="<?php echo $idx === 0 ? 'eager' : 'lazy'; ?>">
                                    <div class="slide-overlay-mask"></div>
                                    <div class="slide-text-box">
                                        <span class="pill-badge-cat cat-bg-<?php echo $cat_key; ?>">
                                            <?php echo htmlspecialchars($cat_label); ?>
                                        </span>
                                        <h2 class="slide-heading"><?php echo htmlspecialchars($slide['title']); ?></h2>
                                        <p class="slide-snippet"><?php echo htmlspecialchars($slide['content']); ?></p>
                                        <div class="slide-meta-strip">
                                            <span><i class="far fa-calendar-alt"></i> <?php echo date('F d, Y', strtotime($slide['created_at'])); ?></span>
                                            <span><i class="far fa-clock"></i> <?php echo date('h:i A', strtotime($slide['created_at'])); ?></span>
                                            <span><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($author_str); ?></span>
                                        </div>
                                        <div>
                                            <button type="button" class="btn-slide-detail" onclick="inspectAnnouncement(<?php echo htmlspecialchars(json_encode($slide), ENT_QUOTES, 'UTF-8'); ?>)">
                                                Inspect Full Bulletin <i class="fas fa-arrow-right"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (count($slideshow_items) > 1): ?>
                            <button type="button" class="carousel-arrow left" id="staffCarouselPrev" aria-label="Previous Slide">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button type="button" class="carousel-arrow right" id="staffCarouselNext" aria-label="Next Slide">
                                <i class="fas fa-chevron-right"></i>
                            </button>

                            <div class="carousel-nav-dock">
                                <?php foreach ($slideshow_items as $idx => $slide): ?>
                                    <div class="dock-dot <?php echo $idx === 0 ? 'active' : ''; ?>" data-target-idx="<?php echo $idx; ?>"></div>
                                <?php endforeach; ?>
                                <span class="dock-counter" id="staffSlideCounter">1 / <?php echo count($slideshow_items); ?></span>
                                <button type="button" class="dock-pause-btn" id="staffSlidePause" title="Pause / Play Slideshow">
                                    <i class="fas fa-pause"></i>
                                </button>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            </div>
            </div>
        </div>
    </main>
</div>

<!-- ========================================================
     STAFF BULLETIN INSPECT MODAL
     ======================================================== -->
<div class="staff-modal-backdrop" id="staffAnnModal" role="dialog" aria-modal="true">
    <div class="staff-modal-box">
        <div class="modal-bar-top">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span id="staffModalBadge" class="pill-badge-cat cat-bg-general" style="margin: 0;">General</span>
                <span style="font-size: 0.76rem; color: var(--ref-muted); font-weight: 700;"><i class="fas fa-shield-alt"></i> CSWDO Staff Review</span>
            </div>
            <button type="button" class="btn-staff-modal-close" onclick="closeStaffModal()" aria-label="Close modal">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="modal-content-scroller">
            <img id="staffModalImg" src="" alt="Bulletin media" class="modal-hero-photo" style="display: none;">

            <h2 id="staffModalTitle" class="modal-headline-lg">Bulletin Title</h2>

            <div class="modal-facts-strip">
                <span><i class="far fa-calendar-alt"></i> Published: <strong id="staffModalDate">---</strong></span>
                <span><i class="far fa-clock"></i> Time: <strong id="staffModalTime">---</strong></span>
                <span><i class="fas fa-user-edit"></i> Poster: <strong id="staffModalAuthor">Administrator</strong></span>
            </div>

            <div id="staffModalBody" class="modal-body-prose">
                Bulletin text.
            </div>
        </div>

        <div class="modal-bar-bottom">
            <div style="display: flex; gap: 8px; align-items: center;">
                <button type="button" id="staffModalEditBtn" class="btn-action-primary" style="padding: 6px 16px; font-size: 0.78rem; background: #7F0303; color: #FFFFFF; border-radius: 10px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fas fa-edit"></i> Edit Notice
                </button>
                <?php if (isAdmin()): ?>
                    <a href="../admin/announcements.php" class="btn-action-primary" style="padding: 6px 16px; font-size: 0.78rem;">
                        <i class="fas fa-edit"></i> Edit in Admin Center
                    </a>
                <?php endif; ?>
                <a id="staffModalDeleteBtn" href="#" onclick="return confirm('Are you sure you want to delete this bulletin?');" class="btn-action-secondary" style="padding: 6px 16px; font-size: 0.78rem; color: #DC2626; border-color: rgba(220, 38, 38, 0.3); display: none;">
                    <i class="fas fa-trash-alt"></i> Delete Bulletin
                </a>
            </div>
            <button type="button" class="btn-action-secondary" onclick="closeStaffModal()" style="padding: 6px 16px; font-size: 0.78rem;">
                Close Window
            </button>
        </div>
    </div>
</div>

<!-- ========================================================
     CREATE NEW ANNOUNCEMENT MODAL
     ======================================================== -->
<div class="staff-modal-backdrop" id="createAnnModal" role="dialog" aria-modal="true">
    <div class="staff-modal-box" style="max-width: 680px;">
        <div class="modal-bar-top" style="background: linear-gradient(135deg, rgba(127, 3, 3, 0.08), rgba(15, 65, 74, 0.05));">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="pill-badge-cat cat-bg-aics" style="margin: 0;"><i class="fas fa-bullhorn"></i> New Broadcast</span>
                <span style="font-size: 0.85rem; color: var(--ref-text, #191D1E); font-weight: 800;">Create Public Announcement</span>
            </div>
            <button type="button" class="btn-staff-modal-close" onclick="closeCreateModal()" aria-label="Close modal">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form action="announcements.php" method="POST" enctype="multipart/form-data" id="createAnnouncementForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create_announcement">

            <div class="modal-content-scroller" style="max-height: calc(85vh - 140px);">
                <div class="create-modal-grid">
                    <!-- Title -->
                    <div class="form-group-custom">
                        <label for="annTitle"><i class="fas fa-heading"></i> Announcement Title <span style="color: #DC2626;">*</span></label>
                        <input type="text" name="title" id="annTitle" class="form-input-custom" placeholder="e.g. Distribution of Educational Assistance for Semester 2" required>
                    </div>

                    <!-- Category & Date/Time Row -->
                    <div class="form-row-2col">
                        <div class="form-group-custom">
                            <label for="annCategory"><i class="fas fa-layer-group"></i> Category <span style="color: #DC2626;">*</span></label>
                            <select name="category" id="annCategory" class="form-select-custom" required>
                                <option value="general">General Notice</option>
                                <option value="aics">AICS Crisis Assistance</option>
                                <option value="housing">Housing Program</option>
                                <option value="appointment">Appointments & Desk Schedule</option>
                                <option value="senior">Senior Citizen Affairs</option>
                                <option value="pwd">PWD Programs & Benefits</option>
                            </select>
                        </div>
                        <div class="form-group-custom">
                            <label for="annDate"><i class="far fa-calendar-alt"></i> Date & Time</label>
                            <div style="display: flex; gap: 8px;">
                                <input type="date" name="announcement_date" id="annDate" class="form-input-custom" value="<?php echo date('Y-m-d'); ?>" style="flex: 1.2;">
                                <input type="time" name="announcement_time" id="annTime" class="form-input-custom" value="<?php echo date('H:i'); ?>" style="flex: 1;">
                            </div>
                        </div>
                    </div>

                    <!-- File / Image Upload -->
                    <div class="form-group-custom">
                        <label><i class="fas fa-image"></i> Attachment / Banner Image (Optional)</label>
                        <div class="file-drop-zone" id="fileDropZone">
                            <input type="file" name="announcement_image" id="annImageInput" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf">
                            <div id="fileUploadPrompt">
                                <i class="fas fa-cloud-upload-alt" style="font-size: 1.8rem; color: #7F0303; margin-bottom: 6px;"></i>
                                <p style="margin: 0; font-size: 0.82rem; font-weight: 700; color: var(--ref-text);">Click or drag image / file here</p>
                                <span style="font-size: 0.72rem; color: var(--ref-muted);">Supports PNG, JPG, WEBP, PDF (Max: 5MB)</span>
                            </div>
                            <div id="fileUploadPreview" style="display: none; align-items: center; justify-content: center; gap: 10px; margin-top: 6px;">
                                <img id="previewImg" src="" alt="Preview" style="max-height: 80px; border-radius: 8px; object-fit: cover;">
                                <div style="text-align: left;">
                                    <span id="previewFileName" style="font-size: 0.82rem; font-weight: 700; color: var(--ref-text); display: block;"></span>
                                    <span style="font-size: 0.7rem; color: #2E7D32;"><i class="fas fa-check-circle"></i> File selected</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Description / Content -->
                    <div class="form-group-custom">
                        <label for="annContent"><i class="fas fa-align-left"></i> Description & Details <span style="color: #DC2626;">*</span></label>
                        <textarea name="content" id="annContent" rows="6" class="form-textarea-custom" placeholder="Type the full announcement content, instructions, requirements, and advisories here..." required></textarea>
                    </div>

                    <!-- Notify Residents Checkbox -->
                    <label class="notify-toggle-box">
                        <input type="checkbox" name="notify_residents" value="1" checked>
                        <div class="notify-toggle-text">
                            <h4><i class="fas fa-bell" style="color: #7F0303; margin-right: 4px;"></i> Notify All Residents Immediately</h4>
                            <p>Sends real-time bell alerts to all active resident accounts and updates the Announcement tab badge counter.</p>
                        </div>
                    </label>
                </div>
            </div>

            <div class="modal-bar-bottom">
                <button type="button" class="btn-action-secondary" onclick="closeCreateModal()" style="padding: 9px 20px; font-size: 0.82rem;">
                    Cancel
                </button>
                <button type="submit" class="btn-create-announcement" style="padding: 9px 24px;">
                    <i class="fas fa-paper-plane"></i> Broadcast Bulletin
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================
     EDIT ANNOUNCEMENT MODAL
     ======================================================== -->
<div class="staff-modal-backdrop" id="editAnnModal" role="dialog" aria-modal="true">
    <div class="staff-modal-box" style="max-width: 680px;">
        <div class="modal-bar-top" style="background: linear-gradient(135deg, rgba(127, 3, 3, 0.08), rgba(15, 65, 74, 0.05));">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="pill-badge-cat cat-bg-aics" style="margin: 0;"><i class="fas fa-edit"></i> Edit Bulletin</span>
                <span style="font-size: 0.85rem; color: var(--ref-text, #191D1E); font-weight: 800;">Modify Notice & Details</span>
            </div>
            <button type="button" class="btn-staff-modal-close" onclick="closeEditModal()" aria-label="Close modal">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form action="announcements.php" method="POST" enctype="multipart/form-data" id="editAnnouncementForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="edit_announcement">
            <input type="hidden" name="announcement_id" id="editAnnId" value="">

            <div class="modal-content-scroller" style="max-height: calc(85vh - 140px);">
                <div class="create-modal-grid">
                    <!-- Title -->
                    <div class="form-group-custom">
                        <label for="editAnnTitle"><i class="fas fa-heading"></i> Announcement Title <span style="color: #DC2626;">*</span></label>
                        <input type="text" name="title" id="editAnnTitle" class="form-input-custom" required>
                    </div>

                    <!-- Category & Date/Time Row -->
                    <div class="form-row-2col">
                        <div class="form-group-custom">
                            <label for="editAnnCategory"><i class="fas fa-layer-group"></i> Category <span style="color: #DC2626;">*</span></label>
                            <select name="category" id="editAnnCategory" class="form-select-custom" required>
                                <option value="general">General Notice</option>
                                <option value="aics">AICS Crisis Assistance</option>
                                <option value="housing">Housing Program</option>
                                <option value="appointment">Appointments & Desk Schedule</option>
                                <option value="senior">Senior Citizen Affairs</option>
                                <option value="pwd">PWD Programs & Benefits</option>
                            </select>
                        </div>
                        <div class="form-group-custom">
                            <label for="editAnnDate"><i class="far fa-calendar-alt"></i> Date & Time</label>
                            <div style="display: flex; gap: 8px;">
                                <input type="date" name="announcement_date" id="editAnnDate" class="form-input-custom" style="flex: 1.2;">
                                <input type="time" name="announcement_time" id="editAnnTime" class="form-input-custom" style="flex: 1;">
                            </div>
                        </div>
                    </div>

                    <!-- Current Photo / Image Replacement -->
                    <div class="form-group-custom">
                        <label><i class="fas fa-image"></i> Attachment / Banner Image</label>
                        
                        <!-- Existing Photo Preview Box -->
                        <div id="editCurrentPhotoWrap" style="display: none; margin-bottom: 12px; padding: 12px 14px; background: var(--ref-canvas); border: 1px solid var(--ref-border); border-radius: 14px; align-items: center; justify-content: space-between; gap: 14px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <img id="editCurrentPhotoImg" src="" alt="Current attachment" style="max-height: 65px; border-radius: 8px; object-fit: cover;">
                                <div>
                                    <span style="font-size: 0.78rem; font-weight: 700; color: var(--ref-text); display: block;">Current Attached Photo</span>
                                    <span style="font-size: 0.7rem; color: var(--ref-muted);">Active in resident view</span>
                                </div>
                            </div>
                            <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 0.75rem; color: #DC2626; cursor: pointer; margin: 0; font-weight: 700;">
                                <input type="checkbox" name="remove_image" id="editRemoveImage" value="1">
                                <i class="fas fa-trash-alt"></i> Remove photo
                            </label>
                        </div>

                        <!-- New Photo Uploader Dropzone -->
                        <div class="file-drop-zone" id="editFileDropZone">
                            <input type="file" name="announcement_image" id="editAnnImageInput" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf">
                            <div id="editFileUploadPrompt">
                                <i class="fas fa-cloud-upload-alt" style="font-size: 1.6rem; color: #7F0303; margin-bottom: 4px;"></i>
                                <p style="margin: 0; font-size: 0.8rem; font-weight: 700; color: var(--ref-text);">Upload new photo to replace or add</p>
                                <span style="font-size: 0.7rem; color: var(--ref-muted);">PNG, JPG, WEBP, PDF (Max: 5MB)</span>
                            </div>
                            <div id="editFileUploadPreview" style="display: none; align-items: center; justify-content: center; gap: 10px; margin-top: 6px;">
                                <img id="editPreviewImg" src="" alt="New preview" style="max-height: 70px; border-radius: 8px; object-fit: cover;">
                                <div style="text-align: left;">
                                    <span id="editPreviewFileName" style="font-size: 0.8rem; font-weight: 700; color: var(--ref-text); display: block;"></span>
                                    <span style="font-size: 0.7rem; color: #2E7D32;"><i class="fas fa-check-circle"></i> New file ready</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Description / Content -->
                    <div class="form-group-custom">
                        <label for="editAnnContent"><i class="fas fa-align-left"></i> Description & Details <span style="color: #DC2626;">*</span></label>
                        <textarea name="content" id="editAnnContent" rows="6" class="form-textarea-custom" required></textarea>
                    </div>

                    <!-- Notify Residents Checkbox -->
                    <label class="notify-toggle-box">
                        <input type="checkbox" name="notify_residents" value="1">
                        <div class="notify-toggle-text">
                            <h4><i class="fas fa-bell" style="color: #7F0303; margin-right: 4px;"></i> Notify Residents of Update</h4>
                            <p>Sends a revised advisory notification to active resident accounts regarding this update.</p>
                        </div>
                    </label>
                </div>
            </div>

            <div class="modal-bar-bottom">
                <button type="button" class="btn-action-secondary" onclick="closeEditModal()" style="padding: 9px 20px; font-size: 0.82rem;">
                    Cancel
                </button>
                <button type="submit" class="btn-create-announcement" style="padding: 9px 24px; background: #7F0303;">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/staff-footer.php'; ?>

<script>
// ==========================================================================
// STAFF SLIDESHOW ENGINE
// ==========================================================================
const staffCarousel = document.getElementById('staffCarousel');
const staffSlides = document.querySelectorAll('.staff-slide');
const staffDots = document.querySelectorAll('.dock-dot');
const staffPrevBtn = document.getElementById('staffCarouselPrev');
const staffNextBtn = document.getElementById('staffCarouselNext');
const staffCounter = document.getElementById('staffSlideCounter');
const staffPauseBtn = document.getElementById('staffSlidePause');

let staffSlideIdx = 0;
let staffTimer = null;
let staffIsPaused = false;
const staffTotalSlides = staffSlides.length;

function showStaffSlide(idx) {
    if (staffTotalSlides === 0) return;
    staffSlideIdx = (idx + staffTotalSlides) % staffTotalSlides;

    staffSlides.forEach((s, i) => s.classList.toggle('active', i === staffSlideIdx));
    staffDots.forEach((d, i) => d.classList.toggle('active', i === staffSlideIdx));

    if (staffCounter) staffCounter.textContent = `${staffSlideIdx + 1} / ${staffTotalSlides}`;
}

function nextStaffSlide() { showStaffSlide(staffSlideIdx + 1); }
function prevStaffSlide() { showStaffSlide(staffSlideIdx - 1); }

function startStaffAutoplay() {
    if (staffTotalSlides <= 1) return;
    stopStaffAutoplay();
    staffTimer = setInterval(() => {
        if (!staffIsPaused) nextStaffSlide();
    }, 6000);
}

function stopStaffAutoplay() {
    if (staffTimer) { clearInterval(staffTimer); staffTimer = null; }
}

if (staffTotalSlides > 1) {
    if (staffNextBtn) staffNextBtn.addEventListener('click', () => { nextStaffSlide(); startStaffAutoplay(); });
    if (staffPrevBtn) staffPrevBtn.addEventListener('click', () => { prevStaffSlide(); startStaffAutoplay(); });

    staffDots.forEach((dot, idx) => {
        dot.addEventListener('click', () => { showStaffSlide(idx); startStaffAutoplay(); });
    });

    if (staffCarousel) {
        staffCarousel.addEventListener('mouseenter', () => { staffIsPaused = true; });
        staffCarousel.addEventListener('mouseleave', () => { 
            const icon = staffPauseBtn ? staffPauseBtn.querySelector('i') : null;
            if (!icon || icon.classList.contains('fa-pause')) staffIsPaused = false; 
        });
    }

    if (staffPauseBtn) {
        staffPauseBtn.addEventListener('click', () => {
            const icon = staffPauseBtn.querySelector('i');
            if (staffIsPaused) {
                staffIsPaused = false;
                if (icon) icon.className = 'fas fa-pause';
            } else {
                staffIsPaused = true;
                if (icon) icon.className = 'fas fa-play';
            }
        });
    }

    startStaffAutoplay();
}

// ==========================================================================
// FILTER & SEARCH LOGIC
// ==========================================================================
const filterTabs = document.querySelectorAll('#staffCatFilters .staff-filter-tab');
const staffSearch = document.getElementById('staffAnnSearch');
const staffClear = document.getElementById('staffSearchClear');
const annCards = document.querySelectorAll('#staffAnnGrid .staff-ann-card');

let currCat = 'all';
let currSearch = '';

function filterStaffAnnouncements() {
    let visible = 0;
    annCards.forEach(card => {
        const cat = card.getAttribute('data-category') || 'general';
        const title = card.getAttribute('data-title') || '';
        const desc = card.getAttribute('data-content') || '';
        const author = card.getAttribute('data-author') || '';

        const matchCat = (currCat === 'all' || cat === currCat);
        const matchSearch = (!currSearch || title.includes(currSearch) || desc.includes(currSearch) || author.includes(currSearch));

        if (matchCat && matchSearch) {
            card.style.display = 'flex';
            visible++;
        } else {
            card.style.display = 'none';
        }
    });
}

filterTabs.forEach(tab => {
    tab.addEventListener('click', function() {
        filterTabs.forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        currCat = this.getAttribute('data-category');
        filterStaffAnnouncements();
    });
});

if (staffSearch) {
    staffSearch.addEventListener('input', function() {
        currSearch = this.value.trim().toLowerCase();
        if (staffClear) staffClear.style.display = currSearch ? 'block' : 'none';
        filterStaffAnnouncements();
    });
}

if (staffClear) {
    staffClear.addEventListener('click', function() {
        if (staffSearch) {
            staffSearch.value = '';
            currSearch = '';
            this.style.display = 'none';
            staffSearch.focus();
            filterStaffAnnouncements();
        }
    });
}

// ==========================================================================
// BULLETIN INSPECT MODAL
// ==========================================================================
const staffModal = document.getElementById('staffAnnModal');
const staffModalBadge = document.getElementById('staffModalBadge');
const staffModalImg = document.getElementById('staffModalImg');
const staffModalTitle = document.getElementById('staffModalTitle');
const staffModalDate = document.getElementById('staffModalDate');
const staffModalTime = document.getElementById('staffModalTime');
const staffModalAuthor = document.getElementById('staffModalAuthor');
const staffModalBody = document.getElementById('staffModalBody');

function inspectAnnouncement(data) {
    if (!data) return;

    staffModalTitle.textContent = data.title || 'Official Bulletin';
    staffModalBody.textContent = data.content || '';

    const author = (data.first_name && data.last_name) ? `${data.first_name} ${data.last_name}` : 'CSWDO Administrator';
    staffModalAuthor.textContent = author;

    const dt = data.created_at ? new Date(data.created_at.replace(/-/g, '/')) : new Date();
    staffModalDate.textContent = dt.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    staffModalTime.textContent = dt.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

    const category = (data.category || 'general').toLowerCase();
    let catLabel = 'General Notice';
    staffModalBadge.className = 'pill-badge-cat';
    if (category === 'aics') {
        catLabel = 'AICS Assistance';
        staffModalBadge.classList.add('cat-bg-aics');
    } else if (category === 'housing') {
        catLabel = 'Housing Program';
        staffModalBadge.classList.add('cat-bg-housing');
    } else if (category === 'appointment') {
        catLabel = 'Appointments';
        staffModalBadge.classList.add('cat-bg-appointment');
    } else {
        staffModalBadge.classList.add('cat-bg-general');
    }
    staffModalBadge.textContent = catLabel;

    if (data.image_path) {
        staffModalImg.src = '../' + data.image_path.replace(/^\/+/, '');
        staffModalImg.style.display = 'block';
    } else {
        staffModalImg.src = '';
        staffModalImg.style.display = 'none';
    }

    const delBtn = document.getElementById('staffModalDeleteBtn');
    if (delBtn) {
        delBtn.href = `announcements.php?delete_id=${data.announcement_id}`;
        delBtn.style.display = 'inline-flex';
    }

    staffModal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeStaffModal() {
    staffModal.classList.remove('active');
    document.body.style.overflow = '';
}

if (staffModal) {
    staffModal.addEventListener('click', function(e) {
        if (e.target === this) closeStaffModal();
    });
}

// ==========================================================================
// CREATE ANNOUNCEMENT MODAL & FILE PREVIEW
// ==========================================================================
const createModal = document.getElementById('createAnnModal');
const annImageInput = document.getElementById('annImageInput');
const fileUploadPrompt = document.getElementById('fileUploadPrompt');
const fileUploadPreview = document.getElementById('fileUploadPreview');
const previewImg = document.getElementById('previewImg');
const previewFileName = document.getElementById('previewFileName');

function openCreateModal() {
    if (createModal) {
        createModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeCreateModal() {
    if (createModal) {
        createModal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

if (createModal) {
    createModal.addEventListener('click', function(e) {
        if (e.target === this) closeCreateModal();
    });
}

if (annImageInput) {
    annImageInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const file = this.files[0];
            previewFileName.textContent = file.name;
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewImg.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                previewImg.style.display = 'none';
            }
            fileUploadPrompt.style.display = 'none';
            fileUploadPreview.style.display = 'flex';
        } else {
            fileUploadPrompt.style.display = 'block';
            fileUploadPreview.style.display = 'none';
        }
    });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (staffModal && staffModal.classList.contains('active')) closeStaffModal();
        if (createModal && createModal.classList.contains('active')) closeCreateModal();
        if (editModal && editModal.classList.contains('active')) closeEditModal();
    }
});

// ==========================================================================
// EDIT ANNOUNCEMENT MODAL LOGIC
// ==========================================================================
let currentInspectedAnn = null;
const editModal = document.getElementById('editAnnModal');
const editAnnId = document.getElementById('editAnnId');
const editAnnTitle = document.getElementById('editAnnTitle');
const editAnnCategory = document.getElementById('editAnnCategory');
const editAnnDate = document.getElementById('editAnnDate');
const editAnnTime = document.getElementById('editAnnTime');
const editAnnContent = document.getElementById('editAnnContent');
const editCurrentPhotoWrap = document.getElementById('editCurrentPhotoWrap');
const editCurrentPhotoImg = document.getElementById('editCurrentPhotoImg');
const editRemoveImage = document.getElementById('editRemoveImage');
const editAnnImageInput = document.getElementById('editAnnImageInput');
const editFileUploadPrompt = document.getElementById('editFileUploadPrompt');
const editFileUploadPreview = document.getElementById('editFileUploadPreview');
const editPreviewImg = document.getElementById('editPreviewImg');
const editPreviewFileName = document.getElementById('editPreviewFileName');

function openEditModal(data) {
    if (!editModal || !data) return;

    if (typeof staffModal !== 'undefined' && staffModal && staffModal.classList.contains('active')) {
        closeStaffModal();
    }

    editAnnId.value = data.announcement_id || '';
    editAnnTitle.value = data.title || '';
    editAnnCategory.value = (data.category || 'general').toLowerCase();
    editAnnContent.value = data.content || '';

    // Date & Time
    if (data.created_at) {
        const parts = data.created_at.split(' ');
        editAnnDate.value = parts[0] || '';
        if (parts[1]) {
            editAnnTime.value = parts[1].substring(0, 5);
        }
    }

    // Current Photo Handling
    if (editRemoveImage) editRemoveImage.checked = false;
    if (data.image_path) {
        editCurrentPhotoImg.src = '../' + data.image_path.replace(/^\/+/, '');
        editCurrentPhotoWrap.style.display = 'flex';
    } else {
        editCurrentPhotoImg.src = '';
        editCurrentPhotoWrap.style.display = 'none';
    }

    // Reset new upload dropzone
    if (editAnnImageInput) editAnnImageInput.value = '';
    if (editFileUploadPreview) editFileUploadPreview.style.display = 'none';
    if (editFileUploadPrompt) editFileUploadPrompt.style.display = 'block';

    editModal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeEditModal() {
    if (editModal) {
        editModal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

if (editModal) {
    editModal.addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });
}

if (editAnnImageInput) {
    editAnnImageInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const file = this.files[0];
            if (editPreviewFileName) editPreviewFileName.textContent = file.name;
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (editPreviewImg) editPreviewImg.src = e.target.result;
                    if (editFileUploadPreview) editFileUploadPreview.style.display = 'flex';
                    if (editFileUploadPrompt) editFileUploadPrompt.style.display = 'none';
                };
                reader.readAsDataURL(file);
            } else {
                if (editPreviewImg) editPreviewImg.src = '../images/pwa/icon-192.png';
                if (editFileUploadPreview) editFileUploadPreview.style.display = 'flex';
                if (editFileUploadPrompt) editFileUploadPrompt.style.display = 'none';
            }
        }
    });
}

const modalEditBtn = document.getElementById('staffModalEditBtn');
if (modalEditBtn) {
    modalEditBtn.addEventListener('click', function() {
        if (currentInspectedAnn) {
            openEditModal(currentInspectedAnn);
        }
    });
}

</script>
</body>
</html>
