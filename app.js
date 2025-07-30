const { useState, useEffect, useRef } = React;

// Reusable Alert Component
function AppAlert({ message, type, onClose }) {
    if (!message) return null;
    return (
        <div className={`alert alert-${type} alert-dismissible fade show`} role="alert">
            {message}
            <button type="button" className="btn-close" onClick={onClose} aria-label="Close"></button>
        </div>
    );
}

// Reusable Confirmation Modal Component
function ConfirmationModal({ show, title, message, onConfirm, onCancel }) {
    const modalRef = useRef(null);

    useEffect(() => {
        const modalElement = modalRef.current;
        if (!modalElement) return;

        const modal = new bootstrap.Modal(modalElement, { keyboard: false, backdrop: 'static' });
        if (show) {
            modal.show();
        } else {
            modal.hide();
        }

        return () => modal.dispose();
    }, [show]);

    return (
        <div className="modal fade" ref={modalRef} tabIndex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
            <div className="modal-dialog modal-dialog-centered">
                <div className="modal-content rounded-4 shadow-lg">
                    <div className="modal-header bg-primary text-white rounded-top-4">
                        <h5 className="modal-title" id="confirmationModalLabel">{title}</h5>
                        <button type="button" className="btn-close btn-close-white" onClick={onCancel} aria-label="Close"></button>
                    </div>
                    <div className="modal-body p-4">
                        <p className="lead text-center">{message}</p>
                    </div>
                    <div className="modal-footer justify-content-center border-0 pb-4">
                        <button type="button" className="btn btn-secondary px-4 py-2" onClick={onCancel}>Cancel</button>
                        <button type="button" className="btn btn-primary px-4 py-2" onClick={onConfirm}>Confirm</button>
                    </div>
                </div>
            </div>
        </div>
    );
}

// Simple Bar Chart Component using D3.js
function BarChart({ data, width, height, title, xLabel, yLabel, barColor = 'var(--prottoy-primary)' }) {
    const svgRef = useRef();

    useEffect(() => {
        if (!data || data.length === 0) {
            d3.select(svgRef.current).selectAll("*").remove();
            return;
        }

        const margin = { top: 30, right: 20, bottom: 50, left: 60 };
        const chartWidth = width - margin.left - margin.right;
        const chartHeight = height - margin.top - margin.bottom;

        const svg = d3.select(svgRef.current)
            .attr("width", width)
            .attr("height", height)
            .html("")
            .append("g")
            .attr("transform", `translate(${margin.left},${margin.top})`);

        const xScale = d3.scaleBand()
            .domain(data.map(d => d.label))
            .range([0, chartWidth])
            .padding(0.3);

        const yScale = d3.scaleLinear()
            .domain([0, d3.max(data, d => d.value) * 1.1])
            .range([chartHeight, 0]);

        svg.append("g")
            .attr("transform", `translate(0,${chartHeight})`)
            .call(d3.axisBottom(xScale))
            .selectAll("text")
            .style("text-anchor", "end")
            .attr("dx", "-.8em")
            .attr("dy", ".15em")
            .attr("transform", "rotate(-45)");

        svg.append("g")
            .call(d3.axisLeft(yScale));

        svg.selectAll(".bar")
            .data(data)
            .enter()
            .append("rect")
            .attr("class", "bar")
            .attr("x", d => xScale(d.label))
            .attr("y", d => yScale(d.value))
            .attr("width", xScale.bandwidth())
            .attr("height", d => chartHeight - yScale(d.value))
            .attr("fill", barColor)
            .on("mouseover", function() {
                d3.select(this).attr("fill", "var(--prottoy-accent)");
            })
            .on("mouseout", function() {
                d3.select(this).attr("fill", barColor);
            });

        svg.append("text")
            .attr("x", chartWidth / 2)
            .attr("y", chartHeight + margin.bottom - 5)
            .attr("text-anchor", "middle")
            .text(xLabel)
            .style("fill", "var(--prottoy-dark)");

        svg.append("text")
            .attr("y", -margin.left + 20)
            .attr("x", -chartHeight / 2)
            .attr("transform", "rotate(-90)")
            .attr("text-anchor", "middle")
            .text(yLabel)
            .style("fill", "var(--prottoy-dark)");

        svg.append("text")
            .attr("x", chartWidth / 2)
            .attr("y", -10)
            .attr("text-anchor", "middle")
            .style("font-size", "1.2rem")
            .style("font-weight", "bold")
            .style("fill", "var(--prottoy-dark)")
            .text(title);
    }, [data, width, height, title, xLabel, yLabel, barColor]);

    return <svg ref={svgRef}></svg>;
}

// Dashboard Component
function Dashboard({ setPage }) {
    const [summaryData, setSummaryData] = useState({
        totalDonations: 0,
        totalExpenses: 0,
        netBalance: 0,
        numDonations: 0,
        numExpenses: 0,
        monthlyDonations: []
    });
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        const fetchDashboardData = async () => {
            setLoading(true);
            setError(null);
            try {
                const donationsRes = await fetch('view_donations.php', { signal: AbortSignal.timeout(5000) });
                if (!donationsRes.ok) throw new Error(`HTTP error! status: ${donationsRes.status}`);
                const donationsData = await donationsRes.json();
                const donations = Array.isArray(donationsData) ? donationsData : donationsData.data || [];
                console.log('Dashboard Donations:', donations); // Debug

                const expensesRes = await fetch('view_expenses.php', { signal: AbortSignal.timeout(5000) });
                if (!expensesRes.ok) throw new Error(`HTTP error! status: ${expensesRes.status}`);
                const expensesData = await expensesRes.json();
                const expenses = Array.isArray(expensesData) ? expensesData : expensesData.data || [];
                console.log('Dashboard Expenses:', expenses); // Debug

                if (donations.length === 0 && expenses.length === 0) {
                    setError('No data found in the database.');
                    setLoading(false);
                    return;
                }

                const totalDonations = donations.reduce((sum, item) => sum + parseFloat(item.amount || 0), 0);
                const totalExpenses = expenses.reduce((sum, item) => sum + parseFloat(item.amount || 0), 0);
                const netBalance = totalDonations - totalExpenses;

                const monthlyDonationMap = {};
                donations.forEach(d => {
                    const month = new Date(d.date).toLocaleString('en-us', { month: 'short' });
                    monthlyDonationMap[month] = (monthlyDonationMap[month] || 0) + parseFloat(d.amount || 0);
                });
                const monthlyDonations = Object.keys(monthlyDonationMap).map(key => ({ label: key, value: monthlyDonationMap[key] }));
                const monthOrder = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                monthlyDonations.sort((a, b) => monthOrder.indexOf(a.label) - monthOrder.indexOf(b.label));

                setSummaryData({
                    totalDonations,
                    totalExpenses,
                    netBalance,
                    numDonations: donations.length,
                    numExpenses: expenses.length,
                    monthlyDonations
                });
            } catch (err) {
                console.error('Dashboard Fetch error:', err);
                setError(`Could not load dashboard data: ${err.message}`);
            } finally {
                setLoading(false);
            }
        };
        fetchDashboardData();
    }, []);

    if (loading) return (
        <div className="text-center py-5">
            <div className="spinner-border text-primary" role="status">
                <span className="visually-hidden">Loading...</span>
            </div>
            <p className="mt-2">Loading Dashboard Data...</p>
        </div>
    );
    if (error) return (
        <div className="container py-5 text-center">
            <div className="alert alert-danger">{error}</div>
        </div>
    );

    return (
        <div className="container py-5">
            <h1 className="text-center mb-5 display-4 text-primary">Finance Dashboard</h1>
            <p className="lead text-center text-muted mb-5">
                Manage Prottoy Foundation's financial activities.
            </p>
            <div className="row g-4 mb-5">
                <div className="col-md-4">
                    <div className="card shadow-sm border-0 rounded-4 h-100">
                        <div className="card-body text-center p-4">
                            <i className="bi bi-piggy-bank display-4 text-success mb-3"></i>
                            <h3 className="card-title text-success">Total Donations</h3>
                            <p className="display-5 fw-bold text-dark">${summaryData.totalDonations.toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
                            <p className="text-muted">{summaryData.numDonations} recorded</p>
                        </div>
                    </div>
                </div>
                <div className="col-md-4">
                    <div className="card shadow-sm border-0 rounded-4 h-100">
                        <div className="card-body text-center p-4">
                            <i className="bi bi-cash-coin display-4 text-danger mb-3"></i>
                            <h3 className="card-title text-danger">Total Expenses</h3>
                            <p className="display-5 fw-bold text-dark">${summaryData.totalExpenses.toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
                            <p className="text-muted">{summaryData.numExpenses} recorded</p>
                        </div>
                    </div>
                </div>
                <div className="col-md-4">
                    <div className="card shadow-sm border-0 rounded-4 h-100">
                        <div className="card-body text-center p-4">
                            <i className="bi bi-wallet-fill display-4 text-primary mb-3"></i>
                            <h3 className="card-title text-primary">Net Balance</h3>
                            <p className="display-5 fw-bold text-dark">${summaryData.netBalance.toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
                            <p className="text-muted">Current funds</p>
                        </div>
                    </div>
                </div>
            </div>
            <div className="card shadow-lg rounded-4 mb-5">
                <div className="card-header bg-dark text-white py-3 rounded-top-4">
                    <h3 className="mb-0 text-center">Monthly Donations</h3>
                </div>
                <div className="card-body p-4">
                    <BarChart
                        data={summaryData.monthlyDonations}
                        width={window.innerWidth > 768 ? 700 : window.innerWidth * 0.8}
                        height={350}
                        title="Monthly Donations"
                        xLabel="Month"
                        yLabel="Amount ($)"
                        barColor="var(--prottoy-primary)"
                    />
                </div>
            </div>
        </div>
    );
}

// Add Donation Component
function AddDonation({ setPage }) {
    const [form, setForm] = useState({ donor_name: '', donor_email: '', amount: '', method: '', date: '' });
    const [alert, setAlert] = useState(null);
    const [showConfirmModal, setShowConfirmModal] = useState(false);

    const handleChange = (e) => {
        setForm({ ...form, [e.target.name]: e.target.value });
    };

    const handleSubmitClick = () => {
        if (!form.donor_name || !form.amount || !form.method || !form.date) {
            setAlert({ message: 'Please fill in all required fields.', type: 'warning' });
            return;
        }
        if (parseFloat(form.amount) <= 0) {
            setAlert({ message: 'Amount must be greater than 0.', type: 'warning' });
            return;
        }
        if (new Date(form.date) > new Date()) {
            setAlert({ message: 'Date cannot be in the future.', type: 'warning' });
            return;
        }
        setShowConfirmModal(true);
    };

    const confirmSubmission = async () => {
        setShowConfirmModal(false);
        setAlert(null);
        try {
            const res = await fetch('add_donation.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(form),
                signal: AbortSignal.timeout(5000)
            });
            const data = await res.json();
            console.log('Add Donation Response:', data); // Debug
            if (data.status === 'success') {
                setAlert({ message: data.message || 'Donation added successfully!', type: 'success' });
                setForm({ donor_name: '', donor_email: '', amount: '', method: '', date: '' });
            } else {
                setAlert({ message: data.message || 'Failed to add donation.', type: 'danger' });
            }
        } catch (error) {
            console.error('Add Donation error:', error);
            setAlert({ message: `Connection error: ${error.message}`, type: 'danger' });
        }
    };

    return (
        <div className="container py-5">
            <div className="row justify-content-center">
                <div className="col-md-8">
                    <div className="card shadow-lg rounded-4">
                        <div className="card-header bg-primary text-white text-center py-4 rounded-top-4">
                            <h2 className="mb-0"><i className="bi bi-currency-dollar me-2"></i>Add Donation</h2>
                        </div>
                        <div className="card-body p-5">
                            <AppAlert message={alert?.message} type={alert?.type} onClose={() => setAlert(null)} />
                            <div className="mb-4">
                                <label htmlFor="donorName" className="form-label">Donor Name <span className="text-danger">*</span></label>
                                <input type="text" className="form-control form-control-lg" id="donorName" name="donor_name" value={form.donor_name} onChange={handleChange} required />
                            </div>
                            <div className="mb-4">
                                <label htmlFor="donorEmail" className="form-label">Donor Email</label>
                                <input type="email" className="form-control form-control-lg" id="donorEmail" name="donor_email" value={form.donor_email} onChange={handleChange} />
                            </div>
                            <div className="mb-4">
                                <label htmlFor="amount" className="form-label">Amount <span className="text-danger">*</span></label>
                                <div className="input-group input-group-lg">
                                    <span className="input-group-text">$</span>
                                    <input type="number" step="0.01" className="form-control" id="amount" name="amount" value={form.amount} onChange={handleChange} required />
                                </div>
                            </div>
                            <div className="mb-4">
                                <label htmlFor="method" className="form-label">Payment Method <span className="text-danger">*</span></label>
                                <input type="text" className="form-control form-control-lg" id="method" name="method" value={form.method} onChange={handleChange} required />
                            </div>
                            <div className="mb-5">
                                <label htmlFor="date" className="form-label">Date <span className="text-danger">*</span></label>
                                <input type="date" className="form-control form-control-lg" id="date" name="date" value={form.date} onChange={handleChange} required />
                            </div>
                            <div className="d-grid">
                                <button type="button" className="btn btn-primary btn-lg" onClick={handleSubmitClick}>
                                    Submit Donation
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <ConfirmationModal
                show={showConfirmModal}
                title="Confirm Donation"
                message="Are you sure you want to add this donation?"
                onConfirm={confirmSubmission}
                onCancel={() => setShowConfirmModal(false)}
            />
        </div>
    );
}

// View Donations Component
function ViewDonations({ setPage }) {
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [searchTerm, setSearchTerm] = useState('');
    const [currentPage, setCurrentPage] = useState(1);
    const itemsPerPage = 5;

    useEffect(() => {
        const fetchDonations = async () => {
            setLoading(true);
            setError(null);
            try {
                const res = await fetch('view_donations.php', { signal: AbortSignal.timeout(5000) });
                if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
                const result = await res.json();
                console.log('View Donations Data:', result); // Debug
                const donations = Array.isArray(result) ? result : result.data || [];
                if (donations.length === 0) {
                    setError('No donations found in the database.');
                } else {
                    setData(donations);
                }
            } catch (err) {
                console.error('View Donations Fetch error:', err);
                setError(`Could not load donations: ${err.message}`);
            } finally {
                setLoading(false);
            }
        };
        fetchDonations();
    }, []);

    const filteredData = data.filter(item =>
        (item.donor_name || '').toLowerCase().includes(searchTerm.toLowerCase()) ||
        (item.donor_email || '').toLowerCase().includes(searchTerm.toLowerCase()) ||
        (item.method || '').toLowerCase().includes(searchTerm.toLowerCase())
    );

    const indexOfLastItem = currentPage * itemsPerPage;
    const indexOfFirstItem = indexOfLastItem - itemsPerPage;
    const currentItems = filteredData.slice(indexOfFirstItem, indexOfLastItem);
    const totalPages = Math.ceil(filteredData.length / itemsPerPage);

    const paginate = (pageNumber) => setCurrentPage(pageNumber);

    const donationMethodMap = {};
    data.forEach(d => {
        const method = d.method || 'Unknown';
        donationMethodMap[method] = (donationMethodMap[method] || 0) + parseFloat(d.amount || 0);
    });
    const donationMethodData = Object.keys(donationMethodMap).map(key => ({ label: key, value: donationMethodMap[key] }));

    if (loading) return (
        <div className="text-center py-5">
            <div className="spinner-border text-primary" role="status">
                <span className="visually-hidden">Loading...</span>
            </div>
            <p className="mt-2">Loading Donations...</p>
        </div>
    );
    if (error) return (
        <div className="container py-5 text-center">
            <div className="alert alert-danger">{error}</div>
        </div>
    );

    return (
        <div className="container py-5">
            <h2 className="text-center mb-5 display-5 text-primary">All Donations</h2>
            <div className="card shadow-sm rounded-4 mb-4 p-4">
                <h4 className="mb-3 text-dark">Search Donations</h4>
                <div className="input-group input-group-lg">
                    <input
                        type="text"
                        className="form-control"
                        placeholder="Search by name, email, or method..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                    />
                    <button className="btn btn-primary" type="button">Search</button>
                </div>
            </div>
            <div className="row g-4 mb-5">
                <div className="col-md-6">
                    <div className="card shadow-lg rounded-4 h-100">
                        <div className="card-header bg-dark text-white py-3 rounded-top-4">
                            <h3 className="mb-0 text-center">Donation Distribution</h3>
                        </div>
                        <div className="card-body p-4">
                            <BarChart
                                data={donationMethodData}
                                width={window.innerWidth > 768 ? 400 : window.innerWidth * 0.7}
                                height={250}
                                title="By Method"
                                xLabel="Method"
                                yLabel="Amount ($)"
                                barColor="var(--prottoy-accent)"
                            />
                        </div>
                    </div>
                </div>
                <div className="col-md-6">
                    <div className="card shadow-lg rounded-4 h-100">
                        <div className="card-body text-center p-4">
                            <i className="bi bi-cash-stack display-3 text-primary mb-3"></i>
                            <h3 className="card-title text-primary">Total Donated</h3>
                            <p className="display-4 fw-bold text-dark">${data.reduce((sum, item) => sum + parseFloat(item.amount || 0), 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
                            <p className="text-muted">Total records: {data.length}</p>
                            <p className="text-muted">Filtered records: {filteredData.length}</p>
                        </div>
                    </div>
                </div>
            </div>
            <div className="card shadow-lg rounded-4">
                <div className="card-header bg-primary text-white py-3 rounded-top-4">
                    <h3 className="mb-0">Donations List</h3>
                </div>
                <div className="card-body p-0">
                    {filteredData.length === 0 ? (
                        <div className="alert alert-info text-center m-4">
                            No donations found.
                        </div>
                    ) : (
                        <div className="table-responsive">
                            <table className="table table-hover table-striped mb-0">
                                <thead className="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {currentItems.map(row => (
                                        <tr key={row.id}>
                                            <td>{row.id}</td>
                                            <td>{row.donor_name}</td>
                                            <td>{row.donor_email || 'N/A'}</td>
                                            <td>${parseFloat(row.amount || 0).toFixed(2)}</td>
                                            <td><span className="badge bg-info text-dark">{row.method}</span></td>
                                            <td>{row.date}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
                {totalPages > 1 && (
                    <div className="card-footer bg-light border-0 d-flex justify-content-center py-3">
                        <nav>
                            <ul className="pagination mb-0">
                                <li className={`page-item ${currentPage === 1 ? 'disabled' : ''}`}>
                                    <a className="page-link" href="#" onClick={(e) => { e.preventDefault(); paginate(currentPage - 1); }}>Previous</a>
                                </li>
                                {[...Array(totalPages).keys()].map(number => (
                                    <li key={number + 1} className={`page-item ${currentPage === number + 1 ? 'active' : ''}`}>
                                        <a className="page-link" href="#" onClick={(e) => { e.preventDefault(); paginate(number + 1); }}>{number + 1}</a>
                                    </li>
                                ))}
                                <li className={`page-item ${currentPage === totalPages ? 'disabled' : ''}`}>
                                    <a className="page-link" href="#" onClick={(e) => { e.preventDefault(); paginate(currentPage + 1); }}>Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                )}
            </div>
        </div>
    );
}

// Add Expense Component
function AddExpense({ setPage }) {
    const [form, setForm] = useState({ category: '', description: '', amount: '', paid_to: '', date: '' });
    const [alert, setAlert] = useState(null);
    const [showConfirmModal, setShowConfirmModal] = useState(false);

    const handleChange = (e) => {
        setForm({ ...form, [e.target.name]: e.target.value });
    };

    const handleSubmitClick = () => {
        if (!form.category || !form.description || !form.amount || !form.paid_to || !form.date) {
            setAlert({ message: 'Please fill in all required fields.', type: 'warning' });
            return;
        }
        if (parseFloat(form.amount) <= 0) {
            setAlert({ message: 'Amount must be greater than 0.', type: 'warning' });
            return;
        }
        if (new Date(form.date) > new Date()) {
            setAlert({ message: 'Date cannot be in the future.', type: 'warning' });
            return;
        }
        setShowConfirmModal(true);
    };

    const confirmSubmission = async () => {
        setShowConfirmModal(false);
        setAlert(null);
        try {
            const res = await fetch('add_expense.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(form),
                signal: AbortSignal.timeout(5000)
            });
            const data = await res.json();
            console.log('Add Expense Response:', data); // Debug
            if (data.status === 'success') {
                setAlert({ message: data.message || 'Expense added successfully!', type: 'success' });
                setForm({ category: '', description: '', amount: '', paid_to: '', date: '' });
            } else {
                setAlert({ message: data.message || 'Failed to add expense.', type: 'danger' });
            }
        } catch (error) {
            console.error('Add Expense error:', error);
            setAlert({ message: `Connection error: ${error.message}`, type: 'danger' });
        }
    };

    return (
        <div className="container py-5">
            <div className="row justify-content-center">
                <div className="col-md-8">
                    <div className="card shadow-lg rounded-4">
                        <div className="card-header bg-success text-white text-center py-4 rounded-top-4">
                            <h2 className="mb-0"><i className="bi bi-receipt me-2"></i>Add Expense</h2>
                        </div>
                        <div className="card-body p-5">
                            <AppAlert message={alert?.message} type={alert?.type} onClose={() => setAlert(null)} />
                            <div className="mb-4">
                                <label htmlFor="category" className="form-label">Category <span className="text-danger">*</span></label>
                                <input type="text" className="form-control form-control-lg" id="category" name="category" value={form.category} onChange={handleChange} required />
                            </div>
                            <div className="mb-4">
                                <label htmlFor="description" className="form-label">Description <span className="text-danger">*</span></label>
                                <textarea className="form-control form-control-lg" id="description" name="description" value={form.description} onChange={handleChange} required rows="3"></textarea>
                            </div>
                            <div className="mb-4">
                                <label htmlFor="amount" className="form-label">Amount <span className="text-danger">*</span></label>
                                <div className="input-group input-group-lg">
                                    <span className="input-group-text">$</span>
                                    <input type="number" step="0.01" className="form-control" id="amount" name="amount" value={form.amount} onChange={handleChange} required />
                                </div>
                            </div>
                            <div className="mb-4">
                                <label htmlFor="paidTo" className="form-label">Paid To <span className="text-danger">*</span></label>
                                <input type="text" className="form-control form-control-lg" id="paidTo" name="paid_to" value={form.paid_to} onChange={handleChange} required />
                            </div>
                            <div className="mb-5">
                                <label htmlFor="date" className="form-label">Date <span className="text-danger">*</span></label>
                                <input type="date" className="form-control form-control-lg" id="date" name="date" value={form.date} onChange={handleChange} required />
                            </div>
                            <div className="d-grid">
                                <button type="button" className="btn btn-success btn-lg" onClick={handleSubmitClick}>
                                    Submit Expense
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <ConfirmationModal
                show={showConfirmModal}
                title="Confirm Expense"
                message="Are you sure you want to add this expense?"
                onConfirm={confirmSubmission}
                onCancel={() => setShowConfirmModal(false)}
            />
        </div>
    );
}

// View Expenses Component
function ViewExpenses({ setPage }) {
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [searchTerm, setSearchTerm] = useState('');
    const [currentPage, setCurrentPage] = useState(1);
    const itemsPerPage = 5;

    useEffect(() => {
        const fetchExpenses = async () => {
            setLoading(true);
            setError(null);
            try {
                const res = await fetch('view_expenses.php', { signal: AbortSignal.timeout(5000) });
                if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
                const result = await res.json();
                console.log('View Expenses Data:', result); // Debug
                const expenses = Array.isArray(result) ? result : result.data || [];
                if (expenses.length === 0) {
                    setError('No expenses found in the database.');
                } else {
                    setData(expenses);
                }
            } catch (err) {
                console.error('View Expenses Fetch error:', err);
                setError(`Could not load expenses: ${err.message}`);
            } finally {
                setLoading(false);
            }
        };
        fetchExpenses();
    }, []);

    const filteredData = data.filter(item =>
        (item.category || '').toLowerCase().includes(searchTerm.toLowerCase()) ||
        (item.description || '').toLowerCase().includes(searchTerm.toLowerCase()) ||
        (item.paid_to || '').toLowerCase().includes(searchTerm.toLowerCase())
    );

    const indexOfLastItem = currentPage * itemsPerPage;
    const indexOfFirstItem = indexOfLastItem - itemsPerPage;
    const currentItems = filteredData.slice(indexOfFirstItem, indexOfLastItem);
    const totalPages = Math.ceil(filteredData.length / itemsPerPage);

    const paginate = (pageNumber) => setCurrentPage(pageNumber);

    const expenseCategoryMap = {};
    data.forEach(d => {
        const category = d.category || 'Unknown';
        expenseCategoryMap[category] = (expenseCategoryMap[category] || 0) + parseFloat(d.amount || 0);
    });
    const expenseCategoryData = Object.keys(expenseCategoryMap).map(key => ({ label: key, value: expenseCategoryMap[key] }));

    if (loading) return (
        <div className="text-center py-5">
            <div className="spinner-border text-success" role="status">
                <span className="visually-hidden">Loading...</span>
            </div>
            <p className="mt-2">Loading Expenses...</p>
        </div>
    );
    if (error) return (
        <div className="container py-5 text-center">
            <div className="alert alert-danger">{error}</div>
        </div>
    );

    return (
        <div className="container py-5">
            <h2 className="text-center mb-5 display-5 text-success">All Expenses</h2>
            <div className="card shadow-sm rounded-4 mb-4 p-4">
                <h4 className="mb-3 text-dark">Search Expenses</h4>
                <div className="input-group input-group-lg">
                    <input
                        type="text"
                        className="form-control"
                        placeholder="Search by category, description, or paid to..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                    />
                    <button className="btn btn-success" type="button">Search</button>
                </div>
            </div>
            <div className="row g-4 mb-5">
                <div className="col-md-6">
                    <div className="card shadow-lg rounded-4 h-100">
                        <div className="card-header bg-dark text-white py-3 rounded-top-4">
                            <h3 className="mb-0 text-center">Expense Distribution</h3>
                        </div>
                        <div className="card-body p-4">
                            <BarChart
                                data={expenseCategoryData}
                                width={window.innerWidth > 768 ? 400 : window.innerWidth * 0.7}
                                height={250}
                                title="By Category"
                                xLabel="Category"
                                yLabel="Amount ($)"
                                barColor="var(--prottoy-success)"
                            />
                        </div>
                    </div>
                </div>
                <div className="col-md-6">
                    <div className="card shadow-lg rounded-4 h-100">
                        <div className="card-body text-center p-4">
                            <i className="bi bi-receipt-cutoff display-3 text-danger mb-3"></i>
                            <h3 className="card-title text-danger">Total Expensed</h3>
                            <p className="display-4 fw-bold text-dark">${data.reduce((sum, item) => sum + parseFloat(item.amount || 0), 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
                            <p className="text-muted">Total records: {data.length}</p>
                            <p className="text-muted">Filtered records: {filteredData.length}</p>
                        </div>
                    </div>
                </div>
            </div>
            <div className="card shadow-lg rounded-4">
                <div className="card-header bg-success text-white py-3 rounded-top-4">
                    <h3 className="mb-0">Expenses List</h3>
                </div>
                <div className="card-body p-0">
                    {filteredData.length === 0 ? (
                        <div className="alert alert-info text-center m-4">
                            No expenses found.
                        </div>
                    ) : (
                        <div className="table-responsive">
                            <table className="table table-hover table-striped mb-0">
                                <thead className="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Category</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Paid To</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {currentItems.map(row => (
                                        <tr key={row.id}>
                                            <td>{row.id}</td>
                                            <td>{row.category}</td>
                                            <td>{row.description}</td>
                                            <td>${parseFloat(row.amount || 0).toFixed(2)}</td>
                                            <td><span className="badge bg-secondary">{row.paid_to}</span></td>
                                            <td>{row.date}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
                {totalPages > 1 && (
                    <div className="card-footer bg-light border-0 d-flex justify-content-center py-3">
                        <nav>
                            <ul className="pagination mb-0">
                                <li className={`page-item ${currentPage === 1 ? 'disabled' : ''}`}>
                                    <a className="page-link" href="#" onClick={(e) => { e.preventDefault(); paginate(currentPage - 1); }}>Previous</a>
                                </li>
                                {[...Array(totalPages).keys()].map(number => (
                                    <li key={number + 1} className={`page-item ${currentPage === number + 1 ? 'active' : ''}`}>
                                        <a className="page-link" href="#" onClick={(e) => { e.preventDefault(); paginate(number + 1); }}>{number + 1}</a>
                                    </li>
                                ))}
                                <li className={`page-item ${currentPage === totalPages ? 'disabled' : ''}`}>
                                    <a className="page-link" href="#" onClick={(e) => { e.preventDefault(); paginate(currentPage + 1); }}>Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                )}
            </div>
        </div>
    );
}

// Main App Component
function App() {
    const [page, setPage] = useState('dashboard');

    const renderPage = () => {
        switch (page) {
            case 'dashboard':
                return <Dashboard setPage={setPage} />;
            case 'addDonation':
                return <AddDonation setPage={setPage} />;
            case 'viewDonations':
                return <ViewDonations setPage={setPage} />;
            case 'addExpense':
                return <AddExpense setPage={setPage} />;
            case 'viewExpenses':
                return <ViewExpenses setPage={setPage} />;
            default:
                return <Dashboard setPage={setPage} />;
        }
    };

    return (
        <div>
            <nav className="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm fixed-top" style={{ display: 'block', visibility: 'visible', opacity: 1, zIndex: 1030 }}>
                <div className="container-fluid">
                    <a className="navbar-brand" href="#" onClick={() => setPage('dashboard')}>
                        <i className="bi bi-currency-exchange me-2"></i> Prottoy Finance
                    </a>
                    <button className="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                        <span className="navbar-toggler-icon"></span>
                    </button>
                    <div className="collapse navbar-collapse" id="navbarNav">
                        <ul className="navbar-nav ms-auto">
                            <li className="nav-item">
                                <a className={`nav-link ${page === 'dashboard' ? 'active' : ''}`} href="#" onClick={() => setPage('dashboard')}>
                                    Dashboard
                                </a>
                            </li>
                            <li className="nav-item dropdown">
                                <a className="nav-link dropdown-toggle" href="#" id="navbarDropdownDonations" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Donations
                                </a>
                                <ul className="dropdown-menu dropdown-menu-dark" aria-labelledby="navbarDropdownDonations">
                                    <li><a className="dropdown-item" href="#" onClick={() => setPage('addDonation')}>Add Donation</a></li>
                                    <li><a className="dropdown-item" href="#" onClick={() => setPage('viewDonations')}>View Donations</a></li>
                                </ul>
                            </li>
                            <li className="nav-item dropdown">
                                <a className="nav-link dropdown-toggle" href="#" id="navbarDropdownExpenses" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Expenses
                                </a>
                                <ul className="dropdown-menu dropdown-menu-dark" aria-labelledby="navbarDropdownExpenses">
                                    <li><a className="dropdown-item" href="#" onClick={() => setPage('addExpense')}>Add Expense</a></li>
                                    <li><a className="dropdown-item" href="#" onClick={() => setPage('viewExpenses')}>View Expenses</a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
            <main style={{ marginTop: '70px' }}>
                {renderPage()}
            </main>
            <footer className="footer bg-dark text-white-50 py-4 mt-5">
                <div className="container text-center">
                    <p className="mb-0">© 2025 Prottoy Foundation. All rights reserved.</p>
                </div>
            </footer>
        </div>
    );
}

// Render the App
ReactDOM.createRoot(document.getElementById('root')).render(<App />);