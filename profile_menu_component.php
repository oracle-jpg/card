<?php
// Tiyakin na ang $user variable ay available bago i-include ito.
// Ang variable na ito ay dapat na na-fetch na mula sa database sa inyong dashboard file.

// Basic logic to determine the name to show
$display_name = isset($user['full_name']) ? htmlspecialchars($user['full_name']) : 'User';
$display_role = isset($user['role']) ? ucfirst(htmlspecialchars($user['role'])) : 'Unknown Role';
?>
<style>
    /* Styling for the Profile Dropdown */
    .profile-container {
        position: relative;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        border-radius: 9999px; /* Pill shape */
        background: #eef2ff;
        border: 1px solid #c7d2fe;
        transition: background 0.2s;
        user-select: none;
    }
    .profile-container:hover {
        background: #c7d2fe;
    }
    .profile-info {
        font-size: 14px;
        line-height: 1.2;
    }
    .profile-info .name {
        font-weight: 600;
        color: #1e3a8a;
    }
    .profile-info .role {
        font-weight: 400;
        color: #64748b;
        font-size: 11px;
    }
    .profile-icon {
        width: 32px;
        height: 32px;
        background: #2563eb;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: 700;
    }
    .profile-dropdown {
        display: none;
        position: absolute;
        top: 100%;
        right: 0;
        margin-top: 10px;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        width: 200px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        z-index: 1000;
        overflow: hidden;
    }
    .dropdown-header {
        padding: 12px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 14px;
        color: #1e293b;
    }
    .dropdown-header .email {
        display: block;
        font-size: 11px;
        color: #64748b;
        margin-top: 2px;
    }
    .dropdown-item {
        display: block;
        padding: 10px 12px;
        text-decoration: none;
        color: #475569;
        transition: background-color 0.2s;
        font-size: 14px;
    }
    .dropdown-item:hover {
        background-color: #f1f5f9;
        color: #1e3a8a;
    }
    .dropdown-item.logout {
        color: #ef4444;
        border-top: 1px solid #f1f5f9;
    }
    .dropdown-item.logout:hover {
        background-color: #fef2f2;
    }
</style>

<div class="profile-container" onclick="toggleProfileDropdown(event)">
    <div class="profile-info">
        <span class="name"><?= $display_name ?></span>
        <span class="role"><?= $display_role ?></span>
    </div>
    <div class="profile-icon">
        <?= substr($display_name, 0, 1) ?>
    </div>

    <div class="profile-dropdown" id="profileDropdown">
        <div class="dropdown-header">
            Hi, <?= $display_name ?>!
            <span class="email"><?= isset($user['email']) ? htmlspecialchars($user['email']) : 'email@example.com' ?></span>
        </div>
        <a href="change_password.php" class="dropdown-item">Change Password</a>
        <!-- Assuming Admin and Staff can manage profile, while Client can only change password -->
        <?php if ($display_role !== 'Client'): ?>
            <a href="<?= strtolower($display_role) ?>_profile.php" class="dropdown-item">View Profile</a>
        <?php endif; ?>
        <a href="logout.php" class="dropdown-item logout">Logout</a>
    </div>
</div>

<script>
    function toggleProfileDropdown(event) {
        event.stopPropagation(); // Pigilan ang pag-click sa container na umabot sa window
        const dd = document.getElementById('profileDropdown');
        // I-toggle ang display
        dd.style.display = (dd.style.display === 'block') ? 'none' : 'block';
    }

    // Isara ang dropdown kapag nag-click sa labas
    window.onclick = function(event) {
        if (!event.target.closest('.profile-container')) {
            const dropdown = document.getElementById('profileDropdown');
            if (dropdown && dropdown.style.display === 'block') {
                dropdown.style.display = 'none';
            }
        }
    }
</script>
