<?php
/**
 * Debug Menu API - Check what's happening when adding menu
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Log all requests
file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Debug started\n", FILE_APPEND);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Menu Add</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 8px; }
        button { padding: 10px 20px; background: #4CAF50; color: white; border: none; cursor: pointer; }
        .response { margin-top: 20px; padding: 15px; background: #f4f4f4; border-radius: 5px; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <h2>🐛 Debug: Add Menu Item</h2>
    
    <form id="debugForm" enctype="multipart/form-data">
        <div class="form-group">
            <label>Category:</label>
            <select name="category_id" required>
                <?php
                require_once 'config/database.php';
                $pdo = getDbConnection();
                $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
                foreach ($categories as $cat) {
                    echo "<option value='{$cat['id']}'>{$cat['name']}</option>";
                }
                ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>Menu Name:</label>
            <input type="text" name="name" value="Test Menu Debug" required>
        </div>
        
        <div class="form-group">
            <label>Description:</label>
            <textarea name="description" rows="3">This is a test description</textarea>
        </div>
        
        <div class="form-group">
            <label>Price:</label>
            <input type="number" name="price" value="50000" required>
        </div>
        
        <div class="form-group">
            <label>Cost Price:</label>
            <input type="number" name="cost_price" value="30000">
        </div>
        
        <div class="form-group">
            <label>Image (optional):</label>
            <input type="file" name="image" accept="image/*">
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" name="is_available" value="1" checked>
                Available
            </label>
        </div>
        
        <button type="submit">🚀 Test Add Menu</button>
    </form>
    
    <div id="response" class="response" style="display: none;"></div>
    
    <hr>
    <h3>API Endpoint Test</h3>
    <button onclick="testEndpoint()">Test api/menu.php Endpoint</button>
    <div id="endpointResponse" class="response" style="display: none;"></div>
    
    <script>
        document.getElementById('debugForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const responseDiv = document.getElementById('response');
            responseDiv.style.display = 'block';
            responseDiv.innerHTML = '<p>⏳ Sending request...</p>';
            
            const formData = new FormData(e.target);
            formData.append('action', 'add');
            
            console.log('Form Data:');
            for (let [key, value] of formData.entries()) {
                console.log(key, value);
            }
            
            try {
                const response = await fetch('api/menu.php', {
                    method: 'POST',
                    body: formData
                });
                
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                const contentType = response.headers.get('content-type');
                let data;
                
                if (contentType && contentType.includes('application/json')) {
                    data = await response.json();
                } else {
                    const text = await response.text();
                    console.log('Response text:', text);
                    responseDiv.innerHTML = `
                        <h3 class="error">❌ Server returned non-JSON response</h3>
                        <p><strong>Status:</strong> ${response.status}</p>
                        <p><strong>Content-Type:</strong> ${contentType}</p>
                        <h4>Response:</h4>
                        <pre>${text}</pre>
                    `;
                    return;
                }
                
                console.log('Response data:', data);
                
                if (data.status === 'success') {
                    responseDiv.innerHTML = `
                        <h3 class="success">✅ Success!</h3>
                        <p><strong>Message:</strong> ${data.message}</p>
                        <p><strong>Menu ID:</strong> ${data.menu_id || 'N/A'}</p>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    `;
                } else {
                    responseDiv.innerHTML = `
                        <h3 class="error">❌ Error</h3>
                        <p><strong>Message:</strong> ${data.message || 'Unknown error'}</p>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    `;
                }
            } catch (error) {
                console.error('Fetch error:', error);
                responseDiv.innerHTML = `
                    <h3 class="error">❌ Network Error</h3>
                    <p>${error.message}</p>
                `;
            }
        });
        
        async function testEndpoint() {
            const div = document.getElementById('endpointResponse');
            div.style.display = 'block';
            div.innerHTML = '<p>⏳ Testing endpoint...</p>';
            
            try {
                const response = await fetch('api/menu.php?action=list');
                const text = await response.text();
                
                console.log('Endpoint test response:', text);
                
                let data;
                try {
                    data = JSON.parse(text);
                    div.innerHTML = `
                        <h3 class="success">✅ Endpoint is working</h3>
                        <p><strong>Status:</strong> ${response.status}</p>
                        <p><strong>Response:</strong></p>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    `;
                } catch (e) {
                    div.innerHTML = `
                        <h3 class="error">❌ Endpoint returned non-JSON</h3>
                        <p><strong>Status:</strong> ${response.status}</p>
                        <pre>${text}</pre>
                    `;
                }
            } catch (error) {
                div.innerHTML = `
                    <h3 class="error">❌ Cannot reach endpoint</h3>
                    <p>${error.message}</p>
                `;
            }
        }
    </script>
    
    <hr>
    <p style="color: red;"><strong>⚠️ Delete this file after debugging!</strong></p>
    <pre>git rm debug-menu.php
git commit -m "Remove debug file"
git push</pre>
</body>
</html>
