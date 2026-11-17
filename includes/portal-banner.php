<?php
// includes/portal-banner.php - Universal Top Page Hero Banner for Resident & Staff Portal
// Renders the clean 2-column banner matching Admin Panel styling:
// [Left: Icon + Title + Subtitle] | [Right: 'One Palayan - Kalinga Hub' in Bulgash Normal]

$b_title = $banner_title ?? ($page_title ?? 'Portal Center');
$b_subtitle = $banner_subtitle ?? '';
$b_icon = $banner_icon ?? 'fa-th-large';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,600&display=swap');

@font-face {
    font-family: 'Bulgash';
    src: url('../fonts/Bulgash.otf') format('opentype'),
         url('fonts/Bulgash.otf') format('opentype');
    font-weight: normal;
    font-style: normal;
    font-display: swap;
}

@font-face {
    font-family: 'Brigtha';
    src: url('../fonts/Brigtha.ttf') format('truetype'),
         url('fonts/Brigtha.ttf') format('truetype');
    font-weight: normal;
    font-style: normal;
    font-display: swap;
}

.portal-hero-banner {
    background: var(--ref-card, #FFFFFF);
    border: 1px solid var(--ref-border, rgba(0, 0, 0, 0.08));
    border-radius: 24px;
    padding: 16px 28px;
    margin-bottom: 22px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
    box-sizing: border-box;
    width: 100%;
    position: relative;
}

[data-theme="dark"] .portal-hero-banner {
    background: #141E22;
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4);
}

.banner-left-wrap {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    flex: 1 1 auto;
    min-width: 290px;
}

.banner-icon-box {
    font-size: 1.45rem;
    color: #7F0303;
    line-height: 1.2;
    flex-shrink: 0;
    margin-top: 3px;
    display: flex;
    align-items: center;
    justify-content: center;
}

[data-theme="dark"] .banner-icon-box {
    color: #F87171;
}

.banner-text-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.banner-text-group h2,
.banner-text-group h1 {
    font-size: 1.25rem !important;
    font-weight: 800 !important;
    color: var(--ref-text, #191D1E) !important;
    margin: 0 !important;
    line-height: 1.3 !important;
    letter-spacing: -0.01em;
}

.banner-text-group p {
    font-size: 0.82rem !important;
    color: var(--ref-muted, #71818B) !important;
    margin: 0 !important;
    line-height: 1.45 !important;
}

.banner-right-side {
    display: flex;
    align-items: center;
    gap: 18px;
    margin-left: auto;
    flex-shrink: 0;
}

.banner-center-branding {
    flex: 0 0 auto;
    text-align: right;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-left: 0;
    margin-left: 0;
}

/* Exact admin panel font & typography styling */
.banner-center-branding .banner-cursive-title,
.dash-cursive-title {
    font-family: 'Bulgash', 'Playfair Display', cursive, serif !important;
    font-size: 2.35rem !important;
    font-weight: normal !important;
    color: #7F0303 !important;
    text-align: right !important;
    line-height: 1.15 !important;
    letter-spacing: 0.5px !important;
    margin: 0 !important;
    white-space: nowrap !important;
    user-select: none;
}

[data-theme="dark"] .banner-center-branding .banner-cursive-title {
    color: #F87171 !important;
}

/* Actions Wrap & Segmented Switcher in Portal Banner (Right-aligned, never in middle) */
.banner-actions-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
    margin: 0;
}

.view-toggle-segmented {
    display: inline-flex;
    align-items: center;
    background: var(--ref-canvas, #E4EFE7);
    padding: 4px;
    border-radius: 50px;
    border: 1px solid var(--ref-border, rgba(0, 0, 0, 0.08));
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.04);
}

.view-toggle-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 18px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--ref-muted, #71818B);
    text-decoration: none;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.view-toggle-btn:hover {
    color: var(--ref-text, #191D1E);
}

.view-toggle-btn.active {
    background: #FFFFFF;
    color: var(--ref-maroon, #7F0303);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

[data-theme="dark"] .view-toggle-segmented {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 255, 255, 0.12);
}

[data-theme="dark"] .view-toggle-btn {
    color: #94A3B8;
}

[data-theme="dark"] .view-toggle-btn.active {
    background: #1E2D33;
    color: #FFFFFF;
}

.banner-action-buttons {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.banner-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 18px;
    border-radius: 50px;
    font-size: 0.82rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.banner-btn-primary {
    background: var(--ref-maroon, #7F0303);
    color: #FFFFFF !important;
    border: 1px solid transparent;
    box-shadow: 0 2px 6px rgba(127, 3, 3, 0.25);
}

.banner-btn-primary:hover {
    background: #9B0404;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(127, 3, 3, 0.35);
}

.banner-btn-secondary {
    background: var(--ref-card, #FFFFFF);
    color: var(--ref-text, #191D1E) !important;
    border: 1px solid var(--ref-border, rgba(0, 0, 0, 0.12));
}

.banner-btn-secondary:hover {
    background: var(--ref-canvas, #F1F5F9);
    border-color: rgba(0, 0, 0, 0.2);
}

[data-theme="dark"] .banner-btn-secondary {
    background: #1E2D33;
    color: #FFFFFF !important;
    border-color: rgba(255, 255, 255, 0.12);
}

@media (max-width: 992px) {
    .portal-hero-banner {
        flex-direction: column;
        align-items: flex-start;
        gap: 14px;
        padding: 16px 20px;
    }
    .banner-left-wrap {
        min-width: 0 !important;
        width: 100%;
    }
    .banner-right-side {
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        margin-left: 0;
        flex-wrap: wrap;
    }
    .banner-actions-wrap {
        margin: 0;
    }
    .banner-center-branding {
        align-self: center;
        margin-left: auto;
        padding-left: 0;
        text-align: right;
    }
    .banner-center-branding .banner-cursive-title {
        text-align: right !important;
        font-size: 1.65rem !important;
        white-space: normal !important;
    }
}

@media (max-width: 600px) {
    .portal-hero-banner {
        padding: 12px 16px;
        border-radius: 18px;
        margin-bottom: 16px;
        gap: 10px;
    }
    .banner-icon-box {
        font-size: 1.2rem;
    }
    .banner-text-group h2,
    .banner-text-group h1 {
        font-size: 1.05rem !important;
    }
    .banner-text-group p {
        font-size: 0.75rem !important;
    }
    .banner-right-side {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
    }
    .banner-actions-wrap {
        width: 100%;
    }
    .view-toggle-segmented {
        width: 100%;
        display: flex;
    }
    .view-toggle-btn {
        flex: 1;
        justify-content: center;
        padding: 6px 8px;
        font-size: 0.72rem;
    }
    .banner-center-branding {
        margin: 2px 0 0;
        text-align: center !important;
        justify-content: center;
        width: 100%;
    }
    .banner-center-branding .banner-cursive-title {
        text-align: center !important;
        font-size: 1.25rem !important;
        white-space: normal !important;
    }
}
</style>

<div class="portal-hero-banner">
    <div class="banner-left-wrap">
        <?php if (!empty($b_icon)): ?>
            <div class="banner-icon-box">
                <i class="fas <?php echo htmlspecialchars($b_icon); ?>"></i>
            </div>
        <?php endif; ?>
        <div class="banner-text-group">
            <h2><span><?php echo htmlspecialchars($b_title); ?></span></h2>
            <?php if (!empty($b_subtitle)): ?>
                <p><?php echo htmlspecialchars($b_subtitle); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="banner-right-side">
    <?php if (!empty($banner_actions) && is_array($banner_actions)): ?>
        <?php 
        $cur_script = basename(explode('?', $_SERVER['PHP_SELF'] ?? '')[0]);
        
        // Check if this action set represents a view switcher (e.g. Table vs Kanban)
        $has_kanban = false;
        foreach ($banner_actions as $act) {
            if (stripos($act['label'] ?? '', 'kanban') !== false) {
                $has_kanban = true;
                break;
            }
        }
        ?>
        <?php if ($has_kanban && count($banner_actions) <= 3): ?>
            <div class="banner-actions-wrap">
                <div class="view-toggle-segmented" role="group" aria-label="View Switcher">
                    <?php foreach ($banner_actions as $act): 
                        $act_url = $act['url'] ?? '#';
                        $act_base = basename(explode('?', $act_url)[0]);
                        $is_active = ($act_base === $cur_script) || (!empty($act['active']));
                        $clean_label = preg_replace('/\s+(Board|View)$/i', '', $act['label'] ?? '');
                    ?>
                        <a href="<?php echo htmlspecialchars($act_url); ?>" class="view-toggle-btn <?php echo $is_active ? 'active' : ''; ?>" title="<?php echo htmlspecialchars($act['label']); ?>">
                            <?php if (!empty($act['icon'])): ?>
                                <i class="fas <?php echo htmlspecialchars($act['icon']); ?>"></i>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($clean_label); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="banner-center-branding">
        <span class="banner-cursive-title">One Palayan - Kalinga Hub</span>
    </div>
    </div><!-- End banner-right-side -->
</div>

<?php unset($banner_actions, $banner_title, $banner_subtitle, $banner_icon); ?>
