<div id="image-create">
    <h2>Create Disk Image</h2>
    <form action="<!-- www -->/?q=image/manage/create" method="post">
        <label for="image-name">Image name:</label>
        <input type="text" id="image-name" name="image-name" 
               value="<!-- image-name-value -->"
               pattern="[a-zA-Z0-9_-]+" 
               title="Use only letters, numbers, underscores and hyphens"
               required>
        
        <label for="image-size">Image size (MB, 1-1048576):</label>
        <input type="number" id="image-size" name="image-size" 
               value="<!-- image-size-value -->"
               min="1" max="1048576" 
               title="Size in megabytes, from 1MB to 1TB"
               required>
        
        <label for="image-format">Image format:</label>
        <select id="image-format" name="image-format" required>
            <option value="qcow2" <!-- qcow2-selected -->>qcow2 (recommended)</option>
            <option value="raw" <!-- raw-selected -->>raw</option>
            <option value="vmdk" <!-- vmdk-selected -->>vmdk</option>
            <option value="vdi" <!-- vdi-selected -->>vdi</option>
            <option value="vhdx" <!-- vhdx-selected -->>vhdx</option>
        </select>
        
        <input type="submit" value="Create Disk Image" class="button-primary">
        <a href="/?q=image/manage/list" class="button">Cancel</a>
    </form>
</div>