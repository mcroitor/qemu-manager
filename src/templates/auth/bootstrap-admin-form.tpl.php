<h3>Bootstrap Admin</h3>
<p>Create the first administrator account for QEMU Manager.</p>
<form method='post' action='/?q=auth/bootstrap-admin'>
    <label for='username'>Username</label>
    <input class='u-full-width' id='username' type='text' name='username' value='<!-- username -->' required>

    <label for='email'>Email</label>
    <input class='u-full-width' id='email' type='email' name='email' value='<!-- email -->' required>

    <label for='password'>Password</label>
    <input class='u-full-width' id='password' type='password' name='password' required>

    <label for='password_confirm'>Confirm password</label>
    <input class='u-full-width' id='password_confirm' type='password' name='password_confirm' required>

    <input type='submit' value='Create admin' class='button button-primary'>
    <a href='/?q=auth/login' class='button'>Back to login</a>
</form>
