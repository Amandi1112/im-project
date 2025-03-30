<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg,rgb(214, 217, 231) 0%,rgb(215, 177, 254) 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
        }
        
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            text-align: center;
            transform: translateY(0);
            transition: all 0.3s ease;
        }
        
        .form-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
        }
        
        .form-logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
            border: 3px solid rgba(102, 126, 234, 0.3);
        }
        
        h2 {
            color: #333;
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-group1 {
            margin-bottom: 20px;
            text-align: left;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        input:focus, select:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }
        
        .form-button {
            margin: 25px 0;
        }
        
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(to right, #667eea, #764ba2);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(102, 126, 234, 0.6);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        .form-footer {
            margin-top: 15px;
            font-size: 14px;
            color: #666;
        }
        
        .form-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .form-footer a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .floating-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #ff4757;
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transform: translateX(150%);
            transition: transform 0.4s ease;
            z-index: 1000;
        }
        
        .floating-alert.show {
            transform: translateX(0);
        }
        
        select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 1em;
        }
        
        /* Email field indicator */
        .email-field {
            position: relative;
        }
        
        .email-loader {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: none;
        }
        
        @keyframes spin {
            0% { transform: translateY(-50%) rotate(0deg); }
            100% { transform: translateY(-50%) rotate(360deg); }
        }
        
        /* Email status styling */
        .email-status {
            font-size: 12px;
            margin-top: 5px;
            font-weight: 500;
            display: none;
        }
        
        .email-status.available {
            color: #2ed573;
        }
        
        .email-status.unavailable {
            color: #ff4757;
        }
        
        /* Notification styling at the top of the form */
        .notification {
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            display: none;
        }
        
        .notification.error {
            background-color: rgba(255, 71, 87, 0.1);
            color: #ff4757;
            border: 1px solid rgba(255, 71, 87, 0.3);
        }
        
        .notification.success {
            background-color: rgba(46, 213, 115, 0.1);
            color: #2ed573;
            border: 1px solid rgba(46, 213, 115, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <img src="images/logo.jpeg" alt="Logo" class="form-logo" id="logo">
            <h2>Create an Account</h2>
            <!-- Notification container -->
            <div id="notification" class="notification"></div>
            <form action="signup_process.php" method="post" id="signupForm">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" required placeholder="Enter your full name">
                </div>
                <div class="form-group1">
                    <label for="position">Position</label>
                    <select id="position" name="position" required>
                        <option value="" disabled selected>Select your position</option>
                        <option value="accountant">Accountant</option>
                        <option value="clerk">Clerk</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="email-field">
                        <input type="email" id="email" name="email" required placeholder="Enter your email">
                        <div class="email-loader" id="emailLoader"></div>
                    </div>
                    <p class="email-status" id="emailStatus"></p>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required placeholder="Create a password">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm your password">
                </div>
                <div class="form-button">
                    <button type="submit" name="signup" id="submitBtn">Sign Up</button>
                </div>
                <div class="form-footer">
                    <p>Already have an account? <a href="login.php">Login</a></p>
                </div>
            </form>
        </div>
    </div>
    
    <div class="floating-alert" id="alert"></div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('signupForm');
            const alertBox = document.getElementById('alert');
            const logo = document.getElementById('logo');
            const emailInput = document.getElementById('email');
            const emailStatus = document.getElementById('emailStatus');
            const emailLoader = document.getElementById('emailLoader');
            const submitBtn = document.getElementById('submitBtn');
            const notification = document.getElementById('notification');
            const nameInput = document.getElementById('name');
            const positionSelect = document.getElementById('position');
            
            let emailTimeout = null;
            let isEmailAvailable = true;
            
            // Add animation to logo on hover
            logo.addEventListener('mouseenter', function() {
                this.style.transform = 'rotate(10deg) scale(1.1)';
                this.style.transition = 'all 0.3s ease';
            });
            
            logo.addEventListener('mouseleave', function() {
                this.style.transform = 'rotate(0) scale(1)';
            });
            
            // Password matching validation
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            function validatePassword() {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity("Passwords don't match");
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
            
            password.onchange = validatePassword;
            confirmPassword.onkeyup = validatePassword;
            
            // Function to check URL parameters and display notifications
            function checkURLParameters() {
                const params = new URLSearchParams(window.location.search);
                
                if (params.has('error')) {
                    showNotification(params.get('error'), 'error');
                }
                
                if (params.has('success')) {
                    showNotification(params.get('success'), 'success');
                }
                
                // Fill form fields with previous values if available
                if (params.has('name')) {
                    nameInput.value = params.get('name');
                }
                
                if (params.has('email')) {
                    emailInput.value = params.get('email');
                }
                
                if (params.has('position')) {
                    positionSelect.value = params.get('position');
                }
            }
            
            // Show notification in the form
            function showNotification(message, type) {
                notification.textContent = message;
                notification.className = `notification ${type}`;
                notification.style.display = 'block';
                
                // Also show floating alert
                showAlert(message, type);
            }
            
            // Email availability check
            emailInput.addEventListener('input', function() {
                const email = this.value.trim();
                
                // Clear previous timeout
                if (emailTimeout) {
                    clearTimeout(emailTimeout);
                }
                
                // Hide previous status
                emailStatus.style.display = 'none';
                
                // Validate email format first
                if (email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    // Show loader
                    emailLoader.style.display = 'block';
                    
                    // Set timeout to avoid too many requests
                    emailTimeout = setTimeout(function() {
                        checkEmailAvailability(email);
                    }, 500);
                }
            });
            
            // Check email availability via AJAX
            function checkEmailAvailability(email) {
                fetch(`signup_process.php?email_check=${encodeURIComponent(email)}`)
                    .then(response => response.json())
                    .then(data => {
                        // Hide loader
                        emailLoader.style.display = 'none';
                        
                        // Show status
                        emailStatus.style.display = 'block';
                        
                        if (data.exists) {
                            emailStatus.textContent = 'Email already exists';
                            emailStatus.className = 'email-status unavailable';
                            isEmailAvailable = false;
                        } else {
                            emailStatus.textContent = 'Email is available';
                            emailStatus.className = 'email-status available';
                            isEmailAvailable = true;
                        }
                    })
                    .catch(error => {
                        console.error('Error checking email:', error);
                        emailLoader.style.display = 'none';
                    });
            }
            
            // Form submission handler
            form.addEventListener('submit', function(e) {
                // Check if passwords match
                if (password.value !== confirmPassword.value) {
                    e.preventDefault();
                    showNotification("Passwords don't match", 'error');
                    return;
                }
                
                // Check email availability before submission
                if (!isEmailAvailable && emailInput.value.trim() !== '') {
                    e.preventDefault();
                    showNotification("Email already exists. Please use a different email.", 'error');
                    emailInput.focus();
                    return;
                }
                
                // Check password length
                if (password.value.length < 6) {
                    e.preventDefault();
                    showNotification("Password must be at least 6 characters", 'error');
                    return;
                }
            });
            
            // Show alert function for floating notifications
            function showAlert(message, type) {
                alertBox.textContent = message;
                alertBox.classList.add('show');
                
                // Set color based on type
                switch(type) {
                    case 'error':
                        alertBox.style.background = '#ff4757';
                        break;
                    case 'success':
                        alertBox.style.background = '#2ed573';
                        break;
                    case 'info':
                        alertBox.style.background = '#1e90ff';
                        break;
                }
                
                // Hide after 3 seconds
                setTimeout(() => {
                    alertBox.classList.remove('show');
                }, 3000);
            }
            
            // Add input focus effects
            const inputs = document.querySelectorAll('input, select');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    const label = this.parentNode.querySelector('label') || 
                                 this.parentNode.parentNode.querySelector('label');
                    if (label) {
                        label.style.color = '#667eea';
                    }
                });
                
                input.addEventListener('blur', function() {
                    const label = this.parentNode.querySelector('label') || 
                                 this.parentNode.parentNode.querySelector('label');
                    if (label) {
                        label.style.color = '#555';
                    }
                });
            });
            
            // Check URL parameters on page load
            checkURLParameters();
        });
    </script>
</body>
</html>