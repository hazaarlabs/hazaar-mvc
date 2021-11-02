$(document).ready(function () {
    $('#selectEnv').change(function () {
        document.location = '?env=' + $(this).val();
    });
});