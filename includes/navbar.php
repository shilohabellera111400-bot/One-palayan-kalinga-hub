<?php
// This file contains the navigation bar that's used across the site
?>
<nav class="navbar" id="mainNavbar">
    <div class="container">
        <div class="nav-brand">
            <a href="index.php" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 15px;">
                <div class="logo">
                    <img src="images/logo.png" alt="One Palayan-Kalinga Hub Logo" style="height: 75px; width: auto;"> 
                </div>
                <div>
                    <h1>ONE PALAYAN-KALINGA HUB</h1>
                    <p class="subtitle">CSWDO Palayan City</p>
                </div>
            </a>
        </div>
        <ul class="nav-menu">
            <!-- Public links - visible to everyone -->
            <li><a href="index.php" <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'class="active"' : ''; ?>>Home</a></li>
            <li><a href="services.php" <?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'class="active"' : ''; ?>>Services</a></li>
            
            <!-- Links for LOGGED IN USERS (Residents) only - NOT for staff -->
            <?php if(isLoggedIn() && !isStaff() && !isAdmin()): ?>
                <li><a href="appointments.php" <?php echo basename($_SERVER['PHP_SELF']) == 'appointments.php' ? 'class="active"' : ''; ?>>Book Appointments</a></li>
                <li><a href="track-aics.php" <?php echo basename($_SERVER['PHP_SELF']) == 'track-aics.php' ? 'class="active"' : ''; ?>>Track AICS</a></li>
                <li><a href="housing.php" <?php echo basename($_SERVER['PHP_SELF']) == 'housing.php' ? 'class="active"' : ''; ?>>Housing Program</a></li>
            <?php endif; ?>
            
            <!-- Links for GUESTS (not logged in) -->
            <?php if(!isLoggedIn()): ?>
                <li><a href="appointments.php">Book Appointments</a></li>
                <li><a href="track-aics.php">Track AICS</a></li>
                <li><a href="housing.php">Housing Program</a></li>
            <?php endif; ?>
            
            <!-- Authentication Links -->
            <?php if(!isLoggedIn()): ?>
                <li><a href="login.php" class="btn-login">Log In</a></li>
                <li><a href="register.php" class="btn-register">Register</a></li>
            <?php else: ?>
                <li class="dropdown">
                    <a href="#">
                        <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?> 
                        <i class="fas fa-chevron-down"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <!-- For RESIDENTS only -->
                        <?php if(!isStaff() && !isAdmin()): ?>
                            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                            <li><a href="my-appointments.php"><i class="fas fa-calendar-alt"></i> My Appointments</a></li>
                            <li><a href="documents.php"><i class="fas fa-file-alt"></i> My Documents</a></li>
                            <li><a href="my-housing.php"><i class="fas fa-home"></i> My Housing</a></li>
                            <li><a href="my-aics.php"><i class="fas fa-hand-holding-heart"></i> My AICS</a></li>
                            <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        
                        <!-- For STAFF and ADMIN only -->
                        <?php if(isStaff() || isAdmin()): ?>
                            <li><a href="staff-dashboard.php"><i class="fas fa-tachometer-alt"></i> Staff Dashboard</a></li>
                            <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        
                        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </li>
            <?php endif; ?>
        </ul>
        <div class="hamburger">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
</nav>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
/* Navbar Styles */
.navbar {
    background: #f4f8f4;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
    position: sticky;
    top: 0;
    z-index: 1000;
    transition: all 0.3s ease;
}

.navbar.scrolled {
    background: #1a472a;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.navbar.scrolled .nav-brand h1 {
    color: white;
}

.navbar.scrolled .nav-brand .subtitle {
    color: rgba(255,255,255,0.8);
}

.navbar.scrolled .nav-menu > li > a {
    color: white;
}

.navbar.scrolled .nav-menu > li > a:hover,
.navbar.scrolled .nav-menu > li > a.active {
    color: #ff6600;
}

.navbar.scrolled .btn-login {
    border-color: white;
    color: white !important;
}

.navbar.scrolled .btn-login:hover {
    background: white;
    color: #1a472a !important;
}

.navbar.scrolled .btn-register {
    background: #ff6600;
    color: white !important;
}

.navbar.scrolled .btn-register:hover {
    background: #ff5500;
}

.navbar.scrolled .hamburger span {
    background: white;
}

.navbar .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 80px;
    transition: min-height 0.3s;
}

.navbar.scrolled .container {
    min-height: 70px;
}

.nav-brand a {
    display: flex;
    align-items: center;
    gap: 15px;
    text-decoration: none;
}

.logo img {
    height: 60px;
    width: auto;
    transition: all 0.3s;
}

.navbar.scrolled .logo img {
    height: 50px;
}

.brand-text h1 {
    font-size: 1.2rem;
    color: #003366;
    margin: 0;
    line-height: 1.3;
    transition: color 0.3s;
}

.brand-text .subtitle {
    font-size: 0.7rem;
    color: #666;
    margin: 0;
    transition: color 0.3s;
}

.nav-menu {
    display: flex;
    align-items: center;
    gap: 25px;
    list-style: none;
    margin: 0;
    padding: 0;
}

.nav-menu > li > a {
    text-decoration: none;
    color: #333;
    font-weight: 500;
    font-size: 0.95rem;
    transition: all 0.3s;
    padding: 8px 0;
    display: inline-block;
}

.nav-menu > li > a:hover,
.nav-menu > li > a.active {
    color: #20167b;
}

.btn-login {
    padding: 8px 20px;
    border: 2px solid #003366;
    border-radius: 5px;
    color: #003366 !important;
    background: transparent;
    transition: all 0.3s;
}

.btn-login:hover {
    background: #003366;
    color: white !important;
}

.btn-register {
    padding: 8px 20px;
    background: #003366;
    border-radius: 5px;
    color: white !important;
    transition: all 0.3s;
}

.btn-register:hover {
    background: #002244;
    transform: translateY(-2px);
}

.dropdown {
    position: relative;
}

.dropdown > a {
    display: flex;
    align-items: center;
    gap: 5px;
}

.dropdown > a i {
    font-size: 0.8rem;
    transition: transform 0.3s;
}

.dropdown:hover > a i {
    transform: rotate(180deg);
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    border-radius: 8px;
    padding: 10px 0;
    min-width: 220px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s;
    z-index: 1001;
}

.dropdown:hover .dropdown-menu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-menu li {
    list-style: none;
}

.dropdown-menu a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 20px;
    color: #333 !important;
    font-size: 0.9rem;
    transition: all 0.3s;
}

.dropdown-menu a:hover {
    background: #f5f5f5;
    color: #003366 !important;
    padding-left: 25px;
}

.dropdown-menu a i {
    width: 20px;
    color: #ff6600;
}

.dropdown-divider {
    margin: 8px 0;
    border: none;
    border-top: 1px solid #eee;
}

.navbar.scrolled .dropdown-menu {
    background: white;
}

.navbar.scrolled .dropdown-menu a {
    color: #333 !important;
}

.navbar.scrolled .dropdown-menu a:hover {
    background: #f5f5f5;
    color: #1a472a !important;
}

.hamburger {
    display: none;
    flex-direction: column;
    cursor: pointer;
    gap: 5px;
}

.hamburger span {
    width: 25px;
    height: 3px;
    background: #333;
    transition: all 0.3s;
    border-radius: 3px;
}

.hamburger.active span:nth-child(1) {
    transform: rotate(45deg) translate(5px, 5px);
}

.hamburger.active span:nth-child(2) {
    opacity: 0;
}

.hamburger.active span:nth-child(3) {
    transform: rotate(-45deg) translate(7px, -6px);
}

@media (max-width: 992px) {
    .nav-menu {
        gap: 15px;
    }
    .brand-text h1 {
        font-size: 1rem;
    }
    .logo img {
        height: 50px;
    }
}

@media (max-width: 768px) {
    .hamburger {
        display: flex;
    }
    
    .nav-menu {
        position: absolute;
        top: 80px;
        left: 0;
        right: 0;
        background: #f4f8f4;
        flex-direction: column;
        align-items: stretch;
        gap: 0;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        padding: 0;
    }
    
    .navbar.scrolled .nav-menu {
        background: #1a472a;
        top: 70px;
    }
    
    .nav-menu.active {
        max-height: 500px;
        padding: 20px 0;
    }
    
    .nav-menu > li {
        width: 100%;
        text-align: center;
    }
    
    .nav-menu > li > a {
        display: block;
        padding: 12px 20px;
        width: 100%;
    }
    
    .dropdown-menu {
        position: static;
        opacity: 1;
        visibility: visible;
        transform: none;
        box-shadow: none;
        padding-left: 20px;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s;
        background: rgba(0,0,0,0.05);
    }
    
    .dropdown:hover .dropdown-menu {
        max-height: 300px;
    }
    
    .navbar.scrolled .dropdown-menu {
        background: rgba(255,255,255,0.1);
    }
    
    .navbar.scrolled .dropdown-menu a {
        color: white !important;
    }
    
    .btn-login, .btn-register {
        display: inline-block;
        margin: 5px 20px;
    }
}

@media (max-width: 480px) {
    .brand-text h1 {
        font-size: 0.8rem;
    }
    .brand-text .subtitle {
        font-size: 0.6rem;
    }
    .logo img {
        height: 40px;
    }
    .navbar .container {
        min-height: 70px;
    }
}
</style>

<script>
// Scroll effect for navbar
window.addEventListener('scroll', function() {
    const navbar = document.getElementById('mainNavbar');
    if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
});

// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const hamburger = document.querySelector('.hamburger');
    const navMenu = document.querySelector('.nav-menu');

    if (hamburger) {
        hamburger.addEventListener('click', function() {
            hamburger.classList.toggle('active');
            navMenu.classList.toggle('active');
        });
    }
});

// Close mobile menu when clicking on a link
document.querySelectorAll('.nav-menu a').forEach(link => {
    link.addEventListener('click', () => {
        const hamburger = document.querySelector('.hamburger');
        const navMenu = document.querySelector('.nav-menu');
        if (hamburger && hamburger.classList.contains('active')) {
            hamburger.classList.remove('active');
            navMenu.classList.remove('active');
        }
    });
});
</script>