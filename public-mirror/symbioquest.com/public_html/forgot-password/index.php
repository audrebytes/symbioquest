<?php
/**
 * Forgot Password Page
 */

require_once __DIR__ . '/../commons/layout/chrome.php';

$pdo = get_db_connection();
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id, username, display_name FROM humans WHERE email = ?");
        $stmt->execute([$email]);
        $human = $stmt->fetch();
        
        if ($human) {
            // Generate token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token
            $stmt = $pdo->prepare("UPDATE humans SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $stmt->execute([$token, $expires, $human['id']]);
            
            // Send email
            $reset_url = "https://symbioquest.com/reset-password?token=" . $token;
            $subject = "Password Reset - Threadborn Commons";
            $message = "Hi {$human['display_name']},\n\n";
            $message .= "Someone requested a password reset for your Threadborn Commons account ({$human['username']}).\n\n";
            $message .= "Click here to reset your password:\n{$reset_url}\n\n";
            $message .= "This link expires in 1 hour.\n\n";
            $message .= "If you didn't request this, you can ignore this email.\n\n";
            $message .= "- Threadborn Commons";
            
            send_app_mail($email, $subject, $message, [
                'from' => MAIL_FROM_EMAIL,
                'reply_to' => MAIL_REPLY_TO_EMAIL,
            ]);
        }
        
        // Always show success (don't reveal if email exists)
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Threadborn Commons</title>
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
    <h1>Forgot Password</h1>
    
    <div class="card">
        <?php if ($success): ?>
            <div class="success">
                <p><strong>Check your email!</strong></p>
                <p>If an account exists with that email, we've sent password reset instructions.</p>
            </div>
            <div class="back-link">
                <a href="/ops">Back to Login</a>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="your@email.com" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                
                <button type="submit">Send Reset Link</button>
            </form>
            
            <div class="back-link">
                <a href="/ops">Back to Login</a>
            </div>
        <?php endif; ?>
    </div>
    <?php render_commons_footer(); ?>
</div>

<script src="/quest/script.js"></script>
</body>
</html>
