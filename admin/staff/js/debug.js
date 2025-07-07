// Add this to your form
$('#addStaffForm').submit(function(e) {
    // Temporarily prevent form submission
    e.preventDefault();
    
    // Log form data
    console.log('Form Data:', $(this).serialize());
    
    // Check required fields
    let missingFields = [];
    $(this).find('[required]').each(function() {
        if (!$(this).val()) {
            missingFields.push($(this).attr('name'));
        }
    });
    
    if (missingFields.length > 0) {
        console.log('Missing required fields:', missingFields);
        return;
    }
    
    // If everything is okay, submit the form
    this.submit();
});