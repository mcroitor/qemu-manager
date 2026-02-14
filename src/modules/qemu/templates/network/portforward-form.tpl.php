<h4>Add Port Forwarding Rule</h4>
<form method='post'>
    <label>Virtual Machine:</label>
    <select name='machine_name' required><!-- machine-options --></select>
    <label>Protocol:</label>
    <select name='protocol' required>
        <option value='tcp'>TCP</option>
        <option value='udp'>UDP</option>
    </select>
    <label>Host Port:</label>
    <input type='number' name='host_port' min='1' max='65535' required>
    <label>Guest Port:</label>
    <input type='number' name='guest_port' min='1' max='65535' required>
    <label>Guest IP (optional):</label>
    <input type='text' name='guest_ip' placeholder='Leave empty for default'>
    <input type='submit' value='Add Rule' class='button button-primary'>
</form>
