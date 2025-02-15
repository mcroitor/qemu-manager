<div id="machine-create">
    <h2>Create virtual machine</h2>
    <form action="<!-- www -->/?q=machine/manage/create" method="post">
        <label for="machine-name">Machine name:</label>
        <input type="text" id="machine-name" name="machine-name" required>
        <label for="machine-cpu">CPU cores:</label>
        <input type="number" id="machine-cpu" name="machine-cpu" required>
        <label for="machine-ram">RAM (MB):</label>
        <input type="number" id="machine-ram" name="machine-ram" required>
        <label for="machine-image">Image:</label>
        <select id="machine-image" name="machine-image">
            <!-- disk-image-list -->
        </select>
        <input type="submit" value="Create">
    </form>
</div>