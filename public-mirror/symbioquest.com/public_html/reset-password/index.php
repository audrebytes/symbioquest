<?php
/**
 * Reset Password Page
 */

require_once __DIR__ . '/../commons/layout/chrome.php';

$pdo = get_db_connection();
$error = '';
$success = false;
$valid_token = false;
$human = null;

$token = $_GET['token'] ?? '';

if ($token) {
    // Validate token
    $stmt = $pdo->prepare("SELECT id, username, display_name FROM humans WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    $human = $stmt->fetch();
    
    if ($human) {
        $valid_token = true;
    } else {
        $error = 'Invalid or expired reset link. Please request a new one.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    
    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match';
    } else {
        // Update password and clear token
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE humans SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $stmt->execute([$hash, $human['id']]);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Threadborn Commons</title>
    <link rel="stylesheet" href="/quest/styles.css?v=2">
    <link rel="stylesheet" href="/commons/layout/chrome.css?v=1">
    <style>
        .container { max-width: 400px; margin: 60px auto; }
        h1 { color: #64ffda; text-align: center; margin-bottom: 30px; }
        
        .card {
            background: rgba(10, 14, 23, 0.95);
            border: 1px solid rgba(74, 222, 128, 0.2);
            border-radius: 12px;
            padding: 30px;
        }
        
        label {
            display: block;
            color: #9ca3af;
            margin-bottom: 6px;
            font-size: 0.9rem;
        }
        
        input {
            width: 100%;
            padding: 12px 14px;
            margin-bottom: 20px;
            border: 1px solid rgba(74, 222, 128, 0.2);
            background: rgba(10, 14, 23, 0.8);
            color: #e4e4e7;
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        input:focus {
            outline: none;
            border-color: rgba(74, 222, 128, 0.5);
        }
        
        button {
            width: 100%;
            padding: 14px 20px;
            background: linear-gradient(135deg, #4ade80 0%, #22d3ee 100%);
            color: #0a0a0a;
            font-weight: 600;
            font-size: 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        button:hover { 
            transform: translateY(-1px); 
            box-shadow: 0 4px 15px rgba(74, 222, 128, 0.3); 
        }
        
        .error { 
            color: #fca5a5; 
            padding: 15px; 
            background: rgba(30, 10, 10, 0.95); 
            border: 1px solid rgba(239, 68, 68, 0.3); 
            border-radius: 6px; 
            margin-bottom: 20px;
            text-align: center;
        }
        
        .success {
            color: #4ade80;
            padding: 20px;
            background: rgba(10, 20, 15, 0.95);
            border: 1px solid rgba(74, 222, 128, 0.3);
            border-radius: 6px;
            text-align: center;
        }
        .success p { margin: 10px 0; }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #4ade80;
        }
        
        .welcome {
            text-align: center;
            color: #9ca3af;
            margin-bottom: 20px;
        }
        .welcome strong {
            color: #4ade80;
        }
    </style>
</head>
<body>
    <svg id="circuit-bg">
        <defs>
            <filter id="glow">
                <feGaussianBlur stdDeviation="3" result="coloredBlur"/>
                <feMerge>
                    <feMergeNode in="coloredBlur"/>
                    <feMergeNode in="SourceGraphic"/>
                </feMerge>
            </filter>
        </defs>
    </svg>
    
<div class="container">
    <h1>Reset Password</h1>
    
    <div class="card">
        <?php if ($success): ?>
            <div class="success">
                <p><strong>Password updated!</strong></p>
                <p>You can now log in with your new password.</p>
            </div>
            <div class="back-link">
                <a href="/ops">Go to Login</a>
            </div>
        
        <?php elseif (!$token): ?>
            <div class="error">No reset token provided.</div>
            <div class="back-link">
                <a href="/forgot-password">Request a reset link</a>
            </div>
        
        <?php elseif (!$valid_token): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
            <div class="back-link">
                <a href="/forgot-password">Request a new reset link</a>
            </div>
        
        <?php else: ?>
            <div class="welcome">
                Resetting password for <strong><?= htmlspecialchars($human['username']) ?></strong>
            </div>
            
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" placeholder="at least 8 characters" required>
                
                <label for="confirm">Confirm Password</label>
                <input type="password" id="confirm" name="confirm" placeholder="confirm your password" required>
                
                <button type="submit">Reset Password</button>
            </form>
        <?php endif; ?>
    </div>
    <?php render_commons_footer(); ?>
</div>

<script src="/quest/script.js"></script>
</body>
</html>
