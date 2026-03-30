<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hello Laravel</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f3f4f6;
            color: #1f2937;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            background-color: white;
            padding: 3rem; /* 余白を広く */
            border-radius: 1.5rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            text-align: center;
            width: 90%;
            max-width: 600px; /* 少し横幅を広げました */
        }
        h1 {
            color: #FF2D20;
            font-size: 3.5rem; /* 文字を大きく設定 (以前の2倍以上) */
            margin: 0.5rem 0;
            font-weight: 700;
        }
        .version-box {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: center;
            gap: 2rem; /* 項目間の間隔 */
        }
        .version-item {
            font-size: 1.1rem; /* バージョン情報の文字も少し大きく */
            color: #4b5563;
        }
        .label {
            display: block;
            font-size: 0.8rem;
            text-transform: uppercase;
            color: #9ca3af;
            font-weight: bold;
            margin-bottom: 0.25rem;
        }
        .links {
            text-align: left;
            border-bottom: 1px solid;
        }
        .pre, pre {
            text-align: left;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <svg viewBox="0 0 62 65" fill="none" xmlns="http://www.w3.org/2000/svg" style="width: 80px; margin-bottom: 1rem;">
            <path d="M61.8548 14.6253L31.3534 0.130565C30.5035 -0.103189 29.5893 -0.034474 28.7858 0.324122L0.672709 13.9806C0.244135 14.1913 0 14.639 0 15.1153V49.8847C0 50.361 0.244135 50.8087 0.672709 51.0194L28.7858 64.6759C29.1867 64.8723 29.5932 64.9705 30 64.9705C30.4068 64.9705 30.8133 64.8723 31.2142 64.6759L61.3273 51.0194C61.7559 50.8087 62 50.361 62 49.8847V15.1153C62 14.9355 61.9482 14.7608 61.8548 14.6253Z" fill="#FF2D20"/>
        </svg>

        <h1>Hello, Laravel!</h1>
        
        <div class="version-box">
            <div class="version-item">
                <span class="label">Laravel</span>
                v{{ Illuminate\Foundation\Application::VERSION }}
            </div>
            <div class="version-item">
                <span class="label">PHP</span>
                v{{ PHP_VERSION }}
            </div>
        </div>
        <div class="links">
          <p><a href="/">Top</a><br>
          <a href="/hello">Query to MySQL internally</a><br>
          <a href="/call">Call External API</a><br>
          <a href="/query">Index Query (causes an Internal Error)</a></p>
        </div>
        <div class="content">{!! $htmlData !!}</div>
    </div>
</body>
</html>
