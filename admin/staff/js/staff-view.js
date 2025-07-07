$(document).ready(function() {
    // Initialize DataTables for activity logs
    $('#activityLogsTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 5,
        lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]]
    });

    // Initialize DataTables for login history
    $('#loginHistoryTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 5,
        lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]]
    });

    // Chart.js initialization for staff statistics
    if ($('#activityChart').length) {
        let ctx = document.getElementById('activityChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: activityDates,
                datasets: [{
                    label: 'Login Activity',
                    data: loginCounts,
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
});

// Print staff profile
function printProfile() {
    window.print();
}

// Download staff data as PDF
function downloadPDF() {
    let docDefinition = {
        content: [
            { text: 'Staff Profile', style: 'header' },
            {
                columns: [
                    {
                        width: '50%',
                        text: [
                            { text: 'Personal Information\n', style: 'subheader' },
                            `Name: ${staffData.full_name}\n`,
                            `Email: ${staffData.email}\n`,
                            `Mobile: ${staffData.mobile}\n`,
                            `Gender: ${staffData.gender}\n`,
                            `DOB: ${staffData.dob}\n`
                        ]
                    },
                    {
                        width: '50%',
                        text: [
                            { text: 'Employment Information\n', style: 'subheader' },
                            `Staff ID: ${staffData.staff_id}\n`,
                            `Branch: ${staffData.branch_name}\n`,
                            `Role: ${staffData.role}\n`,
                            `Department: ${staffData.department}\n`,
                            `Joining Date: ${staffData.joining_date}\n`
                        ]
                    }
                ]
            }
        ],
        styles: {
            header: {
                fontSize: 18,
                bold: true,
                margin: [0, 0, 0, 10]
            },
            subheader: {
                fontSize: 14,
                bold: true,
                margin: [0, 10, 0, 5]
            }
        }
    };
    pdfMake.createPdf(docDefinition).download(`staff_profile_${staffData.staff_id}.pdf`);
}