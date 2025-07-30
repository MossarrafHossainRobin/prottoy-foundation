<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prottoy Foundation - Financial Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root {
            --prottoy-primary: #3498db;
            --prottoy-secondary: #f4f7f9;
            --prottoy-dark: #2c3e50;
            --prottoy-light: #ffffff;
            --prottoy-accent: #2ecc71;
            --prottoy-success: #2ecc71;
            --prottoy-text-muted: #7f8c8d;
        }

        body {
            font-family: 'Lato', sans-serif;
            background-color: var(--prottoy-secondary);
            color: var(--prottoy-dark);
            padding-top: 80px; /* Increased for navbar */
            margin: 0;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }

        #root {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin: 0 auto;
            max-width: 1400px;
            padding: 2rem;
            background-color: var(--prottoy-light);
        }

        .navbar {
            background-color: var(--prottoy-dark) !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            z-index: 1030 !important;
            padding: 0.5rem 1rem;
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.8rem;
            color: var(--prottoy-primary) !important;
            transition: color 0.3s;
        }

        .navbar-brand:hover {
            color: var(--prottoy-accent) !important;
        }

        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 600;
            margin: 0 0.5rem;
            padding: 0.6rem 1.2rem;
            border-radius: 0.4rem;
            transition: all 0.3s;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: var(--prottoy-primary) !important;
            background-color: rgba(255, 255, 255, 0.15);
        }

        .dropdown-menu {
            background-color: var(--prottoy-dark);
            border: none;
            box-shadow: 0 6px 12px rgba(0,0,0,0.3);
            border-radius: 0.4rem;
        }

        .dropdown-item {
            color: rgba(255, 255, 255, 0.9) !important;
            padding: 0.5rem 1.5rem;
        }

        .dropdown-item:hover {
            background-color: rgba(255, 255, 255, 0.15) !important;
            color: var(--prottoy-primary) !important;
        }

        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }

        .card-header {
            border-bottom: none;
            color: var(--prottoy-light);
            font-size: 1.5rem;
            padding: 1.5rem;
            border-radius: 1rem 1rem 0 0 !important;
        }

        .card-header.bg-primary {
            background: var(--prottoy-primary) !important;
        }

        .card-header.bg-success {
            background: var(--prottoy-success) !important;
        }

        .form-control {
            border-radius: 0.5rem;
            border-color: rgba(0,0,0,0.15);
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .form-control:focus {
            border-color: var(--prottoy-primary);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }

        .btn-primary {
            background-color: var(--prottoy-primary);
            border-color: var(--prottoy-primary);
            border-radius: 0.5rem;
            padding: 0.75rem 1.5rem;
            transition: background-color 0.3s, border-color 0.3s;
        }

        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }

        .btn-success {
            background-color: var(--prottoy-success);
            border-color: var(--prottoy-success);
            border-radius: 0.5rem;
            padding: 0.75rem 1.5rem;
            transition: background-color 0.3s, border-color 0.3s;
        }

        .btn-success:hover {
            background-color: #27ae60;
            border-color: #27ae60;
        }

        .table {
            background-color: var(--prottoy-light);
            border-radius: 0.8rem;
            overflow: hidden;
        }

        .table thead th {
            background-color: var(--prottoy-dark);
            color: var(--prottoy-light);
            font-weight: 600;
            padding: 1.2rem;
        }

        .table tbody tr:hover {
            background-color: rgba(52, 152, 219, 0.1);
        }

        .footer {
            background-color: var(--prottoy-dark) !important;
            color: rgba(255, 255, 255, 0.7) !important;
            padding: 2rem 0;
        }

        .chart-container {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
        }

        .bar {
            fill: var(--prottoy-primary);
            transition: fill 0.3s;
        }

        .bar:hover {
            fill: var(--prottoy-accent);
        }

        .axis path,
        .axis line {
            stroke: var(--prottoy-dark);
        }

        .axis text {
            font-family: 'Lato', sans-serif;
            font-size: 0.9rem;
            fill: var(--prottoy-dark);
        }

        .modal-content {
            border-radius: 1rem;
            overflow: hidden;
        }

        .modal-header.bg-primary {
            background: var(--prottoy-primary) !important;
        }

        .alert {
            border-radius: 0.5rem;
        }

        @media (max-width: 768px) {
            #root {
                padding: 1rem;
            }
            .navbar-brand {
                font-size: 1.5rem;
            }
            .navbar-nav .nav-link {
                padding: 0.5rem 1rem;
            }
            .card-body {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div id="root"></div>
    <script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    <script src="https://unpkg.com/@babel/standalone@7/babel.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <script type="text/babel" src="app.js"></script>
</body>
</html>