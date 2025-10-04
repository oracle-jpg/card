<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Microfinance System - Home</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
    body { background: #f8fafc; color: #1e293b; }

    /* Navbar */
    .navbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px 60px;
      background: #fff;
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
      position: sticky;
      top: 0;
      z-index: 100;
    }
    .navbar .logo {
      display: flex;
      align-items: center;
    }
    .navbar .logo img {
      height: 45px;
      margin-right: 12px;
    }
    .navbar nav a {
      margin: 0 12px;
      text-decoration: none;
      color: #334155;
      font-weight: 500;
    }
    .navbar nav a:hover { color: #2563eb; }
    .navbar .auth-buttons a {
      padding: 8px 16px;
      margin-left: 8px;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 500;
    }
    .btn-login { background: #2563eb; color: #fff; }
    .btn-login:hover { background: #1d4ed8; }
    .btn-signup { background: #e2e8f0; color: #1e293b; }
    .btn-signup:hover { background: #cbd5e1; }

    /* Hero */
    .hero {
      display: flex;
      justify-content: center;
      align-items: center;
      text-align: center;
      background: url('banner.jpg') no-repeat center center/cover;
      height: 450px;
      color: #fff;
      position: relative;
    }
    .hero::before {
      content: '';
      position: absolute;
      top:0; left:0; right:0; bottom:0;
      background: rgba(0,0,0,0.5);
    }
    .hero-content {
      position: relative;
      z-index: 1;
    }
    .hero h1 { font-size: 38px; margin-bottom: 15px; }
    .hero p { font-size: 18px; margin-bottom: 25px; }
    .hero a {
      background: #2563eb; padding: 12px 24px; color: #fff;
      text-decoration: none; border-radius: 8px; font-weight: 600;
    }
    .hero a:hover { background: #1d4ed8; }

    /* Services */
    .services { padding: 60px; text-align: center; }
    .services h2 { font-size: 28px; margin-bottom: 15px; }
    .services p { color: #64748b; margin-bottom: 40px; }
    .service-cards {
      display: flex;
      justify-content: center;
      gap: 20px;
    }
    .card {
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
      flex: 1;
      min-width: 250px;
    }
    .card h3 { margin-bottom: 10px; }
    .card p { color: #64748b; font-size: 15px; }

    /* Team Section */
    .team { padding: 60px; background: #f1f5f9; text-align: center; }
    .team h2 { font-size: 28px; margin-bottom: 15px; }
    .team p { margin-bottom: 20px; color: #475569; }
    .team a {
      padding: 10px 20px;
      background: #2563eb;
      color: #fff;
      text-decoration: none;
      border-radius: 6px;
    }
    .team a:hover { background: #1d4ed8; }

    /* Footer */
    footer {
      padding: 20px;
      background: #0f172a;
      color: #94a3b8;
      text-align: center;
      font-size: 14px;
    }
  </style>
</head>
<body>

  <!-- Navbar -->
  <div class="navbar">
    <div class="logo">
      <img src="logo.png" alt="CARD RBI Logo">
      <h1>Microfinance</h1>
    </div>
    <nav>
      <a href="#">About</a>
      <a href="#">Services</a>
      <a href="#">Contact</a>
    </nav>
    <div class="auth-buttons">
      <a href="index.php" class="btn-login">Login</a>
      <a href="register.php" class="btn-signup">Sign Up</a>
    </div>
  </div>

  <!-- Hero Section -->
  <section class="hero">
    <div class="hero-content">
      <h1>Empowering Communities Through Microfinance</h1>
      <p>We provide financial solutions and support to help individuals and small businesses thrive.</p>
      <a href="services.php">Explore Services</a>
    </div>
  </section>

  <!-- Services Section -->
  <section class="services">
    <h2>Our Services</h2>
    <p>We offer a range of microfinance services tailored to meet the needs of our clients.</p>
    <div class="service-cards">
      <div class="card">
        <h3>💼 Microloans</h3>
        <p>Small loans to help individuals and businesses get started or expand.</p>
      </div>
      <div class="card">
        <h3>📚 Business Training</h3>
        <p>Workshops and training to enhance business skills and financial management.</p>
      </div>
      <div class="card">
        <h3>💡 Financial Literacy</h3>
        <p>Education sessions to improve financial understanding and planning.</p>
      </div>
    </div>
  </section>

  <!-- Team Section -->
  <section class="team">
    <h2>For Our Team</h2>
    <p>Access the staff dashboard to manage client accounts and services.</p>
    <a href="staff_dashboard.php">Go to Staff Dashboard</a>
  </section>

  <!-- Footer -->
  <footer>
    <p>© 2024 CARD RBI Microfinance System. All rights reserved.</p>
  </footer>

</body>
</html>
