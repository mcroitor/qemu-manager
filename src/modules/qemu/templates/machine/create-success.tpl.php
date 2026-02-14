<div style="color: green; background: #e6ffe6; padding: 15px; border: 1px solid #00ff00; margin-bottom: 10px;">
    <h3>✓ Success!</h3>
    <p>Virtual machine '<strong><!-- machine-name --></strong>' has been created successfully.</p>
    <p><strong>Configuration:</strong></p>
    <ul>
        <li>CPU cores: <!-- machine-cpu --></li>
        <li>Memory: <!-- machine-ram --> MB</li>
        <li>Primary disk: <!-- machine-image --></li>
        <li>Platform: <!-- machine-platform --></li>
        <li>Network: Default interface created</li>
    </ul>
    <p>
        <a href='/?q=machine/manage/list' class='button w120px'>View all machines</a>
        <a href='/?q=machine/manage/start/<!-- machine-name-url -->' class='button button-primary w120px'>Start this machine</a>
        <a href='/?q=network/manage/edit/<!-- machine-name-url -->' class='button w120px'>Configure Network</a>
    </p>
</div>
