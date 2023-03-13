$(document).ready(function() {
    $("#login-btn").click(login);

    $(".form-control").on("input", function() {
        $(this).removeClass('is-invalid');
        $("#message-feedback").text("");
    });

    $(".form-control").on("keypress", function(e) {
        if (e.which == 13) { // ENTER key
            login(e);
        }
    })
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
        success: function(response) {
            console.log(response);
            /*
            const username_b64 = response.token.substring(0, response.token.indexOf('.'));
            const username = atob(username_b64);
            console.log(username_b64 + ' - ' + username);
            */
            window.location.assign('/');
        },
        error: function(xhr) {
            console.log(xhr.responseText);
            const error_obj = JSON.parse(xhr.responseText);
            console.log(error_obj);

            if (error_obj.message) {
                if (error_obj.code && typeof error_obj.code == 'string') {
                    if (error_obj.code == 'auth-011') {
                        $("#username-feedback").text(error_obj.message);
                        $("#username-input").addClass('is-invalid');
                    }
                    if (error_obj.code == 'auth-010') {
                        $("#password-feedback").text(error_obj.message);
                        $("#password-input").addClass('is-invalid');
                    }
                } else {
                    $("#message-feedback").text(error_obj.message);
                }
            }
        }
    });

}