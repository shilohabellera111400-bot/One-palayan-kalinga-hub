<?php
// includes/usersidenav.php - Shared Sidebar Menu for Resident Portal
$current_page = basename($_SERVER['PHP_SELF']);

if (!function_exists('isActivePage')) {
    function isActivePage($page, $current_page) {
        if ($current_page === $page) {
            return 'class="active"';
        }
        // Sub-page mappings
        if ($page === 'my-appointments.php' && $current_page === 'view-appointment.php') {
            return 'class="active"';
        }
        if ($page === 'my-housing.php' && $current_page === 'view-housing.php') {
            return 'class="active"';
        }
        if ($page === 'aics-requests.php' && $current_page === 'view-aics.php') {
            return 'class="active"';
        }
        return '';
    }
}

// Fetch special category flags from residents table
$is_senior = false;
$is_pwd = false;
if (isLoggedIn() && !isStaff() && !isAdmin()) {
    try {
        $sidenav_db = (new Database())->getConnection();
        $sidenav_stmt = $sidenav_db->prepare("SELECT senior_citizen, pwd FROM residents WHERE user_id = :id");
        $sidenav_stmt->bindParam(':id', $_SESSION['user_id']);
        $sidenav_stmt->execute();
        $sidenav_res = $sidenav_stmt->fetch(PDO::FETCH_ASSOC);
        if ($sidenav_res) {
            $is_senior = ($sidenav_res['senior_citizen'] ?? 0) == 1;
            $is_pwd = ($sidenav_res['pwd'] ?? 0) == 1;
        }
    } catch (Exception $e) {
        // Fail silently
    }
}
?>
<ul class="sidebar-menu">
    <li><a href="dashboard.php" <?php echo isActivePage('dashboard.php', $current_page); ?>><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
    <li><a href="profile.php" <?php echo isActivePage('profile.php', $current_page); ?>><i class="fas fa-user"></i> My Profile</a></li>
    <li><a href="update-profile-info.php" <?php echo isActivePage('update-profile-info.php', $current_page); ?>><i class="fas fa-edit"></i> Complete Profile</a></li>
    <li><a href="verify-resident.php" <?php echo isActivePage('verify-resident.php', $current_page); ?>><i class="fas fa-shield-alt"></i> Verify Residency</a></li>
    
    <li class="menu-divider">Services</li>
    <li><a href="appointments.php" <?php echo isActivePage('appointments.php', $current_page); ?>><i class="fas fa-calendar-plus"></i> Book Appointment</a></li>
    <li><a href="my-appointments.php" <?php echo isActivePage('my-appointments.php', $current_page); ?>><i class="fas fa-calendar-check"></i> My Appointments</a></li>
    <li><a href="aics.php" <?php echo isActivePage('aics.php', $current_page); ?>><i class="fas fa-hand-holding-heart"></i> Apply for AICS</a></li>
    <li><a href="aics-requests.php" <?php echo isActivePage('aics-requests.php', $current_page); ?>><i class="fas fa-list-alt"></i> AICS Requests</a></li>
    
    <li><a href="senior.php" <?php echo (isActivePage('senior.php', $current_page) || isActivePage('senior-services.php', $current_page)); ?>><i class="fas fa-person-cane"></i> Senior Portal</a></li>
    <li><a href="pwd.php" <?php echo (isActivePage('pwd.php', $current_page) || isActivePage('pwd-services.php', $current_page)); ?>><i class="fas fa-wheelchair"></i> PWD Portal</a></li>
    
    <li><a href="calendar.php" <?php echo isActivePage('calendar.php', $current_page); ?>><i class="fas fa-calendar-alt"></i> Event Calendar</a></li>
    <li><a href="print-forms.php" <?php echo isActivePage('print-forms.php', $current_page); ?>><i class="fas fa-print"></i> Printable Forms</a></li>
    <li><a href="housing-applications.php" <?php echo isActivePage('housing-applications.php', $current_page); ?>><i class="fas fa-home"></i> Housing Applications</a></li>
    <li><a href="my-housing.php" <?php echo isActivePage('my-housing.php', $current_page); ?>><i class="fas fa-building"></i> My Housing</a></li>
    <li><a href="documents.php" <?php echo isActivePage('documents.php', $current_page); ?>><i class="fas fa-folder-open"></i> My Documents</a></li>
    <li><a href="feedback.php" <?php echo isActivePage('feedback.php', $current_page); ?>><i class="fas fa-comments"></i> Feedback & Rate Us</a></li>
    <li><a href="announcements.php" <?php echo isActivePage('announcements.php', $current_page); ?>><i class="fas fa-bullhorn"></i> Announcements</a></li>
    
    <li class="menu-divider"></li>
    <li><a href="notifications.php" <?php echo isActivePage('notifications.php', $current_page); ?>><i class="fas fa-bell"></i> Notifications</a></li>
    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
</ul>

<!-- Realtime SSE Notifications Listener -->
<div id="liveToastContainer" style="position: fixed; bottom: 20px; right: 20px; z-index: 99999; display: flex; flex-direction: column; gap: 10px;"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let lastNotifId = 0;
    
    function startSSE() {
        const evtSource = new EventSource(`get-live-notifications-sse.php?last_id=${lastNotifId}`);
        
        evtSource.addEventListener('notification', function(e) {
            const notif = JSON.parse(e.data);
            if (notif.notification_id > lastNotifId) {
                lastNotifId = notif.notification_id;
                showLiveToast(notif.title, notif.message);
            }
        });

        evtSource.onerror = function() {
            evtSource.close();
            setTimeout(startSSE, 10000); // Reconnect after 10s
        };
    }

    function showLiveToast(title, message) {
        const container = document.getElementById('liveToastContainer');
        if (!container) return;

        const toast = document.createElement('div');
        toast.style.cssText = 'background: #0F414A; color: white; padding: 14px 20px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; box-shadow: 0 10px 30px rgba(0,0,0,0.25); display: flex; align-items: center; gap: 12px; min-width: 280px; max-width: 380px; border-left: 4px solid #7F0303; animation: toastSlideIn 0.3s ease;';
        toast.innerHTML = `<i class="fas fa-bell" style="color: #D8BA98; font-size: 1.2rem;"></i> <div><div style="font-weight: 800; font-size: 0.9rem;">${title}</div><div style="font-weight: 400; opacity: 0.9; margin-top: 2px;">${message}</div></div>`;
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(() => toast.remove(), 300);
        }, 6000);
    }

    if (window.EventSource) {
        startSSE();
    }
});
</script>
