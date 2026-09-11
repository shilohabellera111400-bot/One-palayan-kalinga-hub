<?php
// announcements.php
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();

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

// Fetch announcements
$query = "SELECT a.*, u.first_name, u.last_name 
          FROM announcements a 
          LEFT JOIN users u ON a.created_by = u.user_id 
          ORDER BY a.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Announcements - One Palayan-Kalinga Hub</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/portal-layout.css">
    <style>
        /* ========== ANNOUNCEMENTS ========== */
        .announcements-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .announcements-header .count {
            font-size: 0.8rem;
            color: var(--text-muted);
            background: var(--badge-bg);
            padding: 4px 16px;
            border-radius: 50px;
            border: 1px solid var(--border-color);
        }

        .announcement-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 28px;
        }

        .announcement-card {
            display: flex;
            flex-direction: column;
            height: 100%;
            background: var(--bg-card);
            border-radius: var(--radius-card);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            animation: fadeInUp 0.6s ease both;
        }

        .announcement-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .announcement-card:nth-child(2) {
            animation-delay: 0.15s;
        }

        .announcement-card:nth-child(3) {
            animation-delay: 0.2s;
        }

        .announcement-card:nth-child(4) {
            animation-delay: 0.25s;
        }

        .announcement-card:nth-child(5) {
            animation-delay: 0.3s;
        }

        .announcement-card:nth-child(6) {
            animation-delay: 0.35s;
        }

        .announcement-card:hover {
            transform: translateY(-6px) scale(1.01);
            box-shadow: var(--shadow-hover);
            border-color: var(--gold);
        }

        .announcement-img {
            height: 200px;
            background-size: cover;
            background-position: center;
            position: relative;
            flex-shrink: 0;
        }

        .announcement-img .category-badge {
            position: absolute;
            top: 16px;
            left: 16px;
            padding: 4px 14px;
            border-radius: 50px;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .category-badge.general {
            background: rgba(33, 150, 243, 0.15);
            color: #2196F3;
        }

        .category-badge.aics {
            background: rgba(127, 3, 3, 0.15);
            color: var(--maroon-light);
        }

        .category-badge.housing {
            background: rgba(0, 150, 136, 0.15);
            color: #009688;
        }

        .category-badge.appointment {
            background: rgba(255, 152, 0, 0.15);
            color: #FF9800;
        }

        .announcement-body {
            padding: 24px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .announcement-meta {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-bottom: 12px;
            display: flex;
            gap: 16px;
        }

        .announcement-meta i {
            margin-right: 4px;
        }

        .announcement-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .announcement-text {
            font-size: 0.85rem;
            color: var(--text-secondary);
            line-height: 1.7;
            margin-bottom: 20px;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .announcement-footer {
            border-top: 1px solid var(--border-color);
            padding-top: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .author-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 600;
        }

        .author-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--maroon-light), var(--maroon-dark));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .btn-create {
            background: var(--maroon);
            color: white;
            padding: 10px 22px;
            border: none;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            box-shadow: var(--shadow-sm);
        }

        .btn-create:hover {
            background: var(--maroon-light);
            transform: translateY(-2px) scale(1.01);
            box-shadow: var(--shadow-md);
        }

        /* ========== EMPTY STATE ========== */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-sm);
        }

        .empty-state i {
            font-size: 3.5rem;
            color: var(--text-muted);
            opacity: 0.3;
            display: block;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            color: var(--text-primary);
            font-size: 1.2rem;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .announcement-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .announcement-grid {
                grid-template-columns: 1fr;
            }

            .announcements-header {
                flex-direction: column;
                align-items: stretch;
            }

            .btn-create {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .announcement-img {
                height: 160px;
            }

            .announcement-body {
                padding: 18px;
            }

            .announcement-title {
                font-size: 1.05rem;
            }

            .empty-state {
                padding: 40px 16px;
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
                <div class="logo-icon"><i class="fas fa-bullhorn"></i></div>
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
                    <span>
                        <?php echo isAdmin() ? 'Administrator' : (isStaff() ? 'Staff' : 'Resident'); ?>
                    </span>
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
                        <h1><i class="fas fa-bullhorn"></i> Announcements</h1>
                        <p>Stay updated with the latest news and updates from CSWDO</p>
                    </div>
                </div>
                <div class="top-bar-actions">
                    <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                        <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                    </button>
                    <a href="dashboard.php" class="top-icon"><i class="fas fa-tachometer-alt"></i></a>
                </div>
            </div>

            <!-- Announcements Header -->
            <div class="announcements-header">
                <span class="count">
                    <i class="fas fa-newspaper"></i>
                    <?php echo count($announcements); ?> Announcement(s)
                </span>
                <?php if (isStaff() || isAdmin()): ?>
                    <a href="<?php echo isAdmin() ? 'admin/announcements.php' : 'staff/announcements.php'; ?>"
                        class="btn-create">
                        <i class="fas fa-plus"></i> Create Announcement
                    </a>
                <?php endif; ?>
            </div>

            <!-- Announcements Grid -->
            <?php if (empty($announcements)): ?>
                <div class="empty-state">
                    <i class="fas fa-newspaper"></i>
                    <h3>No Announcements</h3>
                    <p>There are no announcements at the moment. Check back later for updates from CSWDO.</p>
                </div>
            <?php else: ?>
                <div class="announcement-grid">
                    <?php foreach ($announcements as $item):
                        $category_class = 'general';
                        if ($item['category'] == 'aics')
                            $category_class = 'aics';
                        elseif ($item['category'] == 'housing')
                            $category_class = 'housing';
                        elseif ($item['category'] == 'appointment')
                            $category_class = 'appointment';

                        $img = !empty($item['image_path']) && file_exists($item['image_path'])
                            ? $item['image_path']
                            : 'images/palayan-bg.png';

                        $author_name = ($item['first_name'] && $item['last_name'])
                            ? $item['first_name'] . ' ' . $item['last_name']
                            : 'CSWDO Staff';

                        $author_initials = '';
                        $name_parts = explode(' ', $author_name);
                        foreach ($name_parts as $part) {
                            $author_initials .= strtoupper(substr($part, 0, 1));
                        }
                        $author_initials = substr($author_initials, 0, 2);

                        $category_display = ucfirst($item['category']);
                        ?>
                        <div class="announcement-card">
                            <div class="announcement-img"
                                style="background-image: url('<?php echo htmlspecialchars($img); ?>');">
                                <span class="category-badge <?php echo $category_class; ?>">
                                    <?php echo $category_display; ?>
                                </span>
                            </div>
                            <div class="announcement-body">
                                <div class="announcement-meta">
                                    <span><i class="far fa-calendar-alt"></i>
                                        <?php echo date('M d, Y', strtotime($item['created_at'])); ?>
                                    </span>
                                    <span><i class="far fa-clock"></i>
                                        <?php echo date('h:i A', strtotime($item['created_at'])); ?>
                                    </span>
                                </div>
                                <h3 class="announcement-title">
                                    <?php echo htmlspecialchars($item['title']); ?>
                                </h3>
                                <p class="announcement-text">
                                    <?php echo nl2br(htmlspecialchars($item['content'])); ?>
                                </p>
                                <div class="announcement-footer">
                                    <div class="author-info">
                                        <div class="author-avatar">
                                            <?php echo $author_initials; ?>
                                        </div>
                                        <span>
                                            <?php echo htmlspecialchars($author_name); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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

        // ========== KEYBOARD SHORTCUTS ==========
        document.addEventListener('keydown', function (e) {
            // Escape to close sidebar
            if (e.key === 'Escape') {
                closeSidebar();
            }
        });

        // ========== CONSOLE WELCOME ==========
        console.log('📢 One Palayan-Kalinga Hub - Announcements');
        console.log('💡 Keyboard shortcuts: ESC (Close Menu)');
        console.log('📱 Mobile navigation: Click the hamburger menu (☰) to open sidebar');
        console.log('🌓 Toggle dark/light mode using the sun/moon button!');
    </script>
</body>

</html>