<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دیباگر هوشمند DeepSeek</title>
    <style>
        body {
            font-family: Tahoma, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        textarea {
            width: 100%;
            height: 150px;
            padding: 10px;
            font-family: monospace;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        button {
            background: #0066cc;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }
        button:hover {
            background: #0052a3;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            background: #f0f0f0;
            border-radius: 5px;
            white-space: pre-wrap;
            font-family: monospace;
        }
        .loading {
            display: none;
            color: #0066cc;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🤖 دیباگر هوشمند DeepSeek</h1>

        <form id="debugForm">
            <label>خطای خود را وارد کنید:</label>
            <textarea name="error" id="error" placeholder="مثال: Call to undefined method App\Models\User::getEmail()"></textarea>

            <label style="margin-top: 10px;">مسیر فایل (اختیاری):</label>
            <input type="text" name="file" id="file" placeholder="app/Models/User.php" style="width: 100%; padding: 8px;">

            <label style="margin-top: 10px;">شماره خط (اختیاری):</label>
            <input type="number" name="line" id="line" placeholder="25" style="width: 100%; padding: 8px;">

            <button type="submit">🔍 رفع خطا</button>
        </form>

        <div class="loading" id="loading">در حال پردازش...</div>
        <div class="result" id="result"></div>
    </div>

    <script>
        document.getElementById('debugForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const loading = document.getElementById('loading');
            const resultDiv = document.getElementById('result');

            loading.style.display = 'block';
            resultDiv.innerHTML = '';

            const formData = new FormData(e.target);

            try {
                const response = await fetch('/debug/fix', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        error: formData.get('error'),
                        file: formData.get('file'),
                        line: formData.get('line')
                    })
                });

                const data = await response.json();
                resultDiv.innerHTML = '<strong>✅ راه حل پیشنهادی:</strong><br><br>' + data.solution;
            } catch (error) {
                resultDiv.innerHTML = '<strong>❌ خطا:</strong><br>' + error.message;
            } finally {
                loading.style.display = 'none';
            }
        });
    </script>
</body>
</html>
