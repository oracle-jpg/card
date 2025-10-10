<?php
/**
 * admin_functions.php
 * Contains utility functions for logging and notifications used across the application.
 */

// =================================================================
// 1. Audit Logging Function
// =================================================================

/**
 * Logs an action performed by a user to the 'logs' table.
 * * @param PDO $pdo The PDO database connection object.
 * @param int $user_id The ID of the user performing the action.
 * @param string $action_type A short code describing the action (e.g., 'LOAN_REQUESTED').
 * @param string $description A detailed description of the action.
 */
function logAudit($pdo, $user_id, $action_type, $description) {
    try {
        $stmt = $pdo->prepare("INSERT INTO logs (user_id, action, action_description, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, $action_type, $description]);
    } catch (PDOException $e) {
        // Log the error internally but do not break the main application flow
        error_log("Audit Log Error: " . $e->getMessage());
        // For debugging: echo "Audit Log Error: " . $e->getMessage();
    }
}


// =================================================================
// 2. Notification Function
// =================================================================

/**
 * Creates a notification for a specific user role (e.g., 'admin').
 * * NOTE: This assumes there is a 'users' table with a 'role' column 
 * and a 'notifications' table.
 * * @param PDO $pdo The PDO database connection object.
 * @param string $target_role The role to notify (e.g., 'admin', 'staff').
 * @param string $title The title of the notification.
 * @param string $message The full message content.
 * @param int|null $user_id Optional specific user ID to notify (if NULL, targets all users of the role).
 */
function notifyRole($pdo, $target_role, $title, $message, $user_id = null) {
    try {
        // 1. Find the User IDs belonging to the target role
        $role_stmt = $pdo->prepare("SELECT id FROM users WHERE role = ?");
        $role_stmt->execute([$target_role]);
        $target_users = $role_stmt->fetchAll(PDO::FETCH_COLUMN);

        // 2. Insert notification for each targeted user
        if ($target_users) {
            $insert_stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
            
            foreach ($target_users as $uid) {
                // If a specific $user_id is passed, only notify that user
                if ($user_id === null || $user_id == $uid) {
                    $insert_stmt->execute([$uid, $title, $message]);
                }
            }
        }
    } catch (PDOException $e) {
        // Log the error internally
        error_log("Notification Error: " . $e->getMessage());
        // For debugging: echo "Notification Error: " . $e->getMessage();
    }
}
?>
