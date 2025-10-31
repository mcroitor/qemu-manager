<div id="machine-create">
    <h2>Create virtual machine</h2>
    <form action="<!-- www -->/?q=machine/manage/create" method="post">
        <label for="machine-name">Machine name:</label>
        <input type="text" id="machine-name" name="machine-name" 
               value="<!-- machine-name-value -->" 
               pattern="[a-zA-Z0-9_-]+" 
               title="Use only letters, numbers, underscores and hyphens"
               required>
        
        <label for="machine-cpu">CPU cores (1-32):</label>
        <input type="number" id="machine-cpu" name="machine-cpu" 
               value="<!-- machine-cpu-value -->" 
               min="1" max="32" value="1" required>
        
        <label for="machine-ram">RAM (MB, 128-32768):</label>
        <input type="number" id="machine-ram" name="machine-ram" 
               value="<!-- machine-ram-value -->" 
               min="128" max="32768" value="512" required>
        
        <label for="machine-image">Primary Disk Image:</label>
        <select id="machine-image" name="machine-image" required>
            <option value="">-- Select an image --</option>
            <!-- disk-image-list -->
        </select>
        
        <input type="submit" value="Create Virtual Machine" class="button-primary">
        <a href="/?q=machine/manage/list" class="button">Cancel</a>
    </form>
</div>