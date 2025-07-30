<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prottoy Foundation - Empowering Communities with Heart</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Playfair Display (for headings, elegant) and Poppins (for body, legible) -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">

    <!-- React and Babel for in-browser JSX compilation -->
    <script src="https://unpkg.com/react@18/umd/react.development.js" crossorigin></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.development.js" crossorigin></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <style>
        /* Custom CSS for Heartfelt Design */
        :root {
            --primary-blue: #1a5276; /* Deep, calming blue */
            --secondary-green: #28a745; /* Vibrant green for growth/hope */
            --accent-gold: #ffc107; /* Warm gold for highlights */
            --light-cream: #fdfcfb; /* Soft, warm background */
            --soft-peach: #fff5ee; /* Gentle accent color */
            --dark-text: #343a40; /* Dark grey for readability */
            --light-text: #e9ecef; /* Light grey for subtle text */
            --border-color: #dee2e6; /* Light border */
            --hero-gradient-start: #e0f2f7; /* Light blue */
            --hero-gradient-end: #e8f5e9; /* Light green */
            --section-bg-light: #fefefe; /* Very light background for sections */
            --footer-bg: #212529; /* Darker, grounding */
        }

        body {
            font-family: 'Poppins', sans-serif;
            color: var(--dark-text);
            background-color: var(--light-cream); /* Softer overall background */
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow-x: hidden; /* Prevent horizontal scroll */
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Playfair Display', serif;
            color: var(--primary-blue);
            font-weight: 700;
        }

        p {
            line-height: 1.7; /* Improve readability */
        }

        /* Navbar */
        .navbar {
            background-color: var(--primary-blue);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
        }
        .navbar-brand {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            font-size: 1.8rem;
            color: white !important;
            display: flex;
            align-items: center;
        }
        .navbar-brand .logo-svg {
            height: 45px;
            width: auto;
            margin-right: 10px;
            transition: transform 0.3s ease, filter 0.3s ease;
        }
        .navbar-brand .logo-svg:hover {
            transform: scale(1.05);
            filter: drop-shadow(0 0 8px var(--accent-gold));
        }
        .navbar-nav .nav-link {
            color: white !important;
            font-weight: 500;
            margin-left: 1.5rem;
            transition: color 0.3s ease, transform 0.3s ease;
            position: relative;
        }
        .navbar-nav .nav-link::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 3px;
            background-color: var(--accent-gold);
            transition: width 0.3s ease;
        }
        .navbar-nav .nav-link:hover::after,
        .navbar-nav .nav-link.active::after {
            width: 100%;
        }
        .navbar-nav .nav-link:hover {
            color: var(--accent-gold) !important;
            transform: translateY(-2px);
        }
        .btn-outline-light-custom {
            color: white;
            border-color: white;
            padding: 0.5rem 1.2rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .btn-outline-light-custom:hover {
            background-color: white;
            color: var(--primary-blue);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.25);
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--hero-gradient-start), var(--hero-gradient-end));
            padding: 6rem 0;
            text-align: center;
            color: var(--primary-blue);
            position: relative;
            overflow: hidden;
            border-bottom-left-radius: 80px; /* Soft curve */
            border-bottom-right-radius: 80px;
            margin-bottom: 3rem;
        }
        .hero-section::before, .hero-section::after {
            content: '';
            position: absolute;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            pointer-events: none;
        }
        .hero-section::before {
            top: -50px;
            left: -50px;
            width: 200px;
            height: 200px;
            animation: floatBubble 10s infinite ease-in-out;
        }
        .hero-section::after {
            bottom: -50px;
            right: -50px;
            width: 150px;
            height: 150px;
            animation: floatBubble 12s infinite reverse ease-in-out;
        }
        @keyframes floatBubble {
            0% { transform: translate(0, 0) scale(0.9); opacity: 0.8; }
            50% { transform: translate(20px, 20px) scale(1.1); opacity: 1; }
            100% { transform: translate(0, 0) scale(0.9); opacity: 0.8; }
        }

        .hero-section h1 {
            font-size: 3.8rem; /* Slightly larger */
            margin-bottom: 1.5rem;
            color: var(--primary-blue);
            text-shadow: 3px 3px 8px rgba(0,0,0,0.08); /* Softer, more pronounced shadow */
        }
        .hero-section p {
            font-size: 1.35rem; /* Slightly larger */
            max-width: 850px;
            margin: 0 auto 2.5rem;
            color: var(--dark-text);
        }
        .hero-section .btn-hero {
            background-color: var(--secondary-green);
            border-color: var(--secondary-green);
            color: white;
            font-weight: 600;
            padding: 1rem 3rem; /* Larger padding */
            border-radius: 50px;
            font-size: 1.2rem; /* Larger font */
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.4); /* More prominent shadow */
        }
        .btn-hero:hover {
            background-color: #218838;
            border-color: #218838;
            transform: translateY(-5px) scale(1.02); /* More dynamic hover */
            box-shadow: 0 12px 25px rgba(40, 167, 69, 0.5);
        }

        /* Content Sections */
        .section-padding {
            padding: 5rem 0;
            background-color: var(--section-bg-light); /* Soft background */
            margin-bottom: 3rem;
            border-radius: 20px; /* Soft corners for sections */
            box-shadow: 0 5px 20px rgba(0,0,0,0.05); /* Subtle shadow */
        }
        .section-title {
            text-align: center;
            margin-bottom: 3.5rem; /* More space */
            font-size: 3rem; /* Larger */
            color: var(--primary-blue);
            position: relative;
            padding-bottom: 15px;
        }
        .section-title::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: 0;
            transform: translateX(-50%);
            width: 80px;
            height: 5px;
            background-color: var(--accent-gold);
            border-radius: 5px;
        }

        .card-custom {
            border: none;
            border-radius: 20px; /* More rounded */
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); /* Softer, larger shadow */
            padding: 3rem; /* More padding */
            height: 100%;
            background-color: white;
            transition: transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94), box-shadow 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94); /* Smoother transition */
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .card-custom:hover {
            transform: translateY(-8px) scale(1.01); /* More pronounced lift */
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        }
        .card-custom .icon-large {
            font-size: 3.5rem; /* Larger icons */
            color: var(--secondary-green);
            margin-bottom: 1.8rem;
            animation: pulseIcon 2s infinite ease-in-out; /* Subtle pulse */
        }
        @keyframes pulseIcon {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .card-custom h3 {
            font-size: 2rem; /* Larger heading */
            margin-bottom: 1.2rem;
            color: var(--primary-blue);
        }
        .card-custom p {
            font-size: 1.05rem;
            color: var(--dark-text);
        }

        /* Registered Users Section (React Component Container) */
        .registered-users-section {
            background: linear-gradient(135deg, var(--primary-blue), #103a54); /* Deeper gradient */
            color: white;
            padding: 6rem 0; /* More padding */
            border-top-left-radius: 80px; /* Soft curve */
            border-top-right-radius: 80px;
            margin-top: 3rem;
            position: relative;
            overflow: hidden;
        }
        .registered-users-section::before, .registered-users-section::after {
            content: '';
            position: absolute;
            background: rgba(255, 255, 255, 0.08); /* Subtle white shapes */
            border-radius: 50%;
            pointer-events: none;
        }
        .registered-users-section::before {
            top: 10%;
            left: 5%;
            width: 100px;
            height: 100px;
            animation: floatBubble 15s infinite ease-in-out reverse;
        }
        .registered-users-section::after {
            bottom: 15%;
            right: 8%;
            width: 120px;
            height: 120px;
            animation: floatBubble 18s infinite ease-in-out;
        }

        .registered-users-section h2 {
            color: white;
            margin-bottom: 3.5rem;
            font-size: 3rem;
        }
        .registered-users-section p.lead {
            color: rgba(255, 255, 255, 0.8); /* Softer white text */
            font-size: 1.15rem;
        }
        .table-users {
            background-color: var(--light-cream); /* Creamy background for table */
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        }
        .table-users th {
            background-color: var(--accent-gold);
            color: var(--primary-blue);
            font-weight: 700;
            padding: 1.2rem; /* More padding */
            border-bottom: 3px solid var(--primary-blue); /* Thicker border */
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .table-users td {
            color: var(--dark-text);
            padding: 1rem 1.2rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }
        .table-users tbody tr:last-child td {
            border-bottom: none;
        }
        .table-users tbody tr:hover {
            background-color: var(--soft-peach); /* Gentle peach hover */
            cursor: pointer;
        }
        .table-users .spinner-border {
            color: var(--accent-gold); /* Gold spinner */
        }
        .table-users .badge {
            font-size: 0.9em;
            padding: 0.5em 0.8em;
            border-radius: 20px;
        }

        /* Footer */
        .footer {
            background-color: var(--footer-bg); /* Darker footer */
            color: var(--light-text);
            padding: 3.5rem 0;
            text-align: center;
            font-size: 0.95rem;
            margin-top: auto; /* Pushes footer to the bottom */
            border-top-left-radius: 40px; /* Soft curve */
            border-top-right-radius: 40px;
        }
        .footer a {
            color: var(--accent-gold);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .footer a:hover {
            color: white;
            text-decoration: underline;
        }
        .footer .social-icons a {
            font-size: 1.8rem; /* Larger icons */
            margin: 0 1rem;
            color: var(--light-text);
            transition: color 0.3s ease, transform 0.3s ease;
        }
        .footer .social-icons a:hover {
            color: var(--accent-gold);
            transform: translateY(-5px) scale(1.1); /* More dynamic hover */
        }

        /* Responsive Adjustments */
        @media (max-width: 991.98px) {
            .navbar-collapse {
                background-color: var(--primary-blue);
                padding: 1rem;
                border-radius: 0 0 10px 10px;
            }
            .navbar-nav .nav-link {
                margin-left: 0;
                padding: 0.75rem 0;
            }
            .navbar-nav .nav-link::after {
                height: 2px;
                bottom: 0;
            }
            .hero-section {
                padding: 4rem 0;
                border-bottom-left-radius: 40px;
                border-bottom-right-radius: 40px;
            }
            .hero-section h1 {
                font-size: 2.8rem;
            }
            .hero-section p {
                font-size: 1.1rem;
            }
            .section-padding {
                padding: 3rem 0;
                margin-bottom: 2rem;
                border-radius: 15px;
            }
            .section-title {
                font-size: 2.2rem;
                margin-bottom: 2.5rem;
            }
            .card-custom {
                margin-bottom: 1.5rem;
                padding: 2rem;
                border-radius: 15px;
            }
            .card-custom h3 {
                font-size: 1.7rem;
            }
            .registered-users-section {
                padding: 4rem 0;
                border-top-left-radius: 40px;
                border-top-right-radius: 40px;
                margin-top: 2rem;
            }
            .registered-users-section h2 {
                font-size: 2.2rem;
                margin-bottom: 2.5rem;
            }
            .registered-users-section p.lead {
                font-size: 1rem;
            }
            .table-users th, .table-users td {
                padding: 0.8rem 1rem;
                font-size: 0.95rem;
            }
            .footer {
                padding: 2.5rem 0;
                border-top-left-radius: 20px;
                border-top-right-radius: 20px;
            }
            .footer .social-icons a {
                font-size: 1.5rem;
                margin: 0 0.6rem;
            }
        }

        @media (max-width: 575.98px) {
            .hero-section {
                padding: 3rem 0;
                border-bottom-left-radius: 30px;
                border-bottom-right-radius: 30px;
            }
            .hero-section h1 {
                font-size: 2rem;
            }
            .hero-section p {
                font-size: 0.9rem;
            }
            .btn-hero {
                padding: 0.8rem 2rem;
                font-size: 1rem;
            }
            .section-padding {
                padding: 2rem 0;
                margin-bottom: 1.5rem;
            }
            .section-title {
                font-size: 1.8rem;
                margin-bottom: 2rem;
            }
            .card-custom {
                padding: 1.5rem;
                margin-bottom: 1rem;
            }
            .card-custom h3 {
                font-size: 1.5rem;
            }
            .card-custom .icon-large {
                font-size: 3rem;
                margin-bottom: 1rem;
            }
            .registered-users-section {
                padding: 3rem 0;
                border-top-left-radius: 30px;
                border-top-right-radius: 30px;
                margin-top: 1.5rem;
            }
            .registered-users-section h2 {
                font-size: 1.8rem;
                margin-bottom: 2rem;
            }
            .table-users th, .table-users td {
                padding: 0.6rem 0.8rem;
                font-size: 0.85rem;
            }
            .footer {
                padding: 2rem 0;
                border-top-left-radius: 15px;
                border-top-right-radius: 15px;
            }
            .footer .social-icons a {
                font-size: 1.3rem;
                margin: 0 0.5rem;
            }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <svg class="logo-svg" viewBox="0 0 100 80" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- Heart-like shape for the logo -->
                    <path d="M50 20C40 0, 20 0, 10 20C0 40, 20 60, 50 80C80 60, 100 40, 90 20C80 0, 60 0, 50 20Z" fill="#FFC107"/>
                    <text x="55" y="50" fill="white" font-family="'Poppins', sans-serif" font-size="25" font-weight="700">Prottoy</text>
                </svg>
                Prottoy Foundation
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Our Work</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Contact</a>
                    </li>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-outline-light-custom" href="login.php">Login</a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-outline-light-custom" href="register.php">Register</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1>Igniting Hope, Cultivating Change</h1>
            <p class="lead">Every act of kindness creates a ripple. At Prottoy Foundation, we're dedicated to fostering a community where compassion transforms lives and builds a brighter, more equitable future for all.</p>
            <a href="#registered-users-section" class="btn btn-hero">Join Our Heartfelt Community</a>
        </div>
    </section>

    <!-- Mission Section -->
    <section class="section-padding bg-white">
        <div class="container text-center">
            <h2 class="section-title">Our Guiding Principles</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card-custom">
                        <i class="bi bi-heart-fill icon-large"></i>
                        <h3>Deep Compassion</h3>
                        <p>Our work is rooted in profound empathy, ensuring every action reflects genuine care for those we serve.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card-custom">
                        <i class="bi bi-people-fill icon-large"></i>
                        <h3>Unified Community</h3>
                        <p>We believe in the strength of togetherness, building bridges and empowering collective growth.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card-custom">
                        <i class="bi bi-lightbulb-fill icon-large"></i>
                        <h3>Inspired Innovation</h3>
                        <p>We continuously seek creative, sustainable solutions that address challenges with foresight and impact.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Registered Users Section (React App Mount Point) -->
    <section class="registered-users-section" id="registered-users-section">
        <div class="container">
            <h2 class="section-title text-white">Our Valued Registered Community</h2>
            <p class="lead text-center text-white-50 mb-5">Meet the wonderful individuals who have joined the Prottoy Foundation family. Their commitment is the heartbeat of our mission, making them eligible to contribute and receive support.</p>
            <div id="root">
                <!-- React App will render here -->
                <div class="text-center text-white">
                    <div class="spinner-border text-light" role="status">
                        <span class="visually-hidden">Loading users...</span>
                    </div>
                    <p class="mt-2">Loading registered users...</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3 mb-md-0">
                    <h5>Prottoy Foundation</h5>
                    <p>Empowering lives, building futures through compassion and community.</p>
                </div>
                <div class="col-md-4 mb-3 mb-md-0">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Our Work</a></li>
                        <li><a href="#">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-3 mb-md-0">
                    <h5>Connect With Us</h5>
                    <div class="social-icons">
                        <a href="#" class="me-3"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="me-3"><i class="bi bi-twitter"></i></a>
                        <a href="#" class="me-3"><i class="bi bi-instagram"></i></a>
                        <a href="#"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>
            </div>
            <hr class="my-4" style="border-color: rgba(255,255,255,0.1);">
            <p class="mb-0">&copy; <?php echo date("Y"); ?> Prottoy Foundation. All rights reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- React Application Script -->
    <script type="text/babel">
        const { useState, useEffect } = React;

        function App() {
            const [users, setUsers] = useState([]);
            const [loading, setLoading] = useState(true);
            const [error, setError] = useState(null);

            useEffect(() => {
                const fetchUsers = async () => {
                    try {
                        const response = await axios.get('api/users_api.php');
                        if (response.data.success) {
                            setUsers(response.data.users);
                        } else {
                            setError(response.data.error || 'Failed to fetch users.');
                        }
                    } catch (err) {
                        console.error("Error fetching users:", err);
                        setError('Network error or server issue. Could not load users.');
                    } finally {
                        setLoading(false);
                    }
                };

                fetchUsers();
                // Optional: Auto-refresh users list every 60 seconds
                const intervalId = setInterval(fetchUsers, 60000); 
                return () => clearInterval(intervalId); // Cleanup on unmount
            }, []);

            if (loading) {
                return (
                    <div className="text-center text-white">
                        <div className="spinner-border text-light" role="status">
                            <span className="visually-hidden">Loading users...</span>
                        </div>
                        <p className="mt-2">Loading registered users...</p>
                    </div>
                );
            }

            if (error) {
                return (
                    <div className="alert alert-danger text-center mx-auto" style={{maxWidth: '500px'}} role="alert">
                        <strong>Error:</strong> {error}
                    </div>
                );
            }

            if (users.length === 0) {
                return (
                    <div className="alert alert-info text-center mx-auto" style={{maxWidth: '500px'}} role="alert">
                        No registered users found yet. Be the first to join our community!
                    </div>
                );
            }

            return (
                <div className="table-responsive">
                    <table className="table table-hover table-striped table-users">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th className="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.map(user => (
                                <tr key={user.id}>
                                    <td>{user.name}</td>
                                    <td>{user.email}</td>
                                    <td>{user.phone || 'N/A'}</td>
                                    <td className="text-center">
                                        <span className="badge bg-success">Registered</span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            );
        }

        // Render the React App component into the 'root' div
        const domNode = document.getElementById('root');
        ReactDOM.createRoot(domNode).render(<App />);
    </script>
</body>
</html>
