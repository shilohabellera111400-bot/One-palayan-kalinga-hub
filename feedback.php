<?php
// feedback.php
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Staff and Admin should not access this page
if (isStaff() || isAdmin()) {
    redirect('staff-dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

// Check if user is verified
$check_verified = "SELECT verification_status FROM users WHERE user_id = :user_id";
$verify_stmt = $db->prepare($check_verified);
$verify_stmt->bindParam(':user_id', $_SESSION['user_id']);
$verify_stmt->execute();
$user_verify = $verify_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_verify || $user_verify['verification_status'] != 'verified') {
    redirect('verify-resident.php');
}

$error = '';
$success = '';

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    csrf_verify();

    $service_type = sanitize($_POST['service_type'] ?? '');
    $rating = intval($_POST['rating'] ?? 0);
    $comment = sanitize($_POST['comment'] ?? '');

    if (empty($service_type) || $rating < 1 || $rating > 5) {
        $error = "Mangyaring pumili ng serbisyo at maglagay ng sapat na rating (1-5 bituin).";
    } else {
        $query = "INSERT INTO feedback (user_id, service_type, rating, comment) 
                  VALUES (:user_id, :service_type, :rating, :comment)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':service_type', $service_type);
        $stmt->bindParam(':rating', $rating);
        $stmt->bindParam(':comment', $comment);

        if ($stmt->execute()) {
            $success = "Maraming salamat! Ang inyong feedback ay matagumpay na naipadala at makakatulong sa pagpapabuti ng ating serbisyo.";
        } else {
            $error = "May naganap na error sa pagpapadala ng inyong feedback.";
        }
    }
}

// Fetch user's own feedbacks
$query = "SELECT * FROM feedback WHERE user_id = :user_id ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$my_feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user data for sidebar
$user_query = "SELECT * FROM users WHERE user_id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $_SESSION['user_id']);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach ($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Profile picture path
$profile_picture = $user['profile_picture'] ?? '';
$avatar_url = !empty($profile_picture) && file_exists($profile_picture)
    ? $profile_picture
    : '';

// Check if dark mode is enabled
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';

// Get greeting based on time
$hour = date('H');
$greeting = $hour < 12 ? 'Morning' : ($hour < 18 ? 'Afternoon' : 'Evening');
$greeting_emoji = $hour < 12 ? '🌅' : ($hour < 18 ? '☀️' : '🌙');
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - One Palayan-Kalinga Hub</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/portal-layout.css">
    <style>
        /* ========== FEEDBACK LAYOUT ========== */
        .feedback-layout {
            display: grid;
            grid-template-columns: 1.2fr 1.8fr;
            gap: 28px;
        }

        /* ========== CARDS ========== */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            padding: 28px 32px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            animation: fadeInUp 0.7s ease both;
        }

        .card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-2px);
            border-color: var(--maroon-light);
        }

        .card .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border-color);
            position: relative;
        }

        .card .card-header::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 60px;
            height: 2px;
            background: var(--gold);
        }

        .card .card-header h2 {
            color: var(--text-primary);
            font-size: 1.15rem;
            font-weight: 700;
        }

        .card .card-header i {
            color: var(--gold);
            font-size: 1.2rem;
        }

        .card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .card:nth-child(2) {
            animation-delay: 0.2s;
        }

        /* ========== FORM ========== */
        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.82rem;
        }

        .form-group label i {
            color: var(--gold);
            margin-right: 6px;
            width: 18px;
        }

        .form-group label .required {
            color: var(--color-danger);
            margin-left: 2px;
        }

        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            transition: all 0.3s;
            background: var(--input-bg);
            color: var(--text-primary);
            font-family: inherit;
        }

        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(201, 168, 108, 0.15);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* ========== STAR RATING ========== */
        .star-rating-wrap {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 18px;
        }

        .star-rating-wrap .stars {
            display: flex;
            gap: 8px;
        }

        .star-item {
            font-size: 2.2rem;
            color: var(--border-color);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .star-item:hover {
            transform: scale(1.15);
        }

        .star-item.active {
            color: var(--gold);
        }

        .star-item.active:hover {
            transform: scale(1.15);
        }

        /* ========== BUTTONS ========== */
        .btn-primary {
            background: var(--maroon);
            color: white;
            padding: 12px 28px;
            border: none;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            justify-content: center;
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            background: var(--maroon-light);
            transform: translateY(-2px) scale(1.01);
            box-shadow: var(--shadow-md);
        }

        .btn-primary:active {
            transform: scale(0.97);
        }

        /* ========== FEEDBACK LIST ========== */
        .feedback-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 16px;
        }

        .feedback-item {
            background: var(--badge-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-element);
            padding: 16px 18px;
            transition: all 0.3s ease;
        }

        .feedback-item:hover {
            border-color: var(--gold);
            transform: translateX(4px);
        }

        .feedback-item .feedback-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .feedback-item .feedback-top .badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 50px;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            background: rgba(127, 3, 3, 0.12);
            color: var(--maroon-light);
        }

        .feedback-item .feedback-top .date {
            font-size: 0.65rem;
            color: var(--text-muted);
        }

        .feedback-item .rating-display {
            color: var(--gold);
            font-size: 0.9rem;
            margin-bottom: 4px;
        }

        .feedback-item .comment {
            font-size: 0.82rem;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-top: 4px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 2.5rem;
            opacity: 0.3;
            display: block;
            margin-bottom: 12px;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .feedback-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .star-item {
                font-size: 1.8rem;
            }
        }

        @media (max-width: 480px) {
            .star-item {
                font-size: 1.5rem;
            }

            .feedback-item {
                padding: 12px 14px;
            }
        }
    </style>
</head>

<body>
    <div class="app-container">
        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon"><i class="fas fa-comment-medical"></i></div>
                <div class="logo-text">
                    <h2>One Palayan-Kalinga Hub</h2>
                    <span>Integrated Service Portal</span>
                </div>
                <button class="sidebar-close" id="sidebarClose" aria-label="Close sidebar">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="user-info-sidebar">
                <div class="user-avatar-sidebar">
                    <?php if (!empty($avatar_url)): ?>
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Profile">
                    <?php else: ?>
                        <?php echo $initials; ?>
                    <?php endif; ?>
                </div>
                <div>
                    <h4>
                        <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                    </h4>
                    <span>Resident</span>
                </div>
            </div>

            <?php include __DIR__ . '/includes/usersidenav.php'; ?>

            <div class="sidebar-footer">
                <a href="index.php"><i class="fas fa-home"></i> Back to Home</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="top-bar-left">
                    <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle navigation">
                        <i class="fas fa-bars"></i>
                        <span>Menu</span>
                    </button>
                    <div class="page-title">
                        <h1><i class="fas fa-comment-medical"></i> Feedback</h1>
                        <p>Share your experience using our hub and services</p>
                    </div>
                </div>
                <div class="top-bar-actions">
                    <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                        <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                    </button>
                    <a href="dashboard.php" class="top-icon"><i class="fas fa-tachometer-alt"></i></a>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button class="close-alert" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <button class="close-alert" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
                </div>
            <?php endif; ?>

            <!-- Feedback Layout -->
            <div class="feedback-layout">
                <!-- Left Panel: Feedback Form -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-edit"></i>
                        <h2>Submit Feedback</h2>
                    </div>
                    <form action="feedback.php" method="POST" id="feedbackForm">
                        <?php echo csrf_field(); ?>

                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Service Type <span class="required">*</span></label>
                            <select name="service_type" required>
                                <option value="">-- Select Service --</option>
                                <option value="AICS Program">AICS Program (Cash, Burial, Medical, etc.)</option>
                                <option value="Housing Program">Housing Program</option>
                                <option value="Appointments">Appointment Booking & Scheduling</option>
                                <option value="Online Hub Portal">Overall Website Experience</option>
                            </select>
                        </div>

                        <div class="star-rating-wrap">
                            <label><i class="fas fa-star"></i> Rating <span class="required">*</span></label>
                            <div class="stars">
                                <i class="fas fa-star star-item" data-value="1"></i>
                                <i class="fas fa-star star-item" data-value="2"></i>
                                <i class="fas fa-star star-item" data-value="3"></i>
                                <i class="fas fa-star star-item" data-value="4"></i>
                                <i class="fas fa-star star-item" data-value="5"></i>
                            </div>
                            <input type="hidden" name="rating" id="ratingInput" value="0" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-comment"></i> Comment or Suggestion</label>
                            <textarea name="comment"
                                placeholder="Write your experience or suggestion to help us improve our service..."></textarea>
                        </div>

                        <button type="submit" name="submit_feedback" class="btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Feedback
                        </button>
                    </form>
                </div>

                <!-- Right Panel: My Feedbacks -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-history"></i>
                        <h2>My Submitted Feedback</h2>
                    </div>

                    <?php if (empty($my_feedbacks)): ?>
                        <div class="empty-state">
                            <i class="fas fa-comments"></i>
                            <p>You haven't submitted any feedback yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="feedback-list">
                            <?php foreach ($my_feedbacks as $fb): ?>
                                <div class="feedback-item">
                                    <div class="feedback-top">
                                        <span class="badge">
                                            <?php echo htmlspecialchars($fb['service_type']); ?>
                                        </span>
                                        <span class="date">
                                            <?php echo date('M d, Y', strtotime($fb['created_at'])); ?>
                                        </span>
                                    </div>
                                    <div class="rating-display">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="<?php echo $i <= $fb['rating'] ? 'fas' : 'far'; ?> fa-star"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <?php if (!empty($fb['comment'])): ?>
                                        <div class="comment">
                                            <?php echo nl2br(htmlspecialchars($fb['comment'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ========== MOBILE SIDEBAR TOGGLE ==========
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebarClose = document.getElementById('sidebarClose');

        function openSidebar() {
            sidebar.classList.add('open');
            sidebarOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        hamburgerBtn.addEventListener('click', openSidebar);
        sidebarClose.addEventListener('click', closeSidebar);
        sidebarOverlay.addEventListener('click', closeSidebar);

        // Close sidebar on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeSidebar();
            }
        });

        // ========== THEME TOGGLE ==========
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;

        const currentTheme = localStorage.getItem('theme') ||
            (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

        function setTheme(theme) {
            html.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            const icon = themeToggle.querySelector('i');
            icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            document.cookie = `dark_mode=${theme === 'dark' ? 'enabled' : 'disabled'}; path=/; max-age=31536000`;

            document.body.style.transition = 'background 0.4s ease, color 0.4s ease';
        }

        setTheme(currentTheme);

        themeToggle.addEventListener('click', function () {
            const current = html.getAttribute('data-theme');
            const newTheme = current === 'dark' ? 'light' : 'dark';
            setTheme(newTheme);

            themeToggle.style.transform = 'scale(0.9)';
            setTimeout(() => {
                themeToggle.style.transform = '';
            }, 200);
        });

        // ========== STAR RATING ==========
        document.addEventListener('DOMContentLoaded', function () {
            const stars = document.querySelectorAll('.star-item');
            const ratingInput = document.getElementById('ratingInput');

            stars.forEach(star => {
                star.addEventListener('click', function () {
                    const ratingValue = parseInt(this.getAttribute('data-value'));
                    ratingInput.value = ratingValue;

                    stars.forEach((s, idx) => {
                        if (idx < ratingValue) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                });

                // Hover effect
                star.addEventListener('mouseenter', function () {
                    const ratingValue = parseInt(this.getAttribute('data-value'));
                    stars.forEach((s, idx) => {
                        if (idx < ratingValue) {
                            s.style.color = 'var(--gold-light)';
                        }
                    });
                });

                star.addEventListener('mouseleave', function () {
                    stars.forEach(s => {
                        s.style.color = '';
                    });
                });
            });
        });

        // ========== AUTO DISMISS ALERTS ==========
        document.addEventListener('DOMContentLoaded', function () {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach((alert) => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateX(30px)';
                    alert.style.transition = 'all 0.5s ease';
                    setTimeout(() => {
                        if (alert.parentElement) {
                            alert.remove();
                        }
                    }, 500);
                }, 5000);
            });
        });

        // ========== KEYBOARD SHORTCUTS ==========
        document.addEventListener('keydown', function (e) {
            // Escape to close sidebar
            if (e.key === 'Escape') {
                closeSidebar();
            }
        });

        // ========== CONSOLE WELCOME ==========
        console.log('⭐ One Palayan-Kalinga Hub - Feedback');
        console.log('💡 Keyboard shortcuts: ESC (Close Menu)');
        console.log('📱 Mobile navigation: Click the hamburger menu (☰) to open sidebar');
        console.log('🌓 Toggle dark/light mode using the sun/moon button!');
    </script>
</body>

</html>