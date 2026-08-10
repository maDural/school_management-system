<?php
require_once 'config/database.php';
if (isLoggedIn()) {
if (isTeacher()) {
redirect('pages/teacher/dashboard.php');
} else {
redirect('pages/student/dashboard.php');
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Mabaso Secondary School, situated at Tugela Ferry, 3010, operates as a public secondary school within the Esidakeni suburb of KwaZulu-Natal Province, South Africa.">
<title>Mabaso Secondary School | Esidakeni | Details & Matric Results</title>
<link rel="stylesheet" href="css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root {
--navy: #0f172a;
--navy-2: #1e293b;
--teal: #0d9488;
--teal-light: #2dd4bf;
--green: #059669;
--ink: #102033;
--muted: #64748b;
--surface: #ffffff;
--soft: #f0fdfa;
--line: #e2e8f0;
--gold: #f59e0b;
--gold-light: #fbbf24;
}
* {
box-sizing: border-box;
}
html {
scroll-behavior: smooth;
}
body {
margin: 0;
font-family: 'Inter', sans-serif;
color: var(--ink);
background: #ffffff;
line-height: 1.6;
overflow-x: hidden;
}
a {
text-decoration: none;
}
.site-header {
position: fixed;
inset: 0 0 auto 0;
z-index: 1000;
background: rgba(15, 23, 42, 0.95);
backdrop-filter: blur(18px);
-webkit-backdrop-filter: blur(18px);
border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.nav {
max-width: 1180px;
margin: auto;
min-height: 76px;
padding: 0 24px;
display: flex;
align-items: center;
justify-content: space-between;
gap: 24px;
}
.brand {
display: flex;
align-items: center;
gap: 11px;
color: #ffffff;
font-weight: 800;
letter-spacing: -0.5px;
font-size: 20px;
white-space: nowrap;
}
.brand-mark {
width: 40px;
height: 40px;
display: grid;
place-items: center;
border-radius: 12px;
background: linear-gradient(135deg, var(--teal-light), var(--green));
box-shadow: 0 8px 24px rgba(13, 148, 136, 0.28);
font-size: 20px;
}
.brand em {
color: var(--teal-light);
font-style: normal;
font-weight: 400;
}
.nav-links {
display: flex;
align-items: center;
gap: 8px;
flex-wrap: wrap;
}
.nav-links a {
color: rgba(255, 255, 255, 0.76);
padding: 10px 14px;
border-radius: 10px;
font-size: 14px;
font-weight: 500;
transition: 0.25s ease;
}
.nav-links a:hover {
color: #ffffff;
background: rgba(45, 212, 191, 0.12);
}
.nav-login {
border: 1px solid rgba(45, 212, 191, 0.35);
color: #ffffff !important;
}
.nav-cta {
background: linear-gradient(135deg, var(--teal), var(--green));
color: #ffffff !important;
box-shadow: 0 8px 20px rgba(13, 148, 136, 0.22);
}
.nav-toggle {
display: none;
border: 0;
background: rgba(255, 255, 255, 0.08);
color: #ffffff;
font-size: 22px;
border-radius: 10px;
padding: 8px 12px;
cursor: pointer;
}
.hero {
min-height: 760px;
padding: 150px 24px 90px;
position: relative;
overflow: hidden;
background:
radial-gradient(circle at 80% 18%, rgba(45, 212, 191, 0.18), transparent 30%),
radial-gradient(circle at 10% 70%, rgba(5, 150, 105, 0.13), transparent 28%),
linear-gradient(135deg, #07111f 0%, #0f172a 48%, #123f4a 100%);
color: #ffffff;
}
.hero::before {
content: "";
position: absolute;
width: 500px;
height: 500px;
right: -180px;
top: 120px;
border: 1px solid rgba(45, 212, 191, 0.16);
border-radius: 50%;
box-shadow: 0 0 0 70px rgba(45, 212, 191, 0.025), 0 0 0 140px rgba(45, 212, 191, 0.02);
}
.hero-inner {
max-width: 1180px;
margin: auto;
display: grid;
grid-template-columns: 1.08fr 0.92fr;
gap: 70px;
align-items: center;
position: relative;
z-index: 1;
}
.eyebrow {
display: inline-flex;
align-items: center;
gap: 8px;
padding: 7px 13px;
border: 1px solid rgba(45, 212, 191, 0.28);
background: rgba(45, 212, 191, 0.08);
color: #99f6e4;
border-radius: 999px;
font-size: 12px;
font-weight: 700;
letter-spacing: 0.5px;
text-transform: uppercase;
}
.eyebrow::before {
content: "";
width: 7px;
height: 7px;
border-radius: 50%;
background: #2dd4bf;
box-shadow: 0 0 12px #2dd4bf;
}
.hero h1 {
font-size: clamp(44px, 6vw, 76px);
line-height: 1.02;
letter-spacing: -3px;
margin: 20px 0;
max-width: 760px;
}
.hero h1 span {
color: var(--teal-light);
}
.hero-copy {
max-width: 650px;
color: rgba(255, 255, 255, 0.72);
font-size: 18px;
line-height: 1.8;
margin-bottom: 32px;
}
.hero-actions {
display: flex;
gap: 12px;
flex-wrap: wrap;
}
.btn {
display: inline-flex;
align-items: center;
justify-content: center;
gap: 9px;
min-height: 50px;
padding: 0 21px;
border-radius: 12px;
font-size: 14px;
font-weight: 700;
transition: 0.25s ease;
}
.btn-primary {
background: linear-gradient(135deg, #2dd4bf, #059669);
color: #ffffff;
box-shadow: 0 14px 32px rgba(13, 148, 136, 0.28);
}
.btn-primary:hover {
transform: translateY(-3px);
box-shadow: 0 18px 40px rgba(13, 148, 136, 0.38);
}
.btn-ghost {
color: #ffffff;
border: 1px solid rgba(255, 255, 255, 0.18);
background: rgba(255, 255, 255, 0.05);
}
.btn-ghost:hover {
background: rgba(255, 255, 255, 0.10);
transform: translateY(-2px);
}
.hero-panel {
position: relative;
padding: 24px;
border-radius: 28px;
background: rgba(255, 255, 255, 0.075);
border: 1px solid rgba(255, 255, 255, 0.14);
backdrop-filter: blur(18px);
-webkit-backdrop-filter: blur(18px);
box-shadow: 0 30px 80px rgba(0, 0, 0, 0.28), inset 0 1px 0 rgba(255, 255, 255, 0.08);
}
.hero-panel::before {
content: "";
position: absolute;
width: 180px;
height: 180px;
top: -70px;
right: -60px;
background: rgba(45, 212, 191, 0.18);
filter: blur(70px);
border-radius: 50%;
pointer-events: none;
}
.dashboard-image-wrapper {
position: relative;
z-index: 1;
width: 100%;
overflow: hidden;
border-radius: 20px;
background: #f8fafc;
box-shadow: 0 25px 60px rgba(0, 0, 0, 0.28), 0 0 0 1px rgba(255, 255, 255, 0.15);
transition: transform 0.35s ease, box-shadow 0.35s ease;
}
.dashboard-image-wrapper:hover {
transform: translateY(-6px);
box-shadow: 0 35px 80px rgba(0, 0, 0, 0.32), 0 0 0 1px rgba(45, 212, 191, 0.20);
}
.dashboard-image {
display: block;
width: 100%;
height: auto;
max-width: 100%;
object-fit: cover;
}
.dashboard-label {
position: absolute;
left: 18px;
bottom: 18px;
z-index: 3;
display: inline-flex;
align-items: center;
gap: 8px;
padding: 9px 13px;
border-radius: 999px;
background: rgba(15, 23, 42, 0.86);
border: 1px solid rgba(255, 255, 255, 0.12);
color: #ffffff;
font-size: 11px;
font-weight: 700;
backdrop-filter: blur(12px);
-webkit-backdrop-filter: blur(12px);
box-shadow: 0 10px 25px rgba(0, 0, 0, 0.20);
}
.dashboard-label::before {
content: "";
width: 7px;
height: 7px;
border-radius: 50%;
background: #2dd4bf;
box-shadow: 0 0 10px #2dd4bf;
}
.section {
padding: 90px 24px;
}
.section-inner {
max-width: 1180px;
margin: auto;
}
.section-heading {
max-width: 680px;
margin-bottom: 42px;
}
.section-kicker {
color: var(--teal);
text-transform: uppercase;
letter-spacing: 1.2px;
font-size: 12px;
font-weight: 800;
}
.section h2 {
margin: 10px 0 12px;
font-size: clamp(30px, 4vw, 46px);
line-height: 1.12;
letter-spacing: -1.7px;
color: var(--navy);
}
.section-heading p {
color: var(--muted);
font-size: 16px;
}
.bg-soft {
background: #f8fafc;
}
.info-grid {
display: grid;
grid-template-columns: 1fr 1fr;
gap: 40px;
}
.info-card {
background: #ffffff;
border: 1px solid var(--line);
border-radius: 20px;
padding: 30px;
box-shadow: 0 10px 35px rgba(15, 23, 42, 0.05);
transition: 0.25s ease;
}
.info-card:hover {
transform: translateY(-4px);
box-shadow: 0 20px 45px rgba(15, 23, 42, 0.09);
}
.info-card .info-icon {
font-size: 28px;
margin-bottom: 12px;
}
.info-card h3 {
margin: 0 0 10px;
font-size: 18px;
color: var(--navy);
}
.info-card p {
color: var(--muted);
font-size: 14px;
line-height: 1.7;
margin: 0 0 12px;
}
.info-card ul {
margin: 0;
padding: 0;
list-style: none;
}
.info-card ul li {
padding: 6px 0;
border-bottom: 1px solid var(--line);
font-size: 13px;
display: flex;
justify-content: space-between;
color: var(--muted);
}
.info-card ul li:last-child {
border-bottom: none;
}
.info-card ul li strong {
color: var(--navy);
}
.stats-grid {
display: grid;
grid-template-columns: repeat(4, 1fr);
gap: 20px;
margin: 30px 0;
}
.stat-item {
background: #ffffff;
border: 1px solid var(--line);
border-radius: 16px;
padding: 25px;
text-align: center;
transition: 0.25s ease;
}
.stat-item:hover {
transform: translateY(-4px);
box-shadow: 0 15px 35px rgba(15, 23, 42, 0.08);
}
.stat-item .stat-number {
font-size: 36px;
font-weight: 800;
color: var(--teal);
}
.stat-item .stat-label {
color: var(--muted);
font-size: 13px;
margin-top: 5px;
}
.results-grid {
display: grid;
grid-template-columns: repeat(4, 1fr);
gap: 15px;
margin-top: 25px;
}
.result-item {
background: #ffffff;
border: 1px solid var(--line);
border-radius: 14px;
padding: 18px 20px;
text-align: center;
transition: 0.25s ease;
}
.result-item:hover {
transform: translateY(-4px);
box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
border-color: var(--teal);
}
.result-item .year {
font-size: 18px;
font-weight: 800;
color: var(--navy);
}
.result-item .rate {
font-size: 24px;
font-weight: 700;
color: var(--teal);
margin: 5px 0;
}
.result-item .status {
font-size: 12px;
color: var(--muted);
}
.result-item .status.pass {
color: #059669;
}
.result-item .status.fail {
color: #ef4444;
}
.contact-grid {
display: grid;
grid-template-columns: 1fr 1fr;
gap: 30px;
}
.contact-card {
background: #ffffff;
border: 1px solid var(--line);
border-radius: 18px;
padding: 28px;
}
.contact-card h4 {
margin: 0 0 15px;
font-size: 16px;
color: var(--navy);
}
.contact-card .contact-item {
display: flex;
align-items: center;
gap: 12px;
padding: 8px 0;
border-bottom: 1px solid var(--line);
font-size: 14px;
color: var(--muted);
}
.contact-card .contact-item:last-child {
border-bottom: none;
}
.contact-card .contact-item .icon {
font-size: 18px;
width: 30px;
}
.cta {
padding: 75px 24px;
background: linear-gradient(135deg, #0f172a, #0f3b42);
color: #ffffff;
}
.cta-inner {
max-width: 900px;
margin: auto;
text-align: center;
}
.cta h2 {
color: #ffffff;
margin-bottom: 14px;
}
.cta p {
color: rgba(255, 255, 255, 0.7);
max-width: 600px;
margin: 0 auto 28px;
}
.footer {
background: #07111f;
color: rgba(255, 255, 255, 0.6);
padding: 26px 24px;
}
.footer-inner {
max-width: 1180px;
margin: auto;
display: flex;
justify-content: space-between;
align-items: center;
gap: 15px;
font-size: 12px;
flex-wrap: wrap;
}
.footer strong {
color: #ffffff;
}
.footer-links {
display: flex;
gap: 15px;
}
.footer-links a {
color: rgba(255, 255, 255, 0.5);
transition: 0.25s ease;
}
.footer-links a:hover {
color: #ffffff;
}
@media (max-width: 1024px) {
.info-grid {
grid-template-columns: 1fr;
}
.results-grid {
grid-template-columns: repeat(2, 1fr);
}
.stats-grid {
grid-template-columns: repeat(2, 1fr);
}
.hero-inner {
gap: 45px;
}
}
@media (max-width: 900px) {
.hero-inner {
grid-template-columns: 1fr;
gap: 45px;
}
.hero {
min-height: auto;
}
.hero-panel {
width: 100%;
max-width: 700px;
margin: 0 auto;
}
.nav-links {
display: none;
position: absolute;
top: 76px;
left: 0;
right: 0;
padding: 12px 24px 18px;
background: #0f172a;
flex-direction: column;
align-items: stretch;
border-bottom: 1px solid rgba(255,255,255,0.08);
}
.nav-links.open {
display: flex;
}
.nav-links a {
width: 100%;
}
.nav-toggle {
display: block;
}
.contact-grid {
grid-template-columns: 1fr;
}
}
@media (max-width: 600px) {
.hero {
padding: 125px 18px 65px;
}
.section {
padding: 65px 18px;
}
.hero h1 {
letter-spacing: -2px;
}
.hero-copy {
font-size: 16px;
}
.stats-grid {
grid-template-columns: 1fr;
}
.results-grid {
grid-template-columns: 1fr;
}
.footer-inner {
flex-direction: column;
text-align: center;
}
.brand {
font-size: 18px;
}
.hero-panel {
padding: 12px;
border-radius: 20px;
}
.dashboard-image-wrapper {
border-radius: 14px;
}
.dashboard-label {
left: 12px;
bottom: 12px;
font-size: 10px;
padding: 7px 10px;
}
}
</style>
</head>
<body>
<header class="site-header">
<nav class="nav">
<a href="#top" class="brand">
<span class="brand-mark">🏫</span>
<span>Mabaso <em>Secondary</em></span>
</a>
<button class="nav-toggle" onclick="toggleNav()" aria-label="Open menu" type="button">☰</button>
<div class="nav-links" id="navLinks">
<a href="#about">About</a>
<a href="#school-info">School Info</a>
<a href="#matric-results">Matric Results</a>
<a href="#contact">Contact</a>
<a href="pages/signin.php" class="nav-login">Log in</a>
<a href="pages/register.php" class="nav-cta">Teacher registration</a>
</div>
</nav>
</header>
<main id="top">
<section class="hero">
<div class="hero-inner">
<div>
<div class="eyebrow">School of Excellence</div>
<h1>Learning, teaching and progress — <span>in one place.</span></h1>
<p class="hero-copy">Mabaso Secondary's digital school platform gives teachers the tools to manage students, lessons, attendance, assignments and results, while students get a clear space to learn, submit work and track their progress.</p>
<div class="hero-actions">
<a class="btn btn-primary" href="pages/signin.php">🚀 Access the portal</a>
<a class="btn btn-ghost" href="#about">Learn more ↓</a>
</div>
</div>
<div class="hero-panel" aria-label="Mabaso Secondary School dashboard preview">
<div class="dashboard-image-wrapper">
<img src="images/dashboard-preview.png" alt="Mabaso Secondary School digital dashboard" class="dashboard-image">
<div class="dashboard-label">Mabaso School Portal</div>
</div>
</div>
</div>
</section>
<section class="section" id="about">
<div class="section-inner">
<div class="section-heading">
<div class="section-kicker">About Mabaso Secondary</div>
<h2>Public Secondary School in Esidakeni, KwaZulu-Natal</h2>
<p>Mabaso Secondary School, situated at Tugela Ferry, 3010, operates as a public secondary school within the Esidakeni suburb of KwaZulu-Natal Province, South Africa.</p>
</div>
<div class="info-grid">
<div class="info-card">
<div class="info-icon">📋</div>
<h3>Admission & Registration</h3>
<p>The admission to public schools like Mabaso Secondary School follows a structured process outlined by the South African Schools Act of 1996. As stipulated, children between the ages of 7 and 15 are mandated to attend school.</p>
<p>Registration for the upcoming academic year typically occurs between April and October of the preceding year, with specific age criteria for entry into Grade R and Grade 1. Parents or guardians are required to submit essential documents, including birth certificates and immunization cards, while non-South African citizens must provide additional documentation.</p>
<p><small style="color: var(--muted);">Provincial Learner Admissions Coordinators are available to provide guidance and support throughout the admissions process, ensuring that every child has access to quality education.</small></p>
</div>
<div class="info-card">
<div class="info-icon">💰</div>
<h3>School Fees</h3>
<p>Mabaso Secondary School is a public school designated as <strong>Quintile 1</strong> as a <strong>no-fee institution</strong>. There are <strong>no tuition fees</strong> for students attending this school.</p>
<p>This quintile level school is eligible to receive up to <strong>R1,602 per learner</strong> from the Department of Education to cover operational expenses and educational resources.</p>
</div>
<div class="info-card">
<div class="info-icon">👔</div>
<h3>School Uniform</h3>
<p>Mabaso Secondary School uniform policies are established by the school's governing body, aligned with the regulations outlined in the South African Schools Act of 1996 and provincial Education Department guidelines.</p>
<p>These policies dictate the design, colors, and proper wearing of school uniforms to cultivate a sense of unity, equality, and discipline among students.</p>
<p><small style="color: var(--muted);">For detailed information regarding Mabaso Secondary School's uniform policy, individuals are encouraged to contact the school administration directly.</small></p>
</div>
<div class="info-card">
<div class="info-icon">📊</div>
<h3>School Statistics</h3>
<p>According to a survey conducted in the year <strong>2023</strong>, the school had a total population of:</p>
<ul>
<li><strong>Total Learners:</strong> <span>1,372</span></li>
<li><strong>Dedicated Educators:</strong> <span>34</span></li>
<li><strong>Student-Teacher Ratio:</strong> <span>40:1</span></li>
</ul>
<div style="margin-top: 15px; padding: 12px; background: #f0fdfa; border-radius: 10px; border-left: 3px solid var(--teal);">
<strong style="color: var(--teal);">2023 Principal:</strong> Mchunu
</div>
</div>
</div>
</div>
</section>
<section class="section bg-soft" id="school-info">
<div class="section-inner">
<div class="section-heading">
<div class="section-kicker">School Statistics</div>
<h2>Mabaso Secondary at a Glance</h2>
<p>Key information about the school's demographics and academic standing.</p>
</div>
<div class="stats-grid">
<div class="stat-item">
<div class="stat-number">1,372</div>
<div class="stat-label">Total Learners (2023)</div>
</div>
<div class="stat-item">
<div class="stat-number">34</div>
<div class="stat-label">Dedicated Educators</div>
</div>
<div class="stat-item">
<div class="stat-number">40:1</div>
<div class="stat-label">Student-Teacher Ratio</div>
</div>
<div class="stat-item">
<div class="stat-number">Quintile 1</div>
<div class="stat-label">No-Fee School</div>
</div>
</div>
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
<div style="background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 24px;">
<h4 style="margin: 0 0 12px; color: var(--navy);">School Information</h4>
<div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--line); font-size: 14px;">
<span style="color: var(--muted);">NatEmis</span>
<span style="font-weight: 600;">500415029</span>
</div>
<div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--line); font-size: 14px;">
<span style="color: var(--muted);">Quintile</span>
<span style="font-weight: 600;">1</span>
</div>
<div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--line); font-size: 14px;">
<span style="color: var(--muted);">Exam Dept</span>
<span style="font-weight: 600;">To Be Updated</span>
</div>
<div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--line); font-size: 14px;">
<span style="color: var(--muted);">Specialisation</span>
<span style="font-weight: 600;">Ordinary School</span>
</div>
<div style="display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px;">
<span style="color: var(--muted);">Postal Address</span>
<span style="font-weight: 600; text-align: right;">P.O.Box 206, Tugelaferry, 3010</span>
</div>
</div>
<div style="background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 24px;">
<h4 style="margin: 0 0 12px; color: var(--navy);">Classification</h4>
<div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--line); font-size: 14px;">
<span style="color: var(--muted);">Phase</span>
<span style="font-weight: 600;">Secondary Schools</span>
</div>
<div style="display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px;">
<span style="color: var(--muted);">Sector</span>
<span style="font-weight: 600;">Public</span>
</div>
<div style="margin-top: 15px; padding: 12px; background: #f0fdfa; border-radius: 10px; border-left: 3px solid var(--teal);">
<strong style="color: var(--teal);">Location:</strong> KwaZulu-Natal Suburb, KwaZulu-Natal Province
</div>
</div>
</div>
</div>
</section>
<section class="section" id="matric-results">
<div class="section-inner">
<div class="section-heading">
<div class="section-kicker">Matric Performance</div>
<h2>Mabaso Secondary Matric Results</h2>
<p>As we look forward to the release of the 2025 matric results, we present detailed performance reports from 2017 to 2024, including the matric pass rate.</p>
<p style="font-size: 14px; color: #94a3b8; margin-top: 10px;">⚠️ Kindly note that these exam results are unofficial. For the official results, please visit <a href="https://www.education.gov.za" target="_blank" rel="noopener noreferrer" style="color: var(--teal);">www.education.gov.za</a>. This platform is not responsible for any discrepancies. To ensure accuracy, we encourage you to verify the results on the official website.</p>
</div>
<div class="results-grid">
<div class="result-item">
<div class="year">2026</div>
<div class="rate">Pending</div>
<div class="status">Awaiting Release</div>
</div>
<div class="result-item">
<div class="year">2025</div>
<div class="rate">74.4%</div>
<div class="status pass">✅ Passed</div>
</div>
<div class="result-item">
<div class="year">2024</div>
<div class="rate">82.4%</div>
<div class="status pass">✅ Passed</div>
</div>
<div class="result-item">
<div class="year">2023</div>
<div class="rate">79.1%</div>
<div class="status pass">✅ Passed</div>
</div>
<div class="result-item">
<div class="year">2022</div>
<div class="rate">76.8%</div>
<div class="status pass">✅ Passed</div>
</div>
<div class="result-item">
<div class="year">2021</div>
<div class="rate">74.2%</div>
<div class="status pass">✅ Passed</div>
</div>
<div class="result-item">
<div class="year">2020</div>
<div class="rate">71.5%</div>
<div class="status pass">✅ Passed</div>
</div>
<div class="result-item">
<div class="year">2019</div>
<div class="rate">82.4%</div>
<div class="status pass">✅ Passed</div>
</div>
<div class="result-item">
<div class="year">2018</div>
<div class="rate">68.9%</div>
<div class="status pass">✅ Passed</div>
</div>
<div class="result-item">
<div class="year">2017</div>
<div class="rate">65.3%</div>
<div class="status pass">✅ Passed</div>
</div>
</div>
<div style="text-align: center; margin-top: 30px;">
<a href="#" style="color: var(--teal); font-weight: 600; font-size: 14px; border: 1px solid var(--teal); padding: 10px 24px; border-radius: 10px; transition: 0.25s ease; display: inline-block;">📊 Suggest Updates</a>
</div>
</div>
</section>
<section class="section bg-soft" id="contact">
<div class="section-inner">
<div class="section-heading">
<div class="section-kicker">Contact & Location</div>
<h2>Get in Touch with Mabaso Secondary</h2>
<p>Reach out to the school administration for more information.</p>
</div>
<div class="contact-grid">
<div class="contact-card">
<h4>📍 School Address</h4>
<div class="contact-item">
<span class="icon">📍</span>
<span>Tugela Ferry, 3010, KwaZulu-Natal</span>
</div>
<div class="contact-item">
<span class="icon">📞</span>
<span>(+27) 72 824 6541</span>
</div>
<div class="contact-item">
<span class="icon">✉️</span>
<span>mabasohigh@gmail.com</span>
</div>
<div class="contact-item">
<span class="icon">🏫</span>
<span>Postal: P.O.Box 206, Tugelaferry, 3010</span>
</div>
</div>
<div class="contact-card">
<h4>📋 Quick Links</h4>
<div class="contact-item">
<span class="icon">📊</span>
<a href="#matric-results" style="color: var(--teal);">Matric Results</a>
</div>
<div class="contact-item">
<span class="icon">📋</span>
<a href="#about" style="color: var(--teal);">Admission Information</a>
</div>
<div class="contact-item">
<span class="icon">👔</span>
<a href="#about" style="color: var(--teal);">School Uniform Policy</a>
</div>
<div class="contact-item">
<span class="icon">📚</span>
<a href="pages/signin.php" style="color: var(--teal);">Student Portal Login</a>
</div>
<div class="contact-item">
<span class="icon">👨‍🏫</span>
<a href="pages/register.php" style="color: var(--teal);">Teacher Registration</a>
</div>
</div>
</div>
</div>
</section>
<section class="cta">
<div class="cta-inner">
<div class="section-kicker" style="color:#5eead4;">Ready to get started?</div>
<h2>Step into the Mabaso Secondary portal.</h2>
<p>Teachers can register for an account, while students can sign in using the account created for them by their teacher.</p>
<div class="hero-actions" style="justify-content:center;">
<a class="btn btn-primary" href="pages/signin.php">Log in to the portal</a>
<a class="btn btn-ghost" href="pages/register.php">Register as a teacher</a>
</div>
</div>
</section>
</main>
<footer class="footer">
<div class="footer-inner">
<span><strong>Mabaso Secondary School</strong> · Esidakeni, KwaZulu-Natal</span>
<span>© <?php echo date('Y'); ?> Mabaso Secondary School. All rights reserved. Created by Ma Dural</span>
</div>
</footer>
<script>
function toggleNav() {
const nav = document.getElementById('navLinks');
nav.classList.toggle('open');
}
document.querySelectorAll('.nav-links a').forEach(link => {
link.addEventListener('click', () => {
document.getElementById('navLinks').classList.remove('open');
});
});
document.addEventListener('click', function(event) {
const nav = document.getElementById('navLinks');
const toggle = document.querySelector('.nav-toggle');
if (nav.classList.contains('open') && !nav.contains(event.target) && !toggle.contains(event.target)) {
nav.classList.remove('open');
}
});
</script>
</body>
</html>