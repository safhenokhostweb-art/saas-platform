<?php
// ============================================
// دیباگر DeepSeek - نسخه کاملاً مستقل
// مسیر: C:\laragon\www\debugger.php
// دسترسی: http://localhost/debugger.php
// ============================================

$apiKey = "sk-22cbabc9aa284678b5755e004aa150fa"; // 🔑 کلید API خود را وارد کن

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $error = $_POST['error'] ?? '';
    if (empty($error)) {
        echo json_encode(['error' => 'Error message is required']);
        exit;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.deepseek.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'deepseek-chat',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a Laravel expert. Give ONLY the final solution. NO explanations. Just tell me the exact fix.'],
            ['role' => 'user', 'content' => "Error:\n" . $error]
        ],
        'temperature' => 0.1,
        'max_tokens' => 300
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        echo json_encode(['solution' => $data['choices'][0]['message']['content'] ?? 'No solution']);
    } else {
        echo json_encode(['error' => "HTTP $httpCode"]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>DeepSeek Debugger</title>
    <style>
        body {
            font-family: Tahoma, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 20px;
            max-width: 700px;
            width: 100%;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            text-align: center;
        }
        .header h1 { margin: 0; font-size: 24px; }
        .header p { margin: 10px 0 0; opacity: 0.9; font-size: 14px; }
        .body { padding: 25px; }
        textarea {
            width: 100%;
            height: 150px;
            padding: 12px;
            font-family: monospace;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 13px;
            resize: vertical;
        }
        textarea:focus { outline: none; border-color: #667eea; }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            margin-top: 15px;
        }
        button:hover { transform: translateY(-2px); }
        .result {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border-right: 4px solid #667eea;
            display: none;
        }
        .result pre {
            margin: 0;
            white-space: pre-wrap;
            font-family: monospace;
            font-size: 13px;
        }
        .loading {
            text-align: center;
            margin: 20px;
            display: none;
        }
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .examples {
            margin-top: 20px;
            padding: 12px;
            background: #fff3cd;
            border-radius: 8px;
            font-size: 12px;
        }
        .examples span {
            display: inline-block;
            background: #ffc107;
            padding: 4px 10px;
            border-radius: 15px;
            margin: 4px;
            cursor: pointer;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>🤖 DeepSeek Debugger</h1>
            <p>رفع خطاهای Laravel - بدون نیاز به کامپوزر</p>
        </div>
        <div class="body">
            <textarea id="error" placeholder="خطای خود را وارد کنید..."></textarea>
            <button onclick="fixError()">🔍 رفع خطا</button>
            
            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p>در حال تحلیل...</p>
            </div>
            
            <div class="result" id="result">
                <pre id="solution"></pre>
            </div>
            
            <div class="examples">
                📋 نمونه خطاها (کلیک کنید):
                <span onclick="setError('SQLSTATE[42S02]: Table \'users\' not found')">Table not found</span>
                <span onclick="setError('Call to undefined method App\\Models\\User::getEmail()')">Undefined method</span>
                <span onclick="setError('Class \'Controller\' not found')">Class not found</span>
            </div>
        </div>
    </div>

    <script>
        function setError(text) {
            document.getElementById('error').value = text;
        }
        
        async function fixError() {
            const error = document.getElementById('error').value;
            if (!error.trim()) {
                alert('لطفا خطا را وارد کنید');
                return;
            }
            
            const loading = document.getElementById('loading');
            const resultDiv = document.getElementById('result');
            
            loading.style.display = 'block';
            resultDiv.style.display = 'none';
            
            const formData = new FormData();
            formData.append('error', error);
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.solution) {
                    document.getElementById('solution').textContent = data.solution;
                    resultDiv.style.display = 'block';
                } else {
                    alert('خطا: ' + (data.error || 'مشخص نیست'));
                }
            } catch (err) {
                alert('خطا: ' + err.message);
            } finally {
                loading.style.display = 'none';
            }
        }
    </script>
</body>
</html>