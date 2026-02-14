<h3><!-- form-title --></h3>
<form action='<!-- form-action -->' method='post'>
    <label for='machine_name'>Virtual Machine:</label>
    <select id='machine_name' name='machine_name' required><!-- machine-options --></select>

    <label for='mac_address'>MAC Address:</label>
    <input type='text' id='mac_address' name='mac_address' value='<!-- mac-value -->'
           pattern='[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}'
           title='Format: AA:BB:CC:DD:EE:FF' required>

    <label for='ip_address'>IP Address (leave empty for DHCP):</label>
    <input type='text' id='ip_address' name='ip_address' value='<!-- ip-value -->' placeholder='192.168.1.100'>

    <label for='netmask'>Netmask:</label>
    <input type='text' id='netmask' name='netmask' value='<!-- netmask-value -->' placeholder='255.255.255.0'>

    <label for='gateway'>Gateway:</label>
    <input type='text' id='gateway' name='gateway' value='<!-- gateway-value -->' placeholder='192.168.1.1'>

    <label for='dns'>DNS Server:</label>
    <input type='text' id='dns' name='dns' value='<!-- dns-value -->' placeholder='8.8.8.8'>

    <input type='submit' value='<!-- submit-text -->' class='button button-primary'>
    <a href='/?q=network/manage/list' class='button'>Cancel</a>
</form>
