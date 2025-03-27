<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Beautiful Interface</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        input:focus {
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
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <img src="images/logo.jpeg" alt="Logo" class="form-logo" id="logo">
            <h2>Welcome Back</h2>
            <form action="login_process.php" method="post">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required placeholder="Enter your email">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required placeholder="Enter your password">
                </div>
                <div class="form-button">
                    <button type="submit" name="login">Login</button>
                </div>
                <div class="form-footer">
                    <p>Don't have an account? <a href="signup.php">Sign up</a></p>
                </div>
                <div class="form-footer">
                    <p>Forgot your password? <a href="reset_password.php">Reset password</a></p>
                </div>
            </form>
        </div>
    </div>
    
    <div class="floating-alert" id="alert"></div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const alertBox = document.getElementById('alert');
            const logo = document.getElementById('logo');
            
            // Add animation to logo on hover
            logo.addEventListener('mouseenter', function() {
                this.style.transform = 'rotate(10deg) scale(1.1)';
                this.style.transition = 'all 0.3s ease';
            });
            
            logo.addEventListener('mouseleave', function() {
                this.style.transform = 'rotate(0) scale(1)';
            });
            
            // Form submission handler
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Simple validation
                const email = document.getElementById('email').value;
                const password = document.getElementById('password').value;
                
                if (!email || !password) {
                    showAlert('Please fill in all fields', 'error');
                    return;
                }
                
                // Simulate form submission
                showAlert('Logging in...', 'info');
                
                // In a real application, you would send this to your server
                setTimeout(() => {
                    showAlert('Login successful! Redirecting...', 'success');
                    
                    // Redirect after 1.5 seconds
                    setTimeout(() => {
                        window.location.href = 'dashboard.php'; // Change to your actual dashboard page
                    }, 1500);
                }, 1500);
            });
            
            // Show alert function
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
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentNode.querySelector('label').style.color = '#667eea';
                });
                
                input.addEventListener('blur', function() {
                    this.parentNode.querySelector('label').style.color = '#555';
                });
            });
        });
    </script>
</body>
</html>