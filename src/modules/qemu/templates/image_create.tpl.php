<div id="image-create">
    <h2>Create drive image</h2>
    <form action="<!-- www -->/?q=image/create" method="post">
        <label for="image-name">Image name:</label>
        <input type="text" id="image-name" name="image-name" required>
        <label for="image-size">Image size (MB):</label>
        <input type="number" id="image-size" name="image-size" required>
        <label for="image-format">Image format:</label>
        <select id="image-format" name="image-format">
            <option value="qcow2">qcow2</option>
            <option value="raw">raw</option>
            <option value="vmdk">vmdk</option>
        </select>
        <input type="submit" value="Create">
</div>