<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لایسنس منقضی شده</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Tahoma, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            padding: 50px;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        .icon {
            width: 100px;
            height: 100px;
            background: #fee2e2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
        }
        .icon svg {
            width: 50px;
            height: 50px;
            color: #dc2626;
        }
        h1 {
            color: #1f2937;
            font-size: 28px;
            margin-bottom: 15px;
        }
        .message {
            color: #6b7280;
            font-size: 16px;
            line-height: 1.8;
            margin-bottom: 30px;
        }
        .grace-period {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 30px;
        }
        .grace-period.expired {
            background: #fee2e2;
            border-color: #dc2626;
        }
        .grace-period h3 {
            color: #92400e;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .grace-period.expired h3 {
            color: #991b1b;
        }
        .grace-period p {
            color: #b45309;
            font-size: 20px;
            font-weight: bold;
        }
        .grace-period.expired p {
            color: #dc2626;
        }
        .contact-box {
            background: #f3f4f6;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
        }
        .contact-box h3 {
            color: #374151;
            font-size: 16px;
            margin-bottom: 15px;
        }
        .contact-item {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: 10px 0;
            color: #4b5563;
            font-size: 15px;
        }
        .contact-item svg {
            width: 20px;
            height: 20px;
            color: #6366f1;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 40px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        .license-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 13px;
            color: #9ca3af;
        }
        .license-info span {
            display: block;
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>

        <h1>لایسنس منقضی شده است</h1>

        <p class="message">
            متأسفانه لایسنس نرم‌افزار شما به پایان رسیده است.
            <br>
            برای ادامه استفاده از نرم‌افزار، لطفاً لایسنس خود را تمدید کنید.
        </p>

        @if(isset($graceRemaining) && $graceRemaining > 0)
            <div class="grace-period">
                <h3>مهلت تمدید (Grace Period)</h3>
                <p>{{ $graceRemaining }} ساعت باقیمانده</p>
            </div>
        @else
            <div class="grace-period expired">
                <h3>مهلت تمدید به پایان رسید</h3>
                <p>دسترسی مسدود شده است</p>
            </div>
        @endif

        <div class="contact-box">
            <h3>برای تمدید لایسنس تماس بگیرید:</h3>

            <div class="contact-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
                <span>{{ config('erp-agent.support.phone', '021-12345678') }}</span>
            </div>

            <div class="contact-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                <span>{{ config('erp-agent.support.email', 'support@example.com') }}</span>
            </div>
        </div>

        <a href="{{ config('erp-agent.support.url', '#') }}" class="btn">
            درخواست تمدید لایسنس
        </a>

        <div class="license-info">
            <span>شناسه Instance: {{ config('erp-agent.instance.id', 'N/A') }}</span>
            <span>کلید لایسنس: {{ Str::mask(config('erp-agent.license.key', 'N/A'), '*', 4, -4) }}</span>
        </div>
    </div>
</body>
</html>
