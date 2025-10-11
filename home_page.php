<?php
session_start();

// Include necessary files (assuming these exist and handle database connection, etc.)
require_once 'db.php'; // Or wherever your PDO connection is defined

// Check if a user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_role = $_SESSION['user_role'] ?? ''; // Keep for potential future use or dashboard logic elsewhere

// Dashboard link logic can remain if 'Go to Dashboard' button is desired for logged-in staff
$dashboard_link = '';
if ($is_logged_in) {
    switch ($user_role) {
        case 'admin':
            $dashboard_link = 'admin_dashboard.php';
            break;
        case 'manager':
            $dashboard_link = 'manager_dashboard.php';
            break;
        case 'staff':
            $dashboard_link = 'staff_dashboard.php';
            break;
        default:
            $dashboard_link = 'dashboard.php'; // Generic dashboard if role is unknown or not specific
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Microfinance System - Home</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary-blue: #2563eb;
      --dark-blue: #1d4ed8;
      --light-gray: #e2e8f0;
      --dark-gray: #cbd5e1;
      --text-dark: #1e293b;
      --text-medium: #334155;
      --text-light: #64748b;
      --bg-light: #f8fafc;
      --bg-dark: #0f172a; /* This dark background color for the footer will remain */
    }

    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
    body { background: var(--bg-light); color: var(--text-dark); line-height: 1.6; }

    /* Utility Classes */
    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px;
    }
    .text-center { text-align: center; }
    .py-10 { padding-top: 100px; padding-bottom: 100px; }
    .py-20 { padding-top: 200px; padding-bottom: 200px; }
    .mb-4 { margin-bottom: 1rem; }
    .mb-8 { margin-bottom: 2rem; }
    .btn {
      display: inline-block;
      padding: 12px 24px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      transition: background-color 0.3s ease, transform 0.2s ease;
    }
    .btn-primary { background: var(--primary-blue); color: #fff; }
    .btn-primary:hover { background: var(--dark-blue); transform: translateY(-2px); }
    .btn-secondary { background: var(--light-gray); color: var(--text-dark); }
    .btn-secondary:hover { background: var(--dark-gray); transform: translateY(-2px); }

    /* Navbar */
    .navbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px 40px;
      background: #fff;
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
      position: sticky;
      top: 0;
      z-index: 100;
    }
    .navbar .logo-group {
      display: flex;
      align-items: center;
    }
    .navbar .logo-group img {
      height: 50px;
      margin-right: 12px;
    }
    .navbar .logo-group h1 {
      font-size: 24px;
      font-weight: 700;
      color: var(--text-dark);
    }
    .navbar nav {
      margin-left: auto; /* Push nav to the right */
      margin-right: 20px; /* Space between nav and auth buttons */
    }
    .navbar nav a {
      margin: 0 15px;
      text-decoration: none;
      color: var(--text-medium);
      font-weight: 500;
      transition: color 0.3s ease;
    }
    .navbar nav a:hover { color: var(--primary-blue); }
    .navbar .auth-buttons a {
      margin-left: 10px;
    }

    /* Hero */
    .hero {
      position: relative;
      height: 550px;
      display: flex;
      justify-content: center;
      align-items: center;
      text-align: center;
      color: #fff;
      overflow: hidden;
    }
    .hero-bg {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: url('https://images.unsplash.com/photo-1579621970795-87facc2f976d?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D') no-repeat center center/cover;
      filter: brightness(0.6);
      transform: scale(1.05);
      transition: transform 0.5s ease-out;
    }
    .hero:hover .hero-bg {
      transform: scale(1.0);
    }

    .hero-content {
      position: relative;
      z-index: 1;
      max-width: 800px;
      padding: 0 20px;
    }
    .hero h1 {
      font-size: 48px;
      font-weight: 800;
      margin-bottom: 20px;
      line-height: 1.2;
      animation: fadeInDown 1s ease-out;
    }
    .hero p {
      font-size: 20px;
      margin-bottom: 30px;
      animation: fadeInUp 1s ease-out 0.3s backwards;
    }
    .hero .btn {
      animation: fadeInUp 1s ease-out 0.6s backwards;
    }

    /* Empowering Section */
    .empowering-section {
      background: linear-gradient(135deg, var(--primary-blue) 0%, #3b82f6 100%);
      color: #fff;
      padding: 80px 0;
    }
    .empowering-content {
      display: flex;
      align-items: center;
      gap: 50px;
      flex-wrap: wrap;
    }
    .empowering-text {
      flex: 1;
      min-width: 300px;
    }
    .empowering-text h2 {
      font-size: 38px;
      font-weight: 800;
      margin-bottom: 25px;
      line-height: 1.2;
    }
    .empowering-text p {
      font-size: 18px;
      margin-bottom: 30px;
      opacity: 0.9;
    }
    .empowering-image {
      flex: 1;
      min-width: 300px;
      text-align: center;
    }
    .empowering-image img {
      max-width: 100%;
      height: auto;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
      transition: transform 0.5s ease-out;
    }
    .empowering-image img:hover {
      transform: scale(1.03);
    }

    /* Services Section */
    .services { padding: 80px 0; background: var(--bg-light); }
    .services h2 { font-size: 36px; margin-bottom: 20px; font-weight: 700; color: var(--primary-blue); }
    .services > p { font-size: 18px; color: var(--text-light); margin-bottom: 60px; }
    .service-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 30px;
    }
    .card {
      background: #fff;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      border-top: 4px solid var(--primary-blue);
    }
    .card:hover {
      transform: translateY(-8px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    .card h3 { font-size: 22px; margin-bottom: 15px; color: var(--primary-blue); }
    .card p { color: var(--text-light); font-size: 16px; flex-grow: 1; }
    .card i {
      color: var(--primary-blue);
      font-size: 40px;
      margin-bottom: 20px;
    }

    /* Team Section */
    .team { padding: 80px 0; background: var(--bg-light); text-align: center; }
    .team h2 { font-size: 36px; margin-bottom: 20px; font-weight: 700; color: var(--text-dark); }
    .team p { font-size: 18px; margin-bottom: 40px; color: var(--text-light); }

    /* Contact Section */
    .contact-section {
        background: #fff;
        padding: 80px 0;
        border-top: 1px solid #e2e8f0;
    }
    .contact-section h2 {
        font-size: 36px;
        margin-bottom: 20px;
        font-weight: 700;
        color: var(--primary-blue);
        text-align: center;
    }
    .contact-section > p {
        font-size: 18px;
        color: var(--text-light);
        margin-bottom: 60px;
        text-align: center;
    }
    .contact-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px;
        margin-top: 40px;
    }
    .contact-card {
        background: var(--bg-light);
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        border-left: 5px solid var(--primary-blue);
        text-align: left;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .contact-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }
    .contact-card h3 {
        color: var(--dark-blue);
        margin-bottom: 15px;
        font-size: 24px;
        display: flex;
        align-items: center;
    }
    .contact-card h3 i {
        font-size: 28px;
        margin-right: 15px;
        color: var(--primary-blue);
    }
    .contact-card p, .contact-card a {
        color: var(--text-medium);
        font-size: 16px;
        margin-bottom: 8px;
        display: block;
        text-decoration: none;
        line-height: 1.5;
    }
    .contact-card a:hover {
        color: var(--primary-blue);
        text-decoration: underline;
    }
    .contact-card strong {
        color: var(--text-dark);
        font-weight: 600;
    }
    .contact-card .sub-heading {
        font-weight: 700;
        color: var(--dark-blue);
        margin-top: 20px;
        margin-bottom: 10px;
        font-size: 18px;
    }

    /* Footer */
    footer {
      padding: 40px 20px; /* Increased padding */
      background: #363636; /* Directly set background to match screenshot */
      color: #bbbbbb; /* Adjusted text color for readability on dark gray */
      font-size: 14px;
      margin-top: 60px;
      line-height: 1.8;
      width: 100%; /* Ensure footer spans full width */
    }

    footer .footer-content {
      display: flex;
      justify-content: space-around; /* Distribute items with space */
      flex-wrap: wrap; /* Allow wrapping on smaller screens */
      gap: 20px; /* Space between columns */
      max-width: 1200px;
      margin: 0 auto;
      text-align: left; /* Align text left within each column */
    }

    footer .footer-col {
      flex: 1; /* Allow columns to grow */
      min-width: 250px; /* Minimum width for each column before wrapping */
      padding: 10px 0;
    }

    footer .footer-col.col-wide {
      min-width: 300px; /* Make one column potentially wider */
    }

    footer .footer-col h4 {
        color: #fff; /* White heading for better contrast */
        font-weight: 600;
        margin-bottom: 15px;
        font-size: 16px;
    }

    footer .footer-col p, footer .footer-col div {
        margin-bottom: 8px; /* Consistent spacing for text blocks */
        color: #bbbbbb; /* Consistent text color */
    }

    footer .footer-col a {
      color: #e0f2fe; /* Your existing link color */
      text-decoration: none;
      transition: color 0.3s ease;
    }
    footer .footer-col a:hover {
      color: #fff;
      text-decoration: underline;
    }

    footer .pdic-logo {
        height: 40px;
        margin-top: 10px; /* Adjust margin as needed */
        margin-bottom: 10px;
        display: inline-block; /* Keep it inline-block for proper alignment */
        vertical-align: middle;
        background-color: transparent; /* Remove white background */
        padding: 0; /* Remove padding */
        border-radius: 0; /* Remove border-radius */
    }
    
    footer .bancnet-logo { /* New style for BancNet logo */
        height: 30px; /* Adjust size as needed */
        vertical-align: middle;
        margin-right: 10px; /* Space between BancNet text and logo */
        filter: invert(1); /* Invert colors to make it visible on dark background if it's black */
    }
    
    /* Specific adjustments for the footer */
    footer .bsp-regulated-text {
        line-height: 1.5;
    }
    footer .footer-links-group a {
        margin-right: 15px; /* Space between footer links */
        white-space: nowrap; /* Prevent links from breaking if short */
    }
    footer .footer-links-group a:last-child {
        margin-right: 0;
    }
    footer .footer-links-group {
        display: flex;
        flex-wrap: wrap;
        gap: 5px; /* Small gap between wrapped links */
        justify-content: flex-start; /* Align links to the left */
    }


    /* Keyframe Animations */
    @keyframes fadeInDown {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    /* Responsive adjustments */
    @media (max-width: 992px) {
      .navbar { padding: 15px 20px; }
      .navbar .logo-group h1 { font-size: 20px; }
      .navbar nav { display: none; } /* Hide nav for smaller screens */
      .hero h1 { font-size: 38px; }
      .hero p { font-size: 18px; }
      .empowering-content { flex-direction: column; text-align: center; }
      .empowering-text, .empowering-image { min-width: unset; width: 100%; }
      .empowering-image img { max-height: 350px; object-fit: cover; }
      .services, .empowering-section, .team, .contact-section { padding: 60px 0; }
    }

    @media (max-width: 768px) {
      .navbar .logo-group img { height: 40px; }
      .hero { height: 400px; }
      .hero h1 { font-size: 32px; }
      .hero p { font-size: 16px; }
      .btn { padding: 10px 20px; font-size: 14px; }
      .service-cards { grid-template-columns: 1fr; }
      .contact-info-grid { grid-template-columns: 1fr; }
      .navbar nav { margin-right: 0; } /* Remove extra margin */
      .navbar .logo-group { width: auto; justify-content: flex-start; margin-bottom: 0; } /* Revert centering */

      footer .footer-content {
          flex-direction: column; /* Stack columns vertically on small screens */
          text-align: center; /* Center text in columns */
      }
      footer .footer-col {
          min-width: unset; /* Remove min-width to allow full width */
          width: 100%;
          border-bottom: 1px solid rgba(255,255,255,0.1); /* Separator between stacked columns */
          padding-bottom: 20px;
          margin-bottom: 20px;
      }
      footer .footer-col:last-child {
          border-bottom: none;
          margin-bottom: 0;
      }
      footer .footer-links-group {
          justify-content: center; /* Center links when stacked */
      }
    }

    @media (max-width: 480px) {
      .navbar { flex-wrap: wrap; justify-content: center; gap: 10px; }
      .navbar .logo-group { width: 100%; justify-content: center; margin-bottom: 10px; }
      .navbar .auth-buttons { width: 100%; justify-content: center; display: flex; }
      .hero h1 { font-size: 28px; }
      .hero p { font-size: 14px; }
      .contact-card h3 { font-size: 20px; }
      .contact-card p, .contact-card a { font-size: 15px; }
    }
  </style>
  <!-- Font Awesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

  <!-- Navbar -->
  <div class="navbar">
    <div class="logo-group">
      <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="CARD RBI Logo">
      <h1>Microfinance</h1>
    </div>
    <nav>
      <a href="#about">About</a>
      <a href="#services">Services</a>
      <a href="#contact-us">Contact</a>
    </nav>
    <div class="auth-buttons">
      <?php if ($is_logged_in): ?>
        <a href="logout.php" class="btn btn-primary">Logout</a>
      <?php else: ?>
        <a href="index.php" class="btn btn-primary">Login</a>
        <a href="register.php" class="btn btn-secondary">Sign Up</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Hero Section -->
  <section class="hero">
    <div class="hero-bg"></div>
    <div class="hero-content">
      <h1>Empowering Communities Through Microfinance</h1>
      <p>We provide accessible financial solutions and comprehensive support to help individuals and small businesses flourish.</p>
      <a href="#services" class="btn btn-primary">Explore Services</a>
    </div>
  </section>

  <!-- Empowering Communities Section -->
  <section class="empowering-section" id="about">
    <div class="container empowering-content">
      <div class="empowering-text">
        <h2>A Legacy of Growth and Empowerment</h2>
        <p>For decades, CARD RBI has been at the forefront of microfinance, transforming lives and fostering economic independence. Our holistic approach combines financial services with social development programs, ensuring sustainable progress for the communities we serve.</p>
        <a href="#" class="btn btn-secondary">Learn More About Us</a>
      </div>
      <div class="empowering-image">
        <img src="https://www.chaitanyaindia.in/wp-content/uploads/2024/09/How-Microfinance-Bank-Transforms-Opportunities-for-Women-600x411.jpg" alt="Empowering Communities">
      </div>
    </div>
  </section>

  <!-- Services Section -->
  <section class="services" id="services">
    <div class="container text-center">
      <h2>Our Comprehensive Services</h2>
      <p>We offer a diverse range of microfinance services meticulously crafted to address the unique aspirations and needs of our valued clients.</p>
      <div class="service-cards">
        <div class="card">
          <i class="fas fa-hand-holding-usd"></i>
          <h3>Microloans for Growth</h3>
          <p>Flexible small loans designed to kickstart new ventures, expand existing businesses, or meet essential household needs, fostering economic self-sufficiency.</p>
        </div>
        <div class="card">
          <i class="fas fa-chart-line"></i>
          <h3>Business Development & Training</h3>
          <p>Engaging workshops and practical training sessions focused on enhancing entrepreneurial skills, improving financial management, and navigating market challenges.</p>
        </div>
        <div class="card">
          <i class="fas fa-book-open"></i>
          <h3>Financial Literacy Programs</h3>
          <p>Empowering educational sessions that demystify financial concepts, cultivate responsible saving habits, and promote informed decision-making for long-term stability.</p>
        </div>
        <div class="card">
            <i class="fas fa-piggy-bank"></i>
            <h3>Savings Mobilization</h3>
            <p>Encouraging and facilitating accessible savings options to build financial resilience and provide a safety net for future aspirations.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Team Section -->
  <section class="team">
    <div class="container text-center">
      <h2>Join Our Dedicated Team</h2>
      <p>Are you a part of our team? Access your personalized dashboard to efficiently manage client accounts, streamline services, and contribute to our mission.</p>
      <?php if ($is_logged_in): ?>
        <a href="<?= htmlspecialchars($dashboard_link) ?>" class="btn btn-primary">Go to Dashboard</a>
      <?php else: ?>
        <a href="index.php" class="btn btn-primary">Login</a>
      <?php endif; ?>
    </div>
  </section>

  <!-- Contact Section - Re-formatted! -->
  <section class="contact-section" id="contact-us">
    <div class="container">
      <h2>Get in Touch With Us</h2>
      <p>We are always ready to listen and assist you with your microfinance needs. Find our contact details below.</p>
      <div class="contact-info-grid">
        <div class="contact-card">
          <h3><i class="fas fa-building"></i> Main Office</h3>
          <p>P. Guevarra St., Corner Aguirre St., Brgy. Poblacion II, Sta. Cruz, Laguna</p>
          <p><strong>Telephone:</strong> <a href="tel:+63495231047">(049) 523-1047</a></p>
          <p><strong>Email:</strong> <a href="mailto:cmrbi@cardmri.com">cmrbi@cardmri.com</a></p>
          <p><strong>Facebook:</strong> <a href="https://www.facebook.com/CMRBofficial" target="_blank">CMRB Official Page</a></p>
        </div>

        <div class="contact-card">
          <h3><i class="fas fa-headset"></i> Customer Service</h3>
          <p class="sub-heading">Concerns & Inquiries</p>
          <p><strong>Telephone:</strong> <a href="tel:+63495307284">(049) 530-7284</a></p>
          <p><strong>Cellphone (Globe - calls):</strong> <a href="tel:+639171327589">0917-132-7589</a></p>
          <p><strong>Cellphone (Smart - calls):</strong> <a href="tel:+639998804785">0999-880-4785</a>, <a href="tel:+639610170677">0961-017-0677</a></p>
          <p><strong>Cellphone (Smart - text):</strong> <a href="sms:+639387446274">0938-744-6274</a></p>
          <p><strong>Cellphone (Smart - outgoing calls):</strong> <a href="tel:+639692854378">0969-285-4378</a>, <a href="tel:+639610170676">0961-017-0676</a></p>
          <p><strong>Email:</strong> <a href="mailto:cmrbi.csr@cardmri.com">cmrbi.csr@cardmri.com</a></p>
        </div>

        <div class="contact-card">
          <h3><i class="fas fa-user-shield"></i> Data Privacy Office</h3>
          <p class="sub-heading">For Data Privacy Concerns</p>
          <p><strong>Contact Person:</strong> Data Privacy Officer</p>
          <p><strong>Telephone:</strong> <a href="tel:+63495231047">(049) 523-1047</a></p>
          <p><strong>Email:</strong> <a href="mailto:cmrbi.compliance@cardmri.com">cmrbi.compliance@cardmri.com</a></p>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer - Restructured to match screenshot -->
  <footer>
    <div class="footer-content">
      <div class="footer-col">
        <p>© 2025 | CARD MRI RIZAL BANK, Inc. ALL RIGHTS RESERVED</p>
        <p>CREATED BY CARD MRI INFORMATION TECHNOLOGY INC.</p>
        <!-- Added a scroll-to-top button here to match the screenshot -->
        <a href="#" class="scroll-to-top" style="
          position: absolute;
          left: 20px; /* Adjust as needed */
          bottom: 20px; /* Adjust as needed */
          background-color: black;
          color: white;
          padding: 8px 12px;
          border-radius: 5px;
          text-decoration: none;
          font-size: 20px;
          line-height: 1;
          display: inline-block;
        ">&#9650;</a> <!-- Upward arrow -->
      </div>

      <div class="footer-col">
        <p>CARD MRI RIZAL BANK, Inc. is a proud member of</p>
        <p style="display: flex; align-items: center; margin-top: 5px;">
          <!-- Placeholder for BancNet Logo, adjust path as needed -->
          <img src="https://www.cardmri.com/rbi/wp-content/themes/mh-magazine-lite/images/Banknet-logo.png" alt="BancNet Logo" class="bancnet-logo">
          
        </p>
      </div>

      <div class="footer-col">
        <!-- Placeholder for PDIC Logo, ensure correct path and transparent background -->
        <img src="https://www.cardmri.com/rbi/wp-content/uploads/2025/09/PDIC.png" alt="PDIC Logo" class="pdic-logo">
        <p>Deposits are insured by PDIC up to ₱1 Million per depositor.</p>
      </div>

      <div class="footer-col col-wide">
        <p class="bsp-regulated-text">Regulated by the Bangko Sentral ng Pilipinas with email address <a href="mailto:consumeraffairs@bsp.gov.ph" style="color: #e0f2fe;">consumeraffairs@bsp.gov.ph</a>.</p>
      </div>

      <div class="footer-col footer-links-group">
        <a href="#">Terms of Use</a> |
        <a href="#">Data Privacy Statement</a> |
        <a href="#">Downloads</a> |
        <a href="#">konek2CARD Terms and Conditions</a>
      </div>
    </div>
  </footer>

</body>
</html>