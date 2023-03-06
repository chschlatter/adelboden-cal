$(document).ready(function() {
    $("#login-btn").click(login);

    $(".form-control").change(function() {
        $(this).removeClass('is-invalid');
    });
});


function login(e) {
    const form_data = new FormData(document.getElementById("login-form"));
    const credentials = Object.fromEntries(form_data);
    console.log(credentials);

    $.ajax({
        url: '/api/users/login',
        dataType: 'json',
        type: 'post',
        data: JSON.stringify(credentials),
        success: function(xhr) {
            window.location.replace("/");
        },
        error: function(xhr) {
            console.log(xhr.responseText);
            const error_obj = JSON.parse(xhr.responseText);
            console.log(error_obj);
            if (error_obj.username_msg) {
                $("#username-feedback").text(error_obj.username_msg);
                $("#username-input").addClass('is-invalid');
            }
            if (error_obj.password_msg) {
                $("#password-feedback").text(error_obj.password_msg);
                $("#password-input").addClass('is-invalid');
            }            
        }
    });

}