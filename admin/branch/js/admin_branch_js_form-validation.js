$(document).ready(function() {
    // Initialize form validation
    $("#addBranchForm").validate({
        rules: {
            branch_code: {
                required: true,
                minlength: 3,
                maxlength: 10
            },
            branch_name: {
                required: true,
                minlength: 3
            },
            opening_date: "required",
            address1: "required",
            city: "required",
            postal_code: {
                required: true,
                pattern: /^[0-9]{6}$/
            },
            contact_number: {
                required: true,
                pattern: /^[0-9]{10}$/
            },
            email: {
                required: true,
                email: true
            }
        },
        messages: {
            branch_code: {
                required: "Please enter branch code",
                minlength: "Branch code must be at least 3 characters",
                maxlength: "Branch code cannot exceed 10 characters"
            },
            branch_name: {
                required: "Please enter branch name",
                minlength: "Branch name must be at least 3 characters"
            },
            postal_code: {
                pattern: "Please enter a valid 6-digit postal code"
            },
            contact_number: {
                pattern: "Please enter a valid 10-digit contact number"
            }
        },
        errorElement: 'span',
        errorPlacement: function(error, element) {
            error.addClass('invalid-feedback');
            element.closest('.mb-3').append(error);
        },
        highlight: function(element, errorClass, validClass) {
            $(element).addClass('is-invalid');
        },
        unhighlight: function(element, errorClass, validClass) {
            $(element).removeClass('is-invalid');
        },
        submitHandler: function(form) {
            // Show loading state
            const submitBtn = $(form).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
            submitBtn.prop('disabled', true);
            
            // Submit form
            form.submit();
        }
    });
});