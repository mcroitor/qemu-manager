<h3>Register</h3>
<form method='post' action='/?q=auth/register'>
    <label for='username'>Username</label>
    <input class='u-full-width' id='username' type='text' name='username' value='<!-- username -->' required>

    <label for='email'>Email</label>
    <input class='u-full-width' id='email' type='email' name='email' value='<!-- email -->' required>

    <label for='password'>Password</label>
    <input class='u-full-width' id='password' type='password' name='password' required>

    <label for='password_confirm'>Confirm password</label>
    <input class='u-full-width' id='password_confirm' type='password' name='password_confirm' required>

    <input type='submit' value='Register' class='button button-primary'>
    <a href='/?q=auth/login' class='button'>Back to login</a>
</form>
