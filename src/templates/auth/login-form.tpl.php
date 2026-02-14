<h3>Login</h3>
<form method='post' action='/?q=auth/login'>
    <label for='username'>Username</label>
    <input class='u-full-width' id='username' type='text' name='username' value='<!-- username -->' required>

    <label for='password'>Password</label>
    <input class='u-full-width' id='password' type='password' name='password' required>

    <input type='submit' value='Login' class='button button-primary'>
    <a href='/?q=auth/register' class='button'>Register</a>
</form>