$(document).ready(function() {
    // Initialize form validation
    initializeFormValidation();
    
    // Initialize file upload preview
    initializeFileUpload();
    
    // Initialize datepickers
    initializeDatepickers();
    
    // Initialize select2 for dropdowns
    initializeSelect2();
});

function initializeFormValidation() {
    $("#addStaffForm, #editStaffForm").validate({
        rules: {
            staff_id: {
                required: true,
                minlength: 3,
                remote: {
                    url: "check_duplicate.php",
                    type: "post",
                    data: {
                        field: "staff_id"
                    }
                }
            },
            full_name: {
                required: true,
                minlength: 3
            },
            email: {
                required: true,
                email: true,
                remote: {
                    url: "check_duplicate.php",
                    type: "post",
                    data: {
                        field: "email"
                    }
                }
            },
            mobile: {
                required: true,
                minlength: 10,
                maxlength: 15
            },
            username: {
                required: true,
                minlength: 5,
                remote: {
                    url: "check_duplicate.php",
                    type: "post",
                    data: {
                        field: "username"
                    }
                }
            },
            password: {
                required: function() {
                    return $("#addStaffForm").length > 0;
                },
                minlength: 6
            },
            confirm_password: {
                required: function() {
                    return $("#password").val().length > 0;
                },
                equalTo: "#password"
            },
            branch_id: "required",
            role: "required",
            dob: {
                required: true,
                date: true
            },
            joining_date: {
                required: true,
                date: true
            }
        },
        messages: {
            staff_id: {
                remote: "This Staff ID is already taken"
            },
            email: {
                remote: "This email is already registered"
            },
            username: {
                remote: "This username is already taken"
            },
            confirm_password: {
                equalTo: "Passwords do not match!"
            }
        },
        errorElement: 'span',
        errorPlacement: function(error, element) {
            error.addClass('invalid-feedback');
            element.closest('.form-group').append(error);
        },
        highlight: function(element, errorClass, validClass) {
            $(element).addClass('is-invalid');
        },
        unhighlight: function(element, errorClass, validClass) {
            $(element).removeClass('is-invalid');
        },
        submitHandler: function(form) {
            $("#submitBtn").prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
            form.submit();
        }
    });
}

function initializeFileUpload() {
    $("#profilePhoto").change(function() {
        if (this.files && this.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#profilePreview').attr('src', e.target.result);
            }
            reader.readAsDataURL(this.files[0]);
        }
    });
}

function initializeDatepickers() {
    $(".datepicker").datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        todayHighlight: true,
        endDate: '0d'
    });
}

function initializeSelect2() {
    $('.select2').select2({
        theme: 'bootstrap4',
        width: '100%'
    });
}

// Handle staff deletion
function confirmDelete(staffId, staffName) {
    Swal.fire({
        title: 'Are you sure?',
        text: `Do you want to deactivate ${staffName}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, deactivate!'
    }).then((result) => {
        if (result.isConfirmed) {
            $("#deleteStaffId").val(staffId);
            $("#deleteForm").submit();
        }
    });
}

// Export staff list
function exportStaffList(format) {
    let table = $('#staffTable').DataTable();
    let data = table.data().toArray();
    
    if (format === 'excel') {
        let wb = XLSX.utils.book_new();
        let ws = XLSX.utils.json_to_sheet(data);
        XLSX.utils.book_append_sheet(wb, ws, "Staff List");
        XLSX.writeFile(wb, `staff_list_${moment().format('YYYY-MM-DD')}.xlsx`);
    } else if (format === 'pdf') {
        let docDefinition = {
            content: [
                { text: 'Staff List', style: 'header' },
                {
                    table: {
                        headerRows: 1,
                        body: [
                            ['Staff ID', 'Name', 'Branch', 'Role', 'Contact', 'Status'],
                            ...data.map(row => [
                                row.staff_id,
                                row.full_name,
                                row.branch_name,
                                row.role,
                                `${row.mobile}\n${row.email}`,
                                row.status
                            ])
                        ]
                    }
                }
            ],
            styles: {
                header: {
                    fontSize: 18,
                    bold: true,
                    margin: [0, 0, 0, 10]
                }
            }
        };
        pdfMake.createPdf(docDefinition).download(`staff_list_${moment().format('YYYY-MM-DD')}.pdf`);
    }
}